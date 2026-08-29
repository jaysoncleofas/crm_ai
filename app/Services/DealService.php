<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Deal;
use App\Models\PipelineStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DealService
{
    /**
     * Move a deal to a stage, keeping the derived fields honest.
     *
     * A won/lost stage closes the deal and stamps closed_at; moving back to an
     * open stage reopens it. Deal + activity are written in one transaction so
     * the timeline can never disagree with the record.
     */
    public function moveToStage(Deal $deal, PipelineStage $stage, ?string $reason = null): Deal
    {
        if ($stage->pipeline_id !== $deal->pipeline_id) {
            throw ValidationException::withMessages([
                'pipeline_stage_id' => 'The selected stage does not belong to this deal\'s pipeline.',
            ]);
        }

        if ($deal->pipeline_stage_id === $stage->id) {
            return $deal;
        }

        return DB::transaction(function () use ($deal, $stage, $reason): Deal {
            $from = $deal->stage()->first();

            $deal->pipeline_stage_id = $stage->id;
            $deal->probability = $stage->probability;

            $deal->status = match ($stage->type) {
                PipelineStage::TYPE_WON => Deal::STATUS_WON,
                PipelineStage::TYPE_LOST => Deal::STATUS_LOST,
                default => Deal::STATUS_OPEN,
            };

            if ($stage->isClosed()) {
                $deal->closed_at = now();
                $deal->won_reason = $stage->type === PipelineStage::TYPE_WON ? $reason : null;
                $deal->lost_reason = $stage->type === PipelineStage::TYPE_LOST ? $reason : null;
            } else {
                $deal->closed_at = null;
                $deal->won_reason = null;
                $deal->lost_reason = null;
            }

            $deal->save();

            Activity::create([
                'type' => 'note',
                'subject' => sprintf('Stage changed: %s → %s', $from?->name ?? 'unassigned', $stage->name),
                'body' => $reason,
                'status' => Activity::STATUS_COMPLETED,
                'completed_at' => now(),
                'owner_id' => auth()->id() ?? $deal->owner_id,
                'related_type' => 'deal',
                'related_id' => $deal->id,
            ]);

            return $deal->refresh();
        });
    }
}
