<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatbotConversations
{
    private function ownerHash(Request $request): string
    {
        if (! $request->session()->has('chatbot_owner')) {
            $request->session()->put('chatbot_owner', Str::random(64));
        }

        return hash('sha256', $request->session()->get('chatbot_owner'));
    }

    private function customerId(Request $request): ?int
    {
        $customerId = filter_var($request->session()->get('storefront_customer_id'), FILTER_VALIDATE_INT);
        if (! $customerId || $customerId < 1) {
            return null;
        }

        $activeId = DB::table('customers')->where('id', $customerId)->where('status', 'active')->value('id');

        return $activeId === null ? null : (int) $activeId;
    }

    private function visitorNumber(string $owner): int
    {
        DB::table('chatbot_visitors')->insertOrIgnore([
            'owner_hash' => $owner,
            'created_at' => now(),
        ]);

        return (int) DB::table('chatbot_visitors')->where('owner_hash', $owner)->value('id');
    }

    public function findOwned(Request $request, string $id): object
    {
        $conversation = DB::table('chatbot_conversations')->where('id', $id)->first();
        $ownerMatches = $conversation && hash_equals($conversation->owner_hash, $this->ownerHash($request));
        $customerId = $this->customerId($request);
        $customerMatches = $conversation && $customerId !== null && (int) $conversation->customer_id === $customerId;
        abort_unless($conversation && ($ownerMatches || $customerMatches), 404);

        return $conversation;
    }

    public function activeConversationId(Request $request): ?string
    {
        $owner = $this->ownerHash($request);
        $customerId = $this->customerId($request);

        $conversationId = DB::table('chatbot_conversations')
            ->whereNull('archived_at')
            ->where(function ($query) use ($owner, $customerId): void {
                $query->where('owner_hash', $owner);
                if ($customerId !== null) {
                    $query->orWhere('customer_id', $customerId);
                }
            })
            ->orderByDesc('updated_at')->orderByDesc('id')
            ->value('id');

        return is_string($conversationId) ? $conversationId : null;
    }

    /** @return array<int, array{role: string, content: string}> */
    public function history(string $id, ?int $excludeMessageId = null): array
    {
        $conversation = DB::table('chatbot_conversations')->where('id', $id)->first(['owner_hash', 'customer_id']);
        if (! $conversation) {
            return [];
        }

        return DB::table('chatbot_messages as m')
            ->join('chatbot_conversations as c', 'c.id', '=', 'm.conversation_id')
            ->whereNull('c.archived_at')
            ->when($conversation->customer_id !== null,
                fn ($query) => $query->where('c.customer_id', $conversation->customer_id),
                fn ($query) => $query->where('c.owner_hash', $conversation->owner_hash),
            )
            ->where('m.is_error', false)
            ->when($excludeMessageId !== null, fn ($query) => $query->where('m.id', '!=', $excludeMessageId))
            ->orderByDesc('m.id')->limit(10)->get(['m.role', 'm.content'])->reverse()->values()
            ->map(fn (object $message): array => ['role' => $message->role, 'content' => mb_substr($message->content, 0, 1200)])->all();
    }

    /**
     * @return array{id: string, status: string, reply: ?string, message_id: int}
     */
    public function receiveCustomerMessage(Request $request, ?string $id, string $message, ?string $handoffReason): array
    {
        $owner = $this->ownerHash($request);
        $customerId = $this->customerId($request);
        $visitorNumber = $customerId === null ? $this->visitorNumber($owner) : null;
        $customerName = $customerId === null
            ? null
            : DB::table('customers')->where('id', $customerId)->value('full_name');

        return DB::transaction(function () use ($request, $id, $message, $handoffReason, $owner, $customerId, $visitorNumber, $customerName): array {
            if ($id === null) {
                $id = $this->activeConversationId($request);
            }
            if ($id !== null && DB::table('chatbot_conversations')->where('id', $id)->value('archived_at') !== null) {
                $id = null;
            }
            if ($id === null) {
                $id = (string) Str::uuid();
                DB::table('chatbot_conversations')->insert([
                    'id' => $id,
                    'owner_hash' => $owner,
                    'user_id' => null,
                    'customer_id' => $customerId,
                    'title' => $customerName ?: 'v'.str_pad((string) $visitorNumber, 7, '0', STR_PAD_LEFT),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'archived_at' => null,
                    'support_status' => 'ai',
                    'interaction_started_at' => now(),
                ]);
            } else {
                if ($customerId !== null) {
                    DB::table('chatbot_conversations')->where('owner_hash', $owner)->whereNull('customer_id')
                        ->update(['customer_id' => $customerId, 'title' => $customerName]);
                    DB::table('chatbot_conversations')->where('id', $id)->update(['title' => $customerName]);
                }
            }

            $conversation = DB::table('chatbot_conversations')->where('id', $id)->lockForUpdate()->first();
            abort_unless($conversation && $conversation->archived_at === null, 404);
            $group = $this->identityQuery($conversation)->whereNull('archived_at');
            $status = (string) ($group->value('support_status') ?? 'ai');
            $lastCustomerMessageAt = $group->max('last_customer_message_at');
            $interactionStartedAt = $group->max('interaction_started_at');
            $now = now();

            if ($status === 'ai' && ($lastCustomerMessageAt === null || Carbon::parse($lastCustomerMessageAt)->lt($now->copy()->subMinutes(15)))) {
                $interactionStartedAt = $now;
            }

            $messageId = DB::table('chatbot_messages')->insertGetId([
                'conversation_id' => $id,
                'role' => 'user',
                'content' => $message,
                'source' => null,
                'model' => null,
                'is_error' => false,
                'created_at' => $now,
            ]);

            $this->identityQuery($conversation)->update([
                'last_customer_message_at' => $now,
                'interaction_started_at' => $interactionStartedAt,
                'updated_at' => $now,
            ]);

            $handoffReply = null;
            if ($status === 'ai' && $handoffReason === null && $interactionStartedAt !== null
                && Carbon::parse($interactionStartedAt)->diffInMinutes($now) >= 15) {
                $handoffReason = 'long_conversation';
            }

            if ($status === 'ai' && $handoffReason !== null) {
                $status = 'requested';
                $this->identityQuery($conversation)->update([
                    'support_status' => $status,
                    'handoff_reason' => $handoffReason,
                ]);
                $handoffReply = match ($handoffReason) {
                    'allergy_safety' => 'No puedo garantizar ingredientes, ausencia de alérgenos ni contaminación cruzada. Confirma la información directamente con el personal antes de pedir; un asesor también puede ayudarte por este chat.',
                    'specialist_required' => 'Este caso requiere revisar información específica. Te derivo con un asesor para que pueda ayudarte por este chat.',
                    default => 'Te estoy derivando con un asesor para que pueda ayudarte mejor. Puedes dejar tu mensaje aquí y te responderemos por este chat.',
                };
                $this->insertAssistantMessage($id, $handoffReply, 'handoff', false, $now);
            }

            return [
                'id' => $id,
                'status' => $status,
                'reply' => $handoffReply,
                'message_id' => $messageId,
            ];
        });
    }

    /** @param array{reply: string, source?: string, model?: string} $response */
    public function addAssistantReply(string $id, array $response, bool $failed = false): ?int
    {
        return DB::transaction(function () use ($id, $response, $failed): ?int {
            $conversation = DB::table('chatbot_conversations')->where('id', $id)->lockForUpdate()->first();
            abort_unless($conversation, 404);
            if ($conversation->archived_at !== null || $this->identityQuery($conversation)->value('support_status') !== 'ai') {
                return null;
            }

            $messageId = $this->insertAssistantMessage(
                $id,
                $response['reply'],
                $response['source'] ?? null,
                $failed,
                now(),
                $response['model'] ?? null,
            );
            DB::table('chatbot_conversations')->where('id', $id)->update(['updated_at' => now()]);

            return $messageId;
        });
    }

    public function requestHandoff(string $id, string $reason, string $reply): array
    {
        return DB::transaction(function () use ($id, $reason, $reply): array {
            $conversation = DB::table('chatbot_conversations')->where('id', $id)->lockForUpdate()->first();
            abort_unless($conversation && $conversation->archived_at === null, 404);
            $group = $this->identityQuery($conversation);
            $status = (string) ($group->value('support_status') ?? 'ai');
            if ($status === 'ai') {
                $group->update(['support_status' => 'requested', 'handoff_reason' => $reason, 'updated_at' => now()]);
                $this->insertAssistantMessage($id, $reply, 'handoff', false, now());
                $status = 'requested';
            }

            return ['status' => $status, 'last_message_id' => $this->lastMessageId($conversation)];
        });
    }

    /**
     * @return array{status: string, handoff_reason: ?string, assigned_staff_id: ?int, assigned_staff_name: ?string, messages: array<int, object>, last_message_id: int}
     */
    public function updates(Request $request, string $id, int $after = 0): array
    {
        $conversation = $this->findOwned($request, $id);
        $this->escalateLongActiveChat($conversation);
        $conversation = DB::table('chatbot_conversations')->where('id', $id)->first();
        $group = $this->identityQuery($conversation)->whereNull('archived_at');
        $state = DB::table('chatbot_conversations as c')
            ->leftJoin('users as staff', 'staff.id', '=', 'c.assigned_staff_id')
            ->whereIn('c.id', $group->select('id'))
            ->whereNull('c.archived_at')
            ->orderByDesc('c.updated_at')->orderByDesc('c.id')
            ->first(['c.support_status', 'c.handoff_reason', 'c.assigned_staff_id', 'staff.name as assigned_staff_name']);

        $messagesQuery = DB::table('chatbot_messages as m')
            ->join('chatbot_conversations as c', 'c.id', '=', 'm.conversation_id')
            ->whereIn('c.id', $group->select('id'))
            ->whereNull('c.archived_at')
            ->when($after > 0, fn ($query) => $query->where('m.id', '>', $after))
            ->orderBy('m.id', $after > 0 ? 'asc' : 'desc')
            ->limit(100);
        $messages = $messagesQuery->get(['m.id', 'm.role', 'm.content', 'm.source', 'm.is_error', 'm.created_at']);
        if ($after === 0) {
            $messages = $messages->reverse()->values();
        }
        $staffMessageIds = $messages->where('source', 'staff')->pluck('id');
        if ($staffMessageIds->isNotEmpty()) {
            DB::table('chatbot_messages')
                ->whereIn('id', $staffMessageIds)
                ->whereNull('delivered_at')
                ->update(['delivered_at' => now()]);
        }

        return [
            'status' => $state->support_status ?? 'ai',
            'handoff_reason' => $state->handoff_reason ?? null,
            'assigned_staff_id' => isset($state->assigned_staff_id) ? (int) $state->assigned_staff_id : null,
            'assigned_staff_name' => $state->assigned_staff_name ?? null,
            'messages' => $messages->all(),
            'last_message_id' => (int) ($messages->last()->id ?? $this->lastMessageId($conversation)),
        ];
    }

    public function markReadByCustomer(Request $request, string $id, int $messageId): void
    {
        $conversation = $this->findOwned($request, $id);
        $messageId = min($messageId, $this->lastMessageId($conversation));

        DB::table('chatbot_messages as m')
            ->join('chatbot_conversations as c', 'c.id', '=', 'm.conversation_id')
            ->whereIn('c.id', $this->identityQuery($conversation)->whereNull('archived_at')->select('id'))
            ->where('m.source', 'staff')
            ->where('m.role', 'assistant')
            ->where('m.id', '<=', $messageId)
            ->whereNull('m.read_at')
            ->update(['m.delivered_at' => now(), 'm.read_at' => now()]);
    }

    public function markReadByStaff(string $id, int $messageId): void
    {
        $conversation = DB::table('chatbot_conversations')->where('id', $id)->first();
        abort_unless($conversation && $conversation->archived_at === null, 404);
        $messageId = min($messageId, $this->lastMessageId($conversation));

        DB::table('chatbot_messages as m')
            ->join('chatbot_conversations as c', 'c.id', '=', 'm.conversation_id')
            ->whereIn('c.id', $this->identityQuery($conversation)->whereNull('archived_at')->select('id'))
            ->where('m.role', 'user')
            ->where('m.id', '<=', $messageId)
            ->whereNull('m.read_at')
            ->update(['m.delivered_at' => now(), 'm.read_at' => now()]);
    }

    public function takeOver(string $id, int $staffId): void
    {
        DB::transaction(function () use ($id, $staffId): void {
            $conversation = DB::table('chatbot_conversations')->where('id', $id)->lockForUpdate()->first();
            abort_unless($conversation && $conversation->archived_at === null, 404);

            $this->identityQuery($conversation)->update([
                'support_status' => 'human',
                'handoff_reason' => $conversation->handoff_reason ?: 'manual_takeover',
                'assigned_staff_id' => $staffId,
                'updated_at' => now(),
            ]);
        });
    }

    public function handoffToStaff(string $id): void
    {
        $this->requestHandoff(
            $id,
            'manual_request',
            'Un asesor ha sido notificado y te atenderá por este chat en cuanto esté disponible.',
        );
    }

    public function returnToAi(string $id): void
    {
        DB::transaction(function () use ($id): void {
            $conversation = DB::table('chatbot_conversations')->where('id', $id)->lockForUpdate()->first();
            abort_unless($conversation && $conversation->archived_at === null, 404);

            $this->identityQuery($conversation)->update([
                'support_status' => 'ai',
                'handoff_reason' => null,
                'assigned_staff_id' => null,
                'interaction_started_at' => now(),
                'updated_at' => now(),
            ]);
            $this->insertAssistantMessage($id, 'Un asesor finalizó la atención. Puedes continuar conversando con DESEGO.', 'handoff', false, now());
        });
    }

    /** @return array{id: int, role: string, content: string, source: string, created_at: string} */
    public function sendStaffReply(string $id, int $staffId, string $message): array
    {
        return DB::transaction(function () use ($id, $staffId, $message): array {
            $conversation = DB::table('chatbot_conversations')->where('id', $id)->lockForUpdate()->first();
            abort_unless($conversation && $conversation->archived_at === null, 404);
            $group = $this->identityQuery($conversation);
            $state = $group->first(['support_status', 'assigned_staff_id']);
            abort_unless($state && $state->support_status === 'human' && (int) $state->assigned_staff_id === $staffId, 409);

            $target = $this->identityQuery($conversation)->whereNull('archived_at')->orderByDesc('updated_at')->orderByDesc('id')->first(['id']);
            abort_unless($target, 404);
            $messageId = $this->insertAssistantMessage((string) $target->id, $message, 'staff', false, now());
            DB::table('chatbot_conversations')->where('id', $target->id)->update(['updated_at' => now()]);

            return ['id' => $messageId, 'role' => 'assistant', 'content' => $message, 'source' => 'staff', 'created_at' => now()->toDateTimeString()];
        });
    }

    private function escalateLongActiveChat(object $conversation): void
    {
        DB::transaction(function () use ($conversation): void {
            $current = DB::table('chatbot_conversations')->where('id', $conversation->id)->lockForUpdate()->first();
            if (! $current || $current->archived_at !== null) {
                return;
            }

            $group = $this->identityQuery($current);
            if ($group->value('support_status') !== 'ai') {
                return;
            }
            $startedAt = $group->max('interaction_started_at');
            $lastCustomerMessageAt = $group->max('last_customer_message_at');
            if ($startedAt === null || $lastCustomerMessageAt === null
                || Carbon::parse($startedAt)->diffInMinutes(now()) < 15
                || Carbon::parse($lastCustomerMessageAt)->lt(now()->copy()->subMinutes(2))) {
                return;
            }

            $group->update(['support_status' => 'requested', 'handoff_reason' => 'long_conversation', 'updated_at' => now()]);
            $this->insertAssistantMessage(
                (string) $current->id,
                'La conversación necesita un poco más de ayuda. Un asesor se pondrá en contacto contigo por este chat.',
                'handoff',
                false,
                now(),
            );
        });
    }

    private function insertAssistantMessage(string $id, string $content, ?string $source, bool $failed, mixed $createdAt, ?string $model = null): int
    {
        return (int) DB::table('chatbot_messages')->insertGetId([
            'conversation_id' => $id,
            'role' => 'assistant',
            'content' => $content,
            'source' => $source,
            'model' => $model,
            'is_error' => $failed,
            'created_at' => $createdAt,
        ]);
    }

    private function identityQuery(object $conversation): Builder
    {
        return DB::table('chatbot_conversations')
            ->when($conversation->customer_id !== null,
                fn ($query) => $query->where('customer_id', $conversation->customer_id),
                fn ($query) => $query->where('owner_hash', $conversation->owner_hash),
            );
    }

    private function lastMessageId(object $conversation): int
    {
        return (int) DB::table('chatbot_messages as m')
            ->join('chatbot_conversations as c', 'c.id', '=', 'm.conversation_id')
            ->whereIn('c.id', $this->identityQuery($conversation)->select('id'))
            ->whereNull('c.archived_at')
            ->max('m.id');
    }

    public function setArchived(string $id, bool $archived): void
    {
        DB::transaction(function () use ($id, $archived): void {
            $conversation = DB::table('chatbot_conversations')->where('id', $id)->lockForUpdate()->first(['id', 'owner_hash', 'customer_id']);
            abort_unless($conversation, 404);

            DB::table('chatbot_conversations')
                ->when($conversation->customer_id !== null,
                    fn ($query) => $query->where('customer_id', $conversation->customer_id),
                    fn ($query) => $query->where('owner_hash', $conversation->owner_hash),
                )
                ->update([
                    'archived_at' => $archived ? now() : null,
                    'support_status' => 'ai',
                    'handoff_reason' => null,
                    'assigned_staff_id' => null,
                ]);
        });
    }

    /** @return array<string, mixed> */
    public function listing(Request $request): array
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:120',
            'page' => 'nullable|integer|min:1',
            'status' => 'nullable|in:open,closed',
        ]);
        $identity = "CASE WHEN c.customer_id IS NOT NULL THEN CONCAT('customer:', c.customer_id) ELSE CONCAT('visitor:', c.owner_hash) END";
        $query = DB::table('chatbot_conversations as c')
            ->leftJoin('customers as customer', 'customer.id', '=', 'c.customer_id')
            ->leftJoin('chatbot_visitors as visitor', 'visitor.owner_hash', '=', 'c.owner_hash')
            ->leftJoin('chatbot_messages as m', 'm.conversation_id', '=', 'c.id')
            ->selectRaw("SUBSTRING_INDEX(GROUP_CONCAT(c.id ORDER BY c.updated_at DESC, c.id DESC), ',', 1) as id")
            ->selectRaw('MIN(c.created_at) as created_at, MAX(c.updated_at) as updated_at')
            ->selectRaw("COALESCE(MAX(customer.full_name), CONCAT('v', LPAD(MAX(visitor.id), 7, '0'))) as customer_name")
            ->selectRaw('COUNT(m.id) as message_count')
            ->selectRaw('SUM(CASE WHEN m.role = \'user\' AND m.read_at IS NULL THEN 1 ELSE 0 END) as unread_count')
            ->selectRaw("SUBSTRING_INDEX(GROUP_CONCAT(c.support_status ORDER BY c.updated_at DESC, c.id DESC), ',', 1) as support_status")
            ->selectRaw("SUBSTRING_INDEX(GROUP_CONCAT(c.handoff_reason ORDER BY c.updated_at DESC, c.id DESC), ',', 1) as handoff_reason")
            ->leftJoin('users as staff', 'staff.id', '=', 'c.assigned_staff_id')
            ->selectRaw("SUBSTRING_INDEX(GROUP_CONCAT(staff.name ORDER BY c.updated_at DESC, c.id DESC), ',', 1) as assigned_staff_name")
            ->where(function ($query) use ($validated): void {
                if (($validated['status'] ?? 'open') === 'closed') {
                    $query->whereNotNull('c.archived_at');
                } else {
                    $query->whereNull('c.archived_at');
                }
            })
            ->groupByRaw($identity);
        if (! empty($validated['q'])) {
            $query->where(function ($query) use ($validated): void {
                $query->where('c.title', 'like', '%'.$validated['q'].'%')
                    ->orWhere('customer.full_name', 'like', '%'.$validated['q'].'%')
                    ->orWhereExists(function ($messages) use ($validated): void {
                        $messages->selectRaw('1')->from('chatbot_messages as search_message')
                            ->whereColumn('search_message.conversation_id', 'c.id')
                            ->where('search_message.content', 'like', '%'.$validated['q'].'%');
                    });
            });
        }

        return [
            'conversations' => $query->orderByDesc('updated_at')->paginate(15)->withQueryString(),
            'filters' => ['q' => $validated['q'] ?? '', 'status' => $validated['status'] ?? 'open'],
        ];
    }
}
