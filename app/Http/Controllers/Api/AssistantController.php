<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssistantChatRequest;
use App\Http\Resources\AiConversationResource;
use App\Http\Resources\AiMessageResource;
use App\Models\AiConversation;
use App\Services\Ai\AssistantException;
use App\Services\Ai\CrmAssistant;
use App\Services\Ai\OpenAiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class AssistantController extends Controller
{
    public function __construct(private readonly OpenAiClient $client) {}

    /** Lets the UI hide the assistant entirely when it isn't set up. */
    public function status(): JsonResponse
    {
        return response()->json(['data' => [
            'enabled' => (bool) config('ai.enabled'),
            'configured' => $this->client->configured(),
            'auth' => $this->client->configured() ? $this->client->authMethod() : null,
            'model' => $this->client->configured() ? $this->client->model() : null,
            'redacts_pii' => (bool) config('ai.redact_pii'),
        ]]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $conversations = AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_message_at')
            ->limit(20)
            ->get();

        return AiConversationResource::collection($conversations);
    }

    public function show(Request $request, AiConversation $conversation): AiConversationResource
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        return AiConversationResource::make($conversation->load('messages'));
    }

    public function chat(AssistantChatRequest $request, CrmAssistant $assistant): JsonResponse
    {
        if (! config('ai.enabled') || ! $this->client->configured()) {
            return response()->json([
                'message' => 'The assistant is not enabled on this installation.',
            ], 503);
        }

        $user = $request->user();
        $question = $request->string('message')->trim()->value();

        $conversation = $request->filled('conversation_id')
            ? AiConversation::where('user_id', $user->id)->findOrFail($request->integer('conversation_id'))
            : AiConversation::create([
                'user_id' => $user->id,
                'model' => $this->client->model(),
                // The opening question makes a good enough title.
                'title' => Str::limit($question, 60),
            ]);

        try {
            $answer = $assistant->ask($user, $conversation, $question);
        } catch (AssistantException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        activity('assistant')
            ->causedBy($user)
            ->performedOn($conversation)
            ->withProperties(['tools' => collect($answer->tool_calls ?? [])->pluck('name')->all()])
            ->log('Asked the CRM assistant');

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->id,
                'message' => AiMessageResource::make($answer)->resolve(),
            ],
        ]);
    }

    public function destroy(Request $request, AiConversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        $conversation->delete();

        return response()->json(['message' => 'Conversation deleted.']);
    }
}
