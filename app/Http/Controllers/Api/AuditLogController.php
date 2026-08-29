<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Activitylog\Models\Activity as AuditLog;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/** Read-only view over the Spatie activity_log audit trail. */
class AuditLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('audit-log.view'), 403);

        $logs = QueryBuilder::for(AuditLog::class)
            ->allowedFilters(
                AllowedFilter::exact('log_name'),
                AllowedFilter::exact('event'),
                AllowedFilter::exact('subject_type'),
                AllowedFilter::exact('subject_id'),
                AllowedFilter::exact('causer_id'),
            )
            ->allowedSorts('created_at', 'log_name', 'event')
            ->with('causer:id,name')
            ->defaultSort('-created_at')
            ->paginate($this->perPage())
            ->withQueryString();

        return AuditLogResource::collection($logs);
    }
}
