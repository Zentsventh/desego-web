<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\ChatbotConversations;
use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    public function message(Request $request, ChatbotService $chatbot, ChatbotConversations $conversations): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:600',
            'conversation_id' => 'nullable|uuid',
        ]);

        $id = $validated['conversation_id'] ?? null;
        if ($id !== null) {
            $conversation = $conversations->findOwned($request, $id);
            if ($conversation->archived_at !== null) {
                $id = null;
            }
        } else {
            $id = $conversations->activeConversationId($request);
        }
        $history = $id !== null ? $conversations->history($id) : [];
        $turn = $conversations->receiveCustomerMessage(
            $request,
            $id,
            $validated['message'],
            $chatbot->handoffReasonForMessage($validated['message'], $history),
        );

        if ($turn['status'] !== 'ai') {
            return response()->json([
                'ok' => true,
                'reply' => $turn['reply'],
                'conversation_id' => $turn['id'],
                'handoff_status' => $turn['status'],
                'last_message_id' => $conversations->updates($request, $turn['id'], $turn['message_id'])['last_message_id'],
            ]);
        }

        $history = $conversations->history($turn['id'], $turn['message_id']);
        $reply = $chatbot->reply($validated['message'], $history);
        if ($reply === null) {
            $message = 'Ahora no puedo resolver tu consulta con seguridad. Un asesor la revisará y te responderá por este chat.';
            $handoff = $conversations->requestHandoff($turn['id'], 'ai_unavailable', $message);

            return response()->json([
                'ok' => true,
                'reply' => $message,
                'conversation_id' => $turn['id'],
                'handoff_status' => $handoff['status'],
                'last_message_id' => $handoff['last_message_id'],
            ]);
        }

        if ($chatbot->replyNeedsHandoff($reply['reply'])) {
            $handoff = $conversations->requestHandoff($turn['id'], 'ai_unresolved', $reply['reply']);

            return response()->json([
                'ok' => true,
                'reply' => $reply['reply'],
                'conversation_id' => $turn['id'],
                'handoff_status' => $handoff['status'],
                'last_message_id' => $handoff['last_message_id'],
            ]);
        }

        $messageId = $conversations->addAssistantReply($turn['id'], $reply);
        if ($messageId === null) {
            $updates = $conversations->updates($request, $turn['id']);

            return response()->json([
                'ok' => true,
                'reply' => null,
                'conversation_id' => $turn['id'],
                'handoff_status' => $updates['status'],
                'last_message_id' => $updates['last_message_id'],
            ]);
        }

        return response()->json([
            'ok' => true,
            'reply' => $reply['reply'],
            'conversation_id' => $turn['id'],
            'handoff_status' => 'ai',
            'last_message_id' => $messageId,
        ]);
    }

    public function updates(Request $request, string $conversation, ChatbotConversations $conversations): JsonResponse
    {
        $validated = $request->validate(['after' => 'nullable|integer|min:0']);
        $updates = $conversations->updates($request, $conversation, $validated['after'] ?? 0);
        unset($updates['handoff_reason'], $updates['assigned_staff_id']);

        return response()->json([
            'ok' => true,
            ...$updates,
        ]);
    }

    public function markRead(Request $request, string $conversation, ChatbotConversations $conversations): JsonResponse
    {
        $validated = $request->validate(['message_id' => 'required|integer|min:1']);
        $conversations->markReadByCustomer($request, $conversation, $validated['message_id']);

        return response()->json(['ok' => true]);
    }
}
