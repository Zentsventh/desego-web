<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Exceptions\Desego\StorefrontProblem;
use App\Http\Controllers\Controller;
use App\Services\NiubizOrderPayment;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use PDO;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function App\Support\Desego\audit_log;
use function App\Support\Desego\desego_consume_cost_layers;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\desego_system_setting;
use function App\Support\Desego\json_body;
use function App\Support\Desego\json_response;
use function App\Support\Desego\require_csrf;
use function App\Support\Desego\require_method;
use function App\Support\Desego\text_length;

class StorefrontController extends Controller
{
    private const DESEGO_WEB_DELIVERY_FEE = 5.00;

    public function __invoke(): Response
    {
        try {
            $pdo = desego_db();
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            if ($method === 'GET') {
                $view = (string) (request()->query()['view'] ?? 'products');
                if ($view === 'profile') {
                    json_response(['ok' => true, 'profile' => $this->storefront_customer_profile($pdo)]);
                }
                if ($view === 'order') {
                    $orderId = filter_var(session()->get('storefront_last_order_id') ?? null, FILTER_VALIDATE_INT);
                    if (! $orderId) {
                        json_response(['ok' => true, 'order' => null]);
                    }
                    $stmt = $pdo->prepare(
                        "SELECT id, order_number, status, payment_status, total, created_at
                         FROM orders WHERE id = :id AND order_channel = 'web' LIMIT 1"
                    );
                    $stmt->execute(['id' => $orderId]);
                    $row = $stmt->fetch();
                    if ($row) {
                        $row['id'] = (int) $row['id'];
                        $row['total'] = (float) $row['total'];
                    }
                    json_response(['ok' => true, 'order' => $row ?: null]);
                }
                if ($view === 'coverage') {
                    $lat = filter_var(request()->query()['lat'] ?? null, FILTER_VALIDATE_FLOAT);
                    $lng = filter_var(request()->query()['lng'] ?? null, FILTER_VALIDATE_FLOAT);
                    if ($lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                        throw new StorefrontProblem('Coordenadas geográficas no válidas.', 422);
                    }
                    $zones = $this->storefront_active_zones($pdo);
                    $matched = $this->storefront_match_zone((float) $lat, (float) $lng, $zones);
                    json_response([
                        'ok' => true,
                        'covered' => $matched !== null,
                        'zone' => $matched,
                    ]);
                }
                if ($view !== 'products') {
                    throw new StorefrontProblem('Vista no válida.', 404);
                }
                $categories = $pdo->query(
                    "SELECT id, name, slug FROM categories WHERE status = 'active' ORDER BY FIELD(slug, 'entradas', 'ceviches', 'duos-trios-marinos', 'sopas', 'arroces', 'pastas', 'frituras', 'bebidas', 'cervezas'), name"
                )->fetchAll();
                foreach ($categories as &$category) {
                    $category['id'] = (int) $category['id'];
                }
                unset($category);

                $products = $pdo->query(
                    "SELECT p.id, p.code, p.name, p.description, p.price, p.emoji, p.slug, p.status, p.is_featured, p.is_popular, p.is_new, p.included_items, p.image_url,
                            c.id AS category_id, c.name AS category, c.slug AS category_slug,
                            (SELECT COUNT(*) FROM product_ingredients pi WHERE pi.product_id = p.id) AS recipe_count,
                            (SELECT GROUP_CONCAT(DISTINCT a.name ORDER BY a.name SEPARATOR ', ')
                             FROM product_ingredients pi2
                             INNER JOIN ingredient_allergens ia ON ia.ingredient_id = pi2.ingredient_id
                             INNER JOIN allergens a ON a.id = ia.allergen_id AND a.status = 'active'
                             WHERE pi2.product_id = p.id) AS allergens
                     FROM products p
                     INNER JOIN categories c ON c.id = p.category_id
                     WHERE c.status = 'active'
                     ORDER BY FIELD(c.slug, 'entradas', 'ceviches', 'duos-trios-marinos', 'sopas', 'arroces', 'pastas', 'frituras', 'bebidas', 'cervezas'), p.code, p.id"
                )->fetchAll();

                $galleryByProduct = [];
                try {
                    foreach ($pdo->query('SELECT id, product_id, image_url, alt_text, sort_order FROM product_images ORDER BY sort_order, id')->fetchAll() as $imgRow) {
                        $galleryByProduct[(int) $imgRow['product_id']][] = [
                            'id' => (int) $imgRow['id'],
                            'image_url' => (string) $imgRow['image_url'],
                            'alt_text' => (string) ($imgRow['alt_text'] ?? ''),
                            'sort_order' => (int) ($imgRow['sort_order'] ?? 0),
                        ];
                    }
                } catch (Throwable) {
                }

                $optionGroupsByProduct = [];
                try {
                    $groups = $pdo->query('SELECT id, product_id, name, is_required, max_selections, sort_order FROM product_option_groups WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
                    $optionsByGroup = [];
                    foreach ($pdo->query('SELECT id, group_id, name, price_delta, sort_order FROM product_options WHERE is_available = 1 ORDER BY sort_order, id')->fetchAll() as $optRow) {
                        $optionsByGroup[(int) $optRow['group_id']][] = [
                            'id' => (int) $optRow['id'],
                            'name' => (string) $optRow['name'],
                            'price_delta' => (float) $optRow['price_delta'],
                            'sort_order' => (int) ($optRow['sort_order'] ?? 0),
                        ];
                    }
                    foreach ($groups as $grpRow) {
                        $gid = (int) $grpRow['id'];
                        $opts = $optionsByGroup[$gid] ?? [];
                        if ($opts) {
                            $optionGroupsByProduct[(int) $grpRow['product_id']][] = [
                                'id' => $gid,
                                'name' => (string) $grpRow['name'],
                                'is_required' => (bool) $grpRow['is_required'],
                                'max_selections' => (int) $grpRow['max_selections'],
                                'options' => $opts,
                            ];
                        }
                    }
                } catch (Throwable) {
                }

                foreach ($products as &$product) {
                    $pid = (int) $product['id'];
                    $product['id'] = $pid;
                    $product['category_id'] = (int) $product['category_id'];
                    $product['price'] = (float) $product['price'];
                    $product['recipe_count'] = (int) $product['recipe_count'];
                    $product['can_order'] = ($product['status'] ?? 'available') === 'available';
                    $product['is_featured'] = (bool) ($product['is_featured'] ?? false);
                    $product['is_popular'] = (bool) ($product['is_popular'] ?? false);
                    $product['is_new'] = (bool) ($product['is_new'] ?? false);
                    $product['allergens'] = (string) ($product['allergens'] ?? '');
                    $product['gallery'] = $galleryByProduct[$pid] ?? [];
                    $product['option_groups'] = $optionGroupsByProduct[$pid] ?? [];
                }
                unset($product);

                $hours = [];
                try {
                    $hours = $pdo->query('SELECT day_of_week, day_name, open_time, close_time, is_closed FROM business_hours ORDER BY day_of_week')->fetchAll();
                    foreach ($hours as &$hourRow) {
                        $hourRow['day_of_week'] = (int) $hourRow['day_of_week'];
                        $hourRow['is_closed'] = (bool) $hourRow['is_closed'];
                    }
                    unset($hourRow);
                } catch (Throwable) {
                }

                $zones = $this->storefront_active_zones($pdo);

                json_response([
                    'ok' => true,
                    'categories' => $categories,
                    'products' => $products,
                    'delivery_fee' => self::DESEGO_WEB_DELIVERY_FEE,
                    'business_hours' => $hours,
                    'delivery_zones' => $zones,
                    'promotions' => $pdo->query("SELECT id, name, description, discount_type, discount_value, max_discount, min_subtotal FROM promotions WHERE promotion_type = 'automatic' AND status = 'active' AND starts_at <= UTC_TIMESTAMP() AND (ends_at IS NULL OR ends_at >= UTC_TIMESTAMP()) ORDER BY id DESC LIMIT 8")->fetchAll(),
                    'csrfToken' => (string) (session()->get('csrf_token') ?? ''),
                ]);
            }

            require_method('POST');
            $body = json_body();
            require_csrf($body);
            $action = (string) ($body['action'] ?? '');

            if ($action === 'quote') {
                $cart = $this->storefront_normalize_cart($pdo, $body['items'] ?? null);
                $couponCode = strtoupper(trim((string) ($body['coupon_code'] ?? '')));
                if (strlen($couponCode) > 40 || ($couponCode !== '' && ! preg_match('/^[A-Z0-9_-]{3,40}$/', $couponCode))) {
                    throw new StorefrontProblem('El código debe tener de 3 a 40 caracteres alfanuméricos.', 422);
                }
                $email = strtolower(trim((string) ($body['customer_email'] ?? '')));
                $phone = trim((string) ($body['customer_phone'] ?? ''));
                $customerKey = ($email !== '' || $phone !== '') ? $this->storefront_customer_key($email, $phone) : '';
                $chosen = $this->storefront_choose_promotion($pdo, (float) $cart['subtotal'], $couponCode, $customerKey, $email, $phone, false);
                $discount = $chosen ? (float) $chosen['discount'] : 0.0;
                $rawType = (string) ($body['order_type'] ?? 'Delivery');
                $isDelivery = $rawType === 'Delivery';
                $fee = 0.0;
                $feeConfirmed = true;
                if ($isDelivery) {
                    $zoneId = filter_var($body['delivery_zone_id'] ?? null, FILTER_VALIDATE_INT);
                    $fee = self::DESEGO_WEB_DELIVERY_FEE;
                    if ($zoneId) {
                        $zoneRow = $this->storefront_find_zone($pdo, (int) $zoneId);
                        if ($zoneRow) {
                            $fee = (float) $zoneRow['delivery_fee'];
                            $feeConfirmed = (bool) $zoneRow['fee_confirmed'];
                        }
                    }
                }
                $promoSummary = null;
                if ($chosen) {
                    if (! empty($chosen['promotion'])) {
                        $promoSummary = [
                            'id' => (int) $chosen['promotion']['id'],
                            'name' => (string) $chosen['promotion']['name'],
                            'code' => $chosen['promotion']['code'] ? (string) $chosen['promotion']['code'] : null,
                        ];
                    } elseif (! empty($chosen['loyalty_redemption'])) {
                        $promoSummary = [
                            'id' => 0,
                            'name' => 'Club DeSego: '.(string) $chosen['loyalty_redemption']['reward_name'],
                            'code' => (string) $chosen['loyalty_redemption']['redemption_code'],
                        ];
                    }
                }
                json_response([
                    'ok' => true,
                    'quote' => [
                        'subtotal' => (float) $cart['subtotal'],
                        'delivery_fee' => $fee,
                        'fee_confirmed' => $feeConfirmed,
                        'discount' => $discount,
                        'total' => round(max(0, (float) $cart['subtotal'] - $discount) + $fee, 2),
                        'promotion' => $promoSummary,
                        'coupon_code' => $couponCode,
                    ],
                ]);
            }

            if ($action !== 'checkout') {
                throw new StorefrontProblem('Acción no válida.', 404);
            }

            // Limitación básica por sesión para evitar envíos repetidos; se recomienda además un WAF/rate limit en producción.
            $now = time();
            $recent = array_values(array_filter((array) (session()->get('storefront_checkout_attempts') ?? []), static fn ($t) => is_numeric($t) && (int) $t > $now - 900));
            if (count($recent) >= 5) {
                throw new StorefrontProblem('Has realizado varios intentos. Espera unos minutos antes de volver a pedir.', 429);
            }
            $recent[] = $now;
            session()->put('storefront_checkout_attempts', $recent);

            // Campo trampa para bots.
            if (trim((string) ($body['website'] ?? '')) !== '') {
                json_response(['ok' => true, 'message' => 'Tu solicitud fue recibida.']);
            }

            $rawType = (string) ($body['order_type'] ?? 'Delivery');
            if (! in_array($rawType, ['Delivery', 'Para llevar', 'Consumo en local', 'Mesa'], true)) {
                throw new StorefrontProblem('Tipo de entrega no valido.');
            }
            $isDelivery = $rawType === 'Delivery';
            $orderType = match ($rawType) {
                'Para llevar', 'Recojo' => 'Para llevar',
                'Consumo en local', 'Mesa' => 'Mesa',
                default => 'Delivery',
            };
            $tableLabel = $orderType === 'Mesa' ? trim((string) ($body['table_label'] ?? '')) : null;
            $name = trim((string) ($body['customer_name'] ?? ''));
            $email = strtolower(trim((string) ($body['email'] ?? '')));
            $phone = trim((string) ($body['phone'] ?? ''));
            $phoneDigits = $this->storefront_phone_digits($phone);
            $addressIdRaw = $body['saved_address_id'] ?? null;
            $savedAddressId = ($addressIdRaw === null || $addressIdRaw === '' || (int) $addressIdRaw === 0)
                ? null : filter_var($addressIdRaw, FILTER_VALIDATE_INT);
            $addressLine = trim((string) ($body['address_line'] ?? ''));
            $reference = trim((string) ($body['reference'] ?? ''));
            $district = trim((string) ($body['district'] ?? ''));
            $province = trim((string) ($body['province'] ?? 'Lima'));
            $department = trim((string) ($body['department'] ?? 'Lima'));
            $latRaw = $body['latitude'] ?? null;
            $lngRaw = $body['longitude'] ?? null;
            $latitude = ($latRaw === null || $latRaw === '') ? null : filter_var($latRaw, FILTER_VALIDATE_FLOAT);
            $longitude = ($lngRaw === null || $lngRaw === '') ? null : filter_var($lngRaw, FILTER_VALIDATE_FLOAT);
            if (($latitude !== null && ($latitude === false || $latitude < -90 || $latitude > 90))
                || ($longitude !== null && ($longitude === false || $longitude < -180 || $longitude > 180))) {
                throw new StorefrontProblem('Las coordenadas de ubicación seleccionadas no son válidas.', 422);
            }
            $notes = trim((string) ($body['notes'] ?? ''));
            $paymentPreference = (string) ($body['payment_preference'] ?? 'Efectivo');
            $couponCode = strtoupper(trim((string) ($body['coupon_code'] ?? '')));
            $checkoutKey = strtolower(trim((string) ($body['checkout_key'] ?? '')));
            $itemsInput = $body['items'] ?? null;

            if (text_length($name) < 2 || text_length($name) > 100) {
                throw new StorefrontProblem('Escribe tu nombre completo (2 a 100 caracteres).');
            }
            if (empty($body['consent'])) {
                throw new StorefrontProblem('Confirma que autorizas el uso de tus datos para gestionar este pedido.');
            }
            if ($orderType !== 'Mesa' && ($phoneDigits === '' || strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15 || text_length($phone) > 30)) {
                throw new StorefrontProblem('Ingresa un teléfono válido para coordinar la entrega.');
            }
            if ($email !== '' && (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190)) {
                throw new StorefrontProblem('El correo electrónico no es válido.');
            }
            $allowedPayments = $this->storefront_payment_methods($pdo);
            if (app(NiubizOrderPayment::class)->available()) {
                $allowedPayments[] = 'Niubiz';
            }
            if (! in_array($paymentPreference, $allowedPayments, true)) {
                throw new StorefrontProblem('Selecciona una forma de pago válida.');
            }
            if (text_length($reference) > 255 || text_length($district) > 100 || text_length($province) > 100 || text_length($department) > 100 || text_length($notes) > 300) {
                throw new StorefrontProblem('Uno de los campos supera el tamaño permitido.');
            }
            if (strlen($couponCode) > 40 || ($couponCode !== '' && ! preg_match('/^[A-Z0-9_-]{3,40}$/', $couponCode))) {
                throw new StorefrontProblem('El cupón debe tener de 3 a 40 caracteres alfanuméricos.');
            }
            if ($savedAddressId !== null && (! $savedAddressId || $savedAddressId < 1)) {
                throw new StorefrontProblem('Dirección guardada no válida.');
            }
            if ($isDelivery && $savedAddressId === null && (text_length($addressLine) < 5 || text_length($addressLine) > 255 || $district === '')) {
                throw new StorefrontProblem('Escribe una dirección completa y selecciona tu distrito.');
            }
            if (! is_array($itemsInput) || count($itemsInput) < 1 || count($itemsInput) > 30) {
                throw new StorefrontProblem('Tu carrito debe tener entre 1 y 30 productos.');
            }
            if (! preg_match('/^[a-f0-9-]{32,36}$/', $checkoutKey)) {
                throw new StorefrontProblem('No se pudo validar el identificador del pedido. Actualiza la página e inténtalo de nuevo.');
            }

            if ($isDelivery && $latitude !== null && $longitude !== null) {
                $activeZones = $this->storefront_active_zones($pdo);
                if ($activeZones && $this->storefront_match_zone((float) $latitude, (float) $longitude, $activeZones) === null) {
                    throw new StorefrontProblem('La ubicación seleccionada en el mapa está fuera de nuestra zona de cobertura actual.', 422);
                }
            }

            $parsedLines = $this->storefront_parse_cart_lines($itemsInput);

            $existingStmt = $pdo->prepare("SELECT id, order_number, total FROM orders WHERE web_checkout_key = :key AND order_channel = 'web' LIMIT 1");
            $existingStmt->execute(['key' => $checkoutKey]);
            if ($existing = $existingStmt->fetch()) {
                session()->put('storefront_last_order_id', (int) $existing['id']);
                json_response(['ok' => true, 'duplicate' => true, 'order_id' => (int) $existing['id'], 'order_number' => $existing['order_number'], 'total' => (float) $existing['total'], 'message' => 'Este pedido ya fue registrado.']);
            }

            $pdo->beginTransaction();
            try {
                $adminId = $pdo->query("SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.status = 'active' AND r.code = 'admin' ORDER BY u.id LIMIT 1 FOR UPDATE")->fetchColumn();
                if (! $adminId) {
                    throw new StorefrontProblem('La tienda no está disponible todavía. El restaurante debe configurar una cuenta administradora activa.', 503);
                }
                $adminId = (int) $adminId;

                $profile = $this->storefront_customer_profile($pdo);
                $customerId = null;
                $sessionCustomer = $profile['customer'] ?? null;
                $identityMatches = $sessionCustomer
                    && (
                        ($sessionCustomer['phone'] === '' || $this->storefront_phone_digits((string) $sessionCustomer['phone']) === $phoneDigits)
                        && ($sessionCustomer['email'] === '' || strtolower((string) $sessionCustomer['email']) === $email)
                    );

                if ($savedAddressId !== null) {
                    if (! $sessionCustomer) {
                        throw new StorefrontProblem('La sesión actual no puede usar esa dirección guardada. Selecciona una dirección nueva o vuelve a iniciar tu pedido.', 403);
                    }
                    $customerId = (int) $sessionCustomer['id'];
                    $addressStmt = $pdo->prepare("SELECT * FROM customer_addresses WHERE id = :address_id AND customer_id = :customer_id AND status = 'active' LIMIT 1 FOR UPDATE");
                    $addressStmt->execute(['address_id' => $savedAddressId, 'customer_id' => $customerId]);
                    $address = $addressStmt->fetch();
                    if (! $address) {
                        throw new StorefrontProblem('La dirección guardada ya no está disponible. Actualiza tus direcciones.', 409);
                    }
                    if ($latitude === null && isset($address['latitude']) && $address['latitude'] !== null) {
                        $latitude = (float) $address['latitude'];
                    }
                    if ($longitude === null && isset($address['longitude']) && $address['longitude'] !== null) {
                        $longitude = (float) $address['longitude'];
                    }
                } else {
                    if ($identityMatches) {
                        $customerId = (int) $sessionCustomer['id'];
                        $updateCustomer = $pdo->prepare('UPDATE customers SET full_name = :name, phone = :phone, email = :email, updated_at = NOW() WHERE id = :id');
                        $updateCustomer->execute(['name' => $name, 'phone' => $phone, 'email' => $email !== '' ? $email : null, 'id' => $customerId]);
                    } else {
                        $insertCustomer = $pdo->prepare(
                            "INSERT INTO customers (customer_code, full_name, document_type, document_number, phone, email, status, created_by, updated_by, created_at, updated_at)
                             VALUES (NULL, :name, 'SIN_DOCUMENTO', NULL, :phone, :email, 'active', :created_by, :updated_by, NOW(), NOW())"
                        );
                        $insertCustomer->execute(['name' => $name, 'phone' => $phone, 'email' => $email !== '' ? $email : null, 'created_by' => $adminId, 'updated_by' => $adminId]);
                        $customerId = (int) $pdo->lastInsertId();
                        $customerCode = 'CLI-'.str_pad((string) $customerId, 6, '0', STR_PAD_LEFT);
                        $pdo->prepare('UPDATE customers SET customer_code = :code WHERE id = :id')->execute(['code' => $customerCode, 'id' => $customerId]);
                    }
                    if ($isDelivery) {
                        $pdo->prepare('UPDATE customer_addresses SET is_default = 0 WHERE customer_id = :id')->execute(['id' => $customerId]);
                        $insertAddress = $pdo->prepare(
                            "INSERT INTO customer_addresses (customer_id, label, recipient_name, phone, address_line, reference, district, province, department, latitude, longitude, is_default, status, created_by, updated_by, created_at, updated_at)
                             VALUES (:customer_id, 'Principal', :recipient_name, :phone, :address_line, :reference, :district, :province, :department, :latitude, :longitude, 1, 'active', :created_by, :updated_by, NOW(), NOW())"
                        );
                        $insertAddress->execute([
                            'customer_id' => $customerId,
                            'recipient_name' => $name,
                            'phone' => $phone,
                            'address_line' => $addressLine,
                            'reference' => $reference !== '' ? $reference : null,
                            'district' => $district,
                            'province' => $province !== '' ? $province : null,
                            'department' => $department !== '' ? $department : null,
                            'latitude' => $latitude !== null ? number_format((float) $latitude, 7, '.', '') : null,
                            'longitude' => $longitude !== null ? number_format((float) $longitude, 7, '.', '') : null,
                            'created_by' => $adminId,
                            'updated_by' => $adminId,
                        ]);
                        $savedAddressId = (int) $pdo->lastInsertId();
                    }
                    $address = [
                        'id' => $savedAddressId,
                        'address_line' => $addressLine,
                        'reference' => $reference,
                        'district' => $district,
                        'province' => $province,
                        'department' => $department,
                        'phone' => $phone,
                    ];
                }

                $fullAddress = trim((string) $address['address_line'].(($address['reference'] ?? '') !== '' ? ' · '.(string) $address['reference'] : ''));
                $zone = trim(implode(', ', array_filter([(string) ($address['district'] ?? ''), (string) ($address['province'] ?? ''), (string) ($address['department'] ?? '')])));
                $deliveryPhone = trim((string) (($address['phone'] ?? '') !== '' ? $address['phone'] : $phone));

                $lines = [];
                $ingredientRequirements = [];
                $subtotal = 0.0;
                $productCache = [];
                $recipeCache = [];

                foreach ($parsedLines as $cartLine) {
                    $productId = $cartLine['product_id'];
                    $quantity = $cartLine['quantity'];
                    if (! isset($productCache[$productId])) {
                        $productStmt = $pdo->prepare(
                            "SELECT p.id, p.name, p.price, p.emoji FROM products p INNER JOIN categories c ON c.id = p.category_id
                             WHERE p.id = :id AND p.status = 'available' AND c.status = 'active' FOR UPDATE"
                        );
                        $productStmt->execute(['id' => $productId]);
                        $productRow = $productStmt->fetch();
                        if (! $productRow) {
                            throw new StorefrontProblem('Un producto dejó de estar disponible. Actualiza el menú e inténtalo de nuevo.', 409);
                        }
                        $productCache[$productId] = $productRow;

                        $recipeStmt = $pdo->prepare('SELECT ingredient_id, quantity FROM product_ingredients WHERE product_id = :id ORDER BY ingredient_id FOR UPDATE');
                        $recipeStmt->execute(['id' => $productId]);
                        $recipeCache[$productId] = $recipeStmt->fetchAll();
                    }

                    $product = $productCache[$productId];
                    foreach ($recipeCache[$productId] as $recipeLine) {
                        $ingredientId = (int) $recipeLine['ingredient_id'];
                        $needed = round((float) $recipeLine['quantity'] * $quantity, 3);
                        $ingredientRequirements[$ingredientId] = round(($ingredientRequirements[$ingredientId] ?? 0) + $needed, 3);
                    }

                    [$resolvedOptions, $optionsDelta] = $this->storefront_resolve_options($pdo, $productId, (string) $product['name'], $cartLine['option_ids']);
                    $unitPrice = round((float) $product['price'] + $optionsDelta, 2);
                    $lineTotal = round($unitPrice * $quantity, 2);
                    $subtotal += $lineTotal;
                    $lines[] = [
                        'id' => (int) $product['id'],
                        'name' => (string) $product['name'],
                        'price' => $unitPrice,
                        'quantity' => $quantity,
                        'line_total' => $lineTotal,
                        'notes' => $cartLine['notes'],
                        'selected_options' => $resolvedOptions ?: null,
                    ];
                }

                $subtotal = round($subtotal, 2);
                $deliveryFee = 0.0;
                if ($isDelivery) {
                    $zoneId = filter_var($body['delivery_zone_id'] ?? null, FILTER_VALIDATE_INT);
                    $deliveryFee = self::DESEGO_WEB_DELIVERY_FEE;
                    if ($zoneId) {
                        $foundZone = $this->storefront_find_zone($pdo, (int) $zoneId);
                        if ($foundZone) {
                            $deliveryFee = (float) $foundZone['delivery_fee'];
                            if ((float) $foundZone['min_order_amount'] > 0 && $subtotal + 0.0001 < (float) $foundZone['min_order_amount']) {
                                throw new StorefrontProblem('Esta zona de reparto requiere un pedido mínimo de S/ '.number_format((float) $foundZone['min_order_amount'], 2).'.', 422);
                            }
                        }
                    }
                }

                $customerKey = $this->storefront_customer_key($email, $phone);
                $chosenPromotion = $this->storefront_choose_promotion($pdo, $subtotal, $couponCode, $customerKey, $email, $phone, true);
                $promotion = $chosenPromotion['promotion'] ?? null;
                $loyaltyRedemption = $chosenPromotion['loyalty_redemption'] ?? null;
                $discount = $chosenPromotion ? (float) $chosenPromotion['discount'] : 0.0;
                $total = round(max(0, $subtotal - $discount) + $deliveryFee, 2);

                $ingredients = [];
                if ($ingredientRequirements) {
                    $ingredientIds = array_keys($ingredientRequirements);
                    sort($ingredientIds, SORT_NUMERIC);
                    $placeholders = implode(',', array_fill(0, count($ingredientIds), '?'));
                    $stockStmt = $pdo->prepare("SELECT id, name, unit, stock_quantity, cost_per_unit, status FROM ingredients WHERE id IN ($placeholders) ORDER BY id FOR UPDATE");
                    $stockStmt->execute($ingredientIds);
                    foreach ($stockStmt->fetchAll() as $ingredient) {
                        $ingredients[(int) $ingredient['id']] = $ingredient;
                    }
                }
                foreach ($ingredientRequirements as $ingredientId => $needed) {
                    $ingredient = $ingredients[$ingredientId] ?? null;
                    if (! $ingredient || $ingredient['status'] !== 'active') {
                        throw new StorefrontProblem('Una receta contiene un ingrediente inactivo. El restaurante debe revisar el menú.', 409);
                    }
                    if ((float) $ingredient['stock_quantity'] + 0.0001 < $needed) {
                        throw new StorefrontProblem('No hay stock suficiente de '.$ingredient['name'].' para preparar todo tu pedido. Prueba con menos unidades.', 409);
                    }
                }

                $noteParts = ['Pedido realizado desde la tienda web. Preferencia de pago: '.$paymentPreference.'.'];
                if ($promotion) {
                    $noteParts[] = 'Promoción aplicada: '.(string) $promotion['name'].(($promotion['code'] ?? '') !== '' ? ' ('.(string) $promotion['code'].')' : '').'.';
                } elseif ($loyaltyRedemption) {
                    $noteParts[] = 'Canje Club DeSego aplicado: '.(string) $loyaltyRedemption['reward_name'].' ('.(string) $loyaltyRedemption['redemption_code'].').';
                }
                if ($notes !== '') {
                    $noteParts[] = 'Nota del cliente: '.$notes;
                }
                $orderNotes = substr(implode(' ', $noteParts), 0, 500);
                $promoCodeSnapshot = $promotion && ($promotion['code'] ?? '') !== ''
                    ? (string) $promotion['code']
                    : ($loyaltyRedemption ? (string) $loyaltyRedemption['redemption_code'] : null);

                $insertOrder = $pdo->prepare(
                    "INSERT INTO orders (order_number, customer_name, customer_id, order_type, order_channel, web_checkout_key, table_label, notes, status, payment_status, payment_method, payment_preference, subtotal, discount, promotion_id, promotion_code, total, created_by, cash_session_id, created_at, paid_at, updated_at)
                     VALUES (NULL, :customer_name, :customer_id, :order_type, 'web', :checkout_key, :table_label, :notes, 'Pendiente', 'Pendiente', NULL, :payment_preference, :subtotal, :discount, :promotion_id, :promotion_code, :total, :created_by, NULL, NOW(), NULL, NOW())"
                );
                $insertOrder->execute([
                    'customer_name' => $name,
                    'customer_id' => $customerId,
                    'checkout_key' => $checkoutKey,
                    'order_type' => $orderType,
                    'table_label' => $tableLabel ? mb_substr($tableLabel, 0, 40) : null,
                    'notes' => $orderNotes,
                    'payment_preference' => $paymentPreference,
                    'subtotal' => number_format($subtotal, 2, '.', ''),
                    'discount' => number_format($discount, 2, '.', ''),
                    'promotion_id' => $promotion ? (int) $promotion['id'] : null,
                    'promotion_code' => $promoCodeSnapshot,
                    'total' => number_format($total, 2, '.', ''),
                    'created_by' => $adminId,
                ]);
                $orderId = (int) $pdo->lastInsertId();
                $orderNumber = 'DES-'.str_pad((string) (1048 + $orderId), 4, '0', STR_PAD_LEFT);
                $pdo->prepare('UPDATE orders SET order_number = :number WHERE id = :id')->execute(['number' => $orderNumber, 'id' => $orderId]);

                if ($isDelivery) {
                    $deliveryInsert = $pdo->prepare(
                        "INSERT INTO order_delivery_details (order_id, customer_address_id, recipient_phone, address_line, zone, latitude, longitude, delivery_fee, status, created_at, updated_at)
                         VALUES (:order_id, :address_id, :phone, :address, :zone, :latitude, :longitude, :fee, 'Pendiente', NOW(), NOW())"
                    );
                    $deliveryInsert->execute([
                        'order_id' => $orderId,
                        'address_id' => $savedAddressId,
                        'phone' => $deliveryPhone,
                        'address' => $fullAddress,
                        'zone' => $zone !== '' ? $zone : null,
                        'latitude' => $latitude !== null ? number_format((float) $latitude, 7, '.', '') : null,
                        'longitude' => $longitude !== null ? number_format((float) $longitude, 7, '.', '') : null,
                        'fee' => number_format($deliveryFee, 2, '.', ''),
                    ]);
                }

                $itemInsert = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total, notes) VALUES (:order_id,:product_id,:name,:price,:quantity,:line_total,:notes)');
                foreach ($lines as $line) {
                    $itemInsert->execute([
                        'order_id' => $orderId,
                        'product_id' => $line['id'],
                        'name' => $line['name'],
                        'price' => number_format($line['price'], 2, '.', ''),
                        'quantity' => $line['quantity'],
                        'line_total' => number_format($line['line_total'], 2, '.', ''),
                        'notes' => $line['notes'] !== '' ? $line['notes'] : null,
                    ]);
                    $orderItemId = (int) $pdo->lastInsertId();
                    foreach ($line['selected_options'] ?? [] as $position => $option) {
                        DB::table('order_item_options')->insert([
                            'order_item_id' => $orderItemId,
                            'option_id' => $option['id'],
                            'position' => $position,
                            'group_name' => $option['group_name'],
                            'name' => $option['name'],
                            'price_delta' => $option['price_delta'],
                        ]);
                    }
                }

                $orderIngredientInsert = $pdo->prepare('INSERT INTO order_ingredients (order_id, ingredient_id, consumed_quantity, unit, unit_cost) VALUES (:order_id,:ingredient_id,:quantity,:unit,:unit_cost)');
                $stockUpdate = $pdo->prepare('UPDATE ingredients SET stock_quantity = :stock, updated_at = NOW() WHERE id = :id');
                $movementInsert = $pdo->prepare("INSERT INTO inventory_movements (ingredient_id, user_id, movement_type, quantity_delta, previous_quantity, new_quantity, unit_cost, reference, notes, created_at) VALUES (:ingredient_id,:user_id,'sale',:delta,:previous,:new,:unit_cost,:reference,:notes,NOW())");

                if ($promotion) {
                    $redeem = $pdo->prepare('INSERT INTO promotion_redemptions (promotion_id, order_id, customer_id, customer_key, code_snapshot, discount_amount, created_at) VALUES (:promotion_id,:order_id,:customer_id,:customer_key,:code,:discount,NOW())');
                    $redeem->execute(['promotion_id' => (int) $promotion['id'], 'order_id' => $orderId, 'customer_id' => $customerId, 'customer_key' => $customerKey, 'code' => ($promotion['code'] ?? '') !== '' ? (string) $promotion['code'] : null, 'discount' => number_format($discount, 2, '.', '')]);
                    $pdo->prepare('UPDATE promotions SET usage_count = usage_count + 1, updated_at = NOW() WHERE id = :id')->execute(['id' => (int) $promotion['id']]);
                } elseif ($loyaltyRedemption) {
                    $pdo->prepare("UPDATE loyalty_redemptions SET status = 'used', used_by = :used_by, used_at = NOW(), notes = :notes WHERE id = :id")
                        ->execute([
                            'used_by' => $adminId,
                            'notes' => 'Aplicado en pedido web '.$orderNumber,
                            'id' => (int) $loyaltyRedemption['id'],
                        ]);
                }

                foreach ($ingredientRequirements as $ingredientId => $needed) {
                    $ingredient = $ingredients[$ingredientId];
                    $before = (float) $ingredient['stock_quantity'];
                    $after = round($before - $needed, 3);
                    $unitCost = desego_consume_cost_layers($pdo, (int) $ingredientId, (float) $needed, (float) $ingredient['cost_per_unit']);
                    $stockUpdate->execute(['stock' => number_format($after, 3, '.', ''), 'id' => $ingredientId]);
                    $orderIngredientInsert->execute(['order_id' => $orderId, 'ingredient_id' => $ingredientId, 'quantity' => number_format($needed, 3, '.', ''), 'unit' => $ingredient['unit'], 'unit_cost' => number_format($unitCost, 4, '.', '')]);
                    $movementInsert->execute(['ingredient_id' => $ingredientId, 'user_id' => $adminId, 'delta' => number_format(-$needed, 3, '.', ''), 'previous' => number_format($before, 3, '.', ''), 'new' => number_format($after, 3, '.', ''), 'unit_cost' => number_format($unitCost, 4, '.', ''), 'reference' => $orderNumber, 'notes' => 'Consumo de receta por pedido web '.$orderNumber]);
                }

                $pdo->commit();
                session()->put('storefront_customer_id', $customerId);
                session()->put('storefront_last_order_id', $orderId);
                audit_log($pdo, null, 'storefront.order_created', ['order_id' => $orderId, 'order_number' => $orderNumber, 'total' => $total, 'item_count' => count($lines)]);

                $promoResponse = null;
                if ($promotion) {
                    $promoResponse = ['name' => (string) $promotion['name'], 'code' => ($promotion['code'] ?? '') !== '' ? (string) $promotion['code'] : null];
                } elseif ($loyaltyRedemption) {
                    $promoResponse = ['name' => 'Club DeSego: '.(string) $loyaltyRedemption['reward_name'], 'code' => (string) $loyaltyRedemption['redemption_code']];
                }

                json_response([
                    'ok' => true,
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee,
                    'discount' => $discount,
                    'promotion' => $promoResponse,
                    'total' => $total,
                    'payment_status' => 'Pendiente',
                    'payment_preference' => $paymentPreference,
                    'message' => 'Tu pedido fue registrado correctamente. El restaurante confirmará la preparación y coordinará el pago según el método elegido.',
                ]);
            } catch (Throwable $e) {
                if ($e instanceof HttpResponseException) {
                    throw $e;
                }
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        } catch (StorefrontProblem $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], $e->httpStatus);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            error_log('[DESEGO storefront] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo procesar la solicitud. Comprueba la configuración del servidor e inténtalo nuevamente.'], 500);
        }

        return response()->noContent();
    }

    /**
     * @return list<string>
     */
    protected function storefront_payment_methods(PDO $pdo): array
    {
        $defaults = ['Efectivo', 'Yape/Plin', 'Tarjeta al recibir'];
        $raw = desego_system_setting($pdo, 'restaurant_payment_methods', '');
        if ($raw === '') {
            return $defaults;
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $decoded = explode(',', $raw);
        }
        $clean = array_values(array_unique(array_filter(array_map(static fn ($v) => trim((string) $v), $decoded))));

        return $clean ?: $defaults;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function storefront_active_zones(PDO $pdo): array
    {
        try {
            $zones = $pdo->query('SELECT id, name, district, zone_sector, center_latitude, center_longitude, radius_km, delivery_fee, fee_confirmed, polygon_coordinates, min_order_amount, estimated_minutes_min, estimated_minutes_max FROM delivery_zones WHERE is_active = 1 ORDER BY id')->fetchAll();
            foreach ($zones as &$z) {
                $z['id'] = (int) $z['id'];
                $z['center_latitude'] = $z['center_latitude'] !== null ? (float) $z['center_latitude'] : null;
                $z['center_longitude'] = $z['center_longitude'] !== null ? (float) $z['center_longitude'] : null;
                $z['radius_km'] = $z['radius_km'] !== null ? (float) $z['radius_km'] : null;
                $z['delivery_fee'] = (float) $z['delivery_fee'];
                $z['fee_confirmed'] = (bool) ($z['fee_confirmed'] ?? false);
                $z['min_order_amount'] = (float) $z['min_order_amount'];
                $z['estimated_minutes_min'] = (int) $z['estimated_minutes_min'];
                $z['estimated_minutes_max'] = (int) $z['estimated_minutes_max'];
                $z['polygon_coordinates'] = ! empty($z['polygon_coordinates']) ? (json_decode((string) $z['polygon_coordinates'], true) ?: null) : null;
            }
            unset($z);

            return $zones;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function storefront_find_zone(PDO $pdo, int $zoneId): ?array
    {
        foreach ($this->storefront_active_zones($pdo) as $zone) {
            if ((int) $zone['id'] === $zoneId) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $zones
     * @return array<string, mixed>|null
     */
    protected function storefront_match_zone(float $lat, float $lng, array $zones): ?array
    {
        foreach ($zones as $zone) {
            if (is_array($zone['polygon_coordinates']) && count($zone['polygon_coordinates']) >= 3) {
                if ($this->point_in_polygon($lat, $lng, $zone['polygon_coordinates'])) {
                    return $zone;
                }
            }
            if ($zone['center_latitude'] !== null && $zone['center_longitude'] !== null && $zone['radius_km'] !== null && $zone['radius_km'] > 0) {
                $distance = $this->haversine_km($lat, $lng, (float) $zone['center_latitude'], (float) $zone['center_longitude']);
                if ($distance <= (float) $zone['radius_km'] * 1.15) {
                    return [...$zone, 'distance_km' => round($distance, 2)];
                }
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $polygon
     */
    protected function point_in_polygon(float $lat, float $lng, array $polygon): bool
    {
        $inside = false;
        $count = count($polygon);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $pi = $polygon[$i];
            $pj = $polygon[$j];
            if (! is_array($pi) || ! is_array($pj) || count($pi) < 2 || count($pj) < 2) {
                continue;
            }
            $yi = (float) $pi[0];
            $xi = (float) $pi[1];
            $yj = (float) $pj[0];
            $xj = (float) $pj[1];
            $intersect = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-9) + $xi);
            if ($intersect) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    protected function haversine_km(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }

    protected function storefront_phone_digits(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    protected function storefront_customer_key(string $email, string $phone): string
    {
        $normalizedPhone = $this->storefront_phone_digits($phone);
        $normalizedEmail = strtolower(trim($email));

        if ($normalizedPhone !== '') {
            return hash('sha256', 'phone:'.$normalizedPhone);
        }

        return $normalizedEmail !== '' ? hash('sha256', 'email:'.$normalizedEmail) : '';
    }

    /**
     * @return list<string>
     */
    protected function storefront_customer_key_candidates(string $email, string $phone): array
    {
        $normalizedPhone = $this->storefront_phone_digits($phone);
        $normalizedEmail = strtolower(trim($email));
        $keys = [];

        if ($normalizedPhone !== '') {
            $keys[] = hash('sha256', 'phone:'.$normalizedPhone);
        }
        if ($normalizedEmail !== '') {
            $keys[] = hash('sha256', 'email:'.$normalizedEmail);
        }

        // Keep redemption history created before the stable identity key was introduced.
        if ($normalizedPhone !== '' || $normalizedEmail !== '') {
            $keys[] = hash('sha256', $normalizedEmail.'|'.$normalizedPhone);
        }
        if ($normalizedPhone !== '') {
            $keys[] = hash('sha256', '|'.$normalizedPhone);
        }

        return array_values(array_unique($keys));
    }

    protected function storefront_discount_for(array $promotion, float $subtotal): float
    {
        if ($subtotal + 0.0001 < (float) $promotion['min_subtotal']) {
            throw new StorefrontProblem('Este cupón requiere una compra mínima de S/ '.number_format((float) $promotion['min_subtotal'], 2).'.', 422);
        }
        $value = (float) $promotion['discount_value'];
        $discount = $promotion['discount_type'] === 'percentage' ? $subtotal * $value / 100 : $value;
        if ($promotion['max_discount'] !== null) {
            $discount = min($discount, (float) $promotion['max_discount']);
        }

        return round(min($subtotal, max(0, $discount)), 2);
    }

    protected function storefront_promotion_eligible(PDO $pdo, array $promotion, string $customerKey, string $email, string $phone): bool
    {
        if ($promotion['status'] !== 'active') {
            return false;
        }
        if ($promotion['usage_limit'] !== null && (int) $promotion['usage_count'] >= (int) $promotion['usage_limit']) {
            return false;
        }
        $perCustomer = (int) ($promotion['per_customer_limit'] ?? 0);
        if ($perCustomer > 0) {
            if ($customerKey === '') {
                return false;
            }
            $customerKeys = $this->storefront_customer_key_candidates($email, $phone);
            if ($customerKeys === []) {
                $customerKeys = [$customerKey];
            }
            $placeholders = [];
            $params = ['promotion_id' => (int) $promotion['id']];
            foreach ($customerKeys as $index => $key) {
                $placeholder = 'customer_key_'.$index;
                $placeholders[] = ':'.$placeholder;
                $params[$placeholder] = $key;
            }
            $sql = 'SELECT COUNT(*) FROM promotion_redemptions WHERE promotion_id = :promotion_id AND customer_key IN ('.implode(',', $placeholders).')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ((int) $stmt->fetchColumn() >= $perCustomer) {
                return false;
            }
        }

        return true;
    }

    protected function storefront_choose_promotion(PDO $pdo, float $subtotal, string $code, string $customerKey, string $email, string $phone, bool $lock = false): ?array
    {
        $now = gmdate('Y-m-d H:i:s');
        if ($code !== '') {
            $upperCode = strtoupper($code);
            $sql = "SELECT * FROM promotions WHERE promotion_type = 'coupon' AND code = :code AND status = 'active' AND starts_at <= :now_start AND (ends_at IS NULL OR ends_at >= :now_end) LIMIT 1";
            if ($lock) {
                $sql .= ' FOR UPDATE';
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['code' => $upperCode, 'now_start' => $now, 'now_end' => $now]);
            $promotion = $stmt->fetch();
            if ($promotion) {
                if ((int) ($promotion['per_customer_limit'] ?? 0) > 0 && $customerKey === '') {
                    throw new StorefrontProblem('Ingresa tu celular o correo para validar los usos permitidos de este cupón.', 422);
                }
                if (! $this->storefront_promotion_eligible($pdo, $promotion, $customerKey, $email, $phone)) {
                    throw new StorefrontProblem('Este cupón alcanzó su límite de usos o ya fue utilizado por este cliente.', 409);
                }
                $discount = $this->storefront_discount_for($promotion, $subtotal);

                return ['promotion' => $promotion, 'loyalty_redemption' => null, 'discount' => $discount];
            }

            $loyaltySql = 'SELECT lr.id, lr.redemption_code, lr.customer_id, lr.status, r.name AS reward_name, r.reward_type, r.discount_amount, p.price AS product_price
                           FROM loyalty_redemptions lr
                           INNER JOIN loyalty_rewards r ON r.id = lr.reward_id
                           LEFT JOIN products p ON p.id = r.product_id
                           WHERE lr.redemption_code = :code LIMIT 1';
            if ($lock) {
                $loyaltySql .= ' FOR UPDATE';
            }
            $loyaltyStmt = $pdo->prepare($loyaltySql);
            $loyaltyStmt->execute(['code' => $upperCode]);
            $redemption = $loyaltyStmt->fetch();
            if ($redemption) {
                if ($redemption['status'] !== 'issued') {
                    throw new StorefrontProblem('Este código del Club DeSego ya fue utilizado o anulado.', 409);
                }
                $rawValue = $redemption['reward_type'] === 'free_product' && (float) ($redemption['product_price'] ?? 0) > 0
                    ? (float) $redemption['product_price']
                    : (float) $redemption['discount_amount'];
                $discount = round(min($subtotal, max(0.0, $rawValue)), 2);

                return ['promotion' => null, 'loyalty_redemption' => $redemption, 'discount' => $discount];
            }

            throw new StorefrontProblem('El cupón no existe, está inactivo o ya venció.', 422);
        }

        $sql = "SELECT * FROM promotions WHERE promotion_type = 'automatic' AND status = 'active' AND starts_at <= :now_start AND (ends_at IS NULL OR ends_at >= :now_end) ORDER BY id";
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['now_start' => $now, 'now_end' => $now]);
        $best = null;
        foreach ($stmt->fetchAll() as $promotion) {
            if ((float) $subtotal + 0.0001 < (float) $promotion['min_subtotal']) {
                continue;
            }
            if (! $this->storefront_promotion_eligible($pdo, $promotion, $customerKey, $email, $phone)) {
                continue;
            }
            $value = (float) $promotion['discount_value'];
            $discount = $promotion['discount_type'] === 'percentage' ? $subtotal * $value / 100 : $value;
            if ($promotion['max_discount'] !== null) {
                $discount = min($discount, (float) $promotion['max_discount']);
            }
            $discount = round(min($subtotal, max(0, $discount)), 2);
            if ($discount > 0 && ($best === null || $discount > $best['discount'])) {
                $best = ['promotion' => $promotion, 'loyalty_redemption' => null, 'discount' => $discount];
            }
        }

        return $best;
    }

    /**
     * @return list<array{product_id: int, quantity: int, notes: string, option_ids: list<int>}>
     */
    protected function storefront_parse_cart_lines(mixed $itemsInput): array
    {
        if (! is_array($itemsInput) || count($itemsInput) < 1 || count($itemsInput) > 30) {
            throw new StorefrontProblem('Tu carrito debe tener entre 1 y 30 productos.');
        }
        $lines = [];
        $totalsByProduct = [];
        foreach ($itemsInput as $line) {
            if (! is_array($line)) {
                continue;
            }
            $productId = filter_var($line['product_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_INT);
            if (! $productId || $productId < 1 || ! $quantity || $quantity < 1 || $quantity > 20) {
                throw new StorefrontProblem('Hay un producto con cantidad no válida. Máximo 20 por producto.');
            }
            $totalsByProduct[$productId] = ($totalsByProduct[$productId] ?? 0) + $quantity;
            if ($totalsByProduct[$productId] > 20) {
                throw new StorefrontProblem('La cantidad máxima por producto es 20.');
            }
            $itemNotes = trim((string) ($line['notes'] ?? ''));
            if (text_length($itemNotes) > 255) {
                throw new StorefrontProblem('La observación por plato no debe superar los 255 caracteres.');
            }
            $optionIds = [];
            if (! empty($line['selected_options']) && is_array($line['selected_options'])) {
                foreach ($line['selected_options'] as $optItem) {
                    $oid = is_array($optItem) ? filter_var($optItem['id'] ?? null, FILTER_VALIDATE_INT) : filter_var($optItem, FILTER_VALIDATE_INT);
                    if ($oid && $oid > 0) {
                        $optionIds[] = (int) $oid;
                    }
                }
                $optionIds = array_values(array_unique($optionIds));
            }
            $lines[] = [
                'product_id' => (int) $productId,
                'quantity' => (int) $quantity,
                'notes' => $itemNotes,
                'option_ids' => $optionIds,
            ];
        }
        if (! $lines) {
            throw new StorefrontProblem('Tu carrito está vacío.');
        }

        return $lines;
    }

    /**
     * @param  list<int>  $optionIds
     * @return array{0: list<array{id: int, group_name: string, name: string, price_delta: float}>, 1: float}
     */
    protected function storefront_resolve_options(PDO $pdo, int $productId, string $productName, array $optionIds): array
    {
        $groups = [];
        try {
            $grpStmt = $pdo->prepare('SELECT id, name, is_required, max_selections FROM product_option_groups WHERE product_id = :pid AND is_active = 1');
            $grpStmt->execute(['pid' => $productId]);
            foreach ($grpStmt->fetchAll() as $g) {
                $groups[(int) $g['id']] = $g;
            }
        } catch (Throwable) {
            return [[], 0.0];
        }

        if (! $groups) {
            return [[], 0.0];
        }

        $selected = [];
        $countPerGroup = [];
        $delta = 0.0;

        if ($optionIds) {
            $marks = implode(',', array_fill(0, count($optionIds), '?'));
            $optStmt = $pdo->prepare("SELECT id, group_id, name, price_delta FROM product_options WHERE id IN ($marks) AND is_available = 1");
            $optStmt->execute($optionIds);
            foreach ($optStmt->fetchAll() as $opt) {
                $gid = (int) $opt['group_id'];
                if (! isset($groups[$gid])) {
                    continue;
                }
                $countPerGroup[$gid] = ($countPerGroup[$gid] ?? 0) + 1;
                if ($countPerGroup[$gid] > max(1, (int) $groups[$gid]['max_selections'])) {
                    throw new StorefrontProblem('Seleccionaste más opciones de las permitidas en '.$groups[$gid]['name'].' para '.$productName.'.', 422);
                }
                $priceDelta = (float) $opt['price_delta'];
                $delta += $priceDelta;
                $selected[] = [
                    'id' => (int) $opt['id'],
                    'group_name' => (string) $groups[$gid]['name'],
                    'name' => (string) $opt['name'],
                    'price_delta' => $priceDelta,
                ];
            }
        }

        foreach ($groups as $gid => $group) {
            if (! empty($group['is_required']) && empty($countPerGroup[$gid])) {
                throw new StorefrontProblem('Selecciona una opción en "'.$group['name'].'" para '.$productName.'.', 422);
            }
        }

        return [$selected, round($delta, 2)];
    }

    protected function storefront_normalize_cart(PDO $pdo, mixed $itemsInput): array
    {
        $parsedLines = $this->storefront_parse_cart_lines($itemsInput);
        $subtotal = 0.0;
        $normalized = [];
        foreach ($parsedLines as $line) {
            $productId = $line['product_id'];
            $quantity = $line['quantity'];
            $stmt = $pdo->prepare("SELECT p.name, p.price FROM products p INNER JOIN categories c ON c.id = p.category_id WHERE p.id = :id AND p.status = 'available' AND c.status = 'active' LIMIT 1");
            $stmt->execute(['id' => $productId]);
            $product = $stmt->fetch();
            if (! $product) {
                throw new StorefrontProblem('Un producto dejó de estar disponible. Actualiza el menú e inténtalo de nuevo.', 409);
            }
            [, $optionsDelta] = $this->storefront_resolve_options($pdo, $productId, (string) $product['name'], $line['option_ids']);
            $subtotal += ((float) $product['price'] + $optionsDelta) * $quantity;
            $normalized[$productId] = ($normalized[$productId] ?? 0) + $quantity;
        }

        return ['items' => $normalized, 'subtotal' => round($subtotal, 2)];
    }

    protected function storefront_customer_profile(PDO $pdo): ?array
    {
        $customerId = filter_var(session()->get('storefront_customer_id') ?? null, FILTER_VALIDATE_INT);
        if (! $customerId || $customerId < 1) {
            return null;
        }
        $stmt = $pdo->prepare("SELECT id, full_name, phone, email FROM customers WHERE id = :id AND status = 'active' LIMIT 1");
        $stmt->execute(['id' => $customerId]);
        $customer = $stmt->fetch();
        if (! $customer) {
            session()->forget('storefront_customer_id');

            return null;
        }
        $addressStmt = $pdo->prepare(
            "SELECT id, label, recipient_name, phone, address_line, reference, district, province, department, latitude, longitude, is_default
             FROM customer_addresses WHERE customer_id = :id AND status = 'active'
             ORDER BY is_default DESC, id DESC LIMIT 30"
        );
        $addressStmt->execute(['id' => $customerId]);
        $addresses = $addressStmt->fetchAll();
        foreach ($addresses as &$address) {
            $address['id'] = (int) $address['id'];
            $address['is_default'] = (bool) $address['is_default'];
            $address['latitude'] = $address['latitude'] !== null ? (float) $address['latitude'] : null;
            $address['longitude'] = $address['longitude'] !== null ? (float) $address['longitude'] : null;
        }
        unset($address);

        return [
            'customer' => [
                'id' => (int) $customer['id'],
                'name' => (string) $customer['full_name'],
                'phone' => (string) ($customer['phone'] ?? ''),
                'email' => (string) ($customer['email'] ?? ''),
            ],
            'addresses' => $addresses,
        ];
    }
}
