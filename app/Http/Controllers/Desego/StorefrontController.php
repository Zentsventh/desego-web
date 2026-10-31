<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Exceptions\Desego\StorefrontProblem;
use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use PDO;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function App\Support\Desego\audit_log;
use function App\Support\Desego\desego_consume_cost_layers;
use function App\Support\Desego\desego_db;
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
        /** Stable non-reversible identifier to enforce promotion limits for anonymous checkout customers. */
        /** Coupon overrides automatic campaigns; otherwise the best eligible automatic discount is selected. */
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
                if ($view !== 'products') {
                    throw new StorefrontProblem('Vista no válida.', 404);
                }
                $categories = $pdo->query(
                    "SELECT id, name, slug FROM categories WHERE status = 'active' ORDER BY name"
                )->fetchAll();
                foreach ($categories as &$category) {
                    $category['id'] = (int) $category['id'];
                }
                unset($category);
                $products = $pdo->query(
                    "SELECT p.id, p.name, p.description, p.price, p.emoji, p.slug,
                    c.id AS category_id, c.name AS category,
                    (SELECT COUNT(*) FROM product_ingredients pi WHERE pi.product_id = p.id) AS recipe_count,
                    (SELECT GROUP_CONCAT(DISTINCT a.name ORDER BY a.name SEPARATOR ', ')
                     FROM product_ingredients pi2
                     INNER JOIN ingredient_allergens ia ON ia.ingredient_id = pi2.ingredient_id
                     INNER JOIN allergens a ON a.id = ia.allergen_id AND a.status = 'active'
                     WHERE pi2.product_id = p.id) AS allergens
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.status = 'available' AND c.status = 'active'
             ORDER BY c.name, p.name"
                )->fetchAll();
                foreach ($products as &$product) {
                    $product['id'] = (int) $product['id'];
                    $product['category_id'] = (int) $product['category_id'];
                    $product['price'] = (float) $product['price'];
                    $product['recipe_count'] = (int) $product['recipe_count'];
                    $product['can_order'] = $product['recipe_count'] > 0;
                    $product['allergens'] = (string) ($product['allergens'] ?? '');
                }
                unset($product);
                json_response([
                    'ok' => true,
                    'categories' => $categories,
                    'products' => $products,
                    'delivery_fee' => self::DESEGO_WEB_DELIVERY_FEE,
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
                $chosen = $this->storefront_choose_promotion($pdo, (float) $cart['subtotal'], $couponCode, $customerKey, false);
                $discount = $chosen ? (float) $chosen['discount'] : 0.0;
                $fee = ($body['order_type'] ?? 'Delivery') === 'Para llevar' ? 0.0 : self::DESEGO_WEB_DELIVERY_FEE;
                json_response(['ok' => true, 'quote' => [
                    'subtotal' => (float) $cart['subtotal'], 'delivery_fee' => $fee, 'discount' => $discount,
                    'total' => round(max(0, (float) $cart['subtotal'] - $discount) + $fee, 2),
                    'promotion' => $chosen ? ['id' => (int) $chosen['promotion']['id'], 'name' => (string) $chosen['promotion']['name'], 'code' => $chosen['promotion']['code'] ? (string) $chosen['promotion']['code'] : null] : null,
                    'coupon_code' => $couponCode,
                ]]);
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
            $isDelivery = ($body['order_type'] ?? 'Delivery') === 'Delivery';
            if (! in_array($body['order_type'] ?? 'Delivery', ['Delivery', 'Para llevar'], true)) {
                throw new StorefrontProblem('Tipo de entrega no valido.');
            }
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
            if ($phoneDigits === '' || strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15 || text_length($phone) > 30) {
                throw new StorefrontProblem('Ingresa un teléfono válido para coordinar la entrega.');
            }
            if ($email !== '' && (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190)) {
                throw new StorefrontProblem('El correo electrónico no es válido.');
            }
            if (! in_array($paymentPreference, ['Efectivo', 'Yape/Plin', 'Tarjeta al recibir'], true)) {
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
            $normalized = [];
            foreach ($itemsInput as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $productId = filter_var($line['product_id'] ?? null, FILTER_VALIDATE_INT);
                $quantity = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_INT);
                if (! $productId || $productId < 1 || ! $quantity || $quantity < 1 || $quantity > 20) {
                    throw new StorefrontProblem('Hay un producto con cantidad no válida. Máximo 20 por producto.');
                }
                $normalized[$productId] = ($normalized[$productId] ?? 0) + $quantity;
                if ($normalized[$productId] > 20) {
                    throw new StorefrontProblem('La cantidad máxima por producto es 20.');
                }
            }
            if (! $normalized) {
                throw new StorefrontProblem('Tu carrito está vacío.');
            }
            $existingStmt = $pdo->prepare("SELECT id, order_number, total FROM orders WHERE web_checkout_key = :key AND order_channel = 'web' LIMIT 1");
            $existingStmt->execute(['key' => $checkoutKey]);
            if ($existing = $existingStmt->fetch()) {
                session()->put('storefront_last_order_id', (int) $existing['id']);
                json_response(['ok' => true, 'duplicate' => true, 'order_id' => (int) $existing['id'], 'order_number' => $existing['order_number'], 'total' => (float) $existing['total'], 'message' => 'Este pedido ya fue registrado.']);
            }
            $pdo->beginTransaction();
            try {
                // orders.created_by es obligatorio en el esquema común: se atribuye el pedido web al usuario admin técnico,
                // mientras order_channel distingue el canal de origen. La tienda no obtiene permisos administrativos.
                $adminId = $pdo->query("SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.status = 'active' AND r.code = 'admin' ORDER BY u.id LIMIT 1 FOR UPDATE")->fetchColumn();
                if (! $adminId) {
                    throw new StorefrontProblem('La tienda no está disponible todavía. El restaurante debe configurar una cuenta administradora activa.', 503);
                }
                $adminId = (int) $adminId;
                $profile = $this->storefront_customer_profile($pdo);
                $customerId = null;
                $sessionCustomer = $profile['customer'] ?? null;
                $identityMatches = $sessionCustomer
                    && $this->storefront_phone_digits((string) $sessionCustomer['phone']) === $phoneDigits
                    && strtolower((string) $sessionCustomer['email']) === $email;
                if ($savedAddressId !== null) {
                    if (! $identityMatches) {
                        throw new StorefrontProblem('La sesión actual no puede usar esa dirección guardada. Selecciona una dirección nueva o vuelve a iniciar tu pedido.', 403);
                    }
                    $customerId = (int) $sessionCustomer['id'];
                    $addressStmt = $pdo->prepare("SELECT * FROM customer_addresses WHERE id = :address_id AND customer_id = :customer_id AND status = 'active' LIMIT 1 FOR UPDATE");
                    $addressStmt->execute(['address_id' => $savedAddressId, 'customer_id' => $customerId]);
                    $address = $addressStmt->fetch();
                    if (! $address) {
                        throw new StorefrontProblem('La dirección guardada ya no está disponible. Actualiza tus direcciones.', 409);
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
                            "INSERT INTO customer_addresses (customer_id, label, recipient_name, phone, address_line, reference, district, province, department, is_default, status, created_by, updated_by, created_at, updated_at)
                 VALUES (:customer_id, 'Principal', :recipient_name, :phone, :address_line, :reference, :district, :province, :department, 1, 'active', :created_by, :updated_by, NOW(), NOW())"
                        );
                        $insertAddress->execute([
                            'customer_id' => $customerId, 'recipient_name' => $name, 'phone' => $phone, 'address_line' => $addressLine,
                            'reference' => $reference !== '' ? $reference : null, 'district' => $district,
                            'province' => $province !== '' ? $province : null, 'department' => $department !== '' ? $department : null,
                            'created_by' => $adminId, 'updated_by' => $adminId,
                        ]);
                        $savedAddressId = (int) $pdo->lastInsertId();
                    }
                    $address = [
                        'id' => $savedAddressId, 'address_line' => $addressLine, 'reference' => $reference,
                        'district' => $district, 'province' => $province, 'department' => $department, 'phone' => $phone,
                    ];
                }
                $fullAddress = trim((string) $address['address_line'].(($address['reference'] ?? '') !== '' ? ' · '.(string) $address['reference'] : ''));
                $zone = trim(implode(', ', array_filter([(string) ($address['district'] ?? ''), (string) ($address['province'] ?? ''), (string) ($address['department'] ?? '')])));
                $deliveryPhone = trim((string) (($address['phone'] ?? '') !== '' ? $address['phone'] : $phone));
                $lines = [];
                $ingredientRequirements = [];
                $subtotal = 0.0;
                foreach ($normalized as $productId => $quantity) {
                    $productStmt = $pdo->prepare(
                        "SELECT p.id, p.name, p.price, p.emoji FROM products p INNER JOIN categories c ON c.id = p.category_id
                 WHERE p.id = :id AND p.status = 'available' AND c.status = 'active' FOR UPDATE"
                    );
                    $productStmt->execute(['id' => $productId]);
                    $product = $productStmt->fetch();
                    if (! $product) {
                        throw new StorefrontProblem('Un producto dejó de estar disponible. Actualiza el menú e inténtalo de nuevo.', 409);
                    }
                    $recipeStmt = $pdo->prepare('SELECT ingredient_id, quantity FROM product_ingredients WHERE product_id = :id ORDER BY ingredient_id FOR UPDATE');
                    $recipeStmt->execute(['id' => $productId]);
                    $recipe = $recipeStmt->fetchAll();
                    if (! $recipe) {
                        throw new StorefrontProblem('El producto “'.$product['name'].'” aún no tiene receta configurada. El restaurante debe completar sus ingredientes antes de aceptar pedidos.', 422);
                    }
                    foreach ($recipe as $recipeLine) {
                        $ingredientId = (int) $recipeLine['ingredient_id'];
                        $needed = round((float) $recipeLine['quantity'] * $quantity, 3);
                        $ingredientRequirements[$ingredientId] = round(($ingredientRequirements[$ingredientId] ?? 0) + $needed, 3);
                    }
                    $price = (float) $product['price'];
                    $lineTotal = round($price * $quantity, 2);
                    $subtotal += $lineTotal;
                    $lines[] = ['id' => (int) $product['id'], 'name' => (string) $product['name'], 'price' => $price, 'quantity' => $quantity, 'line_total' => $lineTotal];
                }
                $subtotal = round($subtotal, 2);
                $deliveryFee = $isDelivery ? self::DESEGO_WEB_DELIVERY_FEE : 0.0;
                $customerKey = $this->storefront_customer_key($email, $phone);
                $chosenPromotion = $this->storefront_choose_promotion($pdo, $subtotal, $couponCode, $customerKey, true);
                $promotion = $chosenPromotion['promotion'] ?? null;
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
                }
                if ($notes !== '') {
                    $noteParts[] = 'Nota del cliente: '.$notes;
                }
                $orderNotes = substr(implode(' ', $noteParts), 0, 500);
                $insertOrder = $pdo->prepare(
                    "INSERT INTO orders (order_number, customer_name, customer_id, order_type, order_channel, web_checkout_key, table_label, notes, status, payment_status, payment_method, payment_preference, subtotal, discount, promotion_id, promotion_code, total, created_by, cash_session_id, created_at, paid_at, updated_at)
             VALUES (NULL, :customer_name, :customer_id, :order_type, 'web', :checkout_key, NULL, :notes, 'Pendiente', 'Pendiente', NULL, :payment_preference, :subtotal, :discount, :promotion_id, :promotion_code, :total, :created_by, NULL, NOW(), NULL, NOW())"
                );
                $insertOrder->execute([
                    'customer_name' => $name, 'customer_id' => $customerId, 'checkout_key' => $checkoutKey, 'order_type' => $isDelivery ? 'Delivery' : 'Para llevar',
                    'notes' => $orderNotes, 'payment_preference' => $paymentPreference, 'subtotal' => number_format($subtotal, 2, '.', ''), 'discount' => number_format($discount, 2, '.', ''), 'promotion_id' => $promotion ? (int) $promotion['id'] : null, 'promotion_code' => $promotion && ($promotion['code'] ?? '') !== '' ? (string) $promotion['code'] : null, 'total' => number_format($total, 2, '.', ''), 'created_by' => $adminId,
                ]);
                $orderId = (int) $pdo->lastInsertId();
                $orderNumber = 'DES-'.str_pad((string) (1048 + $orderId), 4, '0', STR_PAD_LEFT);
                $pdo->prepare('UPDATE orders SET order_number = :number WHERE id = :id')->execute(['number' => $orderNumber, 'id' => $orderId]);
                if ($isDelivery) {
                    $deliveryInsert = $pdo->prepare(
                        "INSERT INTO order_delivery_details (order_id, customer_address_id, recipient_phone, address_line, zone, delivery_fee, status, created_at, updated_at)
             VALUES (:order_id, :address_id, :phone, :address, :zone, :fee, 'Pendiente', NOW(), NOW())"
                    );
                    $deliveryInsert->execute([
                        'order_id' => $orderId, 'address_id' => $savedAddressId, 'phone' => $deliveryPhone,
                        'address' => $fullAddress, 'zone' => $zone !== '' ? $zone : null,
                        'fee' => number_format($deliveryFee, 2, '.', ''),
                    ]);
                }
                $itemInsert = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total) VALUES (:order_id,:product_id,:name,:price,:quantity,:line_total)');
                foreach ($lines as $line) {
                    $itemInsert->execute(['order_id' => $orderId, 'product_id' => $line['id'], 'name' => $line['name'], 'price' => number_format($line['price'], 2, '.', ''), 'quantity' => $line['quantity'], 'line_total' => number_format($line['line_total'], 2, '.', '')]);
                }
                $orderIngredientInsert = $pdo->prepare('INSERT INTO order_ingredients (order_id, ingredient_id, consumed_quantity, unit, unit_cost) VALUES (:order_id,:ingredient_id,:quantity,:unit,:unit_cost)');
                $stockUpdate = $pdo->prepare('UPDATE ingredients SET stock_quantity = :stock, updated_at = NOW() WHERE id = :id');
                $movementInsert = $pdo->prepare("INSERT INTO inventory_movements (ingredient_id, user_id, movement_type, quantity_delta, previous_quantity, new_quantity, unit_cost, reference, notes, created_at) VALUES (:ingredient_id,:user_id,'sale',:delta,:previous,:new,:unit_cost,:reference,:notes,NOW())");
                if ($promotion) {
                    $redeem = $pdo->prepare('INSERT INTO promotion_redemptions (promotion_id, order_id, customer_id, customer_key, code_snapshot, discount_amount, created_at) VALUES (:promotion_id,:order_id,:customer_id,:customer_key,:code,:discount,NOW())');
                    $redeem->execute(['promotion_id' => (int) $promotion['id'], 'order_id' => $orderId, 'customer_id' => $customerId, 'customer_key' => $customerKey, 'code' => ($promotion['code'] ?? '') !== '' ? (string) $promotion['code'] : null, 'discount' => number_format($discount, 2, '.', '')]);
                    $pdo->prepare('UPDATE promotions SET usage_count = usage_count + 1, updated_at = NOW() WHERE id = :id')->execute(['id' => (int) $promotion['id']]);
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
                json_response([
                    'ok' => true, 'order_id' => $orderId, 'order_number' => $orderNumber, 'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee, 'discount' => $discount, 'promotion' => $promotion ? ['name' => (string) $promotion['name'], 'code' => ($promotion['code'] ?? '') !== '' ? (string) $promotion['code'] : null] : null, 'total' => $total, 'payment_status' => 'Pendiente', 'payment_preference' => $paymentPreference,
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

    protected function storefront_phone_digits(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    protected function storefront_customer_key(string $email, string $phone): string
    {
        return hash('sha256', strtolower(trim($email)).'|'.$this->storefront_phone_digits($phone));
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

    protected function storefront_promotion_eligible(PDO $pdo, array $promotion, string $customerKey, bool $lock = false): bool
    {
        if ($promotion['status'] !== 'active') {
            return false;
        }
        if ($promotion['usage_limit'] !== null && (int) $promotion['usage_count'] >= (int) $promotion['usage_limit']) {
            return false;
        }
        $perCustomer = (int) ($promotion['per_customer_limit'] ?? 0);
        if ($perCustomer > 0 && $customerKey !== '') {
            $sql = 'SELECT COUNT(*) FROM promotion_redemptions WHERE promotion_id = :promotion_id AND customer_key = :customer_key';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['promotion_id' => (int) $promotion['id'], 'customer_key' => $customerKey]);
            if ((int) $stmt->fetchColumn() >= $perCustomer) {
                return false;
            }
        }

        return true;
    }

    protected function storefront_choose_promotion(PDO $pdo, float $subtotal, string $code, string $customerKey, bool $lock = false): ?array
    {
        $now = gmdate('Y-m-d H:i:s');
        if ($code !== '') {
            $sql = "SELECT * FROM promotions WHERE promotion_type = 'coupon' AND code = :code AND status = 'active' AND starts_at <= :now_start AND (ends_at IS NULL OR ends_at >= :now_end) LIMIT 1";
            if ($lock) {
                $sql .= ' FOR UPDATE';
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['code' => strtoupper($code), 'now_start' => $now, 'now_end' => $now]);
            $promotion = $stmt->fetch();
            if (! $promotion) {
                throw new StorefrontProblem('El cupón no existe, está inactivo o ya venció.', 422);
            }
            if (! $this->storefront_promotion_eligible($pdo, $promotion, $customerKey, $lock)) {
                throw new StorefrontProblem('Este cupón alcanzó su límite de usos o ya fue utilizado por este cliente.', 409);
            }
            $discount = $this->storefront_discount_for($promotion, $subtotal);

            return ['promotion' => $promotion, 'discount' => $discount];
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
            if (! $this->storefront_promotion_eligible($pdo, $promotion, $customerKey, $lock)) {
                continue;
            }
            $value = (float) $promotion['discount_value'];
            $discount = $promotion['discount_type'] === 'percentage' ? $subtotal * $value / 100 : $value;
            if ($promotion['max_discount'] !== null) {
                $discount = min($discount, (float) $promotion['max_discount']);
            }
            $discount = round(min($subtotal, max(0, $discount)), 2);
            if ($discount > 0 && ($best === null || $discount > $best['discount'])) {
                $best = ['promotion' => $promotion, 'discount' => $discount];
            }
        }

        return $best;
    }

    protected function storefront_normalize_cart(PDO $pdo, mixed $itemsInput): array
    {
        if (! is_array($itemsInput) || count($itemsInput) < 1 || count($itemsInput) > 30) {
            throw new StorefrontProblem('Tu carrito debe tener entre 1 y 30 productos.');
        }
        $normalized = [];
        foreach ($itemsInput as $line) {
            if (! is_array($line)) {
                continue;
            }
            $productId = filter_var($line['product_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_INT);
            if (! $productId || $productId < 1 || ! $quantity || $quantity < 1 || $quantity > 20) {
                throw new StorefrontProblem('Hay un producto con cantidad no válida. Máximo 20 por producto.');
            }
            $normalized[$productId] = ($normalized[$productId] ?? 0) + $quantity;
            if ($normalized[$productId] > 20) {
                throw new StorefrontProblem('La cantidad máxima por producto es 20.');
            }
        }
        if (! $normalized) {
            throw new StorefrontProblem('Tu carrito está vacío.');
        }
        $subtotal = 0.0;
        foreach ($normalized as $productId => $quantity) {
            $stmt = $pdo->prepare("SELECT p.price FROM products p INNER JOIN categories c ON c.id = p.category_id WHERE p.id = :id AND p.status = 'available' AND c.status = 'active' LIMIT 1");
            $stmt->execute(['id' => $productId]);
            $price = $stmt->fetchColumn();
            if ($price === false) {
                throw new StorefrontProblem('Un producto dejó de estar disponible. Actualiza el menú e inténtalo de nuevo.', 409);
            }
            $subtotal += (float) $price * $quantity;
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
            "SELECT id, label, recipient_name, phone, address_line, reference, district, province, department, is_default
         FROM customer_addresses WHERE customer_id = :id AND status = 'active'
         ORDER BY is_default DESC, id DESC LIMIT 30"
        );
        $addressStmt->execute(['id' => $customerId]);
        $addresses = $addressStmt->fetchAll();
        foreach ($addresses as &$address) {
            $address['id'] = (int) $address['id'];
            $address['is_default'] = (bool) $address['is_default'];
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
