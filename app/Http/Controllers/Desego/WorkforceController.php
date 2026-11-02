<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desego;

use App\Exceptions\Desego\WorkforceProblem;
use App\Http\Controllers\Controller;
use DateTime;
use DateTimeImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use PDO;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function App\Support\Desego\audit_log;
use function App\Support\Desego\current_user;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\desego_header;
use function App\Support\Desego\json_body;
use function App\Support\Desego\json_response;
use function App\Support\Desego\require_any_permission;
use function App\Support\Desego\require_csrf;
use function App\Support\Desego\require_permission;
use function App\Support\Desego\text_length;

class WorkforceController extends Controller
{
    public function __invoke(): Response
    {
        date_default_timezone_set('America/Lima');
        try {
            $pdo = desego_db();
            $method = strtoupper((string) (request()->server->all()['REQUEST_METHOD'] ?? 'GET'));
            $actor = current_user();
            if (! $actor) {
                json_response(['ok' => false, 'message' => 'Inicia sesión para continuar.'], 401);
            }
            $perms = $actor['permissions'] ?? [];
            if ($method === 'GET') {
                $view = (string) (request()->query()['view'] ?? 'shifts');
                if ($view === 'branches') {
                    require_any_permission(['branches.view', 'branches.manage']);
                    $todayLocal = date('Y-m-d');
                    $branchQuery = $pdo->prepare("SELECT b.*, (SELECT COUNT(*) FROM employees e WHERE e.branch_id=b.id AND e.status='active') AS active_employees, (SELECT COUNT(*) FROM employee_shifts s WHERE s.branch_id=b.id AND s.shift_date=:today AND s.status IN ('scheduled','in_progress')) AS todays_shifts FROM branches b ORDER BY b.is_main DESC,b.status DESC,b.name");
                    $branchQuery->execute(['today' => $todayLocal]);
                    $branches = $branchQuery->fetchAll();
                    foreach ($branches as &$b) {
                        $b['id'] = (int) $b['id'];
                        $b['is_main'] = (int) $b['is_main'];
                        $b['active_employees'] = (int) $b['active_employees'];
                        $b['todays_shifts'] = (int) $b['todays_shifts'];
                    } unset($b);
                    json_response(['ok' => true, 'branches' => $branches, 'can_manage' => in_array('branches.manage', $perms, true)]);
                }
                if ($view === 'employees') {
                    require_any_permission(['employees.view', 'employees.manage']);
                    $showRate = in_array('employees.compensation.view', $perms, true);
                    $sql = 'SELECT e.id,e.employee_code,e.full_name,e.user_id,e.branch_id,e.job_title,e.phone,e.email,e.employment_type,e.hire_date,'.($showRate ? 'e.hourly_rate' : 'NULL AS hourly_rate').",e.status,e.notes,e.created_at,e.updated_at,b.name AS branch_name,u.name AS account_name,u.email AS account_email,(SELECT COUNT(*) FROM employee_shifts s WHERE s.employee_id=e.id AND s.status IN ('scheduled','in_progress') AND s.shift_date>=:today) AS upcoming_shifts FROM employees e LEFT JOIN branches b ON b.id=e.branch_id LEFT JOIN users u ON u.id=e.user_id ORDER BY e.status DESC,e.full_name";
                    $employeeQuery = $pdo->prepare($sql);
                    $employeeQuery->execute(['today' => date('Y-m-d')]);
                    $employees = $employeeQuery->fetchAll();
                    foreach ($employees as &$e) {
                        $e['id'] = (int) $e['id'];
                        $e['user_id'] = $e['user_id'] === null ? null : (int) $e['user_id'];
                        $e['branch_id'] = $e['branch_id'] === null ? null : (int) $e['branch_id'];
                        $e['hourly_rate'] = $e['hourly_rate'] === null ? null : (float) $e['hourly_rate'];
                        $e['upcoming_shifts'] = (int) $e['upcoming_shifts'];
                    }unset($e);
                    $branches = $pdo->query("SELECT id,code,name,status,is_main FROM branches WHERE status='active' ORDER BY is_main DESC,name")->fetchAll();
                    $users = [];
                    if (in_array('employees.manage', $perms, true)) {
                        $users = $pdo->query("SELECT u.id,u.name,u.email,r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.status='active' ORDER BY u.name")->fetchAll();
                    }
                    json_response(['ok' => true, 'employees' => $employees, 'branches' => $branches, 'users' => $users, 'can_manage' => in_array('employees.manage', $perms, true), 'can_view_rate' => $showRate]);
                }
                if ($view === 'shifts') {
                    require_any_permission(['shifts.view', 'shifts.manage', 'attendance.manage', 'attendance.clock']);
                    $from = $this->wf_date(request()->query()['from'] ?? date('Y-m-d'), 'fecha inicial');
                    $to = $this->wf_date(request()->query()['to'] ?? date('Y-m-d', time() + 13 * 86400), 'fecha final');
                    if ($from > $to) {
                        json_response(['ok' => false, 'message' => 'La fecha inicial debe ser anterior o igual a la final.'], 422);
                    }
                    $days = (strtotime($to) - strtotime($from)) / 86400;
                    if ($days > 93) {
                        json_response(['ok' => false, 'message' => 'Consulta un máximo de 94 días por vez.'], 422);
                    }
                    $isManager = $this->wf_permissions($actor, ['shifts.manage', 'attendance.manage']);
                    $sql = 'SELECT s.id,s.employee_id,s.branch_id,s.shift_date,s.start_time,s.end_time,s.break_minutes,s.status,s.actual_start_at,s.actual_end_at,s.notes,s.created_at,e.employee_code,e.full_name AS employee_name,e.user_id AS employee_user_id,e.job_title,e.status AS employee_status,b.name AS branch_name,b.code AS branch_code,creator.name AS created_by_name FROM employee_shifts s JOIN employees e ON e.id=s.employee_id JOIN branches b ON b.id=s.branch_id LEFT JOIN users creator ON creator.id=s.created_by WHERE s.shift_date BETWEEN :from_date AND :to_date';
                    $params = ['from_date' => $from, 'to_date' => $to];
                    if (! $isManager) {
                        $sql .= ' AND e.user_id=:user_id';
                        $params['user_id'] = (int) $actor['id'];
                    }
                    $branchId = filter_var(request()->query()['branch_id'] ?? null, FILTER_VALIDATE_INT);
                    if ($branchId && $branchId > 0 && $isManager) {
                        $sql .= ' AND s.branch_id=:branch_id';
                        $params['branch_id'] = $branchId;
                    }
                    $sql .= ' ORDER BY s.shift_date,s.start_time,e.full_name LIMIT 1000';
                    $st = $pdo->prepare($sql);
                    $st->execute($params);
                    $shifts = $st->fetchAll();
                    foreach ($shifts as &$s) {
                        $s['id'] = (int) $s['id'];
                        $s['employee_id'] = (int) $s['employee_id'];
                        $s['branch_id'] = (int) $s['branch_id'];
                        $s['break_minutes'] = (int) $s['break_minutes'];
                        $s['employee_user_id'] = $s['employee_user_id'] === null ? null : (int) $s['employee_user_id'];
                        $s['can_clock'] = ($s['employee_user_id'] === (int) $actor['id']) || in_array('attendance.manage', $perms, true) || in_array('shifts.manage', $perms, true);
                        unset($s['employee_user_id']);
                    }unset($s);
                    $branches = $pdo->query("SELECT id,code,name FROM branches WHERE status='active' ORDER BY is_main DESC,name")->fetchAll();
                    $employees = [];
                    if (in_array('shifts.manage', $perms, true)) {
                        $employees = $pdo->query("SELECT id,employee_code,full_name,job_title,branch_id,status FROM employees WHERE status='active' ORDER BY full_name")->fetchAll();
                    }
                    $today = date('Y-m-d');
                    $summary = ['scheduled' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0, 'today' => 0];
                    foreach ($shifts as $s) {
                        $summary[$s['status']] = (int) ($summary[$s['status']] ?? 0) + 1;
                        if ($s['shift_date'] === $today && $s['status'] !== 'cancelled') {
                            $summary['today']++;
                        }
                    }
                    json_response(['ok' => true, 'shifts' => $shifts, 'branches' => $branches, 'employees' => $employees, 'summary' => $summary, 'can_manage' => in_array('shifts.manage', $perms, true), 'can_attendance' => in_array('attendance.manage', $perms, true) || in_array('attendance.clock', $perms, true), 'can_view_all' => $isManager]);
                }
                json_response(['ok' => false, 'message' => 'Vista de personal no reconocida.'], 404);
            }
            if ($method !== 'POST') {
                desego_header('Allow: GET, POST');
                json_response(['ok' => false, 'message' => 'Método no permitido.'], 405);
            }
            $body = json_body();
            require_csrf($body);
            $action = (string) ($body['action'] ?? '');
            if ($action === 'branch.save') {
                $actor = require_permission('branches.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                $code = strtoupper((string) $this->wf_text($body['code'] ?? null, 30, 'código', true));
                if (! preg_match('/^[A-Z0-9][A-Z0-9_-]{1,29}$/', $code)) {
                    throw new WorkforceProblem('Usa un código de 2 a 30 caracteres: letras, números, guion o guion bajo.');
                }
                $name = $this->wf_text($body['name'] ?? null, 150, 'nombre', true);
                $tax = $this->wf_text($body['tax_id'] ?? null, 20, 'RUC', false);
                $phone = $this->wf_text($body['phone'] ?? null, 40, 'teléfono', false);
                $email = $this->wf_text($body['email'] ?? null, 190, 'correo', false);
                if ($email && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new WorkforceProblem('El correo de la sucursal no es válido.');
                }
                $address = $this->wf_text($body['address'] ?? null, 255, 'dirección', false);
                $district = $this->wf_text($body['district'] ?? null, 100, 'distrito', false);
                $province = $this->wf_text($body['province'] ?? null, 100, 'provincia', false);
                $region = $this->wf_text($body['region'] ?? null, 100, 'región', false);
                $open = trim((string) ($body['opening_time'] ?? ''));
                $close = trim((string) ($body['closing_time'] ?? ''));
                if (($open === '') !== ($close === '')) {
                    throw new WorkforceProblem('Indica tanto la hora de apertura como la de cierre, o deja ambas vacías.');
                }
                if ($open !== '') {
                    $open = $this->wf_time($open, 'apertura');
                    $close = $this->wf_time($close, 'cierre');
                    if ($open === $close) {
                        throw new WorkforceProblem('La apertura y el cierre no pueden ser iguales.');
                    }
                } else {
                    $open = null;
                    $close = null;
                }
                $isMain = ! empty($body['is_main']) ? 1 : 0;
                if ($id && $isMain) {
                    $checkBranch = $pdo->prepare('SELECT status FROM branches WHERE id=:id');
                    $checkBranch->execute(['id' => $id]);
                    $currentBranchStatus = $checkBranch->fetchColumn();
                    if ($currentBranchStatus === false) {
                        throw new WorkforceProblem('La sucursal no existe.', 404);
                    } if ($currentBranchStatus !== 'active') {
                        throw new WorkforceProblem('Activa la sucursal antes de marcarla como principal.');
                    }
                }
                try {
                    $pdo->beginTransaction();
                    if ($isMain) {
                        $pdo->exec('UPDATE branches SET is_main=0');
                    }
                    if ($id) {
                        $q = $pdo->prepare('SELECT id FROM branches WHERE id=:id FOR UPDATE');
                        $q->execute(['id' => $id]);
                        if (! $q->fetch()) {
                            throw new WorkforceProblem('La sucursal no existe.', 404);
                        }
                        $q = $pdo->prepare('UPDATE branches SET code=:code,name=:name,tax_id=:tax_id,phone=:phone,email=:email,address=:address,district=:district,province=:province,region=:region,opening_time=:opening_time,closing_time=:closing_time,is_main=:is_main,updated_by=:uid,updated_at=NOW() WHERE id=:id');
                        $q->execute(['code' => $code, 'name' => $name, 'tax_id' => $tax, 'phone' => $phone, 'email' => $email, 'address' => $address, 'district' => $district, 'province' => $province, 'region' => $region, 'opening_time' => $open, 'closing_time' => $close, 'is_main' => $isMain, 'uid' => $actor['id'], 'id' => $id]);
                        $newId = $id;
                        $event = 'branches.updated';
                    } else {
                        $q = $pdo->prepare('INSERT INTO branches (code,name,tax_id,phone,email,address,district,province,region,opening_time,closing_time,is_main,status,created_by,updated_by) VALUES (:code,:name,:tax_id,:phone,:email,:address,:district,:province,:region,:opening_time,:closing_time,:is_main,\'active\',:created_by,:updated_by)');
                        $q->execute(['code' => $code, 'name' => $name, 'tax_id' => $tax, 'phone' => $phone, 'email' => $email, 'address' => $address, 'district' => $district, 'province' => $province, 'region' => $region, 'opening_time' => $open, 'closing_time' => $close, 'is_main' => $isMain, 'created_by' => $actor['id'], 'updated_by' => $actor['id']]);
                        $newId = (int) $pdo->lastInsertId();
                        $event = 'branches.created';
                    }
                    if (! (int) $pdo->query("SELECT COALESCE(MAX(is_main),0) FROM branches WHERE status='active'")->fetchColumn()) {
                        $candidate = $pdo->query("SELECT id FROM branches WHERE status='active' ORDER BY id LIMIT 1")->fetchColumn();
                        if ($candidate) {
                            $pdo->prepare('UPDATE branches SET is_main=1 WHERE id=:id')->execute(['id' => $candidate]);
                        }
                    }
                    $pdo->exec("UPDATE branches SET is_main=0 WHERE status='inactive'");
                    $pdo->commit();
                    $this->wf_audit($pdo, $actor, $event, ['branch_id' => $newId, 'code' => $code]);
                    json_response(['ok' => true, 'message' => 'Sucursal guardada.', 'id' => $newId]);
                } catch (Throwable $e) {
                    if ($e instanceof HttpResponseException) {
                        throw $e;
                    }if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }if ($e instanceof WorkforceProblem) {
                        throw $e;
                    }if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
                        throw new WorkforceProblem('El código o RUC ya está registrado.', 409);
                    }throw $e;
                }
            }
            if ($action === 'branch.status') {
                $actor = require_permission('branches.manage');
                $id = $this->wf_int($body['id'] ?? null, 'sucursal');
                $status = (string) ($body['status'] ?? '');
                if (! in_array($status, ['active', 'inactive'], true)) {
                    throw new WorkforceProblem('Estado no válido.');
                }
                $pdo->beginTransaction();
                try {
                    $q = $pdo->prepare('SELECT * FROM branches WHERE id=:id FOR UPDATE');
                    $q->execute(['id' => $id]);
                    $branch = $q->fetch();
                    if (! $branch) {
                        throw new WorkforceProblem('La sucursal no existe.', 404);
                    }
                    if ($status === 'inactive') {
                        $q = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE branch_id=:id AND status='active'");
                        $q->execute(['id' => $id]);
                        if ((int) $q->fetchColumn() > 0) {
                            throw new WorkforceProblem('Reasigna o desactiva al personal activo de esta sucursal antes de desactivarla.');
                        }
                        $q = $pdo->prepare("SELECT COUNT(*) FROM employee_shifts WHERE branch_id=:id AND (status='in_progress' OR (shift_date>=:today AND status='scheduled'))");
                        $q->execute(['id' => $id, 'today' => date('Y-m-d')]);
                        if ((int) $q->fetchColumn() > 0) {
                            throw new WorkforceProblem('Hay turnos futuros o en curso. Cancélalos o reasígnalos antes de desactivar la sucursal.');
                        }
                        if ((int) $branch['is_main'] === 1) {
                            $q = $pdo->prepare("SELECT id FROM branches WHERE id<>:id AND status='active' ORDER BY id LIMIT 1 FOR UPDATE");
                            $q->execute(['id' => $id]);
                            $replacement = $q->fetchColumn();
                            if (! $replacement) {
                                throw new WorkforceProblem('No puedes desactivar la única sucursal activa.');
                            }$pdo->prepare('UPDATE branches SET is_main=0 WHERE id=:id')->execute(['id' => $id]);
                            $pdo->prepare('UPDATE branches SET is_main=1 WHERE id=:id')->execute(['id' => $replacement]);
                        }
                    }
                    $q = $pdo->prepare('UPDATE branches SET status=:status,updated_by=:uid,updated_at=NOW() WHERE id=:id');
                    $q->execute(['status' => $status, 'uid' => $actor['id'], 'id' => $id]);
                    $pdo->commit();
                    $this->wf_audit($pdo, $actor, 'branches.status', ['branch_id' => $id, 'status' => $status]);
                    json_response(['ok' => true, 'message' => 'Estado de la sucursal actualizado.']);
                } catch (Throwable $e) {
                    if ($e instanceof HttpResponseException) {
                        throw $e;
                    }if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }throw $e;
                }
            }
            if ($action === 'employee.save') {
                $actor = require_permission('employees.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                $code = $this->wf_text($body['employee_code'] ?? null, 30, 'código del empleado', true);
                $code = strtoupper($code);
                if (! preg_match('/^[A-Z0-9][A-Z0-9_-]{1,29}$/', $code)) {
                    throw new WorkforceProblem('El código debe tener entre 2 y 30 caracteres alfanuméricos, guion o guion bajo.');
                }
                $name = $this->wf_text($body['full_name'] ?? null, 150, 'nombre completo', true);
                $job = $this->wf_text($body['job_title'] ?? null, 100, 'puesto', true);
                $phone = $this->wf_text($body['phone'] ?? null, 40, 'teléfono', false);
                $email = $this->wf_text($body['email'] ?? null, 190, 'correo', false);
                if ($email && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new WorkforceProblem('El correo del empleado no es válido.');
                }
                $userId = $this->wf_int($body['user_id'] ?? null, 'cuenta del sistema', false);
                $branchId = $this->wf_int($body['branch_id'] ?? null, 'sucursal', false);
                $hireDate = $this->wf_date($body['hire_date'] ?? null, 'fecha de contratación', false);
                $type = (string) ($body['employment_type'] ?? 'full_time');
                if (! in_array($type, ['full_time', 'part_time', 'temporary', 'contractor'], true)) {
                    throw new WorkforceProblem('Tipo de contrato no válido.');
                }
                $notes = $this->wf_text($body['notes'] ?? null, 1000, 'notas', false);
                $status = (string) ($body['status'] ?? 'active');
                if (! in_array($status, ['active', 'inactive'], true)) {
                    throw new WorkforceProblem('Estado de personal no válido.');
                }
                $hourlyRate = null;
                if (in_array('employees.compensation.view', $actor['permissions'] ?? [], true)) {
                    $raw = $body['hourly_rate'] ?? '';
                    if ($raw !== '') {
                        if (! is_numeric($raw) || ! is_finite((float) $raw) || (float) $raw < 0 || (float) $raw > 1000000) {
                            throw new WorkforceProblem('La tarifa por hora no es válida.');
                        }$hourlyRate = round((float) $raw, 2);
                    }
                }
                if ($branchId) {
                    $q = $pdo->prepare("SELECT id FROM branches WHERE id=:id AND status='active'");
                    $q->execute(['id' => $branchId]);
                    if (! $q->fetch()) {
                        throw new WorkforceProblem('Selecciona una sucursal activa.');
                    }
                }
                if ($userId) {
                    $q = $pdo->prepare("SELECT id FROM users WHERE id=:id AND status='active'");
                    $q->execute(['id' => $userId]);
                    if (! $q->fetch()) {
                        throw new WorkforceProblem('La cuenta vinculada no existe o está inactiva.');
                    }$q = $pdo->prepare('SELECT id FROM employees WHERE user_id=:uid AND id<>:id');
                    $q->execute(['uid' => $userId, 'id' => $id ?? 0]);
                    if ($q->fetch()) {
                        throw new WorkforceProblem('Esa cuenta ya está vinculada a otra ficha de personal.');
                    }
                }
                try {
                    if ($id) {
                        $q = $pdo->prepare('SELECT id, hourly_rate FROM employees WHERE id=:id');
                        $q->execute(['id' => $id]);
                        $existingEmployee = $q->fetch();
                        if (! $existingEmployee) {
                            throw new WorkforceProblem('La ficha de personal no existe.', 404);
                        }if (! in_array('employees.compensation.view', $actor['permissions'] ?? [], true)) {
                            $hourlyRate = $existingEmployee['hourly_rate'];
                        }if ($status === 'inactive') {
                            $q = $pdo->prepare("SELECT COUNT(*) FROM employee_shifts WHERE employee_id=:id AND (status='in_progress' OR (shift_date>=:today AND status='scheduled'))");
                            $q->execute(['id' => $id, 'today' => date('Y-m-d')]);
                            if ((int) $q->fetchColumn() > 0) {
                                throw new WorkforceProblem('Cancela los turnos futuros o en curso antes de desactivar al empleado.');
                            }
                        }
                        $q = $pdo->prepare('UPDATE employees SET employee_code=:code,full_name=:name,user_id=:user_id,branch_id=:branch_id,job_title=:job_title,phone=:phone,email=:email,employment_type=:employment_type,hire_date=:hire_date,hourly_rate=:hourly_rate,status=:status,notes=:notes,updated_by=:uid,updated_at=NOW() WHERE id=:id');
                        $q->execute(['code' => $code, 'name' => $name, 'user_id' => $userId, 'branch_id' => $branchId, 'job_title' => $job, 'phone' => $phone, 'email' => $email, 'employment_type' => $type, 'hire_date' => $hireDate, 'hourly_rate' => $hourlyRate, 'status' => $status, 'notes' => $notes, 'uid' => $actor['id'], 'id' => $id]);
                        $newId = $id;
                        $event = 'employees.updated';
                    } else {
                        $q = $pdo->prepare('INSERT INTO employees (employee_code,full_name,user_id,branch_id,job_title,phone,email,employment_type,hire_date,hourly_rate,status,notes,created_by,updated_by) VALUES (:code,:name,:user_id,:branch_id,:job_title,:phone,:email,:employment_type,:hire_date,:hourly_rate,:status,:notes,:created_by,:updated_by)');
                        $q->execute(['code' => $code, 'name' => $name, 'user_id' => $userId, 'branch_id' => $branchId, 'job_title' => $job, 'phone' => $phone, 'email' => $email, 'employment_type' => $type, 'hire_date' => $hireDate, 'hourly_rate' => $hourlyRate, 'status' => $status, 'notes' => $notes, 'created_by' => $actor['id'], 'updated_by' => $actor['id']]);
                        $newId = (int) $pdo->lastInsertId();
                        $event = 'employees.created';
                    }
                    $this->wf_audit($pdo, $actor, $event, ['employee_id' => $newId, 'employee_code' => $code, 'status' => $status]);
                    json_response(['ok' => true, 'message' => 'Ficha de personal guardada.', 'id' => $newId]);
                } catch (PDOException $e) {
                    if ((string) $e->getCode() === '23000') {
                        throw new WorkforceProblem('El código ya existe o la cuenta está vinculada a otra ficha.', 409);
                    }throw $e;
                }
            }
            if ($action === 'employee.status') {
                $actor = require_permission('employees.manage');
                $id = $this->wf_int($body['id'] ?? null, 'empleado');
                $status = (string) ($body['status'] ?? '');
                if (! in_array($status, ['active', 'inactive'], true)) {
                    throw new WorkforceProblem('Estado no válido.');
                }
                if ($status === 'inactive') {
                    $q = $pdo->prepare("SELECT COUNT(*) FROM employee_shifts WHERE employee_id=:id AND (status='in_progress' OR (shift_date>=:today AND status='scheduled'))");
                    $q->execute(['id' => $id, 'today' => date('Y-m-d')]);
                    if ((int) $q->fetchColumn() > 0) {
                        throw new WorkforceProblem('Cancela primero los turnos futuros o en curso.');
                    }
                }
                $q = $pdo->prepare('UPDATE employees SET status=:status,updated_by=:uid,updated_at=NOW() WHERE id=:id');
                $q->execute(['status' => $status, 'uid' => $actor['id'], 'id' => $id]);
                if (! $q->rowCount()) {
                    $check = $pdo->prepare('SELECT id FROM employees WHERE id=:id');
                    $check->execute(['id' => $id]);
                    if (! $check->fetch()) {
                        throw new WorkforceProblem('Empleado no encontrado.', 404);
                    }
                }
                $this->wf_audit($pdo, $actor, 'employees.status', ['employee_id' => $id, 'status' => $status]);
                json_response(['ok' => true, 'message' => 'Estado del empleado actualizado.']);
            }
            if ($action === 'shift.save') {
                $actor = require_permission('shifts.manage');
                $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                $employeeId = $this->wf_int($body['employee_id'] ?? null, 'empleado');
                $branchId = $this->wf_int($body['branch_id'] ?? null, 'sucursal');
                $date = $this->wf_date($body['shift_date'] ?? null, 'fecha');
                $start = $this->wf_time($body['start_time'] ?? null, 'inicio');
                $end = $this->wf_time($body['end_time'] ?? null, 'fin');
                $break = filter_var($body['break_minutes'] ?? 0, FILTER_VALIDATE_INT);
                if ($break === false || $break < 0 || $break > 240) {
                    throw new WorkforceProblem('El descanso debe estar entre 0 y 240 minutos.');
                }$notes = $this->wf_text($body['notes'] ?? null, 1000, 'notas', false);
                $q = $pdo->prepare("SELECT id,branch_id FROM employees WHERE id=:id AND status='active'");
                $q->execute(['id' => $employeeId]);
                $employee = $q->fetch();
                if (! $employee) {
                    throw new WorkforceProblem('El empleado no existe o está inactivo.');
                }
                $q = $pdo->prepare("SELECT id FROM branches WHERE id=:id AND status='active'");
                $q->execute(['id' => $branchId]);
                if (! $q->fetch()) {
                    throw new WorkforceProblem('La sucursal no existe o está inactiva.');
                }
                $startDateTime = $date.' '.$start;
                $endDateTime = $date.' '.$end;
                if ($end <= $start) {
                    $endDateTime = (new DateTimeImmutable($endDateTime))->modify('+1 day')->format('Y-m-d H:i:s');
                }if (strtotime($endDateTime) - strtotime($startDateTime) > 16 * 3600) {
                    throw new WorkforceProblem('Un turno no puede superar 16 horas.');
                }$paidMinutes = (strtotime($endDateTime) - strtotime($startDateTime)) / 60 - (int) $break;
                if ($paidMinutes <= 0) {
                    throw new WorkforceProblem('El descanso debe ser menor que la duración del turno.');
                }
                if ($id) {
                    $q = $pdo->prepare('SELECT status FROM employee_shifts WHERE id=:id');
                    $q->execute(['id' => $id]);
                    $existing = $q->fetch();
                    if (! $existing) {
                        throw new WorkforceProblem('El turno no existe.', 404);
                    }if ($existing['status'] !== 'scheduled') {
                        throw new WorkforceProblem('Solo se pueden editar turnos programados que no hayan comenzado.');
                    }
                }
                $sql = "SELECT id FROM employee_shifts WHERE employee_id=:employee_id AND status<>'cancelled' AND id<>:id AND TIMESTAMP(shift_date,start_time)<:new_end AND (CASE WHEN end_time<=start_time THEN TIMESTAMP(DATE_ADD(shift_date,INTERVAL 1 DAY),end_time) ELSE TIMESTAMP(shift_date,end_time) END)>:new_start LIMIT 1";
                $q = $pdo->prepare($sql);
                $q->execute(['employee_id' => $employeeId, 'id' => $id ?? 0, 'new_end' => $endDateTime, 'new_start' => $startDateTime]);
                if ($q->fetch()) {
                    throw new WorkforceProblem('El empleado ya tiene otro turno que se cruza con este horario.');
                }
                if ($id) {
                    $q = $pdo->prepare('UPDATE employee_shifts SET employee_id=:employee_id,branch_id=:branch_id,shift_date=:shift_date,start_time=:start_time,end_time=:end_time,break_minutes=:break_minutes,notes=:notes,updated_by=:uid,updated_at=NOW() WHERE id=:id');
                    $q->execute(['employee_id' => $employeeId, 'branch_id' => $branchId, 'shift_date' => $date, 'start_time' => $start, 'end_time' => $end, 'break_minutes' => $break, 'notes' => $notes, 'uid' => $actor['id'], 'id' => $id]);
                    $shiftId = $id;
                    $event = 'shifts.updated';
                } else {
                    $q = $pdo->prepare("INSERT INTO employee_shifts (employee_id,branch_id,shift_date,start_time,end_time,break_minutes,status,notes,created_by,updated_by) VALUES (:employee_id,:branch_id,:shift_date,:start_time,:end_time,:break_minutes,'scheduled',:notes,:created_by,:updated_by)");
                    $q->execute(['employee_id' => $employeeId, 'branch_id' => $branchId, 'shift_date' => $date, 'start_time' => $start, 'end_time' => $end, 'break_minutes' => $break, 'notes' => $notes, 'created_by' => $actor['id'], 'updated_by' => $actor['id']]);
                    $shiftId = (int) $pdo->lastInsertId();
                    $event = 'shifts.created';
                }
                $this->wf_audit($pdo, $actor, $event, ['shift_id' => $shiftId, 'employee_id' => $employeeId, 'branch_id' => $branchId, 'shift_date' => $date]);
                json_response(['ok' => true, 'message' => 'Turno guardado.', 'id' => $shiftId]);
            }
            if ($action === 'shift.status') {
                $actor = require_permission('shifts.manage');
                $id = $this->wf_int($body['id'] ?? null, 'turno');
                $status = (string) ($body['status'] ?? '');
                if ($status !== 'cancelled') {
                    throw new WorkforceProblem('Solo se permite cancelar un turno desde esta acción.');
                }$q = $pdo->prepare("UPDATE employee_shifts SET status='cancelled',updated_by=:uid,updated_at=NOW() WHERE id=:id AND status='scheduled'");
                $q->execute(['uid' => $actor['id'], 'id' => $id]);
                if (! $q->rowCount()) {
                    throw new WorkforceProblem('El turno no existe o ya inició y no puede cancelarse.');
                }$this->wf_audit($pdo, $actor, 'shifts.cancelled', ['shift_id' => $id]);
                json_response(['ok' => true, 'message' => 'Turno cancelado.']);
            }
            if (in_array($action, ['shift.clock_in', 'shift.clock_out'], true)) {
                $actor = require_any_permission(['attendance.manage', 'attendance.clock']);
                $id = $this->wf_int($body['id'] ?? null, 'turno');
                $isIn = $action === 'shift.clock_in';
                $pdo->beginTransaction();
                try {
                    $q = $pdo->prepare('SELECT s.*,e.user_id AS employee_user_id,e.full_name FROM employee_shifts s JOIN employees e ON e.id=s.employee_id WHERE s.id=:id FOR UPDATE');
                    $q->execute(['id' => $id]);
                    $shift = $q->fetch();
                    if (! $shift) {
                        throw new WorkforceProblem('El turno no existe.', 404);
                    }
                    $canManage = in_array('attendance.manage', $actor['permissions'] ?? [], true) || in_array('shifts.manage', $actor['permissions'] ?? [], true);
                    if (! $canManage && (empty($shift['employee_user_id']) || (int) $shift['employee_user_id'] !== (int) $actor['id'])) {
                        throw new WorkforceProblem('Solo puedes registrar la asistencia de tu propia ficha vinculada.', 403);
                    }
                    if ($isIn) {
                        if ($shift['status'] !== 'scheduled' || $shift['actual_start_at'] !== null) {
                            throw new WorkforceProblem('La entrada de este turno ya fue registrada o el turno no está programado.');
                        }$pdo->prepare("UPDATE employee_shifts SET status='in_progress',actual_start_at=UTC_TIMESTAMP(),updated_by=:uid WHERE id=:id")->execute(['uid' => $actor['id'], 'id' => $id]);
                        $event = 'clock_in';
                        $message = 'Entrada registrada.';
                    } else {
                        if ($shift['status'] !== 'in_progress' || $shift['actual_start_at'] === null || $shift['actual_end_at'] !== null) {
                            throw new WorkforceProblem('Solo puedes registrar salida de un turno en curso.');
                        }$pdo->prepare("UPDATE employee_shifts SET status='completed',actual_end_at=UTC_TIMESTAMP(),updated_by=:uid WHERE id=:id")->execute(['uid' => $actor['id'], 'id' => $id]);
                        $event = 'clock_out';
                        $message = 'Salida registrada. El turno quedó completado.';
                    }
                    $q = $pdo->prepare('INSERT INTO attendance_events (shift_id,event_type,recorded_at,recorded_by,source,notes) VALUES (:shift_id,:event_type,UTC_TIMESTAMP(),:uid,:source,:notes)');
                    $q->execute(['shift_id' => $id, 'event_type' => $event, 'uid' => $actor['id'], 'source' => $canManage ? 'admin' : 'web', 'notes' => $this->wf_text($body['notes'] ?? null, 500, 'nota', false)]);
                    $pdo->commit();
                    $this->wf_audit($pdo, $actor, 'attendance.'.$event, ['shift_id' => $id, 'employee_id' => (int) $shift['employee_id']]);
                    json_response(['ok' => true, 'message' => $message]);
                } catch (Throwable $e) {
                    if ($e instanceof HttpResponseException) {
                        throw $e;
                    }if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }throw $e;
                }
            }
            json_response(['ok' => false, 'message' => 'Acción de personal no reconocida.'], 404);
        } catch (WorkforceProblem $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], $e->status);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            error_log('[DESEGO workforce] '.$e->getMessage());
            json_response(['ok' => false, 'message' => 'No se pudo completar la operación de personal. Revisa los registros del servidor.'], 500);
        }

        return response()->noContent();
    }

    protected function wf_text(mixed $value, int $max, string $label, bool $required = false): ?string
    {
        $v = trim((string) ($value ?? ''));
        if ($v === '') {
            if ($required) {
                throw new WorkforceProblem("El campo {$label} es obligatorio.");
            }

            return null;
        }
        if (text_length($v) > $max) {
            throw new WorkforceProblem("El campo {$label} supera la longitud permitida.");
        }

        return $v;
    }

    protected function wf_int(mixed $value, string $label, bool $required = true): ?int
    {
        if ($value === null || $value === '') {
            if ($required) {
                throw new WorkforceProblem("Selecciona {$label}.");
            }

            return null;
        }
        $v = filter_var($value, FILTER_VALIDATE_INT);
        if (! $v || $v < 1) {
            throw new WorkforceProblem("El valor de {$label} no es válido.");
        }

        return (int) $v;
    }

    protected function wf_date(mixed $value, string $label, bool $required = true): ?string
    {
        $v = trim((string) ($value ?? ''));
        if ($v === '') {
            if ($required) {
                throw new WorkforceProblem("Selecciona {$label}.");
            }

            return null;
        }
        $dt = DateTime::createFromFormat('!Y-m-d', $v);
        if (! $dt || $dt->format('Y-m-d') !== $v) {
            throw new WorkforceProblem("La fecha de {$label} no es válida.");
        }

        return $v;
    }

    protected function wf_time(mixed $value, string $label): string
    {
        $v = trim((string) ($value ?? ''));
        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $v)) {
            throw new WorkforceProblem("La hora de {$label} no es válida.");
        }

        return strlen($v) === 5 ? $v.':00' : $v;
    }

    protected function wf_permissions(array $user, array $allowed): bool
    {
        return count(array_intersect($user['permissions'] ?? [], $allowed)) > 0;
    }

    protected function wf_audit(PDO $pdo, array $actor, string $event, array $details = []): void
    {
        audit_log($pdo, (int) $actor['id'], $event, $details);
    }

    protected function wf_employee_by_user(PDO $pdo, int $userId): ?array
    {
        $s = $pdo->prepare("SELECT id,branch_id,full_name FROM employees WHERE user_id=:uid AND status='active' LIMIT 1");
        $s->execute(['uid' => $userId]);
        $r = $s->fetch();

        return $r ?: null;
    }
}
