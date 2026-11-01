<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Exceptions\Desego\BillingReportsProblem;
use App\Http\Controllers\Controller;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Exceptions\HttpResponseException;
use PDO;
use RuntimeException;
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

class BillingReportsController extends Controller
{
    public function __invoke(): Response
    {
        try {
            $pdo = desego_db();
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            if ($method === 'GET') {
                $view = (string) (request()->query()['view'] ?? 'invoices');
                if ($view === 'invoices') {
                    $actor = require_any_permission(['invoices.view', 'invoices.manage']);
                    $stmt = $pdo->query(
                        'SELECT i.*, o.order_number, o.customer_name AS order_customer_name, o.order_type,
                        u.name AS issuer_name, vu.name AS voider_name
                 FROM invoices i
                 INNER JOIN orders o ON o.id = i.order_id
                 INNER JOIN users u ON u.id = i.issued_by
                 LEFT JOIN users vu ON vu.id = i.voided_by
                 ORDER BY i.issued_at DESC, i.id DESC LIMIT 200'
                    );
                    $invoices = $stmt->fetchAll();
                    $this->br_fetch_invoice_items($pdo, $invoices);
                    $eligibleOrders = [];
                    if (in_array('invoices.manage', $actor['permissions'] ?? [], true)) {
                        $eligibleStmt = $pdo->query(
                            "SELECT o.id, o.order_number, o.customer_name, o.order_type, o.total, o.subtotal,
                            o.discount, o.paid_at, o.payment_method
                     FROM orders o LEFT JOIN invoices i ON i.order_id = o.id
                     WHERE o.payment_status = 'Pagado' AND o.status <> 'Cancelado' AND i.id IS NULL
                     ORDER BY o.paid_at DESC, o.id DESC LIMIT 200"
                        );
                        $eligibleOrders = $eligibleStmt->fetchAll();
                        foreach ($eligibleOrders as &$order) {
                            $order['id'] = (int) $order['id'];
                            foreach (['total', 'subtotal', 'discount'] as $field) {
                                $order[$field] = (float) $order[$field];
                            }
                        }
                        unset($order);
                    }
                    json_response(['ok' => true, 'invoices' => $invoices, 'eligibleOrders' => $eligibleOrders]);
                }
                if ($view === 'reports') {
                    require_permission('reports.view');
                    $reportTimezone = new DateTimeZone('America/Lima');
                    $utcTimezone = new DateTimeZone('UTC');
                    $today = new DateTimeImmutable('today', $reportTimezone);
                    $from = $this->br_validate_date((string) (request()->query()['from'] ?? $today->modify('first day of this month')->format('Y-m-d')), 'inicial');
                    $to = $this->br_validate_date((string) (request()->query()['to'] ?? $today->format('Y-m-d')), 'final');
                    if ($to < $from) {
                        throw new BillingReportsProblem('La fecha final debe ser igual o posterior a la inicial.', 422);
                    }
                    if ((new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days > 366) {
                        throw new BillingReportsProblem('El periodo máximo del reporte es de 367 días.', 422);
                    }
                    // El usuario selecciona días locales de Perú; convertimos sus límites a UTC porque MySQL guarda fechas en UTC.
                    $start = (new DateTimeImmutable($from.' 00:00:00', $reportTimezone))->setTimezone($utcTimezone)->format('Y-m-d H:i:s');
                    $end = (new DateTimeImmutable($to.' 00:00:00', $reportTimezone))->modify('+1 day')->setTimezone($utcTimezone)->format('Y-m-d H:i:s');
                    $paymentsStmt = $pdo->prepare(
                        "SELECT COUNT(*) AS operation_count,
                    COALESCE(SUM(CASE WHEN payment_type='payment' THEN amount ELSE 0 END),0) AS gross_payments,
                    COALESCE(SUM(CASE WHEN payment_type='refund' THEN amount ELSE 0 END),0) AS gross_refunds,
                    COALESCE(SUM(CASE WHEN payment_type='payment' THEN amount ELSE -amount END),0) AS net_total
                 FROM payments WHERE created_at >= :start_at AND created_at < :end_at"
                    );
                    $paymentsStmt->execute(['start_at' => $start, 'end_at' => $end]);
                    $summary = $paymentsStmt->fetch() ?: [];
                    foreach (['operation_count'] as $field) {
                        $summary[$field] = (int) ($summary[$field] ?? 0);
                    }
                    foreach (['gross_payments', 'gross_refunds', 'net_total'] as $field) {
                        $summary[$field] = (float) ($summary[$field] ?? 0);
                    }
                    $ordersStmt = $pdo->prepare(
                        "SELECT COUNT(*) AS paid_orders, COALESCE(SUM(total),0) AS paid_order_total,
                        COALESCE(AVG(total),0) AS average_ticket
                 FROM orders WHERE payment_status='Pagado' AND paid_at >= :start_at AND paid_at < :end_at"
                    );
                    $ordersStmt->execute(['start_at' => $start, 'end_at' => $end]);
                    $ordersSummary = $ordersStmt->fetch() ?: [];
                    foreach (['paid_orders'] as $field) {
                        $ordersSummary[$field] = (int) ($ordersSummary[$field] ?? 0);
                    }
                    foreach (['paid_order_total', 'average_ticket'] as $field) {
                        $ordersSummary[$field] = (float) ($ordersSummary[$field] ?? 0);
                    }
                    $invoiceStmt = $pdo->prepare("SELECT COUNT(*) AS invoice_count, COALESCE(SUM(total),0) AS invoice_total FROM invoices WHERE status='issued' AND issued_at >= :start_at AND issued_at < :end_at");
                    $invoiceStmt->execute(['start_at' => $start, 'end_at' => $end]);
                    $invoiceSummary = $invoiceStmt->fetch() ?: [];
                    $invoiceSummary['invoice_count'] = (int) ($invoiceSummary['invoice_count'] ?? 0);
                    $invoiceSummary['invoice_total'] = (float) ($invoiceSummary['invoice_total'] ?? 0);
                    $dailyStmt = $pdo->prepare(
                        "SELECT DATE(DATE_SUB(created_at, INTERVAL 5 HOUR)) AS day,
                    COALESCE(SUM(CASE WHEN payment_type='payment' THEN amount ELSE -amount END),0) AS net_total,
                    COALESCE(SUM(CASE WHEN payment_type='payment' THEN amount ELSE 0 END),0) AS gross_payments,
                    COALESCE(SUM(CASE WHEN payment_type='refund' THEN amount ELSE 0 END),0) AS gross_refunds,
                    COUNT(*) AS operation_count
                 FROM payments WHERE created_at >= :start_at AND created_at < :end_at
                 GROUP BY DATE(DATE_SUB(created_at, INTERVAL 5 HOUR)) ORDER BY day ASC"
                    );
                    $dailyStmt->execute(['start_at' => $start, 'end_at' => $end]);
                    $daily = $dailyStmt->fetchAll();
                    foreach ($daily as &$row) {
                        $row['net_total'] = (float) $row['net_total'];
                        $row['gross_payments'] = (float) $row['gross_payments'];
                        $row['gross_refunds'] = (float) $row['gross_refunds'];
                        $row['operation_count'] = (int) $row['operation_count'];
                    }
                    unset($row);
                    $methodsStmt = $pdo->prepare(
                        "SELECT method,
                    COALESCE(SUM(CASE WHEN payment_type='payment' THEN amount ELSE 0 END),0) AS payments,
                    COALESCE(SUM(CASE WHEN payment_type='refund' THEN amount ELSE 0 END),0) AS refunds,
                    COALESCE(SUM(CASE WHEN payment_type='payment' THEN amount ELSE -amount END),0) AS net_total,
                    COUNT(*) AS operations
                 FROM payments WHERE created_at >= :start_at AND created_at < :end_at
                 GROUP BY method ORDER BY net_total DESC"
                    );
                    $methodsStmt->execute(['start_at' => $start, 'end_at' => $end]);
                    $methods = $methodsStmt->fetchAll();
                    foreach ($methods as &$row) {
                        foreach (['payments', 'refunds', 'net_total'] as $field) {
                            $row[$field] = (float) $row[$field];
                        } $row['operations'] = (int) $row['operations'];
                    }
                    unset($row);
                    $productsStmt = $pdo->prepare(
                        "SELECT oi.product_name, SUM(oi.quantity) AS units_sold, SUM(oi.line_total) AS sales_total
                 FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id
                 WHERE o.payment_status = 'Pagado' AND o.paid_at >= :start_at AND o.paid_at < :end_at
                 GROUP BY oi.product_name ORDER BY units_sold DESC, sales_total DESC LIMIT 10"
                    );
                    $productsStmt->execute(['start_at' => $start, 'end_at' => $end]);
                    $topProducts = $productsStmt->fetchAll();
                    foreach ($topProducts as &$row) {
                        $row['units_sold'] = (int) $row['units_sold'];
                        $row['sales_total'] = (float) $row['sales_total'];
                    }
                    unset($row);
                    $statusStmt = $pdo->prepare('SELECT status, COUNT(*) AS order_count, COALESCE(SUM(total),0) AS amount FROM orders WHERE created_at >= :start_at AND created_at < :end_at GROUP BY status ORDER BY order_count DESC');
                    $statusStmt->execute(['start_at' => $start, 'end_at' => $end]);
                    $orderStatuses = $statusStmt->fetchAll();
                    foreach ($orderStatuses as &$row) {
                        $row['order_count'] = (int) $row['order_count'];
                        $row['amount'] = (float) $row['amount'];
                    }
                    unset($row);
                    json_response([
                        'ok' => true,
                        'period' => ['from' => $from, 'to' => $to],
                        'summary' => array_merge($summary, $ordersSummary, $invoiceSummary),
                        'daily' => $daily,
                        'methods' => $methods,
                        'topProducts' => $topProducts,
                        'orderStatuses' => $orderStatuses,
                        'tax_note' => 'Los reportes son de gestión interna y no sustituyen los libros ni declaraciones tributarias oficiales.',
                    ]);
                }
                json_response(['ok' => false, 'message' => 'Vista de facturación o reportes no válida.'], 404);
            }
            require_method('POST');
            $body = json_body();
            require_csrf($body);
            $action = (string) ($body['action'] ?? '');
            if ($action === 'issue') {
                $actor = require_permission('invoices.manage');
                $orderId = filter_var($body['order_id'] ?? null, FILTER_VALIDATE_INT);
                $documentType = (string) ($body['document_type'] ?? '');
                $buyerName = trim((string) ($body['buyer_name'] ?? ''));
                $buyerDocumentType = (string) ($body['buyer_document_type'] ?? 'Sin documento');
                $buyerDocument = strtoupper(trim((string) ($body['buyer_document'] ?? '')));
                $buyerDocument = preg_replace('/[^A-Z0-9]/', '', $buyerDocument) ?? '';
                $buyerAddress = trim((string) ($body['buyer_address'] ?? ''));
                $notes = trim((string) ($body['notes'] ?? ''));
                $allowedDocTypes = ['DNI', 'RUC', 'CE', 'Pasaporte', 'Sin documento'];
                if (! $orderId || ! in_array($documentType, ['boleta', 'factura'], true)) {
                    throw new BillingReportsProblem('Selecciona un pedido y tipo de comprobante válidos.', 422);
                }
                if ($buyerName === '' || text_length($buyerName) > 160 || text_length($buyerAddress) > 255 || text_length($notes) > 500) {
                    throw new BillingReportsProblem('Revisa el nombre, domicilio y notas del comprador.', 422);
                }
                if (! in_array($buyerDocumentType, $allowedDocTypes, true) || text_length($buyerDocument) > 20) {
                    throw new BillingReportsProblem('El tipo o número de documento no es válido.', 422);
                }
                if ($documentType === 'factura' && ($buyerDocumentType !== 'RUC' || ! preg_match('/^\d{11}$/', $buyerDocument))) {
                    throw new BillingReportsProblem('Para la factura interna, selecciona RUC e ingresa 11 dígitos.', 422);
                }
                if ($buyerDocumentType === 'DNI' && $buyerDocument !== '' && ! preg_match('/^\d{8}$/', $buyerDocument)) {
                    throw new BillingReportsProblem('El DNI debe tener 8 dígitos.', 422);
                }
                if ($buyerDocumentType === 'RUC' && $buyerDocument !== '' && ! preg_match('/^\d{11}$/', $buyerDocument)) {
                    throw new BillingReportsProblem('El RUC debe tener 11 dígitos.', 422);
                }
                if ($buyerDocumentType !== 'Sin documento' && $buyerDocument === '') {
                    throw new BillingReportsProblem('Ingresa el número de documento del comprador.', 422);
                }
                if ($buyerDocumentType === 'Sin documento') {
                    $buyerDocument = '';
                }
                $pdo->beginTransaction();
                try {
                    $orderStmt = $pdo->prepare('SELECT id, order_number, customer_name, subtotal, discount, total, payment_status, status FROM orders WHERE id = :id FOR UPDATE');
                    $orderStmt->execute(['id' => $orderId]);
                    $order = $orderStmt->fetch();
                    if (! $order) {
                        throw new BillingReportsProblem('El pedido no existe.', 404);
                    }
                    if ($order['payment_status'] !== 'Pagado' || $order['status'] === 'Cancelado') {
                        throw new BillingReportsProblem('Solo se pueden emitir comprobantes internos para pedidos pagados y no cancelados.', 409);
                    }
                    $existing = $pdo->prepare('SELECT document_number FROM invoices WHERE order_id = :order_id LIMIT 1');
                    $existing->execute(['order_id' => $orderId]);
                    if ($existing->fetch()) {
                        throw new BillingReportsProblem('Este pedido ya tiene un comprobante. Consulta la lista antes de emitir otro.', 409);
                    }
                    $sequence = $pdo->prepare('SELECT series, current_number FROM invoice_sequences WHERE document_type = :type FOR UPDATE');
                    $sequence->execute(['type' => $documentType]);
                    $seq = $sequence->fetch();
                    if (! $seq) {
                        throw new RuntimeException('No existe la secuencia de comprobantes. Importa la migración del Paso 11.');
                    }
                    $nextNumber = (int) $seq['current_number'] + 1;
                    $series = (string) $seq['series'];
                    $documentNumber = $series.'-'.str_pad((string) $nextNumber, 8, '0', STR_PAD_LEFT);
                    $updateSequence = $pdo->prepare('UPDATE invoice_sequences SET current_number = :next WHERE document_type = :type');
                    $updateSequence->execute(['next' => $nextNumber, 'type' => $documentType]);
                    $total = round((float) $order['total'], 2);
                    $taxableAmount = round($total / 1.18, 2);
                    $igvAmount = round($total - $taxableAmount, 2);
                    $insert = $pdo->prepare(
                        "INSERT INTO invoices (order_id, document_type, series, sequential_number, document_number, buyer_name,
                    buyer_document_type, buyer_document, buyer_address, order_subtotal, order_discount,
                    taxable_amount, igv_rate, igv_amount, total, status, notes, issued_by, issued_at)
                 VALUES (:order_id, :type, :series, :seq, :doc, :buyer_name, :doc_type, :doc_number, :address,
                    :subtotal, :discount, :taxable, 18.00, :igv, :total, 'issued', :notes, :issuer, NOW())"
                    );
                    $insert->execute([
                        'order_id' => (int) $order['id'], 'type' => $documentType, 'series' => $series, 'seq' => $nextNumber,
                        'doc' => $documentNumber, 'buyer_name' => $buyerName, 'doc_type' => $buyerDocumentType,
                        'doc_number' => $buyerDocument !== '' ? $buyerDocument : null, 'address' => $buyerAddress !== '' ? $buyerAddress : null,
                        'subtotal' => number_format((float) $order['subtotal'], 2, '.', ''), 'discount' => number_format((float) $order['discount'], 2, '.', ''),
                        'taxable' => number_format($taxableAmount, 2, '.', ''), 'igv' => number_format($igvAmount, 2, '.', ''),
                        'total' => number_format($total, 2, '.', ''), 'notes' => $notes !== '' ? $notes : null, 'issuer' => (int) $actor['id'],
                    ]);
                    $invoiceId = (int) $pdo->lastInsertId();
                    $items = $pdo->prepare('INSERT INTO invoice_items (invoice_id, product_name, quantity, unit_price, line_total) SELECT :invoice_id, product_name, quantity, unit_price, line_total FROM order_items WHERE order_id = :order_id');
                    $items->execute(['invoice_id' => $invoiceId, 'order_id' => $orderId]);
                    $pdo->commit();
                    audit_log($pdo, (int) $actor['id'], 'invoice.issued_internal', ['invoice_id' => $invoiceId, 'order_id' => $orderId, 'document_number' => $documentNumber, 'type' => $documentType]);
                    json_response(['ok' => true, 'message' => 'Comprobante interno emitido. No es un documento electrónico SUNAT.', 'invoice_id' => $invoiceId, 'document_number' => $documentNumber]);
                } catch (Throwable $error) {
                    if ($error instanceof HttpResponseException) {
                        throw $error;
                    }
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $error;
                }
            }
            if ($action === 'void') {
                $actor = require_permission('invoices.manage');
                $invoiceId = filter_var($body['invoice_id'] ?? null, FILTER_VALIDATE_INT);
                $reason = trim((string) ($body['reason'] ?? ''));
                if (! $invoiceId || text_length($reason) < 5 || text_length($reason) > 255) {
                    throw new BillingReportsProblem('Indica un motivo de anulación de entre 5 y 255 caracteres.', 422);
                }
                $stmt = $pdo->prepare("UPDATE invoices SET status='voided', voided_by=:user_id, voided_at=NOW(), void_reason=:reason WHERE id=:id AND status='issued'");
                $stmt->execute(['user_id' => (int) $actor['id'], 'reason' => $reason, 'id' => $invoiceId]);
                if ($stmt->rowCount() < 1) {
                    throw new BillingReportsProblem('El comprobante no existe o ya está anulado.', 409);
                }
                audit_log($pdo, (int) $actor['id'], 'invoice.voided_internal', ['invoice_id' => $invoiceId, 'reason' => $reason]);
                json_response(['ok' => true, 'message' => 'Comprobante interno anulado. Esta acción no anula documentos en SUNAT.']);
            }
            json_response(['ok' => false, 'message' => 'Acción de facturación no válida.'], 404);
        } catch (BillingReportsProblem $error) {
            json_response(['ok' => false, 'message' => $error->getMessage()], $error->httpStatus);
        } catch (Throwable $error) {
            if ($error instanceof HttpResponseException) {
                throw $error;
            }
            error_log('[DESEGO billing/reports] '.$error->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo completar la operación. Revisa las tablas del Paso 11 y el registro del servidor.'], 500);
        }

        return response()->noContent();
    }

    protected function br_validate_date(string $value, string $label): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if (! $date || $date->format('Y-m-d') !== $value) {
            throw new BillingReportsProblem('La fecha '.$label.' no es válida.', 422);
        }

        return $date->format('Y-m-d');
    }

    protected function br_fetch_invoice_items(PDO $pdo, array &$invoices): void
    {
        if (! $invoices) {
            return;
        }
        $ids = array_values(array_map(static fn (array $invoice): int => (int) $invoice['id'], $invoices));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT invoice_id, product_name, quantity, unit_price, line_total FROM invoice_items WHERE invoice_id IN ($placeholders) ORDER BY id");
        $stmt->execute($ids);
        $byInvoice = [];
        foreach ($stmt->fetchAll() as $item) {
            $item['quantity'] = (int) $item['quantity'];
            foreach (['unit_price', 'line_total'] as $field) {
                $item[$field] = (float) $item[$field];
            }
            $byInvoice[(int) $item['invoice_id']][] = $item;
        }
        foreach ($invoices as &$invoice) {
            $invoice['id'] = (int) $invoice['id'];
            $invoice['order_id'] = (int) $invoice['order_id'];
            foreach (['order_subtotal', 'order_discount', 'taxable_amount', 'igv_rate', 'igv_amount', 'total'] as $field) {
                $invoice[$field] = (float) $invoice[$field];
            }
            $invoice['items'] = $byInvoice[$invoice['id']] ?? [];
        }
        unset($invoice);
    }
}
