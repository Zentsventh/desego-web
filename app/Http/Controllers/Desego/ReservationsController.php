<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Exceptions\Desego\ReservationApiProblem;
use App\Http\Controllers\Controller;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Exceptions\HttpResponseException;
use PDO;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function App\Support\Desego\audit_log;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\desego_header;
use function App\Support\Desego\json_body;
use function App\Support\Desego\json_response;
use function App\Support\Desego\require_any_permission;
use function App\Support\Desego\require_csrf;
use function App\Support\Desego\require_permission;
use function App\Support\Desego\text_length;

class ReservationsController extends Controller
{
    public function __invoke(): Response
    {
        try {
            $pdo = desego_db();
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            if ($method === 'GET') {
                $user = require_any_permission(['reservations.view', 'tables.view', 'reservations.manage', 'tables.manage']);
                $permissions = $user['permissions'] ?? [];
                $canReservations = in_array('reservations.view', $permissions, true) || in_array('reservations.manage', $permissions, true);
                $canTables = in_array('tables.view', $permissions, true) || in_array('tables.manage', $permissions, true) || $canReservations;
                json_response([
                    'ok' => true,
                    'tables' => $canTables ? $this->reservation_fetch_tables($pdo) : [],
                    'reservations' => $canReservations ? $this->reservation_fetch_list($pdo) : [],
                    'server_time' => gmdate('c'),
                ]);
            }
            if ($method !== 'POST') {
                desego_header('Allow: GET, POST');
                json_response(['ok' => false, 'message' => 'Método no permitido.'], 405);
            }
            $body = json_body();
            require_csrf($body);
            $action = (string) ($body['action'] ?? '');
            if ($action === 'reservation.create' || $action === 'reservation.update') {
                $user = require_permission('reservations.manage');
                $id = null;
                if ($action === 'reservation.update') {
                    $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                    if (! $id || $id < 1) {
                        throw new ReservationApiProblem('La reserva seleccionada no es válida.');
                    }
                }
                $customer = trim((string) ($body['customer_name'] ?? ''));
                $customerIdRaw = $body['customer_id'] ?? null;
                $customerId = ($customerIdRaw === null || $customerIdRaw === '' || (int) $customerIdRaw === 0) ? null : filter_var($customerIdRaw, FILTER_VALIDATE_INT);
                if ($customerId !== null && (! $customerId || $customerId < 1)) {
                    throw new ReservationApiProblem('Cliente seleccionado no válido.');
                }
                if ($customerId !== null && ! array_intersect(['customers.view', 'customers.manage', 'customer_addresses.manage'], $user['permissions'] ?? [])) {
                    throw new ReservationApiProblem('Tu rol no tiene permiso para asociar clientes a reservas.', 403);
                }
                $phone = trim((string) ($body['customer_phone'] ?? ''));
                $email = trim((string) ($body['customer_email'] ?? ''));
                $notes = trim((string) ($body['notes'] ?? ''));
                $initialStatus = (string) ($body['initial_status'] ?? 'Confirmada');
                if (! in_array($initialStatus, ['Pendiente', 'Confirmada'], true)) {
                    $initialStatus = 'Confirmada';
                }
                $tableId = filter_var($body['table_id'] ?? null, FILTER_VALIDATE_INT);
                $guests = filter_var($body['guest_count'] ?? null, FILTER_VALIDATE_INT);
                $duration = filter_var($body['duration_minutes'] ?? 120, FILTER_VALIDATE_INT);
                $reservationAt = $this->reservation_input_datetime($body['reservation_at'] ?? null);
                if (text_length($customer) < 2 || text_length($customer) > 100) {
                    throw new ReservationApiProblem('El nombre debe tener entre 2 y 100 caracteres.');
                }
                if (! $tableId || $tableId < 1) {
                    throw new ReservationApiProblem('Selecciona una mesa válida.');
                }
                if (! $guests || $guests < 1 || $guests > 30) {
                    throw new ReservationApiProblem('El número de personas debe estar entre 1 y 30.');
                }
                if (! $duration || $duration < 30 || $duration > 360) {
                    throw new ReservationApiProblem('La duración debe estar entre 30 y 360 minutos.');
                }
                if ($phone !== '' && text_length($phone) > 30) {
                    throw new ReservationApiProblem('El teléfono supera el tamaño permitido.');
                }
                if ($email !== '' && (! filter_var($email, FILTER_VALIDATE_EMAIL) || text_length($email) > 190)) {
                    throw new ReservationApiProblem('El correo del cliente no es válido.');
                }
                if (text_length($notes) > 500) {
                    throw new ReservationApiProblem('Las notas no pueden superar 500 caracteres.');
                }
                $pdo->beginTransaction();
                try {
                    if ($customerId !== null) {
                        $customerStmt = $pdo->prepare('SELECT id, full_name, phone, email, status FROM customers WHERE id=:id FOR UPDATE');
                        $customerStmt->execute(['id' => $customerId]);
                        $customerRecord = $customerStmt->fetch();
                        if (! $customerRecord || $customerRecord['status'] !== 'active') {
                            throw new ReservationApiProblem('El cliente seleccionado no existe o está inactivo.', 409);
                        }
                        $customer = (string) $customerRecord['full_name'];
                        if ($phone === '') {
                            $phone = (string) ($customerRecord['phone'] ?? '');
                        }
                        if ($email === '') {
                            $email = (string) ($customerRecord['email'] ?? '');
                        }
                    }
                    $originalTableId = null;
                    if ($id !== null) {
                        $existingStmt = $pdo->prepare('SELECT id, table_id, status FROM reservations WHERE id = :id FOR UPDATE');
                        $existingStmt->execute(['id' => $id]);
                        $existing = $existingStmt->fetch();
                        if (! $existing) {
                            throw new ReservationApiProblem('La reserva no existe.', 404);
                        }
                        if (! in_array($existing['status'], ['Pendiente', 'Confirmada'], true)) {
                            throw new ReservationApiProblem('Solo se pueden editar reservas pendientes o confirmadas.', 409);
                        }
                        $originalTableId = (int) $existing['table_id'];
                    }
                    $table = $this->reservation_validate_table($pdo, (int) $tableId, (int) $guests, $id, true, $originalTableId);
                    $this->reservation_assert_no_overlap($pdo, (int) $tableId, $reservationAt, (int) $duration, $id);
                    if ($id === null) {
                        $stmt = $pdo->prepare(
                            'INSERT INTO reservations (reservation_code, customer_name, customer_id, customer_phone, customer_email, table_id, guest_count, reservation_at, duration_minutes, status, notes, created_by, updated_by, created_at, updated_at)
                     VALUES (NULL, :customer_name, :customer_id, :customer_phone, :customer_email, :table_id, :guest_count, :reservation_at, :duration_minutes, :status, :notes, :created_by, :updated_by, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
                        );
                        $stmt->execute([
                            'customer_name' => $customer, 'customer_id' => $customerId, 'customer_phone' => $phone !== '' ? $phone : null,
                            'customer_email' => $email !== '' ? $email : null, 'table_id' => (int) $tableId,
                            'guest_count' => (int) $guests, 'reservation_at' => $reservationAt,
                            'duration_minutes' => (int) $duration, 'status' => $initialStatus, 'notes' => $notes !== '' ? $notes : null,
                            'created_by' => (int) $user['id'], 'updated_by' => (int) $user['id'],
                        ]);
                        $newId = (int) $pdo->lastInsertId();
                        $code = 'RES-'.str_pad((string) $newId, 6, '0', STR_PAD_LEFT);
                        $pdo->prepare('UPDATE reservations SET reservation_code = :code WHERE id = :id')->execute(['code' => $code, 'id' => $newId]);
                        audit_log($pdo, (int) $user['id'], 'reservation.create', ['id' => $newId, 'code' => $code, 'table' => $table['label'], 'at' => $reservationAt]);
                        $pdo->commit();
                        json_response(['ok' => true, 'message' => 'Reserva creada correctamente.', 'id' => $newId, 'reservation_code' => $code]);
                    }
                    $stmt = $pdo->prepare(
                        'UPDATE reservations SET customer_name = :customer_name, customer_id = :customer_id, customer_phone = :customer_phone,
                 customer_email = :customer_email, table_id = :table_id, guest_count = :guest_count,
                 reservation_at = :reservation_at, duration_minutes = :duration_minutes, notes = :notes,
                 updated_by = :updated_by, updated_at = UTC_TIMESTAMP() WHERE id = :id'
                    );
                    $stmt->execute([
                        'customer_name' => $customer, 'customer_id' => $customerId, 'customer_phone' => $phone !== '' ? $phone : null,
                        'customer_email' => $email !== '' ? $email : null, 'table_id' => (int) $tableId,
                        'guest_count' => (int) $guests, 'reservation_at' => $reservationAt,
                        'duration_minutes' => (int) $duration, 'notes' => $notes !== '' ? $notes : null,
                        'updated_by' => (int) $user['id'], 'id' => $id,
                    ]);
                    audit_log($pdo, (int) $user['id'], 'reservation.update', ['id' => $id, 'table' => $table['label'], 'at' => $reservationAt]);
                    $pdo->commit();
                    json_response(['ok' => true, 'message' => 'Reserva actualizada correctamente.']);
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
            if ($action === 'reservation.status') {
                $user = require_permission('reservations.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $status = (string) ($body['status'] ?? '');
                if (! $id || $id < 1) {
                    throw new ReservationApiProblem('La reserva seleccionada no es válida.');
                }
                $allowed = ['Confirmada', 'Sentada', 'Completada', 'Cancelada', 'No asistió'];
                if (! in_array($status, $allowed, true)) {
                    throw new ReservationApiProblem('Estado de reserva no permitido.');
                }
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('SELECT id, reservation_code, status, reservation_at FROM reservations WHERE id = :id FOR UPDATE');
                    $stmt->execute(['id' => $id]);
                    $reservation = $stmt->fetch();
                    if (! $reservation) {
                        throw new ReservationApiProblem('La reserva no existe.', 404);
                    }
                    $transitions = [
                        'Pendiente' => ['Confirmada', 'Cancelada'],
                        'Confirmada' => ['Sentada', 'Cancelada', 'No asistió'],
                        'Sentada' => ['Completada'],
                    ];
                    if (! in_array($status, $transitions[$reservation['status']] ?? [], true)) {
                        throw new ReservationApiProblem('No se puede cambiar de '.$reservation['status'].' a '.$status.'.', 409);
                    }
                    if ($status === 'No asistió' && (string) $reservation['reservation_at'] > gmdate('Y-m-d H:i:s')) {
                        throw new ReservationApiProblem('No se puede marcar como no asistió antes de la hora reservada.', 409);
                    }
                    $update = $pdo->prepare('UPDATE reservations SET status = :status, updated_by = :user_id, updated_at = UTC_TIMESTAMP() WHERE id = :id');
                    $update->execute(['status' => $status, 'user_id' => (int) $user['id'], 'id' => $id]);
                    audit_log($pdo, (int) $user['id'], 'reservation.status', ['id' => (int) $id, 'from' => $reservation['status'], 'to' => $status]);
                    $pdo->commit();
                    json_response(['ok' => true, 'message' => 'Estado de la reserva actualizado.']);
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
            if ($action === 'table.create') {
                $user = require_permission('tables.manage');
                $number = filter_var($body['table_number'] ?? null, FILTER_VALIDATE_INT);
                $label = trim((string) ($body['label'] ?? ''));
                $zone = trim((string) ($body['zone'] ?? 'Salón principal'));
                $capacity = filter_var($body['capacity'] ?? null, FILTER_VALIDATE_INT);
                if (! $number || $number < 1 || $number > 999) {
                    throw new ReservationApiProblem('El número de mesa debe estar entre 1 y 999.');
                }
                if ($label === '') {
                    $label = 'Mesa '.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
                }
                if (text_length($label) > 40 || text_length($zone) < 2 || text_length($zone) > 80) {
                    throw new ReservationApiProblem('Revisa el nombre de la mesa y la zona.');
                }
                if (! $capacity || $capacity < 1 || $capacity > 30) {
                    throw new ReservationApiProblem('La capacidad debe estar entre 1 y 30 personas.');
                }
                $stmt = $pdo->prepare("INSERT INTO restaurant_tables (table_number, label, zone, capacity, operational_status, is_active, created_by, updated_by) VALUES (:number, :label, :zone, :capacity, 'available', 1, :created_by, :updated_by)");
                try {
                    $stmt->execute(['number' => $number, 'label' => $label, 'zone' => $zone, 'capacity' => $capacity, 'created_by' => (int) $user['id'], 'updated_by' => (int) $user['id']]);
                } catch (PDOException $e) {
                    if ((string) $e->getCode() === '23000') {
                        throw new ReservationApiProblem('Ya existe una mesa con ese número o nombre.', 409);
                    }
                    throw $e;
                }
                $id = (int) $pdo->lastInsertId();
                audit_log($pdo, (int) $user['id'], 'table.create', ['id' => $id, 'number' => $number, 'label' => $label]);
                json_response(['ok' => true, 'message' => 'Mesa creada correctamente.', 'id' => $id]);
            }
            if ($action === 'table.update') {
                $user = require_permission('tables.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $number = filter_var($body['table_number'] ?? null, FILTER_VALIDATE_INT);
                $label = trim((string) ($body['label'] ?? ''));
                $zone = trim((string) ($body['zone'] ?? 'Salón principal'));
                $capacity = filter_var($body['capacity'] ?? null, FILTER_VALIDATE_INT);
                $active = filter_var($body['is_active'] ?? null, FILTER_VALIDATE_INT);
                if (! $id || ! $number || $number < 1 || $number > 999 || ! $capacity || $capacity < 1 || $capacity > 30 || ! in_array($active, [0, 1], true)) {
                    throw new ReservationApiProblem('Los datos de la mesa no son válidos.');
                }
                if ($label === '') {
                    $label = 'Mesa '.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
                }
                if (text_length($label) > 40 || text_length($zone) < 2 || text_length($zone) > 80) {
                    throw new ReservationApiProblem('Revisa el nombre de la mesa y la zona.');
                }
                $capacityCheck = $pdo->prepare("SELECT COALESCE(MAX(guest_count), 0) FROM reservations WHERE table_id = :id AND status IN ('Pendiente','Confirmada','Sentada') AND reservation_at >= UTC_TIMESTAMP()");
                $capacityCheck->execute(['id' => $id]);
                if ((int) $capacityCheck->fetchColumn() > (int) $capacity) {
                    throw new ReservationApiProblem('La capacidad no puede ser menor que una reserva activa asociada a esta mesa.', 409);
                }
                if ($active === 0) {
                    $check = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE table_id = :id AND status IN ('Pendiente','Confirmada','Sentada') AND reservation_at >= UTC_TIMESTAMP()");
                    $check->execute(['id' => $id]);
                    if ((int) $check->fetchColumn() > 0) {
                        throw new ReservationApiProblem('No se puede desactivar una mesa que tiene reservas activas futuras.', 409);
                    }
                }
                $stmt = $pdo->prepare('UPDATE restaurant_tables SET table_number = :number, label = :label, zone = :zone, capacity = :capacity, is_active = :active, updated_by = :user_id WHERE id = :id');
                try {
                    $stmt->execute(['number' => $number, 'label' => $label, 'zone' => $zone, 'capacity' => $capacity, 'active' => $active, 'user_id' => (int) $user['id'], 'id' => $id]);
                } catch (PDOException $e) {
                    if ((string) $e->getCode() === '23000') {
                        throw new ReservationApiProblem('Ya existe otra mesa con ese número o nombre.', 409);
                    }
                    throw $e;
                }
                if ($stmt->rowCount() < 1) {
                    $exists = $pdo->prepare('SELECT id FROM restaurant_tables WHERE id = :id');
                    $exists->execute(['id' => $id]);
                    if (! $exists->fetch()) {
                        throw new ReservationApiProblem('La mesa no existe.', 404);
                    }
                }
                audit_log($pdo, (int) $user['id'], 'table.update', ['id' => (int) $id, 'active' => $active]);
                json_response(['ok' => true, 'message' => 'Mesa actualizada correctamente.']);
            }
            if ($action === 'table.status') {
                $user = require_permission('tables.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $status = (string) ($body['status'] ?? '');
                if (! $id || $id < 1 || ! in_array($status, ['available', 'occupied', 'maintenance'], true)) {
                    throw new ReservationApiProblem('El estado de mesa no es válido.');
                }
                if ($status === 'maintenance') {
                    $check = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE table_id = :id AND status IN ('Pendiente','Confirmada','Sentada') AND reservation_at < DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY) AND DATE_ADD(reservation_at, INTERVAL duration_minutes MINUTE) > UTC_TIMESTAMP()");
                    $check->execute(['id' => $id]);
                    if ((int) $check->fetchColumn() > 0) {
                        throw new ReservationApiProblem('No se puede poner en mantenimiento una mesa con reservas activas durante las próximas 24 horas.', 409);
                    }
                }
                $stmt = $pdo->prepare('UPDATE restaurant_tables SET operational_status = :status, updated_by = :user_id WHERE id = :id AND is_active = 1');
                $stmt->execute(['status' => $status, 'user_id' => (int) $user['id'], 'id' => $id]);
                if ($stmt->rowCount() < 1) {
                    throw new ReservationApiProblem('Mesa no encontrada o desactivada.', 404);
                }
                audit_log($pdo, (int) $user['id'], 'table.status', ['id' => (int) $id, 'status' => $status]);
                json_response(['ok' => true, 'message' => 'Estado de mesa actualizado.']);
            }
            throw new ReservationApiProblem('Acción no reconocida.', 400);
        } catch (ReservationApiProblem $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], $e->httpStatus);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            error_log('[DESEGO reservations API] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo procesar la operación de reservas. Verifica la migración del Paso 5 y la configuración de MySQL.'], 500);
        }

        return response()->noContent();
    }

    protected function reservation_input_datetime(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new ReservationApiProblem('Selecciona la fecha y hora de la reserva.');
        }
        try {
            $date = new DateTimeImmutable(trim($value));
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            throw new ReservationApiProblem('La fecha y hora no son válidas.');
        }
        $utc = $date->setTimezone(new DateTimeZone('UTC'));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if ($utc < $now->modify('-5 minutes')) {
            throw new ReservationApiProblem('La reserva no puede registrarse en una fecha pasada.');
        }

        return $utc->format('Y-m-d H:i:s');
    }

    protected function reservation_validate_table(PDO $pdo, int $tableId, int $guests, ?int $reservationId = null, bool $lock = false, ?int $originalTableId = null): array
    {
        $sql = 'SELECT id, table_number, label, zone, capacity, operational_status, is_active FROM restaurant_tables WHERE id = :id';
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $tableId]);
        $table = $stmt->fetch();
        if (! $table || (int) $table['is_active'] !== 1) {
            throw new ReservationApiProblem('La mesa elegida no existe o está desactivada.', 404);
        }
        if ($table['operational_status'] === 'maintenance') {
            throw new ReservationApiProblem('La mesa está en mantenimiento y no admite reservas.', 409);
        }
        if ($table['operational_status'] === 'occupied' && ($reservationId === null || $originalTableId !== $tableId)) {
            throw new ReservationApiProblem('La mesa está marcada como ocupada. Cámbiala a disponible antes de asignarla a una nueva reserva.', 409);
        }
        if ($guests < 1 || $guests > (int) $table['capacity']) {
            throw new ReservationApiProblem('El número de personas supera la capacidad de la mesa ('.(int) $table['capacity'].').');
        }

        return $table;
    }

    protected function reservation_assert_no_overlap(PDO $pdo, int $tableId, string $startAt, int $duration, ?int $exceptId = null): void
    {
        $start = new DateTimeImmutable($startAt, new DateTimeZone('UTC'));
        $endAt = $start->modify('+'.$duration.' minutes')->format('Y-m-d H:i:s');
        $sql = "SELECT id, reservation_code FROM reservations
            WHERE table_id = :table_id
              AND status IN ('Pendiente','Confirmada','Sentada')
              AND reservation_at < :end_at
              AND DATE_ADD(reservation_at, INTERVAL duration_minutes MINUTE) > :start_at";
        $params = ['table_id' => $tableId, 'end_at' => $endAt, 'start_at' => $startAt];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $params['except_id'] = $exceptId;
        }
        $sql .= ' LIMIT 1 FOR UPDATE';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $conflict = $stmt->fetch();
        if ($conflict) {
            $code = (string) ($conflict['reservation_code'] ?: ('#'.$conflict['id']));
            throw new ReservationApiProblem('La mesa ya tiene otra reserva que se cruza con ese horario ('.$code.'). Elige otra hora o mesa.', 409);
        }
    }

    protected function reservation_fetch_tables(PDO $pdo): array
    {
        $sql = "SELECT t.id, t.table_number, t.label, t.zone, t.capacity, t.operational_status,
                   t.is_active, t.created_at, t.updated_at,
                   CASE
                     WHEN t.operational_status = 'maintenance' THEN 'maintenance'
                     WHEN t.operational_status = 'occupied' THEN 'occupied'
                     WHEN EXISTS (
                       SELECT 1 FROM reservations r
                       WHERE r.table_id = t.id AND r.status = 'Sentada'
                         AND DATE_ADD(r.reservation_at, INTERVAL r.duration_minutes MINUTE) > UTC_TIMESTAMP()
                     ) THEN 'occupied'
                     WHEN EXISTS (
                       SELECT 1 FROM reservations r
                       WHERE r.table_id = t.id AND r.status IN ('Pendiente','Confirmada')
                         AND r.reservation_at <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)
                         AND DATE_ADD(r.reservation_at, INTERVAL r.duration_minutes MINUTE) > UTC_TIMESTAMP()
                     ) THEN 'reserved'
                     ELSE 'available'
                   END AS effective_status
            FROM restaurant_tables t
            ORDER BY t.is_active DESC, t.zone, t.table_number";
        $rows = $pdo->query($sql)->fetchAll();
        foreach ($rows as &$row) {
            foreach (['id', 'table_number', 'capacity', 'is_active'] as $key) {
                $row[$key] = (int) $row[$key];
            }
        }
        unset($row);

        return $rows;
    }

    protected function reservation_fetch_list(PDO $pdo): array
    {
        $rows = $pdo->query(
            "SELECT r.id, r.reservation_code, r.customer_name, r.customer_id, r.customer_phone, r.customer_email,
                r.table_id, r.guest_count, r.reservation_at, r.duration_minutes, r.status, r.notes,
                r.created_at, r.updated_at, t.label AS table_label, t.zone, t.capacity AS table_capacity,
                u.name AS created_by_name
         FROM reservations r
         INNER JOIN restaurant_tables t ON t.id = r.table_id
         INNER JOIN users u ON u.id = r.created_by
         WHERE r.reservation_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)
            OR r.status IN ('Pendiente','Confirmada','Sentada')
         ORDER BY r.reservation_at ASC, r.id ASC LIMIT 250"
        )->fetchAll();
        foreach ($rows as &$row) {
            foreach (['id', 'table_id', 'guest_count', 'duration_minutes', 'table_capacity'] as $key) {
                $row[$key] = (int) $row[$key];
            }
            // Return a UTC timestamp with an explicit suffix so browser date parsing is unambiguous.
            $row['reservation_at'] = str_replace(' ', 'T', (string) $row['reservation_at']).'Z';
        }
        unset($row);

        return $rows;
    }
}
