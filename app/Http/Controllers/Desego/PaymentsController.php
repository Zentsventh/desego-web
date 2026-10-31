<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Http\Controllers\Controller;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Exceptions\HttpResponseException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function App\Support\Desego\desego_db;
use function App\Support\Desego\json_response;
use function App\Support\Desego\require_method;
use function App\Support\Desego\require_permission;

class PaymentsController extends Controller
{
    public function __invoke(): Response
    {
        try {
            require_method('GET');
            $pdo = desego_db();
            require_permission('payments.view');
            $from = trim((string) (request()->query()['from'] ?? ''));
            $to = trim((string) (request()->query()['to'] ?? ''));
            $where = [];
            $params = [];
            if ($from !== '') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $from, new DateTimeZone('UTC'));
                if (! $date || $date->format('Y-m-d') !== $from) {
                    json_response(['ok' => false, 'message' => 'Fecha inicial no válida.'], 422);
                }
                $where[] = 'p.created_at >= :from_date';
                $params['from_date'] = $from.' 00:00:00';
            }
            if ($to !== '') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $to, new DateTimeZone('UTC'));
                if (! $date || $date->format('Y-m-d') !== $to) {
                    json_response(['ok' => false, 'message' => 'Fecha final no válida.'], 422);
                }
                $where[] = 'p.created_at < DATE_ADD(:to_date, INTERVAL 1 DAY)';
                $params['to_date'] = $to.' 00:00:00';
            }
            if ($from !== '' && $to !== '' && $from > $to) {
                json_response(['ok' => false, 'message' => 'La fecha inicial no puede ser posterior a la final.'], 422);
            }
            $whereSql = $where ? (' WHERE '.implode(' AND ', $where)) : '';
            $summaryStmt = $pdo->prepare("SELECT COUNT(*) AS count_total,
        COALESCE(SUM(CASE WHEN p.payment_type='payment' THEN p.amount ELSE 0 END),0) AS gross_payments,
        COALESCE(SUM(CASE WHEN p.payment_type='refund' THEN p.amount ELSE 0 END),0) AS gross_refunds,
        COALESCE(SUM(CASE WHEN p.payment_type='payment' THEN p.amount WHEN p.payment_type='refund' THEN -p.amount ELSE 0 END),0) AS net_total
        FROM payments p $whereSql");
            $summaryStmt->execute($params);
            $summary = $summaryStmt->fetch() ?: [];
            $summary = [
                'count_total' => (int) ($summary['count_total'] ?? 0),
                'gross_payments' => (float) ($summary['gross_payments'] ?? 0),
                'gross_refunds' => (float) ($summary['gross_refunds'] ?? 0),
                'net_total' => (float) ($summary['net_total'] ?? 0),
            ];
            $stmt = $pdo->prepare("SELECT p.id, p.order_id, p.cash_session_id, p.user_id, p.payment_type, p.method, p.amount, p.notes, p.created_at,
        o.order_number, o.customer_name, o.order_channel, u.name AS user_name
        FROM payments p INNER JOIN orders o ON o.id=p.order_id INNER JOIN users u ON u.id=p.user_id
        $whereSql ORDER BY p.id DESC LIMIT 250");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$row) {
                foreach (['id', 'order_id', 'cash_session_id', 'user_id'] as $key) {
                    $row[$key] = (int) $row[$key];
                }
                $row['amount'] = (float) $row['amount'];
            }
            unset($row);
            $methodsStmt = $pdo->prepare("SELECT p.method,
        COALESCE(SUM(CASE WHEN p.payment_type='payment' THEN p.amount ELSE 0 END),0) AS payments,
        COALESCE(SUM(CASE WHEN p.payment_type='refund' THEN p.amount ELSE 0 END),0) AS refunds
        FROM payments p $whereSql GROUP BY p.method ORDER BY p.method");
            $methodsStmt->execute($params);
            $methods = $methodsStmt->fetchAll();
            foreach ($methods as &$method) {
                $method['payments'] = (float) $method['payments'];
                $method['refunds'] = (float) $method['refunds'];
            }
            unset($method);
            json_response(['ok' => true, 'summary' => $summary, 'payments' => $rows, 'methods' => $methods]);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            if ($e instanceof RuntimeException && method_exists($e, 'httpStatus')) {
                json_response(['ok' => false, 'message' => $e->getMessage()], (int) $e->httpStatus);
            }
            error_log('[DESEGO payments] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo cargar el historial de pagos. Comprueba permisos y migraciones.'], 500);
        }

        return response()->noContent();
    }
}
