import React from 'react';

export default function AdminWorkforceSystemViews() {
    return (
        <>
            {/* SUCURSALES */}
            <section id="view-branches" className="app-view" aria-labelledby="branchesTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">ORGANIZACIÓN</span>
                        <h1 id="branchesTitle">Sucursales</h1>
                        <p>Gestiona sedes, horarios y datos de contacto del restaurante.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button" data-go="profile">
                            ← Perfil
                        </button>
                        <button type="button" className="cyan-button heading-button" id="refreshBranchesButton">
                            ↻ Actualizar
                        </button>
                    </div>
                </div>
                <div id="branchSummary" className="report-summary-grid workforce-summary">
                    <article className="white-card report-kpi">
                        <small>Sucursales activas</small>
                        <strong>—</strong>
                        <span>Ubicaciones operativas</span>
                    </article>
                </div>
                <div id="branchFormCard" className="white-card purchasing-form-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">CONFIGURACIÓN DE SEDE</span>
                            <h2 id="branchFormTitle">Registrar sucursal</h2>
                            <p>Define datos de contacto, ubicación y horario de atención.</p>
                        </div>
                    </div>
                    <form id="branchForm" className="purchasing-grid-form workforce-form">
                        <input type="hidden" id="branchId" />
                        <div className="form-field">
                            <label htmlFor="branchCode">Código *</label>
                            <input id="branchCode" maxLength="30" required placeholder="DES-CENTRO" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="branchName">Nombre de la sucursal *</label>
                            <input id="branchName" maxLength="150" required placeholder="DESEGO Sede Centro" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="branchTaxId">RUC</label>
                            <input id="branchTaxId" maxLength="20" placeholder="RUC de la sede" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="branchPhone">Teléfono</label>
                            <input id="branchPhone" maxLength="40" placeholder="01 555 5555" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="branchEmail">Correo</label>
                            <input id="branchEmail" type="email" maxLength="190" placeholder="sede@desego.pe" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="branchDistrict">Distrito</label>
                            <input id="branchDistrict" maxLength="100" placeholder="Ate" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="branchProvince">Provincia</label>
                            <input id="branchProvince" maxLength="100" placeholder="Lima" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="branchRegion">Región</label>
                            <input id="branchRegion" maxLength="100" placeholder="Lima" />
                        </div>
                        <div className="form-field form-field-wide">
                            <label htmlFor="branchAddress">Dirección</label>
                            <input id="branchAddress" maxLength="255" placeholder="Dirección de la sede" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="branchOpeningTime">Hora de apertura</label>
                            <input id="branchOpeningTime" type="time" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="branchClosingTime">Hora de cierre</label>
                            <input id="branchClosingTime" type="time" />
                        </div>
                        <label className="workforce-check form-field-wide">
                            <input type="checkbox" id="branchIsMain" />
                            <span>Marcar como sucursal principal</span>
                        </label>
                        <div className="purchasing-form-actions form-field-wide">
                            <button type="button" id="branchResetButton" className="secondary-button">
                                Limpiar
                            </button>
                            <button type="submit" className="cyan-button">
                                Guardar sucursal
                            </button>
                        </div>
                    </form>
                </div>
                <div className="white-card purchasing-list-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">DIRECTORIO</span>
                            <h2>Sucursales registradas</h2>
                            <p>Las sedes con personal activo o turnos futuros no pueden desactivarse hasta reasignarlos.</p>
                        </div>
                        <span id="branchCount" className="counter-chip">Cargando…</span>
                    </div>
                    <label className="menu-search">
                        <span aria-hidden="true">⌕</span>
                        <input id="branchSearch" type="search" placeholder="Buscar por código, nombre o ubicación…" aria-label="Buscar sucursales" />
                    </label>
                    <div id="branchList" className="purchasing-list">
                        <div className="manager-loading">Cargando sucursales…</div>
                    </div>
                </div>
            </section>

            {/* PERSONAL */}
            <section id="view-employees" className="app-view" aria-labelledby="employeesTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">RECURSOS HUMANOS</span>
                        <h1 id="employeesTitle">Personal</h1>
                        <p>Fichas de empleados, puestos, cuenta vinculada y sucursal base.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button" data-go="shifts">
                            ▦ Turnos
                        </button>
                        <button type="button" className="secondary-button heading-button" data-go="branches">
                            ⌂ Sucursales
                        </button>
                        <button type="button" className="secondary-button heading-button" data-go="profile">
                            ← Perfil
                        </button>
                        <button type="button" className="cyan-button heading-button" id="refreshEmployeesButton">
                            ↻ Actualizar
                        </button>
                    </div>
                </div>
                <div id="employeeSummary" className="report-summary-grid workforce-summary">
                    <article className="white-card report-kpi">
                        <small>Personal activo</small>
                        <strong>—</strong>
                        <span>Fichas de empleados</span>
                    </article>
                </div>
                <div id="employeeFormCard" className="white-card purchasing-form-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">FICHA DEL COLABORADOR</span>
                            <h2 id="employeeFormTitle">Registrar empleado</h2>
                            <p>La cuenta del sistema es opcional; si se vincula, se podrá limitar la asistencia a su propia ficha.</p>
                        </div>
                    </div>
                    <form id="employeeForm" className="purchasing-grid-form workforce-form">
                        <input type="hidden" id="employeeId" />
                        <div className="form-field">
                            <label htmlFor="employeeCode">Código de empleado *</label>
                            <input id="employeeCode" maxLength="30" required placeholder="EMP-001" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="employeeName">Nombre completo *</label>
                            <input id="employeeName" maxLength="150" required placeholder="Nombre y apellidos" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="employeeJobTitle">Puesto *</label>
                            <input id="employeeJobTitle" maxLength="100" required placeholder="Cajero, cocinero, mozo…" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="employeeBranch">Sucursal base</label>
                            <select id="employeeBranch" defaultValue="">
                                <option value="">Sin asignar</option>
                            </select>
                        </div>
                        <div className="form-field">
                            <label htmlFor="employeeUser">Cuenta DESEGO vinculada</label>
                            <select id="employeeUser" defaultValue="">
                                <option value="">Sin cuenta vinculada</option>
                            </select>
                        </div>
                        <div className="form-field">
                            <label htmlFor="employeePhone">Teléfono</label>
                            <input id="employeePhone" maxLength="40" placeholder="Teléfono de contacto" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="employeeEmail">Correo</label>
                            <input id="employeeEmail" type="email" maxLength="190" placeholder="empleado@desego.pe" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="employeeType">Tipo de contrato</label>
                            <select id="employeeType" defaultValue="full_time">
                                <option value="full_time">Tiempo completo</option>
                                <option value="part_time">Medio tiempo</option>
                                <option value="temporary">Temporal</option>
                                <option value="contractor">Servicios / contratista</option>
                            </select>
                        </div>
                        <div className="form-field">
                            <label htmlFor="employeeHireDate">Fecha de contratación</label>
                            <input id="employeeHireDate" type="date" />
                        </div>
                        <div className="form-field" id="employeeRateField">
                            <label htmlFor="employeeHourlyRate">Tarifa por hora (S/)</label>
                            <input id="employeeHourlyRate" type="number" min="0" max="1000000" step="0.01" placeholder="0.00" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="employeeStatus">Estado</label>
                            <select id="employeeStatus" defaultValue="active">
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                            </select>
                        </div>
                        <div className="form-field form-field-wide">
                            <label htmlFor="employeeNotes">Notas internas</label>
                            <textarea id="employeeNotes" rows="2" maxLength="1000" placeholder="Observaciones administrativas…"></textarea>
                        </div>
                        <div className="purchasing-form-actions form-field-wide">
                            <button type="button" id="employeeResetButton" className="secondary-button">
                                Limpiar
                            </button>
                            <button type="submit" className="cyan-button">
                                Guardar empleado
                            </button>
                        </div>
                    </form>
                </div>
                <div className="white-card purchasing-list-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">DIRECTORIO DE PERSONAL</span>
                            <h2>Empleados registrados</h2>
                            <p>Los empleados vinculados a una cuenta pueden registrar su propia asistencia si tienen permiso.</p>
                        </div>
                        <span id="employeeCount" className="counter-chip">Cargando…</span>
                    </div>
                    <label className="menu-search">
                        <span aria-hidden="true">⌕</span>
                        <input id="employeeSearch" type="search" placeholder="Buscar por nombre, código, puesto o sucursal…" aria-label="Buscar personal" />
                    </label>
                    <div id="employeeList" className="purchasing-list">
                        <div className="manager-loading">Cargando personal…</div>
                    </div>
                </div>
            </section>

            {/* TURNOS Y ASISTENCIA */}
            <section id="view-shifts" className="app-view" aria-labelledby="shiftsTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">PLANIFICACIÓN · ASISTENCIA</span>
                        <h1 id="shiftsTitle">Turnos y asistencia</h1>
                        <p>Programa horarios, controla entradas y salidas y revisa turnos por sede.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button" data-go="employees">
                            ♙ Personal
                        </button>
                        <button type="button" className="secondary-button heading-button" data-go="branches">
                            ⌂ Sucursales
                        </button>
                        <button type="button" className="secondary-button heading-button" data-go="profile">
                            ← Perfil
                        </button>
                        <button type="button" className="cyan-button heading-button" id="refreshShiftsButton">
                            ↻ Actualizar
                        </button>
                    </div>
                </div>
                <div id="shiftSummary" className="report-summary-grid workforce-summary">
                    <article className="white-card report-kpi">
                        <small>Turnos programados</small>
                        <strong>—</strong>
                        <span>Periodo seleccionado</span>
                    </article>
                </div>
                <div className="white-card workforce-filter-card">
                    <form id="shiftFilterForm" className="workforce-filter-form">
                        <div className="form-field">
                            <label htmlFor="shiftFilterFrom">Desde</label>
                            <input type="date" id="shiftFilterFrom" required />
                        </div>
                        <div className="form-field">
                            <label htmlFor="shiftFilterTo">Hasta</label>
                            <input type="date" id="shiftFilterTo" required />
                        </div>
                        <div className="form-field">
                            <label htmlFor="shiftFilterBranch">Sucursal</label>
                            <select id="shiftFilterBranch" defaultValue="">
                                <option value="">Todas las sucursales</option>
                            </select>
                        </div>
                        <button type="submit" className="cyan-button">
                            Filtrar
                        </button>
                    </form>
                </div>
                <div id="shiftFormCard" className="white-card purchasing-form-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">PROGRAMACIÓN</span>
                            <h2 id="shiftFormTitle">Programar turno</h2>
                            <p>Se rechazará cualquier horario que se cruce con otro turno no cancelado del mismo empleado.</p>
                        </div>
                    </div>
                    <form id="shiftForm" className="purchasing-grid-form workforce-form">
                        <input type="hidden" id="shiftId" />
                        <div className="form-field">
                            <label htmlFor="shiftEmployee">Empleado *</label>
                            <select id="shiftEmployee" required defaultValue="">
                                <option value="">Selecciona un empleado…</option>
                            </select>
                        </div>
                        <div className="form-field">
                            <label htmlFor="shiftBranch">Sucursal *</label>
                            <select id="shiftBranch" required defaultValue="">
                                <option value="">Selecciona una sucursal…</option>
                            </select>
                        </div>
                        <div className="form-field">
                            <label htmlFor="shiftDate">Fecha *</label>
                            <input type="date" id="shiftDate" required />
                        </div>
                        <div className="form-field">
                            <label htmlFor="shiftStart">Hora de inicio *</label>
                            <input type="time" id="shiftStart" defaultValue="09:00" required />
                        </div>
                        <div className="form-field">
                            <label htmlFor="shiftEnd">Hora de fin *</label>
                            <input type="time" id="shiftEnd" defaultValue="17:00" required />
                        </div>
                        <div className="form-field">
                            <label htmlFor="shiftBreak">Descanso (minutos)</label>
                            <input id="shiftBreak" type="number" min="0" max="240" step="5" defaultValue="30" required />
                        </div>
                        <div className="form-field form-field-wide">
                            <label htmlFor="shiftNotes">Notas</label>
                            <textarea id="shiftNotes" rows="2" maxLength="1000" placeholder="Área, entrega de turno, indicaciones…"></textarea>
                        </div>
                        <div className="purchasing-form-actions form-field-wide">
                            <button type="button" id="shiftResetButton" className="secondary-button">
                                Limpiar
                            </button>
                            <button type="submit" className="cyan-button">
                                Guardar turno
                            </button>
                        </div>
                    </form>
                </div>
                <div className="white-card purchasing-list-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">AGENDA</span>
                            <h2>Programación de turnos</h2>
                            <p>Las entradas y salidas quedan registradas con fecha y hora del servidor.</p>
                        </div>
                        <span id="shiftCount" className="counter-chip">Cargando…</span>
                    </div>
                    <div id="shiftList" className="purchasing-list">
                        <div className="manager-loading">Cargando turnos…</div>
                    </div>
                </div>
                <div className="white-card attendance-history-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">CONTROL HORARIO</span>
                            <h2>Registro de asistencia</h2>
                            <p>La bitácora de entradas y salidas queda en la base de datos; los administradores pueden consultarla desde Auditoría.</p>
                        </div>
                    </div>
                    <p className="operations-footnote">
                        La versión actual registra hora real de entrada y salida, pero no calcula horas extras, descansos legales ni planilla salarial.
                    </p>
                </div>
            </section>

            {/* PASO 15: NOTIFICACIONES */}
            <section id="view-notifications" className="app-view" aria-labelledby="notificationsTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">COMUNICACIÓN INTERNA</span>
                        <h1 id="notificationsTitle">Notificaciones</h1>
                        <p>Avisos personales y comunicados del restaurante.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button" data-go="profile">
                            ← Perfil
                        </button>
                        <button type="button" className="cyan-button heading-button" id="refreshNotificationsButton">
                            ↻ Actualizar
                        </button>
                        <button type="button" className="secondary-button heading-button" id="markAllNotificationsReadButton">
                            Marcar todas como leídas
                        </button>
                    </div>
                </div>
                <div id="notificationsSummary" className="report-summary-grid">
                    <article className="white-card report-kpi">
                        <small>Sin leer</small>
                        <strong>—</strong>
                        <span>Notificaciones personales y globales</span>
                    </article>
                </div>
                <div id="broadcastNotificationCard" className="white-card communications-form-card is-hidden">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">ADMINISTRACIÓN</span>
                            <h2>Publicar aviso</h2>
                            <p>El aviso será visible para los usuarios activos.</p>
                        </div>
                    </div>
                    <form id="broadcastNotificationForm" className="communications-form">
                        <div className="form-field">
                            <label htmlFor="broadcastTitle">Título</label>
                            <input id="broadcastTitle" maxLength="150" required />
                        </div>
                        <div className="form-field">
                            <label htmlFor="broadcastCategory">Tipo</label>
                            <select id="broadcastCategory" defaultValue="info">
                                <option value="info">Información</option>
                                <option value="success">Éxito</option>
                                <option value="warning">Advertencia</option>
                                <option value="error">Alerta</option>
                            </select>
                        </div>
                        <div className="form-field communications-wide">
                            <label htmlFor="broadcastMessage">Mensaje</label>
                            <textarea id="broadcastMessage" rows="3" maxLength="1000" required></textarea>
                        </div>
                        <button className="cyan-button" type="submit">
                            Publicar aviso
                        </button>
                    </form>
                </div>
                <div className="white-card communications-list-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">BANDEJA</span>
                            <h2>Tus notificaciones</h2>
                            <p>Los avisos leídos se conservan como historial.</p>
                        </div>
                        <span id="notificationCount" className="counter-chip">Cargando…</span>
                    </div>
                    <div id="notificationList" className="communications-list">
                        <div className="manager-loading">Cargando notificaciones…</div>
                    </div>
                </div>
            </section>

            {/* PASO 15: SOPORTE */}
            <section id="view-support" className="app-view" aria-labelledby="supportTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">AYUDA Y SEGUIMIENTO</span>
                        <h1 id="supportTitle">Soporte</h1>
                        <p>Registra consultas, incidencias y su resolución.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button" data-go="profile">
                            ← Perfil
                        </button>
                        <button type="button" className="cyan-button heading-button" id="refreshSupportButton">
                            ↻ Actualizar
                        </button>
                    </div>
                </div>
                <div className="communications-grid">
                    <div className="white-card communications-form-card">
                        <div className="card-heading">
                            <div>
                                <span className="section-eyebrow">NUEVO TICKET</span>
                                <h2>Crear solicitud</h2>
                                <p>Describe el problema para dejar constancia y hacer seguimiento.</p>
                            </div>
                        </div>
                        <form id="supportCreateForm" className="communications-form">
                            <div className="form-field communications-wide">
                                <label htmlFor="supportSubject">Asunto</label>
                                <input id="supportSubject" maxLength="180" required placeholder="Ej. No puedo registrar una venta" />
                            </div>
                            <div className="form-field">
                                <label htmlFor="supportCategory">Categoría</label>
                                <select id="supportCategory" defaultValue="technical">
                                    <option value="technical">Problema técnico</option>
                                    <option value="orders">Pedidos y ventas</option>
                                    <option value="inventory">Inventario</option>
                                    <option value="billing">Pagos y comprobantes</option>
                                    <option value="access">Acceso y permisos</option>
                                    <option value="other">Otro</option>
                                </select>
                            </div>
                            <div className="form-field">
                                <label htmlFor="supportPriority">Prioridad</label>
                                <select id="supportPriority" defaultValue="normal">
                                    <option value="low">Baja</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">Alta</option>
                                    <option value="urgent">Urgente</option>
                                </select>
                            </div>
                            <div className="form-field communications-wide">
                                <label htmlFor="supportMessage">Descripción</label>
                                <textarea
                                    id="supportMessage"
                                    rows="5"
                                    maxLength="5000"
                                    required
                                    placeholder="Indica qué ocurrió y qué esperabas que pasara…"
                                ></textarea>
                            </div>
                            <button type="submit" className="cyan-button">
                                Crear ticket
                            </button>
                        </form>
                    </div>
                    <div className="white-card communications-list-card">
                        <div className="card-heading">
                            <div>
                                <span className="section-eyebrow">SEGUIMIENTO</span>
                                <h2>Tickets</h2>
                                <p>Selecciona un ticket para ver el hilo y responder.</p>
                            </div>
                            <button type="button" className="secondary-button" id="refreshTicketsButton">
                                ↻
                            </button>
                        </div>
                        <div className="filter-row support-filter-row" id="supportStatusFilters">
                            <button className="filter-chip is-selected" type="button" data-support-filter="all">
                                Todos
                            </button>
                            <button className="filter-chip" type="button" data-support-filter="open">
                                Abiertos
                            </button>
                            <button className="filter-chip" type="button" data-support-filter="in_progress">
                                En proceso
                            </button>
                            <button className="filter-chip" type="button" data-support-filter="resolved">
                                Resueltos
                            </button>
                            <button className="filter-chip" type="button" data-support-filter="closed">
                                Cerrados
                            </button>
                        </div>
                        <div id="supportTicketList" className="communications-list">
                            <div className="manager-loading">Cargando tickets…</div>
                        </div>
                    </div>
                </div>
                <div id="supportThreadCard" className="white-card communications-list-card is-hidden">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">CONVERSACIÓN</span>
                            <h2 id="supportThreadTitle">Ticket</h2>
                            <p id="supportThreadMeta"></p>
                        </div>
                        <div className="heading-actions">
                            <label id="supportAssignWrap" className="support-assign-wrap is-hidden">
                                Asignar a
                                <select id="supportAssignUser"></select>
                            </label>
                            <button type="button" className="secondary-button" id="supportStartButton">
                                En proceso
                            </button>
                            <button type="button" className="secondary-button" id="supportResolveButton">
                                Resolver
                            </button>
                            <button type="button" className="secondary-button" id="supportCloseButton">
                                Cerrar
                            </button>
                        </div>
                    </div>
                    <div id="supportThreadMessages" className="support-thread"></div>
                    <form id="supportReplyForm" className="communications-form">
                        <input type="hidden" id="supportThreadId" />
                        <div className="form-field communications-wide">
                            <label htmlFor="supportReplyMessage">Tu respuesta</label>
                            <textarea id="supportReplyMessage" rows="3" maxLength="5000" required></textarea>
                        </div>
                        <label id="supportInternalNoteWrap" className="communications-check is-hidden">
                            <input type="checkbox" id="supportInternalNote" /> Nota interna (solo personal autorizado)
                        </label>
                        <button type="submit" className="cyan-button">
                            Enviar respuesta
                        </button>
                    </form>
                </div>
            </section>

            {/* PASO 15: INTEGRACIONES Y TOKENS API */}
            <section id="view-integrations" className="app-view" aria-labelledby="integrationsTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">CONEXIONES EXTERNAS</span>
                        <h1 id="integrationsTitle">Integraciones y API</h1>
                        <p>Gestiona referencias de configuración sin almacenar claves secretas en MySQL.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button" data-go="profile">
                            ← Perfil
                        </button>
                        <button type="button" className="cyan-button heading-button" id="refreshIntegrationsButton">
                            ↻ Actualizar
                        </button>
                    </div>
                </div>
                <div className="integration-notice">
                    <strong>Seguridad de credenciales</strong>
                    <p>
                        Los secretos deben configurarse en variables de entorno del servidor o en un gestor de secretos. Esta pantalla guarda solo el nombre de la variable y metadatos, nunca la clave real.
                    </p>
                </div>
                <div id="integrationList" className="integration-grid">
                    <div className="manager-loading">Cargando integraciones…</div>
                </div>
                <div className="white-card communications-list-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">API REST</span>
                            <h2>Tokens de acceso</h2>
                            <p>Genera credenciales con permisos de solo lectura. El valor íntegro se muestra una sola vez.</p>
                        </div>
                    </div>
                    <form id="apiTokenForm" className="communications-form api-token-form">
                        <div className="form-field">
                            <label htmlFor="apiTokenName">Nombre del token</label>
                            <input id="apiTokenName" maxLength="120" required placeholder="Ej. Integración de reportes" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="apiTokenExpiry">Caducidad</label>
                            <select id="apiTokenExpiry" defaultValue="90">
                                <option value="30">30 días</option>
                                <option value="90">90 días</option>
                                <option value="180">180 días</option>
                                <option value="365">365 días</option>
                            </select>
                        </div>
                        <div className="form-field communications-wide">
                            <span className="form-field-label">Alcances</span>
                            <label className="communications-check">
                                <input type="checkbox" name="apiScope" value="products:read" defaultChecked /> Leer productos
                            </label>
                            <label className="communications-check">
                                <input type="checkbox" name="apiScope" value="orders:read" /> Leer pedidos (sin datos personales)
                            </label>
                        </div>
                        <button type="submit" className="cyan-button">
                            ＋ Crear token
                        </button>
                    </form>
                    <div id="apiTokenList" className="communications-list">
                        <div className="manager-loading">Cargando tokens…</div>
                    </div>
                </div>
                <div className="white-card communications-list-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">REGISTRO</span>
                            <h2>Comprobaciones recientes</h2>
                            <p>Comprueba la configuración local. No se realizan cobros ni mensajes reales desde esta prueba.</p>
                        </div>
                    </div>
                    <div id="integrationEventsList" className="communications-list"></div>
                </div>
                <div id="newApiTokenReveal" className="white-card token-reveal is-hidden">
                    <h2>Token generado</h2>
                    <p>Copia esta credencial ahora y guárdala en un gestor de secretos. No volverá a mostrarse.</p>
                    <textarea id="newApiTokenValue" rows="2" readOnly></textarea>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button" id="copyApiTokenButton">
                            Copiar token
                        </button>
                        <button type="button" className="cyan-button" id="hideApiTokenButton">
                            Ya lo guardé
                        </button>
                    </div>
                    <p className="operations-footnote">
                        API de solo lectura: <code>api/external.php?resource=products</code> o <code>api/external.php?resource=orders</code>.
                    </p>
                </div>
            </section>

            {/* PERFIL */}
            <section id="view-profile" className="app-view" aria-labelledby="profileTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">TU ESPACIO</span>
                        <h1 id="profileTitle">Mi perfil</h1>
                        <p>Tu cuenta y preferencias.</p>
                    </div>
                </div>
                <article className="white-card profile-card">
                    <div className="profile-cover">
                        <span className="cover-bubble cover-bubble-one"></span>
                        <span className="cover-bubble cover-bubble-two"></span>
                    </div>
                    <div className="profile-main">
                        <div id="profileInitials" className="profile-avatar">JD</div>
                        <div className="profile-details">
                            <h2 id="profileName">Juan Desego</h2>
                            <p id="profileEmail">admin@desego.com</p>
                            <span className="role-label">
                                <i></i>
                                <span id="profileRole">Colaborador</span>
                            </span>
                        </div>
                        <button type="button" id="editProfileButton" className="edit-profile-button">
                            Editar
                        </button>
                    </div>
                </article>
                <div className="profile-option-list">
                    <button type="button" className="white-card profile-option" data-profile-option="account">
                        <span className="option-icon option-blue">♙</span>
                        <span className="option-copy">
                            <strong>Mi cuenta</strong>
                            <small>Información personal</small>
                        </span>
                        <span className="option-chevron">›</span>
                    </button>
                    <button type="button" className="white-card profile-option" data-profile-option="security">
                        <span className="option-icon option-green">▣</span>
                        <span className="option-copy">
                            <strong>Seguridad</strong>
                            <small>Claves y accesos</small>
                        </span>
                        <span className="option-chevron">›</span>
                    </button>
                    <button type="button" className="white-card profile-option" data-profile-option="settings">
                        <span className="option-icon option-purple">⚙</span>
                        <span className="option-copy">
                            <strong>Ajustes</strong>
                            <small>Notificaciones y moneda</small>
                        </span>
                        <span className="option-chevron">›</span>
                    </button>
                    <button type="button" id="analyticsProfileButton" data-admin-view="analytics" className="white-card profile-option is-hidden">
                        <span className="option-icon option-blue">⌁</span>
                        <span className="option-copy">
                            <strong>Analítica avanzada</strong>
                            <small>Tendencias y rentabilidad estimada</small>
                        </span>
                        <span className="option-chevron">›</span>
                    </button>
                    <button type="button" id="auditProfileButton" data-admin-view="audit" className="white-card profile-option is-hidden">
                        <span className="option-icon option-green">✓</span>
                        <span className="option-copy">
                            <strong>Auditoría</strong>
                            <small>Registro de actividad y alertas</small>
                        </span>
                        <span className="option-chevron">›</span>
                    </button>
                    <button type="button" id="backupsProfileButton" data-admin-view="backups" className="white-card profile-option is-hidden">
                        <span className="option-icon option-purple">▣</span>
                        <span className="option-copy">
                            <strong>Copias de seguridad</strong>
                            <small>Crear, verificar y administrar backups</small>
                        </span>
                        <span className="option-chevron">›</span>
                    </button>
                    <button type="button" id="branchesProfileButton" data-admin-view="branches" className="white-card profile-option is-hidden">
                        <span className="option-icon option-blue">⌂</span>
                        <span className="option-copy">
                            <strong>Sucursales</strong>
                            <small>Sedes, contactos y horarios de atención</small>
                        </span>
                        <span className="option-chevron">›</span>
                    </button>
                    <button type="button" id="employeesProfileButton" data-admin-view="employees" className="white-card profile-option is-hidden">
                        <span className="option-icon option-green">♙</span>
                        <span className="option-copy">
                            <strong>Personal</strong>
                            <small>Empleados, puestos y cuentas vinculadas</small>
                        </span>
                        <span className="option-chevron">›</span>
                    </button>
                    <button type="button" id="shiftsProfileButton" data-admin-view="shifts" className="white-card profile-option is-hidden">
                        <span className="option-icon option-purple">▦</span>
                        <span className="option-copy">
                            <strong>Turnos y asistencia</strong>
                            <small>Programación, entradas y salidas</small>
                        </span>
                        <span className="option-chevron">›</span>
                    </button>
                    <button type="button" id="usersManagementButton" className="white-card profile-option is-hidden">
                        <span className="option-icon option-blue">♙</span>
                        <span className="option-copy">
                            <strong>Usuarios y permisos</strong>
                            <small>Administrar cuentas, roles y accesos</small>
                        </span>
                        <span className="option-chevron">›</span>
                    </button>
                    <button type="button" id="notificationsProfileButton" data-admin-view="notifications" className="white-card profile-option">
                        <span className="option-icon option-blue">♢</span>
                        <span className="option-copy">
                            <strong>Notificaciones</strong>
                            <small>Avisos y comunicados internos</small>
                        </span>
                        <span className="option-chevron">›</span>
                    </button>
                    <button type="button" id="supportProfileButton" data-admin-view="support" className="white-card profile-option">
                        <span className="option-icon option-green">?</span>
                        <span className="option-copy">
                            <strong>Soporte</strong>
                            <small>Tickets, incidencias y seguimiento</small>
                        </span>
                        <span className="option-chevron">›</span>
                    </button>
                    <button type="button" id="integrationsProfileButton" data-admin-view="integrations" className="white-card profile-option is-hidden">
                        <span className="option-icon option-purple">↗</span>
                        <span className="option-copy">
                            <strong>Integraciones y API</strong>
                            <small>Conexiones externas y tokens</small>
                        </span>
                        <span className="option-chevron">›</span>
                    </button>
                    <button type="button" id="logoutButton" className="white-card profile-option logout-option">
                        <span className="option-icon option-red">↪</span>
                        <span className="option-copy">
                            <strong>Cerrar sesión</strong>
                            <small>Salir de la aplicación</small>
                        </span>
                        <span className="option-chevron">›</span>
                    </button>
                </div>
            </section>
        </>
    );
}

