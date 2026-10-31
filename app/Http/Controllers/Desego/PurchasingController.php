<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Exceptions\Desego\PurchasingProblem;
use App\Http\Controllers\Controller;
use DateTimeImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function App\Support\Desego\audit_log;
use function App\Support\Desego\current_user;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\json_body;
use function App\Support\Desego\json_response;
use function App\Support\Desego\require_any_permission;
use function App\Support\Desego\require_csrf;
use function App\Support\Desego\require_method;
use function App\Support\Desego\require_permission;
use function App\Support\Desego\text_length;

class PurchasingController extends Controller
{
    public function __invoke(): Response
    {
        try {
            $pdo = desego_db();
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            if ($method === 'GET') {
                $view = (string) (request()->query()['view'] ?? 'dashboard');
                if ($view === 'order') {
                    require_any_permission(['purchases.view', 'purchases.manage', 'purchases.receive']);
                    $id = filter_var(request()->query()['id'] ?? null, FILTER_VALIDATE_INT);
                    if (! $id) {
                        json_response(['ok' => false, 'message' => 'Orden no válida.'], 422);
                    }
                    $stmt = $pdo->prepare('SELECT po.*, s.name AS supplier_name, s.email AS supplier_email, s.phone AS supplier_phone, u.name AS created_by_name FROM purchase_orders po JOIN suppliers s ON s.id=po.supplier_id LEFT JOIN users u ON u.id=po.created_by WHERE po.id=:id');
                    $stmt->execute(['id' => $id]);
                    $order = $stmt->fetch();
                    if (! $order) {
                        json_response(['ok' => false, 'message' => 'No se encontró la orden.'], 404);
                    }
                    $items = $pdo->prepare('SELECT poi.*, i.stock_quantity, i.cost_per_unit AS current_cost FROM purchase_order_items poi JOIN ingredients i ON i.id=poi.ingredient_id WHERE poi.purchase_order_id=:id ORDER BY poi.id');
                    $items->execute(['id' => $id]);
                    json_response(['ok' => true, 'order' => $order, 'items' => $items->fetchAll()]);
                }
                if ($view === 'costs') {
                    require_any_permission(['inventory.costs.view', 'inventory.costs.manage', 'purchases.view', 'purchases.manage']);
                } else {
                    require_any_permission(['suppliers.view', 'suppliers.manage', 'purchases.view', 'purchases.manage', 'purchases.receive', 'inventory.costs.view', 'inventory.costs.manage']);
                }
                $actor = current_user();
                $perms = $actor['permissions'] ?? [];
                $suppliers = [];
                if (array_intersect($perms, ['suppliers.view', 'suppliers.manage', 'purchases.view', 'purchases.manage', 'purchases.receive'])) {
                    $suppliers = $pdo->query("SELECT s.id,s.name,s.tax_id,s.contact_name,s.email,s.phone,s.address,s.city,s.notes,s.rating,s.status,s.created_at,s.updated_at,(SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id=s.id) AS orders_count,(SELECT COALESCE(SUM(po.total_amount),0) FROM purchase_orders po WHERE po.supplier_id=s.id AND po.status IN ('received','partially_received')) AS purchase_volume FROM suppliers s ORDER BY s.status DESC,s.name")->fetchAll();
                    foreach ($suppliers as &$s) {
                        $s['id'] = (int) $s['id'];
                        $s['rating'] = $s['rating'] === null ? null : (float) $s['rating'];
                        $s['orders_count'] = (int) $s['orders_count'];
                        $s['purchase_volume'] = (float) $s['purchase_volume'];
                    } unset($s);
                }
                $ingredients = [];
                if (array_intersect($perms, ['purchases.view', 'purchases.manage', 'purchases.receive', 'inventory.costs.view', 'inventory.costs.manage'])) {
                    $ingredients = $pdo->query("SELECT id,name,unit,stock_quantity,min_stock,cost_per_unit,status FROM ingredients WHERE status='active' ORDER BY name")->fetchAll();
                    foreach ($ingredients as &$i) {
                        $i['id'] = (int) $i['id'];
                        foreach (['stock_quantity', 'min_stock', 'cost_per_unit'] as $k) {
                            $i[$k] = (float) $i[$k];
                        }
                    } unset($i);
                }
                $orders = [];
                if (array_intersect($perms, ['purchases.view', 'purchases.manage', 'purchases.receive'])) {
                    $orders = $pdo->query('SELECT po.id,po.order_number,po.supplier_id,s.name AS supplier_name,s.email AS supplier_email,po.status,po.order_date,po.expected_date,po.subtotal,po.tax_rate,po.tax_amount,po.shipping_cost,po.total_amount,po.sent_at,po.received_at,po.created_at, u.name AS created_by_name,(SELECT COUNT(*) FROM purchase_order_items x WHERE x.purchase_order_id=po.id) AS items_count,(SELECT COALESCE(SUM(x.ordered_quantity-x.received_quantity),0) FROM purchase_order_items x WHERE x.purchase_order_id=po.id) AS pending_quantity FROM purchase_orders po JOIN suppliers s ON s.id=po.supplier_id LEFT JOIN users u ON u.id=po.created_by ORDER BY po.created_at DESC,po.id DESC LIMIT 100')->fetchAll();
                    foreach ($orders as &$o) {
                        $o['id'] = (int) $o['id'];
                        $o['supplier_id'] = (int) $o['supplier_id'];
                        foreach (['subtotal', 'tax_rate', 'tax_amount', 'shipping_cost', 'total_amount', 'pending_quantity'] as $k) {
                            $o[$k] = (float) $o[$k];
                        } $o['items_count'] = (int) $o['items_count'];
                    } unset($o);
                }
                $setting = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key='inventory_costing_method' LIMIT 1");
                $setting->execute();
                $costMethod = (string) ($setting->fetchColumn() ?: 'FIFO');
                $costRows = [];
                $history = [];
                if ($view === 'costs' || in_array('inventory.costs.view', $perms, true) || in_array('inventory.costs.manage', $perms, true)) {
                    $costRows = $pdo->query("SELECT i.id,i.name,i.unit,i.stock_quantity,i.min_stock,i.cost_per_unit,COALESCE(SUM(l.quantity_remaining*l.unit_cost),i.stock_quantity*i.cost_per_unit) AS layered_value,COUNT(DISTINCT l.id) AS layer_count FROM ingredients i LEFT JOIN inventory_cost_layers l ON l.ingredient_id=i.id AND l.quantity_remaining>0 WHERE i.status='active' GROUP BY i.id,i.name,i.unit,i.stock_quantity,i.min_stock,i.cost_per_unit ORDER BY i.name")->fetchAll();
                    foreach ($costRows as &$c) {
                        $c['id'] = (int) $c['id'];
                        foreach (['stock_quantity', 'min_stock', 'cost_per_unit', 'layered_value'] as $k) {
                            $c[$k] = (float) $c[$k];
                        } $c['layer_count'] = (int) $c['layer_count'];
                    } unset($c);
                    $history = $pdo->query('SELECT h.id,h.ingredient_id,i.name AS ingredient_name,h.supplier_id,s.name AS supplier_name,h.purchase_order_id,po.order_number,h.previous_unit_cost,h.new_unit_cost,h.quantity_received,h.costing_method,h.reason,h.created_at,u.name AS user_name FROM ingredient_cost_history h JOIN ingredients i ON i.id=h.ingredient_id LEFT JOIN suppliers s ON s.id=h.supplier_id LEFT JOIN purchase_orders po ON po.id=h.purchase_order_id LEFT JOIN users u ON u.id=h.user_id ORDER BY h.created_at DESC,h.id DESC LIMIT 100')->fetchAll();
                    foreach ($history as &$h) {
                        $h['id'] = (int) $h['id'];
                        $h['ingredient_id'] = (int) $h['ingredient_id'];
                        foreach (['previous_unit_cost', 'new_unit_cost', 'quantity_received'] as $k) {
                            $h[$k] = (float) $h[$k];
                        }
                    } unset($h);
                }
                $summary = ['open_orders' => 0, 'pending_receipts' => 0, 'month_spend' => 0.0, 'active_suppliers' => 0];
                foreach ($orders as $o) {
                    if (in_array($o['status'], ['draft', 'sent', 'partially_received'], true)) {
                        $summary['open_orders']++;
                    } if ((float) $o['pending_quantity'] > 0 && $o['status'] !== 'cancelled') {
                        $summary['pending_receipts']++;
                    } if (substr((string) $o['created_at'], 0, 7) === gmdate('Y-m')) {
                        $summary['month_spend'] += (float) $o['total_amount'];
                    }
                }
                $summary['active_suppliers'] = count(array_filter($suppliers, static fn ($s) => $s['status'] === 'active'));
                $summary['stock_value'] = array_sum(array_map(static fn ($c) => (float) $c['layered_value'], $costRows));
                json_response(['ok' => true, 'suppliers' => $suppliers, 'ingredients' => $ingredients, 'orders' => $orders, 'summary' => $summary, 'costing_method' => $costMethod, 'cost_rows' => $costRows, 'cost_history' => $history, 'permissions' => [
                    'suppliers_view' => in_array('suppliers.view', $perms, true) || in_array('suppliers.manage', $perms, true),
                    'suppliers_manage' => in_array('suppliers.manage', $perms, true),
                    'purchases_view' => in_array('purchases.view', $perms, true) || in_array('purchases.manage', $perms, true) || in_array('purchases.receive', $perms, true),
                    'purchases_manage' => in_array('purchases.manage', $perms, true),
                    'purchases_receive' => in_array('purchases.receive', $perms, true) || in_array('purchases.manage', $perms, true),
                    'costs_view' => in_array('inventory.costs.view', $perms, true) || in_array('inventory.costs.manage', $perms, true),
                    'costs_manage' => in_array('inventory.costs.manage', $perms, true),
                ]]);
            }
            require_method('POST');
            $body = json_body();
            require_csrf($body);
            $action = (string) ($body['action'] ?? '');
            if ($action === 'supplier.save') {
                $actor = require_permission('suppliers.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                $name = $this->purchasing_text($body['name'] ?? '', 160);
                $taxId = $this->purchasing_text($body['tax_id'] ?? '', 20);
                $contact = $this->purchasing_text($body['contact_name'] ?? '', 120);
                $email = strtolower($this->purchasing_text($body['email'] ?? '', 190));
                $phone = $this->purchasing_text($body['phone'] ?? '', 40);
                $address = $this->purchasing_text($body['address'] ?? '', 255);
                $city = $this->purchasing_text($body['city'] ?? '', 100);
                $notes = $this->purchasing_text($body['notes'] ?? '', 1000);
                $ratingRaw = $body['rating'] ?? '';
                $rating = ($ratingRaw === '' || $ratingRaw === null) ? null : filter_var($ratingRaw, FILTER_VALIDATE_FLOAT);
                if ($name === '') {
                    throw new PurchasingProblem('El nombre del proveedor es obligatorio.');
                }
                if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new PurchasingProblem('El correo del proveedor no es válido.');
                }
                if ($rating !== null && ($rating === false || $rating < 1 || $rating > 5)) {
                    throw new PurchasingProblem('La calificación debe estar entre 1 y 5.');
                }
                if ($taxId === '') {
                    $taxId = null;
                }
                if ($id) {
                    $check = $pdo->prepare('SELECT id FROM suppliers WHERE id=:id');
                    $check->execute(['id' => $id]);
                    if (! $check->fetchColumn()) {
                        throw new PurchasingProblem('No existe el proveedor que intentas editar.', 404);
                    }
                    $stmt = $pdo->prepare('UPDATE suppliers SET name=:name,tax_id=:tax,contact_name=:contact,email=:email,phone=:phone,address=:address,city=:city,notes=:notes,rating=:rating,updated_by=:user WHERE id=:id');
                    $stmt->execute(['name' => $name, 'tax' => $taxId, 'contact' => $contact !== '' ? $contact : null, 'email' => $email !== '' ? $email : null, 'phone' => $phone !== '' ? $phone : null, 'address' => $address !== '' ? $address : null, 'city' => $city !== '' ? $city : null, 'notes' => $notes !== '' ? $notes : null, 'rating' => $rating, 'user' => $actor['id'], 'id' => $id]);
                    audit_log($pdo, (int) $actor['id'], 'supplier.updated', ['supplier_id' => $id, 'name' => $name]);
                    json_response(['ok' => true, 'message' => 'Proveedor actualizado.']);
                }
                $stmt = $pdo->prepare("INSERT INTO suppliers (name,tax_id,contact_name,email,phone,address,city,notes,rating,status,created_by,updated_by,created_at,updated_at) VALUES (:name,:tax,:contact,:email,:phone,:address,:city,:notes,:rating,'active',:created_by,:updated_by,NOW(),NOW())");
                $stmt->execute(['name' => $name, 'tax' => $taxId, 'contact' => $contact !== '' ? $contact : null, 'email' => $email !== '' ? $email : null, 'phone' => $phone !== '' ? $phone : null, 'address' => $address !== '' ? $address : null, 'city' => $city !== '' ? $city : null, 'notes' => $notes !== '' ? $notes : null, 'rating' => $rating, 'created_by' => $actor['id'], 'updated_by' => $actor['id']]);
                $newId = (int) $pdo->lastInsertId();
                audit_log($pdo, (int) $actor['id'], 'supplier.created', ['supplier_id' => $newId, 'name' => $name]);
                json_response(['ok' => true, 'message' => 'Proveedor registrado.', 'id' => $newId]);
            }
            if ($action === 'supplier.status') {
                $actor = require_permission('suppliers.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $status = (string) ($body['status'] ?? '');
                if (! $id || ! in_array($status, ['active', 'inactive'], true)) {
                    throw new PurchasingProblem('Proveedor o estado no válido.');
                }
                $stmt = $pdo->prepare('UPDATE suppliers SET status=:status,updated_by=:user,updated_at=NOW() WHERE id=:id');
                $stmt->execute(['status' => $status, 'user' => $actor['id'], 'id' => $id]);
                if (! $stmt->rowCount()) {
                    $check = $pdo->prepare('SELECT id FROM suppliers WHERE id=:id');
                    $check->execute(['id' => $id]);
                    if (! $check->fetchColumn()) {
                        throw new PurchasingProblem('Proveedor no encontrado.', 404);
                    }
                }
                audit_log($pdo, (int) $actor['id'], 'supplier.status_changed', ['supplier_id' => $id, 'status' => $status]);
                json_response(['ok' => true, 'message' => 'Estado del proveedor actualizado.']);
            }
            if ($action === 'costing.set_method') {
                $actor = require_permission('inventory.costs.manage');
                $method = (string) ($body['method'] ?? '');
                if (! in_array($method, ['FIFO', 'LIFO', 'AVG'], true)) {
                    throw new PurchasingProblem('Método de costeo no válido.');
                }
                $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key,setting_value,updated_by,updated_at) VALUES ('inventory_costing_method',:value,:user,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by),updated_at=NOW()");
                $stmt->execute(['value' => $method, 'user' => $actor['id']]);
                audit_log($pdo, (int) $actor['id'], 'inventory.costing_method_changed', ['method' => $method]);
                json_response(['ok' => true, 'message' => 'Método de valoración actualizado a '.$method.'.']);
            }
            if ($action === 'order.create') {
                $actor = require_permission('purchases.manage');
                $supplierId = filter_var($body['supplier_id'] ?? null, FILTER_VALIDATE_INT);
                $expected = trim((string) ($body['expected_date'] ?? ''));
                $notes = $this->purchasing_text($body['notes'] ?? '', 1000);
                $taxRate = filter_var($body['tax_rate'] ?? 0, FILTER_VALIDATE_FLOAT);
                $shipping = filter_var($body['shipping_cost'] ?? 0, FILTER_VALIDATE_FLOAT);
                $items = $body['items'] ?? null;
                if (! $supplierId || ! is_array($items) || count($items) < 1 || count($items) > 100 || $taxRate === false || $taxRate < 0 || $taxRate > 25 || $shipping === false || $shipping < 0 || $shipping > 100000) {
                    throw new PurchasingProblem('Revisa el proveedor, impuestos, transporte y al menos una línea de compra.');
                }
                if ($expected !== '') {
                    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $expected);
                    $dateErrors = DateTimeImmutable::getLastErrors();
                    if (! $date || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $date->format('Y-m-d') !== $expected) {
                        throw new PurchasingProblem('La fecha estimada no es válida.');
                    }
                }
                $pdo->beginTransaction();
                try {
                    $s = $pdo->prepare("SELECT id,name FROM suppliers WHERE id=:id AND status='active' FOR UPDATE");
                    $s->execute(['id' => $supplierId]);
                    $supplier = $s->fetch();
                    if (! $supplier) {
                        throw new PurchasingProblem('Selecciona un proveedor activo.', 409);
                    }
                    $validated = [];
                    $seen = [];
                    $subtotal = 0.0;
                    foreach ($items as $row) {
                        $ingredientId = filter_var($row['ingredient_id'] ?? null, FILTER_VALIDATE_INT);
                        $qty = filter_var($row['quantity'] ?? null, FILTER_VALIDATE_FLOAT);
                        $unitCost = filter_var($row['unit_cost'] ?? null, FILTER_VALIDATE_FLOAT);
                        if (! $ingredientId || $qty === false || $qty <= 0 || $qty > 10000000 || $unitCost === false || $unitCost < 0 || $unitCost > 100000000) {
                            throw new PurchasingProblem('Una línea de compra contiene datos no válidos.');
                        }
                        if (isset($seen[$ingredientId])) {
                            throw new PurchasingProblem('No repitas el mismo ingrediente en varias líneas de una misma orden.');
                        }$seen[$ingredientId] = true;
                        $ig = $pdo->prepare("SELECT id,name,unit FROM ingredients WHERE id=:id AND status='active' FOR UPDATE");
                        $ig->execute(['id' => $ingredientId]);
                        $ingredient = $ig->fetch();
                        if (! $ingredient) {
                            throw new PurchasingProblem('Hay un ingrediente inactivo o inexistente en la orden.', 409);
                        }
                        $line = round((float) $qty * (float) $unitCost, 2);
                        $subtotal += $line;
                        $validated[] = ['ingredient_id' => (int) $ingredient['id'], 'name' => $ingredient['name'], 'unit' => $ingredient['unit'], 'qty' => round((float) $qty, 3), 'cost' => round((float) $unitCost, 4), 'line' => $line];
                    }
                    $subtotal = round($subtotal, 2);
                    $tax = round($subtotal * ((float) $taxRate) / 100, 2);
                    $total = round($subtotal + $tax + (float) $shipping, 2);
                    $temp = 'TMP-'.bin2hex(random_bytes(8));
                    $stmt = $pdo->prepare("INSERT INTO purchase_orders (order_number,supplier_id,status,order_date,expected_date,notes,subtotal,tax_rate,tax_amount,shipping_cost,total_amount,created_by,created_at,updated_at) VALUES (:number,:supplier,'draft',CURDATE(),:expected,:notes,:subtotal,:taxrate,:tax,:shipping,:total,:user,NOW(),NOW())");
                    $stmt->execute(['number' => $temp, 'supplier' => $supplierId, 'expected' => $expected !== '' ? $expected : null, 'notes' => $notes !== '' ? $notes : null, 'subtotal' => number_format($subtotal, 2, '.', ''), 'taxrate' => number_format((float) $taxRate, 2, '.', ''), 'tax' => number_format($tax, 2, '.', ''), 'shipping' => number_format((float) $shipping, 2, '.', ''), 'total' => number_format($total, 2, '.', ''), 'user' => $actor['id']]);
                    $orderId = (int) $pdo->lastInsertId();
                    $orderNumber = 'OC-'.gmdate('Y').'-'.str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
                    $pdo->prepare('UPDATE purchase_orders SET order_number=:number WHERE id=:id')->execute(['number' => $orderNumber, 'id' => $orderId]);
                    $ins = $pdo->prepare('INSERT INTO purchase_order_items (purchase_order_id,ingredient_id,ingredient_name_snapshot,unit,ordered_quantity,received_quantity,unit_cost,line_total,created_at) VALUES (:order,:ingredient,:name,:unit,:quantity,0,:cost,:line,NOW())');
                    foreach ($validated as $v) {
                        $ins->execute(['order' => $orderId, 'ingredient' => $v['ingredient_id'], 'name' => $v['name'], 'unit' => $v['unit'], 'quantity' => number_format($v['qty'], 3, '.', ''), 'cost' => number_format($v['cost'], 4, '.', ''), 'line' => number_format($v['line'], 2, '.', '')]);
                    }
                    $pdo->commit();
                    audit_log($pdo, (int) $actor['id'], 'purchase_order.created', ['purchase_order_id' => $orderId, 'order_number' => $orderNumber, 'supplier_id' => $supplierId, 'total' => $total]);
                    json_response(['ok' => true, 'message' => 'Orden de compra '.$orderNumber.' creada como borrador.', 'id' => $orderId, 'order_number' => $orderNumber]);
                } catch (Throwable $e) {
                    if ($e instanceof HttpResponseException) {
                        throw $e;
                    }if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }throw $e;
                }
            }
            if ($action === 'order.send') {
                $actor = require_permission('purchases.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                if (! $id) {
                    throw new PurchasingProblem('Orden no válida.');
                }
                $stmt = $pdo->prepare('SELECT po.*,s.name AS supplier_name,s.email AS supplier_email FROM purchase_orders po JOIN suppliers s ON s.id=po.supplier_id WHERE po.id=:id FOR UPDATE');
                $pdo->beginTransaction();
                try {
                    $stmt->execute(['id' => $id]);
                    $order = $stmt->fetch();
                    if (! $order) {
                        throw new PurchasingProblem('Orden no encontrada.', 404);
                    }if ($order['status'] !== 'draft') {
                        throw new PurchasingProblem('Solo puedes enviar una orden en borrador.', 409);
                    }
                    $it = $pdo->prepare('SELECT ingredient_name_snapshot,unit,ordered_quantity,unit_cost,line_total FROM purchase_order_items WHERE purchase_order_id=:id ORDER BY id');
                    $it->execute(['id' => $id]);
                    $items = $it->fetchAll();
                    if (! $items) {
                        throw new PurchasingProblem('La orden no tiene líneas.');
                    }
                    $emailSent = false;
                    if (! empty($order['supplier_email'])) {
                        $mailConfig = config('desego.mail');
                        $emailSent = $this->purchasing_send_order_email((string) $order['supplier_email'], (string) $order['supplier_name'], (string) $order['order_number'], $items, (float) $order['total_amount'], $mailConfig);
                    }
                    if (! $emailSent) {
                        $pdo->rollBack();
                        throw new PurchasingProblem(! empty($order['supplier_email']) ? 'No se pudo enviar el correo. Configura config/mail.php y verifica SMTP; la orden sigue en borrador.' : 'El proveedor no tiene correo. Registra un email antes de enviar la orden.', 409);
                    }
                    $pdo->prepare("UPDATE purchase_orders SET status='sent',sent_by=:user,sent_at=NOW(),updated_at=NOW() WHERE id=:id")->execute(['user' => $actor['id'], 'id' => $id]);
                    $pdo->commit();
                    audit_log($pdo, (int) $actor['id'], 'purchase_order.sent', ['purchase_order_id' => $id, 'order_number' => $order['order_number'], 'supplier_email' => $order['supplier_email']]);
                    json_response(['ok' => true, 'message' => 'Orden enviada al correo del proveedor.']);
                } catch (Throwable $e) {
                    if ($e instanceof HttpResponseException) {
                        throw $e;
                    }if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }throw $e;
                }
            }
            if ($action === 'order.mark_sent') {
                $actor = require_permission('purchases.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                if (! $id) {
                    throw new PurchasingProblem('Orden no válida.');
                }
                $stmt = $pdo->prepare("UPDATE purchase_orders SET status='sent',sent_by=:user,sent_at=NOW(),updated_at=NOW() WHERE id=:id AND status='draft'");
                $stmt->execute(['user' => $actor['id'], 'id' => $id]);
                if (! $stmt->rowCount()) {
                    throw new PurchasingProblem('La orden no está en borrador o no existe.', 409);
                }audit_log($pdo, (int) $actor['id'], 'purchase_order.marked_sent_manual', ['purchase_order_id' => $id]);
                json_response(['ok' => true, 'message' => 'Orden marcada como enviada por un canal externo.']);
            }
            if ($action === 'order.cancel') {
                $actor = require_permission('purchases.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                if (! $id) {
                    throw new PurchasingProblem('Orden no válida.');
                }
                $stmt = $pdo->prepare("UPDATE purchase_orders SET status='cancelled',cancelled_by=:user,cancelled_at=NOW(),updated_at=NOW() WHERE id=:id AND status IN ('draft','sent')");
                $stmt->execute(['user' => $actor['id'], 'id' => $id]);
                if (! $stmt->rowCount()) {
                    throw new PurchasingProblem('Solo puedes cancelar una orden sin recepción de mercadería.', 409);
                }audit_log($pdo, (int) $actor['id'], 'purchase_order.cancelled', ['purchase_order_id' => $id]);
                json_response(['ok' => true, 'message' => 'Orden cancelada.']);
            }
            if ($action === 'order.receive') {
                $actor = require_any_permission(['purchases.receive', 'purchases.manage']);
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $invoice = $this->purchasing_text($body['supplier_invoice'] ?? '', 120);
                $notes = $this->purchasing_text($body['notes'] ?? '', 1000);
                $receivedItems = $body['items'] ?? null;
                if (! $id || ! is_array($receivedItems) || count($receivedItems) < 1 || count($receivedItems) > 100) {
                    throw new PurchasingProblem('Selecciona al menos una línea y una cantidad recibida.');
                }
                $pdo->beginTransaction();
                try {
                    $st = $pdo->prepare('SELECT po.*,s.name AS supplier_name FROM purchase_orders po JOIN suppliers s ON s.id=po.supplier_id WHERE po.id=:id FOR UPDATE');
                    $st->execute(['id' => $id]);
                    $order = $st->fetch();
                    if (! $order) {
                        throw new PurchasingProblem('Orden no encontrada.', 404);
                    }if (! in_array($order['status'], ['sent', 'partially_received'], true)) {
                        throw new PurchasingProblem('Solo se pueden recepcionar órdenes enviadas o parcialmente recibidas.', 409);
                    }
                    $validated = [];
                    $seen = [];
                    foreach ($receivedItems as $row) {
                        $itemId = filter_var($row['item_id'] ?? null, FILTER_VALIDATE_INT);
                        $qty = filter_var($row['quantity'] ?? 0, FILTER_VALIDATE_FLOAT);
                        $unitCost = filter_var($row['unit_cost'] ?? null, FILTER_VALIDATE_FLOAT);
                        if (! $itemId || $qty === false || $qty < 0 || $unitCost === false || $unitCost < 0 || $unitCost > 100000000) {
                            throw new PurchasingProblem('Cantidad o costo de recepción no válido.');
                        }if ($qty == 0) {
                            continue;
                        }if (isset($seen[$itemId])) {
                            throw new PurchasingProblem('Una línea aparece duplicada en la recepción.');
                        }$seen[$itemId] = true;
                        $rowStmt = $pdo->prepare('SELECT poi.*,i.stock_quantity,i.cost_per_unit,i.name AS current_name,i.status AS ingredient_status FROM purchase_order_items poi JOIN ingredients i ON i.id=poi.ingredient_id WHERE poi.id=:item_id AND poi.purchase_order_id=:order_id FOR UPDATE');
                        $rowStmt->execute(['item_id' => $itemId, 'order_id' => $id]);
                        $line = $rowStmt->fetch();
                        if (! $line) {
                            throw new PurchasingProblem('Una línea de recepción no pertenece a esta orden.', 409);
                        }$remaining = round((float) $line['ordered_quantity'] - (float) $line['received_quantity'], 3);
                        if ($qty > $remaining + 0.0001) {
                            throw new PurchasingProblem('No puedes recibir más cantidad de la pendiente de '.$line['ingredient_name_snapshot'].'.', 409);
                        }if ($line['ingredient_status'] !== 'active') {
                            throw new PurchasingProblem('El ingrediente '.$line['ingredient_name_snapshot'].' está inactivo.', 409);
                        }
                        $validated[] = ['line' => $line, 'qty' => round((float) $qty, 3), 'cost' => round((float) $unitCost, 4)];
                    }
                    if (! $validated) {
                        throw new PurchasingProblem('Ingresa alguna cantidad mayor que cero.');
                    }
                    $receiptTemp = 'TMP-RC-'.bin2hex(random_bytes(6));
                    $r = $pdo->prepare('INSERT INTO purchase_receipts (receipt_number,purchase_order_id,supplier_id,supplier_invoice,notes,received_by,received_at) VALUES (:number,:order,:supplier,:invoice,:notes,:user,NOW())');
                    $r->execute(['number' => $receiptTemp, 'order' => $id, 'supplier' => $order['supplier_id'], 'invoice' => $invoice !== '' ? $invoice : null, 'notes' => $notes !== '' ? $notes : null, 'user' => $actor['id']]);
                    $receiptId = (int) $pdo->lastInsertId();
                    $receiptNumber = 'RC-'.gmdate('Y').'-'.str_pad((string) $receiptId, 6, '0', STR_PAD_LEFT);
                    $pdo->prepare('UPDATE purchase_receipts SET receipt_number=:number WHERE id=:id')->execute(['number' => $receiptNumber, 'id' => $receiptId]);
                    $movement = $pdo->prepare("INSERT INTO inventory_movements (ingredient_id,user_id,movement_type,quantity_delta,previous_quantity,new_quantity,unit_cost,reference,notes,created_at) VALUES (:ingredient,:user,'entry',:delta,:before,:after,:cost,:reference,:notes,NOW())");
                    $receiptItemInsert = $pdo->prepare('INSERT INTO purchase_receipt_items (receipt_id,purchase_order_item_id,ingredient_id,quantity,unit_cost,line_total,stock_before,stock_after,created_at) VALUES (:receipt,:order_item,:ingredient,:quantity,:cost,:total,:before,:after,NOW())');
                    $orderItemUpdate = $pdo->prepare('UPDATE purchase_order_items SET received_quantity=:received WHERE id=:id');
                    $stockUpdate = $pdo->prepare('UPDATE ingredients SET stock_quantity=:stock,cost_per_unit=:cost,updated_at=NOW() WHERE id=:id');
                    $layerInsert = $pdo->prepare("INSERT INTO inventory_cost_layers (ingredient_id,layer_type,receipt_item_id,quantity_received,quantity_remaining,unit_cost,notes,received_at) VALUES (:ingredient,'purchase',:receipt_item,:quantity,:remaining,:cost,:notes,NOW())");
                    $costHist = $pdo->prepare('INSERT INTO ingredient_cost_history (ingredient_id,supplier_id,purchase_order_id,receipt_id,user_id,previous_unit_cost,new_unit_cost,quantity_received,costing_method,reason,created_at) VALUES (:ingredient,:supplier,:order,:receipt,:user,:previous,:new,:quantity,:method,:reason,NOW())');
                    $setting = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key='inventory_costing_method'");
                    $setting->execute();
                    $costMethod = (string) ($setting->fetchColumn() ?: 'FIFO');
                    foreach ($validated as $v) {
                        $line = $v['line'];
                        $qty = $v['qty'];
                        $unitCost = $v['cost'];
                        $before = (float) $line['stock_quantity'];
                        $after = round($before + $qty, 3);
                        $oldCost = (float) $line['cost_per_unit'];
                        $newCost = $after > 0 ? round((($before * $oldCost) + ($qty * $unitCost)) / $after, 4) : $unitCost;
                        $stockUpdate->execute(['stock' => number_format($after, 3, '.', ''), 'cost' => number_format($newCost, 4, '.', ''), 'id' => $line['ingredient_id']]);
                        $orderItemUpdate->execute(['received' => number_format((float) $line['received_quantity'] + $qty, 3, '.', ''), 'id' => $line['id']]);
                        $receiptItemInsert->execute(['receipt' => $receiptId, 'order_item' => $line['id'], 'ingredient' => $line['ingredient_id'], 'quantity' => number_format($qty, 3, '.', ''), 'cost' => number_format($unitCost, 4, '.', ''), 'total' => number_format(round($qty * $unitCost, 2), 2, '.', ''), 'before' => number_format($before, 3, '.', ''), 'after' => number_format($after, 3, '.', '')]);
                        $receiptItemId = (int) $pdo->lastInsertId();
                        $layerInsert->execute(['ingredient' => $line['ingredient_id'], 'receipt_item' => $receiptItemId, 'quantity' => number_format($qty, 3, '.', ''), 'remaining' => number_format($qty, 3, '.', ''), 'cost' => number_format($unitCost, 4, '.', ''), 'notes' => 'Recepción '.$receiptNumber.' / orden '.$order['order_number']]);
                        $movement->execute(['ingredient' => $line['ingredient_id'], 'user' => $actor['id'], 'delta' => number_format($qty, 3, '.', ''), 'before' => number_format($before, 3, '.', ''), 'after' => number_format($after, 3, '.', ''), 'cost' => number_format($unitCost, 4, '.', ''), 'reference' => $order['order_number'], 'notes' => 'Recepción '.$receiptNumber.($invoice !== '' ? ' · Doc. proveedor '.$invoice : '')]);
                        if (abs($newCost - $oldCost) > 0.00005) {
                            $costHist->execute(['ingredient' => $line['ingredient_id'], 'supplier' => $order['supplier_id'], 'order' => $id, 'receipt' => $receiptId, 'user' => $actor['id'], 'previous' => number_format($oldCost, 4, '.', ''), 'new' => number_format($newCost, 4, '.', ''), 'quantity' => number_format($qty, 3, '.', ''), 'method' => $costMethod, 'reason' => 'Costo promedio ponderado actualizado por recepción '.$receiptNumber]);
                        }
                    }
                    $pending = $pdo->prepare('SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_id=:id AND received_quantity+0.0001<ordered_quantity');
                    $pending->execute(['id' => $id]);
                    $isComplete = (int) $pending->fetchColumn() === 0;
                    $newStatus = $isComplete ? 'received' : 'partially_received';
                    $pdo->prepare('UPDATE purchase_orders SET status=:status,received_at=CASE WHEN :complete=1 THEN NOW() ELSE received_at END,updated_at=NOW() WHERE id=:id')->execute(['status' => $newStatus, 'complete' => $isComplete ? 1 : 0, 'id' => $id]);
                    $pdo->commit();
                    audit_log($pdo, (int) $actor['id'], 'purchase_order.received', ['purchase_order_id' => $id, 'receipt_id' => $receiptId, 'receipt_number' => $receiptNumber, 'status' => $newStatus, 'items' => count($validated)]);
                    json_response(['ok' => true, 'message' => 'Recepción '.$receiptNumber.' registrada. El stock y los costos se actualizaron.', 'receipt_number' => $receiptNumber, 'status' => $newStatus]);
                } catch (Throwable $e) {
                    if ($e instanceof HttpResponseException) {
                        throw $e;
                    }if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }throw $e;
                }
            }
            json_response(['ok' => false, 'message' => 'Acción de compras no reconocida.'], 404);
        } catch (PurchasingProblem $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], $e->status);
        } catch (PDOException $e) {
            error_log('[DESEGO purchasing] '.$e->getMessage());
            if ((string) $e->getCode() === '23000') {
                json_response(['ok' => false, 'message' => 'Hay un dato duplicado o relacionado con otro registro. Revisa el proveedor, ingrediente o número fiscal.'], 409);
            }
            json_response(['ok' => false, 'message' => 'No se pudo completar la operación de compras. Revisa la base de datos y la migración del Paso 13.'], 500);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            error_log('[DESEGO purchasing] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'Ocurrió un error en el módulo de compras. Revisa la configuración y los registros del servidor.'], 500);
        }

        return response()->noContent();
    }

    protected function purchasing_text(mixed $value, int $max = 255): string
    {
        $value = trim((string) ($value ?? ''));
        if (text_length($value) > $max) {
            throw new PurchasingProblem('Uno de los campos de texto supera la longitud permitida.');
        }

        return $value;
    }

    protected function purchasing_status_label(string $status): string
    {
        return match ($status) {
            'draft' => 'Borrador', 'sent' => 'Enviada', 'partially_received' => 'Recepción parcial',
            'received' => 'Recibida', 'cancelled' => 'Cancelada', default => $status,
        };
    }

    protected function purchasing_send_order_email(string $to, string $supplierName, string $orderNumber, array $items, float $total, array $config): bool
    {
        $from = trim((string) ($config['from_email'] ?? ''));
        if (! filter_var($to, FILTER_VALIDATE_EMAIL) || ! filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $fromName = (string) ($config['from_name'] ?? 'DESEGO CEVICHERÍA');
        $subject = 'Orden de compra '.$orderNumber.' - DESEGO CEVICHERÍA';
        $lines = ['Estimado/a '.$supplierName.',', '', 'Adjuntamos en este correo el detalle de la orden de compra '.$orderNumber.'.', '', 'Productos solicitados:'];
        foreach ($items as $item) {
            $lines[] = '- '.$item['ingredient_name_snapshot'].': '.number_format((float) $item['ordered_quantity'], 3, ',', '.').' '.$item['unit'].' x S/ '.number_format((float) $item['unit_cost'], 4, ',', '.').' = S/ '.number_format((float) $item['line_total'], 2, ',', '.');
        }
        $lines[] = '';
        $lines[] = 'Subtotal: S/ '.number_format(array_sum(array_map(static fn ($r) => (float) $r['line_total'], $items)), 2, ',', '.');
        $lines[] = 'Total con impuestos y costos: S/ '.number_format($total, 2, ',', '.');
        $lines[] = '';
        $lines[] = 'Saludos,';
        $lines[] = 'DESEGO CEVICHERÍA';
        $message = implode("\n", $lines);
        try {
            Mail::raw($message, function (Message $mail) use ($to, $subject): void {
                $mail->to($to)->subject($subject);
            });

            return true;
        } catch (Throwable $error) {
            report($error);

            return false;
        }
    }
}
