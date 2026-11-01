<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Exceptions\Desego\OperationsProblem;
use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function App\Support\Desego\audit_log;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\json_body;
use function App\Support\Desego\json_response;
use function App\Support\Desego\require_any_permission;
use function App\Support\Desego\require_csrf;
use function App\Support\Desego\require_method;
use function App\Support\Desego\require_permission;
use function App\Support\Desego\text_length;

class OperationsController extends Controller
{
    public function __invoke(): Response
    {
        try {
            $pdo = desego_db();
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            if ($method === 'GET') {
                $view = (string) (request()->query()['view'] ?? '');
                if ($view === 'kitchen') {
                    $actor = require_permission('kitchen.view');
                    $sql = $this->operations_order_summary_query()."
                    WHERE o.status IN ('Pendiente','Preparando','Listo')
                      AND o.cancelled_at IS NULL
                    ORDER BY FIELD(o.status,'Pendiente','Preparando','Listo'), o.created_at ASC
                    LIMIT 150";
                    $orders = array_map([$this, 'operations_normalize_order'], $pdo->query($sql)->fetchAll());
                    json_response([
                        'ok' => true,
                        'orders' => $orders,
                        'permissions' => ['can_manage' => in_array('kitchen.manage', $actor['permissions'] ?? [], true)],
                        'updated_at' => date(DATE_ATOM),
                    ]);
                }
                if ($view === 'delivery') {
                    $actor = require_permission('delivery.view');
                    $sql = "SELECT o.id, o.order_number, o.customer_name, o.order_type, o.table_label, o.notes,
                           o.status, o.payment_status, o.total, o.created_at, o.updated_at,
                           d.recipient_phone, d.address_line, d.zone, d.courier_user_id,
                           d.delivery_fee, d.status AS delivery_status, d.estimated_delivery_at,
                           d.picked_up_at, d.delivered_at, d.incident_notes,
                           courier.name AS courier_name,
                           TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) AS elapsed_minutes,
                           (SELECT GROUP_CONCAT(CONCAT(oi.quantity, ' × ', oi.product_name) ORDER BY oi.id SEPARATOR ', ')
                            FROM order_items oi WHERE oi.order_id = o.id) AS item_summary
                    FROM orders o
                    INNER JOIN order_delivery_details d ON d.order_id = o.id
                    LEFT JOIN users courier ON courier.id = d.courier_user_id
                    WHERE o.order_type = 'Delivery' AND o.cancelled_at IS NULL";
                    $params = [];
                    if (($actor['role_code'] ?? '') === 'delivery') {
                        $sql .= ' AND d.courier_user_id = :courier_id';
                        $params['courier_id'] = (int) $actor['id'];
                    }
                    $sql .= " ORDER BY FIELD(d.status,'Incidencia','Pendiente','Asignado','En reparto','Entregado'), o.created_at DESC LIMIT 200";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $orders = array_map([$this, 'operations_normalize_order'], $stmt->fetchAll());
                    $couriers = [];
                    if (in_array('delivery.manage', $actor['permissions'] ?? [], true)) {
                        $couriers = $pdo->query("SELECT u.id, u.name, u.email FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.status = 'active' AND r.code = 'delivery' ORDER BY u.name")->fetchAll();
                        foreach ($couriers as &$courier) {
                            $courier['id'] = (int) $courier['id'];
                        }
                        unset($courier);
                    }
                    $zones = [];
                    try {
                        $zones = $pdo->query('SELECT id, name, district, zone_sector, center_latitude, center_longitude, radius_km, delivery_fee, fee_confirmed, polygon_coordinates, min_order_amount, estimated_minutes_min, estimated_minutes_max, is_active FROM delivery_zones ORDER BY id')->fetchAll();
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
                            $z['is_active'] = (bool) $z['is_active'];
                            $z['polygon_coordinates'] = ! empty($z['polygon_coordinates']) ? (json_decode((string) $z['polygon_coordinates'], true) ?: null) : null;
                        }
                        unset($z);
                    } catch (Throwable) {
                    }
                    json_response([
                        'ok' => true,
                        'orders' => $orders,
                        'couriers' => $couriers,
                        'delivery_zones' => $zones,
                        'permissions' => [
                            'can_manage' => in_array('delivery.manage', $actor['permissions'] ?? [], true),
                            'can_update' => in_array('delivery.update', $actor['permissions'] ?? [], true) || in_array('delivery.manage', $actor['permissions'] ?? [], true),
                            'is_courier' => ($actor['role_code'] ?? '') === 'delivery',
                        ],
                        'updated_at' => date(DATE_ATOM),
                    ]);
                }
                json_response(['ok' => false, 'message' => 'Vista operativa no válida.'], 404);
            }
            require_method('POST');
            $body = json_body();
            require_csrf($body);
            $action = (string) ($body['action'] ?? '');
            if ($action === 'kitchen_status') {
                $actor = require_permission('kitchen.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $status = (string) ($body['status'] ?? '');
                if (! $id || ! in_array($status, ['Preparando', 'Listo'], true)) {
                    throw new OperationsProblem('Pedido o estado de cocina no válido.', 422);
                }
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('SELECT id, order_number, status, order_type FROM orders WHERE id = :id FOR UPDATE');
                    $stmt->execute(['id' => $id]);
                    $order = $stmt->fetch();
                    if (! $order || $order['status'] === 'Cancelado') {
                        throw new OperationsProblem('El pedido no existe o fue cancelado.', 404);
                    }
                    $allowed = ['Pendiente' => 'Preparando', 'Preparando' => 'Listo'];
                    if (($allowed[$order['status']] ?? null) !== $status) {
                        throw new OperationsProblem('La preparación debe avanzar de Pendiente a Preparando y luego a Listo.', 409);
                    }
                    $pdo->prepare('UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :id')->execute(['status' => $status, 'id' => $id]);
                    $pdo->commit();
                    audit_log($pdo, (int) $actor['id'], 'kitchen.status_changed', ['order_id' => (int) $id, 'order_number' => $order['order_number'], 'status' => $status]);
                    json_response(['ok' => true, 'message' => $order['order_number'].' pasó a '.$status.'.']);
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
            if ($action === 'save_delivery') {
                $actor = require_permission('delivery.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $phone = trim((string) ($body['recipient_phone'] ?? ''));
                $address = trim((string) ($body['address_line'] ?? ''));
                $zone = trim((string) ($body['zone'] ?? ''));
                $courierRaw = $body['courier_user_id'] ?? null;
                $courierId = ($courierRaw === null || $courierRaw === '')
                    ? null
                    : filter_var($courierRaw, FILTER_VALIDATE_INT);
                $eta = trim((string) ($body['estimated_delivery_at'] ?? ''));
                if (! $id || $address === '' || $phone === '') {
                    throw new OperationsProblem('La dirección y el teléfono del destinatario son obligatorios.', 422);
                }
                if (text_length($phone) > 30 || text_length($address) > 255 || text_length($zone) > 100) {
                    throw new OperationsProblem('Revisa el teléfono, dirección o zona; uno de los campos es demasiado largo.', 422);
                }
                if ($eta !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $eta)) {
                    throw new OperationsProblem('La hora estimada no es válida.', 422);
                }
                if ($courierId === false) {
                    throw new OperationsProblem('Selecciona un repartidor válido.', 422);
                }
                if ($courierId) {
                    $check = $pdo->prepare("SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = :id AND u.status = 'active' AND r.code = 'delivery'");
                    $check->execute(['id' => $courierId]);
                    if ((int) $check->fetchColumn() < 1) {
                        throw new OperationsProblem('El usuario seleccionado no es un repartidor activo.', 422);
                    }
                }
                $orderStmt = $pdo->prepare('SELECT id, order_number, status, order_type FROM orders WHERE id = :id LIMIT 1');
                $orderStmt->execute(['id' => $id]);
                $order = $orderStmt->fetch();
                if (! $order || $order['order_type'] !== 'Delivery' || in_array($order['status'], ['Cancelado', 'Entregado'], true)) {
                    throw new OperationsProblem('Solo puedes configurar un pedido Delivery que siga activo.', 409);
                }
                $estimated = $eta !== '' ? str_replace('T', ' ', $eta).':00' : null;
                $currentStmt = $pdo->prepare('SELECT status FROM order_delivery_details WHERE order_id = :id');
                $currentStmt->execute(['id' => $id]);
                $currentStatus = $currentStmt->fetchColumn();
                $deliveryStatus = $currentStatus ?: 'Pendiente';
                if ($courierId && $deliveryStatus === 'Pendiente') {
                    $deliveryStatus = 'Asignado';
                }
                if (! $courierId && $deliveryStatus === 'Asignado') {
                    $deliveryStatus = 'Pendiente';
                }
                $pdo->prepare('INSERT INTO order_delivery_details (order_id, recipient_phone, address_line, zone, courier_user_id, delivery_fee, status, estimated_delivery_at, created_at, updated_at)
                       VALUES (:order_id,:phone,:address,:zone,:courier,0,:status,:eta,NOW(),NOW())
                       ON DUPLICATE KEY UPDATE recipient_phone=VALUES(recipient_phone), address_line=VALUES(address_line), zone=VALUES(zone), courier_user_id=VALUES(courier_user_id), status=VALUES(status), estimated_delivery_at=VALUES(estimated_delivery_at), updated_at=NOW()')
                    ->execute(['order_id' => $id, 'phone' => $phone, 'address' => $address, 'zone' => $zone !== '' ? $zone : null, 'courier' => $courierId ?: null, 'status' => $deliveryStatus, 'eta' => $estimated]);
                audit_log($pdo, (int) $actor['id'], 'delivery.details_saved', ['order_id' => (int) $id, 'courier_user_id' => $courierId ?: null]);
                json_response(['ok' => true, 'message' => 'Dirección, contacto y asignación de delivery guardados.']);
            }
            if ($action === 'delivery_status') {
                $actor = require_any_permission(['delivery.manage', 'delivery.update']);
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $status = (string) ($body['status'] ?? '');
                $incident = trim((string) ($body['incident_notes'] ?? ''));
                if (! $id || ! in_array($status, ['En reparto', 'Entregado', 'Incidencia'], true)) {
                    throw new OperationsProblem('Pedido o estado de delivery no válido.', 422);
                }
                if (text_length($incident) > 500) {
                    throw new OperationsProblem('La descripción de la incidencia es demasiado larga.', 422);
                }
                if ($status === 'Incidencia' && $incident === '') {
                    throw new OperationsProblem('Describe brevemente la incidencia.', 422);
                }
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("SELECT o.id, o.order_number, o.status AS order_status, d.status AS delivery_status, d.courier_user_id
                                   FROM orders o INNER JOIN order_delivery_details d ON d.order_id = o.id
                                   WHERE o.id = :id AND o.order_type = 'Delivery' FOR UPDATE");
                    $stmt->execute(['id' => $id]);
                    $delivery = $stmt->fetch();
                    if (! $delivery) {
                        throw new OperationsProblem('No se encontró el seguimiento de este pedido.', 404);
                    }
                    if (($actor['role_code'] ?? '') === 'delivery' && (int) ($delivery['courier_user_id'] ?? 0) !== (int) $actor['id']) {
                        throw new OperationsProblem('Solo puedes actualizar entregas asignadas a tu usuario.', 403);
                    }
                    $current = (string) $delivery['delivery_status'];
                    if ($status === 'En reparto') {
                        if (empty($delivery['courier_user_id'])) {
                            throw new OperationsProblem('Asigna un repartidor antes de marcar En reparto.', 409);
                        }
                        if (! in_array($current, ['Asignado', 'Incidencia'], true) || ! in_array($delivery['order_status'], ['Listo', 'En reparto'], true)) {
                            throw new OperationsProblem('El pedido debe estar listo y asignado antes de salir a reparto.', 409);
                        }
                        $pdo->prepare("UPDATE orders SET status='En reparto', updated_at=NOW() WHERE id=:id")->execute(['id' => $id]);
                        $pdo->prepare("UPDATE order_delivery_details SET status='En reparto', incident_notes=NULL, picked_up_at=COALESCE(picked_up_at,NOW()), updated_at=NOW() WHERE order_id=:id")->execute(['id' => $id]);
                    } elseif ($status === 'Entregado') {
                        if ($current !== 'En reparto' || $delivery['order_status'] !== 'En reparto') {
                            throw new OperationsProblem('Solo puedes confirmar una entrega que esté en reparto.', 409);
                        }
                        $pdo->prepare("UPDATE orders SET status='Entregado', completed_at=NOW(), updated_at=NOW() WHERE id=:id")->execute(['id' => $id]);
                        $pdo->prepare("UPDATE order_delivery_details SET status='Entregado', delivered_at=NOW(), updated_at=NOW() WHERE order_id=:id")->execute(['id' => $id]);
                    } else {
                        if (! in_array($current, ['Asignado', 'En reparto'], true)) {
                            throw new OperationsProblem('Solo puedes registrar una incidencia para una entrega asignada o en ruta.', 409);
                        }
                        $pdo->prepare("UPDATE order_delivery_details SET status='Incidencia', incident_notes=:notes, updated_at=NOW() WHERE order_id=:id")->execute(['notes' => $incident, 'id' => $id]);
                    }
                    $pdo->commit();
                    audit_log($pdo, (int) $actor['id'], 'delivery.status_changed', ['order_id' => (int) $id, 'status' => $status]);
                    json_response(['ok' => true, 'message' => $delivery['order_number'].' · estado de entrega: '.$status.'.']);
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
            if (in_array($action, ['zone.create', 'zone.update', 'zone.toggle', 'zone.delete'], true)) {
                $actor = require_permission('delivery.manage');
                if ($action === 'zone.toggle') {
                    $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                    if (! $id) {
                        throw new OperationsProblem('Zona no válida.', 422);
                    }
                    $active = ! empty($body['is_active']) ? 1 : 0;
                    $pdo->prepare('UPDATE delivery_zones SET is_active = :active, updated_at = NOW() WHERE id = :id')->execute(['active' => $active, 'id' => $id]);
                    audit_log($pdo, (int) $actor['id'], 'delivery_zones.toggled', ['zone_id' => (int) $id, 'is_active' => $active]);
                    json_response(['ok' => true, 'message' => $active ? 'Zona de cobertura activada.' : 'Zona de cobertura desactivada.']);
                }
                if ($action === 'zone.delete') {
                    $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                    if (! $id) {
                        throw new OperationsProblem('Zona no válida.', 422);
                    }
                    $pdo->prepare('DELETE FROM delivery_zones WHERE id = :id')->execute(['id' => $id]);
                    audit_log($pdo, (int) $actor['id'], 'delivery_zones.deleted', ['zone_id' => (int) $id]);
                    json_response(['ok' => true, 'message' => 'Zona de cobertura eliminada.']);
                }
                $name = trim((string) ($body['name'] ?? ''));
                $district = trim((string) ($body['district'] ?? 'Ate'));
                $sector = trim((string) ($body['zone_sector'] ?? ''));
                $lat = ($body['center_latitude'] ?? '') === '' || ($body['center_latitude'] ?? null) === null ? null : filter_var($body['center_latitude'], FILTER_VALIDATE_FLOAT);
                $lng = ($body['center_longitude'] ?? '') === '' || ($body['center_longitude'] ?? null) === null ? null : filter_var($body['center_longitude'], FILTER_VALIDATE_FLOAT);
                $radius = ($body['radius_km'] ?? '') === '' || ($body['radius_km'] ?? null) === null ? null : filter_var($body['radius_km'], FILTER_VALIDATE_FLOAT);
                $fee = filter_var($body['delivery_fee'] ?? 0, FILTER_VALIDATE_FLOAT);
                $feeConfirmed = ! empty($body['fee_confirmed']) ? 1 : 0;
                $minOrder = filter_var($body['min_order_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
                $etaMin = filter_var($body['estimated_minutes_min'] ?? 25, FILTER_VALIDATE_INT);
                $etaMax = filter_var($body['estimated_minutes_max'] ?? 45, FILTER_VALIDATE_INT);
                $isActive = array_key_exists('is_active', $body) ? (! empty($body['is_active']) ? 1 : 0) : 1;
                $polygonRaw = $body['polygon_coordinates'] ?? null;
                $polygonJson = null;
                if (is_array($polygonRaw) && count($polygonRaw) >= 3) {
                    $polygonJson = json_encode($polygonRaw);
                } elseif (is_string($polygonRaw) && trim($polygonRaw) !== '') {
                    $decoded = json_decode($polygonRaw, true);
                    if (is_array($decoded) && count($decoded) >= 3) {
                        $polygonJson = json_encode($decoded);
                    }
                }
                if (text_length($name) < 2 || text_length($name) > 100 || text_length($district) < 2 || text_length($district) > 80 || text_length($sector) > 80
                    || $fee === false || $fee < 0 || $fee > 500 || $minOrder === false || $minOrder < 0
                    || $etaMin === false || $etaMin < 5 || $etaMax === false || $etaMax < $etaMin || $etaMax > 240
                    || ($lat !== null && ($lat === false || $lat < -90 || $lat > 90))
                    || ($lng !== null && ($lng === false || $lng < -180 || $lng > 180))
                    || ($radius !== null && ($radius === false || $radius <= 0 || $radius > 100))) {
                    throw new OperationsProblem('Revisa los datos de la zona de cobertura (nombre, coordenadas, tarifa y tiempos).', 422);
                }
                $params = [
                    'name' => $name,
                    'district' => $district,
                    'sector' => $sector !== '' ? $sector : null,
                    'lat' => $lat !== null ? number_format((float) $lat, 7, '.', '') : null,
                    'lng' => $lng !== null ? number_format((float) $lng, 7, '.', '') : null,
                    'radius' => $radius !== null ? number_format((float) $radius, 2, '.', '') : null,
                    'fee' => number_format((float) $fee, 2, '.', ''),
                    'fee_confirmed' => $feeConfirmed,
                    'polygon' => $polygonJson,
                    'min_order' => number_format((float) $minOrder, 2, '.', ''),
                    'eta_min' => (int) $etaMin,
                    'eta_max' => (int) $etaMax,
                    'active' => $isActive,
                ];
                if ($action === 'zone.create') {
                    $pdo->prepare('INSERT INTO delivery_zones (name, district, zone_sector, center_latitude, center_longitude, radius_km, delivery_fee, fee_confirmed, polygon_coordinates, min_order_amount, estimated_minutes_min, estimated_minutes_max, is_active, created_at, updated_at) VALUES (:name, :district, :sector, :lat, :lng, :radius, :fee, :fee_confirmed, :polygon, :min_order, :eta_min, :eta_max, :active, NOW(), NOW())')->execute($params);
                    $zoneId = (int) $pdo->lastInsertId();
                    audit_log($pdo, (int) $actor['id'], 'delivery_zones.created', ['zone_id' => $zoneId, 'name' => $name]);
                    json_response(['ok' => true, 'id' => $zoneId, 'message' => 'Zona de cobertura registrada.'], 201);
                }
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                if (! $id) {
                    throw new OperationsProblem('Zona no válida.', 422);
                }
                $params['id'] = (int) $id;
                $pdo->prepare('UPDATE delivery_zones SET name=:name, district=:district, zone_sector=:sector, center_latitude=:lat, center_longitude=:lng, radius_km=:radius, delivery_fee=:fee, fee_confirmed=:fee_confirmed, polygon_coordinates=:polygon, min_order_amount=:min_order, estimated_minutes_min=:eta_min, estimated_minutes_max=:eta_max, is_active=:active, updated_at=NOW() WHERE id=:id')->execute($params);
                audit_log($pdo, (int) $actor['id'], 'delivery_zones.updated', ['zone_id' => (int) $id, 'name' => $name]);
                json_response(['ok' => true, 'message' => 'Zona de cobertura actualizada.']);
            }
            json_response(['ok' => false, 'message' => 'Acción operativa no válida.'], 404);
        } catch (OperationsProblem $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], $e->httpStatus);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            error_log('[DESEGO operations] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo completar la operación. Revisa el registro del servidor.'], 500);
        }

        return response()->noContent();
    }

    protected function operations_order_summary_query(): string
    {
        return "SELECT o.id, o.order_number, o.customer_name, o.order_type, o.table_label, o.notes,
                   o.status, o.payment_status, o.total, o.created_at, o.updated_at,
                   TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) AS elapsed_minutes,
                   (SELECT GROUP_CONCAT(CONCAT(oi.quantity, ' × ', oi.product_name) ORDER BY oi.id SEPARATOR ', ')
                    FROM order_items oi WHERE oi.order_id = o.id) AS item_summary
            FROM orders o";
    }

    protected function operations_normalize_order(array $row): array
    {
        foreach (['id', 'elapsed_minutes'] as $field) {
            if (isset($row[$field])) {
                $row[$field] = (int) $row[$field];
            }
        }
        foreach (['total', 'delivery_fee'] as $field) {
            if (isset($row[$field])) {
                $row[$field] = (float) $row[$field];
            }
        }

        return $row;
    }
}
