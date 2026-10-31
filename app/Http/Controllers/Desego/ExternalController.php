<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function App\Support\Desego\desego_db;
use function App\Support\Desego\desego_header;
use function App\Support\Desego\json_response;

class ExternalController extends Controller
{
    public function __invoke(): Response
    {
        desego_header('Cache-Control: no-store, max-age=0');
        desego_header('Vary: Authorization');
        try {
            if (strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
                desego_header('Allow: GET');
                json_response(['ok' => false, 'message' => 'Método no permitido.'], 405);
            }
            $header = (string) (request()->server->all()['HTTP_AUTHORIZATION'] ?? '');
            if (! preg_match('/^Bearer\s+(dsg_live_[A-Za-z0-9_-]{30,})$/', $header, $matches)) {
                json_response(['ok' => false, 'message' => 'Envía un token válido en Authorization: Bearer.'], 401);
            }
            $rawToken = $matches[1];
            $hash = hash('sha256', $rawToken);
            $stmt = desego_db()->prepare("SELECT id,scopes_json,expires_at FROM api_access_tokens WHERE token_hash=:hash AND status='active' LIMIT 1");
            $stmt->execute(['hash' => $hash]);
            $token = $stmt->fetch();
            if (! $token || ($token['expires_at'] !== null && strtotime((string) $token['expires_at'].' UTC') <= time())) {
                json_response(['ok' => false, 'message' => 'Token revocado, inexistente o caducado.'], 401);
            }
            $pdo = desego_db();
            $scopes = json_decode((string) $token['scopes_json'], true);
            if (! is_array($scopes)) {
                $scopes = [];
            }
            // Fixed-window throttling is stored in MySQL so it works across PHP workers.
            $limit = 120;
            try {
                $setting = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='external_api_requests_per_minute' LIMIT 1")->fetchColumn();
                if ($setting !== false) {
                    $limit = max(10, min(600, (int) $setting));
                }
            } catch (Throwable $ignored) {
                if ($ignored instanceof HttpResponseException) {
                    throw $ignored;
                }
                // The step-16 migration is the source of the default; absent settings keep safe defaults.
            }
            $windowStart = gmdate('Y-m-d H:i:00');
            $rate = $pdo->prepare('INSERT INTO api_rate_limit_windows (token_id, window_start, request_count) VALUES (:token_id, :window_start, 1) ON DUPLICATE KEY UPDATE request_count = request_count + 1, updated_at = UTC_TIMESTAMP()');
            $rate->execute(['token_id' => (int) $token['id'], 'window_start' => $windowStart]);
            $countStmt = $pdo->prepare('SELECT request_count FROM api_rate_limit_windows WHERE token_id = :token_id AND window_start = :window_start');
            $countStmt->execute(['token_id' => (int) $token['id'], 'window_start' => $windowStart]);
            $requestCount = (int) $countStmt->fetchColumn();
            if ($requestCount > $limit) {
                desego_header('Retry-After: 60');
                json_response(['ok' => false, 'message' => 'Límite de solicitudes excedido. Inténtalo de nuevo en un minuto.', 'retry_after' => 60], 429);
            }
            $resource = (string) (request()->query()['resource'] ?? '');
            if ($resource === 'products') {
                if (! in_array('products:read', $scopes, true)) {
                    json_response(['ok' => false, 'message' => 'El token no tiene el alcance products:read.'], 403);
                }
                $rows = $pdo->query("SELECT p.id,p.slug,p.name,p.description,p.price,p.emoji,c.name AS category FROM products p INNER JOIN categories c ON c.id=p.category_id WHERE p.status='available' ORDER BY c.name,p.name LIMIT 500")->fetchAll();
                foreach ($rows as &$r) {
                    $r['id'] = (int) $r['id'];
                    $r['price'] = (float) $r['price'];
                }unset($r);
                $pdo->prepare('UPDATE api_access_tokens SET last_used_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => (int) $token['id']]);
                json_response(['ok' => true, 'resource' => 'products', 'count' => count($rows), 'data' => $rows]);
            }
            if ($resource === 'orders') {
                if (! in_array('orders:read', $scopes, true)) {
                    json_response(['ok' => false, 'message' => 'El token no tiene el alcance orders:read.'], 403);
                }
                $limit = max(1, min(100, (int) (request()->query()['limit'] ?? 50)));
                $rows = $pdo->query("SELECT id,order_number,order_type,status,payment_status,subtotal,discount,total,created_at,paid_at FROM orders ORDER BY id DESC LIMIT {$limit}")->fetchAll();
                foreach ($rows as &$r) {
                    $r['id'] = (int) $r['id'];
                    foreach (['subtotal', 'discount', 'total'] as $k) {
                        $r[$k] = (float) $r[$k];
                    }
                }unset($r);
                $pdo->prepare('UPDATE api_access_tokens SET last_used_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => (int) $token['id']]);
                json_response(['ok' => true, 'resource' => 'orders', 'count' => count($rows), 'data' => $rows]);
            }
            json_response(['ok' => false, 'message' => 'Recurso no válido. Usa resource=products u orders.'], 404);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            error_log('[DESEGO external API] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'Error interno de API.'], 500);
        }

        return response()->noContent();
    }
}
