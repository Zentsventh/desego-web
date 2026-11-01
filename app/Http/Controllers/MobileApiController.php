<?php

namespace App\Http\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class MobileApiController extends Controller
{
    /**
     * Endpoint de salud requerido por DatabaseApi.kt en tienda-android (/health.php).
     */
    public function health(): JsonResponse
    {
        try {
            DB::select('SELECT 1');

            return response()->json([
                'service' => 'desego',
                'database' => 'connected',
            ]);
        } catch (Throwable) {
            return response()->json([
                'service' => 'desego',
                'database' => 'disconnected',
            ], 503);
        }
    }

    /**
     * Endpoint unificado para sincronización de tienda-android y consultas públicas de reserva/seguimiento.
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            if ($request->isMethod('GET')) {
                return $this->statePayload();
            }

            $body = $request->json()->all();
            if (! is_array($body) || $body === []) {
                $body = $request->all();
            }

            $action = (string) ($body['action'] ?? '');

            if ($action === 'reservation.public' || $action === 'reservation.create') {
                return $this->createPublicReservation($body);
            }

            if ($action === 'order.create') {
                return $this->createMobileOrder($body);
            }

            if ($action === 'order.track') {
                return $this->trackOrder($body);
            }

            return response()->json([
                'ok' => false,
                'message' => 'Acción no reconocida.',
            ], 400);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage() ?: 'Error al procesar la solicitud.',
            ], 422);
        }
    }

    protected function statePayload(): JsonResponse
    {
        $categories = DB::table('categories')
            ->where('status', 'active')
            ->orderBy('id')
            ->get(['id', 'name', 'slug']);

        $products = DB::table('products as p')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.status', 'available')
            ->where('c.status', 'active')
            ->orderBy('p.id')
            ->get([
                'p.id',
                'p.slug',
                'p.name',
                'p.description',
                'p.price',
                'p.emoji',
                'p.category_id',
                'c.name as category',
            ]);

        $tables = DB::table('restaurant_tables')
            ->where('is_active', 1)
            ->where('table_number', '<=', (int) config('dining.table_count', 12))
            ->orderBy('table_number')
            ->get(['id', 'table_number', 'label', 'zone', 'capacity', 'operational_status']);

        $today = gmdate('Y-m-d 00:00:00');
        $salesToday = (float) DB::table('payments')
            ->where('created_at', '>=', $today)
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_type = 'payment' THEN amount ELSE -amount END), 0) AS total")
            ->value('total');

        $ordersToday = (int) DB::table('orders')
            ->where('created_at', '>=', $today)
            ->count();

        $pendingOrders = (int) DB::table('orders')
            ->where('status', 'Pendiente')
            ->whereNull('cancelled_at')
            ->count();

        $recentOrders = DB::table('orders')
            ->orderByDesc('id')
            ->limit(15)
            ->get(['id', 'order_number', 'customer_name', 'order_type', 'status', 'payment_status', 'total', 'created_at']);

        $recentReservations = DB::table('reservations as r')
            ->join('restaurant_tables as t', 't.id', '=', 'r.table_id')
            ->orderByDesc('r.id')
            ->limit(15)
            ->get([
                'r.id',
                'r.reservation_code',
                'r.customer_name',
                'r.guest_count',
                'r.reservation_at',
                'r.status',
                't.label as table_label',
                't.zone',
            ]);

        return response()->json([
            'ok' => true,
            'service' => 'desego',
            'database' => 'connected',
            'categories' => $categories,
            'products' => $products,
            'tables' => $tables,
            'stats' => [
                'sales_today' => $salesToday,
                'orders_today' => $ordersToday,
                'pending_orders' => $pendingOrders,
            ],
            'recent_orders' => $recentOrders,
            'recent_reservations' => $recentReservations,
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function createPublicReservation(array $body): JsonResponse
    {
        $customerName = trim((string) ($body['customer_name'] ?? 'Cliente DESEGO'));
        $phone = trim((string) ($body['customer_phone'] ?? $body['phone'] ?? ''));
        $email = trim((string) ($body['customer_email'] ?? $body['email'] ?? ''));
        $notes = trim((string) ($body['notes'] ?? ''));
        $guestCount = max(1, min(20, (int) ($body['guest_count'] ?? $body['people'] ?? 2)));
        $requestedTableId = filter_var($body['table_id'] ?? null, FILTER_VALIDATE_INT);

        if (mb_strlen($customerName) < 2) {
            return response()->json(['ok' => false, 'message' => 'Ingresa tu nombre para la reserva.'], 422);
        }

        $reservationAtRaw = trim((string) ($body['reservation_at'] ?? ''));
        if ($reservationAtRaw === '' && isset($body['date'], $body['time'])) {
            $dateStr = trim((string) $body['date']);
            $timeStr = trim((string) $body['time']);
            if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $dateStr, $matches)) {
                $reservationAtRaw = "{$matches[3]}-{$matches[2]}-{$matches[1]} {$timeStr}:00";
            } else {
                $reservationAtRaw = "{$dateStr} {$timeStr}:00";
            }
        }

        try {
            $dt = $reservationAtRaw !== ''
                ? new DateTimeImmutable($reservationAtRaw, new DateTimeZone('America/Lima'))
                : (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+2 hours');
            $utcAt = $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            $utcAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+2 hours')->format('Y-m-d H:i:s');
        }

        $adminId = (int) (DB::table('users as u')
            ->join('roles as r', 'r.id', '=', 'u.role_id')
            ->where('u.status', 'active')
            ->where('r.code', 'admin')
            ->orderBy('u.id')
            ->value('u.id') ?? DB::table('users')->orderBy('id')->value('id'));

        if ($adminId < 1) {
            return response()->json(['ok' => false, 'message' => 'No hay una cuenta de administración configurada.'], 503);
        }

        $tableQuery = DB::table('restaurant_tables')
            ->where('is_active', 1)
            ->where('table_number', '<=', (int) config('dining.table_count', 12))
            ->where('operational_status', 'available')
            ->where('capacity', '>=', $guestCount);

        if ($requestedTableId && $requestedTableId > 0) {
            $table = (clone $tableQuery)->where('id', $requestedTableId)->first();
        } else {
            $table = null;
        }

        if (! $table) {
            $table = $tableQuery->orderBy('capacity')->orderBy('table_number')->first();
        }

        if (! $table) {
            $table = DB::table('restaurant_tables')->where('is_active', 1)->where('table_number', '<=', (int) config('dining.table_count', 12))->orderBy('capacity', 'desc')->first();
        }

        if (! $table) {
            return response()->json(['ok' => false, 'message' => 'No hay mesas disponibles en este momento.'], 409);
        }

        $now = gmdate('Y-m-d H:i:s');
        $newId = DB::table('reservations')->insertGetId([
            'reservation_code' => null,
            'customer_name' => mb_substr($customerName, 0, 100),
            'customer_id' => null,
            'customer_phone' => $phone !== '' ? mb_substr($phone, 0, 30) : null,
            'customer_email' => $email !== '' ? mb_substr($email, 0, 190) : null,
            'table_id' => (int) $table->id,
            'guest_count' => $guestCount,
            'reservation_at' => $utcAt,
            'duration_minutes' => 120,
            'status' => 'Pendiente',
            'notes' => $notes !== '' ? mb_substr($notes, 0, 500) : 'Reserva registrada desde tienda online / app',
            'created_by' => $adminId,
            'updated_by' => $adminId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $code = 'RES-'.str_pad((string) $newId, 6, '0', STR_PAD_LEFT);
        DB::table('reservations')->where('id', $newId)->update(['reservation_code' => $code]);

        return response()->json([
            'ok' => true,
            'id' => $newId,
            'reservation_code' => $code,
            'table_label' => (string) $table->label,
            'zone' => (string) $table->zone,
            'guest_count' => $guestCount,
            'reservation_at' => $utcAt,
            'status' => 'Pendiente',
            'message' => "Reserva {$code} registrada en {$table->label} ({$table->zone}).",
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function createMobileOrder(array $body): JsonResponse
    {
        $customerName = trim((string) ($body['customer_name'] ?? 'Cliente App Android'));
        $phone = trim((string) ($body['phone'] ?? '999999999'));
        $email = trim((string) ($body['email'] ?? ''));
        $mode = trim((string) ($body['mode'] ?? 'Delivery'));
        $addressLine = trim((string) ($body['address'] ?? 'Local principal'));
        $paymentPreference = trim((string) ($body['payment'] ?? 'Efectivo'));
        $notes = trim((string) ($body['note'] ?? ''));
        $itemsInput = $body['items'] ?? [];

        if (! is_array($itemsInput) || $itemsInput === []) {
            return response()->json(['ok' => false, 'message' => 'El pedido no contiene productos.'], 422);
        }

        $adminId = (int) (DB::table('users as u')
            ->join('roles as r', 'r.id', '=', 'u.role_id')
            ->where('u.status', 'active')
            ->where('r.code', 'admin')
            ->orderBy('u.id')
            ->value('u.id') ?? DB::table('users')->orderBy('id')->value('id'));

        if ($adminId < 1) {
            return response()->json(['ok' => false, 'message' => 'No hay una cuenta de administración activa.'], 503);
        }

        $lines = [];
        $subtotal = 0.0;

        foreach ($itemsInput as $key => $val) {
            $productId = is_array($val) ? (int) ($val['product_id'] ?? 0) : (int) $key;
            $qty = is_array($val) ? (int) ($val['quantity'] ?? 0) : (int) $val;
            if ($productId < 1 || $qty < 1) {
                continue;
            }
            $qty = min(20, $qty);
            $product = DB::table('products')->where('id', $productId)->first();
            if (! $product) {
                continue;
            }
            $price = (float) $product->price;
            $lineTotal = round($price * $qty, 2);
            $subtotal += $lineTotal;
            $lines[] = [
                'product_id' => (int) $product->id,
                'product_name' => (string) $product->name,
                'unit_price' => $price,
                'quantity' => $qty,
                'line_total' => $lineTotal,
            ];
        }

        if ($lines === []) {
            return response()->json(['ok' => false, 'message' => 'Productos no encontrados en el catálogo.'], 422);
        }

        $isDelivery = stripos($mode, 'delivery') !== false;
        $deliveryFee = $isDelivery ? 5.00 : 0.00;
        $discount = (float) ($body['discount'] ?? 0.0);
        $total = round(max(0.0, $subtotal - $discount) + $deliveryFee, 2);
        $orderType = $isDelivery ? 'Delivery' : 'Para llevar';
        $now = gmdate('Y-m-d H:i:s');

        $orderId = DB::transaction(function () use (
            $customerName,
            $orderType,
            $notes,
            $paymentPreference,
            $subtotal,
            $discount,
            $total,
            $adminId,
            $now,
            $lines,
            $isDelivery,
            $phone,
            $addressLine,
            $deliveryFee,
            $email
        ): int {
            $customerId = null;
            if ($email !== '' || $phone !== '') {
                $existingCustomer = DB::table('customers')
                    ->where('email', $email !== '' ? $email : null)
                    ->orWhere('phone', $phone)
                    ->first();

                if ($existingCustomer) {
                    $customerId = (int) $existingCustomer->id;
                } else {
                    $customerId = DB::table('customers')->insertGetId([
                        'customer_code' => null,
                        'full_name' => mb_substr($customerName, 0, 100),
                        'document_type' => 'SIN_DOCUMENTO',
                        'phone' => mb_substr($phone, 0, 30),
                        'email' => $email !== '' ? mb_substr($email, 0, 190) : null,
                        'status' => 'active',
                        'created_by' => $adminId,
                        'updated_by' => $adminId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('customers')->where('id', $customerId)->update([
                        'customer_code' => 'CLI-'.str_pad((string) $customerId, 6, '0', STR_PAD_LEFT),
                    ]);
                }
            }

            $orderId = DB::table('orders')->insertGetId([
                'order_number' => null,
                'customer_name' => mb_substr($customerName, 0, 100),
                'customer_id' => $customerId,
                'order_type' => $orderType,
                'order_channel' => 'web',
                'web_checkout_key' => bin2hex(random_bytes(16)),
                'table_label' => null,
                'notes' => mb_substr('App Android · '.($notes !== '' ? $notes : $paymentPreference), 0, 500),
                'status' => 'Pendiente',
                'payment_status' => 'Pendiente',
                'payment_method' => null,
                'payment_preference' => in_array($paymentPreference, ['Efectivo', 'Yape/Plin', 'Tarjeta al recibir'], true)
                    ? $paymentPreference
                    : 'Efectivo',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'created_by' => $adminId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $orderNumber = 'DES-'.str_pad((string) (1048 + $orderId), 4, '0', STR_PAD_LEFT);
            DB::table('orders')->where('id', $orderId)->update(['order_number' => $orderNumber]);

            foreach ($lines as $line) {
                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $line['product_id'],
                    'product_name' => $line['product_name'],
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                ]);
            }

            if ($isDelivery) {
                DB::table('order_delivery_details')->insert([
                    'order_id' => $orderId,
                    'customer_address_id' => null,
                    'recipient_phone' => mb_substr($phone !== '' ? $phone : '999999999', 0, 30),
                    'address_line' => mb_substr($addressLine !== '' ? $addressLine : 'Dirección App', 0, 255),
                    'zone' => 'Lima',
                    'delivery_fee' => $deliveryFee,
                    'status' => 'Pendiente',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $orderId;
        });

        $orderNumber = 'DES-'.str_pad((string) (1048 + $orderId), 4, '0', STR_PAD_LEFT);

        return response()->json([
            'ok' => true,
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'status' => 'Pendiente',
            'total' => $total,
            'message' => "Pedido {$orderNumber} sincronizado con el restaurante.",
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function trackOrder(array $body): JsonResponse
    {
        $code = strtoupper(trim((string) ($body['order_number'] ?? '')));
        if ($code === '') {
            return response()->json(['ok' => false, 'message' => 'Ingresa el número de pedido (ej. DES-1049).'], 422);
        }

        $order = DB::table('orders')
            ->where('order_number', $code)
            ->first(['id', 'order_number', 'customer_name', 'order_type', 'status', 'payment_status', 'total', 'created_at']);

        if (! $order) {
            return response()->json(['ok' => false, 'message' => 'No encontramos un pedido con ese código.'], 404);
        }

        $items = DB::table('order_items')
            ->where('order_id', $order->id)
            ->get(['product_name', 'quantity', 'line_total']);

        return response()->json([
            'ok' => true,
            'order' => [
                'id' => (int) $order->id,
                'order_number' => (string) $order->order_number,
                'customer_name' => (string) $order->customer_name,
                'order_type' => (string) $order->order_type,
                'status' => (string) $order->status,
                'payment_status' => (string) $order->payment_status,
                'total' => (float) $order->total,
                'created_at' => (string) $order->created_at,
                'items' => $items,
            ],
        ]);
    }
}
