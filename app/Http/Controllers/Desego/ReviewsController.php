<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Exceptions\Desego\ReviewApiProblem;
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
use function App\Support\Desego\require_any_permission;
use function App\Support\Desego\require_csrf;
use function App\Support\Desego\require_method;
use function App\Support\Desego\require_permission;
use function App\Support\Desego\text_length;

class ReviewsController extends Controller
{
    public function __invoke(): Response
    {
        try {
            $pdo = desego_db();
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            if ($method === 'GET') {
                $view = (string) (request()->query()['view'] ?? 'summary');
                if ($view === 'summary') {
                    $rows = $pdo->query("SELECT product_id, COUNT(*) AS review_count, ROUND(AVG(rating),1) AS rating_average FROM product_reviews WHERE status='approved' GROUP BY product_id")->fetchAll();
                    $summary = [];
                    foreach ($rows as $row) {
                        $summary[(string) (int) $row['product_id']] = ['count' => (int) $row['review_count'], 'average' => (float) $row['rating_average']];
                    }
                    json_response(['ok' => true, 'summary' => $summary]);
                }
                if ($view === 'product') {
                    $productId = filter_var(request()->query()['product_id'] ?? null, FILTER_VALIDATE_INT);
                    if (! $productId || $productId < 1) {
                        throw new ReviewApiProblem('Selecciona un producto válido.');
                    }
                    $product = $this->reviews_product($pdo, (int) $productId);
                    $stmt = $pdo->prepare("SELECT id, reviewer_name, rating, title, comment, photo_url, admin_reply, replied_at, helpful_count, created_at FROM product_reviews WHERE product_id=:id AND status='approved' ORDER BY created_at DESC LIMIT 50");
                    $stmt->execute(['id' => $productId]);
                    $reviews = $stmt->fetchAll();
                    foreach ($reviews as &$review) {
                        $names = preg_split('/\s+/', trim((string) $review['reviewer_name'])) ?: [];
                        $review['reviewer_display'] = (string) ($names[0] ?? 'Cliente').(count($names) > 1 ? ' '.$this->mb_substr_fallback((string) $names[count($names) - 1], 0, 1).'.' : '');
                        $review['rating'] = (int) $review['rating'];
                        $review['helpful_count'] = (int) ($review['helpful_count'] ?? 0);
                    }
                    unset($review);
                    $sum = $pdo->prepare("SELECT COUNT(*) AS total, ROUND(AVG(rating),1) AS average FROM product_reviews WHERE product_id=:id AND status='approved'");
                    $sum->execute(['id' => $productId]);
                    $summary = $sum->fetch() ?: ['total' => 0, 'average' => null];
                    json_response(['ok' => true, 'product' => ['id' => (int) $product['id'], 'name' => $product['name']], 'summary' => ['count' => (int) $summary['total'], 'average' => $summary['average'] === null ? null : (float) $summary['average']], 'reviews' => $reviews]);
                }
                if ($view === 'favorites') {
                    $key = $this->reviews_visitor_key(request()->query()['visitor_key'] ?? '');
                    $stmt = $pdo->prepare('SELECT product_id FROM storefront_favorites WHERE visitor_key=:key ORDER BY created_at DESC');
                    $stmt->execute(['key' => $key]);
                    json_response(['ok' => true, 'favorites' => array_map('intval', array_column($stmt->fetchAll(), 'product_id'))]);
                }
                if ($view === 'moderation') {
                    $actor = require_any_permission(['reviews.view', 'reviews.manage']);
                    $status = (string) (request()->query()['status'] ?? 'pending');
                    if (! in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
                        $status = 'pending';
                    }
                    $where = $status === 'all' ? '' : 'WHERE pr.status=:status';
                    $sql = "SELECT pr.id, pr.order_id, pr.product_id, pr.customer_id, pr.reviewer_name, pr.rating, pr.title, pr.comment, pr.photo_url, pr.admin_reply, pr.replied_at, pr.helpful_count, pr.status, pr.created_at, pr.moderated_at, pr.moderator_note, p.name AS product_name, o.order_number, c.phone AS customer_phone, u.name AS moderator_name FROM product_reviews pr INNER JOIN products p ON p.id=pr.product_id INNER JOIN orders o ON o.id=pr.order_id INNER JOIN customers c ON c.id=pr.customer_id LEFT JOIN users u ON u.id=pr.moderated_by $where ORDER BY FIELD(pr.status,'pending','approved','rejected'), pr.created_at DESC LIMIT 300";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($status === 'all' ? [] : ['status' => $status]);
                    $reviews = $stmt->fetchAll();
                    foreach ($reviews as &$review) {
                        $review['id'] = (int) $review['id'];
                        $review['rating'] = (int) $review['rating'];
                    }
                    unset($review);
                    $summaryRows = $pdo->query('SELECT status, COUNT(*) AS total FROM product_reviews GROUP BY status')->fetchAll();
                    $summary = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
                    foreach ($summaryRows as $summaryRow) {
                        $summary[(string) $summaryRow['status']] = (int) $summaryRow['total'];
                    }
                    $canManage = in_array('reviews.manage', $actor['permissions'] ?? [], true);
                    json_response(['ok' => true, 'reviews' => $reviews, 'status' => $status, 'summary' => $summary, 'can_manage' => $canManage]);
                }
                throw new ReviewApiProblem('Vista de reseñas no válida.', 404);
            }
            require_method('POST');
            $body = json_body();
            require_csrf($body);
            $action = (string) ($body['action'] ?? '');
            if ($action === 'favorite.toggle') {
                $key = $this->reviews_visitor_key($body['visitor_key'] ?? '');
                $productId = filter_var($body['product_id'] ?? null, FILTER_VALIDATE_INT);
                if (! $productId || $productId < 1) {
                    throw new ReviewApiProblem('Selecciona un producto válido.');
                }
                $this->reviews_product($pdo, (int) $productId);
                $exists = $pdo->prepare('SELECT 1 FROM storefront_favorites WHERE visitor_key=:key AND product_id=:product LIMIT 1');
                $exists->execute(['key' => $key, 'product' => $productId]);
                if ($exists->fetchColumn()) {
                    $pdo->prepare('DELETE FROM storefront_favorites WHERE visitor_key=:key AND product_id=:product')->execute(['key' => $key, 'product' => $productId]);
                    $favorite = false;
                } else {
                    $count = $pdo->prepare('SELECT COUNT(*) FROM storefront_favorites WHERE visitor_key=:key');
                    $count->execute(['key' => $key]);
                    if ((int) $count->fetchColumn() >= 200) {
                        throw new ReviewApiProblem('Has llegado al máximo de 200 favoritos.');
                    }
                    $pdo->prepare('INSERT INTO storefront_favorites (visitor_key,product_id,created_at) VALUES (:key,:product,NOW())')->execute(['key' => $key, 'product' => $productId]);
                    $favorite = true;
                }
                audit_log($pdo, null, 'storefront.favorite_toggled', ['product_id' => (int) $productId, 'favorite' => $favorite]);
                json_response(['ok' => true, 'favorite' => $favorite, 'message' => $favorite ? 'Añadido a tus favoritos.' : 'Quitado de tus favoritos.']);
            }
            if ($action === 'review.submit') {
                $orderNumber = strtoupper(trim((string) ($body['order_number'] ?? '')));
                $phone = trim((string) ($body['customer_phone'] ?? ''));
                $phoneDigits = $this->reviews_digits($phone);
                $productId = filter_var($body['product_id'] ?? null, FILTER_VALIDATE_INT);
                $rating = filter_var($body['rating'] ?? null, FILTER_VALIDATE_INT);
                $title = trim((string) ($body['title'] ?? ''));
                $comment = trim((string) ($body['comment'] ?? ''));
                if ($orderNumber === '' || strlen($orderNumber) > 24) {
                    throw new ReviewApiProblem('Ingresa el código del pedido tal como aparece en tu comprobante.');
                }
                if (strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) {
                    throw new ReviewApiProblem('Ingresa el teléfono utilizado al comprar.');
                }
                if (! $productId || $productId < 1) {
                    throw new ReviewApiProblem('Selecciona un producto para reseñar.');
                }
                if (! $rating || $rating < 1 || $rating > 5) {
                    throw new ReviewApiProblem('La calificación debe estar entre 1 y 5 estrellas.');
                }
                if (text_length($title) > 120) {
                    throw new ReviewApiProblem('El título no puede superar 120 caracteres.');
                }
                if (text_length($comment) < 10 || text_length($comment) > 1000) {
                    throw new ReviewApiProblem('La reseña debe tener entre 10 y 1000 caracteres.');
                }
                $verify = $pdo->prepare("SELECT o.id AS order_id,o.order_number,o.customer_id,c.full_name,c.phone FROM orders o INNER JOIN customers c ON c.id=o.customer_id INNER JOIN order_items oi ON oi.order_id=o.id WHERE UPPER(o.order_number)=:number AND o.status='Entregado' AND o.payment_status='Pagado' AND oi.product_id=:product LIMIT 1");
                $verify->execute(['number' => $orderNumber, 'product' => $productId]);
                $purchase = $verify->fetch();
                if (! $purchase || $this->reviews_digits((string) $purchase['phone']) !== $phoneDigits) {
                    throw new ReviewApiProblem('No encontramos una compra entregada y pagada de ese producto con ese código y teléfono.', 403);
                }
                try {
                    $stmt = $pdo->prepare("INSERT INTO product_reviews (order_id,product_id,customer_id,reviewer_name,rating,title,comment,status,created_at,updated_at) VALUES (:order,:product,:customer,:name,:rating,:title,:comment,'pending',NOW(),NOW())");
                    $stmt->execute(['order' => (int) $purchase['order_id'], 'product' => (int) $productId, 'customer' => (int) $purchase['customer_id'], 'name' => (string) $purchase['full_name'], 'rating' => (int) $rating, 'title' => $title !== '' ? $title : null, 'comment' => $comment]);
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') {
                        throw new ReviewApiProblem('Ya enviaste una reseña para este producto y pedido.', 409);
                    }
                    throw $e;
                }
                audit_log($pdo, null, 'storefront.review_submitted', ['order_id' => (int) $purchase['order_id'], 'product_id' => (int) $productId, 'rating' => (int) $rating]);
                json_response(['ok' => true, 'status' => 'pending', 'message' => 'Gracias por tu reseña. Se mostrará públicamente después de la moderación.'], 201);
            }
            if ($action === 'review.moderate') {
                $actor = require_permission('reviews.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $status = (string) ($body['status'] ?? '');
                $note = trim((string) ($body['moderator_note'] ?? ''));
                if (! $id || $id < 1 || ! in_array($status, ['approved', 'rejected'], true)) {
                    throw new ReviewApiProblem('Selecciona una reseña y un estado válidos.');
                }
                if (text_length($note) > 500) {
                    throw new ReviewApiProblem('La nota de moderación supera los 500 caracteres.');
                }
                $stmt = $pdo->prepare('UPDATE product_reviews SET status=:status,moderated_by=:user,moderated_at=NOW(),moderator_note=:note,updated_at=NOW() WHERE id=:id');
                $stmt->execute(['status' => $status, 'user' => (int) $actor['id'], 'note' => $note !== '' ? $note : null, 'id' => $id]);
                if ($stmt->rowCount() < 1) {
                    $chk = $pdo->prepare('SELECT id FROM product_reviews WHERE id=:id');
                    $chk->execute(['id' => $id]);
                    if (! $chk->fetch()) {
                        throw new ReviewApiProblem('La reseña no existe.', 404);
                    }
                }
                audit_log($pdo, (int) $actor['id'], 'product_review.moderated', ['review_id' => (int) $id, 'status' => $status]);
                json_response(['ok' => true, 'message' => $status === 'approved' ? 'Reseña aprobada.' : 'Reseña rechazada.']);
            }
            if ($action === 'review.reply') {
                $actor = require_permission('reviews.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $reply = trim((string) ($body['admin_reply'] ?? ''));
                if (! $id || $id < 1 || text_length($reply) > 1000) {
                    throw new ReviewApiProblem('Revisa el identificador de la reseña y la respuesta (máximo 1000 caracteres).');
                }
                $pdo->prepare('UPDATE product_reviews SET admin_reply=:reply, replied_at=:replied_at, updated_at=NOW() WHERE id=:id')
                    ->execute(['reply' => $reply !== '' ? $reply : null, 'replied_at' => $reply !== '' ? gmdate('Y-m-d H:i:s') : null, 'id' => $id]);
                audit_log($pdo, (int) $actor['id'], 'product_review.replied', ['review_id' => (int) $id]);
                json_response(['ok' => true, 'message' => $reply !== '' ? 'Respuesta publicada en la reseña.' : 'Respuesta eliminada.']);
            }
            if ($action === 'review.helpful') {
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                if (! $id || $id < 1) {
                    throw new ReviewApiProblem('Reseña no válida.');
                }
                $voted = (array) (session()->get('helpful_reviews') ?? []);
                if (! in_array((int) $id, $voted, true)) {
                    $pdo->prepare('UPDATE product_reviews SET helpful_count = COALESCE(helpful_count, 0) + 1 WHERE id = :id AND status = \'approved\'')->execute(['id' => $id]);
                    $voted[] = (int) $id;
                    session()->put('helpful_reviews', array_slice($voted, -100));
                }
                json_response(['ok' => true, 'message' => '¡Gracias por tu valoración!']);
            }
            throw new ReviewApiProblem('Acción de reseñas no válida.', 404);
        } catch (ReviewApiProblem $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], $e->httpStatus);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            error_log('[DESEGO reviews] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo procesar la solicitud de reseñas. Revisa el registro del servidor.'], 500);
        }

        return response()->noContent();
    }

    protected function reviews_visitor_key(mixed $raw): string
    {
        $key = strtolower(trim((string) $raw));
        if (! preg_match('/^[a-f0-9]{64}$/', $key)) {
            throw new ReviewApiProblem('La identidad de favoritos no es válida. Recarga la tienda.', 422);
        }

        return $key;
    }

    protected function reviews_digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    protected function reviews_product(PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare('SELECT id, name, status FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();
        if (! $product) {
            throw new ReviewApiProblem('No se encontró el producto.', 404);
        }

        return $product;
    }

    protected function mb_substr_fallback(string $value, int $start, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, $start, $length, 'UTF-8') : substr($value, $start, $length);
    }
}
