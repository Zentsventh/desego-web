<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Exceptions\Desego\CommunicationsProblem;
use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use PDO;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function App\Support\Desego\audit_log;
use function App\Support\Desego\current_user;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\desego_header;
use function App\Support\Desego\desego_integration_environment;
use function App\Support\Desego\json_body;
use function App\Support\Desego\json_response;
use function App\Support\Desego\require_any_permission;
use function App\Support\Desego\require_csrf;
use function App\Support\Desego\require_permission;
use function App\Support\Desego\text_length;

class CommunicationsController extends Controller
{
    public function __invoke(): Response
    {
        try {
            $pdo = desego_db();
            $actor = current_user();
            if (! $actor) {
                json_response(['ok' => false, 'message' => 'Inicia sesión para continuar.'], 401);
            }
            $perms = $actor['permissions'] ?? [];
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            if ($method === 'GET') {
                $view = (string) (request()->query()['view'] ?? 'notifications');
                if ($view === 'notifications') {
                    require_any_permission(['notifications.view', 'notifications.manage', 'support.create', 'support.manage']);
                    $stmt = $pdo->prepare('SELECT n.id,n.title,n.message,n.category,n.target_view,n.created_at, nr.read_at, (nr.read_at IS NOT NULL) AS is_read, u.name AS author_name FROM notifications n LEFT JOIN notification_reads nr ON nr.notification_id=n.id AND nr.user_id=:uid LEFT JOIN users u ON u.id=n.created_by WHERE (n.recipient_user_id=:recipient OR n.recipient_user_id IS NULL) ORDER BY (nr.read_at IS NULL) DESC,n.created_at DESC,n.id DESC LIMIT 150');
                    $stmt->execute(['uid' => (int) $actor['id'], 'recipient' => (int) $actor['id']]);
                    $rows = $stmt->fetchAll();
                    foreach ($rows as &$row) {
                        $row['id'] = (int) $row['id'];
                        $row['is_read'] = (bool) $row['is_read'];
                    }unset($row);
                    $unread = (int) $pdo->query('SELECT 0')->fetchColumn();
                    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications n LEFT JOIN notification_reads nr ON nr.notification_id=n.id AND nr.user_id=:uid WHERE (n.recipient_user_id=:recipient OR n.recipient_user_id IS NULL) AND nr.read_at IS NULL');
                    $countStmt->execute(['uid' => (int) $actor['id'], 'recipient' => (int) $actor['id']]);
                    $unread = (int) $countStmt->fetchColumn();
                    json_response(['ok' => true, 'notifications' => $rows, 'unread' => $unread, 'can_manage' => in_array('notifications.manage', $perms, true)]);
                }
                if ($view === 'tickets') {
                    require_any_permission(['support.view', 'support.create', 'support.manage']);
                    $manager = in_array('support.view', $perms, true) || in_array('support.manage', $perms, true);
                    $status = (string) (request()->query()['status'] ?? 'all');
                    if (! in_array($status, ['all', 'open', 'in_progress', 'waiting_user', 'resolved', 'closed'], true)) {
                        $status = 'all';
                    }
                    $where = [];
                    $params = [];
                    if (! $manager) {
                        $where[] = 't.created_by=:uid';
                        $params['uid'] = (int) $actor['id'];
                    }
                    if ($status !== 'all') {
                        $where[] = 't.status=:status';
                        $params['status'] = $status;
                    }
                    $clause = $where ? 'WHERE '.implode(' AND ', $where) : '';
                    $sql = "SELECT t.id,t.ticket_number,t.subject,t.category,t.priority,t.status,t.attachment_url,t.created_at,t.updated_at,t.resolved_at,c.name AS creator_name,c.email AS creator_email,a.name AS assignee_name,(SELECT COUNT(*) FROM support_ticket_messages m WHERE m.ticket_id=t.id AND m.is_internal=0) AS message_count,(SELECT MAX(m.created_at) FROM support_ticket_messages m WHERE m.ticket_id=t.id AND m.is_internal=0) AS last_message_at FROM support_tickets t JOIN users c ON c.id=t.created_by LEFT JOIN users a ON a.id=t.assigned_to $clause ORDER BY FIELD(t.status,'open','in_progress','waiting_user','resolved','closed'),FIELD(t.priority,'urgent','high','normal','low'),t.updated_at DESC LIMIT 250";
                    $q = $pdo->prepare($sql);
                    $q->execute($params);
                    $tickets = $q->fetchAll();
                    $ticketIds = array_column($tickets, 'id');
                    $messagesByTicket = [];
                    if ($ticketIds !== []) {
                        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
                        $msgStmt = $pdo->prepare("SELECT id,ticket_id,author_id,author_name,message,attachment_url,is_internal,created_at FROM support_ticket_messages WHERE ticket_id IN ($placeholders) ORDER BY created_at ASC, id ASC");
                        $msgStmt->execute($ticketIds);
                        $allMsgs = $msgStmt->fetchAll();
                        foreach ($allMsgs as $m) {
                            $messagesByTicket[(int) $m['ticket_id']][] = [
                                'id' => (int) $m['id'],
                                'ticket_id' => (int) $m['ticket_id'],
                                'author_id' => (int) $m['author_id'],
                                'author_name' => $m['author_name'],
                                'message' => $m['message'],
                                'attachment_url' => $m['attachment_url'],
                                'is_internal' => (bool) $m['is_internal'],
                                'created_at' => $m['created_at'],
                            ];
                        }
                    }
                    foreach ($tickets as &$t) {
                        $t['id'] = (int) $t['id'];
                        $t['message_count'] = (int) $t['message_count'];
                        $t['messages'] = $messagesByTicket[$t['id']] ?? [];
                    }unset($t);
                    $users = [];
                    if (in_array('support.manage', $perms, true) || in_array('support.view', $perms, true) || ($actor['role_code'] ?? '') === 'admin') {
                        $users = $pdo->query("SELECT id,name,email FROM users WHERE status='active' ORDER BY name")->fetchAll();
                    }
                    json_response(['ok' => true, 'tickets' => $tickets, 'users' => $users, 'can_manage' => in_array('support.manage', $perms, true), 'can_view_all' => $manager]);
                }
                if ($view === 'thread') {
                    require_any_permission(['support.view', 'support.create', 'support.manage']);
                    $id = filter_var(request()->query()['id'] ?? null, FILTER_VALIDATE_INT);
                    if (! $id || $id < 1) {
                        throw new CommunicationsProblem('Selecciona un ticket válido.');
                    }
                    $ticket = $this->comm_can_read_ticket($pdo, $actor, (int) $id);
                    $canManage = in_array('support.manage', $perms, true);
                    $stmt = $pdo->prepare('SELECT m.id,m.author_id,m.author_name,m.message,m.attachment_url,m.is_internal,m.created_at FROM support_ticket_messages m WHERE m.ticket_id=:id '.($canManage ? '' : 'AND m.is_internal=0 ').'ORDER BY m.created_at,m.id');
                    $stmt->execute(['id' => (int) $id]);
                    $messages = $stmt->fetchAll();
                    foreach ($messages as &$m) {
                        $m['id'] = (int) $m['id'];
                        $m['is_internal'] = (bool) $m['is_internal'];
                    }unset($m);
                    $ticket['id'] = (int) $ticket['id'];
                    json_response(['ok' => true, 'ticket' => $ticket, 'messages' => $messages, 'can_manage' => $canManage, 'can_reply' => in_array('support.reply', $perms, true) || $canManage]);
                }
                if ($view === 'integrations') {
                    require_any_permission(['integrations.view', 'integrations.manage']);
                    $rows = $pdo->query('SELECT id,integration_code,name,category,description,endpoint_url,environment_key,is_enabled,last_check_status,last_check_message,last_checked_at,updated_at FROM integration_registry ORDER BY category,name')->fetchAll();
                    foreach ($rows as &$r) {
                        $r['id'] = (int) $r['id'];
                        $r['is_enabled'] = (bool) $r['is_enabled'];
                        $envKey = (string) ($r['environment_key'] ?? '');
                        $r['environment_configured'] = $envKey !== '' && desego_integration_environment($envKey) !== false && trim((string) desego_integration_environment($envKey)) !== '';
                    }unset($r);
                    $events = $pdo->query('SELECT integration_code,event_code,status,message,created_at FROM integration_events ORDER BY id DESC LIMIT 20')->fetchAll();
                    $tokens = [];
                    if (in_array('api_tokens.manage', $perms, true)) {
                        $tokens = $pdo->query('SELECT id,name,token_prefix,scopes_json,status,expires_at,last_used_at,created_at FROM api_access_tokens ORDER BY id DESC LIMIT 100')->fetchAll();
                        foreach ($tokens as &$tok) {
                            $tok['id'] = (int) $tok['id'];
                            $tok['scopes'] = json_decode((string) $tok['scopes_json'], true) ?: [];
                            unset($tok['scopes_json']);
                        }unset($tok);
                    }
                    json_response(['ok' => true, 'integrations' => $rows, 'events' => $events, 'tokens' => $tokens, 'can_manage' => in_array('integrations.manage', $perms, true), 'can_manage_tokens' => in_array('api_tokens.manage', $perms, true)]);
                }
                throw new CommunicationsProblem('Vista de comunicaciones no reconocida.', 404);
            }
            if ($method !== 'POST') {
                desego_header('Allow: GET, POST');
                json_response(['ok' => false, 'message' => 'Método no permitido.'], 405);
            }
            $body = json_body();
            require_csrf($body);
            $action = (string) ($body['action'] ?? '');
            if ($action === 'notification.read') {
                require_any_permission(['notifications.view', 'notifications.manage', 'support.create', 'support.manage']);
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                if (! $id || $id < 1) {
                    throw new CommunicationsProblem('Notificación no válida.');
                }
                $check = $pdo->prepare('SELECT id FROM notifications WHERE id=:id AND (recipient_user_id=:uid OR recipient_user_id IS NULL)');
                $check->execute(['id' => $id, 'uid' => (int) $actor['id']]);
                if (! $check->fetchColumn()) {
                    throw new CommunicationsProblem('La notificación no existe.', 404);
                }
                $ins = $pdo->prepare('INSERT IGNORE INTO notification_reads(notification_id,user_id,read_at) VALUES(:nid,:uid,UTC_TIMESTAMP())');
                $ins->execute(['nid' => $id, 'uid' => (int) $actor['id']]);
                json_response(['ok' => true, 'message' => 'Notificación marcada como leída.']);
            }
            if ($action === 'notification.read_all') {
                require_any_permission(['notifications.view', 'notifications.manage', 'support.create', 'support.manage']);
                $ins = $pdo->prepare('INSERT IGNORE INTO notification_reads(notification_id,user_id,read_at) SELECT n.id,:uid_insert,UTC_TIMESTAMP() FROM notifications n WHERE n.recipient_user_id IS NULL OR n.recipient_user_id=:uid_filter');
                $ins->execute(['uid_insert' => (int) $actor['id'], 'uid_filter' => (int) $actor['id']]);
                json_response(['ok' => true, 'message' => 'Todas las notificaciones quedaron leídas.']);
            }
            if ($action === 'notification.broadcast') {
                require_permission('notifications.manage');
                $title = $this->comm_text($body['title'] ?? null, 150, 'título', true);
                $message = $this->comm_text($body['message'] ?? null, 1000, 'mensaje', true);
                $category = (string) ($body['category'] ?? 'info');
                $target = $this->comm_text($body['target_view'] ?? null, 40, 'destino', false);
                if (! in_array($category, ['info', 'success', 'warning', 'error'], true)) {
                    throw new CommunicationsProblem('Categoría de notificación no válida.');
                }
                if ($target !== null && ! preg_match('/^[a-z_]{1,40}$/', $target)) {
                    throw new CommunicationsProblem('El destino de la notificación no es válido.');
                }
                $this->comm_notification($pdo, null, $title, $message, $category, $target, (int) $actor['id']);
                $this->comm_audit($pdo, $actor, 'notification.broadcast', ['title' => $title, 'target_view' => $target]);
                json_response(['ok' => true, 'message' => 'Aviso publicado para usuarios activos.']);
            }
            if ($action === 'support.create') {
                require_permission('support.create');
                $subject = $this->comm_text($body['subject'] ?? null, 180, 'asunto', true);
                $message = $this->comm_text($body['message'] ?? null, 5000, 'descripción', true);
                $category = (string) ($body['category'] ?? 'other');
                $priority = (string) ($body['priority'] ?? 'normal');
                if (! in_array($category, ['technical', 'orders', 'inventory', 'billing', 'access', 'other'], true)) {
                    throw new CommunicationsProblem('Categoría de soporte no válida.');
                }
                if (! in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
                    throw new CommunicationsProblem('Prioridad no válida.');
                }
                $pdo->beginTransaction();
                try {
                    $s = $pdo->prepare("INSERT INTO support_tickets(created_by,subject,category,priority,status,created_at,updated_at) VALUES(:uid,:subject,:category,:priority,'open',UTC_TIMESTAMP(),UTC_TIMESTAMP())");
                    $s->execute(['uid' => (int) $actor['id'], 'subject' => $subject, 'category' => $category, 'priority' => $priority]);
                    $id = (int) $pdo->lastInsertId();
                    $number = 'SUP-'.gmdate('ym').'-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
                    $pdo->prepare('UPDATE support_tickets SET ticket_number=:number WHERE id=:id')->execute(['number' => $number, 'id' => $id]);
                    $m = $pdo->prepare('INSERT INTO support_ticket_messages(ticket_id,author_id,author_name,message,is_internal,created_at) VALUES(:ticket,:uid,:name,:message,0,UTC_TIMESTAMP())');
                    $m->execute(['ticket' => $id, 'uid' => (int) $actor['id'], 'name' => (string) $actor['name'], 'message' => $message]);
                    $pdo->commit();
                    $this->comm_audit($pdo, $actor, 'support.ticket_created', ['ticket_id' => $id, 'ticket_number' => $number, 'priority' => $priority]);
                    json_response(['ok' => true, 'message' => 'Ticket creado correctamente.', 'ticket_id' => $id, 'ticket_number' => $number], 201);
                } catch (Throwable $e) {
                    if ($e instanceof HttpResponseException) {
                        throw $e;
                    }if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }throw $e;
                }
            }
            if ($action === 'support.reply') {
                require_any_permission(['support.reply', 'support.manage']);
                $id = filter_var($body['ticket_id'] ?? null, FILTER_VALIDATE_INT);
                if (! $id || $id < 1) {
                    throw new CommunicationsProblem('Ticket no válido.');
                }$ticket = $this->comm_can_read_ticket($pdo, $actor, (int) $id);
                $message = $this->comm_text($body['message'] ?? null, 5000, 'mensaje', true);
                $internal = ! empty($body['is_internal']);
                if ($internal && ! in_array('support.manage', $perms, true)) {
                    throw new CommunicationsProblem('No tienes permiso para añadir una nota interna.', 403);
                }if (in_array($ticket['status'], ['closed', 'resolved'], true) && ! $internal) {
                    throw new CommunicationsProblem('El ticket está resuelto o cerrado; reábrelo antes de responder.');
                }
                $stmt = $pdo->prepare('INSERT INTO support_ticket_messages(ticket_id,author_id,author_name,message,is_internal,created_at) VALUES(:ticket,:uid,:name,:message,:internal,UTC_TIMESTAMP())');
                $stmt->execute(['ticket' => (int) $id, 'uid' => (int) $actor['id'], 'name' => (string) $actor['name'], 'message' => $message, 'internal' => $internal ? 1 : 0]);
                if (! $internal) {
                    $newStatus = in_array('support.manage', $perms, true) ? 'waiting_user' : 'in_progress';
                    $pdo->prepare('UPDATE support_tickets SET status=:status,updated_at=UTC_TIMESTAMP(),resolved_at=NULL WHERE id=:id')->execute(['status' => $newStatus, 'id' => (int) $id]);
                }
                if (! $internal && (int) $ticket['created_by'] !== (int) $actor['id']) {
                    $this->comm_notification($pdo, (int) $ticket['created_by'], 'Respuesta de soporte', 'Hay una nueva respuesta en el ticket '.(string) $ticket['ticket_number'].'.', 'info', 'support', (int) $actor['id']);
                }
                $this->comm_audit($pdo, $actor, 'support.ticket_replied', ['ticket_id' => (int) $id, 'internal' => $internal]);
                json_response(['ok' => true, 'message' => $internal ? 'Nota interna agregada.' : 'Respuesta enviada.']);
            }
            if (in_array($action, ['support.update', 'support.manage_ticket'], true)) {
                require_any_permission(['support.manage', 'support.reply']);
                $id = filter_var($body['id'] ?? $body['ticket_id'] ?? null, FILTER_VALIDATE_INT);
                if (! $id || $id < 1) {
                    throw new CommunicationsProblem('Ticket no válido.');
                }
                $ticket = $this->comm_can_read_ticket($pdo, $actor, (int) $id);

                $status = (string) ($body['status'] ?? $ticket['status']);
                if (! in_array($status, ['open', 'in_progress', 'waiting_user', 'resolved', 'closed'], true)) {
                    $status = (string) $ticket['status'];
                }

                $priority = (string) ($body['priority'] ?? $ticket['priority']);
                if (! in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
                    $priority = (string) $ticket['priority'];
                }

                $assignedTo = array_key_exists('assigned_to', $body)
                    ? (filter_var($body['assigned_to'], FILTER_VALIDATE_INT) ?: null)
                    : ($ticket['assigned_to'] ? (int) $ticket['assigned_to'] : null);

                $resolvedAt = in_array($status, ['resolved', 'closed'], true)
                    ? ($ticket['resolved_at'] ?: gmdate('Y-m-d H:i:s'))
                    : null;

                $pdo->beginTransaction();
                try {
                    $updateStmt = $pdo->prepare('UPDATE support_tickets SET status=:status, priority=:priority, assigned_to=:assigned_to, resolved_at=:resolved, updated_at=UTC_TIMESTAMP() WHERE id=:id');
                    $updateStmt->execute([
                        'status' => $status,
                        'priority' => $priority,
                        'assigned_to' => $assignedTo,
                        'resolved' => $resolvedAt,
                        'id' => $id,
                    ]);

                    $messageText = trim((string) ($body['message'] ?? ''));
                    if ($messageText !== '') {
                        $internal = ! empty($body['is_internal']);
                        $stmt = $pdo->prepare('INSERT INTO support_ticket_messages(ticket_id,author_id,author_name,message,attachment_url,is_internal,created_at) VALUES(:ticket,:uid,:name,:message,:attachment,:internal,UTC_TIMESTAMP())');
                        $stmt->execute([
                            'ticket' => (int) $id,
                            'uid' => (int) $actor['id'],
                            'name' => (string) $actor['name'],
                            'message' => $messageText,
                            'attachment' => ! empty($body['attachment_url']) ? (string) $body['attachment_url'] : null,
                            'internal' => $internal ? 1 : 0,
                        ]);

                        if (! $internal && (int) $ticket['created_by'] !== (int) $actor['id']) {
                            $this->comm_notification(
                                $pdo,
                                (int) $ticket['created_by'],
                                'Respuesta en Ticket '.(string) $ticket['ticket_number'],
                                'El equipo de DeSego ha respondido a tu ticket de soporte.',
                                'info',
                                'support',
                                (int) $actor['id']
                            );
                        }
                    }

                    $pdo->commit();
                    $this->comm_audit($pdo, $actor, 'support.ticket_updated', ['ticket_id' => $id, 'status' => $status, 'priority' => $priority]);

                    json_response(['ok' => true, 'message' => 'Ticket de soporte actualizado correctamente.']);
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
            }
            if ($action === 'support.status') {
                require_permission('support.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $status = (string) ($body['status'] ?? '');
                if (! $id || $id < 1 || ! in_array($status, ['open', 'in_progress', 'waiting_user', 'resolved', 'closed'], true)) {
                    throw new CommunicationsProblem('Ticket o estado no válido.');
                }$s = $pdo->prepare('UPDATE support_tickets SET status=:status,resolved_at=:resolved,updated_at=UTC_TIMESTAMP() WHERE id=:id');
                $s->execute(['status' => $status, 'resolved' => in_array($status, ['resolved', 'closed'], true) ? gmdate('Y-m-d H:i:s') : null, 'id' => $id]);
                if (! $s->rowCount()) {
                    $exists = $pdo->prepare('SELECT created_by FROM support_tickets WHERE id=:id');
                    $exists->execute(['id' => $id]);
                    if (! $exists->fetch()) {
                        throw new CommunicationsProblem('El ticket no existe.', 404);
                    }
                }$this->comm_audit($pdo, $actor, 'support.ticket_status', ['ticket_id' => $id, 'status' => $status]);
                json_response(['ok' => true, 'message' => 'Estado del ticket actualizado.']);
            }
            if ($action === 'support.assign') {
                require_permission('support.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $uid = filter_var($body['assigned_to'] ?? null, FILTER_VALIDATE_INT);
                if (! $id || $id < 1) {
                    throw new CommunicationsProblem('Ticket no válido.');
                }if ($uid) {
                    $u = $pdo->prepare("SELECT id FROM users WHERE id=:id AND status='active'");
                    $u->execute(['id' => $uid]);
                    if (! $u->fetchColumn()) {
                        throw new CommunicationsProblem('El usuario asignado no existe o está inactivo.');
                    }
                }$s = $pdo->prepare('UPDATE support_tickets SET assigned_to=:uid,updated_at=UTC_TIMESTAMP() WHERE id=:id');
                $s->execute(['uid' => $uid ?: null, 'id' => $id]);
                $exists = $pdo->prepare('SELECT id FROM support_tickets WHERE id=:id');
                $exists->execute(['id' => $id]);
                if (! $exists->fetchColumn()) {
                    throw new CommunicationsProblem('El ticket no existe.', 404);
                }$this->comm_audit($pdo, $actor, 'support.ticket_assigned', ['ticket_id' => $id, 'assigned_to' => $uid ?: null]);
                json_response(['ok' => true, 'message' => 'Asignación actualizada.']);
            }
            if ($action === 'integration.save') {
                require_permission('integrations.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                $code = $this->comm_text($body['integration_code'] ?? null, 60, 'código', true);
                $name = $this->comm_text($body['name'] ?? null, 120, 'nombre', true);
                $category = (string) ($body['category'] ?? 'other');
                $desc = $this->comm_text($body['description'] ?? null, 500, 'descripción', false);
                $endpoint = $this->comm_text($body['endpoint_url'] ?? null, 500, 'URL', false);
                $env = $this->comm_text($body['environment_key'] ?? null, 100, 'variable de entorno', false);
                $enabled = ! empty($body['is_enabled']) ? 1 : 0;
                if (! preg_match('/^[a-z0-9_]{2,60}$/', $code)) {
                    throw new CommunicationsProblem('Código de integración no válido.');
                }if (! in_array($category, ['email', 'messaging', 'maps', 'payments', 'webhook', 'api', 'other'], true)) {
                    throw new CommunicationsProblem('Categoría de integración no válida.');
                }if ($endpoint !== null && (! filter_var($endpoint, FILTER_VALIDATE_URL) || strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) !== 'https')) {
                    throw new CommunicationsProblem('La URL debe ser válida y utilizar HTTPS.');
                }if ($env !== null && ! preg_match('/^[A-Z][A-Z0-9_]{2,99}$/', $env)) {
                    throw new CommunicationsProblem('El nombre de variable debe usar MAYÚSCULAS, números y guion bajo. Nunca escribas aquí el secreto.');
                }
                $s = $pdo->prepare('UPDATE integration_registry SET name=:name,category=:category,description=:description,endpoint_url=:endpoint,environment_key=:env,is_enabled=:enabled,last_check_status=\'not_checked\',last_check_message=NULL,updated_by=:uid,updated_at=UTC_TIMESTAMP() WHERE integration_code=:code');
                $s->execute(['name' => $name, 'category' => $category, 'description' => $desc, 'endpoint' => $endpoint, 'env' => $env, 'enabled' => $enabled, 'uid' => (int) $actor['id'], 'code' => $code]);
                if (! $s->rowCount()) {
                    $exists = $pdo->prepare('SELECT id FROM integration_registry WHERE integration_code=:code');
                    $exists->execute(['code' => $code]);
                    if (! $exists->fetchColumn()) {
                        throw new CommunicationsProblem('La integración no existe.');
                    }
                }
                $this->comm_audit($pdo, $actor, 'integration.updated', ['integration_code' => $code, 'enabled' => (bool) $enabled]);
                json_response(['ok' => true, 'message' => 'Configuración de integración guardada.']);
            }
            if ($action === 'integration.check') {
                require_permission('integrations.manage');
                $code = $this->comm_text($body['integration_code'] ?? null, 60, 'código', true);
                $q = $pdo->prepare('SELECT * FROM integration_registry WHERE integration_code=:code');
                $q->execute(['code' => $code]);
                $r = $q->fetch();
                if (! $r) {
                    throw new CommunicationsProblem('La integración no existe.', 404);
                }$message = '';
                $status = 'configured';
                $ok = true;
                if (! (int) $r['is_enabled']) {
                    $status = 'needs_configuration';
                    $message = 'Integración desactivada; no se intentó conexión externa.';
                    $ok = false;
                } elseif (trim((string) $r['environment_key']) !== '' && (desego_integration_environment((string) $r['environment_key']) === false || trim((string) desego_integration_environment((string) $r['environment_key'])) === '')) {
                    $status = 'needs_configuration';
                    $message = 'No se encontró la variable de entorno '.(string) $r['environment_key'].'.';
                    $ok = false;
                } elseif (trim((string) $r['endpoint_url']) !== '' && (! filter_var((string) $r['endpoint_url'], FILTER_VALIDATE_URL) || strtolower((string) parse_url((string) $r['endpoint_url'], PHP_URL_SCHEME)) !== 'https')) {
                    $status = 'invalid';
                    $message = 'La URL configurada no es válida o no usa HTTPS.';
                    $ok = false;
                } else {
                    $message = 'La configuración básica está completa. Esta comprobación no realiza un cobro ni valida una conexión externa.';
                }
                $pdo->prepare('UPDATE integration_registry SET last_check_status=:status,last_check_message=:message,last_checked_at=UTC_TIMESTAMP(),updated_by=:uid WHERE integration_code=:code')->execute(['status' => $status, 'message' => $message, 'uid' => (int) $actor['id'], 'code' => $code]);
                $pdo->prepare('INSERT INTO integration_events(integration_code,event_code,status,message,details_json,actor_id,created_at) VALUES(:code,\'configuration_check\',:status,:message,:details,:uid,UTC_TIMESTAMP())')->execute(['code' => $code, 'status' => $ok ? 'success' : 'warning', 'message' => $message, 'details' => json_encode(['enabled' => (bool) $r['is_enabled'], 'env_key' => $r['environment_key']], JSON_UNESCAPED_SLASHES), 'uid' => (int) $actor['id']]);
                $this->comm_audit($pdo, $actor, 'integration.checked', ['integration_code' => $code, 'result' => $status]);
                json_response(['ok' => true, 'configured' => $ok, 'status' => $status, 'message' => $message]);
            }
            if ($action === 'api_token.create') {
                require_permission('api_tokens.manage');
                $name = $this->comm_text($body['name'] ?? null, 120, 'nombre', true);
                $scopes = $body['scopes'] ?? [];
                if (! is_array($scopes)) {
                    throw new CommunicationsProblem('Selecciona permisos API válidos.');
                }$allowed = ['products:read', 'orders:read'];
                $scopes = array_values(array_unique(array_filter($scopes, fn ($s) => is_string($s) && in_array($s, $allowed, true))));
                if (! $scopes) {
                    throw new CommunicationsProblem('Selecciona al menos un alcance permitido.');
                }$expires = null;
                $days = filter_var($body['expires_days'] ?? 90, FILTER_VALIDATE_INT);
                if ($days === false || $days < 1 || $days > 365) {
                    throw new CommunicationsProblem('La caducidad debe estar entre 1 y 365 días.');
                }$expires = gmdate('Y-m-d H:i:s', time() + $days * 86400);
                $raw = 'dsg_live_'.rtrim(strtr(base64_encode(random_bytes(36)), '+/', '-_'), '=');
                $prefix = substr($raw, 0, 18);
                $hash = hash('sha256', $raw);
                $stmt = $pdo->prepare('INSERT INTO api_access_tokens(name,token_prefix,token_hash,scopes_json,status,expires_at,created_by,created_at) VALUES(:name,:prefix,:hash,:scopes,\'active\',:expires,:uid,UTC_TIMESTAMP())');
                $stmt->execute(['name' => $name, 'prefix' => $prefix, 'hash' => $hash, 'scopes' => json_encode($scopes), 'expires' => $expires, 'uid' => (int) $actor['id']]);
                $id = (int) $pdo->lastInsertId();
                $this->comm_audit($pdo, $actor, 'api_token.created', ['token_id' => $id, 'prefix' => $prefix, 'scopes' => $scopes]);
                json_response(['ok' => true, 'message' => 'Token creado. Copia el valor ahora; no volverá a mostrarse.', 'token' => $raw, 'id' => $id, 'prefix' => $prefix, 'scopes' => $scopes, 'expires_at' => $expires], 201);
            }
            if ($action === 'api_token.revoke') {
                require_permission('api_tokens.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
                if (! $id || $id < 1) {
                    throw new CommunicationsProblem('Token no válido.');
                }$s = $pdo->prepare("UPDATE api_access_tokens SET status='revoked',revoked_at=UTC_TIMESTAMP() WHERE id=:id AND status='active'");
                $s->execute(['id' => $id]);
                if (! $s->rowCount()) {
                    throw new CommunicationsProblem('El token no existe o ya fue revocado.', 404);
                }$this->comm_audit($pdo, $actor, 'api_token.revoked', ['token_id' => $id]);
                json_response(['ok' => true, 'message' => 'Token revocado.']);
            }
            throw new CommunicationsProblem('Acción de comunicaciones no reconocida.', 404);
        } catch (CommunicationsProblem $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], $e->httpStatus);
        } catch (PDOException $e) {
            error_log('[DESEGO communications PDO] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo completar la operación de comunicaciones. Verifica la migración del Paso 15.'], 500);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            error_log('[DESEGO communications] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'Error interno en comunicaciones. Revisa el registro del servidor.'], 500);
        }

        return response()->noContent();
    }

    protected function comm_text(mixed $value, int $max, string $label, bool $required = false): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            if ($required) {
                throw new CommunicationsProblem("El campo {$label} es obligatorio.");
            }

            return null;
        }
        if (text_length($value) > $max) {
            throw new CommunicationsProblem("El campo {$label} supera la longitud permitida.");
        }

        return $value;
    }

    protected function comm_audit(PDO $pdo, array $actor, string $event, array $details = []): void
    {
        audit_log($pdo, (int) $actor['id'], $event, $details);
    }

    protected function comm_notification(PDO $pdo, ?int $recipientId, string $title, string $message, string $category, ?string $targetView, int $creatorId): void
    {
        $stmt = $pdo->prepare('INSERT INTO notifications (recipient_user_id,title,message,category,target_view,created_by,created_at) VALUES (:recipient,:title,:message,:category,:target,:creator,UTC_TIMESTAMP())');
        $stmt->execute(['recipient' => $recipientId, 'title' => $title, 'message' => $message, 'category' => $category, 'target' => $targetView, 'creator' => $creatorId]);
    }

    protected function comm_can_read_ticket(PDO $pdo, array $actor, int $ticketId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM support_tickets WHERE id=:id LIMIT 1');
        $stmt->execute(['id' => $ticketId]);
        $ticket = $stmt->fetch();
        if (! $ticket) {
            throw new CommunicationsProblem('El ticket no existe.', 404);
        }
        $perms = $actor['permissions'] ?? [];
        $manager = in_array('support.manage', $perms, true) || in_array('support.view', $perms, true);
        if (! $manager && (int) $ticket['created_by'] !== (int) $actor['id']) {
            throw new CommunicationsProblem('No tienes acceso a este ticket.', 403);
        }

        return $ticket;
    }
}
