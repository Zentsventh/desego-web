<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use PDO;
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

class RolesController extends Controller
{
    public function __invoke(): Response
    {
        try {
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            $pdo = desego_db();
            if ($method === 'GET') {
                require_permission('roles.manage');
                $roles = $pdo->query('SELECT id, code, name, description, is_system, created_at FROM roles ORDER BY is_system DESC, name')->fetchAll();
                $permissions = $pdo->query('SELECT id, code, name, module, description FROM permissions ORDER BY module, name')->fetchAll();
                $mapping = $pdo->query('SELECT role_id, permission_id FROM role_permissions')->fetchAll();
                $byRole = [];
                foreach ($mapping as $row) {
                    $byRole[(int) $row['role_id']][] = (int) $row['permission_id'];
                }
                foreach ($roles as &$role) {
                    $role['id'] = (int) $role['id'];
                    $role['is_system'] = (bool) $role['is_system'];
                    $role['permission_ids'] = $byRole[$role['id']] ?? [];
                }
                unset($role);
                foreach ($permissions as &$permission) {
                    $permission['id'] = (int) $permission['id'];
                }
                unset($permission);
                $history = $pdo->query("SELECT a.id, a.event_code, a.details, a.created_at, u.name AS actor_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE a.event_code IN ('roles.created', 'roles.updated') ORDER BY a.id DESC LIMIT 50")->fetchAll();
                json_response(['ok' => true, 'roles' => $roles, 'permissions' => $permissions, 'history' => $history]);
            }
            require_method('POST');
            $body = json_body();
            require_csrf($body);
            $actor = require_permission('roles.manage');
            $action = (string) ($body['action'] ?? '');
            $name = trim((string) ($body['name'] ?? ''));
            $description = trim((string) ($body['description'] ?? ''));
            $permissionCodes = $body['permissions'] ?? [];
            if (! is_array($permissionCodes)) {
                json_response(['ok' => false, 'message' => 'La lista de permisos no es válida.'], 422);
            }
            $permissionCodes = array_values(array_unique(array_filter(array_map('strval', $permissionCodes))));
            if (text_length($description) > 255) {
                json_response(['ok' => false, 'message' => 'La descripción admite hasta 255 caracteres.'], 422);
            }
            $knownPermissions = $pdo->query('SELECT code FROM permissions')->fetchAll(PDO::FETCH_COLUMN);
            if (array_diff($permissionCodes, $knownPermissions)) {
                json_response(['ok' => false, 'message' => 'Uno o más permisos seleccionados no existen.'], 422);
            }
            if ($action === 'create') {
                if (text_length($name) < 3 || text_length($name) > 80) {
                    json_response(['ok' => false, 'message' => 'El nombre del rol debe tener entre 3 y 80 caracteres.'], 422);
                }
                $code = $this->role_code_from_name($name);
                if ($code === '' || $code === 'admin') {
                    json_response(['ok' => false, 'message' => 'Ese nombre no puede utilizarse para crear un rol.'], 422);
                }
                $pdo->beginTransaction();
                try {
                    $insert = $pdo->prepare('INSERT INTO roles (code, name, description, is_system, created_at, updated_at) VALUES (:code, :name, :description, 0, NOW(), NOW())');
                    $insert->execute(['code' => $code, 'name' => $name, 'description' => $description ?: null]);
                    $roleId = (int) $pdo->lastInsertId();
                    $this->assign_role_permissions($pdo, $roleId, $permissionCodes);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($e instanceof HttpResponseException) {
                        throw $e;
                    }
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
                        json_response(['ok' => false, 'message' => 'Ya existe un rol con ese nombre.'], 409);
                    }
                    throw $e;
                }
                audit_log($pdo, (int) $actor['id'], 'roles.created', ['role_id' => $roleId, 'role_name' => $name, 'code' => $code, 'permissions_after' => $permissionCodes]);
                json_response(['ok' => true, 'message' => 'Rol creado.'], 201);
            }
            if ($action === 'update') {
                $roleId = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                if (! $roleId || text_length($name) < 3 || text_length($name) > 80) {
                    json_response(['ok' => false, 'message' => 'Datos del rol no válidos.'], 422);
                }
                $lookup = $pdo->prepare('SELECT id, code, is_system FROM roles WHERE id = :id');
                $lookup->execute(['id' => $roleId]);
                $role = $lookup->fetch();
                if (! $role) {
                    json_response(['ok' => false, 'message' => 'El rol no existe.'], 404);
                }
                if ($role['code'] === 'admin') {
                    json_response(['ok' => false, 'message' => 'El rol administrador protegido no se puede modificar desde esta pantalla.'], 422);
                }
                $pdo->beginTransaction();
                try {
                    $previous = $pdo->prepare('SELECT p.code FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = :role ORDER BY p.code');
                    $previous->execute(['role' => $roleId]);
                    $previousPermissions = $previous->fetchAll(PDO::FETCH_COLUMN);
                    $update = $pdo->prepare('UPDATE roles SET name = :name, description = :description, updated_at = NOW() WHERE id = :id');
                    $update->execute(['name' => $name, 'description' => $description ?: null, 'id' => $roleId]);
                    $this->assign_role_permissions($pdo, (int) $roleId, $permissionCodes);
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
                audit_log($pdo, (int) $actor['id'], 'roles.updated', ['role_id' => (int) $roleId, 'role_name' => $name, 'permissions_before' => $previousPermissions, 'permissions_after' => $permissionCodes]);
                json_response(['ok' => true, 'message' => 'Rol y permisos actualizados.']);
            }
            json_response(['ok' => false, 'message' => 'Acción de roles no válida.'], 404);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            error_log('[DESEGO roles] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo completar la operación. Revisa la configuración del servidor.'], 500);
        }

        return response()->noContent();
    }

    protected function role_code_from_name(string $name): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $code = strtolower((string) $ascii);
        $code = preg_replace('/[^a-z0-9]+/', '_', $code) ?? '';

        $code = trim($code, '_');

        return strlen($code) > 40
            ? rtrim(substr($code, 0, 31), '_').'_'.substr(hash('sha256', $code), 0, 8)
            : $code;
    }

    protected function assign_role_permissions(PDO $pdo, int $roleId, array $permissionCodes): void
    {
        // Los códigos desconocidos se ignoran; nunca se insertan permisos recibidos del cliente a ciegas.
        $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :role')->execute(['role' => $roleId]);
        if (! $permissionCodes) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($permissionCodes), '?'));
        $stmt = $pdo->prepare("SELECT id, code FROM permissions WHERE code IN ($placeholders)");
        $stmt->execute($permissionCodes);
        $permissionIds = array_map('intval', array_column($stmt->fetchAll(), 'id'));
        $insert = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:role, :permission)');
        foreach ($permissionIds as $permissionId) {
            $insert->execute(['role' => $roleId, 'permission' => $permissionId]);
        }
    }
}
