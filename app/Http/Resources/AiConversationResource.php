<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'model' => $this->model,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'messages' => AiMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
