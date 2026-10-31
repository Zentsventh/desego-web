<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use PDO;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function App\Support\Desego\audit_log;
use function App\Support\Desego\clear_remember_cookie;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\json_body;
use function App\Support\Desego\json_response;
use function App\Support\Desego\require_csrf;
use function App\Support\Desego\require_method;
use function App\Support\Desego\rotate_csrf_token;

class PasswordResetController extends Controller
{
    private const RESET_GENERIC_MESSAGE = 'Si el correo corresponde a una cuenta activa de DESEGO, recibirás instrucciones para restablecer tu contraseña. Revisa también la carpeta de spam.';

    public function __invoke(): Response
    {
        try {
            require_method('POST');
            $body = json_body();
            require_csrf($body);
            $action = (string) (request()->query()['action'] ?? 'request');
            $pdo = desego_db();
            if ($action === 'request') {
                $email = strtolower(trim((string) ($body['email'] ?? '')));
                if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
                    json_response(['ok' => false, 'message' => 'Escribe un correo electrónico válido.'], 422);
                }
                $ipAddress = (string) (request()->server->all()['REMOTE_ADDR'] ?? 'unknown');
                $ipHash = hash('sha256', $ipAddress);
                $emailHash = hash('sha256', $email);
                $rateIp = $pdo->prepare('SELECT COUNT(*) FROM password_reset_tokens WHERE request_ip_hash = :ip_hash AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)');
                $rateIp->execute(['ip_hash' => $ipHash]);
                $rateEmail = $pdo->prepare('SELECT COUNT(*) FROM password_reset_tokens WHERE email_hash = :email_hash AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)');
                $rateEmail->execute(['email_hash' => $emailHash]);
                if ((int) $rateIp->fetchColumn() >= 8 || (int) $rateEmail->fetchColumn() >= 3) {
                    json_response(['ok' => true, 'message' => self::RESET_GENERIC_MESSAGE]);
                }
                $findUser = $pdo->prepare("SELECT id, name, email FROM users WHERE email = :email AND status = 'active' LIMIT 1");
                $findUser->execute(['email' => $email]);
                $user = $findUser->fetch();
                $token = null;
                $tokenHash = null;
                $expiresAt = null;
                if ($user) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);
                    $expiresAt = gmdate('Y-m-d H:i:s', time() + 1800);
                    // El nuevo enlace invalida enlaces anteriores aún no usados para este usuario.
                    $invalidate = $pdo->prepare('UPDATE password_reset_tokens SET used_at = UTC_TIMESTAMP() WHERE user_id = :user_id AND used_at IS NULL');
                    $invalidate->execute(['user_id' => $user['id']]);
                }
                $insert = $pdo->prepare(
                    'INSERT INTO password_reset_tokens (user_id, email_hash, token_hash, request_ip_hash, expires_at, used_at, created_at)
             VALUES (:user_id, :email_hash, :token_hash, :ip_hash, :expires_at, NULL, UTC_TIMESTAMP())'
                );
                $insert->execute([
                    'user_id' => $user ? (int) $user['id'] : null,
                    'email_hash' => $emailHash,
                    'token_hash' => $tokenHash,
                    'ip_hash' => $ipHash,
                    'expires_at' => $expiresAt,
                ]);
                $response = ['ok' => true, 'message' => self::RESET_GENERIC_MESSAGE];
                if ($user && $token !== null) {
                    try {
                        $mailConfig = config('desego.mail');
                        $baseUrl = $this->reset_base_url($mailConfig);
                        $resetUrl = $baseUrl.'/restablecer-contrasena?token='.rawurlencode($token);
                        $sent = $this->send_reset_email((string) $user['email'], (string) $user['name'], $resetUrl, $mailConfig);
                        if (! $sent) {
                            // No registra el token en logs; el enlace de prueba se limita a localhost.
                            error_log('[DESEGO password reset] El envío de correo falló; revisa config/mail.php y la configuración de correo del servidor.');
                            if (! empty($mailConfig['allow_local_debug_link']) && $this->reset_is_local_request()) {
                                $response['debugUrl'] = $resetUrl;
                            }
                        }
                    } catch (Throwable $e) {
                        if ($e instanceof HttpResponseException) {
                            throw $e;
                        }
                        error_log('[DESEGO password reset] Error al preparar correo: '.$e->getMessage());
                        $mailConfig = is_array($mailConfig ?? null) ? $mailConfig : [];
                        if (! empty($mailConfig['allow_local_debug_link']) && $this->reset_is_local_request() && $token !== null) {
                            $response['debugUrl'] = $this->reset_base_url($mailConfig).'/restablecer-contrasena?token='.rawurlencode($token);
                        }
                    }
                    audit_log($pdo, (int) $user['id'], 'auth.password_reset_requested', ['delivery_attempted' => true]);
                }
                json_response($response);
            }
            if ($action === 'complete') {
                $token = strtolower(trim((string) ($body['token'] ?? '')));
                $password = (string) ($body['password'] ?? '');
                $confirm = (string) ($body['confirmPassword'] ?? '');
                if (! preg_match('/\A[a-f0-9]{64}\z/', $token)) {
                    json_response(['ok' => false, 'message' => 'El enlace no es válido o ha caducado. Solicita uno nuevo.'], 422);
                }
                if (strlen($password) < 12 || strlen($password) > 4096) {
                    json_response(['ok' => false, 'message' => 'La contraseña debe tener al menos 12 caracteres.'], 422);
                }
                if (! hash_equals($password, $confirm)) {
                    json_response(['ok' => false, 'message' => 'Las contraseñas no coinciden.'], 422);
                }
                $tokenHash = hash('sha256', $token);
                $pdo->beginTransaction();
                $find = $pdo->prepare(
                    'SELECT pr.id, pr.user_id, u.status
             FROM password_reset_tokens pr
             INNER JOIN users u ON u.id = pr.user_id
             WHERE pr.token_hash = :token_hash
               AND pr.used_at IS NULL
               AND pr.expires_at > UTC_TIMESTAMP()
               AND u.status = \'active\'
             LIMIT 1 FOR UPDATE'
                );
                $find->execute(['token_hash' => $tokenHash]);
                $record = $find->fetch();
                if (! $record) {
                    $pdo->rollBack();
                    json_response(['ok' => false, 'message' => 'El enlace no es válido, ya fue utilizado o ha caducado. Solicita uno nuevo.'], 422);
                }
                $userId = (int) $record['user_id'];
                $update = $pdo->prepare('UPDATE users SET password_hash = :password_hash, failed_login_count = 0, locked_until = NULL, updated_at = UTC_TIMESTAMP() WHERE id = :id');
                $update->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId]);
                $revokeRemember = $pdo->prepare('UPDATE remember_tokens SET revoked_at = UTC_TIMESTAMP() WHERE user_id = :user_id AND revoked_at IS NULL');
                $revokeRemember->execute(['user_id' => $userId]);
                $consume = $pdo->prepare('UPDATE password_reset_tokens SET used_at = UTC_TIMESTAMP() WHERE user_id = :user_id AND used_at IS NULL');
                $consume->execute(['user_id' => $userId]);
                audit_log($pdo, $userId, 'auth.password_reset_completed', []);
                $pdo->commit();
                clear_remember_cookie($pdo);
                session()->invalidate();
                $csrfToken = rotate_csrf_token();
                json_response(['ok' => true, 'message' => 'Contraseña actualizada. Ya puedes iniciar sesión con tu nueva contraseña.', 'csrfToken' => $csrfToken]);
            }
            json_response(['ok' => false, 'message' => 'Acción de recuperación no válida.'], 400);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[DESEGO password reset] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo procesar la recuperación. Verifica la configuración de la base de datos e inténtalo otra vez.'], 500);
        }

        return response()->noContent();
    }

    protected function reset_base_url(array $mailConfig): string
    {
        $configured = trim((string) ($mailConfig['app_url'] ?? ''));
        if ($configured !== '') {
            $parts = parse_url($configured);
            if (! is_array($parts) || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
                throw new RuntimeException('La URL de la aplicación en config/mail.php no es válida.');
            }

            return rtrim($configured, '/');
        }
        $hostHeader = (string) (request()->server->all()['HTTP_HOST'] ?? '');
        if (! preg_match('/\A(?:[a-z0-9.-]+|\[[a-f0-9:]+\])(?::[0-9]{1,5})?\z/i', $hostHeader)) {
            throw new RuntimeException('Configura DESEGO_APP_URL con la URL pública de tu instalación.');
        }
        $https = ! empty(request()->server->all()['HTTPS']) && strtolower((string) request()->server->all()['HTTPS']) !== 'off';
        $scheme = $https ? 'https' : 'http';
        $scriptName = (string) (request()->server->all()['SCRIPT_NAME'] ?? '/api/password-reset.php');
        $basePath = dirname(dirname($scriptName));
        $basePath = $basePath === '/' || $basePath === '\\' || $basePath === '.' ? '' : '/'.trim(str_replace('\\', '/', $basePath), '/');

        return $scheme.'://'.$hostHeader.$basePath;
    }

    protected function reset_is_local_request(): bool
    {
        $remote = (string) (request()->server->all()['REMOTE_ADDR'] ?? '');
        $hostHeader = (string) (request()->server->all()['HTTP_HOST'] ?? '');
        $host = strtolower((string) parse_url('http://'.$hostHeader, PHP_URL_HOST));

        return in_array($remote, ['127.0.0.1', '::1'], true) && in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    protected function send_reset_email(string $email, string $name, string $resetUrl, array $mailConfig): bool
    {
        $fromEmail = trim((string) ($mailConfig['from_email'] ?? ''));
        if (! filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || str_contains($fromEmail, "\r") || str_contains($fromEmail, "\n")) {
            error_log('[DESEGO password reset] Configura una dirección válida DESEGO_MAIL_FROM para enviar correos.');

            return false;
        }
        $fromName = (string) ($mailConfig['from_name'] ?? 'DESEGO CEVICHERÍA');
        $fromName = preg_replace('/[\r\n]+/', ' ', $fromName) ?: 'DESEGO CEVICHERÍA';
        $encodedName = '=?UTF-8?B?'.base64_encode($fromName).'?=';
        $subject = 'Restablecer tu contraseña de DESEGO';
        $safeName = trim(preg_replace('/[\r\n]+/', ' ', $name) ?: 'usuario');
        $message = "Hola {$safeName},\n\n".
            "Recibimos una solicitud para cambiar la contraseña de tu cuenta de DESEGO CEVICHERÍA.\n\n".
            "Para elegir una contraseña nueva, abre este enlace (vence en 30 minutos y solo funciona una vez):\n\n".
            $resetUrl."\n\n".
            "Si no solicitaste el cambio, ignora este correo. Tu contraseña actual no se modificará.\n\n".
            "DESEGO CEVICHERÍA\n";
        try {
            Mail::raw($message, function (Message $mail) use ($email, $subject): void {
                $mail->to($email)->subject($subject);
            });

            return true;
        } catch (Throwable $error) {
            report($error);

            return false;
        }
    }
}
