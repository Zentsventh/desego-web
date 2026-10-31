<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Exceptions\Desego\LoyaltyApiProblem;
use App\Http\Controllers\Controller;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Exceptions\HttpResponseException;
use PDO;
use PDOException;
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

class LoyaltyController extends Controller
{
    public function __invoke(): Response
    {
        try {
            $pdo = desego_db();
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            if ($method === 'GET') {
                $actor = require_any_permission(['loyalty.view', 'loyalty.manage']);
                $view = (string) (request()->query()['view'] ?? 'dashboard');
                if (! in_array($view, ['dashboard', 'customer'], true)) {
                    throw new LoyaltyApiProblem('Vista de fidelización no válida.', 404);
                }
                if ($view === 'customer') {
                    $id = filter_var(request()->query()['customer_id'] ?? null, FILTER_VALIDATE_INT);
                    if (! $id || $id < 1) {
                        throw new LoyaltyApiProblem('Selecciona un cliente válido.');
                    }
                    $customerStmt = $pdo->prepare('SELECT id, customer_code, full_name, phone, email, status FROM customers WHERE id = :id LIMIT 1');
                    $customerStmt->execute(['id' => $id]);
                    $customer = $customerStmt->fetch();
                    if (! $customer) {
                        throw new LoyaltyApiProblem('No se encontró el cliente.', 404);
                    }
                    $accountStmt = $pdo->prepare('SELECT c.id AS customer_id, COALESCE(la.points_balance,0) AS points_balance, COALESCE(la.lifetime_earned,0) AS lifetime_earned, COALESCE(la.lifetime_redeemed,0) AS lifetime_redeemed FROM customers c LEFT JOIN loyalty_accounts la ON la.customer_id=c.id WHERE c.id=:id LIMIT 1');
                    $accountStmt->execute(['id' => $id]);
                    $account = $accountStmt->fetch() ?: ['customer_id' => (int) $id, 'points_balance' => 0, 'lifetime_earned' => 0, 'lifetime_redeemed' => 0];
                    foreach (['customer_id', 'points_balance', 'lifetime_earned', 'lifetime_redeemed'] as $key) {
                        $account[$key] = (int) $account[$key];
                    }
                    $tx = $pdo->prepare('SELECT id, transaction_type, points_delta, balance_after, description, created_at FROM loyalty_transactions WHERE customer_id = :id ORDER BY id DESC LIMIT 100');
                    $tx->execute(['id' => $id]);
                    $redemptions = $pdo->prepare('SELECT lr.id, lr.redemption_code, lr.points_spent, lr.status, lr.issued_at, lr.used_at, r.name AS reward_name FROM loyalty_redemptions lr INNER JOIN loyalty_rewards r ON r.id = lr.reward_id WHERE lr.customer_id = :id ORDER BY lr.id DESC LIMIT 50');
                    $redemptions->execute(['id' => $id]);
                    json_response(['ok' => true, 'customer' => $customer, 'account' => $account, 'transactions' => $tx->fetchAll(), 'redemptions' => $redemptions->fetchAll()]);
                }
                $q = trim((string) (request()->query()['q'] ?? ''));
                if (text_length($q) > 100) {
                    $q = substr($q, 0, 100);
                }
                $where = '';
                $params = [];
                if ($q !== '') {
                    $where = 'WHERE c.full_name LIKE :q1 OR c.customer_code LIKE :q2 OR c.phone LIKE :q3 OR c.email LIKE :q4';
                    $like = '%'.$q.'%';
                    $params = ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
                }
                $accountsStmt = $pdo->prepare("SELECT c.id AS customer_id, c.customer_code, c.full_name, c.phone, c.email, c.status,
                    COALESCE(la.points_balance,0) AS points_balance, COALESCE(la.lifetime_earned,0) AS lifetime_earned,
                    COALESCE(la.lifetime_redeemed,0) AS lifetime_redeemed,
                    (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id AND o.payment_status = 'Pagado') AS paid_orders
                FROM customers c LEFT JOIN loyalty_accounts la ON la.customer_id = c.id $where
                ORDER BY COALESCE(la.points_balance,0) DESC, c.full_name ASC LIMIT 300");
                $accountsStmt->execute($params);
                $accounts = $accountsStmt->fetchAll();
                foreach ($accounts as &$row) {
                    foreach (['customer_id', 'points_balance', 'lifetime_earned', 'lifetime_redeemed', 'paid_orders'] as $k) {
                        $row[$k] = (int) $row[$k];
                    }
                }
                unset($row);
                $settings = $pdo->query('SELECT soles_per_point, minimum_eligible_amount, points_expiry_days, updated_at FROM loyalty_settings WHERE id = 1')->fetch() ?: ['soles_per_point' => 10, 'minimum_eligible_amount' => 0, 'points_expiry_days' => 0, 'updated_at' => null];
                $settings['soles_per_point'] = (float) $settings['soles_per_point'];
                $settings['minimum_eligible_amount'] = (float) $settings['minimum_eligible_amount'];
                $settings['points_expiry_days'] = (int) $settings['points_expiry_days'];
                $summary = $pdo->query('SELECT COUNT(DISTINCT c.id) AS customers_total,
            SUM(CASE WHEN COALESCE(la.points_balance,0) > 0 THEN 1 ELSE 0 END) AS customers_with_points,
            COALESCE(SUM(la.points_balance),0) AS points_balance,
            COALESCE(SUM(la.lifetime_earned),0) AS points_earned,
            COALESCE(SUM(la.lifetime_redeemed),0) AS points_redeemed
            FROM customers c LEFT JOIN loyalty_accounts la ON la.customer_id = c.id')->fetch() ?: [];
                foreach (['customers_total', 'customers_with_points', 'points_balance', 'points_earned', 'points_redeemed'] as $k) {
                    $summary[$k] = (int) ($summary[$k] ?? 0);
                }
                $rewards = $pdo->query("SELECT r.id, r.reward_code, r.name, r.description, r.reward_type, r.points_cost, r.discount_amount, r.product_id, p.name AS product_name,
            r.stock_limit, r.redeemed_count, r.starts_at, r.ends_at, r.status, r.created_at,
            CASE WHEN r.status='active' AND r.starts_at <= UTC_TIMESTAMP() AND (r.ends_at IS NULL OR r.ends_at >= UTC_TIMESTAMP()) AND (r.stock_limit IS NULL OR r.redeemed_count < r.stock_limit) THEN 1 ELSE 0 END AS is_available
            FROM loyalty_rewards r LEFT JOIN products p ON p.id = r.product_id ORDER BY r.created_at DESC, r.id DESC LIMIT 200")->fetchAll();
                foreach ($rewards as &$row) {
                    foreach (['id', 'points_cost', 'product_id', 'stock_limit', 'redeemed_count', 'is_available'] as $k) {
                        $row[$k] = $row[$k] === null ? null : (int) $row[$k];
                    } $row['discount_amount'] = (float) $row['discount_amount'];
                }
                unset($row);
                $transactions = $pdo->query('SELECT t.id, t.customer_id, c.full_name, c.customer_code, t.order_id, o.order_number, t.reward_id, r.name AS reward_name, t.transaction_type, t.points_delta, t.balance_after, t.description, t.created_at, u.name AS user_name
            FROM loyalty_transactions t INNER JOIN customers c ON c.id=t.customer_id LEFT JOIN orders o ON o.id=t.order_id LEFT JOIN loyalty_rewards r ON r.id=t.reward_id LEFT JOIN users u ON u.id=t.user_id ORDER BY t.id DESC LIMIT 100')->fetchAll();
                $redemptions = $pdo->query('SELECT lr.id, lr.redemption_code, lr.customer_id, c.full_name, lr.reward_id, r.name AS reward_name, lr.points_spent, lr.status, lr.issued_at, lr.used_at, iu.name AS issued_by_name, uu.name AS used_by_name
            FROM loyalty_redemptions lr INNER JOIN customers c ON c.id=lr.customer_id INNER JOIN loyalty_rewards r ON r.id=lr.reward_id LEFT JOIN users iu ON iu.id=lr.issued_by LEFT JOIN users uu ON uu.id=lr.used_by ORDER BY lr.id DESC LIMIT 80')->fetchAll();
                foreach ($transactions as &$row) {
                    foreach (['id', 'customer_id', 'order_id', 'reward_id', 'points_delta', 'balance_after'] as $k) {
                        $row[$k] = $row[$k] === null ? null : (int) $row[$k];
                    }
                }
                unset($row);
                foreach ($redemptions as &$row) {
                    foreach (['id', 'customer_id', 'reward_id', 'points_spent'] as $k) {
                        $row[$k] = (int) $row[$k];
                    }
                }
                unset($row);
                json_response(['ok' => true, 'settings' => $settings, 'summary' => $summary, 'accounts' => $accounts, 'rewards' => $rewards, 'transactions' => $transactions, 'redemptions' => $redemptions,
                    'permissions' => ['can_manage' => in_array('loyalty.manage', $actor['permissions'] ?? [], true)]]);
            }
            require_method('POST');
            $body = json_body();
            require_csrf($body);
            $actor = require_permission('loyalty.manage');
            $action = (string) ($body['action'] ?? '');
            if ($action === 'settings.update') {
                $solesPerPoint = filter_var($body['soles_per_point'] ?? null, FILTER_VALIDATE_FLOAT);
                $minimum = filter_var($body['minimum_eligible_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
                $expiry = filter_var($body['points_expiry_days'] ?? 0, FILTER_VALIDATE_INT);
                if (! is_numeric($solesPerPoint) || (float) $solesPerPoint < 1 || (float) $solesPerPoint > 10000) {
                    throw new LoyaltyApiProblem('La conversión debe ser entre S/ 1 y S/ 10,000 por punto.');
                }
                if (! is_numeric($minimum) || (float) $minimum < 0 || (float) $minimum > 1000000) {
                    throw new LoyaltyApiProblem('El mínimo de compra no es válido.');
                }
                if (! is_numeric($expiry) || (int) $expiry !== 0) {
                    throw new LoyaltyApiProblem('La caducidad automática aún no está habilitada. Mantén la vigencia en 0 (sin vencimiento).');
                }
                $pdo->prepare('UPDATE loyalty_settings SET soles_per_point=:s, minimum_eligible_amount=:m, points_expiry_days=:e, updated_by=:u, updated_at=NOW() WHERE id=1')
                    ->execute(['s' => number_format((float) $solesPerPoint, 2, '.', ''), 'm' => number_format((float) $minimum, 2, '.', ''), 'e' => (int) $expiry, 'u' => (int) $actor['id']]);
                audit_log($pdo, (int) $actor['id'], 'loyalty.settings_updated', ['soles_per_point' => (float) $solesPerPoint, 'minimum_eligible_amount' => (float) $minimum, 'points_expiry_days' => (int) $expiry]);
                json_response(['ok' => true, 'message' => 'Configuración de puntos guardada.']);
            }
            if ($action === 'reward.save') {
                $idRaw = $body['id'] ?? null;
                $id = ($idRaw === null || $idRaw === '') ? null : filter_var($idRaw, FILTER_VALIDATE_INT);
                if ($idRaw !== null && $idRaw !== '' && (! $id || $id < 1)) {
                    throw new LoyaltyApiProblem('Recompensa no válida.');
                }
                $code = strtoupper(trim((string) ($body['reward_code'] ?? '')));
                $name = trim((string) ($body['name'] ?? ''));
                $description = trim((string) ($body['description'] ?? ''));
                $type = (string) ($body['reward_type'] ?? 'discount');
                $pointsCost = filter_var($body['points_cost'] ?? null, FILTER_VALIDATE_INT);
                $discountAmount = filter_var($body['discount_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
                $productRaw = $body['product_id'] ?? null;
                $productId = ($productRaw === null || $productRaw === '') ? null : filter_var($productRaw, FILTER_VALIDATE_INT);
                $stockRaw = $body['stock_limit'] ?? null;
                $stockLimit = ($stockRaw === null || $stockRaw === '') ? null : filter_var($stockRaw, FILTER_VALIDATE_INT);
                $startsAt = $this->loyalty_datetime((string) ($body['starts_at'] ?? ''), false);
                $endsAt = $this->loyalty_datetime((string) ($body['ends_at'] ?? ''), true);
                if (! preg_match('/^[A-Z0-9_-]{3,32}$/', $code)) {
                    throw new LoyaltyApiProblem('El código debe tener entre 3 y 32 caracteres: letras, números, guion o guion bajo.');
                }
                if (text_length($name) < 3 || text_length($name) > 120 || text_length($description) > 255) {
                    throw new LoyaltyApiProblem('El nombre debe tener entre 3 y 120 caracteres y la descripción máximo 255.');
                }
                if (! in_array($type, ['discount', 'free_product'], true)) {
                    throw new LoyaltyApiProblem('Tipo de recompensa no válido.');
                }
                if (! is_numeric($pointsCost) || (int) $pointsCost < 1 || (int) $pointsCost > 10000000) {
                    throw new LoyaltyApiProblem('El costo debe ser de al menos 1 punto.');
                }
                if (! is_numeric($discountAmount) || (float) $discountAmount < 0 || (float) $discountAmount > 100000) {
                    throw new LoyaltyApiProblem('Importe de descuento no válido.');
                }
                if ($type === 'discount' && (float) $discountAmount <= 0) {
                    throw new LoyaltyApiProblem('La recompensa de descuento necesita un importe mayor que cero.');
                }
                if ($type === 'free_product') {
                    if (! $productId || $productId < 1) {
                        throw new LoyaltyApiProblem('Selecciona el producto que entregará la recompensa.');
                    }
                    $p = $pdo->prepare("SELECT id FROM products WHERE id=:id AND status='available'");
                    $p->execute(['id' => $productId]);
                    if (! $p->fetchColumn()) {
                        throw new LoyaltyApiProblem('El producto de la recompensa no está disponible.');
                    }
                } else {
                    $productId = null;
                }
                if ($stockLimit !== null && (! is_numeric($stockLimit) || (int) $stockLimit < 1 || (int) $stockLimit > 100000000)) {
                    throw new LoyaltyApiProblem('El límite de canjes debe estar vacío o ser un entero positivo.');
                }
                if ($endsAt !== null && $endsAt < (string) $startsAt) {
                    throw new LoyaltyApiProblem('La fecha final debe ser posterior a la inicial.');
                }
                if ($id) {
                    $stmt = $pdo->prepare('UPDATE loyalty_rewards SET reward_code=:code,name=:name,description=:description,reward_type=:type,points_cost=:points,discount_amount=:discount,product_id=:product,stock_limit=:stock,starts_at=:starts,ends_at=:ends,updated_by=:user_id,updated_at=NOW() WHERE id=:id');
                    $stmt->execute(['code' => $code, 'name' => $name, 'description' => $description !== '' ? $description : null, 'type' => $type, 'points' => (int) $pointsCost, 'discount' => number_format((float) $discountAmount, 2, '.', ''), 'product' => $productId, 'stock' => $stockLimit === null ? null : (int) $stockLimit, 'starts' => $startsAt, 'ends' => $endsAt, 'user_id' => (int) $actor['id'], 'id' => (int) $id]);
                    $newId = (int) $id;
                    $message = 'Recompensa actualizada.';
                    $event = 'loyalty.reward_updated';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO loyalty_rewards (reward_code,name,description,reward_type,points_cost,discount_amount,product_id,stock_limit,redeemed_count,starts_at,ends_at,status,created_by,updated_by,created_at,updated_at) VALUES (:code,:name,:description,:type,:points,:discount,:product,:stock,0,:starts,:ends,'active',:created_by,:updated_by,NOW(),NOW())");
                    $stmt->execute(['code' => $code, 'name' => $name, 'description' => $description !== '' ? $description : null, 'type' => $type, 'points' => (int) $pointsCost, 'discount' => number_format((float) $discountAmount, 2, '.', ''), 'product' => $productId, 'stock' => $stockLimit === null ? null : (int) $stockLimit, 'starts' => $startsAt, 'ends' => $endsAt, 'created_by' => (int) $actor['id'], 'updated_by' => (int) $actor['id']]);
                    $newId = (int) $pdo->lastInsertId();
                    $message = 'Recompensa creada.';
                    $event = 'loyalty.reward_created';
                }
                audit_log($pdo, (int) $actor['id'], $event, ['reward_id' => $newId, 'code' => $code, 'points_cost' => (int) $pointsCost]);
                json_response(['ok' => true, 'id' => $newId, 'message' => $message]);
            }
            if ($action === 'reward.toggle') {
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $status = (string) ($body['status'] ?? '');
                if (! $id || $id < 1 || ! in_array($status, ['active', 'inactive'], true)) {
                    throw new LoyaltyApiProblem('Recompensa o estado no válido.');
                }
                $stmt = $pdo->prepare('UPDATE loyalty_rewards SET status=:status,updated_by=:uid,updated_at=NOW() WHERE id=:id');
                $stmt->execute(['status' => $status, 'uid' => (int) $actor['id'], 'id' => $id]);
                if ($stmt->rowCount() < 1) {
                    $check = $pdo->prepare('SELECT id FROM loyalty_rewards WHERE id=:id');
                    $check->execute(['id' => $id]);
                    if (! $check->fetchColumn()) {
                        throw new LoyaltyApiProblem('Recompensa no encontrada.', 404);
                    }
                }
                audit_log($pdo, (int) $actor['id'], 'loyalty.reward_status_changed', ['reward_id' => $id, 'status' => $status]);
                json_response(['ok' => true, 'message' => $status === 'active' ? 'Recompensa activada.' : 'Recompensa desactivada.']);
            }
            if ($action === 'points.adjust') {
                $customerId = filter_var($body['customer_id'] ?? null, FILTER_VALIDATE_INT);
                $delta = filter_var($body['points_delta'] ?? null, FILTER_VALIDATE_INT);
                $reason = trim((string) ($body['description'] ?? ''));
                if (! $customerId || $customerId < 1 || ! is_numeric($delta) || (int) $delta === 0 || abs((int) $delta) > 1000000 || text_length($reason) < 3 || text_length($reason) > 255) {
                    throw new LoyaltyApiProblem('Indica el cliente, una cantidad de puntos distinta de cero y un motivo de 3 a 255 caracteres.');
                }
                $pdo->beginTransaction();
                try {
                    $c = $pdo->prepare("SELECT id FROM customers WHERE id=:id AND status='active' FOR UPDATE");
                    $c->execute(['id' => $customerId]);
                    if (! $c->fetchColumn()) {
                        throw new LoyaltyApiProblem('El cliente no existe o está inactivo.', 404);
                    }
                    $account = $this->loyalty_get_account($pdo, (int) $customerId, true);
                    $newBalance = (int) $account['points_balance'] + (int) $delta;
                    if ($newBalance < 0) {
                        throw new LoyaltyApiProblem('El ajuste no puede dejar el saldo por debajo de cero.');
                    }
                    $pdo->prepare('UPDATE loyalty_accounts SET points_balance=:balance, lifetime_earned=CASE WHEN :delta1 > 0 THEN lifetime_earned + :delta2 ELSE lifetime_earned END, updated_at=NOW() WHERE customer_id=:id')->execute(['balance' => $newBalance, 'delta1' => (int) $delta, 'delta2' => (int) $delta, 'id' => $customerId]);
                    $pdo->prepare("INSERT INTO loyalty_transactions (customer_id,user_id,transaction_type,points_delta,balance_after,description,created_at) VALUES (:customer,:user,'adjustment',:delta,:balance,:description,NOW())")->execute(['customer' => $customerId, 'user' => (int) $actor['id'], 'delta' => (int) $delta, 'balance' => $newBalance, 'description' => $reason]);
                    $pdo->commit();
                    audit_log($pdo, (int) $actor['id'], 'loyalty.points_adjusted', ['customer_id' => (int) $customerId, 'points_delta' => (int) $delta, 'balance_after' => $newBalance]);
                    json_response(['ok' => true, 'message' => 'Ajuste de puntos guardado.', 'balance' => $newBalance]);
                } catch (Throwable $e) {
                    if ($e instanceof HttpResponseException) {
                        throw $e;
                    } if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    } throw $e;
                }
            }
            if ($action === 'reward.redeem') {
                $customerId = filter_var($body['customer_id'] ?? null, FILTER_VALIDATE_INT);
                $rewardId = filter_var($body['reward_id'] ?? null, FILTER_VALIDATE_INT);
                if (! $customerId || $customerId < 1 || ! $rewardId || $rewardId < 1) {
                    throw new LoyaltyApiProblem('Selecciona un cliente y una recompensa válidos.');
                }
                $pdo->beginTransaction();
                try {
                    $c = $pdo->prepare("SELECT id FROM customers WHERE id=:id AND status='active' FOR UPDATE");
                    $c->execute(['id' => $customerId]);
                    if (! $c->fetchColumn()) {
                        throw new LoyaltyApiProblem('Cliente no encontrado o inactivo.', 404);
                    }
                    $r = $pdo->prepare('SELECT * FROM loyalty_rewards WHERE id=:id FOR UPDATE');
                    $r->execute(['id' => $rewardId]);
                    $reward = $r->fetch();
                    if (! $reward || $reward['status'] !== 'active' || (string) $reward['starts_at'] > gmdate('Y-m-d H:i:s') || ($reward['ends_at'] !== null && (string) $reward['ends_at'] < gmdate('Y-m-d H:i:s')) || ($reward['stock_limit'] !== null && (int) $reward['redeemed_count'] >= (int) $reward['stock_limit'])) {
                        throw new LoyaltyApiProblem('Esta recompensa no está disponible para canje.', 409);
                    }
                    $account = $this->loyalty_get_account($pdo, (int) $customerId, true);
                    $cost = (int) $reward['points_cost'];
                    if ((int) $account['points_balance'] < $cost) {
                        throw new LoyaltyApiProblem('El cliente no tiene suficientes puntos para esta recompensa.', 409);
                    }
                    $code = strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
                    $pdo->prepare("INSERT INTO loyalty_redemptions (redemption_code,customer_id,reward_id,points_spent,status,issued_by,issued_at,notes) VALUES (:code,:customer,:reward,:points,'issued',:user,NOW(),'Canje emitido desde administración')")
                        ->execute(['code' => $code, 'customer' => $customerId, 'reward' => $rewardId, 'points' => $cost, 'user' => (int) $actor['id']]);
                    $redemptionId = (int) $pdo->lastInsertId();
                    $newBalance = (int) $account['points_balance'] - $cost;
                    $pdo->prepare('UPDATE loyalty_accounts SET points_balance=:balance,lifetime_redeemed=lifetime_redeemed+:points,updated_at=NOW() WHERE customer_id=:id')->execute(['balance' => $newBalance, 'points' => $cost, 'id' => $customerId]);
                    $pdo->prepare("INSERT INTO loyalty_transactions (customer_id,reward_id,redemption_id,user_id,transaction_type,points_delta,balance_after,description,created_at) VALUES (:customer,:reward,:redemption,:user,'redeem',:delta,:balance,:description,NOW())")
                        ->execute(['customer' => $customerId, 'reward' => $rewardId, 'redemption' => $redemptionId, 'user' => (int) $actor['id'], 'delta' => -$cost, 'balance' => $newBalance, 'description' => 'Canje de recompensa: '.$reward['name'].' · '.$code]);
                    $pdo->prepare('UPDATE loyalty_rewards SET redeemed_count=redeemed_count+1,updated_at=NOW() WHERE id=:id')->execute(['id' => $rewardId]);
                    $pdo->commit();
                    audit_log($pdo, (int) $actor['id'], 'loyalty.reward_redeemed', ['customer_id' => (int) $customerId, 'reward_id' => (int) $rewardId, 'redemption_code' => $code, 'points' => $cost]);
                    json_response(['ok' => true, 'message' => 'Recompensa emitida. Registra el código al momento de usarla.', 'redemption_code' => $code, 'points_spent' => $cost, 'balance' => $newBalance], 201);
                } catch (Throwable $e) {
                    if ($e instanceof HttpResponseException) {
                        throw $e;
                    } if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    } throw $e;
                }
            }
            if ($action === 'redemption.status') {
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $status = (string) ($body['status'] ?? '');
                if (! $id || $id < 1 || ! in_array($status, ['used', 'cancelled'], true)) {
                    throw new LoyaltyApiProblem('Canje o estado no válido.');
                }
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('SELECT * FROM loyalty_redemptions WHERE id=:id FOR UPDATE');
                    $stmt->execute(['id' => $id]);
                    $redemption = $stmt->fetch();
                    if (! $redemption) {
                        throw new LoyaltyApiProblem('Canje no encontrado.', 404);
                    }
                    if ($redemption['status'] !== 'issued') {
                        throw new LoyaltyApiProblem('Solo se pueden marcar como usado o cancelar los vales emitidos que no se han usado.', 409);
                    }
                    if ($status === 'used') {
                        $pdo->prepare("UPDATE loyalty_redemptions SET status='used',used_by=:user,used_at=NOW() WHERE id=:id")->execute(['user' => (int) $actor['id'], 'id' => $id]);
                    } else {
                        $customerId = (int) $redemption['customer_id'];
                        $account = $this->loyalty_get_account($pdo, $customerId, true);
                        $points = (int) $redemption['points_spent'];
                        $newBalance = (int) $account['points_balance'] + $points;
                        $pdo->prepare("UPDATE loyalty_redemptions SET status='cancelled',cancelled_at=NOW(),notes=CONCAT(COALESCE(notes,''),' · Cancelado por ',:user_name) WHERE id=:id")->execute(['user_name' => (string) $actor['name'], 'id' => $id]);
                        $pdo->prepare('UPDATE loyalty_accounts SET points_balance=:balance,lifetime_redeemed=GREATEST(0,lifetime_redeemed-:points),updated_at=NOW() WHERE customer_id=:id')->execute(['balance' => $newBalance, 'points' => $points, 'id' => $customerId]);
                        $pdo->prepare("INSERT INTO loyalty_transactions (customer_id,reward_id,redemption_id,user_id,transaction_type,points_delta,balance_after,description,created_at) VALUES (:customer,:reward,:redemption,:user,'reward_cancel',:delta,:balance,:description,NOW())")
                            ->execute(['customer' => $customerId, 'reward' => (int) $redemption['reward_id'], 'redemption' => $id, 'user' => (int) $actor['id'], 'delta' => $points, 'balance' => $newBalance, 'description' => 'Devolución de puntos por cancelar vale '.$redemption['redemption_code']]);
                        $pdo->prepare('UPDATE loyalty_rewards SET redeemed_count=GREATEST(0,redeemed_count-1),updated_at=NOW() WHERE id=:id')->execute(['id' => (int) $redemption['reward_id']]);
                    }
                    $pdo->commit();
                    audit_log($pdo, (int) $actor['id'], 'loyalty.redemption_status_changed', ['redemption_id' => (int) $id, 'status' => $status]);
                    json_response(['ok' => true, 'message' => $status === 'used' ? 'Vale marcado como utilizado.' : 'Canje cancelado y puntos devueltos.']);
                } catch (Throwable $e) {
                    if ($e instanceof HttpResponseException) {
                        throw $e;
                    } if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    } throw $e;
                }
            }
            throw new LoyaltyApiProblem('Acción de fidelización no válida.', 404);
        } catch (LoyaltyApiProblem $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], $e->httpStatus);
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                json_response(['ok' => false, 'message' => 'El código de recompensa ya existe o hay registros relacionados.'], 409);
            }
            error_log('[DESEGO loyalty] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo completar la operación de fidelización. Comprueba la migración del Paso 9.'], 500);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            error_log('[DESEGO loyalty] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo completar la operación. Revisa el registro del servidor.'], 500);
        }

        return response()->noContent();
    }

    protected function loyalty_datetime(?string $raw, bool $nullable = true): ?string
    {
        $value = trim((string) $raw);
        if ($value === '' && $nullable) {
            return null;
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone('UTC'));
        if (! $dt || $dt->format('Y-m-d\TH:i') !== $value) {
            throw new LoyaltyApiProblem('Ingresa una fecha y hora válida.');
        }

        return $dt->format('Y-m-d H:i:s');
    }

    protected function loyalty_get_account(PDO $pdo, int $customerId, bool $lock = false): array
    {
        $pdo->prepare('INSERT IGNORE INTO loyalty_accounts (customer_id, points_balance, lifetime_earned, lifetime_redeemed, created_at, updated_at) VALUES (:id,0,0,0,NOW(),NOW())')->execute(['id' => $customerId]);
        $sql = 'SELECT customer_id, points_balance, lifetime_earned, lifetime_redeemed FROM loyalty_accounts WHERE customer_id = :id';
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $customerId]);
        $row = $stmt->fetch();
        if (! $row) {
            throw new LoyaltyApiProblem('No se pudo cargar la cuenta de puntos.', 500);
        }
        foreach (['customer_id', 'points_balance', 'lifetime_earned', 'lifetime_redeemed'] as $key) {
            $row[$key] = (int) $row[$key];
        }

        return $row;
    }
}
