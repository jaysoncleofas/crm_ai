<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ContactController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Contact::class);

        $contacts = QueryBuilder::for(Contact::class)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $q, $value) => $q->where(
                    fn (Builder $inner) => $inner
                        ->where('first_name', 'like', "%{$value}%")
                        ->orWhere('last_name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%")
                )),
                AllowedFilter::exact('company_id'),
                AllowedFilter::exact('owner_id'),
                AllowedFilter::exact('lifecycle_stage'),
                AllowedFilter::exact('lead_status'),
                AllowedFilter::exact('source'),
                AllowedFilter::trashed(),
            )
            ->allowedSorts('first_name', 'last_name', 'email', 'lead_score', 'created_at', 'updated_at')
            ->allowedIncludes('company', 'owner', 'tags', 'creator', 'updater')
            ->with(['company:id,name', 'owner:id,name'])
            ->withCount('activities')
            ->defaultSort('-created_at')
            ->paginate($this->perPage())
            ->withQueryString();

        return ContactResource::collection($contacts);
    }

    public function store(ContactRequest $request): JsonResponse
    {
        $this->authorize('create', Contact::class);

        $contact = DB::transaction(function () use ($request): Contact {
            $contact = Contact::create($request->safe()->except('tags') + [
                'owner_id' => $request->input('owner_id', $request->user()->id),
            ]);

            if ($request->has('tags')) {
                $contact->syncTags($request->input('tags', []));
            }

            return $contact;
        });

        // refresh() so database defaults (lifecycle_stage, lead_status, …)
        // appear in the created response rather than as nulls.
        return ContactResource::make($this->loadDetail($contact->refresh()))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Contact $contact): ContactResource
    {
        $this->authorize('view', $contact);

        return ContactResource::make($this->loadDetail($contact));
    }

    public function update(ContactRequest $request, Contact $contact): ContactResource
    {
        $this->authorize('update', $contact);

        DB::transaction(function () use ($request, $contact): void {
            $contact->update($request->safe()->except('tags'));

            if ($request->has('tags')) {
                $contact->syncTags($request->input('tags', []));
            }
        });

        return ContactResource::make($this->loadDetail($contact->refresh()));
    }

    /** Soft delete — the row keeps deleted_at and deleted_by for the audit trail. */
    public function destroy(Contact $contact): JsonResponse
    {
        $this->authorize('delete', $contact);

        $contact->delete();

        return response()->json(['message' => 'Contact moved to trash.']);
    }

    public function restore(int $contact): ContactResource
    {
        $record = Contact::onlyTrashed()->findOrFail($contact);
        $this->authorize('restore', $record);

        $record->restore();

        return ContactResource::make($this->loadDetail($record));
    }

    protected function loadDetail(Contact $contact): Contact
    {
        return $contact->load([
            'company:id,name',
            'owner:id,name',
            'deals:id,name,amount,currency,status',
            'tags',
            'creator:id,name',
            'updater:id,name',
            'deleter:id,name',
        ]);
    }
}
