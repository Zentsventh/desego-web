<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use PDOException;
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

class UsersController extends Controller
{
    public function __invoke(): Response
    {
        try {
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            $pdo = desego_db();
            if ($method === 'GET') {
                $actor = require_permission('users.view');
                $users = $pdo->query(
                    'SELECT u.id, u.name, u.email, u.status, u.last_login_at, u.created_at, r.id AS role_id, r.name AS role_name, r.code AS role_code
             FROM users u INNER JOIN roles r ON r.id = u.role_id ORDER BY u.created_at DESC'
                )->fetchAll();
                $roles = $pdo->query('SELECT id, code, name, description, is_system FROM roles ORDER BY is_system DESC, name')->fetchAll();
                foreach ($users as &$item) {
                    $item['id'] = (int) $item['id'];
                    $item['role_id'] = (int) $item['role_id'];
                    $item['is_self'] = ((int) $item['id'] === (int) $actor['id']);
                }
                unset($item);
                json_response(['ok' => true, 'users' => $users, 'roles' => $roles]);
            }
            require_method('POST');
            $body = json_body();
            require_csrf($body);
            $action = (string) ($body['action'] ?? '');
            if ($action === 'create') {
                $actor = require_permission('users.create');
                $name = trim((string) ($body['name'] ?? ''));
                $email = strtolower(trim((string) ($body['email'] ?? '')));
                $password = (string) ($body['password'] ?? '');
                $roleId = filter_var($body['role_id'] ?? null, FILTER_VALIDATE_INT);
                if (text_length($name) < 3 || text_length($name) > 100) {
                    json_response(['ok' => false, 'message' => 'El nombre debe tener entre 3 y 100 caracteres.'], 422);
                }
                if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
                    json_response(['ok' => false, 'message' => 'Correo electrónico no válido.'], 422);
                }
                if (strlen($password) < 10 || strlen($password) > 4096) {
                    json_response(['ok' => false, 'message' => 'La contraseña temporal debe tener al menos 10 caracteres.'], 422);
                }
                if (! $roleId) {
                    json_response(['ok' => false, 'message' => 'Selecciona un rol válido.'], 422);
                }
                $roleStmt = $pdo->prepare('SELECT id, code FROM roles WHERE id = :id LIMIT 1');
                $roleStmt->execute(['id' => $roleId]);
                $role = $roleStmt->fetch();
                if (! $role) {
                    json_response(['ok' => false, 'message' => 'El rol seleccionado no existe.'], 422);
                }
                if ($role['code'] === 'admin' && $actor['role_code'] !== 'admin') {
                    json_response(['ok' => false, 'message' => 'No puedes asignar el rol administrador.'], 403);
                }
                try {
                    $insert = $pdo->prepare('INSERT INTO users (name, email, password_hash, role_id, status, created_at, updated_at) VALUES (:name, :email, :password, :role, \'active\', NOW(), NOW())');
                    $insert->execute(['name' => $name, 'email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT), 'role' => $roleId]);
                } catch (PDOException $e) {
                    if ((string) $e->getCode() === '23000') {
                        json_response(['ok' => false, 'message' => 'Ese correo ya está registrado.'], 409);
                    }
                    throw $e;
                }
                $id = (int) $pdo->lastInsertId();
                audit_log($pdo, (int) $actor['id'], 'users.created', ['user_id' => $id, 'email' => $email, 'role' => $role['code']]);
                json_response(['ok' => true, 'message' => 'Usuario creado correctamente.'], 201);
            }
            if ($action === 'update') {
                $actor = require_permission('users.update');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $name = trim((string) ($body['name'] ?? ''));
                $email = strtolower(trim((string) ($body['email'] ?? '')));
                $roleId = filter_var($body['role_id'] ?? null, FILTER_VALIDATE_INT);
                if (! $id || text_length($name) < 3 || text_length($name) > 100 || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $roleId) {
                    json_response(['ok' => false, 'message' => 'Revisa los datos enviados.'], 422);
                }
                $roleStmt = $pdo->prepare('SELECT id, code FROM roles WHERE id = :id LIMIT 1');
                $roleStmt->execute(['id' => $roleId]);
                $role = $roleStmt->fetch();
                if (! $role) {
                    json_response(['ok' => false, 'message' => 'El rol seleccionado no existe.'], 422);
                }
                if ($role['code'] === 'admin' && $actor['role_code'] !== 'admin') {
                    json_response(['ok' => false, 'message' => 'No puedes asignar el rol administrador.'], 403);
                }
                $targetStmt = $pdo->prepare('SELECT id, role_id FROM users WHERE id = :id');
                $targetStmt->execute(['id' => $id]);
                $target = $targetStmt->fetch();
                if (! $target) {
                    json_response(['ok' => false, 'message' => 'El usuario no existe.'], 404);
                }
                if ((int) $id === (int) $actor['id'] && $role['code'] !== 'admin') {
                    json_response(['ok' => false, 'message' => 'No puedes quitarte el rol de administrador desde tu propia sesión.'], 422);
                }
                try {
                    $update = $pdo->prepare('UPDATE users SET name = :name, email = :email, role_id = :role_id, updated_at = NOW() WHERE id = :id');
                    $update->execute(['name' => $name, 'email' => $email, 'role_id' => $roleId, 'id' => $id]);
                } catch (PDOException $e) {
                    if ((string) $e->getCode() === '23000') {
                        json_response(['ok' => false, 'message' => 'Ese correo ya está en uso.'], 409);
                    }
                    throw $e;
                }
                audit_log($pdo, (int) $actor['id'], 'users.updated', ['user_id' => (int) $id, 'role' => $role['code']]);
                json_response(['ok' => true, 'message' => 'Usuario actualizado.']);
            }
            if ($action === 'status') {
                $actor = require_permission('users.deactivate');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $status = (string) ($body['status'] ?? '');
                if (! $id || ! in_array($status, ['active', 'inactive'], true)) {
                    json_response(['ok' => false, 'message' => 'Estado o usuario no válido.'], 422);
                }
                if ((int) $id === (int) $actor['id'] && $status !== 'active') {
                    json_response(['ok' => false, 'message' => 'No puedes desactivar tu propia cuenta.'], 422);
                }
                $targetStmt = $pdo->prepare('SELECT u.id, u.role_id, r.code AS role_code FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id');
                $targetStmt->execute(['id' => $id]);
                $target = $targetStmt->fetch();
                if (! $target) {
                    json_response(['ok' => false, 'message' => 'El usuario no existe.'], 404);
                }
                if ($status === 'inactive' && $target['role_code'] === 'admin') {
                    // Bloquea administradores activos durante la comprobación para evitar desactivar al último por carreras simultáneas.
                    $pdo->beginTransaction();
                    $lockedAdmins = $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'admin' AND u.status = 'active' FOR UPDATE")->fetchAll();
                    if (count($lockedAdmins) <= 1) {
                        $pdo->rollBack();
                        json_response(['ok' => false, 'message' => 'No puedes desactivar al último administrador activo.'], 422);
                    }
                    $update = $pdo->prepare('UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id');
                    $update->execute(['status' => $status, 'id' => $id]);
                    $pdo->commit();
                } else {
                    $update = $pdo->prepare('UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id');
                    $update->execute(['status' => $status, 'id' => $id]);
                }
                audit_log($pdo, (int) $actor['id'], 'users.status_changed', ['user_id' => (int) $id, 'status' => $status]);
                json_response(['ok' => true, 'message' => $status === 'active' ? 'Usuario activado.' : 'Usuario desactivado.']);
            }
            json_response(['ok' => false, 'message' => 'Acción de usuarios no válida.'], 404);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            error_log('[DESEGO users] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo completar la operación. Revisa la configuración del servidor.'], 500);
        }

        return response()->noContent();
    }
}
