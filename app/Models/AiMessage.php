<?php

namespace App\Models;

use App\Models\Concerns\Blameable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One turn of an assistant conversation.
 *
 * Deliberately not Auditable: a transcript is already an append-only record, so
 * an activity_log row per message would double the writes for no audit value.
 * Soft deletes and blame stamps are kept for consistency with the rest of the
 * schema.
 */
class AiMessage extends Model
{
    use Blameable;
    use HasFactory;
    use SoftDeletes;

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    protected $fillable = [
        'ai_conversation_id', 'role', 'content', 'tool_calls', 'citations', 'usage',
    ];

    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
            'citations' => 'array',
            'usage' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
