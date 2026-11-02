<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use InvalidArgumentException;
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

class CatalogController extends Controller
{
    public function __invoke(): Response
    {
        try {
            $pdo = desego_db();
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            if ($method === 'GET') {
                $view = (string) (request()->query()['view'] ?? 'menu');
                if ($view === 'menu') {
                    $menuActor = require_permission('menu.view');
                    $categorySql = in_array('categories.manage', $menuActor['permissions'] ?? [], true)
                        ? 'SELECT id, slug, name, description, status FROM categories ORDER BY name'
                        : "SELECT id, slug, name, description, status FROM categories WHERE status = 'active' ORDER BY name";
                    $categories = $pdo->query($categorySql)->fetchAll();
                    $products = $pdo->query(
                        'SELECT p.id, p.code, p.category_id, c.name AS category, c.status AS category_status, p.name, p.description, p.price, p.emoji, p.status, p.is_featured, p.is_popular, p.is_new, p.included_items, p.image_url, p.created_at, p.updated_at
                 FROM products p INNER JOIN categories c ON c.id = p.category_id ORDER BY FIELD(c.slug, \'entradas\', \'ceviches\', \'duos-trios-marinos\', \'sopas\', \'arroces\', \'pastas\', \'frituras\', \'bebidas\', \'cervezas\'), p.code, p.name'
                    )->fetchAll();
                    $recipeRows = $pdo->query(
                        'SELECT pi.product_id, i.id AS ingredient_id, i.name AS ingredient_name, i.unit, pi.quantity,
                        a.id AS allergen_id, a.name AS allergen_name
                 FROM product_ingredients pi
                 INNER JOIN ingredients i ON i.id = pi.ingredient_id
                 LEFT JOIN ingredient_allergens ia ON ia.ingredient_id = i.id
                 LEFT JOIN allergens a ON a.id = ia.allergen_id AND a.status = \'active\'
                 ORDER BY i.name, a.name'
                    )->fetchAll();
                    $mapped = [];
                    $galleryByProduct = [];
                    try {
                        foreach ($pdo->query('SELECT id, product_id, image_url, alt_text, sort_order FROM product_images ORDER BY sort_order, id')->fetchAll() as $imgRow) {
                            $galleryByProduct[(int) $imgRow['product_id']][] = [
                                'id' => (int) $imgRow['id'],
                                'image_url' => (string) $imgRow['image_url'],
                                'alt_text' => (string) ($imgRow['alt_text'] ?? ''),
                                'sort_order' => (int) ($imgRow['sort_order'] ?? 0),
                            ];
                        }
                    } catch (Throwable) {
                    }
                    $optionGroupsByProduct = [];
                    try {
                        $groups = $pdo->query('SELECT id, product_id, name, is_required, max_selections, sort_order, is_active FROM product_option_groups ORDER BY sort_order, id')->fetchAll();
                        $optionsByGroup = [];
                        foreach ($pdo->query('SELECT id, group_id, name, price_delta, is_available, sort_order FROM product_options ORDER BY sort_order, id')->fetchAll() as $optRow) {
                            $optionsByGroup[(int) $optRow['group_id']][] = [
                                'id' => (int) $optRow['id'],
                                'name' => (string) $optRow['name'],
                                'price_delta' => (float) $optRow['price_delta'],
                                'is_available' => (bool) $optRow['is_available'],
                                'sort_order' => (int) ($optRow['sort_order'] ?? 0),
                            ];
                        }
                        foreach ($groups as $grpRow) {
                            $gid = (int) $grpRow['id'];
                            $optionGroupsByProduct[(int) $grpRow['product_id']][] = [
                                'id' => $gid,
                                'name' => (string) $grpRow['name'],
                                'is_required' => (bool) $grpRow['is_required'],
                                'max_selections' => (int) $grpRow['max_selections'],
                                'is_active' => (bool) $grpRow['is_active'],
                                'options' => $optionsByGroup[$gid] ?? [],
                            ];
                        }
                    } catch (Throwable) {
                    }
                    foreach ($products as &$product) {
                        $product['id'] = (int) $product['id'];
                        $product['category_id'] = (int) $product['category_id'];
                        $product['price'] = (float) $product['price'];
                        $product['is_featured'] = (bool) ($product['is_featured'] ?? false);
                        $product['is_popular'] = (bool) ($product['is_popular'] ?? false);
                        $product['is_new'] = (bool) ($product['is_new'] ?? false);
                        $product['ingredients'] = [];
                        $product['allergen_list'] = [];
                        $product['gallery'] = $galleryByProduct[$product['id']] ?? [];
                        $product['option_groups'] = $optionGroupsByProduct[$product['id']] ?? [];
                        $mapped[$product['id']] = &$product;
                    }
                    unset($product);
                    foreach ($recipeRows as $row) {
                        $pid = (int) $row['product_id'];
                        if (! isset($mapped[$pid])) {
                            continue;
                        }
                        $ingredientId = (int) $row['ingredient_id'];
                        if (! isset($mapped[$pid]['ingredients'][$ingredientId])) {
                            $mapped[$pid]['ingredients'][$ingredientId] = [
                                'ingredient_id' => $ingredientId,
                                'name' => $row['ingredient_name'],
                                'unit' => $row['unit'],
                                'quantity' => (float) $row['quantity'],
                            ];
                        }
                        if (! empty($row['allergen_id'])) {
                            $mapped[$pid]['allergen_list'][(int) $row['allergen_id']] = $row['allergen_name'];
                        }
                    }
                    foreach ($products as &$product) {
                        $product['ingredients'] = array_values($product['ingredients']);
                        $product['allergen_list'] = array_values($product['allergen_list']);
                        $product['allergens'] = $product['allergen_list'] ? implode(', ', $product['allergen_list']) : 'Sin alérgenos registrados';
                        $product['safe'] = ! $product['allergen_list'];
                    }
                    unset($product, $mapped);
                    json_response(['ok' => true, 'categories' => $categories, 'products' => $products]);
                }
                if ($view === 'categories') {
                    require_permission('categories.manage');
                    $categories = $pdo->query(
                        'SELECT c.id, c.slug, c.name, c.description, c.status, c.created_at,
                        (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
                 FROM categories c ORDER BY c.name'
                    )->fetchAll();
                    foreach ($categories as &$row) {
                        $row['id'] = (int) $row['id'];
                        $row['product_count'] = (int) $row['product_count'];
                    }
                    unset($row);
                    json_response(['ok' => true, 'categories' => $categories]);
                }
                if ($view === 'ingredients') {
                    require_any_permission(['ingredients.manage', 'allergens.manage', 'inventory.view', 'inventory.manage', 'products.manage']);
                    $ingredients = $pdo->query(
                        'SELECT i.id, i.name, i.description, i.unit, i.stock_quantity, i.min_stock, i.cost_per_unit, i.status,
                        GROUP_CONCAT(DISTINCT a.name ORDER BY a.name SEPARATOR \'||\') AS allergen_names,
                        GROUP_CONCAT(DISTINCT a.id ORDER BY a.id SEPARATOR \'||\') AS allergen_ids
                 FROM ingredients i
                 LEFT JOIN ingredient_allergens ia ON ia.ingredient_id = i.id
                 LEFT JOIN allergens a ON a.id = ia.allergen_id
                 GROUP BY i.id ORDER BY i.name'
                    )->fetchAll();
                    foreach ($ingredients as &$row) {
                        $row['id'] = (int) $row['id'];
                        $row['stock_quantity'] = (float) $row['stock_quantity'];
                        $row['min_stock'] = (float) $row['min_stock'];
                        $row['cost_per_unit'] = (float) $row['cost_per_unit'];
                        $row['allergen_names'] = $row['allergen_names'] ? explode('||', $row['allergen_names']) : [];
                        $row['allergen_ids'] = $row['allergen_ids'] ? array_map('intval', explode('||', $row['allergen_ids'])) : [];
                    }
                    unset($row);
                    $allergens = $pdo->query('SELECT id, name, description, status FROM allergens ORDER BY name')->fetchAll();
                    foreach ($allergens as &$row) {
                        $row['id'] = (int) $row['id'];
                    }
                    unset($row);
                    json_response(['ok' => true, 'ingredients' => $ingredients, 'allergens' => $allergens]);
                }
                if ($view === 'allergens') {
                    require_any_permission(['allergens.manage', 'ingredients.manage', 'products.manage']);
                    $allergens = $pdo->query('SELECT id, name, description, status FROM allergens ORDER BY name')->fetchAll();
                    foreach ($allergens as &$row) {
                        $row['id'] = (int) $row['id'];
                    }
                    unset($row);
                    json_response(['ok' => true, 'allergens' => $allergens]);
                }
                json_response(['ok' => false, 'message' => 'Vista de catálogo no válida.'], 404);
            }
            require_method('POST');
            $body = json_body();
            require_csrf($body);
            $action = (string) ($body['action'] ?? '');
            $actor = null;
            if (str_starts_with($action, 'category.')) {
                $actor = require_permission('categories.manage');
                if ($action === 'category.create' || $action === 'category.update') {
                    $name = trim((string) ($body['name'] ?? ''));
                    $description = trim((string) ($body['description'] ?? ''));
                    if (text_length($name) < 2 || text_length($name) > 80 || text_length($description) > 255) {
                        json_response(['ok' => false, 'message' => 'El nombre debe tener entre 2 y 80 caracteres y la descripción hasta 255.'], 422);
                    }
                    if ($action === 'category.create') {
                        $slug = $this->unique_slug($pdo, 'categories', $this->catalog_slug($name));
                        $stmt = $pdo->prepare('INSERT INTO categories (slug, name, description, status) VALUES (:slug, :name, :description, \'active\')');
                        try {
                            $stmt->execute(['slug' => $slug, 'name' => $name, 'description' => $description ?: null]);
                        } catch (PDOException $e) {
                            if ((string) $e->getCode() === '23000') {
                                json_response(['ok' => false, 'message' => 'Ya existe una categoría con ese nombre.'], 409);
                            }
                            throw $e;
                        }
                        audit_log($pdo, (int) $actor['id'], 'categories.created', ['category_id' => (int) $pdo->lastInsertId(), 'name' => $name]);
                        json_response(['ok' => true, 'message' => 'Categoría creada.'], 201);
                    }
                    $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                    if (! $id) {
                        json_response(['ok' => false, 'message' => 'Categoría no válida.'], 422);
                    }
                    $slug = $this->unique_slug($pdo, 'categories', $this->catalog_slug($name), (int) $id);
                    $stmt = $pdo->prepare('UPDATE categories SET slug = :slug, name = :name, description = :description, updated_at = NOW() WHERE id = :id');
                    try {
                        $stmt->execute(['slug' => $slug, 'name' => $name, 'description' => $description ?: null, 'id' => $id]);
                    } catch (PDOException $e) {
                        if ((string) $e->getCode() === '23000') {
                            json_response(['ok' => false, 'message' => 'Ya existe otra categoría con ese nombre.'], 409);
                        }
                        throw $e;
                    }
                    if (! $stmt->rowCount()) {
                        $exists = $pdo->prepare('SELECT id FROM categories WHERE id = :id');
                        $exists->execute(['id' => $id]);
                        if (! $exists->fetch()) {
                            json_response(['ok' => false, 'message' => 'Categoría no encontrada.'], 404);
                        }
                    }
                    audit_log($pdo, (int) $actor['id'], 'categories.updated', ['category_id' => (int) $id]);
                    json_response(['ok' => true, 'message' => 'Categoría actualizada.']);
                }
                if ($action === 'category.status') {
                    $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                    $status = (string) ($body['status'] ?? '');
                    if (! $id || ! in_array($status, ['active', 'inactive'], true)) {
                        json_response(['ok' => false, 'message' => 'Datos de categoría no válidos.'], 422);
                    }
                    $stmt = $pdo->prepare('UPDATE categories SET status = :status, updated_at = NOW() WHERE id = :id');
                    $stmt->execute(['status' => $status, 'id' => $id]);
                    audit_log($pdo, (int) $actor['id'], 'categories.status_changed', ['category_id' => (int) $id, 'status' => $status]);
                    json_response(['ok' => true, 'message' => $status === 'active' ? 'Categoría activada.' : 'Categoría desactivada.']);
                }
            }
            if (str_starts_with($action, 'product.')) {
                $actor = require_permission('products.manage');
                if ($action === 'product.create' || $action === 'product.update') {
                    $existingProduct = null;
                    $id = null;
                    if ($action === 'product.update') {
                        $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                        if (! $id) {
                            json_response(['ok' => false, 'message' => 'Producto no válido.'], 422);
                        }
                        $existingStmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
                        $existingStmt->execute(['id' => $id]);
                        $existingProduct = $existingStmt->fetch();
                        if (! $existingProduct) {
                            json_response(['ok' => false, 'message' => 'Producto no encontrado.'], 404);
                        }
                    }

                    $code = array_key_exists('code', $body)
                        ? (trim((string) $body['code']) ?: null)
                        : ($existingProduct['code'] ?? null);
                    $name = trim((string) ($body['name'] ?? ($existingProduct['name'] ?? '')));
                    $includedItems = array_key_exists('included_items', $body)
                        ? (trim((string) $body['included_items']) ?: null)
                        : ($existingProduct['included_items'] ?? null);
                    $imageUrl = array_key_exists('image_url', $body)
                        ? (trim((string) $body['image_url']) ?: null)
                        : ($existingProduct['image_url'] ?? null);
                    $isFeatured = array_key_exists('is_featured', $body)
                        ? (! empty($body['is_featured']) ? 1 : 0)
                        : (int) ($existingProduct['is_featured'] ?? 0);
                    $isPopular = array_key_exists('is_popular', $body)
                        ? (! empty($body['is_popular']) ? 1 : 0)
                        : (int) ($existingProduct['is_popular'] ?? 0);
                    $isNew = array_key_exists('is_new', $body)
                        ? (! empty($body['is_new']) ? 1 : 0)
                        : (int) ($existingProduct['is_new'] ?? 0);
                    $description = array_key_exists('description', $body)
                        ? trim((string) $body['description'])
                        : trim((string) ($existingProduct['description'] ?? ''));
                    $price = filter_var($body['price'] ?? ($existingProduct['price'] ?? null), FILTER_VALIDATE_FLOAT);
                    $categoryId = filter_var($body['category_id'] ?? ($existingProduct['category_id'] ?? null), FILTER_VALIDATE_INT);
                    $emoji = trim((string) ($body['emoji'] ?? ($existingProduct['emoji'] ?? '🍽️')));
                    $status = (string) ($body['status'] ?? ($existingProduct['status'] ?? 'available'));
                    if (text_length($name) < 2 || text_length($name) > 100 || text_length($description) > 500 || ! is_numeric($price) || (float) $price < 0 || (float) $price > 99999999 || ! $categoryId || ! in_array($status, ['available', 'unavailable'], true)) {
                        json_response(['ok' => false, 'message' => 'Revisa el nombre, categoría, precio y estado del producto.'], 422);
                    }
                    if ($code !== null && text_length($code) > 32) {
                        json_response(['ok' => false, 'message' => 'El código del plato no debe superar los 32 caracteres.'], 422);
                    }
                    $cat = $pdo->prepare("SELECT id FROM categories WHERE id = :id AND status = 'active'");
                    $cat->execute(['id' => $categoryId]);
                    if (! $cat->fetch()) {
                        json_response(['ok' => false, 'message' => 'Selecciona una categoría activa.'], 422);
                    }
                    if ($emoji === '') {
                        $emoji = '🍽️';
                    }
                    $emojiChars = preg_split('//u', $emoji, -1, PREG_SPLIT_NO_EMPTY) ?: ['🍽️'];
                    $emoji = implode('', array_slice($emojiChars, 0, 8));
                    $pdo->beginTransaction();
                    try {
                        if ($action === 'product.create') {
                            $slug = $this->unique_slug($pdo, 'products', $this->catalog_slug($name));
                            $stmt = $pdo->prepare('INSERT INTO products (code, category_id, slug, name, description, price, emoji, status, included_items, image_url, is_featured, is_popular, is_new) VALUES (:code, :category_id, :slug, :name, :description, :price, :emoji, :status, :included_items, :image_url, :is_featured, :is_popular, :is_new)');
                            $stmt->execute(['code' => $code, 'category_id' => $categoryId, 'slug' => $slug, 'name' => $name, 'description' => $description ?: null, 'price' => number_format((float) $price, 2, '.', ''), 'emoji' => $emoji, 'status' => $status, 'included_items' => $includedItems, 'image_url' => $imageUrl, 'is_featured' => $isFeatured, 'is_popular' => $isPopular, 'is_new' => $isNew]);
                            $id = (int) $pdo->lastInsertId();
                        } else {
                            $stmt = $pdo->prepare('UPDATE products SET code = :code, category_id = :category_id, name = :name, description = :description, price = :price, emoji = :emoji, status = :status, included_items = :included_items, image_url = :image_url, is_featured = :is_featured, is_popular = :is_popular, is_new = :is_new, updated_at = NOW() WHERE id = :id');
                            $stmt->execute(['code' => $code, 'category_id' => $categoryId, 'name' => $name, 'description' => $description ?: null, 'price' => number_format((float) $price, 2, '.', ''), 'emoji' => $emoji, 'status' => $status, 'included_items' => $includedItems, 'image_url' => $imageUrl, 'is_featured' => $isFeatured, 'is_popular' => $isPopular, 'is_new' => $isNew, 'id' => $id]);
                        }
                        if ($action === 'product.create' || array_key_exists('ingredients', $body)) {
                            $this->replace_product_ingredients($pdo, (int) $id, $body['ingredients'] ?? []);
                        }
                        if (array_key_exists('gallery', $body)) {
                            $this->replace_product_gallery($pdo, (int) $id, $body['gallery']);
                        }
                        if (array_key_exists('option_groups', $body)) {
                            $this->replace_product_option_groups($pdo, (int) $id, $body['option_groups']);
                        }
                        $pdo->commit();
                        audit_log($pdo, (int) $actor['id'], $action === 'product.create' ? 'products.created' : 'products.updated', ['product_id' => (int) $id, 'name' => $name]);
                        json_response(['ok' => true, 'message' => $action === 'product.create' ? 'Producto creado.' : 'Producto actualizado.'], $action === 'product.create' ? 201 : 200);
                    } catch (Throwable $e) {
                        if ($e instanceof HttpResponseException) {
                            throw $e;
                        }
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
                            json_response(['ok' => false, 'message' => 'Ya existe un producto con esos datos o código comercial.'], 409);
                        }
                        throw $e;
                    }
                }
                if ($action === 'product.status') {
                    $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                    $status = (string) ($body['status'] ?? '');
                    if (! $id || ! in_array($status, ['available', 'unavailable'], true)) {
                        json_response(['ok' => false, 'message' => 'Datos de producto no válidos.'], 422);
                    }
                    $stmt = $pdo->prepare('UPDATE products SET status = :status, updated_at = NOW() WHERE id = :id');
                    $stmt->execute(['status' => $status, 'id' => $id]);
                    audit_log($pdo, (int) $actor['id'], 'products.status_changed', ['product_id' => (int) $id, 'status' => $status]);
                    json_response(['ok' => true, 'message' => $status === 'available' ? 'Producto disponible.' : 'Producto marcado como no disponible.']);
                }
            }
            if (str_starts_with($action, 'ingredient.')) {
                $actor = require_permission('ingredients.manage');
                if ($action === 'ingredient.create' || $action === 'ingredient.update') {
                    $name = trim((string) ($body['name'] ?? ''));
                    $description = trim((string) ($body['description'] ?? ''));
                    $unit = (string) ($body['unit'] ?? 'g');
                    $minStock = filter_var($body['min_stock'] ?? 0, FILTER_VALIDATE_FLOAT);
                    $cost = filter_var($body['cost_per_unit'] ?? 0, FILTER_VALIDATE_FLOAT);
                    $allergenIds = $this->read_ids($body['allergen_ids'] ?? []);
                    $units = ['g', 'kg', 'ml', 'l', 'unidad', 'porción'];
                    if (text_length($name) < 2 || text_length($name) > 100 || text_length($description) > 255 || ! in_array($unit, $units, true) || ! is_numeric($minStock) || (float) $minStock < 0 || ! is_numeric($cost) || (float) $cost < 0) {
                        json_response(['ok' => false, 'message' => 'Revisa los datos del ingrediente.'], 422);
                    }
                    if ($action === 'ingredient.create') {
                        $initialStock = filter_var($body['initial_stock'] ?? 0, FILTER_VALIDATE_FLOAT);
                        if (! is_numeric($initialStock) || (float) $initialStock < 0) {
                            json_response(['ok' => false, 'message' => 'El stock inicial no puede ser negativo.'], 422);
                        }
                        if ((float) $initialStock > 0 && ! in_array('inventory.manage', $actor['permissions'] ?? [], true)) {
                            json_response(['ok' => false, 'message' => 'Necesitas permiso de inventario para registrar stock inicial.'], 403);
                        }
                        $pdo->beginTransaction();
                        try {
                            $stmt = $pdo->prepare('INSERT INTO ingredients (name, description, unit, stock_quantity, min_stock, cost_per_unit, status) VALUES (:name, :description, :unit, :stock, :min_stock, :cost, \'active\')');
                            $stmt->execute(['name' => $name, 'description' => $description ?: null, 'unit' => $unit, 'stock' => number_format((float) $initialStock, 3, '.', ''), 'min_stock' => number_format((float) $minStock, 3, '.', ''), 'cost' => number_format((float) $cost, 4, '.', '')]);
                            $id = (int) $pdo->lastInsertId();
                            $this->replace_ingredient_allergens($pdo, $id, $allergenIds);
                            if ((float) $initialStock > 0) {
                                $movement = $pdo->prepare("INSERT INTO inventory_movements (ingredient_id, user_id, movement_type, quantity_delta, previous_quantity, new_quantity, unit_cost, notes, created_at) VALUES (:ingredient, :user, 'initial', :delta, 0, :new, :cost, 'Stock inicial del ingrediente', NOW())");
                                $movement->execute(['ingredient' => $id, 'user' => (int) $actor['id'], 'delta' => $initialStock, 'new' => $initialStock, 'cost' => $cost]);
                            }
                            $pdo->commit();
                            audit_log($pdo, (int) $actor['id'], 'ingredients.created', ['ingredient_id' => $id, 'name' => $name]);
                            json_response(['ok' => true, 'message' => 'Ingrediente creado.'], 201);
                        } catch (Throwable $e) {
                            if ($e instanceof HttpResponseException) {
                                throw $e;
                            }
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
                                json_response(['ok' => false, 'message' => 'Ya existe un ingrediente con ese nombre.'], 409);
                            }
                            throw $e;
                        }
                    }
                    $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                    if (! $id) {
                        json_response(['ok' => false, 'message' => 'Ingrediente no válido.'], 422);
                    }
                    $current = $pdo->prepare('SELECT unit FROM ingredients WHERE id = :id');
                    $current->execute(['id' => $id]);
                    $old = $current->fetch();
                    if (! $old) {
                        json_response(['ok' => false, 'message' => 'Ingrediente no encontrado.'], 404);
                    }
                    if ($old['unit'] !== $unit) {
                        $use = $pdo->prepare('SELECT 1 FROM product_ingredients WHERE ingredient_id = :id LIMIT 1');
                        $use->execute(['id' => $id]);
                        if ($use->fetch()) {
                            json_response(['ok' => false, 'message' => 'No puedes cambiar la unidad porque este ingrediente ya está asociado a recetas.'], 422);
                        }
                    }
                    $pdo->beginTransaction();
                    try {
                        $stmt = $pdo->prepare('UPDATE ingredients SET name = :name, description = :description, unit = :unit, min_stock = :min_stock, cost_per_unit = :cost, updated_at = NOW() WHERE id = :id');
                        $stmt->execute(['name' => $name, 'description' => $description ?: null, 'unit' => $unit, 'min_stock' => number_format((float) $minStock, 3, '.', ''), 'cost' => number_format((float) $cost, 4, '.', ''), 'id' => $id]);
                        $this->replace_ingredient_allergens($pdo, (int) $id, $allergenIds);
                        $pdo->commit();
                        audit_log($pdo, (int) $actor['id'], 'ingredients.updated', ['ingredient_id' => (int) $id]);
                        json_response(['ok' => true, 'message' => 'Ingrediente actualizado.']);
                    } catch (Throwable $e) {
                        if ($e instanceof HttpResponseException) {
                            throw $e;
                        }
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
                            json_response(['ok' => false, 'message' => 'Ya existe un ingrediente con ese nombre.'], 409);
                        }
                        throw $e;
                    }
                }
                if ($action === 'ingredient.status') {
                    $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                    $status = (string) ($body['status'] ?? '');
                    if (! $id || ! in_array($status, ['active', 'inactive'], true)) {
                        json_response(['ok' => false, 'message' => 'Datos de ingrediente no válidos.'], 422);
                    }
                    if ($status === 'inactive') {
                        $linked = $pdo->prepare('SELECT COUNT(*) FROM product_ingredients WHERE ingredient_id = :id');
                        $linked->execute(['id' => $id]);
                        if ((int) $linked->fetchColumn() > 0) {
                            json_response(['ok' => false, 'message' => 'Este ingrediente está asociado a recetas; edítalo o reemplázalo antes de desactivarlo.'], 422);
                        }
                    }
                    $stmt = $pdo->prepare('UPDATE ingredients SET status = :status, updated_at = NOW() WHERE id = :id');
                    $stmt->execute(['status' => $status, 'id' => $id]);
                    audit_log($pdo, (int) $actor['id'], 'ingredients.status_changed', ['ingredient_id' => (int) $id, 'status' => $status]);
                    json_response(['ok' => true, 'message' => $status === 'active' ? 'Ingrediente activado.' : 'Ingrediente desactivado.']);
                }
            }
            if (str_starts_with($action, 'allergen.')) {
                $actor = require_permission('allergens.manage');
                if ($action === 'allergen.create') {
                    $name = trim((string) ($body['name'] ?? ''));
                    $description = trim((string) ($body['description'] ?? ''));
                    if (text_length($name) < 2 || text_length($name) > 80 || text_length($description) > 255) {
                        json_response(['ok' => false, 'message' => 'Revisa el nombre y la descripción del alérgeno.'], 422);
                    }
                    try {
                        $stmt = $pdo->prepare('INSERT INTO allergens (name, description, status) VALUES (:name, :description, \'active\')');
                        $stmt->execute(['name' => $name, 'description' => $description ?: null]);
                    } catch (PDOException $e) {
                        if ((string) $e->getCode() === '23000') {
                            json_response(['ok' => false, 'message' => 'Ese alérgeno ya existe.'], 409);
                        }
                        throw $e;
                    }
                    audit_log($pdo, (int) $actor['id'], 'allergens.created', ['allergen_id' => (int) $pdo->lastInsertId(), 'name' => $name]);
                    json_response(['ok' => true, 'message' => 'Alérgeno creado.'], 201);
                }
                if ($action === 'allergen.status') {
                    $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                    $status = (string) ($body['status'] ?? '');
                    if (! $id || ! in_array($status, ['active', 'inactive'], true)) {
                        json_response(['ok' => false, 'message' => 'Datos de alérgeno no válidos.'], 422);
                    }
                    $stmt = $pdo->prepare('UPDATE allergens SET status = :status, updated_at = NOW() WHERE id = :id');
                    $stmt->execute(['status' => $status, 'id' => $id]);
                    audit_log($pdo, (int) $actor['id'], 'allergens.status_changed', ['allergen_id' => (int) $id, 'status' => $status]);
                    json_response(['ok' => true, 'message' => $status === 'active' ? 'Alérgeno activado.' : 'Alérgeno desactivado.']);
                }
            }
            json_response(['ok' => false, 'message' => 'Acción de catálogo no válida.'], 404);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            error_log('[DESEGO catalog] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo completar la operación. Revisa la base de datos y los permisos de la cuenta.'], 500);
        }

        return response()->noContent();
    }

    protected function catalog_slug(string $value): string
    {
        $value = trim($value);
        if (function_exists('iconv')) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($ascii !== false) {
                $value = $ascii;
            }
        }
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? substr($value, 0, 110) : 'item';
    }

    protected function unique_slug(PDO $pdo, string $table, string $slug, ?int $exceptId = null): string
    {
        if (! in_array($table, ['categories', 'products'], true)) {
            throw new InvalidArgumentException('Tabla no válida para slug.');
        }
        $base = $slug;
        $suffix = 2;
        while (true) {
            $sql = "SELECT id FROM {$table} WHERE slug = :slug";
            $params = ['slug' => $slug];
            if ($exceptId !== null) {
                $sql .= ' AND id <> :id';
                $params['id'] = $exceptId;
            }
            $sql .= ' LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if (! $stmt->fetch()) {
                return $slug;
            }
            $slug = substr($base, 0, 100).'-'.$suffix++;
        }
    }

    protected function read_ids(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }
        $ids = [];
        foreach ($values as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT);
            if ($id && $id > 0) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    protected function replace_ingredient_allergens(PDO $pdo, int $ingredientId, array $allergenIds): void
    {
        if ($allergenIds) {
            $marks = implode(',', array_fill(0, count($allergenIds), '?'));
            $stmt = $pdo->prepare("SELECT id FROM allergens WHERE status = 'active' AND id IN ({$marks})");
            $stmt->execute($allergenIds);
            $found = array_map('intval', array_column($stmt->fetchAll(), 'id'));
            if (count($found) !== count($allergenIds)) {
                json_response(['ok' => false, 'message' => 'Uno o más alérgenos seleccionados no existen o están inactivos.'], 422);
            }
        }
        $delete = $pdo->prepare('DELETE FROM ingredient_allergens WHERE ingredient_id = :id');
        $delete->execute(['id' => $ingredientId]);
        if ($allergenIds) {
            $insert = $pdo->prepare('INSERT INTO ingredient_allergens (ingredient_id, allergen_id) VALUES (:ingredient_id, :allergen_id)');
            foreach ($allergenIds as $allergenId) {
                $insert->execute(['ingredient_id' => $ingredientId, 'allergen_id' => $allergenId]);
            }
        }
    }

    protected function replace_product_ingredients(PDO $pdo, int $productId, mixed $recipe): void
    {
        if (! is_array($recipe)) {
            json_response(['ok' => false, 'message' => 'La receta enviada no es válida.'], 422);
        }
        $rows = [];
        foreach ($recipe as $row) {
            if (! is_array($row)) {
                continue;
            }
            $ingredientId = filter_var($row['ingredient_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($row['quantity'] ?? null, FILTER_VALIDATE_FLOAT);
            if (! $ingredientId || ! is_numeric($quantity) || (float) $quantity <= 0 || (float) $quantity > 1000000) {
                json_response(['ok' => false, 'message' => 'Cada ingrediente seleccionado debe tener una cantidad mayor que cero.'], 422);
            }
            $rows[(int) $ingredientId] = round((float) $quantity, 3);
        }
        if ($rows) {
            $ids = array_keys($rows);
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id FROM ingredients WHERE status = 'active' AND id IN ({$marks})");
            $stmt->execute($ids);
            if (count($stmt->fetchAll()) !== count($ids)) {
                json_response(['ok' => false, 'message' => 'Uno o más ingredientes no existen o están inactivos.'], 422);
            }
        }
        $delete = $pdo->prepare('DELETE FROM product_ingredients WHERE product_id = :id');
        $delete->execute(['id' => $productId]);
        if ($rows) {
            $insert = $pdo->prepare('INSERT INTO product_ingredients (product_id, ingredient_id, quantity) VALUES (:product_id, :ingredient_id, :quantity)');
            foreach ($rows as $ingredientId => $quantity) {
                $insert->execute(['product_id' => $productId, 'ingredient_id' => $ingredientId, 'quantity' => $quantity]);
            }
        }
    }

    protected function replace_product_gallery(PDO $pdo, int $productId, mixed $gallery): void
    {
        if (! is_array($gallery)) {
            return;
        }
        $pdo->prepare('DELETE FROM product_images WHERE product_id = :id')->execute(['id' => $productId]);
        $insert = $pdo->prepare('INSERT INTO product_images (product_id, image_url, alt_text, sort_order, created_at, updated_at) VALUES (:pid, :url, :alt, :sort, NOW(), NOW())');
        $sort = 1;
        foreach (array_slice($gallery, 0, 12) as $item) {
            $url = is_array($item) ? trim((string) ($item['image_url'] ?? '')) : trim((string) $item);
            $alt = is_array($item) ? trim((string) ($item['alt_text'] ?? '')) : '';
            if ($url === '' || text_length($url) > 500) {
                continue;
            }
            $insert->execute([
                'pid' => $productId,
                'url' => $url,
                'alt' => $alt !== '' ? mb_substr($alt, 0, 160) : null,
                'sort' => $sort++,
            ]);
        }
    }

    protected function replace_product_option_groups(PDO $pdo, int $productId, mixed $groups): void
    {
        if (! is_array($groups)) {
            return;
        }
        $pdo->prepare('DELETE FROM product_option_groups WHERE product_id = :id')->execute(['id' => $productId]);
        $groupInsert = $pdo->prepare('INSERT INTO product_option_groups (product_id, name, is_required, max_selections, sort_order, is_active, created_at, updated_at) VALUES (:pid, :name, :req, :max, :sort, :active, NOW(), NOW())');
        $optionInsert = $pdo->prepare('INSERT INTO product_options (group_id, name, price_delta, is_available, sort_order, created_at, updated_at) VALUES (:gid, :name, :delta, :avail, :sort, NOW(), NOW())');
        $groupSort = 1;
        foreach (array_slice($groups, 0, 10) as $group) {
            if (! is_array($group)) {
                continue;
            }
            $groupName = trim((string) ($group['name'] ?? ''));
            if ($groupName === '' || text_length($groupName) > 80) {
                continue;
            }
            $options = is_array($group['options'] ?? null) ? $group['options'] : [];
            $validOptions = [];
            foreach (array_slice($options, 0, 20) as $opt) {
                if (! is_array($opt)) {
                    continue;
                }
                $optName = trim((string) ($opt['name'] ?? ''));
                $delta = filter_var($opt['price_delta'] ?? 0, FILTER_VALIDATE_FLOAT);
                if ($optName === '' || text_length($optName) > 80 || $delta === false || $delta < 0 || $delta > 9999) {
                    continue;
                }
                $validOptions[] = [
                    'name' => $optName,
                    'price_delta' => round((float) $delta, 2),
                    'is_available' => array_key_exists('is_available', $opt) ? (! empty($opt['is_available']) ? 1 : 0) : 1,
                ];
            }
            if (! $validOptions) {
                continue;
            }
            $maxSel = max(1, min(10, (int) ($group['max_selections'] ?? 1)));
            $groupInsert->execute([
                'pid' => $productId,
                'name' => $groupName,
                'req' => ! empty($group['is_required']) ? 1 : 0,
                'max' => $maxSel,
                'sort' => $groupSort++,
                'active' => array_key_exists('is_active', $group) ? (! empty($group['is_active']) ? 1 : 0) : 1,
            ]);
            $gid = (int) $pdo->lastInsertId();
            $optSort = 1;
            foreach ($validOptions as $validOpt) {
                $optionInsert->execute([
                    'gid' => $gid,
                    'name' => $validOpt['name'],
                    'delta' => number_format($validOpt['price_delta'], 2, '.', ''),
                    'avail' => $validOpt['is_available'],
                    'sort' => $optSort++,
                ]);
            }
        }
    }
}
