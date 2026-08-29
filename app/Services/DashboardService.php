<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Support\CrmCache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Dashboard tiles. Cached per-user because "my open deals" and "my overdue
     * tasks" differ per caller; a write to any CRM record drops the whole tag.
     */
    public function summary(int $userId): array
    {
        return CrmCache::remember(
            [CrmCache::TAG_DASHBOARD],
            "dashboard:summary:{$userId}",
            CrmCache::TTL_STATS,
            fn (): array => [
                'totals' => $this->totals(),
                'my' => $this->personal($userId),
                'pipeline' => $this->pipelineBreakdown(),
                'recent_won' => $this->recentWon(),
            ],
        );
    }

    protected function totals(): array
    {
        $openDeals = Deal::query()->open();

        return [
            'contacts' => Contact::query()->count(),
            'companies' => Company::query()->count(),
            'open_deals' => (clone $openDeals)->count(),
            'open_deal_value' => (float) (clone $openDeals)->sum('amount'),
            'won_deal_value_this_month' => (float) Deal::query()
                ->won()
                ->where('closed_at', '>=', now()->startOfMonth())
                ->sum('amount'),
            'activities_due_today' => Activity::query()
                ->where('status', Activity::STATUS_PLANNED)
                ->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])
                ->count(),
        ];
    }

    protected function personal(int $userId): array
    {
        return [
            'open_deals' => Deal::query()->open()->where('owner_id', $userId)->count(),
            'open_deal_value' => (float) Deal::query()->open()->where('owner_id', $userId)->sum('amount'),
            'overdue_activities' => Activity::query()->overdue()->where('owner_id', $userId)->count(),
            'contacts' => Contact::query()->where('owner_id', $userId)->count(),
        ];
    }

    /** Deal count and value per stage, for the pipeline funnel widget. */
    protected function pipelineBreakdown(): array
    {
        $pipeline = Pipeline::query()->with('stages')->where('is_default', true)->first()
            ?? Pipeline::query()->with('stages')->orderBy('position')->first();

        if ($pipeline === null) {
            return [];
        }

        $byStage = Deal::query()
            ->where('pipeline_id', $pipeline->id)
            ->where('status', Deal::STATUS_OPEN)
            ->groupBy('pipeline_stage_id')
            ->select('pipeline_stage_id', DB::raw('COUNT(*) as deals'), DB::raw('SUM(amount) as value'))
            ->get()
            ->keyBy('pipeline_stage_id');

        return $pipeline->stages->map(fn ($stage): array => [
            'stage_id' => $stage->id,
            'name' => $stage->name,
            'color' => $stage->color,
            'type' => $stage->type,
            'deals' => (int) ($byStage->get($stage->id)?->deals ?? 0),
            'value' => (float) ($byStage->get($stage->id)?->value ?? 0),
        ])->all();
    }

    protected function recentWon(): array
    {
        return Deal::query()
            ->won()
            ->with('owner:id,name')
            ->orderByDesc('closed_at')
            ->limit(5)
            ->get(['id', 'name', 'amount', 'currency', 'closed_at', 'owner_id'])
            ->map(fn (Deal $deal): array => [
                'id' => $deal->id,
                'name' => $deal->name,
                'amount' => (float) $deal->amount,
                'currency' => $deal->currency,
                'closed_at' => $deal->closed_at?->toIso8601String(),
                'owner' => $deal->owner?->name,
            ])->all();
    }
}
