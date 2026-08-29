<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CompanyController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Company::class);

        $companies = QueryBuilder::for(Company::class)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $q, $value) => $q->where(
                    fn (Builder $inner) => $inner
                        ->where('name', 'like', "%{$value}%")
                        ->orWhere('domain', 'like', "%{$value}%")
                        ->orWhere('industry', 'like', "%{$value}%")
                )),
                AllowedFilter::exact('industry'),
                AllowedFilter::exact('owner_id'),
                AllowedFilter::trashed(),
            )
            ->allowedSorts('name', 'industry', 'annual_revenue', 'created_at', 'updated_at')
            ->allowedIncludes('owner', 'tags', 'creator', 'updater')
            ->with('owner:id,name')
            ->withCount(['contacts', 'deals'])
            ->defaultSort('name')
            ->paginate($this->perPage())
            ->withQueryString();

        return CompanyResource::collection($companies);
    }

    public function store(CompanyRequest $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $company = DB::transaction(function () use ($request): Company {
            $company = Company::create($request->safe()->except('tags') + [
                'owner_id' => $request->input('owner_id', $request->user()->id),
            ]);

            if ($request->has('tags')) {
                $company->syncTags($request->input('tags', []));
            }

            return $company;
        });

        return CompanyResource::make($this->loadDetail($company->refresh()))->response()->setStatusCode(201);
    }

    public function show(Company $company): CompanyResource
    {
        $this->authorize('view', $company);

        return CompanyResource::make($this->loadDetail($company));
    }

    public function update(CompanyRequest $request, Company $company): CompanyResource
    {
        $this->authorize('update', $company);

        DB::transaction(function () use ($request, $company): void {
            $company->update($request->safe()->except('tags'));

            if ($request->has('tags')) {
                $company->syncTags($request->input('tags', []));
            }
        });

        return CompanyResource::make($this->loadDetail($company->refresh()));
    }

    public function destroy(Company $company): JsonResponse
    {
        $this->authorize('delete', $company);

        $company->delete();

        return response()->json(['message' => 'Company moved to trash.']);
    }

    public function restore(int $company): CompanyResource
    {
        $record = Company::onlyTrashed()->findOrFail($company);
        $this->authorize('restore', $record);

        $record->restore();

        return CompanyResource::make($this->loadDetail($record));
    }

    protected function loadDetail(Company $company): Company
    {
        return $company->load([
            'owner:id,name',
            'tags',
            'creator:id,name',
            'updater:id,name',
            'deleter:id,name',
        ])->loadCount(['contacts', 'deals']);
    }
}
