<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function App\Support\Desego\audit_log;
use function App\Support\Desego\clear_remember_cookie;
use function App\Support\Desego\current_user;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\json_body;
use function App\Support\Desego\json_response;
use function App\Support\Desego\require_auth;
use function App\Support\Desego\require_csrf;
use function App\Support\Desego\require_method;
use function App\Support\Desego\require_permission;
use function App\Support\Desego\rotate_csrf_token;
use function App\Support\Desego\text_length;

class ProfileController extends Controller
{
    public function __invoke(): Response
    {
        try {
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            $action = (string) (request()->query()['action'] ?? '');
            if ($method === 'GET' && $action === 'preferences') {
                $user = require_auth();
                $pdo = desego_db();
                $seed = $pdo->prepare('INSERT IGNORE INTO user_preferences (user_id) VALUES (:id)');
                $seed->execute(['id' => $user['id']]);
                $stmt = $pdo->prepare('SELECT theme_tone, show_notification_badge, desktop_notifications, sound_notifications, default_view FROM user_preferences WHERE user_id = :id LIMIT 1');
                $stmt->execute(['id' => $user['id']]);
                $prefs = $stmt->fetch() ?: [];
                $prefs = [
                    'theme_tone' => (int) ($prefs['theme_tone'] ?? 72),
                    'show_notification_badge' => (bool) ($prefs['show_notification_badge'] ?? true),
                    'desktop_notifications' => (bool) ($prefs['desktop_notifications'] ?? false),
                    'sound_notifications' => (bool) ($prefs['sound_notifications'] ?? false),
                    'default_view' => (string) ($prefs['default_view'] ?? 'home'),
                ];
                json_response(['ok' => true, 'preferences' => $prefs]);
            }
            require_method('POST');
            $body = json_body();
            require_csrf($body);
            $pdo = desego_db();
            $action = (string) ($body['action'] ?? $action ?? 'profile.update');
            $user = require_auth();
            $userId = (int) $user['id'];
            if ($action === 'profile.update' || $action === '') {
                $user = require_permission('profile.update');
                $name = trim((string) ($body['name'] ?? ''));
                $email = strtolower(trim((string) ($body['email'] ?? '')));
                if (text_length($name) < 3 || text_length($name) > 100) {
                    json_response(['ok' => false, 'message' => 'El nombre debe tener entre 3 y 100 caracteres.'], 422);
                }
                if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
                    json_response(['ok' => false, 'message' => 'Ingresa un correo electrónico válido.'], 422);
                }
                $stmt = $pdo->prepare('UPDATE users SET name = :name, email = :email, updated_at = NOW() WHERE id = :id');
                try {
                    $stmt->execute(['name' => $name, 'email' => $email, 'id' => $user['id']]);
                } catch (PDOException $e) {
                    if ((string) $e->getCode() === '23000') {
                        json_response(['ok' => false, 'message' => 'Ese correo ya está registrado en otra cuenta.'], 409);
                    }
                    throw $e;
                }
                audit_log($pdo, (int) $user['id'], 'profile.updated', ['email' => $email]);
                json_response(['ok' => true, 'message' => 'Perfil actualizado.', 'user' => current_user()]);
            }
            if ($action === 'preferences.update') {
                $tone = filter_var($body['theme_tone'] ?? null, FILTER_VALIDATE_INT);
                $defaultView = (string) ($body['default_view'] ?? 'home');
                $allowedViews = ['home', 'orders', 'menu', 'reservations', 'profile'];
                if ($tone === false || $tone < 0 || $tone > 100) {
                    json_response(['ok' => false, 'message' => 'El tono del fondo debe estar entre 0 y 100.'], 422);
                }
                if (! in_array($defaultView, $allowedViews, true)) {
                    json_response(['ok' => false, 'message' => 'Selecciona una pantalla inicial válida.'], 422);
                }
                $badge = ! empty($body['show_notification_badge']) ? 1 : 0;
                $desktop = ! empty($body['desktop_notifications']) ? 1 : 0;
                $sound = ! empty($body['sound_notifications']) ? 1 : 0;
                $stmt = $pdo->prepare(
                    'INSERT INTO user_preferences (user_id, theme_tone, show_notification_badge, desktop_notifications, sound_notifications, default_view, created_at, updated_at)
             VALUES (:user_id, :theme_tone, :badge, :desktop, :sound, :default_view, NOW(), NOW())
             ON DUPLICATE KEY UPDATE theme_tone = VALUES(theme_tone), show_notification_badge = VALUES(show_notification_badge), desktop_notifications = VALUES(desktop_notifications), sound_notifications = VALUES(sound_notifications), default_view = VALUES(default_view), updated_at = NOW()'
                );
                $stmt->execute(['user_id' => $userId, 'theme_tone' => $tone, 'badge' => $badge, 'desktop' => $desktop, 'sound' => $sound, 'default_view' => $defaultView]);
                $prefs = ['theme_tone' => $tone, 'show_notification_badge' => (bool) $badge, 'desktop_notifications' => (bool) $desktop, 'sound_notifications' => (bool) $sound, 'default_view' => $defaultView];
                audit_log($pdo, $userId, 'profile.preferences_updated', $prefs);
                json_response(['ok' => true, 'message' => 'Tus preferencias se guardaron en el servidor.', 'preferences' => $prefs]);
            }
            if ($action === 'password.change') {
                $currentPassword = (string) ($body['current_password'] ?? '');
                $newPassword = (string) ($body['new_password'] ?? '');
                $confirmPassword = (string) ($body['confirm_password'] ?? '');
                if ($currentPassword === '' || strlen($currentPassword) > 4096) {
                    json_response(['ok' => false, 'message' => 'Introduce tu contraseña actual.'], 422);
                }
                if (strlen($newPassword) < 12 || strlen($newPassword) > 4096) {
                    json_response(['ok' => false, 'message' => 'La nueva contraseña debe tener al menos 12 caracteres.'], 422);
                }
                if (! hash_equals($newPassword, $confirmPassword)) {
                    json_response(['ok' => false, 'message' => 'La confirmación no coincide con la nueva contraseña.'], 422);
                }
                $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id AND status = \'active\' LIMIT 1');
                $stmt->execute(['id' => $userId]);
                $hash = (string) ($stmt->fetchColumn() ?: '');
                if ($hash === '' || ! password_verify($currentPassword, $hash)) {
                    audit_log($pdo, $userId, 'profile.password_change_failed', ['reason' => 'current_password_mismatch']);
                    json_response(['ok' => false, 'message' => 'La contraseña actual no es correcta.'], 422);
                }
                if (password_verify($newPassword, $hash)) {
                    json_response(['ok' => false, 'message' => 'La nueva contraseña debe ser diferente de la actual.'], 422);
                }
                $pdo->beginTransaction();
                try {
                    $update = $pdo->prepare('UPDATE users SET password_hash = :hash, failed_login_count = 0, locked_until = NULL, updated_at = NOW() WHERE id = :id');
                    $update->execute(['hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $userId]);
                    $revoke = $pdo->prepare('UPDATE remember_tokens SET revoked_at = NOW() WHERE user_id = :id AND revoked_at IS NULL');
                    $revoke->execute(['id' => $userId]);
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
                clear_remember_cookie($pdo);
                session()->regenerate(true);
                rotate_csrf_token();
                audit_log($pdo, $userId, 'profile.password_changed', ['remember_tokens_revoked' => true]);
                json_response(['ok' => true, 'message' => 'Contraseña actualizada. Se revocaron los accesos recordados; tu sesión actual continúa abierta.', 'csrfToken' => (string) session()->get('csrf_token')]);
            }
            json_response(['ok' => false, 'message' => 'Acción de perfil desconocida.'], 404);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            error_log('[DESEGO profile] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo completar la operación. Revisa la configuración del servidor.'], 500);
        }

        return response()->noContent();
    }
}
