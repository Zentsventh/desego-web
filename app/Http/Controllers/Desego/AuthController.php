<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Http\Controllers\Controller;
use App\Services\RestaurantPortal;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function App\Support\Desego\audit_log;
use function App\Support\Desego\clear_remember_cookie;
use function App\Support\Desego\current_user;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\establish_authenticated_session;
use function App\Support\Desego\issue_remember_token;
use function App\Support\Desego\json_body;
use function App\Support\Desego\json_response;
use function App\Support\Desego\require_csrf;
use function App\Support\Desego\require_method;
use function App\Support\Desego\restore_remembered_session;
use function App\Support\Desego\rotate_csrf_token;

class AuthController extends Controller
{
    public function __invoke(): Response
    {
        try {
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            $action = (string) (request()->query()['action'] ?? '');
            if ($method === 'GET' && $action === 'session') {
                // Forzar una prueba de conexión para no confundir una BD caída con una sesión cerrada.
                $pdo = desego_db();
                $remembered = false;
                if (! current_user()) {
                    $remembered = restore_remembered_session($pdo);
                }
                $user = current_user();
                json_response([
                    'ok' => true,
                    'authenticated' => $user !== null,
                    'user' => $user,
                    'csrfToken' => (string) session()->get('csrf_token'),
                ]);
            }
            require_method('POST');
            $body = json_body();
            require_csrf($body);
            $pdo = desego_db();
            if ($action === 'login') {
                $email = strtolower(trim((string) ($body['email'] ?? '')));
                $password = (string) ($body['password'] ?? '');
                if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || strlen($password) > 4096) {
                    json_response(['ok' => false, 'message' => 'El correo o la contraseña no son correctos.'], 422);
                }
                $stmt = $pdo->prepare(
                    'SELECT u.id, u.name, u.email, u.password_hash, u.status, u.failed_login_count, u.locked_until
             FROM users u WHERE u.email = :email LIMIT 1'
                );
                $stmt->execute(['email' => $email]);
                $row = $stmt->fetch();
                $invalid = ! $row || $row['status'] !== 'active';
                if ($row && ! empty($row['locked_until']) && strtotime((string) $row['locked_until']) > time()) {
                    $invalid = true;
                }
                if ($row && ! $invalid && ! password_verify($password, (string) $row['password_hash'])) {
                    $expiredLock = ! empty($row['locked_until']) && strtotime((string) $row['locked_until']) <= time();
                    $newCount = min(255, ($expiredLock ? 0 : (int) $row['failed_login_count']) + 1);
                    $lockedUntil = $newCount >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
                    $update = $pdo->prepare('UPDATE users SET failed_login_count = :count, locked_until = :locked WHERE id = :id');
                    $update->execute(['count' => $newCount, 'locked' => $lockedUntil, 'id' => $row['id']]);
                    audit_log($pdo, (int) $row['id'], 'auth.login_failed', ['email_hash' => hash('sha256', $email), 'attempts' => $newCount, 'reason' => 'invalid_password']);
                    $invalid = true;
                }
                if ($invalid) {
                    if (! $row) {
                        // Se registran también correos desconocidos, pero no se guarda su valor en texto claro.
                        audit_log($pdo, null, 'auth.login_failed', ['email_hash' => hash('sha256', $email), 'attempts' => 1, 'reason' => 'unknown_account']);
                    } elseif ($row['status'] !== 'active' || (! empty($row['locked_until']) && strtotime((string) $row['locked_until']) > time())) {
                        audit_log($pdo, (int) $row['id'], 'auth.login_blocked', ['reason' => $row['status'] !== 'active' ? 'inactive_account' : 'temporary_lock']);
                    }
                    json_response(['ok' => false, 'message' => 'El correo o la contraseña no son correctos, o la cuenta está temporalmente bloqueada.'], 401);
                }
                $update = $pdo->prepare('UPDATE users SET failed_login_count = 0, locked_until = NULL, last_login_at = NOW() WHERE id = :id');
                $update->execute(['id' => $row['id']]);
                establish_authenticated_session((int) $row['id'], (string) $row['password_hash']);
                if (! empty($body['remember'])) {
                    clear_remember_cookie($pdo);
                    issue_remember_token($pdo, (int) $row['id']);
                } else {
                    clear_remember_cookie($pdo);
                }
                $token = rotate_csrf_token();
                audit_log($pdo, (int) $row['id'], 'auth.login', ['email' => $email, 'remember' => ! empty($body['remember'])]);
                json_response(['ok' => true, 'message' => 'Sesión iniciada.', 'user' => current_user(), 'csrfToken' => $token]);
            }
            if ($action === 'register') {
                json_response(app(RestaurantPortal::class)->register($body), 201);
            }
            if ($action === 'logout') {
                $user = current_user();
                clear_remember_cookie($pdo);
                if ($user) {
                    audit_log($pdo, (int) $user['id'], 'auth.logout', []);
                }
                session()->invalidate();
                $token = rotate_csrf_token();
                json_response(['ok' => true, 'message' => 'Sesión cerrada.', 'csrfToken' => $token]);
            }
            json_response(['ok' => false, 'message' => 'Acción de autenticación no válida.'], 404);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException || $e instanceof ValidationException) {
                throw $e;
            }
            error_log('[DESEGO auth] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo completar la operación. Revisa la configuración de la base de datos y los registros de PHP.'], 500);
        }

        return response()->noContent();
    }
}
