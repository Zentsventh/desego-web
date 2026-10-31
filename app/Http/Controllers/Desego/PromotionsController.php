<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Exceptions\Desego\PromotionsApiProblem;
use App\Http\Controllers\Controller;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Exceptions\HttpResponseException;
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

class PromotionsController extends Controller
{
    public function __invoke(): Response
    {
        try {
            $pdo = desego_db();
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            if ($method === 'GET') {
                $actor = require_any_permission(['promotions.view', 'promotions.manage']);
                $view = (string) (request()->query()['view'] ?? 'list');
                if ($view !== 'list') {
                    throw new PromotionsApiProblem('Vista no válida.', 404);
                }
                $rows = $pdo->query(
                    'SELECT p.id, p.name, p.description, p.promotion_type, p.code, p.discount_type, p.discount_value,
                    p.max_discount, p.min_subtotal, p.starts_at, p.ends_at, p.usage_limit, p.usage_count,
                    p.per_customer_limit, p.status, p.created_at, p.updated_at,
                    u.name AS created_by_name,
                    (SELECT COUNT(*) FROM promotion_redemptions pr WHERE pr.promotion_id = p.id) AS redemptions
             FROM promotions p LEFT JOIN users u ON u.id = p.created_by
             ORDER BY p.created_at DESC, p.id DESC LIMIT 250'
                )->fetchAll();
                foreach ($rows as &$row) {
                    $row['id'] = (int) $row['id'];
                    $row['discount_value'] = (float) $row['discount_value'];
                    $row['max_discount'] = $row['max_discount'] === null ? null : (float) $row['max_discount'];
                    $row['min_subtotal'] = (float) $row['min_subtotal'];
                    $row['usage_limit'] = $row['usage_limit'] === null ? null : (int) $row['usage_limit'];
                    $row['usage_count'] = (int) $row['usage_count'];
                    $row['per_customer_limit'] = (int) $row['per_customer_limit'];
                    $row['redemptions'] = (int) $row['redemptions'];
                }
                unset($row);
                json_response(['ok' => true, 'promotions' => $rows, 'can_manage' => in_array('promotions.manage', $actor['permissions'] ?? [], true)]);
            }
            require_method('POST');
            $body = json_body();
            require_csrf($body);
            $actor = require_permission('promotions.manage');
            $action = (string) ($body['action'] ?? '');
            if ($action === 'toggle') {
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $status = (string) ($body['status'] ?? '');
                if (! $id || ! in_array($status, ['active', 'inactive'], true)) {
                    throw new PromotionsApiProblem('Promoción o estado no válido.');
                }
                $stmt = $pdo->prepare('UPDATE promotions SET status = :status, updated_by = :user_id, updated_at = NOW() WHERE id = :id');
                $stmt->execute(['status' => $status, 'user_id' => (int) $actor['id'], 'id' => $id]);
                if ($stmt->rowCount() < 1) {
                    $check = $pdo->prepare('SELECT id FROM promotions WHERE id = :id');
                    $check->execute(['id' => $id]);
                    if (! $check->fetchColumn()) {
                        throw new PromotionsApiProblem('La promoción no existe.', 404);
                    }
                }
                audit_log($pdo, (int) $actor['id'], 'promotion.status_changed', ['promotion_id' => $id, 'status' => $status]);
                json_response(['ok' => true, 'message' => $status === 'active' ? 'Promoción activada.' : 'Promoción desactivada.']);
            }
            if (! in_array($action, ['create', 'update'], true)) {
                throw new PromotionsApiProblem('Acción no válida.', 404);
            }
            $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
            if ($action === 'update' && (! $id || $id < 1)) {
                throw new PromotionsApiProblem('La promoción que deseas editar no es válida.');
            }
            $name = trim((string) ($body['name'] ?? ''));
            $description = trim((string) ($body['description'] ?? ''));
            $type = (string) ($body['promotion_type'] ?? 'coupon');
            $code = strtoupper(trim((string) ($body['code'] ?? '')));
            $discountType = (string) ($body['discount_type'] ?? 'percentage');
            $value = filter_var($body['discount_value'] ?? null, FILTER_VALIDATE_FLOAT);
            $maxDiscountRaw = trim((string) ($body['max_discount'] ?? ''));
            $minSubtotal = filter_var($body['min_subtotal'] ?? 0, FILTER_VALIDATE_FLOAT);
            $usageLimitRaw = trim((string) ($body['usage_limit'] ?? ''));
            $perCustomerLimit = filter_var($body['per_customer_limit'] ?? 1, FILTER_VALIDATE_INT);
            $startsAt = $this->promo_datetime_input($body['starts_at'] ?? '', false);
            $endsAt = $this->promo_datetime_input($body['ends_at'] ?? '', true);
            $maxDiscount = $maxDiscountRaw === '' ? null : filter_var($maxDiscountRaw, FILTER_VALIDATE_FLOAT);
            $usageLimit = $usageLimitRaw === '' ? null : filter_var($usageLimitRaw, FILTER_VALIDATE_INT);
            if (text_length($name) < 3 || text_length($name) > 120 || text_length($description) > 255) {
                throw new PromotionsApiProblem('El nombre debe tener entre 3 y 120 caracteres y la descripción no superar 255.');
            }
            if (! in_array($type, ['coupon', 'automatic'], true) || ! in_array($discountType, ['percentage', 'fixed'], true)) {
                throw new PromotionsApiProblem('Tipo de promoción no válido.');
            }
            if ($type === 'coupon' && ! preg_match('/^[A-Z0-9_-]{3,40}$/', $code)) {
                throw new PromotionsApiProblem('El cupón debe tener de 3 a 40 caracteres: letras, números, guion o guion bajo.');
            }
            if ($type === 'automatic') {
                $code = '';
            }
            if (! is_numeric($value) || (float) $value <= 0 || (float) $value > ($discountType === 'percentage' ? 100 : 1000000)) {
                throw new PromotionsApiProblem($discountType === 'percentage' ? 'El porcentaje debe ser mayor que 0 y máximo 100.' : 'El descuento fijo debe ser mayor que 0.');
            }
            if (! is_numeric($minSubtotal) || (float) $minSubtotal < 0 || (float) $minSubtotal > 1000000) {
                throw new PromotionsApiProblem('El subtotal mínimo no es válido.');
            }
            if ($maxDiscount !== null && (! is_numeric($maxDiscount) || (float) $maxDiscount <= 0 || (float) $maxDiscount > 1000000)) {
                throw new PromotionsApiProblem('El descuento máximo debe estar vacío o ser mayor que cero.');
            }
            if ($usageLimit !== null && (! is_numeric($usageLimit) || (int) $usageLimit < 1 || (int) $usageLimit > 100000000)) {
                throw new PromotionsApiProblem('El límite total debe estar vacío o ser un entero positivo.');
            }
            if (! is_numeric($perCustomerLimit) || (int) $perCustomerLimit < 0 || (int) $perCustomerLimit > 100000) {
                throw new PromotionsApiProblem('El límite por cliente debe ser cero (sin límite) o un entero positivo.');
            }
            if ($endsAt !== null && $endsAt < (string) $startsAt) {
                throw new PromotionsApiProblem('La fecha de fin debe ser posterior al inicio.');
            }
            $params = [
                'name' => $name, 'description' => $description !== '' ? $description : null,
                'promotion_type' => $type, 'code' => $code !== '' ? $code : null,
                'discount_type' => $discountType, 'discount_value' => number_format((float) $value, 2, '.', ''),
                'max_discount' => $maxDiscount === null ? null : number_format((float) $maxDiscount, 2, '.', ''),
                'min_subtotal' => number_format((float) $minSubtotal, 2, '.', ''), 'starts_at' => $startsAt, 'ends_at' => $endsAt,
                'usage_limit' => $usageLimit === null ? null : (int) $usageLimit, 'per_customer_limit' => (int) $perCustomerLimit,
                'user_id' => (int) $actor['id'],
            ];
            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO promotions (name, description, promotion_type, code, discount_type, discount_value, max_discount, min_subtotal, starts_at, ends_at, usage_limit, per_customer_limit, status, created_by, updated_by, created_at, updated_at)
            VALUES (:name,:description,:promotion_type,:code,:discount_type,:discount_value,:max_discount,:min_subtotal,:starts_at,:ends_at,:usage_limit,:per_customer_limit,'active',:created_by,:updated_by,NOW(),NOW())");
                $params['created_by'] = (int) $actor['id'];
                $params['updated_by'] = (int) $actor['id'];
                $stmt->execute($params);
                $newId = (int) $pdo->lastInsertId();
            } else {
                $params['id'] = (int) $id;
                $stmt = $pdo->prepare('UPDATE promotions SET name=:name, description=:description, promotion_type=:promotion_type, code=:code, discount_type=:discount_type, discount_value=:discount_value, max_discount=:max_discount, min_subtotal=:min_subtotal, starts_at=:starts_at, ends_at=:ends_at, usage_limit=:usage_limit, per_customer_limit=:per_customer_limit, updated_by=:user_id, updated_at=NOW() WHERE id=:id');
                $stmt->execute($params);
                $newId = (int) $id;
                if ($stmt->rowCount() < 1) {
                    $check = $pdo->prepare('SELECT id FROM promotions WHERE id=:id');
                    $check->execute(['id' => $newId]);
                    if (! $check->fetchColumn()) {
                        throw new PromotionsApiProblem('La promoción no existe.', 404);
                    }
                }
            }
            audit_log($pdo, (int) $actor['id'], $action === 'create' ? 'promotion.created' : 'promotion.updated', ['promotion_id' => $newId, 'promotion_type' => $type, 'code' => $code]);
            json_response(['ok' => true, 'id' => $newId, 'message' => $action === 'create' ? 'Promoción creada.' : 'Promoción actualizada.']);
        } catch (PromotionsApiProblem $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], $e->httpStatus);
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                json_response(['ok' => false, 'message' => 'Ese código de cupón ya existe o hay datos relacionados que impiden el cambio.'], 409);
            }
            error_log('[DESEGO promotions] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo guardar la promoción. Revisa el registro del servidor.'], 500);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            error_log('[DESEGO promotions] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo completar la operación. Revisa el registro del servidor.'], 500);
        }

        return response()->noContent();
    }

    protected function promo_datetime_input(mixed $raw, bool $nullable): ?string
    {
        $value = trim((string) ($raw ?? ''));
        if ($value === '' && $nullable) {
            return null;
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone('UTC'));
        if (! $dt) {
            throw new PromotionsApiProblem('Ingresa una fecha y hora válida.');
        }

        return $dt->format('Y-m-d H:i:s');
    }
}
