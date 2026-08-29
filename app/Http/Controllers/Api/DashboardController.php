<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): JsonResponse
    {
        $upcoming = Activity::query()
            ->where('owner_id', $request->user()->id)
            ->where('status', Activity::STATUS_PLANNED)
            ->whereNotNull('due_at')
            ->with(['owner:id,name', 'related'])
            ->orderBy('due_at')
            ->limit(8)
            ->get();

        return response()->json([
            'data' => $dashboard->summary($request->user()->id) + [
                'upcoming_activities' => ActivityResource::collection($upcoming)->resolve(),
            ],
        ]);
    }
}
