<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Exceptions\Desego\CashApiProblem;
use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use PDO;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function App\Support\Desego\audit_log;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\json_body;
use function App\Support\Desego\json_response;
use function App\Support\Desego\require_csrf;
use function App\Support\Desego\require_method;
use function App\Support\Desego\require_permission;
use function App\Support\Desego\text_length;

class CashController extends Controller
{
    public function __invoke(): Response
    {
        try {
            $pdo = desego_db();
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            if ($method === 'GET') {
                require_permission('cash.manage');
                $session = $this->cash_get_open_session($pdo);
                $totals = $session ? $this->cash_expected($pdo, $session) : null;
                $history = $pdo->query('SELECT cs.id, cs.opening_amount, cs.expected_closing, cs.counted_closing, cs.difference_amount, cs.status, cs.opened_at, cs.closed_at, u.name AS opened_by_name FROM cash_sessions cs INNER JOIN users u ON u.id = cs.opened_by ORDER BY cs.id DESC LIMIT 8')->fetchAll();
                foreach ($history as &$row) {
                    $row['id'] = (int) $row['id'];
                    foreach (['opening_amount', 'expected_closing', 'counted_closing', 'difference_amount'] as $key) {
                        $row[$key] = $row[$key] === null ? null : (float) $row[$key];
                    }
                }
                unset($row);
                $movements = [];
                if ($session) {
                    $stmt = $pdo->prepare('SELECT cm.id, cm.movement_type, cm.amount, cm.reference, cm.notes, cm.created_at, u.name AS user_name FROM cash_movements cm INNER JOIN users u ON u.id = cm.user_id WHERE cm.session_id = :id ORDER BY cm.id DESC LIMIT 15');
                    $stmt->execute(['id' => $session['id']]);
                    $movements = $stmt->fetchAll();
                    foreach ($movements as &$row) {
                        $row['id'] = (int) $row['id'];
                        $row['amount'] = (float) $row['amount'];
                    }
                    unset($row);
                }
                json_response(['ok' => true, 'session' => $session, 'totals' => $totals, 'history' => $history, 'movements' => $movements]);
            }
            require_method('POST');
            $body = json_body();
            require_csrf($body);
            $actor = require_permission('cash.manage');
            $action = (string) ($body['action'] ?? '');
            if ($action === 'open') {
                $amount = filter_var($body['opening_amount'] ?? null, FILTER_VALIDATE_FLOAT);
                $notes = trim((string) ($body['notes'] ?? ''));
                if (! is_numeric($amount) || (float) $amount < 0 || (float) $amount > 100000000 || text_length($notes) > 500) {
                    throw new CashApiProblem('Revisa el monto inicial y las notas.', 422);
                }
                $pdo->beginTransaction();
                try {
                    $pdo->query('SELECT id FROM cash_register_mutex WHERE id = 1 FOR UPDATE')->fetch();
                    if ($this->cash_get_open_session($pdo, true)) {
                        throw new CashApiProblem('Ya hay una caja abierta. Cierra la sesión actual antes de abrir otra.', 409);
                    }
                    $stmt = $pdo->prepare("INSERT INTO cash_sessions (opened_by, opening_amount, status, opening_notes, opened_at) VALUES (:user_id, :amount, 'open', :notes, NOW())");
                    $stmt->execute(['user_id' => (int) $actor['id'], 'amount' => number_format((float) $amount, 2, '.', ''), 'notes' => $notes !== '' ? $notes : null]);
                    $id = (int) $pdo->lastInsertId();
                    $pdo->commit();
                    audit_log($pdo, (int) $actor['id'], 'cash.opened', ['session_id' => $id, 'opening_amount' => (float) $amount]);
                    json_response(['ok' => true, 'message' => 'Caja abierta correctamente.', 'session_id' => $id]);
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
            if ($action === 'close') {
                $counted = filter_var($body['counted_closing'] ?? null, FILTER_VALIDATE_FLOAT);
                $notes = trim((string) ($body['notes'] ?? ''));
                if (! is_numeric($counted) || (float) $counted < 0 || (float) $counted > 100000000 || text_length($notes) > 500) {
                    throw new CashApiProblem('Ingresa el efectivo contado y unas notas válidas.', 422);
                }
                $pdo->beginTransaction();
                try {
                    $pdo->query('SELECT id FROM cash_register_mutex WHERE id = 1 FOR UPDATE')->fetch();
                    $session = $this->cash_get_open_session($pdo, true);
                    if (! $session) {
                        throw new CashApiProblem('No hay una caja abierta para cerrar.', 409);
                    }
                    $totals = $this->cash_expected($pdo, $session);
                    $expected = (float) $totals['expected_closing'];
                    $difference = round((float) $counted - $expected, 2);
                    $stmt = $pdo->prepare("UPDATE cash_sessions SET closed_by = :user_id, expected_closing = :expected, counted_closing = :counted, difference_amount = :difference, status = 'closed', closing_notes = :notes, closed_at = NOW() WHERE id = :id");
                    $stmt->execute(['user_id' => (int) $actor['id'], 'expected' => number_format($expected, 2, '.', ''), 'counted' => number_format((float) $counted, 2, '.', ''), 'difference' => number_format($difference, 2, '.', ''), 'notes' => $notes !== '' ? $notes : null, 'id' => $session['id']]);
                    $pdo->commit();
                    audit_log($pdo, (int) $actor['id'], 'cash.closed', ['session_id' => (int) $session['id'], 'expected' => $expected, 'counted' => (float) $counted, 'difference' => $difference]);
                    json_response(['ok' => true, 'message' => 'Caja cerrada. Diferencia: S/ '.number_format($difference, 2), 'expected_closing' => $expected, 'difference' => $difference]);
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
            if ($action === 'movement') {
                $type = (string) ($body['movement_type'] ?? '');
                $amount = filter_var($body['amount'] ?? null, FILTER_VALIDATE_FLOAT);
                $notes = trim((string) ($body['notes'] ?? ''));
                $reference = trim((string) ($body['reference'] ?? ''));
                if (! in_array($type, ['income', 'expense'], true) || ! is_numeric($amount) || (float) $amount <= 0 || (float) $amount > 100000000 || $notes === '' || text_length($notes) > 500 || text_length($reference) > 120) {
                    throw new CashApiProblem('Completa tipo, monto y descripción del movimiento.', 422);
                }
                $pdo->beginTransaction();
                try {
                    $pdo->query('SELECT id FROM cash_register_mutex WHERE id = 1 FOR UPDATE')->fetch();
                    $session = $this->cash_get_open_session($pdo, true);
                    if (! $session) {
                        throw new CashApiProblem('Abre caja antes de registrar un ingreso o egreso.', 409);
                    }
                    $stmt = $pdo->prepare('INSERT INTO cash_movements (session_id, user_id, movement_type, amount, reference, notes, created_at) VALUES (:session_id, :user_id, :type, :amount, :reference, :notes, NOW())');
                    $stmt->execute(['session_id' => $session['id'], 'user_id' => (int) $actor['id'], 'type' => $type, 'amount' => number_format((float) $amount, 2, '.', ''), 'reference' => $reference !== '' ? $reference : null, 'notes' => $notes]);
                    $pdo->commit();
                    audit_log($pdo, (int) $actor['id'], 'cash.movement_registered', ['session_id' => (int) $session['id'], 'movement_type' => $type, 'amount' => (float) $amount]);
                    json_response(['ok' => true, 'message' => $type === 'income' ? 'Ingreso de caja registrado.' : 'Egreso de caja registrado.']);
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
            throw new CashApiProblem('Acción de caja no válida.', 404);
        } catch (CashApiProblem $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], $e->httpStatus);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            error_log('[DESEGO cash] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo completar la operación de caja. Revisa el registro del servidor.'], 500);
        }

        return response()->noContent();
    }

    protected function cash_get_open_session(PDO $pdo, bool $lock = false): ?array
    {
        $sql = "SELECT cs.*, u.name AS opened_by_name FROM cash_sessions cs INNER JOIN users u ON u.id = cs.opened_by WHERE cs.status = 'open' ORDER BY cs.id DESC LIMIT 1";
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $session = $pdo->query($sql)->fetch();
        if (! $session) {
            return null;
        }
        foreach (['id', 'opened_by'] as $key) {
            $session[$key] = (int) $session[$key];
        }
        $session['opening_amount'] = (float) $session['opening_amount'];

        return $session;
    }

    protected function cash_session_totals(PDO $pdo, int $sessionId): array
    {
        $stmt = $pdo->prepare(
            "SELECT
            COALESCE(SUM(CASE WHEN method = 'Efectivo' AND payment_type = 'payment' THEN amount WHEN method = 'Efectivo' AND payment_type = 'refund' THEN -amount ELSE 0 END),0) AS cash_net,
            COALESCE(SUM(CASE WHEN method <> 'Efectivo' AND payment_type = 'payment' THEN amount WHEN method <> 'Efectivo' AND payment_type = 'refund' THEN -amount ELSE 0 END),0) AS digital_net,
            COALESCE(SUM(CASE WHEN payment_type = 'payment' THEN amount ELSE 0 END),0) AS payments_total,
            COALESCE(SUM(CASE WHEN payment_type = 'refund' THEN amount ELSE 0 END),0) AS refunds_total
         FROM payments WHERE cash_session_id = :session_id"
        );
        $stmt->execute(['session_id' => $sessionId]);
        $payments = $stmt->fetch() ?: [];
        $movements = $pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN movement_type='income' THEN amount ELSE 0 END),0) AS incomes,
                COALESCE(SUM(CASE WHEN movement_type='expense' THEN amount ELSE 0 END),0) AS expenses
         FROM cash_movements WHERE session_id = :session_id"
        );
        $movements->execute(['session_id' => $sessionId]);
        $other = $movements->fetch() ?: [];
        foreach (['cash_net', 'digital_net', 'payments_total', 'refunds_total'] as $key) {
            $payments[$key] = (float) ($payments[$key] ?? 0);
        }
        $other['incomes'] = (float) ($other['incomes'] ?? 0);
        $other['expenses'] = (float) ($other['expenses'] ?? 0);

        return array_merge($payments, $other);
    }

    protected function cash_expected(PDO $pdo, array $session): array
    {
        $totals = $this->cash_session_totals($pdo, (int) $session['id']);
        $totals['expected_closing'] = round((float) $session['opening_amount'] + $totals['cash_net'] + $totals['incomes'] - $totals['expenses'], 2);

        return $totals;
    }
}
