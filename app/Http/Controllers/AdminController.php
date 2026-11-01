<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

use function App\Support\Desego\current_user;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\restore_remembered_session;

class AdminController extends Controller
{
    /**
     * Panel de control híbrido (Laravel + Inertia.js + React).
     * Pasa los datos iniciales de la base de datos directamente como props a React.
     */
    public function index(): Response
    {
        $user = null;
        $initialStats = null;
        $initialOrders = [];
        $initialMenu = ['categories' => [], 'products' => []];
        $initialReservations = ['tables' => [], 'reservations' => []];
        $initialInventory = [];
        $initialCustomers = [];

        try {
            $user = current_user();
            if (! $user) {
                $pdo = desego_db();
                restore_remembered_session($pdo);
                $user = current_user();
            }

            if ($user) {
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

                $averageTicket = (float) DB::table('orders')
                    ->where('payment_status', 'Pagado')
                    ->where('created_at', '>=', $today)
                    ->avg('total');

                $lowStockCount = (int) DB::table('ingredients')
                    ->where('status', 'active')
                    ->whereColumn('stock_quantity', '<=', 'min_stock')
                    ->count();

                $availableTables = (int) DB::table('restaurant_tables')
                    ->where('is_active', 1)
                    ->where('table_number', '<=', (int) config('dining.table_count', 12))
                    ->where('operational_status', 'available')
                    ->count();

                $activeReservations = (int) DB::table('reservations')
                    ->whereIn('status', ['Pendiente', 'Confirmada', 'Sentada'])
                    ->count();

                $customersCount = (int) DB::table('customers')
                    ->where('status', 'active')
                    ->count();

                $initialStats = [
                    'sales_today' => $salesToday,
                    'orders_today' => $ordersToday,
                    'pending_orders' => $pendingOrders,
                    'average_ticket' => $averageTicket,
                    'low_stock_count' => $lowStockCount,
                    'available_tables' => $availableTables,
                    'active_reservations' => $activeReservations,
                    'customers_count' => $customersCount,
                ];

                $initialOrders = DB::table('orders as o')
                    ->join('users as u', 'u.id', '=', 'o.created_by')
                    ->orderByDesc('o.id')
                    ->limit(50)
                    ->get([
                        'o.id',
                        'o.order_number',
                        'o.customer_name',
                        'o.order_type',
                        'o.table_label',
                        'o.status',
                        'o.payment_status',
                        'o.payment_method',
                        'o.payment_preference',
                        'o.subtotal',
                        'o.discount',
                        'o.total',
                        'o.created_at',
                        'u.name as created_by_name',
                    ])
                    ->map(function (object $order): array {
                        $items = DB::table('order_items')
                            ->where('order_id', $order->id)
                            ->orderBy('id')
                            ->get(['quantity', 'product_name']);

                        $summary = $items
                            ->map(fn (object $item): string => "{$item->quantity} × {$item->product_name}")
                            ->implode(', ');

                        return [
                            'id' => (int) $order->id,
                            'order_number' => (string) $order->order_number,
                            'customer_name' => (string) $order->customer_name,
                            'order_type' => (string) $order->order_type,
                            'table_label' => $order->table_label ? (string) $order->table_label : null,
                            'status' => (string) $order->status,
                            'payment_status' => (string) $order->payment_status,
                            'payment_method' => $order->payment_method ? (string) $order->payment_method : null,
                            'payment_preference' => $order->payment_preference ? (string) $order->payment_preference : null,
                            'subtotal' => (float) $order->subtotal,
                            'discount' => (float) $order->discount,
                            'total' => (float) $order->total,
                            'created_at' => (string) $order->created_at,
                            'created_by_name' => (string) $order->created_by_name,
                            'item_count' => $items->count(),
                            'item_summary' => $summary,
                        ];
                    })
                    ->all();

                $categories = DB::table('categories')
                    ->orderBy('name')
                    ->get(['id', 'slug', 'name', 'description', 'status'])
                    ->map(fn (object $cat): array => [
                        'id' => (int) $cat->id,
                        'slug' => (string) $cat->slug,
                        'name' => (string) $cat->name,
                        'description' => (string) ($cat->description ?? ''),
                        'status' => (string) $cat->status,
                    ])
                    ->all();

                $products = DB::table('products as p')
                    ->join('categories as c', 'c.id', '=', 'p.category_id')
                    ->orderBy('p.name')
                    ->get([
                        'p.id',
                        'p.category_id',
                        'c.name as category',
                        'p.name',
                        'p.description',
                        'p.price',
                        'p.emoji',
                        'p.status',
                    ])
                    ->map(fn (object $prod): array => [
                        'id' => (int) $prod->id,
                        'category_id' => (int) $prod->category_id,
                        'category' => (string) $prod->category,
                        'name' => (string) $prod->name,
                        'description' => (string) ($prod->description ?? ''),
                        'price' => (float) $prod->price,
                        'emoji' => (string) ($prod->emoji ?? '🍽️'),
                        'status' => (string) $prod->status,
                        'allergens' => 'Registrado en receta',
                    ])
                    ->all();

                $initialMenu = [
                    'categories' => $categories,
                    'products' => $products,
                ];

                $tables = DB::table('restaurant_tables')
                    ->where('table_number', '<=', (int) config('dining.table_count', 12))
                    ->orderByDesc('is_active')
                    ->orderBy('zone')
                    ->orderBy('table_number')
                    ->get(['id', 'table_number', 'label', 'zone', 'capacity', 'operational_status', 'is_active'])
                    ->map(fn (object $table): array => [
                        'id' => (int) $table->id,
                        'table_number' => (int) $table->table_number,
                        'label' => (string) $table->label,
                        'zone' => (string) $table->zone,
                        'capacity' => (int) $table->capacity,
                        'operational_status' => (string) $table->operational_status,
                        'effective_status' => (string) $table->operational_status,
                        'is_active' => (int) $table->is_active,
                    ])
                    ->all();

                $reservations = DB::table('reservations as r')
                    ->join('restaurant_tables as t', 't.id', '=', 'r.table_id')
                    ->orderBy('r.reservation_at')
                    ->limit(100)
                    ->get([
                        'r.id',
                        'r.reservation_code',
                        'r.customer_name',
                        'r.customer_phone',
                        'r.customer_email',
                        'r.table_id',
                        'r.guest_count',
                        'r.reservation_at',
                        'r.duration_minutes',
                        'r.status',
                        'r.notes',
                        't.label as table_label',
                        't.zone',
                    ])
                    ->map(fn (object $res): array => [
                        'id' => (int) $res->id,
                        'reservation_code' => (string) $res->reservation_code,
                        'customer_name' => (string) $res->customer_name,
                        'customer_phone' => (string) ($res->customer_phone ?? ''),
                        'customer_email' => (string) ($res->customer_email ?? ''),
                        'table_id' => (int) $res->table_id,
                        'guest_count' => (int) $res->guest_count,
                        'reservation_at' => str_replace(' ', 'T', (string) $res->reservation_at).'Z',
                        'duration_minutes' => (int) $res->duration_minutes,
                        'status' => (string) $res->status,
                        'notes' => (string) ($res->notes ?? ''),
                        'table_label' => (string) $res->table_label,
                        'zone' => (string) $res->zone,
                    ])
                    ->all();

                $initialReservations = [
                    'tables' => $tables,
                    'reservations' => $reservations,
                ];

                $initialInventory = DB::table('ingredients')
                    ->orderBy('name')
                    ->get(['id', 'name', 'unit', 'stock_quantity', 'min_stock', 'cost_per_unit', 'status'])
                    ->map(fn (object $ing): array => [
                        'id' => (int) $ing->id,
                        'name' => (string) $ing->name,
                        'unit' => (string) $ing->unit,
                        'stock_quantity' => (float) $ing->stock_quantity,
                        'min_stock' => (float) $ing->min_stock,
                        'cost_per_unit' => (float) $ing->cost_per_unit,
                        'status' => (string) $ing->status,
                        'low_stock' => (float) $ing->stock_quantity <= (float) $ing->min_stock,
                    ])
                    ->all();

                $initialCustomers = DB::table('customers as c')
                    ->orderByDesc('c.id')
                    ->limit(50)
                    ->get([
                        'c.id',
                        'c.customer_code',
                        'c.full_name',
                        'c.phone',
                        'c.email',
                        'c.status',
                        'c.created_at',
                        DB::raw('(SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS orders_count'),
                        DB::raw('(SELECT COALESCE(SUM(o.total), 0) FROM orders o WHERE o.customer_id = c.id AND o.cancelled_at IS NULL) AS total_spent'),
                    ])
                    ->map(fn (object $cust): array => [
                        'id' => (int) $cust->id,
                        'customer_code' => (string) ($cust->customer_code ?? 'CLI-'.str_pad((string) $cust->id, 6, '0', STR_PAD_LEFT)),
                        'full_name' => (string) $cust->full_name,
                        'phone' => (string) ($cust->phone ?? ''),
                        'email' => (string) ($cust->email ?? ''),
                        'status' => (string) $cust->status,
                        'orders_count' => (int) $cust->orders_count,
                        'total_spent' => (float) $cust->total_spent,
                        'created_at' => (string) $cust->created_at,
                    ])
                    ->all();
            }
        } catch (Throwable) {
            // Entornos de prueba sin tablas migradas mantienen estado inicial vacío.
        }

        return Inertia::render('Admin/Index', [
            'user' => $user,
            'initialStats' => $initialStats,
            'initialOrders' => $initialOrders,
            'initialMenu' => $initialMenu,
            'initialReservations' => $initialReservations,
            'initialInventory' => $initialInventory,
            'initialCustomers' => $initialCustomers,
        ]);
    }
}
