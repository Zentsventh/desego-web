<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use function App\Support\Desego\audit_log;
use function App\Support\Desego\current_user;
use function App\Support\Desego\desego_consume_cost_layers;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\loyalty_award_order_points;

class DiningService
{
    public function prepare(): void
    {
        for ($number = 1; $number <= config('dining.table_count'); $number++) {
            DB::table('restaurant_tables')->insertOrIgnore(['table_number' => $number, 'label' => sprintf('Mesa %02d', $number), 'capacity' => 4, 'zone' => 'Salón principal', 'operational_status' => 'available', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('restaurant_tables')->where('table_number', $number)->whereNull('dining_qr_token')->update(['dining_qr_token' => Str::random(32)]);
        }
    }

    public function table(string $token): object
    {
        return DB::table('restaurant_tables')->where('dining_qr_token', $token)->where('is_active', true)->where('table_number', '<=', config('dining.table_count'))->first() ?? abort(404, 'El código de esta mesa no está disponible.');
    }

    public function preview(string $token): array
    {
        $table = $this->table($token);

        return ['ok' => true, 'table' => ['number' => $table->table_number, 'label' => $table->label], 'occupied' => DB::table('dining_sessions')->where('active_table_id', $table->id)->exists(), 'maintenance' => $table->operational_status === 'maintenance'];
    }

    public function join(string $token, array $input): array
    {
        $data = Validator::make($input, ['name' => 'required|string|min:2|max:100', 'join_code' => 'nullable|digits:6', 'member_token' => 'nullable|string|max:100', 'confirm' => 'accepted'])->validate();
        $table = $this->table($token);

        return DB::transaction(function () use ($table, $data): array {
            $table = DB::table('restaurant_tables')->where('id', $table->id)->lockForUpdate()->first();
            abort_if($table->operational_status === 'maintenance', 409, 'Esta mesa está en mantenimiento.');
            $visit = DB::table('dining_sessions')->where('active_table_id', $table->id)->lockForUpdate()->first();
            if ($visit && ! empty($data['member_token'])) {
                $member = DB::table('dining_members')->where('dining_session_id', $visit->id)->where('token_hash', hash('sha256', $data['member_token']))->first();
                if ($member) {
                    return ['ok' => true, 'member_token' => $data['member_token'], ...$this->snapshot($visit, $member)];
                }
            }
            if ($visit) {
                abort_unless($visit->status === 'open', 409, 'La cuenta de esta visita ya se está cerrando.');
                if (empty($data['join_code']) || ! Hash::check($data['join_code'], $visit->join_code_hash)) {
                    throw ValidationException::withMessages(['join_code' => 'La mesa ya tiene una visita. Pide el código de seis dígitos a quien la abrió.']);
                }
            } else {
                abort_unless($table->operational_status === 'available', 409, 'El personal debe liberar esta mesa antes de abrir una visita.');
                $reserved = DB::table('reservations')->where('table_id', $table->id)->whereIn('status', ['Pendiente', 'Confirmada', 'Sentada'])->whereBetween('reservation_at', [now()->subMinutes(120), now()->addMinutes(30)])->exists();
                abort_if($reserved, 409, 'Esta mesa está reservada. El personal te ayudará a ocuparla.');
                $id = (string) Str::uuid();
                $code = (string) random_int(100000, 999999);
                DB::table('dining_sessions')->insert(['id' => $id, 'table_id' => $table->id, 'active_table_id' => $table->id, 'status' => 'open', 'join_code_hash' => Hash::make($code), 'join_code_encrypted' => Crypt::encryptString($code), 'created_at' => now(), 'updated_at' => now()]);
                DB::table('restaurant_tables')->where('id', $table->id)->update(['operational_status' => 'occupied', 'updated_at' => now()]);
                $visit = DB::table('dining_sessions')->where('id', $id)->first();
            }
            $memberToken = Str::random(64);
            $memberId = (string) Str::uuid();
            DB::table('dining_members')->insert(['id' => $memberId, 'dining_session_id' => $visit->id, 'name' => $data['name'], 'token_hash' => hash('sha256', $memberToken), 'created_at' => now(), 'updated_at' => now()]);
            audit_log(desego_db(), null, 'dining.joined', ['session_id' => $visit->id, 'table_id' => $table->id]);

            return ['ok' => true, 'member_token' => $memberToken, ...$this->snapshot($visit, DB::table('dining_members')->where('id', $memberId)->first())];
        });
    }

    public function member(string $token): object
    {
        abort_unless(strlen($token) === 64, 401, 'Escanea el QR para entrar a tu mesa.');

        return DB::table('dining_members')->where('token_hash', hash('sha256', $token))->first() ?? abort(401, 'La sesión de esta mesa no es válida.');
    }

    public function state(string $token): array
    {
        $member = $this->member($token);
        $visit = DB::table('dining_sessions')->where('id', $member->dining_session_id)->first();

        return ['ok' => true, ...$this->snapshot($visit, $member)];
    }

    public function snapshot(object $visit, ?object $member = null): array
    {
        $table = DB::table('restaurant_tables')->where('id', $visit->table_id)->first();
        $orders = DB::table('orders')->where('dining_session_id', $visit->id)->orderBy('id')->get();
        foreach ($orders as $order) {
            $order->items = DB::table('order_items')->where('order_id', $order->id)->get(['product_name', 'quantity', 'unit_price', 'line_total']);
            $order->is_mine = $member && $order->dining_member_id === $member->id;
        }
        $due = $orders->where('status', '<>', 'Cancelado')->where('payment_status', 'Pendiente');

        return ['visit' => ['id' => $visit->id, 'status' => $visit->status, 'started_at' => $visit->created_at, 'join_code' => $visit->status === 'open' ? Crypt::decryptString($visit->join_code_encrypted) : null], 'table' => ['id' => $table->id, 'number' => $table->table_number, 'label' => $table->label], 'orders' => $orders, 'balance' => round($due->sum('total'), 2), 'my_balance' => round($due->where('dining_member_id', $member?->id)->sum('total'), 2), 'requests' => DB::table('dining_requests')->where('dining_session_id', $visit->id)->orderByDesc('id')->get(['id', 'kind', 'status', 'notes', 'created_at']), 'direct_payment' => config('dining.direct_payment'), 'online_payment_available' => filled(config('dining.niubiz.merchant_id')) && filled(config('dining.niubiz.password')), 'niubiz_sandbox' => config('dining.niubiz.environment') !== 'production'];
    }

    public function order(string $token, array $input): array
    {
        $member = $this->member($token);
        $data = Validator::make($input, ['checkout_key' => 'required|uuid', 'items' => 'required|array|min:1|max:30', 'items.*.product_id' => 'required|integer|distinct', 'items.*.quantity' => 'required|integer|min:1|max:20', 'notes' => 'nullable|string|max:500'])->validate();

        return DB::transaction(function () use ($member, $data): array {
            $visit = DB::table('dining_sessions')->where('id', $member->dining_session_id)->lockForUpdate()->first();
            $existing = DB::table('orders')->where('web_checkout_key', $data['checkout_key'])->first();
            if ($existing) {
                abort_unless($existing->dining_member_id === $member->id, 403);

                return ['ok' => true, 'order_id' => $existing->id, ...$this->snapshot($visit, $member)];
            }
            abort_unless($visit->status === 'open', 409, 'La cuenta está cerrándose. Pide al mesero que la reabra antes de agregar más platos.');
            $actor = DB::table('users')->join('roles', 'roles.id', '=', 'users.role_id')->where('roles.code', 'admin')->where('users.status', 'active')->value('users.id');
            abort_unless($actor, 503, 'El restaurante aún no recibe pedidos.');
            $lines = [];
            $requirements = [];
            $totalCents = 0;
            $items = collect($data['items'])->sortBy('product_id');
            foreach ($items as $item) {
                $product = DB::table('products')->join('categories', 'categories.id', '=', 'products.category_id')->where('products.id', $item['product_id'])->where('products.status', 'available')->where('categories.status', 'active')->select('products.*')->lockForUpdate()->first();
                abort_unless($product, 409, 'Un plato dejó de estar disponible. Actualiza la carta.');
                $recipe = DB::table('product_ingredients')->where('product_id', $product->id)->orderBy('ingredient_id')->lockForUpdate()->get();
                abort_if($recipe->isEmpty(), 409, 'El restaurante debe completar la receta de este plato.');
                foreach ($recipe as $ingredient) {
                    $requirements[$ingredient->ingredient_id] = round(($requirements[$ingredient->ingredient_id] ?? 0) + (float) $ingredient->quantity * $item['quantity'], 3);
                }
                $price = (int) round((float) $product->price * 100);
                $totalCents += $price * $item['quantity'];
                $lines[] = ['product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => $price / 100, 'quantity' => $item['quantity'], 'line_total' => $price * $item['quantity'] / 100];
            }
            $ingredients = DB::table('ingredients')->whereIn('id', array_keys($requirements))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($requirements as $id => $quantity) {
                $ingredient = $ingredients->get($id);
                abort_unless($ingredient && $ingredient->status === 'active' && (float) $ingredient->stock_quantity + .0001 >= $quantity, 409, 'No hay ingredientes suficientes para este pedido.');
            }
            $table = DB::table('restaurant_tables')->where('id', $visit->table_id)->first();
            $orderId = DB::table('orders')->insertGetId(['customer_name' => $member->name, 'order_type' => 'Mesa', 'order_channel' => 'web', 'web_checkout_key' => $data['checkout_key'], 'table_label' => $table->label, 'dining_session_id' => $visit->id, 'dining_member_id' => $member->id, 'notes' => $data['notes'] ?? null, 'status' => 'Pendiente', 'payment_status' => 'Pendiente', 'subtotal' => $totalCents / 100, 'total' => $totalCents / 100, 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
            $number = 'M-'.str_pad((string) $orderId, 8, '0', STR_PAD_LEFT);
            DB::table('orders')->where('id', $orderId)->update(['order_number' => $number]);
            foreach ($lines as $line) {
                DB::table('order_items')->insert(['order_id' => $orderId, ...$line]);
            }
            foreach ($requirements as $id => $quantity) {
                $ingredient = $ingredients->get($id);
                $cost = desego_consume_cost_layers(desego_db(), (int) $id, (float) $quantity, (float) $ingredient->cost_per_unit);
                $after = round((float) $ingredient->stock_quantity - $quantity, 3);
                DB::table('ingredients')->where('id', $id)->update(['stock_quantity' => $after, 'updated_at' => now()]);
                DB::table('order_ingredients')->insert(['order_id' => $orderId, 'ingredient_id' => $id, 'consumed_quantity' => $quantity, 'unit' => $ingredient->unit, 'unit_cost' => $cost]);
                DB::table('inventory_movements')->insert(['ingredient_id' => $id, 'user_id' => $actor, 'movement_type' => 'sale', 'quantity_delta' => -$quantity, 'previous_quantity' => $ingredient->stock_quantity, 'new_quantity' => $after, 'unit_cost' => $cost, 'reference' => $number, 'notes' => 'Pedido QR de '.$table->label, 'created_at' => now()]);
            }
            DB::table('dining_sessions')->where('id', $visit->id)->update(['updated_at' => now()]);
            audit_log(desego_db(), null, 'dining.order_created', ['order_id' => $orderId, 'session_id' => $visit->id]);

            return ['ok' => true, 'order_id' => $orderId, ...$this->snapshot($visit, $member)];
        }, 3);
    }

    public function requestAttention(string $token, array $input): array
    {
        $member = $this->member($token);
        $data = Validator::make($input, ['kind' => 'required|in:waiter,bill,help', 'notes' => 'nullable|string|max:300'])->validate();

        return DB::transaction(function () use ($member, $data): array {
            $visit = DB::table('dining_sessions')->where('id', $member->dining_session_id)->lockForUpdate()->first();
            abort_if(in_array($visit->status, ['closed', 'cleaning'], true), 409, 'Esta visita ya terminó.');
            $existing = DB::table('dining_requests')->where('dining_session_id', $visit->id)->where('kind', $data['kind'])->whereIn('status', ['pending', 'acknowledged'])->first();
            if (! $existing) {
                DB::table('dining_requests')->insert(['dining_session_id' => $visit->id, 'dining_member_id' => $member->id, 'kind' => $data['kind'], 'notes' => $data['notes'] ?? null, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            } elseif (! empty($data['notes'])) {
                DB::table('dining_requests')->where('id', $existing->id)->update(['notes' => $data['notes'], 'updated_at' => now()]);
            }
            if ($data['kind'] === 'bill') {
                DB::table('dining_sessions')->where('id', $visit->id)->update(['status' => 'checkout', 'updated_at' => now()]);
                $visit->status = 'checkout';
            }

            return ['ok' => true, ...$this->snapshot($visit, $member)];
        });
    }

    public function authorizeStaff(array $permissions): array
    {
        $user = current_user();
        abort_unless($user && $user['role_code'] !== 'customer' && array_intersect($permissions, $user['permissions']), 403);

        return $user;
    }

    public function paymentInFlight(string $sessionId): bool
    {
        return DB::table('dining_payment_attempts')->where('dining_session_id', $sessionId)->where(function ($query): void {
            $query->whereIn('status', ['authorizing', 'unknown'])->orWhere(function ($pending): void {
                $pending->whereIn('status', ['creating', 'ready'])->where('expires_at', '>', now());
            });
        })->exists();
    }

    public function floor(): array
    {
        $user = $this->authorizeStaff(['reservations.view', 'tables.view', 'orders.view', 'kitchen.view']);
        $tables = DB::table('restaurant_tables')->where('table_number', '<=', config('dining.table_count'))->where('is_active', true)->orderBy('table_number')->get();
        foreach ($tables as $table) {
            $visit = DB::table('dining_sessions')->where('active_table_id', $table->id)->first();
            $table->visit = $visit ? $this->snapshot($visit) : null;
            $table->qr_url = $table->dining_qr_token ? route('dining.table', ['token' => $table->dining_qr_token]) : null;
            unset($table->dining_qr_token);
        }

        return ['ok' => true, 'tables' => $tables, 'permissions' => $user['permissions'], 'server_time' => now()->toIso8601String()];
    }

    public function staffAction(array $input): array
    {
        $data = Validator::make($input, ['action' => 'required|in:acknowledge,resolve,clean,reopen,status,pay', 'session_id' => 'required|uuid', 'request_id' => 'nullable|integer', 'order_id' => 'nullable|integer', 'status' => 'nullable|in:Preparando,Listo,Entregado', 'method' => 'nullable|in:Efectivo,Yape/Plin,Tarjeta,Transferencia'])->validate();
        $permission = match ($data['action']) {
            'pay' => ['orders.pay', 'cash.manage'],
            'status' => ['orders.update', 'kitchen.manage'],
            default => ['tables.manage', 'reservations.manage', 'orders.update'],
        };
        $actor = $this->authorizeStaff($permission);

        return DB::transaction(function () use ($data, $actor): array {
            $visit = DB::table('dining_sessions')->where('id', $data['session_id'])->lockForUpdate()->first() ?? abort(404);
            abort_if($visit->status === 'closed', 409, 'Esta visita ya está cerrada.');
            switch ($data['action']) {
                case 'acknowledge':
                case 'resolve':
                    $affected = DB::table('dining_requests')->where('id', $data['request_id'] ?? 0)->where('dining_session_id', $visit->id)->whereIn('status', ['pending', 'acknowledged'])->update(['status' => $data['action'] === 'resolve' ? 'resolved' : 'acknowledged', 'handled_by' => $actor['id'], 'handled_at' => now(), 'updated_at' => now()]);
                    abort_unless($affected, 409, 'Esta solicitud ya fue atendida.');
                    break;
                case 'status':
                    $order = DB::table('orders')->where('id', $data['order_id'] ?? 0)->where('dining_session_id', $visit->id)->lockForUpdate()->first() ?? abort(404);
                    $next = ['Pendiente' => 'Preparando', 'Preparando' => 'Listo', 'Listo' => 'Entregado'];
                    abort_unless(($next[$order->status] ?? null) === ($data['status'] ?? null), 409, 'El estado del pedido cambió. Actualiza la mesa.');
                    DB::table('orders')->where('id', $order->id)->update(['status' => $data['status'], 'updated_at' => now(), 'completed_at' => $data['status'] === 'Entregado' ? now() : null]);
                    break;
                case 'pay':
                    abort_if($this->paymentInFlight($visit->id), 409, 'Hay un pago de Niubiz en curso o pendiente de verificación. No cobres nuevamente.');
                    abort_unless(in_array($visit->status, ['open', 'checkout'], true), 409, 'Esta cuenta ya está pagada.');
                    DB::table('cash_register_mutex')->where('id', 1)->lockForUpdate()->first();
                    $cash = DB::table('cash_sessions')->where('status', 'open')->lockForUpdate()->first();
                    if (! $cash) {
                        $cashId = DB::table('cash_sessions')->insertGetId([
                            'opened_by' => $actor['id'],
                            'opening_amount' => 0,
                            'status' => 'open',
                            'opening_notes' => 'Apertura automática desde cobro de mesa en Salón 3D',
                            'opened_at' => now(),
                        ]);
                        $cash = DB::table('cash_sessions')->where('id', $cashId)->first();
                    }
                    $orders = DB::table('orders')->where('dining_session_id', $visit->id)->where('status', '<>', 'Cancelado')->where('payment_status', 'Pendiente')->lockForUpdate()->get();
                    abort_if($orders->isEmpty(), 409, 'No hay consumo pendiente por cobrar.');
                    $method = $data['method'] ?? 'Efectivo';
                    foreach ($orders as $order) {
                        DB::table('payments')->insert(['order_id' => $order->id, 'cash_session_id' => $cash->id, 'user_id' => $actor['id'], 'payment_type' => 'payment', 'method' => $method, 'amount' => $order->total, 'notes' => 'Cobro verificado de mesa QR ('.$method.')', 'created_at' => now()]);
                        DB::table('orders')->where('id', $order->id)->update(['payment_status' => 'Pagado', 'payment_method' => $method, 'cash_session_id' => $cash->id, 'paid_at' => now(), 'updated_at' => now()]);
                        loyalty_award_order_points(desego_db(), $order->id, $actor['id']);
                    }
                    DB::table('dining_requests')->where('dining_session_id', $visit->id)->where('kind', 'bill')->whereIn('status', ['pending', 'acknowledged'])->update(['status' => 'resolved', 'handled_by' => $actor['id'], 'handled_at' => now(), 'updated_at' => now()]);
                    DB::table('dining_sessions')->where('id', $visit->id)->update(['status' => 'paid', 'updated_at' => now()]);
                    break;
                case 'reopen':
                    abort_if($this->paymentInFlight($visit->id), 409, 'Espera el resultado del pago antes de reabrir la cuenta.');
                    abort_unless($visit->status === 'checkout', 409);
                    DB::table('dining_sessions')->where('id', $visit->id)->update(['status' => 'open', 'updated_at' => now()]);
                    break;
                case 'clean':
                    $pending = DB::table('orders')->where('dining_session_id', $visit->id)->where('status', '<>', 'Cancelado')->where(function ($query): void {
                        $query->where('payment_status', 'Pendiente')->orWhere('status', '<>', 'Entregado');
                    })->exists();
                    abort_if($pending, 409, 'Entrega y cobra todos los pedidos antes de liberar la mesa.');
                    if ($visit->status !== 'cleaning') {
                        DB::table('dining_sessions')->where('id', $visit->id)->update(['status' => 'cleaning', 'updated_at' => now()]);
                    } else {
                        DB::table('dining_sessions')->where('id', $visit->id)->update(['status' => 'closed', 'active_table_id' => null, 'closed_at' => now(), 'updated_at' => now()]);
                        DB::table('restaurant_tables')->where('id', $visit->table_id)->update(['operational_status' => 'available', 'updated_at' => now()]);
                        DB::table('dining_requests')->where('dining_session_id', $visit->id)->whereIn('status', ['pending', 'acknowledged'])->update(['status' => 'resolved', 'handled_by' => $actor['id'], 'handled_at' => now(), 'updated_at' => now()]);
                    }
                    break;
            }
            audit_log(desego_db(), $actor['id'], 'dining.'.$data['action'], ['session_id' => $visit->id, 'request_id' => $data['request_id'] ?? null, 'order_id' => $data['order_id'] ?? null]);

            return ['ok' => true];
        }, 3);
    }
}
