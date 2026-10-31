<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function App\Support\Desego\audit_log;
use function App\Support\Desego\current_user;
use function App\Support\Desego\desego_add_adjustment_cost_layer;
use function App\Support\Desego\desego_consume_cost_layers;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\json_body;
use function App\Support\Desego\json_response;
use function App\Support\Desego\require_csrf;
use function App\Support\Desego\require_method;
use function App\Support\Desego\require_permission;

class InventoryController extends Controller
{
    public function __invoke(): Response
    {
        try {
            $pdo = desego_db();
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            if ($method === 'GET') {
                require_permission('inventory.view');
                $ingredients = $pdo->query(
                    'SELECT i.id, i.name, i.unit, i.stock_quantity, i.min_stock, i.cost_per_unit, i.status,
                    CASE WHEN i.stock_quantity <= i.min_stock THEN 1 ELSE 0 END AS is_low
             FROM ingredients i ORDER BY is_low DESC, i.name'
                )->fetchAll();
                foreach ($ingredients as &$row) {
                    $row['id'] = (int) $row['id'];
                    $row['stock_quantity'] = (float) $row['stock_quantity'];
                    $row['min_stock'] = (float) $row['min_stock'];
                    $row['cost_per_unit'] = (float) $row['cost_per_unit'];
                    $row['is_low'] = (bool) $row['is_low'];
                }
                unset($row);
                $movements = $pdo->query(
                    'SELECT m.id, m.ingredient_id, i.name AS ingredient_name, i.unit, m.movement_type,
                    m.quantity_delta, m.previous_quantity, m.new_quantity, m.unit_cost,
                    m.reference, m.notes, m.created_at, u.name AS user_name
             FROM inventory_movements m
             INNER JOIN ingredients i ON i.id = m.ingredient_id
             LEFT JOIN users u ON u.id = m.user_id
             ORDER BY m.created_at DESC, m.id DESC LIMIT 100'
                )->fetchAll();
                foreach ($movements as &$row) {
                    $row['id'] = (int) $row['id'];
                    $row['ingredient_id'] = (int) $row['ingredient_id'];
                    $row['quantity_delta'] = (float) $row['quantity_delta'];
                    $row['previous_quantity'] = (float) $row['previous_quantity'];
                    $row['new_quantity'] = (float) $row['new_quantity'];
                    $row['unit_cost'] = (float) $row['unit_cost'];
                }
                unset($row);
                $canManage = in_array('inventory.manage', (current_user()['permissions'] ?? []), true);
                json_response(['ok' => true, 'ingredients' => $ingredients, 'movements' => $movements, 'can_manage' => $canManage]);
            }
            require_method('POST');
            $body = json_body();
            require_csrf($body);
            $actor = require_permission('inventory.manage');
            if (($body['action'] ?? '') !== 'adjust') {
                json_response(['ok' => false, 'message' => 'Acción de inventario no válida.'], 404);
            }
            $ingredientId = filter_var($body['ingredient_id'] ?? null, FILTER_VALIDATE_INT);
            $type = (string) ($body['movement_type'] ?? '');
            $quantity = filter_var($body['quantity'] ?? null, FILTER_VALIDATE_FLOAT);
            $notes = trim((string) ($body['notes'] ?? ''));
            $reference = trim((string) ($body['reference'] ?? ''));
            $allowedTypes = ['entry', 'exit', 'waste', 'adjustment'];
            if (! $ingredientId || ! is_numeric($quantity) || (float) $quantity < 0 || (float) $quantity > 100000000 || ! in_array($type, $allowedTypes, true) || strlen($notes) > 500 || strlen($reference) > 120) {
                json_response(['ok' => false, 'message' => 'Revisa los datos del movimiento.'], 422);
            }
            if ($type !== 'adjustment' && (float) $quantity <= 0) {
                json_response(['ok' => false, 'message' => 'La cantidad debe ser mayor que cero.'], 422);
            }
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('SELECT id, name, stock_quantity, unit, cost_per_unit FROM ingredients WHERE id = :id AND status = \'active\' FOR UPDATE');
                $stmt->execute(['id' => $ingredientId]);
                $ingredient = $stmt->fetch();
                if (! $ingredient) {
                    $pdo->rollBack();
                    json_response(['ok' => false, 'message' => 'Ingrediente no encontrado o inactivo.'], 404);
                }
                $before = (float) $ingredient['stock_quantity'];
                $entered = (float) $quantity;
                if ($type === 'entry') {
                    $after = $before + $entered;
                } elseif ($type === 'exit' || $type === 'waste') {
                    $after = $before - $entered;
                } else {
                    $after = $entered;
                } // Ajuste: la cantidad indicada es el stock contado físicamente.
                if ($after < -0.0005) {
                    $pdo->rollBack();
                    json_response(['ok' => false, 'message' => 'No hay stock suficiente para registrar esa salida.'], 422);
                }
                $after = max(0, round($after, 3));
                $delta = round($after - $before, 3);
                $unitCost = (float) $ingredient['cost_per_unit'];
                $update = $pdo->prepare('UPDATE ingredients SET stock_quantity = :stock, updated_at = NOW() WHERE id = :id');
                $update->execute(['stock' => number_format($after, 3, '.', ''), 'id' => $ingredientId]);
                if ($type === 'entry') {
                    desego_add_adjustment_cost_layer($pdo, (int) $ingredientId, $entered, $unitCost, 'Entrada manual de inventario'.($reference !== '' ? ' · '.$reference : ''));
                } elseif ($type === 'exit' || $type === 'waste') {
                    desego_consume_cost_layers($pdo, (int) $ingredientId, $entered, $unitCost);
                } elseif ($type === 'adjustment') {
                    $difference = round($after - $before, 3);
                    if ($difference > 0) {
                        desego_add_adjustment_cost_layer($pdo, (int) $ingredientId, $difference, $unitCost, 'Ajuste físico de inventario'.($reference !== '' ? ' · '.$reference : ''));
                    } elseif ($difference < 0) {
                        desego_consume_cost_layers($pdo, (int) $ingredientId, abs($difference), $unitCost);
                    }
                }
                $insert = $pdo->prepare(
                    'INSERT INTO inventory_movements
             (ingredient_id, user_id, movement_type, quantity_delta, previous_quantity, new_quantity, unit_cost, reference, notes, created_at)
             VALUES (:ingredient_id, :user_id, :movement_type, :quantity_delta, :previous_quantity, :new_quantity, :unit_cost, :reference, :notes, NOW())'
                );
                $insert->execute([
                    'ingredient_id' => $ingredientId,
                    'user_id' => (int) $actor['id'],
                    'movement_type' => $type,
                    'quantity_delta' => number_format($delta, 3, '.', ''),
                    'previous_quantity' => number_format($before, 3, '.', ''),
                    'new_quantity' => number_format($after, 3, '.', ''),
                    'unit_cost' => number_format($unitCost, 4, '.', ''),
                    'reference' => $reference !== '' ? $reference : null,
                    'notes' => $notes !== '' ? $notes : null,
                ]);
                $pdo->commit();
                audit_log($pdo, (int) $actor['id'], 'inventory.adjusted', [
                    'ingredient_id' => (int) $ingredientId,
                    'movement_type' => $type,
                    'quantity_delta' => $delta,
                    'new_quantity' => $after,
                ]);
                json_response(['ok' => true, 'message' => 'Movimiento guardado y stock actualizado.', 'stock_quantity' => $after]);
            } catch (Throwable $e) {
                if ($e instanceof HttpResponseException) {
                    throw $e;
                }
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            error_log('[DESEGO inventory] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo completar la operación de inventario.'], 500);
        }

        return response()->noContent();
    }
}
