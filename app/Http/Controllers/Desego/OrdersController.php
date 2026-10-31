<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Exceptions\Desego\OrderApiProblem;
use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use PDO;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function App\Support\Desego\audit_log;
use function App\Support\Desego\desego_add_adjustment_cost_layer;
use function App\Support\Desego\desego_consume_cost_layers;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\json_body;
use function App\Support\Desego\json_response;
use function App\Support\Desego\loyalty_award_order_points;
use function App\Support\Desego\loyalty_reverse_order_points;
use function App\Support\Desego\require_any_permission;
use function App\Support\Desego\require_csrf;
use function App\Support\Desego\require_method;
use function App\Support\Desego\require_permission;
use function App\Support\Desego\text_length;

class OrdersController extends Controller
{
    public function __invoke(): Response
    {
        try {
            $pdo = desego_db();
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            if ($method === 'GET') {
                $view = (string) (request()->query()['view'] ?? 'orders');
                if ($view === 'products') {
                    require_permission('orders.create');
                    $rows = $pdo->query(
                        "SELECT p.id, p.name, p.price, p.emoji, p.status, c.name AS category,
                        (SELECT COUNT(*) FROM product_ingredients pi WHERE pi.product_id = p.id) AS recipe_count
                 FROM products p INNER JOIN categories c ON c.id = p.category_id
                 WHERE p.status = 'available' AND c.status = 'active' ORDER BY c.name, p.name"
                    )->fetchAll();
                    foreach ($rows as &$row) {
                        $row['id'] = (int) $row['id'];
                        $row['price'] = (float) $row['price'];
                        $row['recipe_count'] = (int) $row['recipe_count'];
                    }
                    unset($row);
                    json_response(['ok' => true, 'products' => $rows]);
                }
                if ($view === 'stats') {
                    require_any_permission(['dashboard.view', 'orders.view']);
                    json_response(['ok' => true, 'stats' => $this->order_load_stats($pdo)]);
                }
                $user = require_permission('orders.view');
                $ordersSql = "SELECT o.id, o.order_number, o.customer_name, o.order_type, o.table_label, o.notes,
                    o.status, o.payment_status, o.payment_method, o.payment_preference, o.promotion_id, o.promotion_code, o.subtotal, o.discount, o.total,
                    o.created_at, o.paid_at, o.created_by, u.name AS created_by_name,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
                    (SELECT GROUP_CONCAT(CONCAT(oi.quantity, ' × ', oi.product_name) ORDER BY oi.id SEPARATOR ', ')
                     FROM order_items oi WHERE oi.order_id = o.id) AS item_summary
             FROM orders o INNER JOIN users u ON u.id = o.created_by";
                if (($user['role_code'] ?? '') === 'delivery') {
                    $ordersSql .= ' INNER JOIN order_delivery_details courier_scope ON courier_scope.order_id = o.id AND courier_scope.courier_user_id = :courier_id';
                    $ordersSql .= ' ORDER BY o.id DESC LIMIT 150';
                    $ordersStmt = $pdo->prepare($ordersSql);
                    $ordersStmt->execute(['courier_id' => (int) $user['id']]);
                    $rows = $ordersStmt->fetchAll();
                } else {
                    $rows = $pdo->query($ordersSql.' ORDER BY o.id DESC LIMIT 150')->fetchAll();
                }
                foreach ($rows as &$row) {
                    foreach (['id', 'created_by', 'item_count'] as $key) {
                        $row[$key] = (int) $row[$key];
                    }
                    foreach (['subtotal', 'discount', 'total'] as $key) {
                        $row[$key] = (float) $row[$key];
                    }
                }
                unset($row);
                json_response([
                    'ok' => true,
                    'orders' => $rows,
                    'stats' => $this->order_load_stats($pdo),
                    'permissions' => [
                        'can_pay' => in_array('orders.pay', $user['permissions'] ?? [], true) || in_array('cash.manage', $user['permissions'] ?? [], true),
                        'can_cancel' => in_array('orders.cancel', $user['permissions'] ?? [], true),
                        'can_update' => in_array('orders.update', $user['permissions'] ?? [], true),
                        'can_cash' => in_array('cash.manage', $user['permissions'] ?? [], true),
                    ],
                ]);
            }
            require_method('POST');
            $body = json_body();
            require_csrf($body);
            $action = (string) ($body['action'] ?? '');
            if ($action === 'create') {
                $actor = require_permission('orders.create');
                $itemsInput = $body['items'] ?? null;
                if (! is_array($itemsInput) || count($itemsInput) < 1 || count($itemsInput) > 50) {
                    throw new OrderApiProblem('Agrega entre 1 y 50 líneas de productos al pedido.', 422);
                }
                $customer = trim((string) ($body['customer_name'] ?? 'Consumidor final'));
                $customerIdRaw = $body['customer_id'] ?? null;
                $customerId = ($customerIdRaw === null || $customerIdRaw === '' || (int) $customerIdRaw === 0) ? null : filter_var($customerIdRaw, FILTER_VALIDATE_INT);
                if ($customerId !== null && (! $customerId || $customerId < 1)) {
                    throw new OrderApiProblem('Cliente seleccionado no válido.', 422);
                }
                if ($customerId !== null && ! array_intersect(['customers.view', 'customers.manage', 'customer_addresses.manage'], $actor['permissions'] ?? [])) {
                    throw new OrderApiProblem('Tu rol no tiene permiso para asociar fichas de clientes a pedidos.', 403);
                }
                $type = (string) ($body['order_type'] ?? 'Mesa');
                $table = trim((string) ($body['table_label'] ?? ''));
                $notes = trim((string) ($body['notes'] ?? ''));
                $deliveryInput = is_array($body['delivery'] ?? null) ? $body['delivery'] : [];
                $deliveryPhone = trim((string) ($deliveryInput['recipient_phone'] ?? ''));
                $deliveryAddress = trim((string) ($deliveryInput['address_line'] ?? ''));
                $customerAddressRaw = $deliveryInput['customer_address_id'] ?? null;
                $customerAddressId = ($customerAddressRaw === null || $customerAddressRaw === '' || (int) $customerAddressRaw === 0) ? null : filter_var($customerAddressRaw, FILTER_VALIDATE_INT);
                if ($customerAddressId !== null && (! $customerAddressId || $customerAddressId < 1)) {
                    throw new OrderApiProblem('Dirección guardada no válida.', 422);
                }
                $deliveryZone = trim((string) ($deliveryInput['zone'] ?? ''));
                $deliveryFeeInput = filter_var($body['delivery_fee'] ?? 0, FILTER_VALIDATE_FLOAT);
                $paymentStatus = (string) ($body['payment_status'] ?? 'pending');
                $paymentMethod = (string) ($body['payment_method'] ?? '');
                $discountInput = filter_var($body['discount'] ?? 0, FILTER_VALIDATE_FLOAT);
                if ($customer === '') {
                    $customer = 'Consumidor final';
                }
                if (text_length($customer) > 100 || text_length($table) > 40 || text_length($notes) > 500) {
                    throw new OrderApiProblem('Revisa el nombre del cliente, mesa o notas.', 422);
                }
                if (! in_array($type, ['Mesa', 'Delivery', 'Para llevar'], true)) {
                    throw new OrderApiProblem('Tipo de pedido no válido.', 422);
                }
                if ($type === 'Mesa' && $table === '') {
                    throw new OrderApiProblem('Indica la mesa para este pedido.', 422);
                }
                if ($type === 'Delivery' && $customerAddressId === null && ($deliveryPhone === '' || $deliveryAddress === '')) {
                    throw new OrderApiProblem('Para pedidos Delivery, indica teléfono y dirección de entrega, o selecciona una dirección guardada.', 422);
                }
                if (text_length($deliveryPhone) > 30 || text_length($deliveryAddress) > 255 || text_length($deliveryZone) > 100) {
                    throw new OrderApiProblem('El teléfono, dirección o zona de Delivery supera la longitud permitida.', 422);
                }
                if ($deliveryFeeInput === false || (float) $deliveryFeeInput < 0 || (float) $deliveryFeeInput > 1000) {
                    throw new OrderApiProblem('El costo de envío debe ser entre S/ 0 y S/ 1,000.', 422);
                }
                $deliveryFee = $type === 'Delivery' ? round((float) $deliveryFeeInput, 2) : 0.0;
                if (! in_array($paymentStatus, ['pending', 'paid'], true)) {
                    throw new OrderApiProblem('Estado de pago no válido.', 422);
                }
                if ($paymentStatus === 'paid') {
                    if (! in_array('cash.manage', $actor['permissions'] ?? [], true)) {
                        throw new OrderApiProblem('Tu rol no tiene permiso para cobrar. Registra el pedido con pago pendiente.', 403);
                    }
                    if (! in_array($paymentMethod, $this->order_payment_methods(), true)) {
                        throw new OrderApiProblem('Selecciona un medio de pago válido.', 422);
                    }
                }
                if (! is_numeric($discountInput) || (float) $discountInput < 0 || (float) $discountInput > 100000000) {
                    throw new OrderApiProblem('Descuento no válido.', 422);
                }
                $normalized = [];
                foreach ($itemsInput as $line) {
                    if (! is_array($line)) {
                        continue;
                    }
                    $pid = filter_var($line['product_id'] ?? null, FILTER_VALIDATE_INT);
                    $qty = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_INT);
                    if (! $pid || ! $qty || $qty < 1 || $qty > 100) {
                        throw new OrderApiProblem('Hay un producto con una cantidad no válida.', 422);
                    }
                    $normalized[$pid] = ($normalized[$pid] ?? 0) + $qty;
                    if ($normalized[$pid] > 100) {
                        throw new OrderApiProblem('La cantidad máxima por producto es 100.', 422);
                    }
                }
                if (! $normalized) {
                    throw new OrderApiProblem('El carrito está vacío.', 422);
                }
                $pdo->beginTransaction();
                try {
                    $selectedCustomer = null;
                    if ($customerId !== null) {
                        $customerStmt = $pdo->prepare('SELECT id, full_name, phone, status FROM customers WHERE id = :id FOR UPDATE');
                        $customerStmt->execute(['id' => $customerId]);
                        $selectedCustomer = $customerStmt->fetch();
                        if (! $selectedCustomer || $selectedCustomer['status'] !== 'active') {
                            throw new OrderApiProblem('El cliente seleccionado no existe o está inactivo.', 409);
                        }
                        $customer = (string) $selectedCustomer['full_name'];
                        if ($type === 'Delivery' && $deliveryPhone === '') {
                            $deliveryPhone = (string) ($selectedCustomer['phone'] ?? '');
                        }
                    }
                    if ($customerAddressId !== null) {
                        if ($customerId === null || $type !== 'Delivery') {
                            throw new OrderApiProblem('Selecciona un cliente guardado para usar una dirección de Delivery.', 422);
                        }
                        $addressStmt = $pdo->prepare('SELECT id, customer_id, phone, address_line, reference, district, province, status FROM customer_addresses WHERE id = :id AND customer_id = :customer_id FOR UPDATE');
                        $addressStmt->execute(['id' => $customerAddressId, 'customer_id' => $customerId]);
                        $savedAddress = $addressStmt->fetch();
                        if (! $savedAddress || $savedAddress['status'] !== 'active') {
                            throw new OrderApiProblem('La dirección guardada no está activa o no pertenece al cliente.', 409);
                        }
                        $deliveryAddress = trim((string) $savedAddress['address_line'].(($savedAddress['reference'] ?? '') !== '' ? ' · '.$savedAddress['reference'] : ''));
                        $deliveryZone = trim(implode(', ', array_filter([(string) ($savedAddress['district'] ?? ''), (string) ($savedAddress['province'] ?? '')])));
                        $deliveryPhone = (string) (($savedAddress['phone'] ?? '') !== '' ? $savedAddress['phone'] : ($selectedCustomer['phone'] ?? $deliveryPhone));
                    }
                    if ($type === 'Delivery' && ($deliveryPhone === '' || $deliveryAddress === '')) {
                        throw new OrderApiProblem('La dirección guardada debe tener una dirección de entrega y un teléfono válido.', 422);
                    }
                    $activeCash = null;
                    if ($paymentStatus === 'paid') {
                        $pdo->query('SELECT id FROM cash_register_mutex WHERE id = 1 FOR UPDATE')->fetch();
                        $activeCash = $this->order_open_cash_session($pdo, true);
                        if (! $activeCash) {
                            throw new OrderApiProblem('La caja está cerrada. Abre caja antes de cobrar una venta.', 409);
                        }
                    }
                    $lines = [];
                    $ingredientRequirements = [];
                    $subtotal = 0.0;
                    foreach ($normalized as $productId => $qty) {
                        $stmt = $pdo->prepare(
                            "SELECT p.id, p.name, p.price, p.emoji FROM products p
                     INNER JOIN categories c ON c.id = p.category_id
                     WHERE p.id = :id AND p.status = 'available' AND c.status = 'active' FOR UPDATE"
                        );
                        $stmt->execute(['id' => $productId]);
                        $product = $stmt->fetch();
                        if (! $product) {
                            throw new OrderApiProblem('Uno de los productos ya no está disponible. Actualiza el menú e inténtalo otra vez.', 409);
                        }
                        $recipeStmt = $pdo->prepare('SELECT ingredient_id, quantity FROM product_ingredients WHERE product_id = :id ORDER BY ingredient_id FOR UPDATE');
                        $recipeStmt->execute(['id' => $productId]);
                        $recipe = $recipeStmt->fetchAll();
                        if (! $recipe) {
                            throw new OrderApiProblem('El producto “'.$product['name'].'” no tiene receta configurada. Completa sus ingredientes en Menú antes de venderlo.', 422);
                        }
                        foreach ($recipe as $recipeRow) {
                            $iid = (int) $recipeRow['ingredient_id'];
                            $need = round((float) $recipeRow['quantity'] * $qty, 3);
                            $ingredientRequirements[$iid] = round(($ingredientRequirements[$iid] ?? 0) + $need, 3);
                        }
                        $price = (float) $product['price'];
                        $lineTotal = round($price * $qty, 2);
                        $subtotal += $lineTotal;
                        $lines[] = ['id' => (int) $product['id'], 'name' => $product['name'], 'price' => $price, 'quantity' => (int) $qty, 'line_total' => $lineTotal];
                    }
                    $discount = round((float) $discountInput, 2);
                    if ($discount > round($subtotal, 2)) {
                        throw new OrderApiProblem('El descuento no puede superar el subtotal.', 422);
                    }
                    $total = round($subtotal - $discount + $deliveryFee, 2);
                    $ingredients = [];
                    if ($ingredientRequirements) {
                        $ids = array_keys($ingredientRequirements);
                        sort($ids, SORT_NUMERIC);
                        $placeholders = implode(',', array_fill(0, count($ids), '?'));
                        $stmt = $pdo->prepare("SELECT id, name, unit, stock_quantity, cost_per_unit, status FROM ingredients WHERE id IN ($placeholders) ORDER BY id FOR UPDATE");
                        $stmt->execute($ids);
                        foreach ($stmt->fetchAll() as $ingredient) {
                            $ingredients[(int) $ingredient['id']] = $ingredient;
                        }
                    }
                    foreach ($ingredientRequirements as $ingredientId => $requiredQty) {
                        $ingredient = $ingredients[$ingredientId] ?? null;
                        if (! $ingredient || $ingredient['status'] !== 'active') {
                            throw new OrderApiProblem('Una receta utiliza un ingrediente inactivo o inexistente. Revisa el catálogo.', 409);
                        }
                        if ((float) $ingredient['stock_quantity'] + 0.0001 < $requiredQty) {
                            throw new OrderApiProblem('Stock insuficiente de '.$ingredient['name'].'. Disponible: '.number_format((float) $ingredient['stock_quantity'], 3).' '.$ingredient['unit'].'; necesario: '.number_format($requiredQty, 3).' '.$ingredient['unit'].'.', 409);
                        }
                    }
                    $insertOrder = $pdo->prepare(
                        "INSERT INTO orders (order_number, customer_name, customer_id, order_type, table_label, notes, status, payment_status,
                    payment_method, subtotal, discount, total, created_by, cash_session_id, created_at, paid_at, updated_at)
                 VALUES (NULL, :customer, :customer_id, :type, :table_label, :notes, 'Pendiente', :payment_status, :payment_method,
                    :subtotal, :discount, :total, :created_by, :cash_session_id, NOW(), :paid_at, NOW())"
                    );
                    $insertOrder->execute([
                        'customer' => $customer,
                        'customer_id' => $customerId,
                        'type' => $type,
                        'table_label' => $table !== '' ? $table : null,
                        'notes' => $notes !== '' ? $notes : null,
                        'payment_status' => $paymentStatus === 'paid' ? 'Pagado' : 'Pendiente',
                        'payment_method' => $paymentStatus === 'paid' ? $paymentMethod : null,
                        'subtotal' => number_format($subtotal, 2, '.', ''),
                        'discount' => number_format($discount, 2, '.', ''),
                        'total' => number_format($total, 2, '.', ''),
                        'created_by' => (int) $actor['id'],
                        'cash_session_id' => $activeCash['id'] ?? null,
                        'paid_at' => $paymentStatus === 'paid' ? date('Y-m-d H:i:s') : null,
                    ]);
                    $orderId = (int) $pdo->lastInsertId();
                    $orderNumber = 'DES-'.str_pad((string) (1048 + $orderId), 4, '0', STR_PAD_LEFT);
                    $pdo->prepare('UPDATE orders SET order_number = :number WHERE id = :id')->execute(['number' => $orderNumber, 'id' => $orderId]);
                    if ($type === 'Delivery') {
                        $pdo->prepare("INSERT INTO order_delivery_details (order_id, customer_address_id, recipient_phone, address_line, zone, delivery_fee, status, created_at, updated_at) VALUES (:order_id, :customer_address_id, :phone, :address, :zone, :fee, 'Pendiente', NOW(), NOW())")
                            ->execute(['order_id' => $orderId, 'customer_address_id' => $customerAddressId, 'phone' => $deliveryPhone, 'address' => $deliveryAddress, 'zone' => $deliveryZone !== '' ? $deliveryZone : null, 'fee' => number_format($deliveryFee, 2, '.', '')]);
                    }
                    $itemInsert = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total) VALUES (:order_id, :product_id, :name, :price, :quantity, :total)');
                    foreach ($lines as $line) {
                        $itemInsert->execute(['order_id' => $orderId, 'product_id' => $line['id'], 'name' => $line['name'], 'price' => number_format($line['price'], 2, '.', ''), 'quantity' => $line['quantity'], 'total' => number_format($line['line_total'], 2, '.', '')]);
                    }
                    $orderIngredientInsert = $pdo->prepare('INSERT INTO order_ingredients (order_id, ingredient_id, consumed_quantity, unit, unit_cost) VALUES (:order_id, :ingredient_id, :quantity, :unit, :unit_cost)');
                    $stockUpdate = $pdo->prepare('UPDATE ingredients SET stock_quantity = :stock, updated_at = NOW() WHERE id = :id');
                    $movementInsert = $pdo->prepare("INSERT INTO inventory_movements (ingredient_id, user_id, movement_type, quantity_delta, previous_quantity, new_quantity, unit_cost, reference, notes, created_at) VALUES (:ingredient_id, :user_id, 'sale', :delta, :previous, :new, :unit_cost, :reference, :notes, NOW())");
                    foreach ($ingredientRequirements as $ingredientId => $requiredQty) {
                        $ingredient = $ingredients[$ingredientId];
                        $before = (float) $ingredient['stock_quantity'];
                        $after = round($before - $requiredQty, 3);
                        $unitCost = desego_consume_cost_layers($pdo, (int) $ingredientId, (float) $requiredQty, (float) $ingredient['cost_per_unit']);
                        $stockUpdate->execute(['stock' => number_format($after, 3, '.', ''), 'id' => $ingredientId]);
                        $orderIngredientInsert->execute(['order_id' => $orderId, 'ingredient_id' => $ingredientId, 'quantity' => number_format($requiredQty, 3, '.', ''), 'unit' => $ingredient['unit'], 'unit_cost' => number_format($unitCost, 4, '.', '')]);
                        $movementInsert->execute(['ingredient_id' => $ingredientId, 'user_id' => (int) $actor['id'], 'delta' => number_format(-$requiredQty, 3, '.', ''), 'previous' => number_format($before, 3, '.', ''), 'new' => number_format($after, 3, '.', ''), 'unit_cost' => number_format($unitCost, 4, '.', ''), 'reference' => $orderNumber, 'notes' => 'Consumo de receta por pedido '.$orderNumber]);
                    }
                    if ($paymentStatus === 'paid') {
                        $paymentInsert = $pdo->prepare("INSERT INTO payments (order_id, cash_session_id, user_id, payment_type, method, amount, notes, created_at) VALUES (:order_id, :session_id, :user_id, 'payment', :method, :amount, 'Cobro al crear pedido', NOW())");
                        $paymentInsert->execute(['order_id' => $orderId, 'session_id' => $activeCash['id'], 'user_id' => (int) $actor['id'], 'method' => $paymentMethod, 'amount' => number_format($total, 2, '.', '')]);
                        loyalty_award_order_points($pdo, $orderId, (int) $actor['id']);
                    }
                    $pdo->commit();
                    audit_log($pdo, (int) $actor['id'], 'orders.created', ['order_id' => $orderId, 'order_number' => $orderNumber, 'total' => $total, 'payment_status' => $paymentStatus === 'paid' ? 'Pagado' : 'Pendiente', 'order_type' => $type, 'delivery_fee' => $deliveryFee]);
                    json_response(['ok' => true, 'message' => 'Pedido '.$orderNumber.' registrado. Stock de ingredientes actualizado.', 'order_id' => $orderId, 'order_number' => $orderNumber, 'total' => $total]);
                } catch (Throwable $e) {
                    if ($e instanceof HttpResponseException) {
                        throw $e;
                    }
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
            }
            if ($action === 'status') {
                $actor = require_permission('orders.update');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $status = (string) ($body['status'] ?? '');
                $allowed = ['Pendiente', 'Preparando', 'Listo', 'En reparto', 'Entregado'];
                if (! $id || ! in_array($status, $allowed, true)) {
                    throw new OrderApiProblem('Estado o pedido no válido.', 422);
                }
                $stmt = $pdo->prepare('SELECT id, order_number, status, order_type FROM orders WHERE id = :id FOR UPDATE');
                $pdo->beginTransaction();
                try {
                    $stmt->execute(['id' => $id]);
                    $order = $stmt->fetch();
                    if (! $order || $order['status'] === 'Cancelado') {
                        throw new OrderApiProblem('El pedido no existe o ya fue cancelado.', 404);
                    }
                    if ($order['order_type'] === 'Delivery' && in_array($status, ['En reparto', 'Entregado'], true)) {
                        throw new OrderApiProblem('Los estados En reparto y Entregado se gestionan desde el Centro de Delivery para validar asignaciones.', 409);
                    }
                    $transitions = [
                        'Pendiente' => ['Pendiente', 'Preparando'],
                        'Preparando' => ['Preparando', 'Listo'],
                        'Listo' => $order['order_type'] === 'Delivery' ? ['Listo', 'En reparto'] : ['Listo', 'Entregado'],
                        'En reparto' => ['En reparto', 'Entregado'],
                        'Entregado' => ['Entregado'],
                    ];
                    if (! in_array($status, $transitions[$order['status']] ?? [], true)) {
                        throw new OrderApiProblem('Transición de estado no permitida. Sigue el flujo Pendiente → Preparando → Listo → Entregado; Delivery pasa por En reparto.', 409);
                    }
                    $sql = "UPDATE orders SET status = :status, completed_at = CASE WHEN :status2 = 'Entregado' THEN NOW() ELSE completed_at END, updated_at = NOW() WHERE id = :id";
                    $pdo->prepare($sql)->execute(['status' => $status, 'status2' => $status, 'id' => $id]);
                    $pdo->commit();
                    audit_log($pdo, (int) $actor['id'], 'orders.status_changed', ['order_id' => (int) $id, 'status' => $status]);
                    json_response(['ok' => true, 'message' => $order['order_number'].' actualizado a '.$status.'.']);
                } catch (Throwable $e) {
                    if ($e instanceof HttpResponseException) {
                        throw $e;
                    }
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
            }
            if ($action === 'pay') {
                $actor = require_any_permission(['orders.pay', 'cash.manage']);
                if (! in_array('cash.manage', $actor['permissions'] ?? [], true) && ! in_array('orders.pay', $actor['permissions'] ?? [], true)) {
                    throw new OrderApiProblem('No tienes permiso para cobrar pedidos.', 403);
                }
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $paymentMethod = (string) ($body['payment_method'] ?? '');
                if (! $id || ! in_array($paymentMethod, $this->order_payment_methods(), true)) {
                    throw new OrderApiProblem('Pedido o medio de pago no válido.', 422);
                }
                if (! in_array('cash.manage', $actor['permissions'] ?? [], true)) {
                    throw new OrderApiProblem('Para cobrar se requiere permiso de caja.', 403);
                }
                $pdo->beginTransaction();
                try {
                    $pdo->query('SELECT id FROM cash_register_mutex WHERE id = 1 FOR UPDATE')->fetch();
                    $session = $this->order_open_cash_session($pdo, true);
                    if (! $session) {
                        throw new OrderApiProblem('La caja está cerrada. Abre caja para registrar el cobro.', 409);
                    }
                    $stmt = $pdo->prepare('SELECT id, order_number, total, payment_status, status FROM orders WHERE id = :id FOR UPDATE');
                    $stmt->execute(['id' => $id]);
                    $order = $stmt->fetch();
                    if (! $order || $order['status'] === 'Cancelado') {
                        throw new OrderApiProblem('El pedido no existe o está cancelado.', 404);
                    }
                    if ($order['payment_status'] !== 'Pendiente') {
                        throw new OrderApiProblem('Este pedido no tiene un pago pendiente.', 409);
                    }
                    $pdo->prepare("INSERT INTO payments (order_id, cash_session_id, user_id, payment_type, method, amount, notes, created_at) VALUES (:order_id, :session_id, :user_id, 'payment', :method, :amount, 'Cobro de pedido existente', NOW())")
                        ->execute(['order_id' => $id, 'session_id' => $session['id'], 'user_id' => (int) $actor['id'], 'method' => $paymentMethod, 'amount' => number_format((float) $order['total'], 2, '.', '')]);
                    $pdo->prepare("UPDATE orders SET payment_status = 'Pagado', payment_method = :method, cash_session_id = :session_id, paid_at = NOW(), updated_at = NOW() WHERE id = :id")
                        ->execute(['method' => $paymentMethod, 'session_id' => $session['id'], 'id' => $id]);
                    loyalty_award_order_points($pdo, (int) $id, (int) $actor['id']);
                    $pdo->commit();
                    audit_log($pdo, (int) $actor['id'], 'orders.paid', ['order_id' => (int) $id, 'method' => $paymentMethod, 'amount' => (float) $order['total']]);
                    json_response(['ok' => true, 'message' => $order['order_number'].' cobrado por '.number_format((float) $order['total'], 2).'.']);
                } catch (Throwable $e) {
                    if ($e instanceof HttpResponseException) {
                        throw $e;
                    }
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
            }
            if ($action === 'refund') {
                $actor = require_permission('cash.manage');
                if (! in_array('orders.cancel', $actor['permissions'] ?? [], true)) {
                    throw new OrderApiProblem('Tu rol no tiene permiso para reembolsar/cancelar pedidos.', 403);
                }
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                if (! $id) {
                    throw new OrderApiProblem('Pedido no válido.', 422);
                }
                $pdo->beginTransaction();
                try {
                    $pdo->query('SELECT id FROM cash_register_mutex WHERE id = 1 FOR UPDATE')->fetch();
                    $session = $this->order_open_cash_session($pdo, true);
                    if (! $session) {
                        throw new OrderApiProblem('Abre caja antes de registrar un reembolso.', 409);
                    }
                    $stmt = $pdo->prepare('SELECT id, order_number, total, payment_status, payment_method, status FROM orders WHERE id = :id FOR UPDATE');
                    $stmt->execute(['id' => $id]);
                    $order = $stmt->fetch();
                    if (! $order || $order['payment_status'] !== 'Pagado' || $order['status'] === 'Cancelado') {
                        throw new OrderApiProblem('Solo puedes reembolsar un pedido pagado y no cancelado.', 409);
                    }
                    $pdo->prepare("INSERT INTO payments (order_id, cash_session_id, user_id, payment_type, method, amount, notes, created_at) VALUES (:order_id, :session_id, :user_id, 'refund', :method, :amount, 'Reembolso del pedido', NOW())")
                        ->execute(['order_id' => $id, 'session_id' => $session['id'], 'user_id' => (int) $actor['id'], 'method' => $order['payment_method'], 'amount' => number_format((float) $order['total'], 2, '.', '')]);
                    $pdo->prepare("UPDATE orders SET payment_status = 'Reembolsado', updated_at = NOW() WHERE id = :id")->execute(['id' => $id]);
                    loyalty_reverse_order_points($pdo, (int) $id, (int) $actor['id']);
                    $pdo->commit();
                    audit_log($pdo, (int) $actor['id'], 'orders.refunded', ['order_id' => (int) $id, 'amount' => (float) $order['total']]);
                    json_response(['ok' => true, 'message' => 'Reembolso registrado. Ahora puedes cancelar el pedido si corresponde.']);
                } catch (Throwable $e) {
                    if ($e instanceof HttpResponseException) {
                        throw $e;
                    }
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
            }
            if ($action === 'cancel') {
                $actor = require_permission('orders.cancel');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $reason = trim((string) ($body['reason'] ?? 'Cancelado por administración'));
                if (! $id || text_length($reason) > 500) {
                    throw new OrderApiProblem('Pedido o motivo no válido.', 422);
                }
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('SELECT id, order_number, status, payment_status FROM orders WHERE id = :id FOR UPDATE');
                    $stmt->execute(['id' => $id]);
                    $order = $stmt->fetch();
                    if (! $order || $order['status'] === 'Cancelado') {
                        throw new OrderApiProblem('El pedido no existe o ya estaba cancelado.', 404);
                    }
                    if ($order['status'] === 'Entregado') {
                        throw new OrderApiProblem('No se puede cancelar un pedido ya entregado desde este flujo.', 409);
                    }
                    if ($order['payment_status'] === 'Pagado') {
                        throw new OrderApiProblem('El pedido está pagado. Registra un reembolso antes de cancelarlo.', 409);
                    }
                    $restoreInventory = $order['status'] === 'Pendiente';
                    if ($restoreInventory) {
                        $stmt = $pdo->prepare('SELECT oi.ingredient_id, oi.consumed_quantity, oi.unit, oi.unit_cost, oi.restored_at, i.name, i.stock_quantity FROM order_ingredients oi INNER JOIN ingredients i ON i.id = oi.ingredient_id WHERE oi.order_id = :order_id ORDER BY oi.ingredient_id FOR UPDATE');
                        $stmt->execute(['order_id' => $id]);
                        $ingredients = $stmt->fetchAll();
                        foreach ($ingredients as $ingredient) {
                            if ($ingredient['restored_at'] !== null) {
                                continue;
                            }
                            $before = (float) $ingredient['stock_quantity'];
                            $quantity = (float) $ingredient['consumed_quantity'];
                            $after = round($before + $quantity, 3);
                            $pdo->prepare('UPDATE ingredients SET stock_quantity = :stock, updated_at = NOW() WHERE id = :id')->execute(['stock' => number_format($after, 3, '.', ''), 'id' => $ingredient['ingredient_id']]);
                            desego_add_adjustment_cost_layer($pdo, (int) $ingredient['ingredient_id'], $quantity, (float) $ingredient['unit_cost'], 'Restitución por cancelación de '.$order['order_number']);
                            $pdo->prepare("INSERT INTO inventory_movements (ingredient_id, user_id, movement_type, quantity_delta, previous_quantity, new_quantity, unit_cost, reference, notes, created_at) VALUES (:ingredient_id, :user_id, 'entry', :delta, :previous, :new, :unit_cost, :reference, :notes, NOW())")
                                ->execute(['ingredient_id' => $ingredient['ingredient_id'], 'user_id' => (int) $actor['id'], 'delta' => number_format($quantity, 3, '.', ''), 'previous' => number_format($before, 3, '.', ''), 'new' => number_format($after, 3, '.', ''), 'unit_cost' => number_format((float) $ingredient['unit_cost'], 4, '.', ''), 'reference' => $order['order_number'], 'notes' => 'Restitución por cancelación de '.$order['order_number']]);
                            $pdo->prepare('UPDATE order_ingredients SET restored_at = NOW() WHERE order_id = :order_id AND ingredient_id = :ingredient_id')->execute(['order_id' => $id, 'ingredient_id' => $ingredient['ingredient_id']]);
                        }
                    }
                    $pdo->prepare("UPDATE orders SET status = 'Cancelado', cancelled_at = NOW(), notes = CONCAT(COALESCE(notes,''), CASE WHEN COALESCE(notes,'') = '' THEN '' ELSE ' | ' END, 'Cancelación: ', :reason), updated_at = NOW() WHERE id = :id")
                        ->execute(['reason' => $reason, 'id' => $id]);
                    $pdo->commit();
                    audit_log($pdo, (int) $actor['id'], 'orders.cancelled', ['order_id' => (int) $id, 'reason' => $reason, 'inventory_restored' => $restoreInventory]);
                    json_response(['ok' => true, 'message' => $order['order_number'].' cancelado.'.($restoreInventory ? ' Se restauró el inventario.' : ' El consumo no se revirtió porque el pedido ya había pasado a preparación.')]);
                } catch (Throwable $e) {
                    if ($e instanceof HttpResponseException) {
                        throw $e;
                    }
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
            }
            throw new OrderApiProblem('Acción de pedidos no válida.', 404);
        } catch (OrderApiProblem $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], $e->httpStatus);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            error_log('[DESEGO orders] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo completar la operación de pedidos. Revisa el registro del servidor.'], 500);
        }

        return response()->noContent();
    }

    protected function order_payment_methods(): array
    {
        return ['Efectivo', 'Yape/Plin', 'Tarjeta', 'Transferencia'];
    }

    protected function order_open_cash_session(PDO $pdo, bool $lock = false): ?array
    {
        $sql = "SELECT id, opened_by, opening_amount, opened_at FROM cash_sessions WHERE status = 'open' ORDER BY id DESC LIMIT 1";
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $row = $pdo->query($sql)->fetch();
        if (! $row) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['opening_amount'] = (float) $row['opening_amount'];

        return $row;
    }

    protected function order_load_stats(PDO $pdo): array
    {
        $sales = $pdo->query(
            "SELECT COALESCE(SUM(CASE WHEN payment_type = 'payment' THEN amount ELSE -amount END),0)
         FROM payments WHERE created_at >= CURDATE() AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
        )->fetchColumn();
        $orders = $pdo->query('SELECT COUNT(*) FROM orders WHERE created_at >= CURDATE() AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)')->fetchColumn();
        $pending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Pendiente' AND cancelled_at IS NULL")->fetchColumn();
        $average = $pdo->query(
            "SELECT COALESCE(AVG(total),0) FROM orders WHERE payment_status = 'Pagado'
         AND created_at >= CURDATE() AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
        )->fetchColumn();

        return ['sales_today' => (float) $sales, 'orders_today' => (int) $orders, 'pending_orders' => (int) $pending, 'average_ticket' => (float) $average];
    }
}
