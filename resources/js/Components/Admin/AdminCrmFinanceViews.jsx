import React from 'react';

export default function AdminCrmFinanceViews() {
    return (
        <>
            {/* CLIENTES Y DIRECCIONES */}
            <section id="view-customers" className="app-view" aria-labelledby="customersTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">CRM · DATOS DE CLIENTES</span>
                        <h1 id="customersTitle">Clientes y direcciones</h1>
                        <p>Información de contacto y ubicaciones guardadas para pedidos y reservas.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button" data-go="orders">
                            ← Volver
                        </button>
                        <button type="button" className="secondary-button heading-button is-hidden" id="loyaltyManagementButton">
                            ✦ Fidelización
                        </button>
                        <button type="button" className="secondary-button heading-button" id="refreshCustomersButton">
                            ↻ Actualizar
                        </button>
                        <button type="button" className="cyan-button heading-button is-hidden" id="newCustomerButton">
                            ＋ Nuevo cliente
                        </button>
                    </div>
                </div>
                <label className="menu-search customer-search">
                    <span aria-hidden="true">⌕</span>
                    <input
                        id="customerSearch"
                        type="search"
                        placeholder="Buscar por nombre, documento, teléfono o correo…"
                        aria-label="Buscar clientes"
                    />
                    <kbd>⌕</kbd>
                </label>
                <div id="customerSummary" className="operations-summary customer-summary">
                    <article className="operations-metric">
                        <span className="operations-metric-icon metric-blue">♙</span>
                        <div>
                            <small>Clientes cargados</small>
                            <strong>0</strong>
                        </div>
                    </article>
                    <article className="operations-metric">
                        <span className="operations-metric-icon metric-green">⌂</span>
                        <div>
                            <small>Direcciones guardadas</small>
                            <strong>0</strong>
                        </div>
                    </article>
                    <article className="operations-metric">
                        <span className="operations-metric-icon metric-amber">▤</span>
                        <div>
                            <small>Clientes activos</small>
                            <strong>0</strong>
                        </div>
                    </article>
                </div>
                <div id="customersList" className="customers-grid">
                    <div className="manager-loading">Cargando clientes…</div>
                </div>
                <p className="operations-footnote">
                    Los datos se guardan en MySQL. Las direcciones usadas en Delivery se copian al pedido como una
                    instantánea para conservar el destino original, aunque la ficha cambie posteriormente.
                </p>
            </section>

            {/* FIDELIZACIÓN */}
            <section id="view-loyalty" className="app-view" aria-labelledby="loyaltyTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">CRM · PROGRAMA DE PUNTOS</span>
                        <h1 id="loyaltyTitle">Fidelización DESEGO</h1>
                        <p>Configura puntos, administra recompensas y consulta el historial de cada cliente.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button" data-go="customers">
                            ← Clientes
                        </button>
                        <button type="button" className="secondary-button heading-button" id="refreshLoyaltyButton">
                            ↻ Actualizar
                        </button>
                        <button type="button" className="cyan-button heading-button" id="newLoyaltyRewardButton">
                            ＋ Recompensa
                        </button>
                    </div>
                </div>
                <div id="loyaltySummary" className="operations-summary loyalty-summary">
                    <article className="operations-metric">
                        <span className="operations-metric-icon metric-blue">✦</span>
                        <div>
                            <small>Clientes inscritos</small>
                            <strong>0</strong>
                        </div>
                    </article>
                    <article className="operations-metric">
                        <span className="operations-metric-icon metric-green">★</span>
                        <div>
                            <small>Puntos en circulación</small>
                            <strong>0</strong>
                        </div>
                    </article>
                    <article className="operations-metric">
                        <span className="operations-metric-icon metric-purple">↗</span>
                        <div>
                            <small>Puntos acumulados</small>
                            <strong>0</strong>
                        </div>
                    </article>
                    <article className="operations-metric">
                        <span className="operations-metric-icon metric-amber">♢</span>
                        <div>
                            <small>Canjes emitidos</small>
                            <strong>0</strong>
                        </div>
                    </article>
                </div>
                <div className="white-card loyalty-settings-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">REGLAS DEL PROGRAMA</span>
                            <h2>Configuración de puntos</h2>
                            <p>
                                Los puntos se acreditan cuando el pedido queda pagado y se revierten ante un reembolso.
                            </p>
                        </div>
                    </div>
                    <form id="loyaltySettingsForm" className="loyalty-settings-form">
                        <div className="form-field">
                            <label htmlFor="loyaltySolesPerPoint">Importe elegible por punto (S/) *</label>
                            <input
                                id="loyaltySolesPerPoint"
                                type="number"
                                min="1"
                                max="10000"
                                step="0.50"
                                defaultValue="10"
                                required
                            />
                            <small>Ejemplo: S/ 10 de productos generan 1 punto.</small>
                        </div>
                        <div className="form-field">
                            <label htmlFor="loyaltyMinAmount">Compra mínima elegible (S/)</label>
                            <input
                                id="loyaltyMinAmount"
                                type="number"
                                min="0"
                                max="1000000"
                                step="0.50"
                                defaultValue="0"
                                required
                            />
                        </div>
                        <div className="form-field">
                            <label htmlFor="loyaltyExpiryDays">Vigencia de puntos (días)</label>
                            <input
                                id="loyaltyExpiryDays"
                                type="number"
                                min="0"
                                max="0"
                                step="1"
                                defaultValue="0"
                                readOnly
                                aria-readonly="true"
                            />
                            <small>
                                La caducidad automática aún no está habilitada; los puntos no vencen en esta versión.
                            </small>
                        </div>
                        <button type="submit" className="cyan-button">
                            Guardar reglas
                        </button>
                    </form>
                </div>
                <div className="white-card loyalty-rewards-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">CATÁLOGO DE RECOMPENSAS</span>
                            <h2>Recompensas</h2>
                            <p>
                                Los vales se emiten una sola vez y pueden marcarse usados o cancelarse desde el
                                historial.
                            </p>
                        </div>
                    </div>
                    <div id="loyaltyRewardsGrid" className="loyalty-rewards-grid">
                        <div className="manager-loading">Cargando recompensas…</div>
                    </div>
                </div>
                <div className="white-card loyalty-accounts-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">CARTERA DE CLIENTES</span>
                            <h2>Saldos y puntos</h2>
                            <p>Busca una ficha para ajustar puntos o emitir una recompensa.</p>
                        </div>
                    </div>
                    <label className="menu-search">
                        <span aria-hidden="true">⌕</span>
                        <input
                            id="loyaltySearch"
                            type="search"
                            placeholder="Buscar por nombre, código, correo o teléfono…"
                            aria-label="Buscar cuentas de fidelización"
                        />
                        <kbd>⌕</kbd>
                    </label>
                    <div id="loyaltyAccountsList" className="loyalty-accounts-list">
                        <div className="manager-loading">Cargando saldos…</div>
                    </div>
                </div>
                <div className="white-card loyalty-redemptions-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">VALES</span>
                            <h2>Canjes y recompensas emitidas</h2>
                        </div>
                        <span id="loyaltyRedemptionCount" className="counter-chip">
                            Cargando…
                        </span>
                    </div>
                    <div id="loyaltyRedemptionsList" className="loyalty-transactions-list">
                        <div className="manager-loading">Cargando canjes…</div>
                    </div>
                </div>
                <div className="white-card loyalty-transactions-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">AUDITORÍA</span>
                            <h2>Últimos movimientos de puntos</h2>
                        </div>
                        <span id="loyaltyTransactionCount" className="counter-chip">
                            Cargando…
                        </span>
                    </div>
                    <div id="loyaltyTransactionsList" className="loyalty-transactions-list">
                        <div className="manager-loading">Cargando movimientos…</div>
                    </div>
                </div>
                <p className="operations-footnote">
                    Los puntos se calculan sobre subtotal menos descuentos; el delivery no acumula puntos.
                </p>
            </section>

            {/* RESEÑAS Y CALIFICACIONES */}
            <section id="view-reviews" className="app-view" aria-labelledby="reviewsTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">EXPERIENCIA DEL CLIENTE</span>
                        <h1 id="reviewsTitle">Reseñas y calificaciones</h1>
                        <p>Modera comentarios de compras verificadas y controla lo que aparece en la tienda.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button" data-go="menu">
                            ← Menú
                        </button>
                        <button type="button" className="cyan-button heading-button" id="refreshReviewsButton">
                            ↻ Actualizar
                        </button>
                    </div>
                </div>
                <div id="reviewsSummary" className="operations-summary reviews-summary">
                    <article className="operations-metric">
                        <span className="operations-metric-icon metric-amber">★</span>
                        <div>
                            <small>Pendientes</small>
                            <strong>—</strong>
                        </div>
                    </article>
                    <article className="operations-metric">
                        <span className="operations-metric-icon metric-green">✓</span>
                        <div>
                            <small>Aprobadas</small>
                            <strong>—</strong>
                        </div>
                    </article>
                    <article className="operations-metric">
                        <span className="operations-metric-icon metric-red">×</span>
                        <div>
                            <small>Rechazadas</small>
                            <strong>—</strong>
                        </div>
                    </article>
                </div>
                <div className="white-card review-admin-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">MODERACIÓN</span>
                            <h2>Opiniones de clientes</h2>
                            <p>Solo se aceptan reseñas asociadas a un pedido entregado, pagado y al producto comprado.</p>
                        </div>
                        <span id="adminReviewsCount" className="counter-chip">
                            Cargando…
                        </span>
                    </div>
                    <div className="filter-row review-admin-filters" id="reviewAdminFilters" aria-label="Filtrar reseñas">
                        <button type="button" className="filter-chip is-selected" data-review-status="pending">
                            Pendientes
                        </button>
                        <button type="button" className="filter-chip" data-review-status="approved">
                            Aprobadas
                        </button>
                        <button type="button" className="filter-chip" data-review-status="rejected">
                            Rechazadas
                        </button>
                        <button type="button" className="filter-chip" data-review-status="all">
                            Todas
                        </button>
                    </div>
                    <div id="adminReviewsList" className="admin-reviews-list">
                        <div className="manager-loading">Cargando reseñas…</div>
                    </div>
                </div>
                <p className="operations-footnote">
                    Las opiniones aprobadas se publican en la tienda online. Rechazar una reseña la oculta al público,
                    pero conserva el registro para auditoría.
                </p>
            </section>

            {/* FACTURACIÓN Y COMPROBANTES INTERNOS */}
            <section id="view-billing" className="app-view" aria-labelledby="billingTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">VENTAS · DOCUMENTOS</span>
                        <h1 id="billingTitle">Facturación y comprobantes</h1>
                        <p>Emite y consulta comprobantes internos de pedidos pagados.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button" data-go="orders">
                            ← Pedidos
                        </button>
                        <button type="button" className="cyan-button heading-button" id="refreshBillingButton">
                            ↻ Actualizar
                        </button>
                    </div>
                </div>
                <div className="billing-disclaimer white-card">
                    <span className="billing-disclaimer-icon">i</span>
                    <div>
                        <strong>Documento de control interno</strong>
                        <p>
                            Estos comprobantes y sus cálculos referenciales de IGV no son comprobantes electrónicos
                            autorizados por SUNAT. Para facturación electrónica legal se requiere integrar un proveedor
                            autorizado y completar la configuración tributaria.
                        </p>
                    </div>
                </div>
                <div id="issueInvoiceCard" className="white-card billing-form-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">NUEVO COMPROBANTE</span>
                            <h2>Emitir para un pedido pagado</h2>
                            <p>Cada pedido puede tener un solo comprobante interno.</p>
                        </div>
                    </div>
                    <form id="invoiceIssueForm" className="billing-form">
                        <div className="form-field billing-field-full">
                            <label htmlFor="invoiceOrderSelect">Pedido pagado *</label>
                            <select id="invoiceOrderSelect" required defaultValue="">
                                <option value="">Cargando pedidos pagados…</option>
                            </select>
                        </div>
                        <div className="form-field">
                            <label htmlFor="invoiceDocumentType">Tipo de documento *</label>
                            <select id="invoiceDocumentType" required defaultValue="boleta">
                                <option value="boleta">Boleta interna</option>
                                <option value="factura">Factura interna</option>
                            </select>
                        </div>
                        <div className="form-field">
                            <label htmlFor="invoiceBuyerName">Nombre o razón social *</label>
                            <input
                                id="invoiceBuyerName"
                                maxLength={160}
                                required
                                placeholder="Nombre del comprador"
                            />
                        </div>
                        <div className="form-field">
                            <label htmlFor="invoiceBuyerDocType">Tipo de documento</label>
                            <select id="invoiceBuyerDocType" defaultValue="Sin documento">
                                <option value="Sin documento">Sin documento</option>
                                <option value="DNI">DNI</option>
                                <option value="RUC">RUC</option>
                                <option value="CE">Carné de extranjería</option>
                                <option value="Pasaporte">Pasaporte</option>
                            </select>
                        </div>
                        <div className="form-field">
                            <label htmlFor="invoiceBuyerDoc">Número de documento</label>
                            <input id="invoiceBuyerDoc" maxLength={20} placeholder="DNI o RUC" />
                        </div>
                        <div className="form-field billing-field-full">
                            <label htmlFor="invoiceBuyerAddress">Dirección del comprador</label>
                            <input id="invoiceBuyerAddress" maxLength={255} placeholder="Dirección (opcional)" />
                        </div>
                        <div className="form-field billing-field-full">
                            <label htmlFor="invoiceNotes">Notas internas</label>
                            <input id="invoiceNotes" maxLength={500} placeholder="Observaciones (opcional)" />
                        </div>
                        <div className="billing-form-footer">
                            <small>
                                La información se guarda en MySQL y se conserva una copia de los productos del pedido.
                            </small>
                            <button type="submit" className="cyan-button" id="issueInvoiceSubmit">
                                Emitir comprobante interno
                            </button>
                        </div>
                        <p id="invoiceFormFeedback" className="billing-feedback" aria-live="polite" />
                    </form>
                </div>
                <div className="white-card billing-list-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">HISTORIAL</span>
                            <h2>Comprobantes emitidos</h2>
                            <p>Consulta, imprime o anula el registro interno.</p>
                        </div>
                        <span id="invoiceCount" className="counter-chip">
                            Cargando…
                        </span>
                    </div>
                    <div id="invoicesList" className="billing-invoices-list">
                        <div className="manager-loading">Cargando comprobantes…</div>
                    </div>
                </div>
            </section>

            {/* REPORTES DEL NEGOCIO */}
            <section id="view-reports" className="app-view" aria-labelledby="reportsTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">ANÁLISIS · OPERACIONES</span>
                        <h1 id="reportsTitle">Reportes del restaurante</h1>
                        <p>Resumen de cobros, pedidos, métodos de pago y productos.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button" data-go="orders">
                            ← Pedidos
                        </button>
                        <button type="button" className="secondary-button heading-button is-hidden" id="analyticsManagementButton">
                            Analítica
                        </button>
                        <button type="button" className="secondary-button heading-button is-hidden" id="auditManagementButton">
                            Auditoría
                        </button>
                        <button type="button" className="secondary-button heading-button is-hidden" id="backupsManagementButton">
                            Backups
                        </button>
                        <button type="button" className="cyan-button heading-button" id="exportReportsButton">
                            ↓ Exportar CSV
                        </button>
                    </div>
                </div>
                <div className="white-card report-filter-card">
                    <form id="reportFilterForm" className="report-filter-form">
                        <div className="form-field">
                            <label htmlFor="reportFrom">Desde</label>
                            <input type="date" id="reportFrom" required />
                        </div>
                        <div className="form-field">
                            <label htmlFor="reportTo">Hasta</label>
                            <input type="date" id="reportTo" required />
                        </div>
                        <button type="submit" className="cyan-button">
                            Aplicar período
                        </button>
                    </form>
                    <p id="reportPeriodLabel" className="report-period-label">
                        Selecciona un período para consultar datos reales de MySQL.
                    </p>
                </div>
                <div id="reportSummary" className="report-summary-grid">
                    <article className="white-card report-kpi">
                        <small>Cobros netos</small>
                        <strong>—</strong>
                        <span>Pagos menos reembolsos</span>
                    </article>
                    <article className="white-card report-kpi">
                        <small>Pedidos pagados</small>
                        <strong>—</strong>
                        <span>Según fecha de cobro</span>
                    </article>
                    <article className="white-card report-kpi">
                        <small>Ticket promedio</small>
                        <strong>—</strong>
                        <span>Pedido pagado</span>
                    </article>
                    <article className="white-card report-kpi">
                        <small>Comprobantes emitidos</small>
                        <strong>—</strong>
                        <span>Registro interno</span>
                    </article>
                </div>
                <div className="report-layout-grid">
                    <article className="white-card report-panel report-daily-panel">
                        <div className="card-heading">
                            <div>
                                <span className="section-eyebrow">TENDENCIA</span>
                                <h2>Cobros netos por día</h2>
                            </div>
                        </div>
                        <div id="reportDailyChart" className="report-daily-chart">
                            <p className="catalog-note">Carga un período para ver el resumen.</p>
                        </div>
                    </article>
                    <article className="white-card report-panel">
                        <div className="card-heading">
                            <div>
                                <span className="section-eyebrow">COBROS</span>
                                <h2>Por método de pago</h2>
                            </div>
                        </div>
                        <div id="reportPaymentMethods" className="report-data-list">
                            <p className="catalog-note">Sin datos para mostrar.</p>
                        </div>
                    </article>
                    <article className="white-card report-panel">
                        <div className="card-heading">
                            <div>
                                <span className="section-eyebrow">PRODUCTOS</span>
                                <h2>Más vendidos</h2>
                            </div>
                        </div>
                        <div id="reportTopProducts" className="report-data-list">
                            <p className="catalog-note">Sin datos para mostrar.</p>
                        </div>
                    </article>
                    <article className="white-card report-panel">
                        <div className="card-heading">
                            <div>
                                <span className="section-eyebrow">OPERACIÓN</span>
                                <h2>Pedidos por estado</h2>
                            </div>
                        </div>
                        <div id="reportOrderStatuses" className="report-data-list">
                            <p className="catalog-note">Sin datos para mostrar.</p>
                        </div>
                    </article>
                </div>
                <p className="operations-footnote">
                    Reportes calculados desde pagos, pedidos e ítems registrados en MySQL.
                </p>
            </section>

            {/* ANALÍTICA AVANZADA */}
            <section id="view-analytics" className="app-view" aria-labelledby="analyticsTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">ANÁLISIS · INTELIGENCIA COMERCIAL</span>
                        <h1 id="analyticsTitle">Analítica avanzada</h1>
                        <p>Tendencias, clientes, horarios pico y rentabilidad estimada desde MySQL.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button" data-go="reports">
                            ← Reportes
                        </button>
                        <button type="button" className="cyan-button heading-button" id="refreshAnalyticsButton">
                            ↻ Actualizar
                        </button>
                    </div>
                </div>
                <div className="white-card report-filter-card">
                    <form id="analyticsFilterForm" className="report-filter-form">
                        <div className="form-field">
                            <label htmlFor="analyticsFrom">Desde</label>
                            <input type="date" id="analyticsFrom" required />
                        </div>
                        <div className="form-field">
                            <label htmlFor="analyticsTo">Hasta</label>
                            <input type="date" id="analyticsTo" required />
                        </div>
                        <button type="submit" className="cyan-button">
                            Analizar período
                        </button>
                    </form>
                    <p id="analyticsPeriodLabel" className="report-period-label">
                        Selecciona un período para cargar analítica.
                    </p>
                </div>
                <div className="white-card analytics-preferences">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">PANEL PERSONALIZABLE</span>
                            <h2>Indicadores visibles</h2>
                            <p>Elige las métricas que deseas destacar.</p>
                        </div>
                        <button type="button" className="secondary-button" id="saveAnalyticsPreferences">
                            Guardar selección
                        </button>
                    </div>
                    <div id="analyticsMetricPrefs" className="analytics-metric-prefs">
                        <label>
                            <input type="checkbox" defaultValue="net_sales" defaultChecked /> Cobros netos
                        </label>
                        <label>
                            <input type="checkbox" defaultValue="paid_orders" defaultChecked /> Pedidos pagados
                        </label>
                        <label>
                            <input type="checkbox" defaultValue="average_ticket" defaultChecked /> Ticket promedio
                        </label>
                        <label>
                            <input type="checkbox" defaultValue="customers" defaultChecked /> Clientes
                        </label>
                        <label>
                            <input type="checkbox" defaultValue="conversion" defaultChecked /> Conversión web
                        </label>
                        <label>
                            <input type="checkbox" defaultValue="estimated_margin" defaultChecked /> Margen estimado
                        </label>
                        <label>
                            <input type="checkbox" defaultValue="cancellations" defaultChecked /> Cancelaciones
                        </label>
                    </div>
                </div>
                <div id="analyticsSummary" className="report-summary-grid analytics-summary-grid">
                    <article className="white-card report-kpi">
                        <small>Cobros netos</small>
                        <strong>—</strong>
                        <span>Pagos menos reembolsos</span>
                    </article>
                </div>
                <div className="report-layout-grid analytics-report-grid">
                    <article className="white-card report-panel report-daily-panel">
                        <div className="card-heading">
                            <div>
                                <span className="section-eyebrow">TENDENCIA</span>
                                <h2>Cobros netos por día</h2>
                            </div>
                            <span id="analyticsComparison" className="counter-chip">
                                —
                            </span>
                        </div>
                        <div id="analyticsDailyChart" className="report-daily-chart">
                            <p className="catalog-note">Carga un período.</p>
                        </div>
                    </article>
                    <article className="white-card report-panel">
                        <div className="card-heading">
                            <div>
                                <span className="section-eyebrow">CLIENTES</span>
                                <h2>Comportamiento de clientes</h2>
                            </div>
                        </div>
                        <div id="analyticsCustomerBehavior" className="report-data-list">
                            <p className="catalog-note">Sin datos.</p>
                        </div>
                        <div className="analytics-forecast" id="analyticsForecast">
                            Pronóstico estimado de 7 días: —
                        </div>
                    </article>
                    <article className="white-card report-panel">
                        <div className="card-heading">
                            <div>
                                <span className="section-eyebrow">RENTABILIDAD</span>
                                <h2>Margen estimado por producto</h2>
                            </div>
                        </div>
                        <div id="analyticsProfitability" className="report-data-list">
                            <p className="catalog-note">Sin datos.</p>
                        </div>
                        <p className="analytics-disclaimer">
                            El costo se calcula con la receta y el costo actual de ingredientes.
                        </p>
                    </article>
                    <article className="white-card report-panel analytics-peak-panel">
                        <div className="card-heading">
                            <div>
                                <span className="section-eyebrow">OPERACIÓN</span>
                                <h2>Horarios pico</h2>
                                <p>Pedidos pagados por día y hora de Perú.</p>
                            </div>
                        </div>
                        <div id="analyticsPeakHours" className="analytics-peak-hours">
                            <p className="catalog-note">Sin datos de horarios.</p>
                        </div>
                    </article>
                </div>
                <p className="operations-footnote">
                    Las predicciones son una referencia de tendencia calculada por promedio.
                </p>
            </section>

            {/* AUDITORÍA Y ALERTAS */}
            <section id="view-audit" className="app-view" aria-labelledby="auditTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">ADMINISTRACIÓN · SEGURIDAD</span>
                        <h1 id="auditTitle">Auditoría y actividad</h1>
                        <p>Consulta quién hizo qué, cuándo y desde qué dirección IP.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button" data-go="reports">
                            ← Reportes
                        </button>
                        <button type="button" className="cyan-button heading-button" id="exportAuditButton">
                            ↓ Exportar CSV
                        </button>
                    </div>
                </div>
                <div id="auditAlerts" className="analytics-alert-list">
                    <div className="manager-loading">Comprobando actividad sospechosa…</div>
                </div>
                <div className="white-card report-filter-card">
                    <form id="auditFilterForm" className="audit-filter-form">
                        <div className="form-field">
                            <label htmlFor="auditFrom">Desde</label>
                            <input type="date" id="auditFrom" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="auditTo">Hasta</label>
                            <input type="date" id="auditTo" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="auditEvent">Evento</label>
                            <input
                                type="text"
                                id="auditEvent"
                                placeholder="auth.login, orders…"
                                maxLength={100}
                            />
                        </div>
                        <div className="form-field">
                            <label htmlFor="auditActor">ID de usuario</label>
                            <input type="number" min="1" id="auditActor" placeholder="Todos" />
                        </div>
                        <div className="form-field audit-search-field">
                            <label htmlFor="auditQuery">Buscar en detalles, usuario o IP</label>
                            <input type="search" id="auditQuery" placeholder="Buscar actividad…" maxLength={100} />
                        </div>
                        <button type="submit" className="cyan-button">
                            Filtrar
                        </button>
                    </form>
                    <p id="auditCountLabel" className="report-period-label">
                        Cargando registros…
                    </p>
                </div>
                <div className="white-card audit-list-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">TRAZABILIDAD</span>
                            <h2>Registro de acciones</h2>
                            <p>
                                Los logs detallados pueden contener información administrativa; exporta solo cuando sea
                                necesario.
                            </p>
                        </div>
                        <button type="button" className="secondary-button" id="refreshAuditButton">
                            ↻ Actualizar
                        </button>
                    </div>
                    <div id="auditLogList" className="audit-log-list">
                        <div className="manager-loading">Cargando auditoría…</div>
                    </div>
                    <div className="audit-pagination">
                        <button type="button" className="secondary-button" id="auditPrevPage" disabled>
                            ← Anterior
                        </button>
                        <span id="auditPageLabel">Página 1</span>
                        <button type="button" className="secondary-button" id="auditNextPage" disabled>
                            Siguiente →
                        </button>
                    </div>
                </div>
                <div className="white-card audit-retention-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">POLÍTICA DE RETENCIÓN</span>
                            <h2>Retención y alertas</h2>
                            <p>
                                La limpieza elimina logs antiguos de forma permanente y marca backups antiguos como
                                vencidos.
                            </p>
                        </div>
                    </div>
                    <form id="retentionSettingsForm" className="retention-settings-form">
                        <div className="form-field">
                            <label htmlFor="auditRetentionDays">Auditoría (días)</label>
                            <input
                                id="auditRetentionDays"
                                type="number"
                                min="30"
                                max="3650"
                                required
                                defaultValue="365"
                            />
                        </div>
                        <div className="form-field">
                            <label htmlFor="backupRetentionDays">Backups (días)</label>
                            <input
                                id="backupRetentionDays"
                                type="number"
                                min="7"
                                max="3650"
                                required
                                defaultValue="90"
                            />
                        </div>
                        <div className="form-field">
                            <label htmlFor="suspiciousThreshold">Intentos fallidos por IP / 15 min</label>
                            <input
                                id="suspiciousThreshold"
                                type="number"
                                min="3"
                                max="100"
                                required
                                defaultValue="5"
                            />
                        </div>
                        <button type="submit" className="cyan-button">
                            Guardar política
                        </button>
                        <button type="button" className="secondary-button" id="applyRetentionButton">
                            Aplicar retención ahora
                        </button>
                    </form>
                </div>
            </section>

            {/* COPIAS CIFRADAS Y RECUPERACIÓN */}
            <section id="view-backups" className="app-view" aria-labelledby="backupsTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">ADMINISTRACIÓN · CONTINUIDAD</span>
                        <h1 id="backupsTitle">Copias de seguridad</h1>
                        <p>Backups cifrados, validación de integridad e historial de recuperación.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button" data-go="audit">
                            Auditoría
                        </button>
                        <button type="button" className="cyan-button heading-button" id="createBackupButton">
                            ＋ Crear copia ahora
                        </button>
                    </div>
                </div>
                <div className="billing-disclaimer white-card">
                    <span className="billing-disclaimer-icon">i</span>
                    <div>
                        <strong>Cifrado y custodia de la clave</strong>
                        <p>
                            Los archivos se cifran con AES-256-GCM. Configura una clave de 64 caracteres hexadecimales
                            en config/backup.php o DESEGO_BACKUP_KEY.
                        </p>
                    </div>
                </div>
                <div className="backup-status-grid" id="backupStatusSummary">
                    <article className="white-card report-kpi">
                        <small>Copias completas</small>
                        <strong>—</strong>
                        <span>Historial disponible</span>
                    </article>
                    <article className="white-card report-kpi">
                        <small>Última copia</small>
                        <strong>—</strong>
                        <span>Fecha UTC</span>
                    </article>
                    <article className="white-card report-kpi">
                        <small>Almacenamiento secundario</small>
                        <strong>—</strong>
                        <span>Configurable</span>
                    </article>
                </div>
                <div className="white-card billing-list-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">HISTORIAL</span>
                            <h2>Backups guardados</h2>
                            <p>Verifica el hash y el descifrado antes de conservar una copia como válida.</p>
                        </div>
                        <button type="button" className="secondary-button" id="refreshBackupsButton">
                            ↻ Actualizar
                        </button>
                    </div>
                    <div id="backupList" className="backup-list">
                        <div className="manager-loading">Cargando copias…</div>
                    </div>
                </div>
                <div className="white-card backup-recovery-note">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">RECUPERACIÓN</span>
                            <h2>Restaurar una copia</h2>
                        </div>
                    </div>
                    <p>
                        Por seguridad, la restauración es una operación de consola y requiere confirmación explícita.
                        Primero crea una copia previa; luego usa{' '}
                        <code>scripts/restore_backup.php ID --confirm=RESTORE-DESEGO</code> en el servidor.
                    </p>
                </div>
            </section>
        </>
    );
}

