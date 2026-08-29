<?php

namespace App\Services\Ai;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The read-only surface the assistant is allowed to touch.
 *
 * Two rules hold for every tool here:
 *
 *  1. Authorization is checked in PHP against the *calling user*, never
 *     delegated to the prompt. A model cannot talk its way past Gate::denies().
 *  2. Results are compact projections, not models. That bounds token cost and
 *     means fields nobody asked for never leave the database.
 *
 * Records the tools touch are recorded as citations so the UI can link the
 * answer back to the real rows.
 */
class CrmToolkit
{
    /** @var array<int, array{type: string, id: int, label: string}> */
    private array $citations = [];

    public function __construct(private readonly User $user) {}

    /** @return array<int, array<string, mixed>> */
    public function definitions(): array
    {
        $limit = ['type' => 'integer', 'description' => 'Maximum rows to return.', 'minimum' => 1, 'maximum' => config('ai.max_rows_per_tool')];

        return [
            $this->tool('whoami', 'Who the current user is, their roles, and what they own. Call this first for questions using "my", "me" or "mine".', []),

            $this->tool('search_contacts', 'Find contacts by name, email, phone, company or lifecycle stage.', [
                'query' => ['type' => ['string', 'null'], 'description' => 'Free text matched against name, email and phone.'],
                'lifecycle_stage' => ['type' => ['string', 'null'], 'enum' => [...Contact::LIFECYCLE_STAGES, null]],
                'owner_name' => ['type' => ['string', 'null'], 'description' => 'Restrict to contacts owned by this person.'],
                'limit' => [...$limit, 'type' => ['integer', 'null']],
            ]),

            $this->tool('get_contact', 'Full detail for one contact: company, owner, deals and recent activity.', [
                'contact_id' => ['type' => 'integer', 'description' => 'The contact id, from search_contacts.'],
            ], ['contact_id']),

            $this->tool('search_companies', 'Find companies by name, domain or industry.', [
                'query' => ['type' => ['string', 'null']],
                'industry' => ['type' => ['string', 'null']],
                'limit' => [...$limit, 'type' => ['integer', 'null']],
            ]),

            $this->tool('get_company', 'Full detail for one company, including its contacts and deals.', [
                'company_id' => ['type' => 'integer'],
            ], ['company_id']),

            $this->tool('search_deals', 'Find deals by name, status, stage or owner.', [
                'query' => ['type' => ['string', 'null']],
                'status' => ['type' => ['string', 'null'], 'enum' => ['open', 'won', 'lost', null]],
                'stage_name' => ['type' => ['string', 'null']],
                'owner_name' => ['type' => ['string', 'null']],
                'min_amount' => ['type' => ['number', 'null']],
                'closing_before' => ['type' => ['string', 'null'], 'description' => 'ISO date; deals expected to close on or before it.'],
                'limit' => [...$limit, 'type' => ['integer', 'null']],
            ]),

            $this->tool('get_deal', 'Full detail for one deal, including stage history notes.', [
                'deal_id' => ['type' => 'integer'],
            ], ['deal_id']),

            $this->tool('list_activities', 'Calls, emails, meetings, notes and tasks — optionally for one record.', [
                'related_type' => ['type' => ['string', 'null'], 'enum' => ['contact', 'company', 'deal', null]],
                'related_id' => ['type' => ['integer', 'null']],
                'status' => ['type' => ['string', 'null'], 'enum' => ['planned', 'completed', 'canceled', null]],
                'overdue_only' => ['type' => ['boolean', 'null']],
                'owner_name' => ['type' => ['string', 'null']],
                'limit' => [...$limit, 'type' => ['integer', 'null']],
            ]),

            $this->tool('pipeline_summary', 'Deal count and total value per stage, for forecasting questions.', [
                'pipeline_name' => ['type' => ['string', 'null'], 'description' => 'Defaults to the default pipeline.'],
            ]),
        ];
    }

    /**
     * Run one tool. Returns a JSON-encodable array; failures come back as an
     * `error` key so the model can recover rather than the turn dying.
     *
     * @return array<string, mixed>
     */
    public function call(string $name, array $arguments): array
    {
        return match ($name) {
            'whoami' => $this->whoami(),
            'search_contacts' => $this->searchContacts($arguments),
            'get_contact' => $this->getContact($arguments),
            'search_companies' => $this->searchCompanies($arguments),
            'get_company' => $this->getCompany($arguments),
            'search_deals' => $this->searchDeals($arguments),
            'get_deal' => $this->getDeal($arguments),
            'list_activities' => $this->listActivities($arguments),
            'pipeline_summary' => $this->pipelineSummary($arguments),
            default => ['error' => "Unknown tool: {$name}"],
        };
    }

    /** @return array<int, array{type: string, id: int, label: string}> */
    public function citations(): array
    {
        return array_values($this->citations);
    }

    // -- tools ---------------------------------------------------------------

    private function whoami(): array
    {
        return [
            'name' => $this->user->name,
            'email' => $this->redact($this->user->email, 'email'),
            'job_title' => $this->user->job_title,
            'roles' => $this->user->getRoleNames()->all(),
            'owns' => [
                'contacts' => $this->user->ownedContacts()->count(),
                'companies' => $this->user->ownedCompanies()->count(),
                'open_deals' => $this->user->ownedDeals()->where('status', 'open')->count(),
                'overdue_activities' => Activity::query()->overdue()->where('owner_id', $this->user->id)->count(),
            ],
            'today' => now()->toDateString(),
        ];
    }

    private function searchContacts(array $args): array
    {
        if ($denied = $this->denied('contacts.view', Contact::class)) {
            return $denied;
        }

        $rows = Contact::query()
            ->with(['company:id,name', 'owner:id,name'])
            ->when($args['query'] ?? null, fn (Builder $q, $term) => $q->where(fn (Builder $i) => $i
                ->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")))
            ->when($args['lifecycle_stage'] ?? null, fn (Builder $q, $s) => $q->where('lifecycle_stage', $s))
            ->when($args['owner_name'] ?? null, fn (Builder $q, $n) => $q->whereHas('owner', fn (Builder $o) => $o->where('name', 'like', "%{$n}%")))
            ->orderByDesc('lead_score')
            ->limit($this->limit($args))
            ->get();

        return ['contacts' => $rows->map(fn (Contact $c) => $this->contactSummary($c))->all()];
    }

    private function getContact(array $args): array
    {
        if ($denied = $this->denied('contacts.view', Contact::class)) {
            return $denied;
        }

        $contact = Contact::query()
            ->with(['company:id,name,domain,industry', 'owner:id,name', 'deals:id,name,amount,currency,status', 'tags:id,name'])
            ->find($args['contact_id'] ?? 0);

        if ($contact === null) {
            return ['error' => 'No contact with that id.'];
        }

        return ['contact' => [
            ...$this->contactSummary($contact),
            'notes' => $this->untrusted($contact->notes),
            'source' => $contact->source,
            'location' => collect([$contact->city, $contact->state, $contact->country])->filter()->join(', ') ?: null,
            'last_contacted_at' => $contact->last_contacted_at?->toDateString(),
            'tags' => $contact->tags->pluck('name')->all(),
            'deals' => $contact->deals->map(fn (Deal $d) => [
                'id' => $d->id, 'name' => $d->name, 'amount' => (float) $d->amount, 'status' => $d->status,
            ])->all(),
            'recent_activities' => $this->activitiesFor('contact', $contact->id, 5),
        ]];
    }

    private function searchCompanies(array $args): array
    {
        if ($denied = $this->denied('companies.view', Company::class)) {
            return $denied;
        }

        $rows = Company::query()
            ->with('owner:id,name')
            ->withCount(['contacts', 'deals'])
            ->when($args['query'] ?? null, fn (Builder $q, $term) => $q->where(fn (Builder $i) => $i
                ->where('name', 'like', "%{$term}%")
                ->orWhere('domain', 'like', "%{$term}%")))
            ->when($args['industry'] ?? null, fn (Builder $q, $i) => $q->where('industry', 'like', "%{$i}%"))
            ->orderBy('name')
            ->limit($this->limit($args))
            ->get();

        return ['companies' => $rows->map(fn (Company $c) => $this->companySummary($c))->all()];
    }

    private function getCompany(array $args): array
    {
        if ($denied = $this->denied('companies.view', Company::class)) {
            return $denied;
        }

        $company = Company::query()
            ->with(['owner:id,name', 'contacts:id,company_id,first_name,last_name,job_title,lifecycle_stage', 'deals:id,company_id,name,amount,currency,status'])
            ->withCount(['contacts', 'deals'])
            ->find($args['company_id'] ?? 0);

        if ($company === null) {
            return ['error' => 'No company with that id.'];
        }

        return ['company' => [
            ...$this->companySummary($company),
            'description' => $this->untrusted($company->description),
            'size' => $company->size,
            'annual_revenue' => $company->annual_revenue,
            'contacts' => $company->contacts->map(fn (Contact $c) => [
                'id' => $c->id, 'name' => $c->full_name, 'job_title' => $c->job_title, 'lifecycle_stage' => $c->lifecycle_stage,
            ])->all(),
            'deals' => $company->deals->map(fn (Deal $d) => [
                'id' => $d->id, 'name' => $d->name, 'amount' => (float) $d->amount, 'status' => $d->status,
            ])->all(),
        ]];
    }

    private function searchDeals(array $args): array
    {
        if ($denied = $this->denied('deals.view', Deal::class)) {
            return $denied;
        }

        $rows = Deal::query()
            ->with(['stage:id,name', 'company:id,name', 'owner:id,name'])
            ->when($args['query'] ?? null, fn (Builder $q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->when($args['status'] ?? null, fn (Builder $q, $s) => $q->where('status', $s))
            ->when($args['stage_name'] ?? null, fn (Builder $q, $n) => $q->whereHas('stage', fn (Builder $s) => $s->where('name', 'like', "%{$n}%")))
            ->when($args['owner_name'] ?? null, fn (Builder $q, $n) => $q->whereHas('owner', fn (Builder $o) => $o->where('name', 'like', "%{$n}%")))
            ->when($args['min_amount'] ?? null, fn (Builder $q, $a) => $q->where('amount', '>=', $a))
            ->when($args['closing_before'] ?? null, fn (Builder $q, $d) => $q->whereDate('expected_close_date', '<=', $d))
            ->orderByDesc('amount')
            ->limit($this->limit($args))
            ->get();

        return ['deals' => $rows->map(fn (Deal $d) => $this->dealSummary($d))->all()];
    }

    private function getDeal(array $args): array
    {
        if ($denied = $this->denied('deals.view', Deal::class)) {
            return $denied;
        }

        $deal = Deal::query()
            ->with(['stage:id,name,probability', 'pipeline:id,name', 'company:id,name', 'primaryContact:id,first_name,last_name', 'owner:id,name'])
            ->find($args['deal_id'] ?? 0);

        if ($deal === null) {
            return ['error' => 'No deal with that id.'];
        }

        return ['deal' => [
            ...$this->dealSummary($deal),
            'pipeline' => $deal->pipeline?->name,
            'probability' => $deal->probability,
            'description' => $this->untrusted($deal->description),
            'source' => $deal->source,
            'won_reason' => $deal->won_reason,
            'lost_reason' => $deal->lost_reason,
            'closed_at' => $deal->closed_at?->toDateString(),
            'primary_contact' => $deal->primaryContact?->full_name,
            'recent_activities' => $this->activitiesFor('deal', $deal->id, 8),
        ]];
    }

    private function listActivities(array $args): array
    {
        if ($denied = $this->denied('activities.view', Activity::class)) {
            return $denied;
        }

        $rows = Activity::query()
            ->with('owner:id,name')
            ->when($args['related_type'] ?? null, fn (Builder $q, $t) => $q->where('related_type', $t))
            ->when($args['related_id'] ?? null, fn (Builder $q, $i) => $q->where('related_id', $i))
            ->when($args['status'] ?? null, fn (Builder $q, $s) => $q->where('status', $s))
            ->when($args['overdue_only'] ?? false, fn (Builder $q) => $q->overdue())
            ->when($args['owner_name'] ?? null, fn (Builder $q, $n) => $q->whereHas('owner', fn (Builder $o) => $o->where('name', 'like', "%{$n}%")))
            ->orderByDesc('created_at')
            ->limit($this->limit($args))
            ->get();

        return ['activities' => $rows->map(fn (Activity $a) => [
            'id' => $a->id,
            'type' => $a->type,
            'subject' => $this->untrusted($a->subject),
            'body' => $this->untrusted($a->body),
            'status' => $a->status,
            'due_at' => $a->due_at?->toDateTimeString(),
            'owner' => $a->owner?->name,
            'related' => $a->related_type ? "{$a->related_type}#{$a->related_id}" : null,
        ])->all()];
    }

    private function pipelineSummary(array $args): array
    {
        if ($denied = $this->denied('deals.view', Deal::class)) {
            return $denied;
        }

        $pipeline = Pipeline::query()
            ->with('stages')
            ->when($args['pipeline_name'] ?? null, fn (Builder $q, $n) => $q->where('name', 'like', "%{$n}%"))
            ->when(! ($args['pipeline_name'] ?? null), fn (Builder $q) => $q->orderByDesc('is_default'))
            ->first();

        if ($pipeline === null) {
            return ['error' => 'No pipeline found.'];
        }

        $byStage = Deal::query()
            ->where('pipeline_id', $pipeline->id)
            ->where('status', 'open')
            ->groupBy('pipeline_stage_id')
            ->select('pipeline_stage_id', DB::raw('COUNT(*) as deals'), DB::raw('SUM(amount) as value'))
            ->get()
            ->keyBy('pipeline_stage_id');

        return [
            'pipeline' => $pipeline->name,
            'currency' => 'USD',
            'stages' => $pipeline->stages->map(fn ($stage) => [
                'name' => $stage->name,
                'type' => $stage->type,
                'open_deals' => (int) ($byStage->get($stage->id)?->deals ?? 0),
                'open_value' => (float) ($byStage->get($stage->id)?->value ?? 0),
            ])->all(),
        ];
    }

    // -- helpers -------------------------------------------------------------

    private function tool(string $name, string $description, array $properties, array $required = []): array
    {
        return [
            'type' => 'function',
            'name' => $name,
            'description' => $description,
            'parameters' => [
                'type' => 'object',
                'properties' => (object) $properties,
                'required' => array_values($required),
                'additionalProperties' => false,
            ],
        ];
    }

    /** Permission check against the calling user, plus the model-level policy. */
    private function denied(string $permission, string $model): ?array
    {
        if (! $this->user->is_active) {
            return ['error' => 'This account is deactivated.'];
        }

        if (Gate::forUser($this->user)->denies('viewAny', $model) || ! $this->user->can($permission)) {
            return ['error' => 'You do not have permission to read that in the CRM.'];
        }

        return null;
    }

    private function limit(array $args): int
    {
        $max = (int) config('ai.max_rows_per_tool');

        return min(max((int) ($args['limit'] ?? $max), 1), $max);
    }

    private function contactSummary(Contact $contact): array
    {
        $this->cite('contact', $contact->id, $contact->full_name);

        return [
            'id' => $contact->id,
            'name' => $contact->full_name,
            'email' => $this->redact($contact->email, 'email'),
            'phone' => $this->redact($contact->phone, 'phone'),
            'job_title' => $contact->job_title,
            'company' => $contact->company?->name,
            'company_id' => $contact->company_id,
            'lifecycle_stage' => $contact->lifecycle_stage,
            'lead_status' => $contact->lead_status,
            'lead_score' => $contact->lead_score,
            'owner' => $contact->owner?->name,
        ];
    }

    private function companySummary(Company $company): array
    {
        $this->cite('company', $company->id, $company->name);

        return [
            'id' => $company->id,
            'name' => $company->name,
            'domain' => $company->domain,
            'industry' => $company->industry,
            'owner' => $company->owner?->name,
            'contacts_count' => $company->contacts_count ?? null,
            'deals_count' => $company->deals_count ?? null,
        ];
    }

    private function dealSummary(Deal $deal): array
    {
        $this->cite('deal', $deal->id, $deal->name);

        return [
            'id' => $deal->id,
            'name' => $deal->name,
            'amount' => (float) $deal->amount,
            'currency' => $deal->currency,
            'status' => $deal->status,
            'stage' => $deal->stage?->name,
            'company' => $deal->company?->name,
            'owner' => $deal->owner?->name,
            'expected_close_date' => $deal->expected_close_date?->toDateString(),
        ];
    }

    private function activitiesFor(string $type, int $id, int $limit): array
    {
        return Activity::query()
            ->where('related_type', $type)
            ->where('related_id', $id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Activity $a) => [
                'type' => $a->type,
                'subject' => $this->untrusted($a->subject),
                'status' => $a->status,
                'due_at' => $a->due_at?->toDateString(),
            ])->all();
    }

    private function cite(string $type, int $id, string $label): void
    {
        $this->citations["{$type}:{$id}"] = ['type' => $type, 'id' => $id, 'label' => $label];
    }

    private function redact(?string $value, string $kind): ?string
    {
        if ($value === null || $value === '' || ! config('ai.redact_pii')) {
            return $value;
        }

        return match ($kind) {
            'email' => preg_replace('/^(.).*(@.*)$/u', '$1•••$2', $value),
            'phone' => preg_replace('/\d(?=\d{2})/u', '•', $value),
            default => $value,
        };
    }

    /**
     * Free text written by CRM users. It is data, never instructions — the
     * system prompt says so, and we fence it so an injected "ignore previous
     * instructions" is visibly quoted content rather than a bare directive.
     */
    private function untrusted(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return '<<user_content>>'.mb_substr($value, 0, 2000).'<</user_content>>';
    }
}
