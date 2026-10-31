-- DESEGO CEVICHERÍA - PASO 14: personal, turnos, asistencia y sucursales.
-- Importar sobre la misma base de datos del Paso 13. No elimina datos existentes.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS branches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(30) NOT NULL,
    name VARCHAR(150) NOT NULL,
    tax_id VARCHAR(20) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    address VARCHAR(255) NULL,
    district VARCHAR(100) NULL,
    province VARCHAR(100) NULL,
    region VARCHAR(100) NULL,
    opening_time TIME NULL,
    closing_time TIME NULL,
    is_main TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_step14_branch_code (code),
    KEY idx_step14_branch_status (status, name),
    CONSTRAINT fk_step14_branch_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_step14_branch_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Solo crea una sede inicial si la instalación todavía no tiene ninguna.
INSERT INTO branches (code, name, is_main, status)
SELECT 'DES-MAIN', 'DESEGO Sede Principal', 1, 'active'
WHERE NOT EXISTS (SELECT 1 FROM branches);

CREATE TABLE IF NOT EXISTS employees (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_code VARCHAR(30) NULL,
    full_name VARCHAR(150) NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    branch_id BIGINT UNSIGNED NULL,
    job_title VARCHAR(100) NOT NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    employment_type ENUM('full_time','part_time','temporary','contractor') NOT NULL DEFAULT 'full_time',
    hire_date DATE NULL,
    hourly_rate DECIMAL(12,2) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    notes VARCHAR(1000) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_step14_employee_code (employee_code),
    UNIQUE KEY uq_step14_employee_user (user_id),
    KEY idx_step14_employee_status_branch (status, branch_id),
    KEY idx_step14_employee_name (full_name),
    CONSTRAINT fk_step14_employee_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_step14_employee_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    CONSTRAINT fk_step14_employee_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_step14_employee_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_shifts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    shift_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    break_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    actual_start_at DATETIME NULL,
    actual_end_at DATETIME NULL,
    notes VARCHAR(1000) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_step14_shift_date_status (shift_date, status),
    KEY idx_step14_shift_employee_date (employee_id, shift_date, status),
    KEY idx_step14_shift_branch_date (branch_id, shift_date),
    CONSTRAINT fk_step14_shift_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
    CONSTRAINT fk_step14_shift_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
    CONSTRAINT fk_step14_shift_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_step14_shift_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    shift_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    event_type ENUM('clock_in','clock_out','manual_adjustment') NOT NULL,
    recorded_at DATETIME NOT NULL,
    recorded_by BIGINT UNSIGNED NULL,
    source ENUM('web','admin') NOT NULL DEFAULT 'web',
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_step14_attendance_employee_date (employee_id, recorded_at),
    KEY idx_step14_attendance_shift (shift_id, recorded_at),
    CONSTRAINT fk_step14_attendance_shift FOREIGN KEY (shift_id) REFERENCES employee_shifts(id) ON DELETE CASCADE,
    CONSTRAINT fk_step14_attendance_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
    CONSTRAINT fk_step14_attendance_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
    CONSTRAINT fk_step14_attendance_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (code, name, module, description) VALUES
('branches.view', 'Consultar sucursales', 'branches', 'Ver la lista y datos básicos de sucursales.'),
('branches.manage', 'Gestionar sucursales', 'branches', 'Crear, editar y activar o desactivar sucursales.'),
('employees.view', 'Consultar personal', 'employees', 'Consultar fichas del personal y su asignación.'),
('employees.manage', 'Gestionar personal', 'employees', 'Crear, editar y activar o desactivar fichas de empleados.'),
('employees.compensation.view', 'Consultar costos de personal', 'employees', 'Consultar las tarifas horarias registradas.'),
('shifts.view', 'Consultar turnos', 'shifts', 'Consultar la programación de turnos.'),
('shifts.manage', 'Gestionar turnos', 'shifts', 'Crear, editar y cancelar turnos.'),
('attendance.manage', 'Administrar asistencia', 'attendance', 'Registrar entrada y salida de cualquier empleado.'),
('attendance.clock', 'Registrar propia asistencia', 'attendance', 'Registrar entrada y salida de la propia ficha vinculada.');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'branches.view','branches.manage','employees.view','employees.manage','employees.compensation.view',
  'shifts.view','shifts.manage','attendance.manage','attendance.clock'
) WHERE r.code = 'manager';

-- Los equipos operativos pueden consultar su planificación y registrar la propia entrada/salida.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('shifts.view','attendance.clock')
WHERE r.code IN ('cashier','kitchen','waiter','delivery','collaborator');

-- Asegura que el gerente pueda consultar la ficha del personal incluso si las migraciones previas variaron.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('employees.view','branches.view','shifts.view')
WHERE r.code = 'manager';
