<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Exceptions\Desego\AdminToolsProblem;
use App\Http\Controllers\Controller;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Exceptions\HttpResponseException;
use PDO;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function App\Support\Desego\audit_log;
use function App\Support\Desego\current_user;
use function App\Support\Desego\desego_apply_retention;
use function App\Support\Desego\desego_backup_config;
use function App\Support\Desego\desego_backup_create;
use function App\Support\Desego\desego_backup_verify;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\desego_header;
use function App\Support\Desego\desego_system_setting;
use function App\Support\Desego\json_body;
use function App\Support\Desego\json_response;
use function App\Support\Desego\require_any_permission;
use function App\Support\Desego\require_csrf;
use function App\Support\Desego\require_method;
use function App\Support\Desego\require_permission;
use function App\Support\Desego\text_length;

class AdminToolsController extends Controller
{
    public function __invoke(): Response
    {
        try {
            $pdo = desego_db();
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            if ($method === 'GET') {
                $view = (string) (request()->query()['view'] ?? '');
                if ($view === 'analytics') {
                    $actor = require_permission('analytics.view');
                    [$from,$to,$start,$end] = $this->at_range();
                    $spanDays = max(1, (int) (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days + 1);
                    $prevStart = (new DateTimeImmutable($start, new DateTimeZone('UTC')))->modify('-'.$spanDays.' days')->format('Y-m-d H:i:s');
                    $prevEnd = $start;
                    $paymentAggregate = static function (PDO $pdo, string $fromAt, string $toAt): array {
                        $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN payment_type='payment' THEN amount ELSE -amount END),0) AS net_total, COALESCE(SUM(CASE WHEN payment_type='payment' THEN amount ELSE 0 END),0) AS payments, COALESCE(SUM(CASE WHEN payment_type='refund' THEN amount ELSE 0 END),0) AS refunds, COUNT(*) AS operations FROM payments WHERE created_at >= :start_at AND created_at < :end_at");
                        $stmt->execute(['start_at' => $fromAt, 'end_at' => $toAt]);
                        $row = $stmt->fetch() ?: [];
                        foreach (['net_total', 'payments', 'refunds'] as $k) {
                            $row[$k] = (float) ($row[$k] ?? 0);
                        } $row['operations'] = (int) ($row['operations'] ?? 0);

                        return $row;
                    };
                    $moneyNow = $paymentAggregate($pdo, $start, $end);
                    $moneyPrev = $paymentAggregate($pdo, $prevStart, $prevEnd);
                    $orders = $pdo->prepare("SELECT COUNT(*) AS total_orders, SUM(payment_status='Pagado') AS paid_orders, SUM(status='Cancelado') AS cancelled_orders, SUM(payment_status='Reembolsado') AS refunded_orders, COALESCE(AVG(CASE WHEN payment_status='Pagado' THEN total END),0) AS average_ticket, COALESCE(SUM(CASE WHEN payment_status='Pagado' THEN total ELSE 0 END),0) AS paid_total, SUM(order_channel='web') AS web_orders, SUM(order_channel='web' AND payment_status='Pagado') AS web_paid_orders FROM orders WHERE created_at >= :start_at AND created_at < :end_at");
                    $orders->execute(['start_at' => $start, 'end_at' => $end]);
                    $orderSummary = $orders->fetch() ?: [];
                    foreach (['total_orders', 'paid_orders', 'cancelled_orders', 'refunded_orders', 'web_orders', 'web_paid_orders'] as $k) {
                        $orderSummary[$k] = (int) ($orderSummary[$k] ?? 0);
                    }
                    foreach (['average_ticket', 'paid_total'] as $k) {
                        $orderSummary[$k] = (float) ($orderSummary[$k] ?? 0);
                    }
                    $customerStmt = $pdo->prepare("SELECT COUNT(DISTINCT o.customer_id) AS unique_customers, COUNT(DISTINCT CASE WHEN c.created_at >= :start_at AND c.created_at < :end_at THEN o.customer_id END) AS new_customers, COUNT(DISTINCT CASE WHEN EXISTS (SELECT 1 FROM orders prior WHERE prior.customer_id=o.customer_id AND prior.payment_status='Pagado' AND prior.paid_at < :start2) THEN o.customer_id END) AS returning_customers FROM orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE o.payment_status='Pagado' AND o.paid_at >= :start3 AND o.paid_at < :end2 AND o.customer_id IS NOT NULL");
                    $customerStmt->execute(['start_at' => $start, 'end_at' => $end, 'start2' => $start, 'start3' => $start, 'end2' => $end]);
                    $customerBehavior = $customerStmt->fetch() ?: [];
                    foreach (['unique_customers', 'new_customers', 'returning_customers'] as $k) {
                        $customerBehavior[$k] = (int) ($customerBehavior[$k] ?? 0);
                    }
                    $dailyStmt = $pdo->prepare("SELECT DATE(DATE_SUB(created_at, INTERVAL 5 HOUR)) AS day, COALESCE(SUM(CASE WHEN payment_type='payment' THEN amount ELSE -amount END),0) AS net_total FROM payments WHERE created_at >= :start_at AND created_at < :end_at GROUP BY DATE(DATE_SUB(created_at, INTERVAL 5 HOUR)) ORDER BY day");
                    $dailyStmt->execute(['start_at' => $start, 'end_at' => $end]);
                    $daily = $dailyStmt->fetchAll();
                    foreach ($daily as &$r) {
                        $r['net_total'] = (float) $r['net_total'];
                    } unset($r);
                    $heatStmt = $pdo->prepare("SELECT DAYOFWEEK(DATE_SUB(paid_at, INTERVAL 5 HOUR)) AS weekday, HOUR(DATE_SUB(paid_at, INTERVAL 5 HOUR)) AS hour, COUNT(*) AS orders_count, COALESCE(SUM(total),0) AS sales FROM orders WHERE payment_status='Pagado' AND paid_at >= :start_at AND paid_at < :end_at AND paid_at IS NOT NULL GROUP BY weekday,hour ORDER BY orders_count DESC LIMIT 60");
                    $heatStmt->execute(['start_at' => $start, 'end_at' => $end]);
                    $heat = $heatStmt->fetchAll();
                    foreach ($heat as &$r) {
                        $r['weekday'] = (int) $r['weekday'];
                        $r['hour'] = (int) $r['hour'];
                        $r['orders_count'] = (int) $r['orders_count'];
                        $r['sales'] = (float) $r['sales'];
                    } unset($r);
                    $profitStmt = $pdo->prepare("SELECT oi.product_id, oi.product_name, SUM(oi.quantity) AS units_sold, SUM(oi.line_total) AS revenue, SUM(oi.quantity * COALESCE(rc.unit_cost,0)) AS estimated_cost, SUM(oi.line_total) - SUM(oi.quantity * COALESCE(rc.unit_cost,0)) AS estimated_margin, MAX(rc.has_recipe) AS has_recipe FROM order_items oi INNER JOIN orders o ON o.id=oi.order_id LEFT JOIN (SELECT pi.product_id, SUM(pi.quantity * i.cost_per_unit) AS unit_cost, 1 AS has_recipe FROM product_ingredients pi INNER JOIN ingredients i ON i.id=pi.ingredient_id GROUP BY pi.product_id) rc ON rc.product_id=oi.product_id WHERE o.payment_status='Pagado' AND o.paid_at >= :start_at AND o.paid_at < :end_at GROUP BY oi.product_id,oi.product_name ORDER BY estimated_margin DESC LIMIT 15");
                    $profitStmt->execute(['start_at' => $start, 'end_at' => $end]);
                    $profit = $profitStmt->fetchAll();
                    foreach ($profit as &$r) {
                        $r['product_id'] = (int) $r['product_id'];
                        $r['units_sold'] = (int) $r['units_sold'];
                        foreach (['revenue', 'estimated_cost', 'estimated_margin'] as $k) {
                            $r[$k] = (float) $r[$k];
                        }$r['has_recipe'] = (int) ($r['has_recipe'] ?? 0);
                    } unset($r);
                    $conversion = $orderSummary['web_orders'] > 0 ? round(100 * $orderSummary['web_paid_orders'] / $orderSummary['web_orders'], 1) : 0.0;
                    $marginTotal = 0.0;
                    foreach ($profit as $r) {
                        if ($r['has_recipe']) {
                            $marginTotal += (float) $r['estimated_margin'];
                        }
                    }
                    $preferenceStmt = $pdo->prepare('SELECT metric_code,is_visible FROM analytics_preferences WHERE user_id=:user_id');
                    $preferenceStmt->execute(['user_id' => $actor['id']]);
                    $preferenceRows = $preferenceStmt->fetchAll();
                    $prefs = [];
                    $metricCodes = ['net_sales', 'paid_orders', 'average_ticket', 'customers', 'conversion', 'estimated_margin', 'cancellations'];
                    foreach ($metricCodes as $metric) {
                        $prefs[$metric] = true;
                    } if ($preferenceRows) {
                        foreach ($metricCodes as $metric) {
                            $prefs[$metric] = false;
                        }foreach ($preferenceRows as $row) {
                            $prefs[$row['metric_code']] = (bool) $row['is_visible'];
                        }
                    }
                    json_response(['ok' => true, 'period' => ['from' => $from, 'to' => $to, 'days' => $spanDays], 'summary' => ['net_total' => $moneyNow['net_total'], 'gross_payments' => $moneyNow['payments'], 'refunds' => $moneyNow['refunds'], 'paid_orders' => $orderSummary['paid_orders'], 'average_ticket' => $orderSummary['average_ticket'], 'total_orders' => $orderSummary['total_orders'], 'cancelled_orders' => $orderSummary['cancelled_orders'], 'refunded_orders' => $orderSummary['refunded_orders'], 'new_customers' => $customerBehavior['new_customers'], 'returning_customers' => $customerBehavior['returning_customers'], 'unique_customers' => $customerBehavior['unique_customers'], 'conversion_rate' => $conversion, 'estimated_margin' => $marginTotal, 'previous_net_total' => $moneyPrev['net_total'], 'change_percent' => $moneyPrev['net_total'] != 0 ? round(($moneyNow['net_total'] - $moneyPrev['net_total']) / abs($moneyPrev['net_total']) * 100, 1) : null, 'forecast_7_days' => $spanDays > 0 ? round($moneyNow['net_total'] / $spanDays * 7, 2) : 0], 'daily' => $daily, 'peak_hours' => $heat, 'product_profitability' => $profit, 'preferences' => $prefs]);
                }
                if ($view === 'audit' || $view === 'audit-export') {
                    $actor = require_permission($view === 'audit-export' ? 'audit.export' : 'audit.view');
                    $filters = ['from' => trim((string) (request()->query()['from'] ?? '')), 'to' => trim((string) (request()->query()['to'] ?? '')), 'event' => trim((string) (request()->query()['event'] ?? '')), 'actor' => trim((string) (request()->query()['actor'] ?? '')), 'q' => trim((string) (request()->query()['q'] ?? ''))];
                    if ($filters['from'] !== '') {
                        $filters['from'] = $this->at_date($filters['from'], 'inicial');
                    } if ($filters['to'] !== '') {
                        $filters['to'] = $this->at_date($filters['to'], 'final');
                    }
                    if ($filters['from'] !== '' && $filters['to'] !== '' && $filters['from'] > $filters['to']) {
                        throw new AdminToolsProblem('La fecha inicial no puede ser posterior a la final.');
                    }
                    if ($view === 'audit-export') {
                        $result = $this->at_audit_rows($pdo, $filters, 10000, 0);
                        desego_header('Content-Type: text/csv; charset=utf-8');
                        desego_header('Content-Disposition: attachment; filename="desego-auditoria-'.gmdate('Ymd-His').'.csv"');
                        desego_header('Cache-Control: no-store, max-age=0');
                        $csv = "\xEF\xBB\xBF";
                        $head = ['ID', 'Fecha UTC', 'Usuario', 'Correo', 'Evento', 'IP', 'Detalle'];
                        $csv .= implode(';', array_map([$this, 'at_csv_cell'], $head))."\r\n";
                        foreach ($result['rows'] as $r) {
                            $fields = [$r['id'], $r['created_at'], $r['actor_name'], $r['actor_email'], $r['event_code'], $r['ip_address'], json_encode($r['details_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
                            $csv .= implode(';', array_map([$this, 'at_csv_cell'], $fields))."\r\n";
                        }
                        $actor = current_user();
                        audit_log($pdo, $actor ? (int) $actor['id'] : null, 'audit.exported', ['rows' => count($result['rows']), 'filters' => $filters]);

                        return response($csv);
                    }
                    $page = max(1, min(100000, (int) (request()->query()['page'] ?? 1)));
                    $result = $this->at_audit_rows($pdo, $filters, 100, min(1000000, ($page - 1) * 100));
                    $threshold = max(3, min(100, (int) $this->at_setting($pdo, 'suspicious_login_threshold', '5')));
                    $alertStmt = $pdo->prepare("SELECT ip_address, COUNT(*) AS attempts, MIN(created_at) AS first_attempt, MAX(created_at) AS last_attempt FROM audit_logs WHERE event_code='auth.login_failed' AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE) GROUP BY ip_address HAVING COUNT(*) >= :threshold ORDER BY attempts DESC LIMIT 20");
                    $alertStmt->bindValue(':threshold', $threshold, PDO::PARAM_INT);
                    $alertStmt->execute();
                    $alerts = $alertStmt->fetchAll();
                    foreach ($alerts as &$a) {
                        $a['attempts'] = (int) $a['attempts'];
                    }unset($a);
                    json_response(['ok' => true, 'filters' => $filters, 'page' => $page, 'page_size' => 100, 'total' => $result['total'], 'rows' => $result['rows'], 'alerts' => $alerts, 'settings' => ['audit_retention_days' => (int) $this->at_setting($pdo, 'audit_retention_days', '365'), 'backup_retention_days' => (int) $this->at_setting($pdo, 'backup_retention_days', '90'), 'suspicious_login_threshold' => $threshold], 'can_export' => in_array('audit.export', $actor['permissions'] ?? [], true), 'can_manage_retention' => in_array('audit.retention.manage', $actor['permissions'] ?? [], true)]);
                }
                if ($view === 'backups') {
                    $actor = require_permission('backups.view');
                    $rows = $pdo->query('SELECT b.id,b.file_name,b.secondary_file_name,b.backup_kind,b.status,b.size_bytes,b.sha256_hash,b.is_encrypted,b.verification_status,b.verified_at,b.error_message,b.created_at,u.name AS created_by_name FROM backup_history b LEFT JOIN users u ON u.id=b.created_by ORDER BY b.created_at DESC,b.id DESC LIMIT 100')->fetchAll();
                    foreach ($rows as &$r) {
                        $r['id'] = (int) $r['id'];
                        $r['size_bytes'] = (int) $r['size_bytes'];
                        $r['is_encrypted'] = (bool) $r['is_encrypted'];
                        $r['has_secondary_copy'] = ! empty($r['secondary_file_name']);
                        if ($r['sha256_hash']) {
                            $r['sha256_prefix'] = substr((string) $r['sha256_hash'], 0, 16);
                        }
                    }unset($r);
                    json_response(['ok' => true, 'backups' => $rows, 'settings' => ['audit_retention_days' => (int) $this->at_setting($pdo, 'audit_retention_days', '365'), 'backup_retention_days' => (int) $this->at_setting($pdo, 'backup_retention_days', '90'), 'suspicious_login_threshold' => (int) $this->at_setting($pdo, 'suspicious_login_threshold', '5')], 'permissions' => ['create' => in_array('backups.create', $actor['permissions'] ?? [], true), 'manage' => in_array('backups.manage', $actor['permissions'] ?? [], true), 'retention' => in_array('audit.retention.manage', $actor['permissions'] ?? [], true)]]);
                }
                if ($view === 'download') {
                    require_permission('backups.view');
                    $id = filter_var(request()->query()['id'] ?? null, FILTER_VALIDATE_INT);
                    if (! $id) {
                        throw new AdminToolsProblem('Identificador de backup no válido.');
                    }
                    $verified = desego_backup_verify($pdo, (int) $id);
                    $row = $verified['row'];
                    $actor = current_user();
                    audit_log($pdo, $actor ? (int) $actor['id'] : null, 'backup.downloaded', ['backup_id' => (int) $id, 'sha256_prefix' => substr($verified['sha256_hash'], 0, 12)]);
                    desego_header('Content-Type: application/octet-stream');
                    desego_header('X-Content-Type-Options: nosniff');
                    desego_header('Cache-Control: no-store, max-age=0');
                    desego_header('Content-Length: '.filesize($verified['path']));
                    desego_header('Content-Disposition: attachment; filename="'.basename((string) $row['file_name']).'"');

                    return response()->download($verified['path'], basename((string) $row['file_name']));
                }
                if ($view === 'settings') {
                    require_any_permission(['settings.manage', 'dashboard.view']);
                    $settingsRows = $pdo->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll();
                    $settingsMap = [];
                    foreach ($settingsRows as $sr) {
                        $settingsMap[(string) $sr['setting_key']] = (string) $sr['setting_value'];
                    }
                    $paymentMethods = json_decode($settingsMap['restaurant_payment_methods'] ?? '', true);
                    if (! is_array($paymentMethods) || ! $paymentMethods) {
                        $paymentMethods = ['Efectivo', 'Yape/Plin', 'Tarjeta al recibir'];
                    }
                    $hours = [];
                    try {
                        $hours = $pdo->query('SELECT day_of_week, day_name, open_time, close_time, is_closed FROM business_hours ORDER BY day_of_week')->fetchAll();
                        foreach ($hours as &$h) {
                            $h['day_of_week'] = (int) $h['day_of_week'];
                            $h['open_time'] = substr((string) $h['open_time'], 0, 5);
                            $h['close_time'] = substr((string) $h['close_time'], 0, 5);
                            $h['is_closed'] = (bool) $h['is_closed'];
                        }
                        unset($h);
                    } catch (Throwable) {
                    }
                    $resConfig = null;
                    try {
                        $resConfig = $pdo->query('SELECT advance_amount, tolerance_minutes, cancellation_deadline_hours, reservations_enabled FROM reservation_configurations ORDER BY id LIMIT 1')->fetch() ?: null;
                        if ($resConfig) {
                            $resConfig['advance_amount'] = (float) $resConfig['advance_amount'];
                            $resConfig['tolerance_minutes'] = (int) $resConfig['tolerance_minutes'];
                            $resConfig['cancellation_deadline_hours'] = (int) $resConfig['cancellation_deadline_hours'];
                            $resConfig['reservations_enabled'] = (bool) $resConfig['reservations_enabled'];
                        }
                    } catch (Throwable) {
                    }
                    $branch = $pdo->query("SELECT id, name, address, district, phone, email, opening_time, closing_time FROM branches WHERE status = 'active' ORDER BY is_main DESC, id ASC LIMIT 1")->fetch() ?: [];
                    json_response([
                        'ok' => true,
                        'commercial' => [
                            'restaurant_name' => $settingsMap['restaurant_name'] ?? 'DeSego Cevichería',
                            'restaurant_specialty' => $settingsMap['restaurant_specialty'] ?? 'Pescados, mariscos y comida peruana',
                            'restaurant_address' => $settingsMap['restaurant_address'] ?? ($branch['address'] ?? 'UCV 19, Lote 13, Zona A, Huaycán, Ate'),
                            'restaurant_district' => $settingsMap['restaurant_district'] ?? ($branch['district'] ?? 'Ate'),
                            'restaurant_postal_code' => $settingsMap['restaurant_postal_code'] ?? '15479',
                            'restaurant_latitude' => (float) ($settingsMap['restaurant_latitude'] ?? -12.0168500),
                            'restaurant_longitude' => (float) ($settingsMap['restaurant_longitude'] ?? -76.8211200),
                            'restaurant_whatsapp' => $settingsMap['restaurant_whatsapp'] ?? '+51 922 119 238',
                            'restaurant_whatsapp_url' => $settingsMap['restaurant_whatsapp_url'] ?? 'https://wa.me/51922119238',
                            'restaurant_facebook' => $settingsMap['restaurant_facebook'] ?? 'https://facebook.com/desegocevicheria',
                            'restaurant_instagram' => $settingsMap['restaurant_instagram'] ?? 'https://instagram.com/desegocevicheria',
                            'restaurant_tiktok' => $settingsMap['restaurant_tiktok'] ?? 'https://www.tiktok.com/@desegocevicheria/',
                            'restaurant_email' => $settingsMap['restaurant_email'] ?? '',
                            'delivery_enabled' => ($settingsMap['delivery_enabled'] ?? '1') === '1',
                            'pickup_enabled' => ($settingsMap['pickup_enabled'] ?? '1') === '1',
                            'dine_in_enabled' => ($settingsMap['dine_in_enabled'] ?? '1') === '1',
                            'reservations_enabled' => ($settingsMap['reservations_enabled'] ?? '1') === '1',
                            'payment_methods' => array_values($paymentMethods),
                        ],
                        'business_hours' => $hours,
                        'reservation_config' => $resConfig ?: [
                            'advance_amount' => 10.00,
                            'tolerance_minutes' => 15,
                            'cancellation_deadline_hours' => 3,
                            'reservations_enabled' => true,
                        ],
                    ]);
                }
                json_response(['ok' => false, 'message' => 'Vista administrativa no válida.'], 404);
            }
            require_method('POST');
            $body = json_body();
            require_csrf($body);
            $action = (string) ($body['action'] ?? '');
            if ($action === 'create_backup') {
                $actor = require_permission('backups.create');
                try {
                    $created = desego_backup_create($pdo, (int) $actor['id'], 'manual');
                    json_response(['ok' => true, 'message' => 'Copia cifrada creada correctamente.', 'backup' => $created]);
                } catch (Throwable $error) {
                    if ($error instanceof HttpResponseException) {
                        throw $error;
                    }$message = $error instanceof RuntimeException ? $error->getMessage() : 'No se pudo crear el backup. Revisa los registros del servidor.';
                    audit_log($pdo, (int) $actor['id'], 'backup.failed', ['kind' => 'manual', 'reason' => substr($message, 0, 250)]);
                    throw new AdminToolsProblem($message, 500);
                }
            }
            if ($action === 'verify_backup') {
                $actor = require_permission('backups.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                if (! $id) {
                    throw new AdminToolsProblem('Identificador no válido.');
                }$result = desego_backup_verify($pdo, (int) $id);
                audit_log($pdo, (int) $actor['id'], 'backup.verified', ['backup_id' => (int) $id, 'sha256_prefix' => substr($result['sha256_hash'], 0, 12)]);
                json_response(['ok' => true, 'message' => 'El hash SHA-256 coincide y el contenido descifra correctamente.', 'verification_status' => 'valid']);
            }
            if ($action === 'delete_backup') {
                $actor = require_permission('backups.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                if (! $id) {
                    throw new AdminToolsProblem('Identificador no válido.');
                }
                $total = (int) $pdo->query("SELECT COUNT(*) FROM backup_history WHERE status='completed'")->fetchColumn();
                if ($total <= 1) {
                    throw new AdminToolsProblem('No se puede eliminar la última copia completada. Crea otra copia primero.', 409);
                }
                $stmt = $pdo->prepare("SELECT * FROM backup_history WHERE id=:id AND status='completed' LIMIT 1");
                $stmt->execute(['id' => $id]);
                $row = $stmt->fetch();
                if (! $row) {
                    throw new AdminToolsProblem('La copia no existe o ya fue eliminada.', 404);
                }
                $config = desego_backup_config();
                foreach ([[(string) ($row['file_name'] ?? ''), (string) $config['storage_dir']], [(string) ($row['secondary_file_name'] ?? ''), (string) $config['redundant_dir']]] as $pair) {
                    [$name,$dir] = $pair;
                    if ($name !== '' && $dir !== '' && preg_match('/^[a-zA-Z0-9._-]+\.dsgbk$/', $name)) {
                        @unlink(rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name);
                    }
                }
                $update = $pdo->prepare("UPDATE backup_history SET status='deleted',verification_status='deleted',error_message='Eliminado manualmente por un administrador.' WHERE id=:id");
                $update->execute(['id' => $id]);
                audit_log($pdo, (int) $actor['id'], 'backup.deleted', ['backup_id' => (int) $id]);
                json_response(['ok' => true, 'message' => 'Copia eliminada; se conservó el registro histórico.']);
            }
            if ($action === 'update_settings') {
                $actor = require_permission('backups.manage');
                require_permission('audit.retention.manage');
                $auditDays = filter_var($body['audit_retention_days'] ?? 365, FILTER_VALIDATE_INT);
                $backupDays = filter_var($body['backup_retention_days'] ?? 90, FILTER_VALIDATE_INT);
                $threshold = filter_var($body['suspicious_login_threshold'] ?? 5, FILTER_VALIDATE_INT);
                if ($auditDays === false || $auditDays < 30 || $auditDays > 3650 || $backupDays === false || $backupDays < 7 || $backupDays > 3650 || $threshold === false || $threshold < 3 || $threshold > 100) {
                    throw new AdminToolsProblem('Retención de auditoría: 30–3650 días; backups: 7–3650 días; alertas: 3–100 intentos.');
                }
                $stmt = $pdo->prepare('INSERT INTO system_settings (setting_key,setting_value,updated_by) VALUES (:key,:value,:user_id) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by),updated_at=UTC_TIMESTAMP()');
                foreach (['audit_retention_days' => (string) $auditDays, 'backup_retention_days' => (string) $backupDays, 'suspicious_login_threshold' => (string) $threshold] as $key => $value) {
                    $stmt->execute(['key' => $key, 'value' => $value, 'user_id' => (int) $actor['id']]);
                }
                audit_log($pdo, (int) $actor['id'], 'retention.settings_updated', ['audit_retention_days' => $auditDays, 'backup_retention_days' => $backupDays, 'suspicious_login_threshold' => $threshold]);
                json_response(['ok' => true, 'message' => 'La configuración de retención y alertas quedó guardada.']);
            }
            if ($action === 'apply_retention') {
                $actor = require_permission('backups.manage');
                require_permission('audit.retention.manage');
                $result = desego_apply_retention($pdo, (int) $actor['id']);
                json_response(['ok' => true, 'message' => 'Política aplicada.', 'result' => $result]);
            }
            if ($action === 'save_analytics_preferences') {
                $actor = require_permission('analytics.preferences');
                $allowed = ['net_sales', 'paid_orders', 'average_ticket', 'customers', 'conversion', 'estimated_margin', 'cancellations'];
                $metrics = $body['metrics'] ?? [];
                if (! is_array($metrics)) {
                    throw new AdminToolsProblem('Los indicadores seleccionados no son válidos.');
                }$metrics = array_values(array_unique(array_filter($metrics, static fn ($v) => is_string($v) && in_array($v, $allowed, true))));
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('DELETE FROM analytics_preferences WHERE user_id=:user_id')->execute(['user_id' => (int) $actor['id']]);
                    $stmt = $pdo->prepare('INSERT INTO analytics_preferences (user_id,metric_code,is_visible) VALUES (:user_id,:metric_code,:visible)');
                    foreach ($allowed as $metric) {
                        $stmt->execute(['user_id' => (int) $actor['id'], 'metric_code' => $metric, 'visible' => in_array($metric, $metrics, true) ? 1 : 0]);
                    }$pdo->commit();
                } catch (Throwable $e) {
                    if ($e instanceof HttpResponseException) {
                        throw $e;
                    }if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }throw $e;
                }
                audit_log($pdo, (int) $actor['id'], 'analytics.preferences_updated', ['metrics' => $metrics]);
                json_response(['ok' => true, 'message' => 'Preferencias de indicadores guardadas.']);
            }
            if ($action === 'update_commercial_settings') {
                $actor = require_permission('settings.manage');
                $name = trim((string) ($body['restaurant_name'] ?? 'DeSego Cevichería'));
                $specialty = trim((string) ($body['restaurant_specialty'] ?? 'Pescados, mariscos y comida peruana'));
                $address = trim((string) ($body['restaurant_address'] ?? ''));
                $district = trim((string) ($body['restaurant_district'] ?? 'Ate'));
                $postalCode = trim((string) ($body['restaurant_postal_code'] ?? '15479'));
                $lat = filter_var($body['restaurant_latitude'] ?? -12.0168500, FILTER_VALIDATE_FLOAT);
                $lng = filter_var($body['restaurant_longitude'] ?? -76.8211200, FILTER_VALIDATE_FLOAT);
                $whatsapp = trim((string) ($body['restaurant_whatsapp'] ?? '+51 922 119 238'));
                $whatsappDigits = preg_replace('/\D+/', '', $whatsapp) ?: '51922119238';
                $whatsappUrl = trim((string) ($body['restaurant_whatsapp_url'] ?? '')) ?: ('https://wa.me/'.$whatsappDigits);
                $facebook = trim((string) ($body['restaurant_facebook'] ?? ''));
                $instagram = trim((string) ($body['restaurant_instagram'] ?? ''));
                $tiktok = trim((string) ($body['restaurant_tiktok'] ?? ''));
                $email = trim((string) ($body['restaurant_email'] ?? ''));
                $paymentMethods = $body['payment_methods'] ?? ['Efectivo', 'Yape/Plin', 'Tarjeta al recibir'];
                if (is_string($paymentMethods)) {
                    $paymentMethods = array_filter(array_map('trim', explode(',', $paymentMethods)));
                }
                if (! is_array($paymentMethods) || ! $paymentMethods) {
                    throw new AdminToolsProblem('Debes habilitar al menos un método de pago.', 422);
                }
                $cleanMethods = array_values(array_unique(array_filter(array_map(static fn ($m) => mb_substr(trim((string) $m), 0, 50), $paymentMethods))));
                if (! $cleanMethods || text_length($name) < 2 || text_length($address) < 5 || $lat === false || $lng === false) {
                    throw new AdminToolsProblem('Revisa el nombre comercial, la dirección, las coordenadas y los métodos de pago.', 422);
                }
                if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new AdminToolsProblem('El correo comercial ingresado no tiene un formato válido.', 422);
                }
                $pairs = [
                    'restaurant_name' => $name,
                    'restaurant_specialty' => $specialty,
                    'restaurant_address' => $address,
                    'restaurant_district' => $district,
                    'restaurant_postal_code' => $postalCode,
                    'restaurant_latitude' => number_format((float) $lat, 7, '.', ''),
                    'restaurant_longitude' => number_format((float) $lng, 7, '.', ''),
                    'restaurant_whatsapp' => $whatsapp,
                    'restaurant_whatsapp_url' => $whatsappUrl,
                    'restaurant_facebook' => $facebook,
                    'restaurant_instagram' => $instagram,
                    'restaurant_tiktok' => $tiktok,
                    'restaurant_email' => $email,
                    'delivery_enabled' => ! empty($body['delivery_enabled']) ? '1' : '0',
                    'pickup_enabled' => ! empty($body['pickup_enabled']) ? '1' : '0',
                    'dine_in_enabled' => ! empty($body['dine_in_enabled']) ? '1' : '0',
                    'reservations_enabled' => ! empty($body['reservations_enabled']) ? '1' : '0',
                    'restaurant_payment_methods' => json_encode($cleanMethods, JSON_UNESCAPED_UNICODE),
                ];
                $stmt = $pdo->prepare('INSERT INTO system_settings (setting_key,setting_value,updated_by) VALUES (:key,:value,:user_id) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by),updated_at=UTC_TIMESTAMP()');
                foreach ($pairs as $key => $val) {
                    $stmt->execute(['key' => $key, 'value' => $val, 'user_id' => (int) $actor['id']]);
                }
                $pdo->prepare('UPDATE branches SET name=:name, address=:address, district=:district, phone=:phone, email=:email, updated_at=NOW() WHERE is_main = 1')
                    ->execute([
                        'name' => $name.' - Sede Huaycán',
                        'address' => $address,
                        'district' => $district,
                        'phone' => $whatsapp,
                        'email' => $email !== '' ? $email : null,
                    ]);
                audit_log($pdo, (int) $actor['id'], 'settings.commercial_updated', ['restaurant_name' => $name]);
                json_response(['ok' => true, 'message' => 'Datos comerciales, redes sociales y modalidades actualizados.']);
            }
            if ($action === 'update_business_hours') {
                $actor = require_permission('settings.manage');
                $hoursInput = $body['hours'] ?? null;
                if (! is_array($hoursInput) || count($hoursInput) < 1) {
                    throw new AdminToolsProblem('Envía la programación semanal de horarios.', 422);
                }
                $dayNames = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
                $pdo->beginTransaction();
                try {
                    $upsert = $pdo->prepare(
                        'INSERT INTO business_hours (day_of_week, day_name, open_time, close_time, is_closed, created_at, updated_at)
                         VALUES (:dow, :name, :open_t, :close_t, :closed, NOW(), NOW())
                         ON DUPLICATE KEY UPDATE day_name=VALUES(day_name), open_time=VALUES(open_time), close_time=VALUES(close_time), is_closed=VALUES(is_closed), updated_at=NOW()'
                    );
                    $firstOpen = null;
                    $firstClose = null;
                    foreach ($hoursInput as $item) {
                        if (! is_array($item)) {
                            continue;
                        }
                        $dow = filter_var($item['day_of_week'] ?? null, FILTER_VALIDATE_INT);
                        if (! $dow || ! isset($dayNames[$dow])) {
                            continue;
                        }
                        $open = substr(trim((string) ($item['open_time'] ?? '09:00')), 0, 5);
                        $close = substr(trim((string) ($item['close_time'] ?? '17:00')), 0, 5);
                        $closed = ! empty($item['is_closed']) ? 1 : 0;
                        if (! preg_match('/^\d{2}:\d{2}$/', $open) || ! preg_match('/^\d{2}:\d{2}$/', $close)) {
                            throw new AdminToolsProblem('Formato de hora inválido en '.$dayNames[$dow].' (usa HH:MM).', 422);
                        }
                        if (! $closed && $open >= $close) {
                            throw new AdminToolsProblem('La hora de apertura debe ser menor que la de cierre en '.$dayNames[$dow].'.', 422);
                        }
                        $upsert->execute([
                            'dow' => $dow,
                            'name' => $dayNames[$dow],
                            'open_t' => $open.':00',
                            'close_t' => $close.':00',
                            'closed' => $closed,
                        ]);
                        if (! $closed && $firstOpen === null) {
                            $firstOpen = $open.':00';
                            $firstClose = $close.':00';
                        }
                    }
                    if ($firstOpen && $firstClose) {
                        $pdo->prepare('UPDATE branches SET opening_time=:open_t, closing_time=:close_t, updated_at=NOW() WHERE is_main = 1')
                            ->execute(['open_t' => $firstOpen, 'close_t' => $firstClose]);
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($e instanceof HttpResponseException) {
                        throw $e;
                    }
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                audit_log($pdo, (int) $actor['id'], 'settings.business_hours_updated', []);
                json_response(['ok' => true, 'message' => 'Horarios de atención actualizados correctamente.']);
            }
            if ($action === 'update_reservation_settings') {
                $actor = require_permission('settings.manage');
                $advance = filter_var($body['advance_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
                $tolerance = filter_var($body['tolerance_minutes'] ?? 15, FILTER_VALIDATE_INT);
                $deadline = filter_var($body['cancellation_deadline_hours'] ?? 3, FILTER_VALIDATE_INT);
                $enabled = ! empty($body['reservations_enabled']) ? 1 : 0;
                if ($advance === false || $advance < 0 || $advance > 500 || $tolerance === false || $tolerance < 5 || $tolerance > 120 || $deadline === false || $deadline < 0 || $deadline > 72) {
                    throw new AdminToolsProblem('Revisa el monto de anticipo (0–500), tolerancia (5–120 min) y plazo de cancelación (0–72 h).', 422);
                }
                $existsId = $pdo->query('SELECT id FROM reservation_configurations ORDER BY id LIMIT 1')->fetchColumn();
                if ($existsId) {
                    $pdo->prepare('UPDATE reservation_configurations SET advance_amount=:adv, tolerance_minutes=:tol, cancellation_deadline_hours=:dead, reservations_enabled=:en, updated_at=NOW() WHERE id=:id')
                        ->execute(['adv' => number_format((float) $advance, 2, '.', ''), 'tol' => (int) $tolerance, 'dead' => (int) $deadline, 'en' => $enabled, 'id' => (int) $existsId]);
                } else {
                    $pdo->prepare('INSERT INTO reservation_configurations (advance_amount, tolerance_minutes, cancellation_deadline_hours, reservations_enabled, created_at, updated_at) VALUES (:adv, :tol, :dead, :en, NOW(), NOW())')
                        ->execute(['adv' => number_format((float) $advance, 2, '.', ''), 'tol' => (int) $tolerance, 'dead' => (int) $deadline, 'en' => $enabled]);
                }
                audit_log($pdo, (int) $actor['id'], 'settings.reservations_updated', ['advance_amount' => $advance, 'reservations_enabled' => $enabled]);
                json_response(['ok' => true, 'message' => 'Políticas de reservas actualizadas.']);
            }
            json_response(['ok' => false, 'message' => 'Acción administrativa no válida.'], 404);
        } catch (AdminToolsProblem $error) {
            json_response(['ok' => false, 'message' => $error->getMessage()], $error->httpStatus);
        } catch (Throwable $error) {
            if ($error instanceof HttpResponseException) {
                throw $error;
            }
            error_log('[DESEGO admin-tools] '.$error->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo completar la operación. Revisa los permisos, la migración del Paso 12 y los registros de PHP.'], 500);
        }

        return response()->noContent();
    }

    protected function at_date(string $value, string $label): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if (! $date || $date->format('Y-m-d') !== $value) {
            throw new AdminToolsProblem('La fecha '.$label.' no es válida.', 422);
        }

        return $value;
    }

    protected function at_range(): array
    {
        $tz = new DateTimeZone('America/Lima');
        $utc = new DateTimeZone('UTC');
        $today = new DateTimeImmutable('today', $tz);
        $from = $this->at_date((string) (request()->query()['from'] ?? $today->modify('first day of this month')->format('Y-m-d')), 'inicial');
        $to = $this->at_date((string) (request()->query()['to'] ?? $today->format('Y-m-d')), 'final');
        if ($from > $to) {
            throw new AdminToolsProblem('La fecha inicial no puede ser posterior a la final.');
        }
        if ((new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days > 366) {
            throw new AdminToolsProblem('El período máximo es de 367 días.');
        }

        return [
            $from, $to,
            (new DateTimeImmutable($from.' 00:00:00', $tz))->setTimezone($utc)->format('Y-m-d H:i:s'),
            (new DateTimeImmutable($to.' 00:00:00', $tz))->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s'),
        ];
    }

    protected function at_setting(PDO $pdo, string $key, string $default): string
    {
        return desego_system_setting($pdo, $key, $default);
    }

    protected function at_audit_rows(PDO $pdo, array $filters, int $limit = 100, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];
        if (($filters['from'] ?? '') !== '') {
            $where[] = 'a.created_at >= :from_at';
            $params['from_at'] = $filters['from'].' 00:00:00';
        }
        if (($filters['to'] ?? '') !== '') {
            $where[] = 'a.created_at < :to_at';
            $params['to_at'] = (new DateTimeImmutable($filters['to'], new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d H:i:s');
        }
        if (($filters['event'] ?? '') !== '') {
            $where[] = 'a.event_code LIKE :event_code';
            $params['event_code'] = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filters['event']).'%';
        }
        if (($filters['actor'] ?? '') !== '' && ctype_digit((string) $filters['actor'])) {
            $where[] = 'a.user_id = :user_id';
            $params['user_id'] = (int) $filters['actor'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(a.event_code LIKE :q_event OR CAST(a.details AS CHAR) LIKE :q_details OR a.ip_address LIKE :q_ip OR u.name LIKE :q_name OR u.email LIKE :q_email)';
            $rawNeedle = (string) $filters['q'];
            $rawNeedle = function_exists('mb_substr') ? mb_substr($rawNeedle, 0, 100, 'UTF-8') : substr($rawNeedle, 0, 100);
            $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $rawNeedle).'%';
            foreach (['q_event', 'q_details', 'q_ip', 'q_name', 'q_email'] as $key) {
                $params[$key] = $needle;
            }
        }
        $clause = implode(' AND ', $where);
        $count = $pdo->prepare("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE $clause");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $stmt = $pdo->prepare("SELECT a.id, a.user_id, COALESCE(u.name, 'Sistema/Invitado') AS actor_name, u.email AS actor_email, a.event_code, a.details, a.ip_address, a.created_at FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE $clause ORDER BY a.created_at DESC, a.id DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':'.$key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['user_id'] = $row['user_id'] === null ? null : (int) $row['user_id'];
            if (is_string($row['details'])) {
                $decoded = json_decode($row['details'], true);
                $row['details_json'] = is_array($decoded) ? $decoded : [];
            } else {
                $row['details_json'] = [];
            }
        }
        unset($row);

        return ['rows' => $rows, 'total' => $total];
    }

    protected function at_csv_cell(mixed $value): string
    {
        $text = (string) ($value ?? '');
        if (preg_match('/^[=+@\-]/', $text)) {
            $text = "'".$text;
        }

        return '"'.str_replace('"', '""', $text).'"';
    }
}
