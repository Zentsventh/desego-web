<?php

namespace App\Services;

use App\Mail\AccountVerificationCode;
use App\Support\Desego\LegacyEndpoint;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

use function App\Support\Desego\audit_log;
use function App\Support\Desego\clear_remember_cookie;
use function App\Support\Desego\current_user;
use function App\Support\Desego\desego_add_adjustment_cost_layer;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\establish_authenticated_session;

class RestaurantPortal
{
    public function __construct(private LegacyEndpoint $legacy) {}

    /**
     * @param  list<string>  $allowedMimes
     */
    public function storeUploadedFile(?UploadedFile $file, string $folder, array $allowedMimes = ['jpg', 'jpeg', 'png', 'webp'], int $maxKb = 4096): ?string
    {
        if (! $file) {
            return null;
        }
        Validator::make(
            ['file' => $file],
            ['file' => 'required|file|mimes:'.implode(',', $allowedMimes).'|max:'.$maxKb]
        )->validate();

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        if (! in_array($ext, $allowedMimes, true)) {
            $ext = $allowedMimes[0] ?? 'jpg';
        }
        $directory = public_path('uploads/'.trim($folder, '/'));
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $filename = Str::uuid()->toString().'.'.$ext;
        $file->move($directory, $filename);

        return '/uploads/'.trim($folder, '/').'/'.$filename;
    }

    public function catalog(): array
    {
        $catalog = $this->legacy->call('storefront');
        $stock = DB::select('SELECT pi.product_id, MIN(FLOOR(i.stock_quantity / NULLIF(pi.quantity,0))) AS portions, MIN(i.status = ?) AS ingredients_active FROM product_ingredients pi JOIN ingredients i ON i.id=pi.ingredient_id GROUP BY pi.product_id', ['active']);
        $available = collect($stock)->keyBy('product_id');
        $reviews = $this->legacy->call('reviews')['summary'] ?? [];
        $catalog['products'] = array_map(function (array $product) use ($available, $reviews): array {
            $inventory = $available->get($product['id']);
            $product['can_order'] = ($product['status'] ?? 'available') === 'available' && (! $inventory || ($inventory->ingredients_active && $inventory->portions > 0));
            $product['image'] = ! empty($product['image_url']) ? $product['image_url'] : $this->photo($product['name']);
            $product['review'] = $reviews[$product['id']] ?? ['count' => 0, 'average' => null];

            return $product;
        }, $catalog['products']);

        $branch = DB::table('branches')->where('status', 'active')->orderByDesc('is_main')->first(['name', 'address', 'district', 'phone', 'email', 'opening_time', 'closing_time']);
        $catalog['branch'] = $branch;

        $settings = [];
        try {
            $settings = DB::table('system_settings')->pluck('setting_value', 'setting_key')->all();
        } catch (Throwable) {
        }

        $hours = $catalog['business_hours'] ?? [];
        $scheduleText = $this->formatScheduleSummary($hours);
        $isOpenNow = $this->computeIsOpenNow($hours);

        $rawPayments = (string) ($settings['restaurant_payment_methods'] ?? '');
        $paymentMethods = json_decode($rawPayments, true);
        if (! is_array($paymentMethods)) {
            $paymentMethods = $rawPayments !== '' ? explode(',', $rawPayments) : [];
        }
        $paymentMethods = array_values(array_unique(array_filter(array_map(static fn ($v): string => trim((string) $v), $paymentMethods))));
        if (! $paymentMethods) {
            $paymentMethods = ['Efectivo', 'Yape/Plin', 'Tarjeta al recibir'];
        }

        $catalog['social'] = [
            'name' => (string) ($settings['restaurant_name'] ?? $settings['restaurant_trade_name'] ?? 'DeSego Cevichería'),
            'specialty' => (string) ($settings['restaurant_specialty'] ?? 'Pescados, mariscos y comida peruana'),
            'whatsapp_phone' => (string) ($settings['restaurant_whatsapp'] ?? '+51 922 119 238'),
            'whatsapp_url' => (string) ($settings['restaurant_whatsapp_url'] ?? $settings['restaurant_whatsapp_link'] ?? 'https://wa.me/51922119238'),
            'facebook' => (string) ($settings['restaurant_facebook'] ?? 'https://facebook.com/desegocevicheria'),
            'instagram' => (string) ($settings['restaurant_instagram'] ?? 'https://instagram.com/desegocevicheria'),
            'tiktok' => (string) ($settings['restaurant_tiktok'] ?? 'https://www.tiktok.com/@desegocevicheria/'),
            'address' => (string) ($settings['restaurant_address'] ?? ($branch->address ?? 'UCV 19, Lote 13, Zona A, Huaycán, Ate')),
            'district' => (string) ($settings['restaurant_district'] ?? ($branch->district ?? 'Ate')),
            'postal_code' => (string) ($settings['restaurant_postal_code'] ?? '15479'),
            'latitude' => (float) ($settings['restaurant_latitude'] ?? -12.0168500),
            'longitude' => (float) ($settings['restaurant_longitude'] ?? -76.8211200),
            'email' => (string) ($settings['restaurant_email'] ?? ''),
            'schedule' => $scheduleText,
        ];
        $catalog['is_open_now'] = $isOpenNow;
        $paymentMethods = array_values(array_filter($paymentMethods, fn (string $method): bool => $method !== 'Niubiz'));
        if (current_user() && app(NiubizOrderPayment::class)->available()) {
            $paymentMethods[] = 'Niubiz';
        }
        $catalog['payment_methods'] = array_values($paymentMethods);
        $resConfig = DB::table('reservation_configurations')->orderBy('id')->first();
        $resEnabled = ($settings['reservations_enabled'] ?? '1') === '1' && ($resConfig ? (bool) $resConfig->reservations_enabled : true);

        $catalog['services'] = [
            'delivery_enabled' => ($settings['delivery_enabled'] ?? '1') === '1',
            'pickup_enabled' => ($settings['pickup_enabled'] ?? '1') === '1',
            'dine_in_enabled' => ($settings['dine_in_enabled'] ?? '1') === '1',
            'reservations_enabled' => $resEnabled,
        ];

        $customer = $this->customer(false);
        if ($customer) {
            $visitorKey = hash('sha256', 'customer_'.$customer->id);
            $catalog['favorite_ids'] = DB::table('storefront_favorites')
                ->where('visitor_key', $visitorKey)
                ->pluck('product_id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();
            $catalog['customer_profile'] = [
                'id' => (int) $customer->id,
                'name' => (string) $customer->full_name,
                'phone' => (string) ($customer->phone ?? ''),
                'email' => (string) ($customer->email ?? ''),
            ];
            $catalog['customer_addresses'] = DB::table('customer_addresses')
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->orderByDesc('id')
                ->get(['id', 'label', 'recipient_name', 'phone', 'address_line', 'reference', 'district', 'latitude', 'longitude', 'is_default']);
        } else {
            $catalog['favorite_ids'] = [];
            $catalog['customer_profile'] = null;
            $catalog['customer_addresses'] = [];
        }

        return $catalog;
    }

    /**
     * @param  list<array<string, mixed>>  $hours
     */
    private function formatScheduleSummary(array $hours): string
    {
        if (! $hours) {
            return 'Lunes a Domingo: 9:00 a. m. – 5:00 p. m.';
        }
        $openRows = array_values(array_filter($hours, static fn (array $h): bool => empty($h['is_closed'])));
        if (! $openRows) {
            return 'Temporalmente cerrado';
        }
        $firstSlot = substr((string) $openRows[0]['open_time'], 0, 5).'-'.substr((string) $openRows[0]['close_time'], 0, 5);
        $sameSlot = true;
        foreach ($openRows as $row) {
            if (substr((string) $row['open_time'], 0, 5).'-'.substr((string) $row['close_time'], 0, 5) !== $firstSlot) {
                $sameSlot = false;
                break;
            }
        }
        if ($sameSlot && count($openRows) === 7) {
            return 'Lunes a Domingo: '.$this->humanHour((string) $openRows[0]['open_time']).' – '.$this->humanHour((string) $openRows[0]['close_time']);
        }

        return $openRows[0]['day_name'].' a '.$openRows[count($openRows) - 1]['day_name'].': '.$this->humanHour((string) $openRows[0]['open_time']).' – '.$this->humanHour((string) $openRows[0]['close_time']);
    }

    private function humanHour(string $hhmmss): string
    {
        $parts = explode(':', $hhmmss);
        $h = (int) ($parts[0] ?? 9);
        $m = (int) ($parts[1] ?? 0);
        $suffix = $h >= 12 ? 'p. m.' : 'a. m.';
        $h12 = $h % 12 ?: 12;

        return $h12.':'.str_pad((string) $m, 2, '0', STR_PAD_LEFT).' '.$suffix;
    }

    /**
     * @param  list<array<string, mixed>>  $hours
     */
    private function computeIsOpenNow(array $hours): bool
    {
        $nowLima = CarbonImmutable::now('America/Lima');
        $dow = (int) $nowLima->dayOfWeekIso;
        $currentHm = $nowLima->format('H:i');
        if (! $hours) {
            return $currentHm >= '09:00' && $currentHm <= '17:00';
        }
        foreach ($hours as $h) {
            if ((int) ($h['day_of_week'] ?? 0) === $dow) {
                if (! empty($h['is_closed'])) {
                    return false;
                }
                $open = substr((string) $h['open_time'], 0, 5);
                $close = substr((string) $h['close_time'], 0, 5);

                return $currentHm >= $open && $currentHm <= $close;
            }
        }

        return false;
    }

    public function photo(string $name): string
    {
        $value = mb_strtolower($name);

        return match (true) {
            str_contains($value, 'leche') => '/assets/images/dish_leche_tigre.jpg',
            str_contains($value, 'causa') => '/assets/images/dish_causa_marina.jpg',
            str_contains($value, 'jalea') => '/assets/images/dish_jalea_mixta.jpg',
            str_contains($value, 'mixto') && str_contains($value, 'ceviche') => '/assets/images/dish_ceviche_mixto.jpg',
            str_contains($value, 'arroz') || str_contains($value, 'chaufa') => '/assets/images/dish_arroz_mariscos.jpg',
            str_contains($value, 'chicha') || str_contains($value, 'bebida') => '/assets/images/drink_chicha_morada.jpg',
            str_contains($value, 'parihuela') || str_contains($value, 'chilcano') || str_contains($value, 'sudado') => '/assets/images/dish_chupe_langostinos.jpg',
            str_contains($value, 'chicharron') || str_contains($value, 'frito') || str_contains($value, 'spaghetti') || str_contains($value, 'tallarin') => '/assets/images/food_pescado.jpg',
            str_contains($value, 'trio') || str_contains($value, 'duo') || str_contains($value, 'ronda') => '/assets/images/dish_jalea_mixta.jpg',
            default => '/assets/images/dish_ceviche_clasico.jpg',
        };
    }

    public function customer(bool $required = true): ?object
    {
        $user = current_user();
        if (! $user) {
            abort_if($required, 401);

            return null;
        }

        $customer = DB::table('customers')->where('user_id', $user['id'])->first();

        if (! $customer && ! empty($user['email'])) {
            $byEmail = DB::table('customers')
                ->where('email', mb_strtolower((string) $user['email']))
                ->first();

            if ($byEmail) {
                $linkUpdate = [
                    'user_id' => $user['id'],
                    'status' => 'active',
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('customers', 'deleted_at')) {
                    $linkUpdate['deleted_at'] = null;
                }
                DB::table('customers')->where('id', $byEmail->id)->update($linkUpdate);
                $customer = DB::table('customers')->where('id', $byEmail->id)->first();
            }
        }

        if (! $customer) {
            $customerId = DB::table('customers')->insertGetId([
                'user_id' => $user['id'],
                'full_name' => $user['name'] ?: 'Cliente DESEGO',
                'email' => ! empty($user['email']) ? mb_strtolower((string) $user['email']) : null,
                'document_type' => 'SIN_DOCUMENTO',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('customers')->where('id', $customerId)->update([
                'customer_code' => 'CLI-'.str_pad((string) $customerId, 6, '0', STR_PAD_LEFT),
            ]);

            DB::table('loyalty_accounts')->insertOrIgnore([
                'customer_id' => $customerId,
                'points_balance' => 0,
                'lifetime_earned' => 0,
                'lifetime_redeemed' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $customer = DB::table('customers')->where('id', $customerId)->first();
        }

        if ($customer && $customer->status !== 'active') {
            $reactivate = [
                'status' => 'active',
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('customers', 'deleted_at')) {
                $reactivate['deleted_at'] = null;
            }
            DB::table('customers')->where('id', $customer->id)->update($reactivate);
            $customer->status = 'active';
        }

        if ($customer) {
            session()->put('storefront_customer_id', $customer->id);
        }

        return $customer;
    }

    public function register(array $input): array
    {
        $data = Validator::make($input, ['name' => 'required|string|min:3|max:100', 'email' => 'required|email|max:190|unique:users,email', 'password' => 'required|string|min:10|max:4096|confirmed', 'phone' => 'nullable|string|max:30'])->validate();
        DB::transaction(function () use ($data): void {
            DB::table('roles')->insertOrIgnore(['code' => 'customer', 'name' => 'Cliente', 'description' => 'Cuenta de cliente de la tienda', 'is_system' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $passwordHash = Hash::make($data['password']);
            $id = DB::table('users')->insertGetId(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'password_hash' => $passwordHash, 'role_id' => DB::table('roles')->where('code', 'customer')->value('id'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $customerId = DB::table('customers')->insertGetId(['user_id' => $id, 'full_name' => $data['name'], 'email' => mb_strtolower($data['email']), 'phone' => $data['phone'] ?? null, 'document_type' => 'SIN_DOCUMENTO', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('customers')->where('id', $customerId)->update(['customer_code' => 'CLI-'.str_pad((string) $customerId, 6, '0', STR_PAD_LEFT)]);
            clear_remember_cookie(desego_db());
            establish_authenticated_session($id, $passwordHash);
            session()->regenerateToken();
            session()->put('storefront_customer_id', $customerId);
            session()->put('csrf_token', session()->token());
        });

        return ['ok' => true, 'user' => current_user(), 'csrfToken' => session()->token()];
    }

    public function login(array $input): array
    {
        $result = $this->legacy->call('auth', $input, ['action' => 'login'], 'POST');
        $this->customer(false);

        return $result;
    }

    public function logout(): array
    {
        $result = $this->legacy->call('auth', [], ['action' => 'logout'], 'POST');
        session()->forget(['storefront_customer_id', 'storefront_last_order_id']);

        return $result;
    }

    public function checkout(array $input): array
    {
        $customer = $this->customer(false);
        if ($customer) {
            $isDineIn = in_array((string) ($input['order_type'] ?? ''), ['Consumo en local', 'Mesa'], true);
            $phoneRule = $isDineIn ? 'nullable|string|max:30' : 'required|string|max:30';
            $validated = Validator::make($input, ['phone' => $phoneRule])->validate();
            $phone = trim((string) ($validated['phone'] ?? ($customer->phone ?? '')));
            if ($phone !== '') {
                DB::table('customers')->where('id', $customer->id)->update(['phone' => $phone, 'updated_at' => now()]);
                $input['phone'] = $phone;
            }
            $input['email'] = ! empty($input['email']) ? $input['email'] : $customer->email;
            $input['customer_name'] = $input['customer_name'] ?? $customer->full_name;
            if (isset($input['checkout_key'])) {
                $existing = DB::table('orders')->where('web_checkout_key', $input['checkout_key'])->first();
                abort_if($existing && (int) $existing->customer_id !== (int) $customer->id, 403);
            }
        }

        return $this->legacy->call('storefront', [...$input, 'action' => 'checkout'], [], 'POST');
    }

    public function account(): array
    {
        $customer = $this->customer();
        $user = DB::table('users')->where('id', $customer->user_id)->first();
        $customer->email = $user->email ?? $customer->email;
        $customer->avatar_url = ($user->avatar_url ?? null) ?: ($customer->avatar_url ?? null);
        $customer->last_login_at = $user->last_login_at ?? null;
        $customer->email_verified_at = $user->email_verified_at ?? null;
        $customer->phone_verified_at = $customer->phone_verified_at ?? null;

        if (! empty($customer->email)) {
            DB::table('reservations')
                ->whereNull('customer_id')
                ->where('customer_email', mb_strtolower((string) $customer->email))
                ->update(['customer_id' => $customer->id]);
        }

        $orderStats = DB::table('orders')
            ->where('customer_id', $customer->id)
            ->where('status', '<>', 'Cancelado')
            ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(total), 0) as total_spent')
            ->first();
        $activeReservationsCount = DB::table('reservations')
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['Pendiente', 'Confirmada', 'Sentada', 'Solicitada', 'CONFIRMED', 'PENDING_PAYMENT', 'CHECKED_IN'])
            ->count();

        $orders = DB::table('orders')->where('customer_id', $customer->id)->orderByDesc('id')->limit(50)->get();
        foreach ($orders as $order) {
            $order->online_payment_pending = app(NiubizOrderPayment::class)->hasPendingAttempt((int) $order->id);
            $order->items = DB::table('order_items')->where('order_id', $order->id)->get()->map(function (object $item): object {
                if (! empty($item->selected_options) && is_string($item->selected_options)) {
                    $item->selected_options = json_decode($item->selected_options, true) ?: [];
                } else {
                    $item->selected_options = [];
                }

                return $item;
            });
            $order->invoice = DB::table('invoices')->where('order_id', $order->id)->first();
            $order->delivery = DB::table('order_delivery_details')->where('order_id', $order->id)->first();
            $order->reviewed_product_ids = DB::table('product_reviews')->where('order_id', $order->id)->where('customer_id', $customer->id)->pluck('product_id')->all();
        }
        $frequentProducts = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('products as p', 'p.id', '=', 'oi.product_id')
            ->where('o.customer_id', $customer->id)
            ->where('o.status', '<>', 'Cancelado')
            ->selectRaw('p.id, p.code, p.name, p.price, p.image_url, p.status, COUNT(DISTINCT o.id) as times_ordered, SUM(oi.quantity) as total_quantity')
            ->groupBy('p.id', 'p.code', 'p.name', 'p.price', 'p.image_url', 'p.status')
            ->orderByDesc('total_quantity')
            ->orderByDesc('times_ordered')
            ->limit(6)
            ->get()
            ->map(function (object $prod): object {
                $prod->price = (float) $prod->price;
                $prod->times_ordered = (int) $prod->times_ordered;
                $prod->total_quantity = (int) $prod->total_quantity;
                $prod->can_order = $prod->status === 'available';
                $prod->image = ! empty($prod->image_url) ? $prod->image_url : $this->photo($prod->name);

                return $prod;
            });

        $loyaltyRow = DB::table('loyalty_accounts')->where('customer_id', $customer->id)->first();
        $pointsBalance = (int) ($loyaltyRow->points_balance ?? 0);
        $lifetimeEarned = (int) ($loyaltyRow->lifetime_earned ?? 0);
        $lifetimeRedeemed = (int) ($loyaltyRow->lifetime_redeemed ?? 0);
        $tier = $lifetimeEarned >= 300 ? 'Oro' : ($lifetimeEarned >= 100 ? 'Plata' : 'Bronce');
        $nextTier = $tier === 'Bronce' ? 'Plata' : ($tier === 'Plata' ? 'Oro' : null);
        $pointsToNext = $tier === 'Bronce' ? max(0, 100 - $lifetimeEarned) : ($tier === 'Plata' ? max(0, 300 - $lifetimeEarned) : 0);

        $now = gmdate('Y-m-d H:i:s');
        $rewards = DB::table('loyalty_rewards')
            ->where('status', 'active')
            ->where('starts_at', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function ($query): void {
                $query->whereNull('stock_limit')->orWhereColumn('redeemed_count', '<', 'stock_limit');
            })
            ->orderBy('points_cost')
            ->get(['id', 'reward_code', 'name', 'description', 'reward_type', 'points_cost', 'discount_amount']);

        $redemptions = DB::table('loyalty_redemptions as lr')
            ->join('loyalty_rewards as r', 'r.id', '=', 'lr.reward_id')
            ->where('lr.customer_id', $customer->id)
            ->orderByDesc('lr.id')
            ->limit(20)
            ->get(['lr.id', 'lr.redemption_code', 'lr.points_spent', 'lr.status', 'lr.issued_at', 'lr.used_at', 'r.name as reward_name']);

        $transactions = DB::table('loyalty_transactions')
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get(['id', 'transaction_type', 'points_delta', 'balance_after', 'description', 'created_at']);

        $reservations = DB::table('reservations')->where('customer_id', $customer->id)->orderByDesc('reservation_at')->limit(30)->get()->map(function (object $reservation): object {
            $reservation->tables = app(ReservationAvailability::class)->tables((int) $reservation->id);
            $reservation->seating = app(ReservationAvailability::class)->seating($reservation->tables);

            return $reservation;
        });

        $visitorKey = hash('sha256', 'customer_'.$customer->id);
        $favorites = DB::table('storefront_favorites as f')
            ->join('products as p', 'p.id', '=', 'f.product_id')
            ->where('f.visitor_key', $visitorKey)
            ->get(['p.id', 'p.code', 'p.name', 'p.slug', 'p.description', 'p.price', 'p.emoji', 'p.image_url', 'p.status', 'p.category_id', 'f.created_at as favorited_at'])
            ->map(function (object $fav): object {
                $fav->image = ! empty($fav->image_url) ? $fav->image_url : $this->photo($fav->name);
                $fav->can_order = $fav->status === 'available';

                return $fav;
            });

        $reviews = DB::table('product_reviews as pr')
            ->join('products as p', 'p.id', '=', 'pr.product_id')
            ->where('pr.customer_id', $customer->id)
            ->orderByDesc('pr.id')
            ->limit(30)
            ->get(['pr.*', 'p.name as product_name', 'p.emoji as product_emoji']);

        $tickets = DB::table('support_tickets')
            ->where('created_by', $customer->user_id)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function (object $t): object {
                $t->messages = DB::table('support_ticket_messages')
                    ->where('ticket_id', $t->id)
                    ->where('is_internal', 0)
                    ->orderBy('id')
                    ->get();

                return $t;
            });

        $preferences = DB::table('user_preferences')->where('user_id', $customer->user_id)->first();
        if (! $preferences) {
            DB::table('user_preferences')->insertOrIgnore([
                'user_id' => $customer->user_id,
                'show_notification_badge' => 1,
                'desktop_notifications' => 1,
                'sound_notifications' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $preferences = DB::table('user_preferences')->where('user_id', $customer->user_id)->first();
        }

        $customerNotes = [];
        if (! empty($customer->notes) && str_starts_with((string) $customer->notes, '{')) {
            $customerNotes = json_decode((string) $customer->notes, true) ?: [];
        }

        return [
            'customer' => $customer,
            'niubiz_available' => app(NiubizOrderPayment::class)->available(),
            'stats' => [
                'orders_count' => (int) $orderStats->orders_count,
                'total_spent' => round((float) $orderStats->total_spent, 2),
                'active_reservations' => $activeReservationsCount,
                'points_balance' => $pointsBalance,
                'favorites_count' => $favorites->count(),
                'reviews_count' => $reviews->count(),
                'tickets_count' => $tickets->count(),
            ],
            'orders' => $orders,
            'frequent_products' => $frequentProducts,
            'addresses' => DB::table('customer_addresses')->where('customer_id', $customer->id)->where('status', 'active')->orderByDesc('is_default')->orderByDesc('id')->get(),
            'reservations' => $reservations,
            'loyalty' => [
                'points_balance' => $pointsBalance,
                'lifetime_earned' => $lifetimeEarned,
                'lifetime_redeemed' => $lifetimeRedeemed,
                'tier' => $tier,
                'next_tier' => $nextTier,
                'points_to_next_tier' => $pointsToNext,
                'rewards' => $rewards,
                'redemptions' => $redemptions,
                'transactions' => $transactions,
            ],
            'favorites' => $favorites,
            'reviews' => $reviews,
            'tickets' => $tickets,
            'preferences' => [
                'show_notification_badge' => (bool) ($preferences->show_notification_badge ?? 1),
                'desktop_notifications' => (bool) ($preferences->desktop_notifications ?? 1),
                'sound_notifications' => (bool) ($preferences->sound_notifications ?? 0),
                'order_emails' => (bool) ($preferences->account_order_emails ?? $customerNotes['order_emails'] ?? true),
                'promo_emails' => (bool) ($preferences->account_promo_emails ?? $customerNotes['promo_emails'] ?? true),
                'preferred_payment' => (string) ($preferences->account_preferred_payment ?? $customerNotes['preferred_payment'] ?? 'Efectivo'),
            ],
        ];
    }

    public function saveProfile(array $input, ?UploadedFile $avatarFile = null): array
    {
        $customer = $this->customer();
        $docType = $input['document_type'] ?? 'DNI';
        $docNumber = ! empty($input['document_number']) ? trim((string) $input['document_number']) : null;

        $data = Validator::make($input, [
            'name' => 'required|string|min:3|max:100',
            'phone' => 'nullable|string|max:30',
            'document_type' => 'nullable|string|in:DNI,RUC,CE,PASAPORTE,OTRO,SIN_DOCUMENTO',
            'document_number' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('customers', 'document_number')
                    ->where(fn ($query) => $query->where('document_type', $docType))
                    ->ignore($customer->id),
            ],
            'birth_date' => 'nullable|date_format:Y-m-d',
            'avatar_url' => 'nullable|string|max:500',
        ], [
            'document_number.unique' => 'Este número de documento ya está registrado en otra cuenta.',
        ])->validate();

        $uploadedAvatar = $this->storeUploadedFile($avatarFile, 'avatars', ['jpg', 'jpeg', 'png', 'webp'], 3072);
        if ($uploadedAvatar !== null) {
            $data['avatar_url'] = $uploadedAvatar;
        }

        DB::transaction(function () use ($customer, $data): void {
            $update = [
                'full_name' => $data['name'],
                'updated_at' => now(),
            ];
            if (array_key_exists('phone', $data)) {
                $update['phone'] = $data['phone'] ?: null;
                if ((string) ($customer->phone ?? '') !== (string) ($data['phone'] ?? '') && Schema::hasColumn('customers', 'phone_verified_at')) {
                    $update['phone_verified_at'] = null;
                }
            }
            if (array_key_exists('document_type', $data) && $data['document_type']) {
                $update['document_type'] = $data['document_type'];
            }
            if (array_key_exists('document_number', $data)) {
                $update['document_number'] = $data['document_number'] ?: null;
            }
            if (array_key_exists('birth_date', $data)) {
                $update['birth_date'] = $data['birth_date'] ?: null;
            }
            if (array_key_exists('avatar_url', $data) && Schema::hasColumn('customers', 'avatar_url')) {
                $update['avatar_url'] = $data['avatar_url'] ?: null;
            }
            DB::table('customers')->where('id', $customer->id)->update($update);

            if ($customer->user_id) {
                $userUpdate = ['name' => $data['name'], 'updated_at' => now()];
                if (array_key_exists('avatar_url', $data) && Schema::hasColumn('users', 'avatar_url')) {
                    $userUpdate['avatar_url'] = $data['avatar_url'] ?: null;
                }
                DB::table('users')->where('id', $customer->user_id)->update($userUpdate);
            }
        });

        return ['ok' => true, 'message' => 'Perfil actualizado.'];
    }

    public function savePreferences(array $input): array
    {
        $customer = $this->customer();
        $data = Validator::make($input, [
            'show_notification_badge' => 'nullable|boolean',
            'desktop_notifications' => 'nullable|boolean',
            'sound_notifications' => 'nullable|boolean',
            'order_emails' => 'nullable|boolean',
            'promo_emails' => 'nullable|boolean',
            'preferred_payment' => 'nullable|string|max:50',
        ])->validate();

        $preferenceUpdates = ['updated_at' => now()];
        foreach ([
            'show_notification_badge' => 'show_notification_badge',
            'desktop_notifications' => 'desktop_notifications',
            'sound_notifications' => 'sound_notifications',
            'order_emails' => 'account_order_emails',
            'promo_emails' => 'account_promo_emails',
            'preferred_payment' => 'account_preferred_payment',
        ] as $inputKey => $column) {
            if (array_key_exists($inputKey, $data)) {
                $preferenceUpdates[$column] = in_array($inputKey, ['preferred_payment'], true)
                    ? $data[$inputKey]
                    : (int) (bool) $data[$inputKey];
            }
        }

        DB::table('user_preferences')->updateOrInsert(['user_id' => $customer->user_id], $preferenceUpdates);

        return ['ok' => true, 'message' => 'Preferencias actualizadas con éxito.'];
    }

    public function submitReview(array $input, ?UploadedFile $photoFile = null): array
    {
        $customer = $this->customer();
        $data = Validator::make($input, [
            'order_id' => 'required|integer|min:1',
            'product_id' => 'required|integer|min:1',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:120',
            'comment' => 'required|string|min:4|max:1000',
            'photo_url' => 'nullable|string|max:500',
        ])->validate();

        $order = DB::table('orders')->where('id', $data['order_id'])->where('customer_id', $customer->id)->first();
        abort_unless($order, 404, 'Pedido no encontrado.');
        if (! in_array($order->status, ['Listo', 'Entregado'], true)) {
            throw ValidationException::withMessages(['order_id' => 'Solo puedes calificar productos de pedidos listos o entregados.']);
        }

        $productExists = DB::table('order_items')->where('order_id', $order->id)->where('product_id', $data['product_id'])->exists();
        if (! $productExists) {
            throw ValidationException::withMessages(['product_id' => 'El producto seleccionado no pertenece a este pedido.']);
        }

        $alreadyReviewed = DB::table('product_reviews')->where('order_id', $order->id)->where('product_id', $data['product_id'])->where('customer_id', $customer->id)->exists();
        if ($alreadyReviewed) {
            throw ValidationException::withMessages(['product_id' => 'Ya has enviado una reseña para este producto en este pedido.']);
        }

        $uploadedPhoto = $this->storeUploadedFile($photoFile, 'reviews', ['jpg', 'jpeg', 'png', 'webp'], 4096);
        $photoUrl = $uploadedPhoto ?: ($data['photo_url'] ?? null);

        $insert = [
            'order_id' => $order->id,
            'product_id' => $data['product_id'],
            'customer_id' => $customer->id,
            'reviewer_name' => $customer->full_name,
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'comment' => $data['comment'],
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('product_reviews', 'photo_url')) {
            $insert['photo_url'] = $photoUrl;
        }
        DB::table('product_reviews')->insert($insert);

        return ['ok' => true, 'message' => '¡Gracias por tu calificación! Reseña registrada correctamente.'];
    }

    public function createSupportTicket(array $input, ?UploadedFile $attachmentFile = null): array
    {
        $customer = $this->customer();
        $data = Validator::make($input, [
            'subject' => 'required|string|min:4|max:180',
            'category' => 'required|string|in:technical,orders,reservations,loyalty,inventory,billing,access,other',
            'priority' => 'required|string|in:low,normal,high,urgent',
            'message' => 'required|string|min:5|max:2000',
        ])->validate();

        $attachmentUrl = $this->storeUploadedFile($attachmentFile, 'support', ['jpg', 'jpeg', 'png', 'webp', 'pdf'], 5120);

        return DB::transaction(function () use ($customer, $data, $attachmentUrl): array {
            $ticketData = [
                'created_by' => $customer->user_id,
                'subject' => $data['subject'],
                'category' => $data['category'],
                'priority' => $data['priority'],
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($attachmentUrl !== null && Schema::hasColumn('support_tickets', 'attachment_url')) {
                $ticketData['attachment_url'] = $attachmentUrl;
            }
            $ticketId = DB::table('support_tickets')->insertGetId($ticketData);

            $ticketNumber = 'TCK-'.str_pad((string) $ticketId, 6, '0', STR_PAD_LEFT);
            DB::table('support_tickets')->where('id', $ticketId)->update(['ticket_number' => $ticketNumber]);

            $msgData = [
                'ticket_id' => $ticketId,
                'author_id' => $customer->user_id,
                'author_name' => $customer->full_name,
                'message' => $data['message'],
                'is_internal' => 0,
                'created_at' => now(),
            ];
            if ($attachmentUrl !== null && Schema::hasColumn('support_ticket_messages', 'attachment_url')) {
                $msgData['attachment_url'] = $attachmentUrl;
            }
            DB::table('support_ticket_messages')->insert($msgData);

            audit_log(desego_db(), (int) $customer->user_id, 'support_tickets.created', ['ticket_id' => $ticketId, 'number' => $ticketNumber]);

            return ['ok' => true, 'ticket_number' => $ticketNumber, 'message' => 'Ticket de atención generado exitosamente (Nro. '.$ticketNumber.'). Te responderemos en breve.'];
        });
    }

    public function replySupportTicket(int $ticketId, array $input, ?UploadedFile $attachmentFile = null): array
    {
        $customer = $this->customer();
        $ticket = DB::table('support_tickets')->where('id', $ticketId)->where('created_by', $customer->user_id)->first();
        abort_unless($ticket, 404, 'Ticket no encontrado.');

        $data = Validator::make($input, [
            'message' => 'required|string|min:2|max:2000',
        ])->validate();

        $attachmentUrl = $this->storeUploadedFile($attachmentFile, 'support', ['jpg', 'jpeg', 'png', 'webp', 'pdf'], 5120);

        DB::transaction(function () use ($ticket, $customer, $data, $attachmentUrl): void {
            $msgData = [
                'ticket_id' => $ticket->id,
                'author_id' => $customer->user_id,
                'author_name' => $customer->full_name,
                'message' => $data['message'],
                'is_internal' => 0,
                'created_at' => now(),
            ];
            if ($attachmentUrl !== null && Schema::hasColumn('support_ticket_messages', 'attachment_url')) {
                $msgData['attachment_url'] = $attachmentUrl;
            }
            DB::table('support_ticket_messages')->insert($msgData);

            if ($ticket->status === 'waiting_user' || $ticket->status === 'resolved') {
                DB::table('support_tickets')->where('id', $ticket->id)->update([
                    'status' => 'open',
                    'updated_at' => now(),
                ]);
            }
        });

        return ['ok' => true, 'message' => 'Mensaje agregado al ticket.'];
    }

    public function toggleFavorite(int $productId): array
    {
        $customer = $this->customer();
        $product = DB::table('products')->where('id', $productId)->first();
        abort_unless($product, 404, 'Producto no encontrado.');

        $visitorKey = hash('sha256', 'customer_'.$customer->id);
        $exists = DB::table('storefront_favorites')->where('visitor_key', $visitorKey)->where('product_id', $productId)->exists();

        if ($exists) {
            DB::table('storefront_favorites')->where('visitor_key', $visitorKey)->where('product_id', $productId)->delete();

            return ['ok' => true, 'favorited' => false, 'message' => 'Plato eliminado de tus favoritos.'];
        }

        DB::table('storefront_favorites')->insert([
            'visitor_key' => $visitorKey,
            'product_id' => $productId,
            'created_at' => now(),
        ]);

        return ['ok' => true, 'favorited' => true, 'message' => '¡Plato guardado en tus favoritos!'];
    }

    public function sendVerificationCode(array $input): array
    {
        $customer = $this->customer();
        $data = Validator::make($input, [
            'channel' => 'required|string|in:email,phone',
        ])->validate();

        $channel = $data['channel'];
        if ($channel === 'phone') {
            throw ValidationException::withMessages([
                'channel' => 'La verificación por SMS no está disponible todavía.',
            ]);
        }

        $user = DB::table('users')->where('id', $customer->user_id)->first();
        $target = mb_strtolower(trim((string) ($user->email ?? '')));
        if ($target === '') {
            throw ValidationException::withMessages([
                'channel' => 'Registra primero un correo electrónico en tu cuenta.',
            ]);
        }

        $code = (string) random_int(100000, 999999);
        Mail::to($target)->send(new AccountVerificationCode($code));
        session()->put('account_verification_'.$channel, [
            'hash' => Hash::make($code),
            'target' => $target,
            'expires_at' => time() + 600,
        ]);

        audit_log(desego_db(), (int) $customer->user_id, 'customers.verification_code_sent', ['channel' => $channel]);

        $isDev = app()->environment(['local', 'testing']);
        $message = 'Código de verificación enviado a '.$target.'.';

        return [
            'ok' => true,
            'channel' => $channel,
            'dev_code' => $isDev ? $code : null,
            'message' => $isDev ? $message.' (Código de prueba: '.$code.')' : $message,
        ];
    }

    public function confirmVerificationCode(array $input): array
    {
        $customer = $this->customer();
        $data = Validator::make($input, [
            'channel' => 'required|string|in:email,phone',
            'code' => 'required|digits:6',
        ])->validate();

        $channel = $data['channel'];
        $stored = session()->get('account_verification_'.$channel);
        if (! is_array($stored) || empty($stored['hash']) || (int) ($stored['expires_at'] ?? 0) < time()) {
            throw ValidationException::withMessages(['code' => 'El código expiró o no fue solicitado. Solicita uno nuevo.']);
        }
        $user = DB::table('users')->where('id', $customer->user_id)->first();
        $currentTarget = $channel === 'email'
            ? mb_strtolower(trim((string) ($user->email ?? '')))
            : trim((string) ($customer->phone ?? ''));
        if (! hash_equals((string) ($stored['target'] ?? ''), $currentTarget)) {
            session()->forget('account_verification_'.$channel);
            throw ValidationException::withMessages(['code' => 'El dato de contacto cambió. Solicita un nuevo código de verificación.']);
        }
        if (! Hash::check((string) $data['code'], (string) $stored['hash'])) {
            throw ValidationException::withMessages(['code' => 'El código de verificación ingresado no es correcto.']);
        }

        if ($channel === 'email' && Schema::hasColumn('users', 'email_verified_at')) {
            DB::table('users')->where('id', $customer->user_id)->update(['email_verified_at' => now(), 'updated_at' => now()]);
        } elseif ($channel === 'phone' && Schema::hasColumn('customers', 'phone_verified_at')) {
            DB::table('customers')->where('id', $customer->id)->update(['phone_verified_at' => now(), 'updated_at' => now()]);
        }

        session()->forget('account_verification_'.$channel);
        audit_log(desego_db(), (int) $customer->user_id, 'customers.verified_'.$channel, ['customer_id' => $customer->id]);

        return [
            'ok' => true,
            'message' => $channel === 'email' ? '¡Tu correo electrónico quedó verificado!' : '¡Tu teléfono móvil quedó verificado!',
        ];
    }

    public function deactivateAccount(array $input): array
    {
        $customer = $this->customer();
        $data = Validator::make($input, [
            'password' => 'required|string|max:4096',
        ])->validate();

        $user = DB::table('users')->where('id', $customer->user_id)->first();
        abort_unless($user, 404);

        if (! Hash::check($data['password'], $user->password_hash)) {
            throw ValidationException::withMessages(['password' => 'La contraseña actual ingresada no es correcta.']);
        }

        DB::transaction(function () use ($customer): void {
            DB::table('customers')->where('id', $customer->id)->update(['status' => 'inactive', 'updated_at' => now()]);
            DB::table('users')->where('id', $customer->user_id)->update(['status' => 'inactive', 'updated_at' => now()]);
            audit_log(desego_db(), (int) $customer->user_id, 'customers.self_deactivated', ['customer_id' => $customer->id]);
        });

        $this->logout();

        return ['ok' => true, 'message' => 'Tu cuenta ha sido desactivada correctamente. Esperamos verte pronto.'];
    }

    public function deleteAccount(array $input): array
    {
        $customer = $this->customer();
        $data = Validator::make($input, [
            'password' => 'required|string|max:4096',
            'confirmation' => 'required|string|in:ELIMINAR',
        ], [
            'confirmation.in' => 'Escribe ELIMINAR en mayúsculas para confirmar la eliminación definitiva.',
        ])->validate();

        $user = DB::table('users')->where('id', $customer->user_id)->first();
        abort_unless($user, 404);

        if (! Hash::check($data['password'], $user->password_hash)) {
            throw ValidationException::withMessages(['password' => 'La contraseña actual ingresada no es correcta.']);
        }

        DB::transaction(function () use ($customer, $user): void {
            $anonEmail = 'eliminado_'.$user->id.'_'.time().'@anonimo.desego.local';
            $customerUpdate = [
                'full_name' => 'Cuenta eliminada #'.$customer->id,
                'email' => $anonEmail,
                'phone' => null,
                'document_type' => 'SIN_DOCUMENTO',
                'document_number' => null,
                'birth_date' => null,
                'notes' => null,
                'status' => 'inactive',
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('customers', 'deleted_at')) {
                $customerUpdate['deleted_at'] = now();
            }
            if (Schema::hasColumn('customers', 'avatar_url')) {
                $customerUpdate['avatar_url'] = null;
            }
            DB::table('customers')->where('id', $customer->id)->update($customerUpdate);
            DB::table('customer_addresses')->where('customer_id', $customer->id)->update(['status' => 'inactive', 'is_default' => 0, 'updated_at' => now()]);

            $userUpdate = [
                'name' => 'Cuenta eliminada',
                'email' => $anonEmail,
                'password_hash' => Hash::make(Str::random(40)),
                'status' => 'inactive',
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('users', 'google_id')) {
                $userUpdate['google_id'] = null;
            }
            if (Schema::hasColumn('users', 'avatar_url')) {
                $userUpdate['avatar_url'] = null;
            }
            DB::table('users')->where('id', $user->id)->update($userUpdate);
            audit_log(desego_db(), (int) $user->id, 'customers.self_deleted', ['customer_id' => $customer->id]);
        });

        $this->logout();

        return ['ok' => true, 'message' => 'Tu cuenta y tus datos personales fueron eliminados correctamente.'];
    }

    public function changePassword(array $input): array
    {
        $customer = $this->customer();
        $data = Validator::make($input, [
            'current_password' => 'required|string|max:4096',
            'password' => 'required|string|min:10|max:4096|confirmed',
        ])->validate();
        $user = DB::table('users')->where('id', $customer->user_id)->first();
        abort_unless($user, 404);
        if (! Hash::check($data['current_password'], $user->password_hash)) {
            throw ValidationException::withMessages(['current_password' => 'La contraseña actual no es correcta.']);
        }
        $newHash = Hash::make($data['password']);
        DB::table('users')->where('id', $user->id)->update([
            'password_hash' => $newHash,
            'updated_at' => now(),
        ]);
        establish_authenticated_session((int) $user->id, $newHash);

        return ['ok' => true, 'message' => 'Contraseña actualizada correctamente.'];
    }

    public function saveAddress(array $input): array
    {
        $customer = $this->customer();
        $data = Validator::make($input, [
            'id' => 'nullable|integer|min:1',
            'label' => 'required|string|max:40',
            'address_line' => 'required|string|min:5|max:255',
            'district' => 'required|string|max:100',
            'reference' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_default' => 'nullable|boolean',
        ])->validate();

        $editId = isset($data['id']) ? (int) $data['id'] : null;
        unset($data['id']);

        $hasDefault = DB::table('customer_addresses')->where('customer_id', $customer->id)->where('status', 'active')->where('is_default', 1)->exists();
        $isDefault = ! empty($data['is_default']) || ! $hasDefault;
        unset($data['is_default']);

        $id = DB::transaction(function () use ($customer, $data, $isDefault, $editId): int {
            if ($isDefault) {
                DB::table('customer_addresses')->where('customer_id', $customer->id)->update(['is_default' => 0]);
            }
            if ($editId) {
                $existing = DB::table('customer_addresses')->where('id', $editId)->where('customer_id', $customer->id)->where('status', 'active')->first();
                abort_unless($existing, 404);
                DB::table('customer_addresses')->where('id', $editId)->update([
                    ...$data,
                    'is_default' => $isDefault ? 1 : (int) $existing->is_default,
                    'updated_at' => now(),
                ]);

                return $editId;
            }

            return DB::table('customer_addresses')->insertGetId([
                ...$data,
                'customer_id' => $customer->id,
                'recipient_name' => $customer->full_name,
                'province' => 'Lima',
                'department' => 'Lima',
                'status' => 'active',
                'is_default' => $isDefault ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return ['ok' => true, 'id' => $id, 'message' => 'Dirección guardada correctamente.'];
    }

    public function setDefaultAddress(int $id): array
    {
        $customer = $this->customer();
        $address = DB::table('customer_addresses')->where('id', $id)->where('customer_id', $customer->id)->where('status', 'active')->first();
        abort_unless($address, 404);
        DB::transaction(function () use ($customer, $id): void {
            DB::table('customer_addresses')->where('customer_id', $customer->id)->update(['is_default' => 0, 'updated_at' => now()]);
            DB::table('customer_addresses')->where('id', $id)->update(['is_default' => 1, 'updated_at' => now()]);
        });

        return ['ok' => true, 'message' => 'Dirección predeterminada actualizada.'];
    }

    public function redeemReward(int $rewardId): array
    {
        $customer = $this->customer();

        return DB::transaction(function () use ($customer, $rewardId): array {
            $now = gmdate('Y-m-d H:i:s');
            $reward = DB::table('loyalty_rewards')->where('id', $rewardId)->lockForUpdate()->first();
            if (! $reward || $reward->status !== 'active' || (string) $reward->starts_at > $now || ($reward->ends_at !== null && (string) $reward->ends_at < $now) || ($reward->stock_limit !== null && (int) $reward->redeemed_count >= (int) $reward->stock_limit)) {
                throw ValidationException::withMessages(['reward' => 'Esta recompensa ya no está disponible.']);
            }
            DB::table('loyalty_accounts')->insertOrIgnore([
                'customer_id' => $customer->id,
                'points_balance' => 0,
                'lifetime_earned' => 0,
                'lifetime_redeemed' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $account = DB::table('loyalty_accounts')->where('customer_id', $customer->id)->lockForUpdate()->first();
            $cost = (int) $reward->points_cost;
            if ((int) $account->points_balance < $cost) {
                throw ValidationException::withMessages(['reward' => 'No tienes puntos suficientes para canjear este beneficio.']);
            }
            $code = strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
            $newBalance = (int) $account->points_balance - $cost;
            $redemptionId = DB::table('loyalty_redemptions')->insertGetId([
                'redemption_code' => $code,
                'customer_id' => $customer->id,
                'reward_id' => $reward->id,
                'points_spent' => $cost,
                'status' => 'issued',
                'issued_by' => $customer->user_id,
                'issued_at' => now(),
                'notes' => 'Canje desde Mi Cuenta web',
            ]);
            DB::table('loyalty_accounts')->where('customer_id', $customer->id)->update([
                'points_balance' => $newBalance,
                'lifetime_redeemed' => (int) $account->lifetime_redeemed + $cost,
                'updated_at' => now(),
            ]);
            DB::table('loyalty_transactions')->insert([
                'customer_id' => $customer->id,
                'reward_id' => $reward->id,
                'redemption_id' => $redemptionId,
                'user_id' => $customer->user_id,
                'transaction_type' => 'redeem',
                'points_delta' => -$cost,
                'balance_after' => $newBalance,
                'description' => 'Canje de beneficio: '.$reward->name.' · '.$code,
                'created_at' => now(),
            ]);
            DB::table('loyalty_rewards')->where('id', $reward->id)->update([
                'redeemed_count' => (int) $reward->redeemed_count + 1,
                'updated_at' => now(),
            ]);
            audit_log(desego_db(), (int) $customer->user_id, 'loyalty.customer_redeemed', ['customer_id' => $customer->id, 'reward_id' => $reward->id, 'code' => $code]);

            return ['ok' => true, 'redemption_code' => $code, 'balance' => $newBalance, 'message' => 'Beneficio canjeado. Tu código es '.$code.'.'];
        });
    }

    public function deleteAddress(int $id): array
    {
        $customer = $this->customer();
        $address = DB::table('customer_addresses')->where('id', $id)->where('customer_id', $customer->id)->first();
        abort_unless($address, 404);
        DB::table('customer_addresses')->where('id', $id)->update(['status' => 'inactive', 'is_default' => 0, 'updated_at' => now()]);

        return ['ok' => true];
    }

    public function reserve(array $input): array
    {
        $maxTables = (int) config('dining.table_count', 12);
        $data = Validator::make($input, ['customer_name' => 'required|string|min:3|max:100', 'phone' => 'required|string|max:30', 'email' => 'nullable|email|max:190', 'date' => 'required|date_format:Y-m-d', 'time' => 'required|date_format:H:i', 'people' => 'required|integer|min:1|max:40', 'notes' => 'nullable|string|max:500', 'table_ids' => 'sometimes|required|array|min:1|max:'.$maxTables, 'table_ids.*' => 'required|integer|distinct|min:1'])->validate();
        $availability = app(ReservationAvailability::class);
        $availability->start($data['date'], $data['time']);
        $customer = $this->customer(false);
        $actor = $customer?->user_id ?? DB::table('users')->join('roles', 'roles.id', '=', 'users.role_id')->where('roles.code', 'admin')->where('users.status', 'active')->value('users.id') ?? DB::table('users')->value('id');

        return DB::transaction(function () use ($data, $customer, $actor, $availability, $maxTables): array {
            $query = DB::table('restaurant_tables')->where('table_number', '<=', $maxTables)->orderBy('id')->lockForUpdate();
            $selected = isset($data['table_ids']);
            $tables = $selected ? $query->whereIn('id', $data['table_ids'])->get() : $query->where('is_active', 1)->where('capacity', '>=', $data['people'])->orderBy('capacity')->get();
            $start = $availability->start($data['date'], $data['time']);
            if ($selected && $tables->count() !== count($data['table_ids'])) {
                throw ValidationException::withMessages(['table_ids' => 'Alguna mesa seleccionada ya no existe.']);
            }
            if (! $selected) {
                $tables = $tables->filter(fn (object $table): bool => ! $availability->unavailable($table, $start))->take(1);
            }
            if ($tables->isEmpty() || $tables->sum('capacity') < $data['people']) {
                throw ValidationException::withMessages(['table_ids' => 'Selecciona mesas disponibles con asientos suficientes para tu grupo.']);
            }
            foreach ($tables as $candidate) {
                if ($availability->unavailable($candidate, $start)) {
                    throw ValidationException::withMessages(['table_ids' => 'Una mesa seleccionada ya no esta disponible. Actualiza la disponibilidad y elige otra.']);
                }
            }
            $table = $tables->first();
            $id = DB::table('reservations')->insertGetId(['customer_name' => $data['customer_name'], 'customer_id' => $customer?->id, 'customer_phone' => $data['phone'], 'customer_email' => $data['email'] ?? null, 'table_id' => $table->id, 'guest_count' => $data['people'], 'reservation_at' => $start, 'duration_minutes' => 120, 'status' => 'Pendiente', 'notes' => $data['notes'] ?? null, 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
            $code = 'RES-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
            DB::table('reservations')->where('id', $id)->update(['reservation_code' => $code]);
            DB::table('reservation_tables')->insert($tables->map(fn (object $table): array => ['reservation_id' => $id, 'table_id' => $table->id])->values()->all());
            $assigned = $availability->tables($id);
            audit_log(desego_db(), (int) $actor, 'reservations.store_created', ['reservation_id' => $id]);

            return ['ok' => true, 'id' => $id, 'reservation_code' => $code, 'tables' => $assigned, 'seating' => $availability->seating($assigned), 'reservation_at' => $start->toIso8601String(), 'message' => 'Solicitud recibida. El restaurante confirmara tu reserva.'];
        });
    }

    public function cancelReservation(int $id): array
    {
        $customer = $this->customer();
        $record = DB::table('reservations')->where('id', $id)->where('customer_id', $customer->id)->first();
        abort_unless($record, 404);
        $cancellable = ['Pendiente', 'Confirmada', 'Solicitada', 'CONFIRMED', 'PENDING_PAYMENT'];
        if (! in_array($record->status, $cancellable, true) || CarbonImmutable::parse($record->reservation_at, 'UTC')->isPast()) {
            throw ValidationException::withMessages(['reservation' => 'Esta reserva ya no se puede cancelar.']);
        }
        if (in_array($record->status, ['CONFIRMED', 'PENDING_PAYMENT'], true)) {
            return app(ReservationAvailabilityService::class)->cancelReservation($id, 'Cancelada por el cliente desde Mi Cuenta', false, null);
        }
        DB::table('reservations')->where('id', $id)->update([
            'status' => 'Cancelada',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cancelada por el cliente desde Mi Cuenta',
            'updated_at' => now(),
        ]);
        audit_log(desego_db(), (int) $customer->user_id, 'reservations.customer_cancelled', ['reservation_id' => $id]);

        return ['ok' => true, 'message' => 'Reserva cancelada correctamente.'];
    }

    public function rescheduleReservation(int $id, array $input): array
    {
        $customer = $this->customer();
        $data = Validator::make($input, [
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|date_format:H:i',
            'people' => 'required|integer|min:1|max:40',
            'notes' => 'nullable|string|max:500',
        ])->validate();

        $availability = app(ReservationAvailability::class);
        $newStart = $availability->start($data['date'], $data['time']);
        $maxTables = (int) config('dining.table_count', 12);

        return DB::transaction(function () use ($id, $customer, $data, $newStart): array {
            $record = DB::table('reservations')->where('id', $id)->where('customer_id', $customer->id)->lockForUpdate()->first();
            abort_unless($record, 404);
            $editable = ['Pendiente', 'Confirmada', 'Solicitada', 'CONFIRMED'];
            if (! in_array($record->status, $editable, true) || CarbonImmutable::parse($record->reservation_at, 'UTC')->isPast()) {
                throw ValidationException::withMessages(['reservation' => 'Esta reserva ya no admite reprogramación.']);
            }

            $service = app(ReservationAvailabilityService::class);
            $duration = max(30, (int) ($record->duration_minutes ?: 120));

            try {
                $chosenTables = $service->findCompatibleTables((int) $data['people'], $newStart, $duration, null, $id);
            } catch (ValidationException) {
                throw ValidationException::withMessages(['time' => 'No hay mesas suficientes disponibles en el nuevo horario elegido. Prueba otra hora o fecha.']);
            }

            $chosen = collect($chosenTables);
            $primaryId = (int) $chosen->first()->id;
            $config = DB::table('reservation_configurations')->orderBy('id')->first();
            $tolerance = (int) ($config->tolerance_minutes ?? 15);

            DB::table('reservations')->where('id', $id)->update([
                'table_id' => $primaryId,
                'guest_count' => (int) $data['people'],
                'reservation_at' => $newStart,
                'arrival_deadline' => $newStart->addMinutes($tolerance),
                'notes' => array_key_exists('notes', $data) ? ($data['notes'] ?: null) : $record->notes,
                'updated_at' => now(),
            ]);

            DB::table('reservation_tables')->where('reservation_id', $id)->delete();
            DB::table('reservation_tables')->insert(
                $chosen->map(fn (object $t): array => ['reservation_id' => $id, 'table_id' => (int) $t->id])->values()->all()
            );

            audit_log(desego_db(), (int) $customer->user_id, 'reservations.customer_rescheduled', [
                'reservation_id' => $id,
                'new_reservation_at' => $newStart->toIso8601String(),
                'people' => (int) $data['people'],
            ]);

            return [
                'ok' => true,
                'message' => 'Tu reserva fue reprogramada con éxito para el '.$data['date'].' a las '.$data['time'].'.',
            ];
        });
    }

    public function modifyPendingOrder(int $id, array $input): array
    {
        $customer = $this->customer();
        $data = Validator::make($input, [
            'notes' => 'nullable|string|max:300',
            'recipient_phone' => 'nullable|string|max:30',
            'address_line' => 'nullable|string|min:5|max:255',
            'reference' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:100',
        ])->validate();

        return DB::transaction(function () use ($id, $customer, $data): array {
            $order = DB::table('orders')->where('id', $id)->where('customer_id', $customer->id)->lockForUpdate()->first();
            abort_unless($order, 404);
            abort_if(app(NiubizOrderPayment::class)->hasPendingAttempt($id), 409, 'Hay un pago Niubiz en curso. Espera su confirmación antes de modificar el pedido.');
            if ($order->status !== 'Pendiente') {
                throw ValidationException::withMessages(['order' => 'El pedido ya entró en preparación y no puede modificarse en línea. Escríbenos por WhatsApp si necesitas ayuda urgente.']);
            }

            $orderUpdate = ['updated_at' => now()];
            if (array_key_exists('notes', $data)) {
                $cleanNote = trim((string) ($data['notes'] ?? ''));
                $orderUpdate['notes'] = $cleanNote !== '' ? $cleanNote : null;
            }
            DB::table('orders')->where('id', $id)->update($orderUpdate);

            if ($order->order_type === 'Delivery') {
                $delivery = DB::table('order_delivery_details')->where('order_id', $id)->first();
                if ($delivery) {
                    $addrLine = ! empty($data['address_line']) ? trim((string) $data['address_line']) : (string) $delivery->address_line;
                    if (! empty($data['reference'])) {
                        $addrLine = explode(' · ', $addrLine)[0].' · '.trim((string) $data['reference']);
                    }
                    $delUpdate = [
                        'address_line' => mb_substr($addrLine, 0, 255),
                        'updated_at' => now(),
                    ];
                    if (! empty($data['recipient_phone'])) {
                        $delUpdate['recipient_phone'] = trim((string) $data['recipient_phone']);
                    }
                    if (! empty($data['district'])) {
                        $delUpdate['zone'] = trim((string) $data['district']).', Lima, Lima';
                    }
                    DB::table('order_delivery_details')->where('order_id', $id)->update($delUpdate);
                }
            }

            audit_log(desego_db(), (int) $customer->user_id, 'orders.customer_modified', ['order_id' => $id]);

            return ['ok' => true, 'message' => 'Los datos de tu pedido '.$order->order_number.' fueron actualizados.'];
        });
    }

    public function orderReceipt(int $id): array
    {
        $customer = $this->customer();
        $order = DB::table('orders')->where('id', $id)->where('customer_id', $customer->id)->first();
        abort_unless($order, 404);

        $items = DB::table('order_items')->where('order_id', $order->id)->get()->map(function (object $item): object {
            $item->selected_options = ! empty($item->selected_options) && is_string($item->selected_options)
                ? (json_decode($item->selected_options, true) ?: [])
                : [];

            return $item;
        });
        $delivery = DB::table('order_delivery_details')->where('order_id', $order->id)->first();
        $invoice = DB::table('invoices')->where('order_id', $order->id)->first();
        $settings = DB::table('system_settings')->pluck('setting_value', 'setting_key')->all();

        return [
            'order' => $order,
            'items' => $items,
            'delivery' => $delivery,
            'invoice' => $invoice,
            'customer' => $customer,
            'restaurant' => [
                'name' => $settings['restaurant_name'] ?? 'DeSego Cevichería',
                'address' => $settings['restaurant_address'] ?? 'UCV 19, Lote 13, Zona A, Huaycán, Ate',
                'whatsapp' => $settings['restaurant_whatsapp'] ?? '+51 922 119 238',
            ],
        ];
    }

    public function cancelOrder(int $id): array
    {
        $customer = $this->customer();

        return DB::transaction(function () use ($customer, $id): array {
            $order = DB::table('orders')->where('id', $id)->where('customer_id', $customer->id)->lockForUpdate()->first();
            abort_unless($order, 404);
            if ($order->status !== 'Pendiente' || $order->payment_status !== 'Pendiente') {
                throw ValidationException::withMessages(['order' => 'Solo puedes cancelar un pedido pendiente y sin cobrar.']);
            }

            abort_if(app(NiubizOrderPayment::class)->hasPendingAttempt($id), 409, 'Hay un pago Niubiz en curso. Espera su confirmación antes de cancelar el pedido.');
            foreach (DB::table('order_ingredients')->where('order_id', $id)->whereNull('restored_at')->orderBy('ingredient_id')->get() as $line) {
                $ingredient = DB::table('ingredients')->where('id', $line->ingredient_id)->lockForUpdate()->first();
                DB::table('ingredients')->where('id', $line->ingredient_id)->update(['stock_quantity' => $ingredient->stock_quantity + $line->consumed_quantity, 'updated_at' => now()]);
                desego_add_adjustment_cost_layer(desego_db(), (int) $line->ingredient_id, (float) $line->consumed_quantity, (float) $line->unit_cost, 'Cancelacion de pedido '.$order->order_number);
                DB::table('inventory_movements')->insert(['ingredient_id' => $line->ingredient_id, 'user_id' => $customer->user_id, 'movement_type' => 'adjustment', 'quantity_delta' => $line->consumed_quantity, 'previous_quantity' => $ingredient->stock_quantity, 'new_quantity' => $ingredient->stock_quantity + $line->consumed_quantity, 'unit_cost' => $line->unit_cost, 'reference' => $order->order_number, 'notes' => 'Cancelacion del cliente; devolucion de consumo pendiente.', 'created_at' => now()]);
                DB::table('order_ingredients')->where('order_id', $id)->where('ingredient_id', $line->ingredient_id)->update(['restored_at' => now()]);
            }
            DB::table('orders')->where('id', $id)->update(['status' => 'Cancelado', 'cancelled_at' => now(), 'updated_at' => now()]);
            audit_log(desego_db(), (int) $customer->user_id, 'orders.customer_cancelled', ['order_id' => $id]);

            return ['ok' => true, 'message' => 'Pedido cancelado.'];
        });
    }

    public function sections(): array
    {
        return [
            'dashboard' => ['title' => 'Vista general', 'module' => null, 'permission' => 'dashboard.view'],
            'pedidos' => ['title' => 'Pedidos', 'module' => 'orders', 'permission' => 'orders.view'],
            'carta' => ['title' => 'Carta y productos', 'module' => 'catalog', 'permission' => 'menu.view'],
            'categorias' => ['title' => 'Categorías', 'module' => 'catalog', 'view' => 'categories', 'permission' => 'categories.manage'],
            'insumos' => ['title' => 'Ingredientes', 'module' => 'catalog', 'view' => 'ingredients', 'permission' => 'ingredients.manage'],
            'alergenos' => ['title' => 'Alérgenos', 'module' => 'catalog', 'view' => 'allergens', 'permission' => 'allergens.manage'],
            'inventario' => ['title' => 'Inventario', 'module' => 'inventory', 'permission' => 'inventory.view'],
            'clientes' => ['title' => 'Clientes', 'module' => 'customers', 'permission' => 'customers.view'],
            'reservas' => ['title' => 'Reservas y mesas', 'module' => 'reservations', 'permission' => 'reservations.view'],
            'salon' => ['title' => 'Salón en vivo', 'module' => null, 'permission' => 'reservations.view'],
            'caja' => ['title' => 'Caja y cobros', 'module' => 'cash', 'permission' => 'cash.manage'],
            'cocina' => ['title' => 'Cocina', 'module' => 'operations', 'view' => 'kitchen', 'permission' => 'kitchen.view'],
            'delivery' => ['title' => 'Reparto y entregas', 'module' => 'operations', 'view' => 'delivery', 'permission' => 'delivery.view'],
            'compras' => ['title' => 'Compras y proveedores', 'module' => 'purchasing', 'permission' => 'purchases.view'],
            'promociones' => ['title' => 'Promociones', 'module' => 'promotions', 'permission' => 'promotions.view'],
            'fidelizacion' => ['title' => 'Fidelización', 'module' => 'loyalty', 'permission' => 'loyalty.view'],
            'resenas' => ['title' => 'Reseñas', 'module' => 'reviews', 'view' => 'moderation', 'permission' => 'reviews.view'],
            'configuracion' => ['title' => 'Horarios y local', 'module' => 'admin-tools', 'view' => 'settings', 'permission' => 'settings.manage'],
            'equipo' => ['title' => 'Equipo', 'module' => 'workforce', 'view' => 'employees', 'permission' => 'employees.view'],
            'usuarios' => ['title' => 'Usuarios y accesos', 'module' => 'users', 'permission' => 'users.view'],
            'roles' => ['title' => 'Roles y permisos', 'module' => 'roles', 'permission' => 'roles.manage'],
            'chatbot' => ['title' => 'Chats del asistente', 'module' => null, 'permission' => 'support.view'],
            'reportes' => ['title' => 'Reportes', 'module' => 'billing-reports', 'view' => 'reports', 'permission' => 'reports.view'],
            'auditoria' => ['title' => 'Auditoría', 'module' => 'admin-tools', 'view' => 'audit', 'permission' => 'audit.view'],
            'backups' => ['title' => 'Copias de seguridad', 'module' => 'admin-tools', 'view' => 'backups', 'permission' => 'backups.view'],
            'soporte' => ['title' => 'Soporte', 'module' => 'communications', 'view' => 'tickets', 'permission' => 'support.view'],
        ];
    }

    public function dashboard(): array
    {
        $user = current_user();
        $permissions = $user['permissions'] ?? [];
        $isAdmin = ($user['role_code'] ?? '') === 'admin';
        $canOrders = $isAdmin || in_array('orders.view', $permissions, true);
        $canInventory = $isAdmin || in_array('inventory.view', $permissions, true);
        $canReservations = $isAdmin || in_array('reservations.view', $permissions, true);
        $canCustomers = $isAdmin || in_array('customers.view', $permissions, true);
        $start = now('America/Lima')->startOfDay()->utc();
        $monthStart = now('America/Lima')->startOfMonth()->utc();

        $topProducts = [];
        $ordersByModality = [];
        if ($canOrders) {
            $topProducts = DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->where('o.status', '<>', 'Cancelado')
                ->selectRaw('oi.product_id, oi.product_name, SUM(oi.quantity) as units_sold, SUM(oi.line_total) as revenue')
                ->groupBy('oi.product_id', 'oi.product_name')
                ->orderByDesc('units_sold')
                ->limit(5)
                ->get();

            $ordersByModality = DB::table('orders')
                ->where('status', '<>', 'Cancelado')
                ->selectRaw('order_type, COUNT(*) as total_orders, COALESCE(SUM(total), 0) as revenue')
                ->groupBy('order_type')
                ->orderByDesc('total_orders')
                ->get();
        }

        $pendingReservationsCount = null;
        $upcomingReservations = [];
        if ($canReservations) {
            $activeResStatuses = ['Pendiente', 'Confirmada', 'Solicitada', 'CONFIRMED', 'CHECKED_IN', 'IN_SERVICE', 'Sentada', 'PENDING_PAYMENT'];
            $pendingReservationsCount = DB::table('reservations')
                ->whereIn('status', $activeResStatuses)
                ->where('reservation_at', '>=', $start)
                ->count();
            $upcomingReservations = DB::table('reservations')
                ->whereIn('status', $activeResStatuses)
                ->where('reservation_at', '>=', $start)
                ->orderBy('reservation_at')
                ->limit(6)
                ->get(['id', 'reservation_code', 'customer_name', 'guest_count', 'reservation_at', 'status']);
        }

        return [
            'sales' => $canOrders ? (float) DB::table('orders')->where('payment_status', 'Pagado')->where('paid_at', '>=', $start)->sum('total') : null,
            'monthly_sales' => $canOrders ? (float) DB::table('orders')->where('payment_status', 'Pagado')->where('paid_at', '>=', $monthStart)->sum('total') : null,
            'orders_today' => $canOrders ? DB::table('orders')->where('created_at', '>=', $start)->count() : null,
            'pending' => $canOrders ? DB::table('orders')->whereIn('status', ['Pendiente', 'Preparando', 'Listo', 'En reparto'])->count() : null,
            'pending_reservations' => $pendingReservationsCount,
            'customers' => $canCustomers ? DB::table('customers')->where('status', 'active')->count() : null,
            'recent_orders' => $canOrders ? DB::table('orders')->orderByDesc('id')->limit(8)->get(['id', 'order_number', 'customer_name', 'order_type', 'status', 'payment_status', 'total', 'created_at']) : [],
            'top_products' => $topProducts,
            'orders_by_modality' => $ordersByModality,
            'upcoming_reservations' => $upcomingReservations,
            'low_stock' => $canInventory ? DB::table('ingredients')->where('status', 'active')->whereColumn('stock_quantity', '<=', 'min_stock')->get(['id', 'name', 'stock_quantity', 'min_stock', 'unit']) : [],
            'daily_sales' => $canOrders ? DB::table('orders')->selectRaw('DATE(DATE_SUB(paid_at, INTERVAL 5 HOUR)) AS date, SUM(total) AS total')->where('payment_status', 'Pagado')->where('paid_at', '>=', now()->subDays(7))->groupByRaw('DATE(DATE_SUB(paid_at, INTERVAL 5 HOUR))')->orderBy('date')->get() : [],
        ];
    }
}
