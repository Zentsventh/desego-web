<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\ChatbotConversations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use function App\Support\Desego\current_user;

class ChatbotHistoryController extends Controller
{
    public function show(Request $request, string $conversation): JsonResponse
    {
        abort_unless(in_array('support.view', current_user()['permissions'] ?? [], true), 403);
        $record = DB::table('chatbot_conversations as c')
            ->leftJoin('customers as customer', 'customer.id', '=', 'c.customer_id')
            ->leftJoin('chatbot_visitors as visitor', 'visitor.owner_hash', '=', 'c.owner_hash')
            ->leftJoin('users as staff', 'staff.id', '=', 'c.assigned_staff_id')
            ->where('c.id', $conversation)
            ->first([
                'c.id', 'c.created_at', 'c.updated_at', 'c.archived_at', 'c.owner_hash', 'c.customer_id',
                'c.support_status', 'c.handoff_reason', 'c.assigned_staff_id',
                'customer.full_name as customer_name', 'visitor.id as visitor_number', 'staff.name as assigned_staff_name',
            ]);
        abort_unless($record, 404);
        $validated = $request->validate([
            'before' => 'nullable|integer|min:1',
            'after' => 'nullable|integer|min:0',
        ]);
        $messages = DB::table('chatbot_messages as m')
            ->join('chatbot_conversations as c', 'c.id', '=', 'm.conversation_id')
            ->when($record->customer_id !== null,
                fn ($query) => $query->where('c.customer_id', $record->customer_id),
                fn ($query) => $query->where('c.owner_hash', $record->owner_hash),
            )
            ->when(isset($validated['after']), fn ($query) => $query->where('m.id', '>', $validated['after']))
            ->when(! isset($validated['after']) && isset($validated['before']), fn ($query) => $query->where('m.id', '<', $validated['before']))
            ->orderBy('m.id', isset($validated['after']) ? 'asc' : 'desc')
            ->limit(101)->get(['m.id', 'm.role', 'm.content', 'm.source', 'm.model', 'm.is_error', 'm.delivered_at', 'm.read_at', 'm.created_at']);
        $staffReceipts = DB::table('chatbot_messages as m')
            ->join('chatbot_conversations as c', 'c.id', '=', 'm.conversation_id')
            ->when($record->customer_id !== null,
                fn ($query) => $query->where('c.customer_id', $record->customer_id),
                fn ($query) => $query->where('c.owner_hash', $record->owner_hash),
            )
            ->where('m.role', 'assistant')
            ->where('m.source', 'staff')
            ->orderByDesc('m.id')
            ->limit(100)
            ->get(['m.id', 'm.delivered_at', 'm.read_at']);

        return response()->json([
            'conversation' => [
                'id' => $record->id,
                'customer_name' => $record->customer_name ?: 'v'.str_pad((string) $record->visitor_number, 7, '0', STR_PAD_LEFT),
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
                'archived_at' => $record->archived_at,
                'support_status' => $record->support_status,
                'handoff_reason' => $record->handoff_reason,
                'assigned_staff_id' => $record->assigned_staff_id === null ? null : (int) $record->assigned_staff_id,
                'assigned_staff_name' => $record->assigned_staff_name,
                'current_staff_id' => (int) (current_user()['id'] ?? 0),
            ],
            'messages' => isset($validated['after'])
                ? $messages->take(100)->values()
                : $messages->take(100)->reverse()->values(),
            'staff_receipts' => $staffReceipts,
            'has_more' => $messages->count() > 100,
        ]);
    }

    public function close(string $conversation, ChatbotConversations $conversations): JsonResponse
    {
        abort_unless(in_array('support.view', current_user()['permissions'] ?? [], true), 403);
        $conversations->setArchived($conversation, true);

        return response()->json(['ok' => true]);
    }

    public function reopen(string $conversation, ChatbotConversations $conversations): JsonResponse
    {
        abort_unless(in_array('support.view', current_user()['permissions'] ?? [], true), 403);
        $conversations->setArchived($conversation, false);

        return response()->json(['ok' => true]);
    }

    public function takeOver(string $conversation, ChatbotConversations $conversations): JsonResponse
    {
        $staffId = $this->staffId();
        $conversations->takeOver($conversation, $staffId);

        return response()->json(['ok' => true, 'support_status' => 'human', 'assigned_staff_id' => $staffId]);
    }

    public function handoff(string $conversation, ChatbotConversations $conversations): JsonResponse
    {
        $this->staffId();
        $conversations->handoffToStaff($conversation);

        return response()->json(['ok' => true, 'support_status' => 'requested']);
    }

    public function returnToAi(string $conversation, ChatbotConversations $conversations): JsonResponse
    {
        $this->staffId();
        $conversations->returnToAi($conversation);

        return response()->json(['ok' => true, 'support_status' => 'ai']);
    }

    public function reply(Request $request, string $conversation, ChatbotConversations $conversations): JsonResponse
    {
        $staffId = $this->staffId();
        $validated = $request->validate(['message' => 'required|string|max:2000']);

        return response()->json([
            'ok' => true,
            'message' => $conversations->sendStaffReply($conversation, $staffId, $validated['message']),
        ]);
    }

    public function markRead(Request $request, string $conversation, ChatbotConversations $conversations): JsonResponse
    {
        $this->staffId();
        $validated = $request->validate(['message_id' => 'required|integer|min:1']);
        $conversations->markReadByStaff($conversation, $validated['message_id']);

        return response()->json(['ok' => true]);
    }

    private function staffId(): int
    {
        $user = current_user();
        abort_unless(in_array('support.view', $user['permissions'] ?? [], true), 403);

        return (int) $user['id'];
    }
}
