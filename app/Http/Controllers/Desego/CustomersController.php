<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Exceptions\Desego\CustomerApiProblem;
use App\Http\Controllers\Controller;
use DateTime;
use Illuminate\Http\Exceptions\HttpResponseException;
use PDO;
use PDOException;
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

class CustomersController extends Controller
{
    public function __invoke(): Response
    {
        try {
            $pdo = desego_db();
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            if ($method === 'GET') {
                $user = require_any_permission(['customers.view', 'customers.manage', 'customer_addresses.manage']);
                $view = (string) (request()->query()['view'] ?? 'list');
                if ($view === 'detail') {
                    $id = filter_var(request()->query()['id'] ?? null, FILTER_VALIDATE_INT);
                    if (! $id || $id < 1) {
                        throw new CustomerApiProblem('Selecciona un cliente válido.', 422);
                    }
                    $stmt = $pdo->prepare(
                        'SELECT c.id, c.customer_code, c.full_name, c.document_type, c.document_number, c.phone, c.email, c.birth_date, c.notes, c.status,
                    c.created_at, c.updated_at,
                    COALESCE((SELECT la.points_balance FROM loyalty_accounts la WHERE la.customer_id = c.id), 0) AS points_balance,
                    COALESCE((SELECT la.lifetime_earned FROM loyalty_accounts la WHERE la.customer_id = c.id), 0) AS lifetime_points_earned,
                    COALESCE((SELECT la.lifetime_redeemed FROM loyalty_accounts la WHERE la.customer_id = c.id), 0) AS lifetime_points_redeemed,
                    (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS orders_count,
                    (SELECT COUNT(*) FROM reservations r WHERE r.customer_id = c.id) AS reservations_count
                 FROM customers c WHERE c.id = :id LIMIT 1'
                    );
                    $stmt->execute(['id' => $id]);
                    $customer = $stmt->fetch();
                    if (! $customer) {
                        throw new CustomerApiProblem('No se encontró el cliente.', 404);
                    }
                    $customer['id'] = (int) $customer['id'];
                    foreach (['points_balance', 'lifetime_points_earned', 'lifetime_points_redeemed'] as $key) {
                        $customer[$key] = (int) $customer[$key];
                    }
                    $customer['orders_count'] = (int) $customer['orders_count'];
                    $customer['reservations_count'] = (int) $customer['reservations_count'];
                    $customer['addresses'] = $this->customer_fetch_addresses($pdo, (int) $customer['id']);
                    json_response(['ok' => true, 'customer' => $customer, 'addresses' => $customer['addresses']]);
                }
                $query = trim((string) (request()->query()['q'] ?? ''));
                if (text_length($query) > 100) {
                    $query = substr($query, 0, 100);
                }
                $params = [];
                $where = '';
                if ($query !== '') {
                    $where = 'WHERE c.full_name LIKE :q1 OR c.phone LIKE :q2 OR c.email LIKE :q3 OR c.document_number LIKE :q4 OR c.customer_code LIKE :q5';
                    $like = '%'.$query.'%';
                    $params = ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like];
                }
                $stmt = $pdo->prepare(
                    "SELECT c.id, c.customer_code, c.full_name, c.document_type, c.document_number, c.phone, c.email, c.birth_date, c.notes, c.status,
                c.created_at, c.updated_at,
                COALESCE((SELECT la.points_balance FROM loyalty_accounts la WHERE la.customer_id = c.id), 0) AS points_balance,
                COALESCE((SELECT la.lifetime_earned FROM loyalty_accounts la WHERE la.customer_id = c.id), 0) AS lifetime_points_earned,
                COALESCE((SELECT la.lifetime_redeemed FROM loyalty_accounts la WHERE la.customer_id = c.id), 0) AS lifetime_points_redeemed,
                (SELECT COUNT(*) FROM customer_addresses a WHERE a.customer_id = c.id AND a.status = 'active') AS addresses_count,
                (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS orders_count,
                (SELECT COUNT(*) FROM reservations r WHERE r.customer_id = c.id) AS reservations_count
             FROM customers c $where ORDER BY c.status ASC, c.full_name ASC LIMIT 500"
                );
                $stmt->execute($params);
                $rows = $stmt->fetchAll();
                foreach ($rows as &$row) {
                    foreach (['id', 'addresses_count', 'orders_count', 'reservations_count', 'points_balance', 'lifetime_points_earned', 'lifetime_points_redeemed'] as $key) {
                        $row[$key] = (int) $row[$key];
                    }
                }
                unset($row);
                json_response(['ok' => true, 'customers' => $rows, 'can_manage' => in_array('customers.manage', $user['permissions'] ?? [], true), 'can_manage_addresses' => in_array('customer_addresses.manage', $user['permissions'] ?? [], true) || in_array('customers.manage', $user['permissions'] ?? [], true)]);
            }
            require_method('POST');
            $body = json_body();
            require_csrf($body);
            $action = (string) ($body['action'] ?? '');
            if ($action === 'customer.create' || $action === 'customer.update') {
                $user = require_permission('customers.manage');
                $id = null;
                if ($action === 'customer.update') {
                    $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                    if (! $id || $id < 1) {
                        throw new CustomerApiProblem('Cliente seleccionado no válido.');
                    }
                }
                $name = $this->customer_value($body, 'full_name', 120, 'nombre completo', true);
                if (text_length((string) $name) < 2) {
                    throw new CustomerApiProblem('El nombre debe tener al menos 2 caracteres.');
                }
                $documentType = (string) ($body['document_type'] ?? 'SIN_DOCUMENTO');
                $documentNumber = $this->customer_value($body, 'document_number', 30, 'documento');
                $this->customer_validate_document($documentType, $documentNumber);
                $phone = $this->customer_value($body, 'phone', 30, 'teléfono');
                $email = $this->customer_value($body, 'email', 190, 'correo');
                if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new CustomerApiProblem('Ingresa un correo válido.');
                }
                $birthDate = $this->customer_value($body, 'birth_date', 10, 'fecha de nacimiento');
                if ($birthDate !== null) {
                    $date = DateTime::createFromFormat('!Y-m-d', $birthDate);
                    if (! $date || $date->format('Y-m-d') !== $birthDate || $birthDate > gmdate('Y-m-d')) {
                        throw new CustomerApiProblem('La fecha de nacimiento no es válida.');
                    }
                }
                $notes = $this->customer_value($body, 'notes', 500, 'notas');
                try {
                    if ($id === null) {
                        $stmt = $pdo->prepare(
                            "INSERT INTO customers (customer_code, full_name, document_type, document_number, phone, email, birth_date, notes, status, created_by, updated_by, created_at, updated_at)
                     VALUES (NULL, :name, :document_type, :document_number, :phone, :email, :birth_date, :notes, 'active', :created_by, :updated_by, NOW(), NOW())"
                        );
                        $stmt->execute(['name' => $name, 'document_type' => $documentType, 'document_number' => $documentNumber, 'phone' => $phone, 'email' => $email, 'birth_date' => $birthDate, 'notes' => $notes, 'created_by' => (int) $user['id'], 'updated_by' => (int) $user['id']]);
                        $id = (int) $pdo->lastInsertId();
                        $code = 'CLI-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
                        $pdo->prepare('UPDATE customers SET customer_code = :code WHERE id = :id')->execute(['code' => $code, 'id' => $id]);
                        audit_log($pdo, (int) $user['id'], 'customers.created', ['customer_id' => $id, 'customer_code' => $code]);
                        json_response(['ok' => true, 'message' => 'Cliente registrado correctamente.', 'id' => $id, 'customer_code' => $code], 201);
                    }
                    $stmt = $pdo->prepare('UPDATE customers SET full_name=:name, document_type=:document_type, document_number=:document_number, phone=:phone, email=:email, birth_date=:birth_date, notes=:notes, updated_by=:updated_by, updated_at=NOW() WHERE id=:id');
                    $stmt->execute(['name' => $name, 'document_type' => $documentType, 'document_number' => $documentNumber, 'phone' => $phone, 'email' => $email, 'birth_date' => $birthDate, 'notes' => $notes, 'updated_by' => (int) $user['id'], 'id' => $id]);
                    if ($stmt->rowCount() === 0) {
                        $exists = $pdo->prepare('SELECT id FROM customers WHERE id = :id');
                        $exists->execute(['id' => $id]);
                        if (! $exists->fetchColumn()) {
                            throw new CustomerApiProblem('No se encontró el cliente.', 404);
                        }
                    }
                    audit_log($pdo, (int) $user['id'], 'customers.updated', ['customer_id' => $id]);
                    json_response(['ok' => true, 'message' => 'Datos del cliente actualizados.', 'id' => $id]);
                } catch (PDOException $e) {
                    if ((string) $e->getCode() === '23000') {
                        throw new CustomerApiProblem('El documento ya está registrado para otro cliente.', 409);
                    }
                    throw $e;
                }
            }
            if ($action === 'customer.status') {
                $user = require_permission('customers.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $status = (string) ($body['status'] ?? '');
                if (! $id || $id < 1 || ! in_array($status, ['active', 'inactive'], true)) {
                    throw new CustomerApiProblem('Cliente o estado no válido.');
                }
                $stmt = $pdo->prepare('UPDATE customers SET status=:status, updated_by=:user_id, updated_at=NOW() WHERE id=:id');
                $stmt->execute(['status' => $status, 'user_id' => (int) $user['id'], 'id' => $id]);
                if ($stmt->rowCount() === 0) {
                    $exists = $pdo->prepare('SELECT id FROM customers WHERE id=:id');
                    $exists->execute(['id' => $id]);
                    if (! $exists->fetchColumn()) {
                        throw new CustomerApiProblem('No se encontró el cliente.', 404);
                    }
                }
                audit_log($pdo, (int) $user['id'], 'customers.status_changed', ['customer_id' => $id, 'status' => $status]);
                json_response(['ok' => true, 'message' => $status === 'active' ? 'Cliente activado.' : 'Cliente desactivado.']);
            }
            if (str_starts_with($action, 'address.')) {
                $user = require_any_permission(['customer_addresses.manage', 'customers.manage']);
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $customerId = filter_var($body['customer_id'] ?? null, FILTER_VALIDATE_INT);
                if (! $customerId || $customerId < 1) {
                    throw new CustomerApiProblem('Selecciona un cliente válido.');
                }
                $customerStmt = $pdo->prepare('SELECT id, status FROM customers WHERE id=:id');
                $customerStmt->execute(['id' => $customerId]);
                $customer = $customerStmt->fetch();
                if (! $customer) {
                    throw new CustomerApiProblem('No se encontró el cliente.', 404);
                }
                if ($customer['status'] !== 'active' && $action !== 'address.status') {
                    throw new CustomerApiProblem('Activa al cliente antes de modificar sus direcciones.', 409);
                }
                if ($action === 'address.create' || $action === 'address.update') {
                    if ($action === 'address.update' && (! $id || $id < 1)) {
                        throw new CustomerApiProblem('Dirección seleccionada no válida.');
                    }
                    $label = $this->customer_value($body, 'label', 60, 'etiqueta', true);
                    $recipientName = $this->customer_value($body, 'recipient_name', 120, 'destinatario');
                    $phone = $this->customer_value($body, 'phone', 30, 'teléfono');
                    $addressLine = $this->customer_value($body, 'address_line', 255, 'dirección', true);
                    $reference = $this->customer_value($body, 'reference', 255, 'referencia');
                    $district = $this->customer_value($body, 'district', 100, 'distrito');
                    $province = $this->customer_value($body, 'province', 100, 'provincia');
                    $department = $this->customer_value($body, 'department', 100, 'departamento');
                    $postal = $this->customer_value($body, 'postal_code', 20, 'código postal');
                    $latRaw = $body['latitude'] ?? null;
                    $lngRaw = $body['longitude'] ?? null;
                    $lat = ($latRaw === '' || $latRaw === null) ? null : filter_var($latRaw, FILTER_VALIDATE_FLOAT);
                    $lng = ($lngRaw === '' || $lngRaw === null) ? null : filter_var($lngRaw, FILTER_VALIDATE_FLOAT);
                    if ($lat !== null && ($lat === false || $lat < -90 || $lat > 90)) {
                        throw new CustomerApiProblem('Latitud no válida.');
                    }
                    if ($lng !== null && ($lng === false || $lng < -180 || $lng > 180)) {
                        throw new CustomerApiProblem('Longitud no válida.');
                    }
                    $makeDefault = ! empty($body['is_default']);
                    if ($action === 'address.update') {
                        $own = $pdo->prepare('SELECT id FROM customer_addresses WHERE id=:id AND customer_id=:customer_id');
                        $own->execute(['id' => $id, 'customer_id' => $customerId]);
                        if (! $own->fetchColumn()) {
                            throw new CustomerApiProblem('La dirección no pertenece al cliente seleccionado.', 404);
                        }
                    }
                    $pdo->beginTransaction();
                    try {
                        if ($makeDefault) {
                            $pdo->prepare('UPDATE customer_addresses SET is_default=0 WHERE customer_id=:id')->execute(['id' => $customerId]);
                        }
                        if ($action === 'address.create') {
                            $stmt = $pdo->prepare("INSERT INTO customer_addresses (customer_id,label,recipient_name,phone,address_line,reference,district,province,department,postal_code,latitude,longitude,is_default,status,created_by,updated_by,created_at,updated_at) VALUES (:customer_id,:label,:recipient_name,:phone,:address_line,:reference,:district,:province,:department,:postal_code,:latitude,:longitude,:is_default,'active',:created_by,:updated_by,NOW(),NOW())");
                            $stmt->execute(['customer_id' => $customerId, 'label' => $label, 'recipient_name' => $recipientName, 'phone' => $phone, 'address_line' => $addressLine, 'reference' => $reference, 'district' => $district, 'province' => $province, 'department' => $department, 'postal_code' => $postal, 'latitude' => $lat, 'longitude' => $lng, 'is_default' => $makeDefault ? 1 : 0, 'created_by' => (int) $user['id'], 'updated_by' => (int) $user['id']]);
                            $id = (int) $pdo->lastInsertId();
                        } else {
                            $stmt = $pdo->prepare('UPDATE customer_addresses SET label=:label,recipient_name=:recipient_name,phone=:phone,address_line=:address_line,reference=:reference,district=:district,province=:province,department=:department,postal_code=:postal_code,latitude=:latitude,longitude=:longitude,is_default=:is_default,updated_by=:updated_by,updated_at=NOW() WHERE id=:id AND customer_id=:customer_id');
                            $stmt->execute(['label' => $label, 'recipient_name' => $recipientName, 'phone' => $phone, 'address_line' => $addressLine, 'reference' => $reference, 'district' => $district, 'province' => $province, 'department' => $department, 'postal_code' => $postal, 'latitude' => $lat, 'longitude' => $lng, 'is_default' => $makeDefault ? 1 : 0, 'updated_by' => (int) $user['id'], 'id' => $id, 'customer_id' => $customerId]);
                        }
                        if ($makeDefault) {
                            $pdo->prepare('UPDATE customer_addresses SET is_default=0 WHERE customer_id=:customer_id AND id<>:id')->execute(['customer_id' => $customerId, 'id' => $id]);
                        }
                        $pdo->commit();
                    } catch (Throwable $e) {
                        if ($e instanceof HttpResponseException) {
                            throw $e;
                        } if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        } throw $e;
                    }
                    audit_log($pdo, (int) $user['id'], $action === 'address.create' ? 'customers.address_created' : 'customers.address_updated', ['customer_id' => $customerId, 'address_id' => $id]);
                    json_response(['ok' => true, 'message' => $action === 'address.create' ? 'Dirección guardada.' : 'Dirección actualizada.', 'id' => $id]);
                }
                if ($action === 'address.status') {
                    if (! $id || $id < 1) {
                        throw new CustomerApiProblem('Dirección seleccionada no válida.');
                    }
                    $status = (string) ($body['status'] ?? '');
                    if (! in_array($status, ['active', 'inactive'], true)) {
                        throw new CustomerApiProblem('Estado de dirección no válido.');
                    }
                    $stmt = $pdo->prepare('UPDATE customer_addresses SET status=:status, is_default=CASE WHEN :status2="inactive" THEN 0 ELSE is_default END, updated_by=:user_id, updated_at=NOW() WHERE id=:id AND customer_id=:customer_id');
                    $stmt->execute(['status' => $status, 'status2' => $status, 'user_id' => (int) $user['id'], 'id' => $id, 'customer_id' => $customerId]);
                    if ($stmt->rowCount() === 0) {
                        throw new CustomerApiProblem('No se encontró la dirección para ese cliente.', 404);
                    }
                    audit_log($pdo, (int) $user['id'], 'customers.address_status', ['customer_id' => $customerId, 'address_id' => $id, 'status' => $status]);
                    json_response(['ok' => true, 'message' => $status === 'active' ? 'Dirección activada.' : 'Dirección desactivada.']);
                }
                if ($action === 'address.default') {
                    if (! $id || $id < 1) {
                        throw new CustomerApiProblem('Dirección seleccionada no válida.');
                    }
                    $pdo->beginTransaction();
                    try {
                        $stmt = $pdo->prepare("SELECT id FROM customer_addresses WHERE id=:id AND customer_id=:customer_id AND status='active' FOR UPDATE");
                        $stmt->execute(['id' => $id, 'customer_id' => $customerId]);
                        if (! $stmt->fetchColumn()) {
                            throw new CustomerApiProblem('Activa una dirección del cliente para establecerla como predeterminada.', 404);
                        }
                        $pdo->prepare('UPDATE customer_addresses SET is_default=0 WHERE customer_id=:id')->execute(['id' => $customerId]);
                        $pdo->prepare('UPDATE customer_addresses SET is_default=1, updated_by=:user_id, updated_at=NOW() WHERE id=:id AND customer_id=:customer_id')->execute(['user_id' => (int) $user['id'], 'id' => $id, 'customer_id' => $customerId]);
                        $pdo->commit();
                    } catch (Throwable $e) {
                        if ($e instanceof HttpResponseException) {
                            throw $e;
                        } if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        } throw $e;
                    }
                    audit_log($pdo, (int) $user['id'], 'customers.address_default', ['customer_id' => $customerId, 'address_id' => $id]);
                    json_response(['ok' => true, 'message' => 'Dirección predeterminada actualizada.']);
                }
                throw new CustomerApiProblem('Acción de direcciones no reconocida.', 404);
            }
            throw new CustomerApiProblem('Acción de clientes no reconocida.', 404);
        } catch (CustomerApiProblem $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], $e->httpStatus);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            error_log('[DESEGO customers] '.$e->getMessage());
            if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
                json_response(['ok' => false, 'message' => 'El dato entra en conflicto con otro registro. Revisa el documento y los vínculos.'], 409);
            }
            json_response(['ok' => false, 'message' => 'No se pudo completar la operación. Revisa la configuración y los registros de PHP.'], 500);
        }

        return response()->noContent();
    }

    protected function customer_value(array $body, string $key, int $max, string $label, bool $required = false): ?string
    {
        $value = trim((string) ($body[$key] ?? ''));
        if ($required && $value === '') {
            throw new CustomerApiProblem('El campo '.$label.' es obligatorio.');
        }
        if (text_length($value) > $max) {
            throw new CustomerApiProblem('El campo '.$label.' supera el tamaño permitido.');
        }

        return $value === '' ? null : $value;
    }

    protected function customer_validate_document(string $type, ?string $number): void
    {
        $types = ['DNI', 'RUC', 'CE', 'PASAPORTE', 'OTRO', 'SIN_DOCUMENTO'];
        if (! in_array($type, $types, true)) {
            throw new CustomerApiProblem('Tipo de documento no válido.');
        }
        if ($type === 'SIN_DOCUMENTO') {
            if ($number !== null) {
                throw new CustomerApiProblem('No ingreses número si seleccionaste Sin documento.');
            }

            return;
        }
        if ($number === null) {
            throw new CustomerApiProblem('Ingresa el número de documento seleccionado.');
        }
        if (! preg_match('/^[A-Za-z0-9\-]{4,30}$/', $number)) {
            throw new CustomerApiProblem('El número de documento debe tener entre 4 y 30 caracteres alfanuméricos.');
        }
        if ($type === 'DNI' && ! preg_match('/^\d{8}$/', $number)) {
            throw new CustomerApiProblem('El DNI debe contener 8 dígitos.');
        }
        if ($type === 'RUC' && ! preg_match('/^\d{11}$/', $number)) {
            throw new CustomerApiProblem('El RUC debe contener 11 dígitos.');
        }
    }

    protected function customer_fetch_addresses(PDO $pdo, int $customerId): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, customer_id, label, recipient_name, phone, address_line, reference, district, province, department, postal_code,
                latitude, longitude, is_default, status, created_at, updated_at
         FROM customer_addresses WHERE customer_id = :id ORDER BY is_default DESC, status ASC, label ASC, id DESC'
        );
        $stmt->execute(['id' => $customerId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            foreach (['id', 'customer_id', 'is_default'] as $key) {
                $row[$key] = (int) $row[$key];
            }
            foreach (['latitude', 'longitude'] as $key) {
                $row[$key] = $row[$key] === null ? null : (float) $row[$key];
            }
        }
        unset($row);

        return $rows;
    }
}
