<?php

namespace App\Http\Controllers\Api;

use App\Ai\Agents\InstructorAssistantAgent;
use App\Ai\Tools\SearchKnowledgeBase;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Models\Conversation;
use Throwable;

/**
 * Conversational KURSA instructor assistant (RAG over the platform knowledge
 * base — see InstructorAssistantAgent / SearchKnowledgeBase).
 */
class InstructorAssistantController extends Controller
{
    /**
     * Send a message to the assistant and get a reply, starting a new
     * conversation or continuing an existing one owned by the caller.
     */
    public function message(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|string|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $user = $request->user();

        try {
            $agent = InstructorAssistantAgent::make();

            if ($conversationId = $data['conversation_id'] ?? null) {
                $conversation = Conversation::where('id', $conversationId)
                    ->where('user_id', $user->id)
                    ->first();

                if (! $conversation) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Conversation not found.',
                    ], 404);
                }

                $agent->continue($conversationId, $user);
            } else {
                $agent->forUser($user);
            }

            // Pre-retrieval RAG: fetch the relevant knowledge-base passages
            // ourselves and hand them to the model as context. The small local
            // model answers well from context but cannot do agentic tool calls.
            $context = SearchKnowledgeBase::search($data['message']);

            $prompt = "### Contexte (extraits de la base de connaissances KURSA) :\n"
                .$context
                ."\n\n### Question de l'instructeur :\n"
                .$data['message'];

            $response = $agent->prompt($prompt);

            return response()->json([
                'success' => true,
                'data' => [
                    'reply' => $response->text,
                    'conversation_id' => $response->conversationId,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('InstructorAssistantController: assistant failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Assistant unavailable',
            ], 503);
        }
    }
}
