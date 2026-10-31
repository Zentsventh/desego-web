<?php

declare(strict_types=1);

namespace App\Support\Desego;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

function desego_db(): PDO
{
    $pdo = DB::connection()->getPdo();
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $pdo;
}

function text_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    $count = preg_match_all('/./us', $value, $matches);

    return $count === false ? strlen($value) : $count;
}

function json_response(array $payload, int $status = 200): never
{
    if ($status >= 400) {
        $connection = DB::connection();
        if ($connection->getRawPdo() instanceof PDO && $connection->getRawPdo()->inTransaction()) {
            $connection->getRawPdo()->rollBack();
        }
    }
    throw new HttpResponseException(response()->json($payload, $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function json_body(): array
{
    $raw = request()->getContent();
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (! is_array($data)) {
        json_response(['ok' => false, 'message' => 'El cuerpo de la solicitud no es JSON válido.'], 400);
    }

    return $data;
}

function require_method(string ...$methods): void
{
    $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
    if (! in_array($method, $methods, true)) {
        desego_header('Allow: '.implode(', ', $methods));
        json_response(['ok' => false, 'message' => 'Método no permitido.'], 405);
    }
}

function require_csrf(array $body = []): void
{
    $sent = (string) (request()->server->all()['HTTP_X_CSRF_TOKEN'] ?? ($body['_csrf'] ?? ''));
    $actual = (string) (session()->get('csrf_token') ?? '');
    if ($sent === '' || $actual === '' || ! hash_equals($actual, $sent)) {
        json_response(['ok' => false, 'message' => 'La sesión de seguridad expiró. Recarga la página e inténtalo otra vez.'], 419);
    }
}

function current_user(bool $refresh = false): ?array
{
    $userId = filter_var(session()->get('user_id') ?? null, FILTER_VALIDATE_INT);
    if (! $userId) {
        return null;
    }

    $cacheKey = $userId.':'.(string) session()->get('auth_password_fingerprint', '');
    if (! $refresh && request()->attributes->get('_desego_user_key') === $cacheKey) {
        return request()->attributes->get('_desego_user_val');
    }

    try {
        $pdo = desego_db();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email, u.password_hash, u.status, u.role_id, r.code AS role_code, r.name AS role_name
             FROM users u INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        if (! $user || $user['status'] !== 'active' || ! hash_equals(
            hash_hmac('sha256', (string) $user['password_hash'], (string) config('app.key')),
            (string) session()->get('auth_password_fingerprint', '')
        )) {
            session()->forget(['user_id', 'auth_password_fingerprint', 'storefront_customer_id', 'storefront_last_order_id']);

            return null;
        }

        $permStmt = $pdo->prepare(
            'SELECT p.code FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = :role_id ORDER BY p.code'
        );
        $permStmt->execute(['role_id' => $user['role_id']]);
        $user['permissions'] = array_column($permStmt->fetchAll(), 'code');
        $user['id'] = (int) $user['id'];
        $user['role_id'] = (int) $user['role_id'];
        unset($user['status'], $user['password_hash']);
        request()->attributes->set('_desego_user_key', $cacheKey);
        request()->attributes->set('_desego_user_val', $user);

        return $user;
    } catch (Throwable $e) {
        if ($e instanceof HttpResponseException) {
            throw $e;
        }
        error_log('[DESEGO current_user] '.$e->getMessage());

        return null;
    }
}

function establish_authenticated_session(int $userId, string $passwordHash): void
{
    session()->regenerate(true);
    session()->forget(['storefront_customer_id', 'storefront_last_order_id']);
    session()->put('user_id', $userId);
    session()->put('auth_password_fingerprint', hash_hmac('sha256', $passwordHash, (string) config('app.key')));
}

function require_auth(): array
{
    $user = current_user();
    if (! $user) {
        json_response(['ok' => false, 'message' => 'Debes iniciar sesión para continuar.'], 401);
    }

    return $user;
}

function require_permission(string $permission): array
{
    $user = require_auth();
    if (! in_array($permission, $user['permissions'] ?? [], true)) {
        json_response(['ok' => false, 'message' => 'No tienes permiso para realizar esta acción.'], 403);
    }

    return $user;
}

function require_any_permission(array $permissions): array
{
    $user = require_auth();
    foreach ($permissions as $permission) {
        if (in_array($permission, $user['permissions'] ?? [], true)) {
            return $user;
        }
    }
    json_response(['ok' => false, 'message' => 'No tienes permiso para realizar esta acción.'], 403);
}

function audit_log(PDO $pdo, ?int $userId, string $event, array $details = []): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, event_code, details, ip_address, created_at)
             VALUES (:user_id, :event_code, :details, :ip_address, NOW())'
        );
        $stmt->execute([
            'user_id' => $userId,
            'event_code' => $event,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip_address' => substr((string) (request()->server->all()['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
    } catch (Throwable $e) {
        if ($e instanceof HttpResponseException) {
            throw $e;
        }
        error_log('[DESEGO audit] '.$e->getMessage());
    }
}

function set_remember_cookie(string $selector, string $validator, int $expiresAt): void
{
    $https = isset(request()->server->all()['HTTPS']) && request()->server->all()['HTTPS'] !== '' && strtolower((string) request()->server->all()['HTTPS']) !== 'off';
    desego_cookie('DESEGO_REMEMBER', $selector.':'.$validator, [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clear_remember_cookie(PDO $pdo): void
{
    $cookie = (string) (request()->cookies->all()['DESEGO_REMEMBER'] ?? '');
    if (preg_match('/^([a-f0-9]{24}):[a-f0-9]{64}$/', $cookie, $match)) {
        $stmt = $pdo->prepare('UPDATE remember_tokens SET revoked_at = NOW() WHERE selector = :selector AND revoked_at IS NULL');
        $stmt->execute(['selector' => $match[1]]);
    }
    $https = isset(request()->server->all()['HTTPS']) && request()->server->all()['HTTPS'] !== '' && strtolower((string) request()->server->all()['HTTPS']) !== 'off';
    desego_cookie('DESEGO_REMEMBER', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    request()->cookies->remove('DESEGO_REMEMBER');
}

function issue_remember_token(PDO $pdo, int $userId): void
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expires = time() + (14 * 24 * 60 * 60);
    $stmt = $pdo->prepare(
        'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at, created_at, user_agent)
         VALUES (:user_id, :selector, :token_hash, :expires_at, NOW(), :user_agent)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'selector' => $selector,
        'token_hash' => hash('sha256', $validator),
        'expires_at' => date('Y-m-d H:i:s', $expires),
        'user_agent' => substr((string) (request()->server->all()['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
    set_remember_cookie($selector, $validator, $expires);
}

function restore_remembered_session(PDO $pdo): bool
{
    if (! empty(session()->get('user_id'))) {
        return false;
    }
    $cookie = (string) (request()->cookies->all()['DESEGO_REMEMBER'] ?? '');
    if (! preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $cookie, $match)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT rt.id, rt.user_id, rt.token_hash, rt.expires_at, u.password_hash
         FROM remember_tokens rt INNER JOIN users u ON u.id = rt.user_id
         WHERE rt.selector = :selector AND rt.revoked_at IS NULL AND rt.expires_at > NOW() AND u.status = \'active\'
         LIMIT 1 FOR UPDATE'
    );
    $pdo->beginTransaction();
    try {
        $stmt->execute(['selector' => $match[1]]);
        $row = $stmt->fetch();
        if (! $row || ! hash_equals((string) $row['token_hash'], hash('sha256', $match[2]))) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            clear_remember_cookie($pdo);

            return false;
        }

        $revoke = $pdo->prepare('UPDATE remember_tokens SET revoked_at = NOW(), last_used_at = NOW() WHERE id = :id');
        $revoke->execute(['id' => $row['id']]);
        establish_authenticated_session((int) $row['user_id'], (string) $row['password_hash']);
        session()->regenerateToken();
        session()->put('csrf_token', session()->token());
        issue_remember_token($pdo, (int) $row['user_id']);
        $pdo->commit();

        return true;
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

function rotate_csrf_token(): string
{
    session()->regenerateToken();
    session()->put('csrf_token', session()->token());

    return session()->get('csrf_token');
}

function desego_inventory_costing_method(PDO $pdo): string
{
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key='inventory_costing_method' LIMIT 1");
    $stmt->execute();
    $method = strtoupper((string) ($stmt->fetchColumn() ?: 'FIFO'));

    return in_array($method, ['FIFO', 'LIFO', 'AVG'], true) ? $method : 'FIFO';
}

function desego_consume_cost_layers(PDO $pdo, int $ingredientId, float $quantity, float $fallbackUnitCost): float
{
    $quantity = round($quantity, 3);
    if ($quantity <= 0) {
        return max(0, $fallbackUnitCost);
    }
    $method = desego_inventory_costing_method($pdo);
    $order = $method === 'LIFO' ? 'received_at DESC, id DESC' : 'received_at ASC, id ASC';
    $stmt = $pdo->prepare("SELECT id, quantity_remaining, unit_cost FROM inventory_cost_layers WHERE ingredient_id=:ingredient AND quantity_remaining>0 ORDER BY {$order} FOR UPDATE");
    $stmt->execute(['ingredient' => $ingredientId]);
    $layers = $stmt->fetchAll();
    $remaining = $quantity;
    $costTotal = 0.0;
    $update = $pdo->prepare('UPDATE inventory_cost_layers SET quantity_remaining=:remaining WHERE id=:id');
    foreach ($layers as $layer) {
        if ($remaining <= 0.0001) {
            break;
        }
        $available = (float) $layer['quantity_remaining'];
        $take = min($available, $remaining);
        if ($take <= 0) {
            continue;
        }
        $newLayerQuantity = round($available - $take, 3);
        $update->execute(['remaining' => number_format(max(0, $newLayerQuantity), 3, '.', ''), 'id' => (int) $layer['id']]);
        $costTotal += $take * (float) $layer['unit_cost'];
        $remaining = round($remaining - $take, 3);
    }
    // If legacy stock was manually adjusted without layers, use the current weighted average for the uncovered remainder.
    if ($remaining > 0.0001) {
        $costTotal += $remaining * max(0, $fallbackUnitCost);
    }
    if ($method === 'AVG') {
        return max(0, round($fallbackUnitCost, 4));
    }

    return max(0, round($costTotal / $quantity, 4));
}

function desego_add_adjustment_cost_layer(PDO $pdo, int $ingredientId, float $quantity, float $unitCost, string $notes): void
{
    $quantity = round($quantity, 3);
    if ($quantity <= 0) {
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO inventory_cost_layers (ingredient_id, layer_type, receipt_item_id, quantity_received, quantity_remaining, unit_cost, notes, received_at) VALUES (:ingredient,'adjustment',NULL,:quantity,:remaining,:cost,:notes,NOW())");
    $stmt->execute(['ingredient' => $ingredientId, 'quantity' => number_format($quantity, 3, '.', ''), 'remaining' => number_format($quantity, 3, '.', ''), 'cost' => number_format(max(0, $unitCost), 4, '.', ''), 'notes' => substr($notes, 0, 255)]);
}

function loyalty_award_order_points(PDO $pdo, int $orderId, ?int $userId = null): int
{
    $stmt = $pdo->prepare('SELECT id, order_number, customer_id, payment_status, subtotal, discount FROM orders WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $orderId]);
    $order = $stmt->fetch();
    if (! $order || $order['payment_status'] !== 'Pagado' || empty($order['customer_id'])) {
        return 0;
    }

    $exists = $pdo->prepare("SELECT id FROM loyalty_transactions WHERE order_id = :id AND transaction_type IN ('earn','order_refund') LIMIT 1");
    $exists->execute(['id' => $orderId]);
    if ($exists->fetchColumn()) {
        return 0;
    }

    $settings = $pdo->query('SELECT soles_per_point, minimum_eligible_amount FROM loyalty_settings WHERE id = 1')->fetch();
    if (! $settings || (float) $settings['soles_per_point'] <= 0) {
        return 0;
    }
    $eligible = max(0.0, (float) $order['subtotal'] - (float) $order['discount']);
    if ($eligible + 0.0001 < (float) $settings['minimum_eligible_amount']) {
        return 0;
    }
    $points = (int) floor(($eligible + 0.000001) / (float) $settings['soles_per_point']);
    if ($points <= 0) {
        return 0;
    }

    $customerId = (int) $order['customer_id'];
    $pdo->prepare('INSERT IGNORE INTO loyalty_accounts (customer_id, points_balance, lifetime_earned, lifetime_redeemed, created_at, updated_at) VALUES (:id,0,0,0,NOW(),NOW())')->execute(['id' => $customerId]);
    $accountStmt = $pdo->prepare('SELECT points_balance FROM loyalty_accounts WHERE customer_id = :id FOR UPDATE');
    $accountStmt->execute(['id' => $customerId]);
    $balance = (int) $accountStmt->fetchColumn();
    $newBalance = $balance + $points;
    $pdo->prepare('UPDATE loyalty_accounts SET points_balance = :balance, lifetime_earned = lifetime_earned + :earned, updated_at = NOW() WHERE customer_id = :id')
        ->execute(['balance' => $newBalance, 'earned' => $points, 'id' => $customerId]);
    $pdo->prepare("INSERT INTO loyalty_transactions (customer_id, order_id, user_id, transaction_type, points_delta, balance_after, description, created_at) VALUES (:customer_id,:order_id,:user_id,'earn',:delta,:balance,:description,NOW())")
        ->execute(['customer_id' => $customerId, 'order_id' => $orderId, 'user_id' => $userId, 'delta' => $points, 'balance' => $newBalance, 'description' => 'Puntos por pedido '.(string) ($order['order_number'] ?? ('#'.$orderId))]);

    return $points;
}

function loyalty_reverse_order_points(PDO $pdo, int $orderId, ?int $userId = null): int
{
    $stmt = $pdo->prepare("SELECT id, customer_id, points_delta, description FROM loyalty_transactions WHERE order_id = :id AND transaction_type = 'earn' LIMIT 1 FOR UPDATE");
    $stmt->execute(['id' => $orderId]);
    $earn = $stmt->fetch();
    if (! $earn || (int) $earn['points_delta'] <= 0) {
        return 0;
    }

    $exists = $pdo->prepare("SELECT id FROM loyalty_transactions WHERE order_id = :id AND transaction_type = 'order_refund' LIMIT 1");
    $exists->execute(['id' => $orderId]);
    if ($exists->fetchColumn()) {
        return 0;
    }

    $customerId = (int) $earn['customer_id'];
    $points = (int) $earn['points_delta'];
    $pdo->prepare('INSERT IGNORE INTO loyalty_accounts (customer_id, points_balance, lifetime_earned, lifetime_redeemed, created_at, updated_at) VALUES (:id,0,0,0,NOW(),NOW())')->execute(['id' => $customerId]);
    $accountStmt = $pdo->prepare('SELECT points_balance FROM loyalty_accounts WHERE customer_id = :id FOR UPDATE');
    $accountStmt->execute(['id' => $customerId]);
    $newBalance = (int) $accountStmt->fetchColumn() - $points;
    $pdo->prepare('UPDATE loyalty_accounts SET points_balance = :balance, updated_at = NOW() WHERE customer_id = :id')->execute(['balance' => $newBalance, 'id' => $customerId]);
    $pdo->prepare("INSERT INTO loyalty_transactions (customer_id, order_id, user_id, transaction_type, points_delta, balance_after, description, created_at) VALUES (:customer_id,:order_id,:user_id,'order_refund',:delta,:balance,:description,NOW())")
        ->execute(['customer_id' => $customerId, 'order_id' => $orderId, 'user_id' => $userId, 'delta' => -$points, 'balance' => $newBalance, 'description' => 'Reversión de puntos por reembolso del pedido #'.$orderId]);

    return $points;
}

function desego_backup_config(): array
{
    $config = config('desego.backup');
    $key = (string) ($config['encryption_key_hex'] ?? '');
    if (! preg_match('/^[a-f0-9]{64}$/i', $key) || str_contains(strtoupper($key), 'REEMPLAZAR')) {
        throw new RuntimeException('Configura una clave de cifrado válida de 64 caracteres hexadecimales en config/backup.php o en DESEGO_BACKUP_KEY.');
    }
    $config['key'] = hex2bin($key);
    $config['max_dump_bytes'] = max(1024 * 1024, (int) ($config['max_dump_bytes'] ?? 67108864));
    $config['storage_dir'] = (string) ($config['storage_dir'] ?? storage_path('app/private/desego/backups'));
    $config['redundant_dir'] = trim((string) ($config['redundant_dir'] ?? ''));

    return $config;
}

function desego_backup_ensure_dir(string $directory): void
{
    if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
        throw new RuntimeException('No se pudo crear el directorio privado de backups.');
    }
    @chmod($directory, 0700);
    if (! is_writable($directory)) {
        throw new RuntimeException('El directorio de backups no tiene permisos de escritura.');
    }
}

function desego_sql_identifier(string $identifier): string
{
    return '`'.str_replace('`', '``', $identifier).'`';
}

function desego_sql_value(PDO $pdo, mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    $quoted = $pdo->quote((string) $value);
    if (! is_string($quoted)) {
        throw new RuntimeException('No se pudo serializar un valor de la base de datos.');
    }

    return $quoted;
}

function desego_backup_dump(PDO $pdo, string $databaseName, int $maxBytes): string
{
    if ($databaseName === '') {
        throw new RuntimeException('No se ha identificado la base de datos que se copiará.');
    }
    $out = 'SET @DESEGO_BACKUP_TIMESTAMP = '.$pdo->quote(gmdate('Y-m-d H:i:s')).";\n";
    $out .= "SET NAMES utf8mb4;\nSET time_zone = '+00:00';\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";
    $tablesRaw = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
    $tableNames = [];
    foreach ($tablesRaw as $tableInfo) {
        $name = (string) ($tableInfo[0] ?? '');
        if ($name !== '') {
            $tableNames[] = $name;
        }
    }
    $dependencies = array_fill_keys($tableNames, []);
    $depsStmt = $pdo->query('SELECT TABLE_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL');
    foreach ($depsStmt->fetchAll(PDO::FETCH_ASSOC) as $dep) {
        $child = (string) $dep['TABLE_NAME'];
        $parent = (string) $dep['REFERENCED_TABLE_NAME'];
        if (isset($dependencies[$child], $dependencies[$parent]) && $child !== $parent) {
            $dependencies[$child][$parent] = true;
        }
    }
    // Topological order: referenced parent tables before child tables. Cyclic leftovers are appended after acyclic tables.
    $orderedNames = [];
    $remaining = $dependencies;
    while ($remaining) {
        $progress = false;
        foreach ($remaining as $name => $parents) {
            $unresolved = array_diff_key($parents, array_flip($orderedNames));
            if (! $unresolved) {
                $orderedNames[] = $name;
                unset($remaining[$name]);
                $progress = true;
            }
        }
        if (! $progress) {
            foreach (array_keys($remaining) as $name) {
                $orderedNames[] = $name;
            } break;
        }
    }
    $allTables = [];
    foreach ($orderedNames as $tableName) {
        $show = $pdo->query('SHOW CREATE TABLE '.desego_sql_identifier($tableName))->fetch(PDO::FETCH_NUM);
        $create = (string) ($show[1] ?? '');
        $columns = $pdo->query('SHOW COLUMNS FROM '.desego_sql_identifier($tableName))->fetchAll(PDO::FETCH_ASSOC);
        if ($create !== '' && $columns) {
            $allTables[] = ['name' => $tableName, 'create' => $create, 'columns' => $columns];
        }
    }
    $tables = $allTables;

    // Drop child tables first; create parent tables first. Foreign-key checks are disabled during row restoration.
    foreach (array_reverse($tables) as $table) {
        $out .= 'DROP TABLE IF EXISTS '.desego_sql_identifier($table['name']).";\n";
        if (strlen($out) > $maxBytes) {
            throw new RuntimeException('El backup supera el límite de tamaño configurado.');
        }
    }
    $out .= "\n";
    foreach ($tables as $table) {
        $out .= $table['create'].";\n\n";
        if (strlen($out) > $maxBytes) {
            throw new RuntimeException('El backup supera el límite de tamaño configurado.');
        }
    }
    foreach ($tables as $table) {
        $names = array_map(static fn (array $column): string => desego_sql_identifier((string) $column['Field']), $table['columns']);
        $select = $pdo->query('SELECT * FROM '.desego_sql_identifier($table['name']));
        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            $values = [];
            foreach ($table['columns'] as $column) {
                $field = (string) $column['Field'];
                $values[] = desego_sql_value($pdo, $row[$field] ?? null);
            }
            $out .= 'INSERT INTO '.desego_sql_identifier($table['name']).' ('.implode(', ', $names).') VALUES ('.implode(', ', $values).");\n";
            if (strlen($out) > $maxBytes) {
                throw new RuntimeException('El backup supera el límite para la interfaz web. Ejecuta scripts/backup_daily.php desde CLI o aumenta max_dump_bytes si el servidor tiene memoria suficiente.');
            }
        }
        $out .= "\n";
    }
    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";

    return $out;
}

function desego_backup_decrypt(string $fileContents, string $key): string
{
    $magic = 'DSGBK1';
    if (strlen($fileContents) < 34 || substr($fileContents, 0, 6) !== $magic) {
        throw new RuntimeException('El archivo no tiene el formato de backup DESEGO reconocido.');
    }
    $nonce = substr($fileContents, 6, 12);
    $tag = substr($fileContents, 18, 16);
    $ciphertext = substr($fileContents, 34);
    $compressed = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'DESEGO-DB-BACKUP-v1');
    if (! is_string($compressed)) {
        throw new RuntimeException('No se pudo descifrar el backup. Comprueba la clave de cifrado.');
    }
    $sql = gzdecode($compressed);
    if (! is_string($sql) || ! str_contains($sql, 'SET FOREIGN_KEY_CHECKS=0;') || ! str_contains($sql, 'SET NAMES utf8mb4;')) {
        throw new RuntimeException('El contenido del backup no pasó la comprobación de integridad.');
    }

    return $sql;
}

function desego_backup_filename(array $row): string
{
    $config = desego_backup_config();
    $primary = (string) ($row['file_name'] ?? '');
    if ($primary !== '' && preg_match('/^[a-zA-Z0-9._-]+\.dsgbk$/', $primary)) {
        $path = rtrim($config['storage_dir'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$primary;
        if (is_file($path)) {
            return $path;
        }
    }
    $secondary = (string) ($row['secondary_file_name'] ?? '');
    if ($secondary !== '' && preg_match('/^[a-zA-Z0-9._-]+\.dsgbk$/', $secondary) && $config['redundant_dir'] !== '') {
        $path = rtrim($config['redundant_dir'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$secondary;
        if (is_file($path)) {
            return $path;
        }
    }
    throw new RuntimeException('No se encuentra el archivo de backup en el almacenamiento configurado.');
}

function desego_backup_verify(PDO $pdo, int $backupId, bool $update = true): array
{
    $config = desego_backup_config();
    $stmt = $pdo->prepare("SELECT * FROM backup_history WHERE id = :id AND status = 'completed' LIMIT 1");
    $stmt->execute(['id' => $backupId]);
    $row = $stmt->fetch();
    if (! $row) {
        throw new RuntimeException('No existe una copia completada con ese identificador.');
    }
    $path = desego_backup_filename($row);
    $content = file_get_contents($path);
    if (! is_string($content)) {
        throw new RuntimeException('No se pudo leer el archivo del backup.');
    }
    $calculated = hash('sha256', $content);
    if (! is_string($row['sha256_hash']) || ! hash_equals((string) $row['sha256_hash'], $calculated)) {
        if ($update) {
            $bad = $pdo->prepare("UPDATE backup_history SET verification_status = 'invalid', verified_at = UTC_TIMESTAMP(), error_message = :msg WHERE id = :id");
            $bad->execute(['msg' => 'El hash SHA-256 no coincide.', 'id' => $backupId]);
        }
        throw new RuntimeException('La integridad del archivo falló: el hash SHA-256 no coincide.');
    }
    desego_backup_decrypt($content, (string) $config['key']);
    if ($update) {
        $ok = $pdo->prepare("UPDATE backup_history SET verification_status = 'valid', verified_at = UTC_TIMESTAMP(), error_message = NULL WHERE id = :id");
        $ok->execute(['id' => $backupId]);
    }

    return ['row' => $row, 'path' => $path, 'size_bytes' => strlen($content), 'sha256_hash' => $calculated];
}

function desego_backup_create(PDO $pdo, ?int $userId, string $kind = 'manual'): array
{
    $config = desego_backup_config();
    if (! extension_loaded('openssl')) {
        throw new RuntimeException('La extensión OpenSSL de PHP debe estar habilitada para cifrar backups.');
    }
    if (! function_exists('gzencode') || ! function_exists('gzdecode')) {
        throw new RuntimeException('La extensión zlib de PHP debe estar habilitada para comprimir y comprobar backups.');
    }
    desego_backup_ensure_dir((string) $config['storage_dir']);
    $dbConfig = ['name' => config('database.connections.'.config('database.default').'.database')];
    $sql = desego_backup_dump($pdo, (string) ($dbConfig['name'] ?? ''), (int) $config['max_dump_bytes']);
    $compressed = gzencode($sql, 6);
    if (! is_string($compressed)) {
        throw new RuntimeException('No se pudo comprimir el backup.');
    }
    $nonce = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($compressed, 'aes-256-gcm', (string) $config['key'], OPENSSL_RAW_DATA, $nonce, $tag, 'DESEGO-DB-BACKUP-v1');
    if (! is_string($cipher) || strlen($tag) !== 16) {
        throw new RuntimeException('No se pudo cifrar el backup.');
    }
    $contents = 'DSGBK1'.$nonce.$tag.$cipher;
    $fileName = 'desego-'.$kind.'-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(5)).'.dsgbk';
    $path = rtrim((string) $config['storage_dir'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$fileName;
    if (file_put_contents($path, $contents, LOCK_EX) !== strlen($contents)) {
        @unlink($path);
        throw new RuntimeException('No se pudo guardar el archivo de backup.');
    }
    @chmod($path, 0600);
    $secondaryFile = null;
    if ((string) $config['redundant_dir'] !== '') {
        try {
            desego_backup_ensure_dir((string) $config['redundant_dir']);
            $secondaryPath = rtrim((string) $config['redundant_dir'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$fileName;
            if (! copy($path, $secondaryPath)) {
                throw new RuntimeException('No se pudo copiar a la ubicación redundante.');
            }
            @chmod($secondaryPath, 0600);
            $secondaryFile = $fileName;
        } catch (Throwable $error) {
            if ($error instanceof HttpResponseException) {
                throw $error;
            }
            error_log('[DESEGO backup redundancy] '.$error->getMessage());
        }
    }
    $hash = hash('sha256', $contents);
    $insert = $pdo->prepare("INSERT INTO backup_history (file_name, secondary_file_name, backup_kind, status, size_bytes, sha256_hash, is_encrypted, verification_status, verified_at, created_by, created_at) VALUES (:file_name, :secondary_file_name, :kind, 'completed', :size_bytes, :sha256_hash, 1, 'pending', NULL, :created_by, UTC_TIMESTAMP())");
    $insert->execute(['file_name' => $fileName, 'secondary_file_name' => $secondaryFile, 'kind' => $kind, 'size_bytes' => strlen($contents), 'sha256_hash' => $hash, 'created_by' => $userId]);
    $id = (int) $pdo->lastInsertId();
    audit_log($pdo, $userId, 'backup.created', ['backup_id' => $id, 'kind' => $kind, 'size_bytes' => strlen($contents), 'sha256_prefix' => substr($hash, 0, 12), 'redundant_copy' => $secondaryFile !== null]);

    return ['id' => $id, 'file_name' => $fileName, 'size_bytes' => strlen($contents), 'sha256_hash' => $hash, 'secondary_copy' => $secondaryFile !== null, 'created_at' => gmdate('Y-m-d H:i:s')];
}

function desego_system_setting(PDO $pdo, string $key, string $default): string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = :key LIMIT 1');
    $stmt->execute(['key' => $key]);
    $value = $stmt->fetchColumn();

    return $value === false ? $default : (string) $value;
}

function desego_apply_retention(PDO $pdo, ?int $userId): array
{
    // Validate backup configuration before mutating retention data.
    $config = desego_backup_config();
    $auditDays = max(30, min(3650, (int) desego_system_setting($pdo, 'audit_retention_days', '365')));
    $backupDays = max(7, min(3650, (int) desego_system_setting($pdo, 'backup_retention_days', '90')));
    $auditCutoff = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('-'.$auditDays.' days')->format('Y-m-d H:i:s');
    $backupCutoff = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('-'.$backupDays.' days')->format('Y-m-d H:i:s');
    $countLogs = $pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE created_at < :cutoff');
    $countLogs->execute(['cutoff' => $auditCutoff]);
    $oldLogs = (int) $countLogs->fetchColumn();
    $delLogs = $pdo->prepare('DELETE FROM audit_logs WHERE created_at < :cutoff');
    $delLogs->execute(['cutoff' => $auditCutoff]);

    $old = $pdo->prepare("SELECT id, file_name, secondary_file_name FROM backup_history WHERE status = 'completed' AND created_at < :cutoff AND id <> COALESCE((SELECT keep_id FROM (SELECT MAX(id) AS keep_id FROM backup_history WHERE status='completed') keep_latest), 0)");
    $old->execute(['cutoff' => $backupCutoff]);
    $expired = $old->fetchAll();
    foreach ($expired as $row) {
        foreach ([[(string) ($row['file_name'] ?? ''), (string) $config['storage_dir']], [(string) ($row['secondary_file_name'] ?? ''), (string) $config['redundant_dir']]] as [$name, $dir]) {
            if ($name !== '' && $dir !== '' && preg_match('/^[a-zA-Z0-9._-]+\.dsgbk$/', $name)) {
                @unlink(rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name);
            }
        }
        $mark = $pdo->prepare("UPDATE backup_history SET status = 'deleted', verification_status = 'deleted', error_message = 'Eliminado por política de retención.' WHERE id = :id");
        $mark->execute(['id' => (int) $row['id']]);
    }
    audit_log($pdo, $userId, 'retention.applied', ['audit_logs_removed' => $oldLogs, 'backups_expired' => count($expired), 'audit_retention_days' => $auditDays, 'backup_retention_days' => $backupDays]);

    return ['audit_logs_removed' => $oldLogs, 'backups_expired' => count($expired), 'audit_retention_days' => $auditDays, 'backup_retention_days' => $backupDays];
}

function desego_split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = '';
    $escaped = false;
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        if ($quote !== '') {
            $buffer .= $char;
            if ($escaped) {
                $escaped = false;

                continue;
            }
            if ($char === '\\' && $quote !== '`') {
                $escaped = true;

                continue;
            }
            if ($char === $quote) {
                if ($quote === '`' && $i + 1 < $length && $sql[$i + 1] === '`') {
                    $buffer .= $sql[++$i];

                    continue;
                }
                if (($quote === "'" || $quote === '"') && $i + 1 < $length && $sql[$i + 1] === $quote) {
                    $buffer .= $sql[++$i];

                    continue;
                }
                $quote = '';
            }

            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $buffer .= $char;

            continue;
        }
        if ($char === ';') {
            $candidate = trim($buffer);
            if ($candidate !== '' && ! str_starts_with($candidate, '--')) {
                $statements[] = $candidate;
            } $buffer = '';

            continue;
        }
        $buffer .= $char;
    }
    $candidate = trim($buffer);
    if ($candidate !== '' && ! str_starts_with($candidate, '--')) {
        $statements[] = $candidate;
    }

    return $statements;
}

function desego_header(string $header): void
{
    [$name, $value] = array_pad(explode(':', $header, 2), 2, '');
    $headers = request()->attributes->get('desego_headers', []);
    $headers[trim($name)] = trim($value);
    request()->attributes->set('desego_headers', $headers);
}
function desego_cookie(string $name, string $value, array $options): void
{
    Cookie::queue(cookie($name, $value,
        max(-2628000, (int) ceil((($options['expires'] ?? time()) - time()) / 60)),
        $options['path'] ?? '/', null, request()->isSecure(), true, false, 'Lax'));
}

function desego_integration_environment(string $key): string|false
{
    $value = config('desego.integration_environment.'.$key, getenv($key));

    return is_string($value) ? $value : false;
}
