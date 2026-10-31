import React from 'react';

export default function AdminOperationsViews() {
    return (
        <>
            {/* INICIO · SMART DASHBOARD */}
            <section id="view-home" className="app-view is-active smart-home-view" aria-labelledby="homeTitle">
                {/* Elementos de compatibilidad ocultos/sincronizados con script.js */}
                <div className="hidden" aria-hidden="true">
                    <h1 id="homeTitle">Panel General</h1>
                    <strong id="welcomeName">Jonathan</strong>
                </div>

                {/* FILA SUPERIOR: 3 WIDGETS PRINCIPALES (SOLAR POWER / POWER USAGE / BEDROOM) */}
                <div className="smart-top-widgets-grid">
                    {/* Widget 1: Tarjeta Celeste con Curva Suave (Solar Power -> Ventas & Flujo) */}
                    <article className="smart-solar-card">
                        <div className="smart-solar-head">
                            <div>
                                <h2>Ventas del Día</h2>
                                <div className="smart-solar-metrics">
                                    <strong id="salesTodayValue">S/ 0.00</strong>
                                    <span id="salesTodayBadge" className="smart-solar-badge">
                                        MySQL
                                    </span>
                                </div>
                            </div>
                            <span className="smart-sun-icon" aria-hidden="true">
                                <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" strokeWidth="1.6">
                                    <circle cx="16" cy="16" r="6" />
                                    <path
                                        d="M16 3v3.5M16 25.5V29M3 16h3.5M25.5 16H29M6.8 6.8l2.5 2.5M22.7 22.7l2.5 2.5M25.2 6.8l-2.5 2.5M9.3 22.7l-2.5 2.5"
                                        strokeLinecap="round"
                                    />
                                </svg>
                            </span>
                        </div>

                        <div className="smart-wave-chart" aria-hidden="true">
                            <svg viewBox="0 0 280 95" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="smartWaveFill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="#FFFFFF" stopOpacity="0.65" />
                                        <stop offset="100%" stopColor="#FFFFFF" stopOpacity="0.0" />
                                    </linearGradient>
                                </defs>
                                <path
                                    d="M 8 62 C 32 32, 62 32, 86 56 C 110 80, 136 18, 164 22 C 190 26, 212 82, 238 66 C 254 54, 264 38, 274 36 L 274 92 L 8 92 Z"
                                    fill="url(#smartWaveFill)"
                                />
                                <path
                                    d="M 8 62 C 32 32, 62 32, 86 56 C 110 80, 136 18, 164 22 C 190 26, 212 82, 238 66 C 254 54, 264 38, 274 36"
                                    fill="none"
                                    stroke="#FFFFFF"
                                    strokeWidth="3"
                                    strokeLinecap="round"
                                />
                                <circle cx="192" cy="48" r="6.5" fill="#FFFFFF" />
                            </svg>
                        </div>

                        <div className="smart-weekdays">
                            <span>lun</span>
                            <span>mar</span>
                            <span>mié</span>
                            <span>jue</span>
                            <span>vie</span>
                            <span>sáb</span>
                            <span>dom</span>
                        </div>
                    </article>

                    {/* Widget 2: Gráfico de Barras Verticales (Flujo Operativo) */}
                    <article className="smart-power-card">
                        <div className="smart-power-head">
                            <div>
                                <h2>Flujo de Pedidos</h2>
                                <p className="smart-power-sub">
                                    Hoy: <strong id="ordersTodayValue">0</strong> (
                                    <span id="pendingOrdersBadge">0 pendientes</span>) · Ticket:{' '}
                                    <strong id="averageTicketValue">S/ 0.00</strong>
                                </p>
                            </div>
                            <span className="smart-bolt-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7">
                                    <path
                                        d="M13 2 4.5 13.5h6.5L9.5 22 19.5 10.5h-6.5L13 2Z"
                                        strokeLinejoin="round"
                                    />
                                </svg>
                            </span>
                        </div>

                        <div className="smart-bars-container">
                            {[
                                { hour: '05', height: '34%' },
                                { hour: '08', height: '58%' },
                                { hour: '10', height: '78%' },
                                { hour: '12', height: '94%' },
                                { hour: '03', height: '68%' },
                                { hour: '06', height: '54%' },
                                { hour: '09', height: '48%' },
                            ].map((bar) => (
                                <div key={bar.hour} className="smart-bar-col">
                                    <div className="smart-bar-track">
                                        <div
                                            className="smart-bar-fill"
                                            style={{ height: bar.height }}
                                        />
                                    </div>
                                    <span className="smart-bar-label">{bar.hour}</span>
                                </div>
                            ))}
                        </div>

                        <div className="smart-ampm-bar">
                            <div className="smart-ampm-line">
                                <span className="is-am" />
                                <span className="is-pm" />
                            </div>
                            <div className="smart-ampm-labels">
                                <strong>MAÑANA</strong>
                                <strong className="is-blue">TARDE</strong>
                            </div>
                        </div>
                    </article>

                    {/* Widget 3: Ilustración Interactiva de Salón (Bedroom -> Salón Principal) */}
                    <article className="smart-room-card">
                        <div className="smart-room-banner">
                            <h2>Salón Principal</h2>
                        </div>

                        <div className="smart-room-illustration">
                            <svg viewBox="0 0 280 145" fill="none" aria-hidden="true">
                                {/* Unidad superior de climatización / cocina (01) */}
                                <rect
                                    x="98"
                                    y="10"
                                    width="72"
                                    height="24"
                                    rx="4"
                                    stroke="#1E293B"
                                    strokeWidth="1.5"
                                    fill="#FFFFFF"
                                />
                                <path d="M112 10l6 10h28l6-10M110 28h48" stroke="#1E293B" strokeWidth="1.3" />
                                <circle cx="192" cy="22" r="9" fill="#5DA3EE" />
                                <text
                                    x="192"
                                    y="25.5"
                                    textAnchor="middle"
                                    fill="#FFFFFF"
                                    fontSize="8.5"
                                    fontWeight="700"
                                >
                                    01
                                </text>

                                {/* Lámpara / Estación Izquierda (02) */}
                                <circle cx="58" cy="56" r="9" fill="#5DA3EE" />
                                <text
                                    x="58"
                                    y="59.5"
                                    textAnchor="middle"
                                    fill="#FFFFFF"
                                    fontSize="8.5"
                                    fontWeight="700"
                                >
                                    02
                                </text>
                                <path d="M24 64h20l4 13H20l4-13Z" stroke="#1E293B" strokeWidth="1.4" fill="#FFFFFF" />
                                <path d="M34 77v10M27 87h14" stroke="#1E293B" strokeWidth="1.4" />
                                <rect x="14" y="88" width="40" height="6" rx="1.5" fill="#4D7FE3" />
                                <rect x="18" y="94" width="32" height="34" rx="2" fill="#6BB3F8" />

                                {/* Lámpara / Estación Derecha (03) */}
                                <circle cx="258" cy="58" r="9" fill="#5DA3EE" />
                                <text
                                    x="258"
                                    y="61.5"
                                    textAnchor="middle"
                                    fill="#FFFFFF"
                                    fontSize="8.5"
                                    fontWeight="700"
                                >
                                    03
                                </text>
                                <path
                                    d="M224 64h20l4 13h-28l4-13Z"
                                    stroke="#1E293B"
                                    strokeWidth="1.4"
                                    fill="#FFFFFF"
                                />
                                <path d="M234 77v10M227 87h14" stroke="#1E293B" strokeWidth="1.4" />
                                <rect x="214" y="88" width="40" height="6" rx="1.5" fill="#4D7FE3" />
                                <rect x="218" y="94" width="32" height="34" rx="2" fill="#6BB3F8" />

                                {/* Mesa / Booth Central Azul */}
                                <path
                                    d="M84 68c0-7 6-12 13-12h74c7 0 13 5 13 12v22H84V68Z"
                                    fill="#4E83E4"
                                />
                                <rect x="96" y="65" width="34" height="17" rx="8" fill="#E2EEFD" />
                                <rect x="138" y="65" width="34" height="17" rx="8" fill="#E2EEFD" />
                                <rect x="74" y="88" width="120" height="14" rx="6" fill="#6ED0F9" />
                                <path
                                    d="M74 98h120v22a8 8 0 0 1-8 8H82a8 8 0 0 1-8-8V98Z"
                                    fill="#538CE8"
                                />
                            </svg>
                        </div>

                        <div className="smart-room-controls">
                            <strong>Control :</strong>
                            <button
                                type="button"
                                data-go="kitchen"
                                className="smart-control-dot"
                                title="Estación 01: Cocina KDS"
                            >
                                01
                            </button>
                            <button
                                type="button"
                                data-go="reservations"
                                className="smart-control-dot"
                                title="Estación 02: Salón y Mesas"
                            >
                                02
                            </button>
                            <button
                                type="button"
                                data-go="orders"
                                className="smart-control-dot"
                                title="Estación 03: Pedidos POS"
                            >
                                03
                            </button>
                        </div>
                    </article>
                </div>

                {/* FILA INFERIOR: ESTACIONES (3x2) + ÁREAS DEL RESTAURANTE Y CLIMA/FECHA */}
                <div className="smart-bottom-grid">
                    {/* Columna Izquierda: Estaciones Operativas */}
                    <div className="smart-devices-block">
                        <h2 className="smart-section-title">Estaciones</h2>

                        <div className="smart-devices-grid">
                            {/* 1. Air Conditioner -> Terminal POS / Nueva Venta (#quickSale) */}
                            <button
                                type="button"
                                id="quickSale"
                                className="smart-device-card"
                                title="Registrar Nueva Venta"
                            >
                                <strong className="smart-device-name">Terminal POS</strong>
                                <div className="smart-device-icon" aria-hidden="true">
                                    <svg viewBox="0 0 80 42" fill="none" stroke="#1E293B" strokeWidth="1.6">
                                        <rect x="6" y="6" width="68" height="24" rx="4" />
                                        <path d="M20 6l6 10h28l6-10M18 24h44" />
                                    </svg>
                                </div>
                                <div className="smart-device-foot">
                                    <span className="smart-toggle is-on">
                                        <i />
                                    </span>
                                    <small>Salón</small>
                                </div>
                            </button>

                            {/* 2. Smart TV -> Monitor KDS Cocina */}
                            <button
                                type="button"
                                data-go="kitchen"
                                className="smart-device-card"
                                title="Abrir Pantalla de Cocina KDS"
                            >
                                <strong className="smart-device-name">Monitor KDS</strong>
                                <div className="smart-device-icon" aria-hidden="true">
                                    <svg viewBox="0 0 80 48" fill="none" stroke="#1E293B" strokeWidth="1.5">
                                        <rect x="10" y="6" width="60" height="32" rx="2" />
                                        <path d="M12 34h56M34 38l-3 5h18l-3-5M24 43h32" />
                                    </svg>
                                </div>
                                <div className="smart-device-foot">
                                    <span className="smart-toggle">
                                        <i />
                                    </span>
                                    <small>Cocina</small>
                                </div>
                            </button>

                            {/* 3. Refrigerator -> Cámara Frío & Carta */}
                            <button
                                type="button"
                                data-go="menu"
                                className="smart-device-card"
                                title="Abrir Carta, Recetas e Inventario"
                            >
                                <strong className="smart-device-name">Inventario</strong>
                                <div className="smart-device-icon" aria-hidden="true">
                                    <svg viewBox="0 0 60 54" fill="none" stroke="#1E293B" strokeWidth="1.6">
                                        <rect x="16" y="4" width="28" height="44" rx="2" />
                                        <path d="M16 20h28M21 10v6M21 25v8M18 48v2M42 48v2" />
                                    </svg>
                                </div>
                                <div className="smart-device-foot">
                                    <span className="smart-toggle is-on">
                                        <i />
                                    </span>
                                    <small>Almacén</small>
                                </div>
                            </button>

                            {/* 4. Bulb (Destacada en Azul) -> Control y Arqueo de Caja */}
                            <button
                                type="button"
                                onClick={() => document.getElementById('sidebarCashModalBtn')?.click()}
                                className="smart-device-card is-featured-blue"
                                title="Abrir Control y Arqueo de Caja"
                            >
                                <strong className="smart-device-name">Caja Activa</strong>
                                <div className="smart-device-icon" aria-hidden="true">
                                    <svg viewBox="0 0 60 54" fill="none" stroke="#FFFFFF" strokeWidth="1.6">
                                        <path d="M30 8a13 13 0 0 0-8 23.2c1.6 1.3 2.5 3.2 2.5 5.3v1.5h11v-1.5c0-2.1.9-4 2.5-5.3A13 13 0 0 0 30 8Z" />
                                        <path d="M30 22v14M26 26l4 4 4-4M25 41h10M27 44h6" />
                                    </svg>
                                </div>
                                <div className="smart-device-foot">
                                    <span className="smart-toggle is-white-on">
                                        <i />
                                    </span>
                                    <small>Turno hoy</small>
                                </div>
                            </button>

                            {/* 5. Microwave -> Despacho & Delivery */}
                            <button
                                type="button"
                                data-go="delivery"
                                className="smart-device-card"
                                title="Abrir Centro de Delivery"
                            >
                                <strong className="smart-device-name">Despacho</strong>
                                <div className="smart-device-icon" aria-hidden="true">
                                    <svg viewBox="0 0 80 48" fill="none" stroke="#1E293B" strokeWidth="1.6">
                                        <rect x="8" y="8" width="64" height="34" rx="2" />
                                        <path d="M54 8v34M16 18h30M60 16h6M63 26a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z" />
                                    </svg>
                                </div>
                                <div className="smart-device-foot">
                                    <span className="smart-toggle is-on">
                                        <i />
                                    </span>
                                    <small>Delivery</small>
                                </div>
                            </button>

                            {/* 6. + Ver Más -> Reserva Rápida (#quickReserve) */}
                            <button
                                type="button"
                                id="quickReserve"
                                className="smart-device-add-card"
                                title="Crear Reserva Rápida"
                            >
                                <span className="smart-add-plus">+</span>
                                <strong>Ver Más</strong>
                                <small>Nueva Reserva</small>
                            </button>
                        </div>
                    </div>

                    {/* Columna Derecha: Áreas del Restaurante + Fecha y Clima */}
                    <div className="smart-rooms-block">
                        <h2 className="smart-section-title">Áreas y Módulos</h2>

                        <div className="smart-rooms-pills">
                            <button
                                type="button"
                                data-go="reservations"
                                className="smart-room-pill is-active"
                            >
                                Salón Principal
                            </button>
                            <button type="button" data-go="orders" className="smart-room-pill">
                                Centro Pedidos
                            </button>
                            <button type="button" data-go="kitchen" className="smart-room-pill">
                                Cocina KDS
                            </button>
                            <button type="button" data-go="customers" className="smart-room-pill">
                                Clientes CRM
                            </button>
                            <button type="button" data-go="billing" className="smart-room-pill">
                                Facturación
                            </button>
                            <button type="button" data-go="reports" className="smart-room-pill">
                                Reportes BI
                            </button>
                        </div>

                        {/* Bloque Fecha y Clima */}
                        <div className="smart-weather-footer">
                            <div className="smart-date-display">
                                <span id="todayChip" className="smart-date-weekday">
                                    Miércoles,
                                </span>
                                <strong className="smart-date-day">28 Octubre</strong>
                            </div>

                            <div className="smart-temp-display">
                                <span className="smart-cloud-svg" aria-hidden="true">
                                    <svg viewBox="0 0 64 44" fill="none">
                                        <defs>
                                            <linearGradient
                                                id="smartCloudGrad"
                                                x1="0"
                                                y1="0"
                                                x2="1"
                                                y2="1"
                                            >
                                                <stop offset="0%" stopColor="#72B8FA" />
                                                <stop offset="100%" stopColor="#4F8CE6" />
                                            </linearGradient>
                                        </defs>
                                        <path
                                            d="M50 38H16a11 11 0 0 1-1.2-21.9A15 15 0 0 1 43.5 13 12.5 12.5 0 0 1 50 38Z"
                                            fill="url(#smartCloudGrad)"
                                        />
                                    </svg>
                                </span>
                                <strong className="smart-temp-value">
                                    24<sup>°</sup> C
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Estado en vivo de las 24 mesas de MySQL (oculto en inicio para mantener cápsula limpia) */}
                <div id="homeTablesWidget" className="hidden" aria-hidden="true" />
            </section>

            {/* PEDIDOS */}
            <section id="view-orders" className="app-view" aria-labelledby="ordersTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">OPERACIONES</span>
                        <h1 id="ordersTitle">Centro de pedidos</h1>
                        <p>Pedidos y seguimiento de ventas reales.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button is-hidden" id="customersManagementButton">
                            ♙ Clientes
                        </button>
                        <button type="button" className="secondary-button heading-button is-hidden" id="cashManagementButton">
                            ▣ Caja
                        </button>
                        <button type="button" className="secondary-button heading-button is-hidden" id="paymentHistoryButton">
                            ↗ Pagos
                        </button>
                        <button type="button" className="secondary-button heading-button is-hidden" id="invoiceManagementButton">
                            ▤ Comprobantes
                        </button>
                        <button type="button" className="secondary-button heading-button is-hidden" id="reportsManagementButton">
                            ▥ Reportes
                        </button>
                        <button type="button" className="secondary-button heading-button is-hidden" id="kitchenBoardButton">
                            ♨ Cocina KDS
                        </button>
                        <button type="button" className="secondary-button heading-button is-hidden" id="deliveryBoardButton">
                            ⌁ Delivery
                        </button>
                        <button type="button" className="cyan-button heading-button" id="newOrderButton">
                            ＋ Nuevo pedido
                        </button>
                    </div>
                </div>
                <div className="filter-row" id="orderFilters" aria-label="Filtrar pedidos">
                    <button className="filter-chip is-selected" data-order-filter="Todos">
                        Todos
                    </button>
                    <button className="filter-chip" data-order-filter="Pendientes">
                        Pendientes
                    </button>
                    <button className="filter-chip" data-order-filter="Preparando">
                        Preparando
                    </button>
                    <button className="filter-chip" data-order-filter="Listos">
                        Listos
                    </button>
                    <button className="filter-chip" data-order-filter="Delivery">
                        Delivery
                    </button>
                </div>
                <div id="ordersList" className="orders-list" />
            </section>

            {/* COCINA / KDS */}
            <section id="view-kitchen" className="app-view" aria-labelledby="kitchenTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">OPERACIONES · KDS</span>
                        <h1 id="kitchenTitle">Pantalla de cocina</h1>
                        <p>Cola de preparación actualizada desde MySQL.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button" data-go="orders">
                            ← Pedidos
                        </button>
                        <button type="button" className="cyan-button heading-button" id="kitchenRefreshButton">
                            ↻ Actualizar
                        </button>
                    </div>
                </div>
                <div id="kitchenSummary" className="operations-summary" />
                <div id="kitchenBoard" className="kitchen-board">
                    <div className="manager-loading">Cargando cola de cocina…</div>
                </div>
                <p className="operations-footnote">
                    La cocina puede avanzar cada pedido de Pendiente a Preparando y luego a Listo. La salida a delivery
                    se gestiona en el módulo Delivery.
                </p>
            </section>

            {/* DELIVERY */}
            <section id="view-delivery" className="app-view" aria-labelledby="deliveryTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">OPERACIONES · REPARTO</span>
                        <h1 id="deliveryTitle">Centro de Delivery</h1>
                        <p>Direcciones, repartidores, entregas e incidencias.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button" data-go="orders">
                            ← Pedidos
                        </button>
                        <button type="button" className="cyan-button heading-button" id="deliveryRefreshButton">
                            ↻ Actualizar
                        </button>
                    </div>
                </div>
                <div id="deliverySummary" className="operations-summary" />
                <div id="deliveryBoard" className="delivery-board">
                    <div className="manager-loading">Cargando entregas…</div>
                </div>
                <p className="operations-footnote">
                    Los repartidores solo pueden consultar y actualizar las entregas que están asignadas a su cuenta. El
                    administrador y el gerente pueden asignar o editar datos de entrega.
                </p>
            </section>

            {/* MENU */}
            <section id="view-menu" className="app-view" aria-labelledby="menuTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">CATÁLOGO</span>
                        <h1 id="menuTitle">Menú DESEGO</h1>
                        <p>Productos, recetas, costos y disponibilidad en tiempo real.</p>
                    </div>
                    <div className="heading-actions">
                        <a className="secondary-button heading-button" href="/" target="_blank" rel="noopener">
                            ↗ Vista cliente
                        </a>
                        <button type="button" className="cyan-button heading-button" id="newProductButton">
                            ＋ Producto
                        </button>
                    </div>
                </div>
                <label className="menu-search">
                    <span aria-hidden="true">⌕</span>
                    <input
                        id="productSearch"
                        type="search"
                        placeholder="Buscar producto..."
                        aria-label="Buscar productos"
                    />
                    <kbd>⌘ K</kbd>
                </label>
                <div className="catalog-tools" aria-label="Administrar catálogo">
                    <button type="button" id="manageCategoriesButton" className="catalog-tool is-hidden">
                        ▦ <span>Categorías</span>
                    </button>
                    <button type="button" id="manageIngredientsButton" className="catalog-tool is-hidden">
                        ♧ <span>Ingredientes y alérgenos</span>
                    </button>
                    <button type="button" id="manageInventoryButton" className="catalog-tool is-hidden">
                        ▤ <span>Inventario</span>
                    </button>
                    <button type="button" id="managePromotionsButton" className="catalog-tool is-hidden">
                        ％ <span>Promociones y cupones</span>
                    </button>
                    <button type="button" id="manageReviewsButton" className="catalog-tool is-hidden">
                        ★ <span>Reseñas y calificaciones</span>
                    </button>
                    <button type="button" id="manageSuppliersButton" className="catalog-tool is-hidden">
                        ♧ <span>Proveedores</span>
                    </button>
                    <button type="button" id="managePurchasesButton" className="catalog-tool is-hidden">
                        ▣ <span>Compras a proveedores</span>
                    </button>
                    <button type="button" id="manageCostsButton" className="catalog-tool is-hidden">
                        S/ <span>Costos de inventario</span>
                    </button>
                </div>
                <div className="filter-row" id="menuFilters" aria-label="Filtrar productos">
                    <button className="filter-chip is-selected" data-menu-filter="Todos">
                        Todos
                    </button>
                </div>
                <div id="catalogLoading" className="catalog-note is-hidden">
                    Cargando catálogo de la base de datos…
                </div>
                <div id="productsGrid" className="products-grid" />
                <div id="noProducts" className="empty-state is-hidden">
                    <span>⌕</span>
                    <strong>No se encontraron productos</strong>
                    <p>Prueba con otra categoría o búsqueda.</p>
                </div>
            </section>

            {/* PASO 13: PROVEEDORES */}
            <section id="view-suppliers" className="app-view" aria-labelledby="suppliersTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">COMPRAS · DIRECTORIO</span>
                        <h1 id="suppliersTitle">Proveedores</h1>
                        <p>Contactos, historial de compras y evaluación de proveedores.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button" data-go="menu">
                            ← Menú
                        </button>
                        <button type="button" className="cyan-button heading-button" id="refreshSuppliersButton">
                            ↻ Actualizar
                        </button>
                    </div>
                </div>
                <div id="supplierSummary" className="report-summary-grid supplier-summary-grid">
                    <article className="white-card report-kpi">
                        <small>Proveedores activos</small>
                        <strong>—</strong>
                        <span>Directorio de compra</span>
                    </article>
                </div>
                <div id="supplierFormCard" className="white-card purchasing-form-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">DIRECTORIO DE COMPRAS</span>
                            <h2 id="supplierFormTitle">Registrar proveedor</h2>
                            <p>Guarda datos fiscales y contacto para órdenes de compra.</p>
                        </div>
                    </div>
                    <form id="supplierForm" className="purchasing-grid-form">
                        <input type="hidden" id="supplierId" defaultValue="" />
                        <div className="form-field">
                            <label htmlFor="supplierName">Razón social / nombre *</label>
                            <input
                                id="supplierName"
                                maxLength={160}
                                required
                                placeholder="Ej. Pesquera del Pacífico S.A.C."
                            />
                        </div>
                        <div className="form-field">
                            <label htmlFor="supplierTaxId">RUC / documento</label>
                            <input id="supplierTaxId" maxLength={20} placeholder="Número fiscal (opcional)" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="supplierContact">Persona de contacto</label>
                            <input id="supplierContact" maxLength={120} placeholder="Nombre de contacto" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="supplierEmail">Correo de compras</label>
                            <input
                                id="supplierEmail"
                                type="email"
                                maxLength={190}
                                placeholder="ventas@proveedor.pe"
                            />
                        </div>
                        <div className="form-field">
                            <label htmlFor="supplierPhone">Teléfono</label>
                            <input id="supplierPhone" maxLength={40} placeholder="Teléfono / WhatsApp" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="supplierRating">Calificación interna</label>
                            <select id="supplierRating" defaultValue="">
                                <option value="">Sin calificar</option>
                                <option value="1">1 · Muy deficiente</option>
                                <option value="2">2 · Deficiente</option>
                                <option value="3">3 · Regular</option>
                                <option value="4">4 · Bueno</option>
                                <option value="5">5 · Excelente</option>
                            </select>
                        </div>
                        <div className="form-field form-field-wide">
                            <label htmlFor="supplierAddress">Dirección</label>
                            <input
                                id="supplierAddress"
                                maxLength={255}
                                placeholder="Dirección fiscal o de despacho"
                            />
                        </div>
                        <div className="form-field">
                            <label htmlFor="supplierCity">Ciudad / distrito</label>
                            <input id="supplierCity" maxLength={100} placeholder="Lima" />
                        </div>
                        <div className="form-field form-field-wide">
                            <label htmlFor="supplierNotes">Notas internas</label>
                            <textarea
                                id="supplierNotes"
                                maxLength={1000}
                                rows={2}
                                placeholder="Horario de entrega, condiciones, observaciones…"
                            />
                        </div>
                        <div className="purchasing-form-actions">
                            <button type="button" id="supplierResetButton" className="secondary-button">
                                Limpiar
                            </button>
                            <button type="submit" id="supplierSaveButton" className="cyan-button">
                                Guardar proveedor
                            </button>
                        </div>
                    </form>
                </div>
                <div className="white-card purchasing-list-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">DIRECTORIO</span>
                            <h2>Proveedores registrados</h2>
                            <p>El estado inactivo impide seleccionarlo para nuevas órdenes.</p>
                        </div>
                        <span id="supplierCount" className="counter-chip">
                            Cargando…
                        </span>
                    </div>
                    <label className="menu-search">
                        <span aria-hidden="true">⌕</span>
                        <input
                            id="supplierSearch"
                            type="search"
                            placeholder="Buscar por nombre, documento o contacto…"
                            aria-label="Buscar proveedores"
                        />
                    </label>
                    <div id="supplierList" className="purchasing-list">
                        <div className="manager-loading">Cargando proveedores…</div>
                    </div>
                </div>
            </section>

            {/* PASO 13: COMPRAS Y COSTOS */}
            <section id="view-purchasing" className="app-view" aria-labelledby="purchasingTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">ABASTECIMIENTO · INVENTARIO</span>
                        <h1 id="purchasingTitle">Compras a proveedores</h1>
                        <p>Órdenes, recepción de mercadería y costos de ingredientes desde MySQL.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button" data-go="suppliers">
                            ♧ Proveedores
                        </button>
                        <button type="button" className="secondary-button heading-button" data-go="menu">
                            ← Menú
                        </button>
                        <button type="button" className="cyan-button heading-button" id="refreshPurchasingButton">
                            ↻ Actualizar
                        </button>
                    </div>
                </div>
                <div id="purchaseSummary" className="report-summary-grid purchase-summary-grid">
                    <article className="white-card report-kpi">
                        <small>Órdenes abiertas</small>
                        <strong>—</strong>
                        <span>Compras por completar</span>
                    </article>
                </div>
                <div id="purchaseFormCard" className="white-card purchasing-form-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">NUEVA ORDEN</span>
                            <h2>Crear orden de compra</h2>
                            <p>
                                El costo unitario ingresado es costo neto antes de impuestos; la recepción actualizará
                                el costo medio ponderado y las capas de inventario.
                            </p>
                        </div>
                    </div>
                    <form id="purchaseOrderForm" className="purchasing-grid-form">
                        <div className="form-field">
                            <label htmlFor="purchaseSupplier">Proveedor *</label>
                            <select id="purchaseSupplier" required defaultValue="">
                                <option value="">Cargando proveedores…</option>
                            </select>
                        </div>
                        <div className="form-field">
                            <label htmlFor="purchaseExpectedDate">Fecha estimada de entrega</label>
                            <input id="purchaseExpectedDate" type="date" />
                        </div>
                        <div className="form-field">
                            <label htmlFor="purchaseTaxRate">Impuesto (%)</label>
                            <input
                                id="purchaseTaxRate"
                                type="number"
                                min="0"
                                max="25"
                                step="0.01"
                                defaultValue="18"
                                required
                            />
                        </div>
                        <div className="form-field">
                            <label htmlFor="purchaseShippingCost">Transporte (S/)</label>
                            <input
                                id="purchaseShippingCost"
                                type="number"
                                min="0"
                                max="100000"
                                step="0.01"
                                defaultValue="0"
                                required
                            />
                        </div>
                        <div className="form-field form-field-wide">
                            <label htmlFor="purchaseNotes">Notas de la orden</label>
                            <textarea
                                id="purchaseNotes"
                                maxLength={1000}
                                rows={2}
                                placeholder="Condiciones de despacho, lote esperado, etc."
                            />
                        </div>
                        <div className="purchase-lines-section form-field-wide">
                            <div className="purchase-lines-heading">
                                <div>
                                    <strong>Ingredientes solicitados</strong>
                                    <small>Elige cantidades y costo unitario neto en soles.</small>
                                </div>
                                <button type="button" className="secondary-button" id="addPurchaseLineButton">
                                    ＋ Agregar ingrediente
                                </button>
                            </div>
                            <div id="purchaseLines" className="purchase-lines">
                                <div className="manager-loading">Cargando ingredientes…</div>
                            </div>
                            <div className="purchase-total-preview">
                                <span>Subtotal estimado</span>
                                <strong id="purchaseSubtotalPreview">S/ 0.00</strong>
                            </div>
                        </div>
                        <div className="purchasing-form-actions">
                            <button type="button" className="secondary-button" id="purchaseResetButton">
                                Limpiar
                            </button>
                            <button type="submit" className="cyan-button" id="purchaseSaveButton">
                                Crear borrador de orden
                            </button>
                        </div>
                    </form>
                </div>
                <div className="white-card purchasing-list-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">SEGUIMIENTO</span>
                            <h2>Órdenes de compra</h2>
                            <p>Envía por correo, marca envíos externos y registra recepciones parciales o completas.</p>
                        </div>
                        <span id="purchaseOrderCount" className="counter-chip">
                            Cargando…
                        </span>
                    </div>
                    <div className="filter-row" id="purchaseStatusFilters">
                        <button type="button" className="filter-chip is-selected" data-purchase-filter="all">
                            Todas
                        </button>
                        <button type="button" className="filter-chip" data-purchase-filter="draft">
                            Borrador
                        </button>
                        <button type="button" className="filter-chip" data-purchase-filter="sent">
                            Enviadas
                        </button>
                        <button type="button" className="filter-chip" data-purchase-filter="partially_received">
                            Recepción parcial
                        </button>
                        <button type="button" className="filter-chip" data-purchase-filter="received">
                            Recibidas
                        </button>
                        <button type="button" className="filter-chip" data-purchase-filter="cancelled">
                            Canceladas
                        </button>
                    </div>
                    <div id="purchaseOrdersList" className="purchasing-list">
                        <div className="manager-loading">Cargando órdenes…</div>
                    </div>
                </div>
                <div id="inventoryCostCard" className="white-card purchasing-list-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">VALORACIÓN DE INVENTARIO</span>
                            <h2>Costos actuales por ingrediente</h2>
                            <p>
                                FIFO y LIFO valoran las capas existentes; Promedio usa el costo medio ponderado
                                registrado.
                            </p>
                        </div>
                        <div className="cost-method-control">
                            <label htmlFor="costingMethodSelect">Método</label>
                            <select id="costingMethodSelect" defaultValue="FIFO">
                                <option value="FIFO">FIFO · Primero en entrar</option>
                                <option value="LIFO">LIFO · Último en entrar</option>
                                <option value="AVG">Promedio ponderado</option>
                            </select>
                            <button type="button" className="secondary-button" id="saveCostingMethodButton">
                                Guardar
                            </button>
                        </div>
                    </div>
                    <div id="inventoryCostSummary" className="inventory-summary" />
                    <div id="inventoryCostList" className="purchasing-list">
                        <div className="manager-loading">Cargando costos…</div>
                    </div>
                </div>
                <div id="costHistoryCard" className="white-card purchasing-list-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">TRAZABILIDAD DE COSTOS</span>
                            <h2>Historial de costo de ingredientes</h2>
                            <p>Costos anteriores y nuevos registrados al recibir mercancía.</p>
                        </div>
                        <span id="costHistoryCount" className="counter-chip">
                            Cargando…
                        </span>
                    </div>
                    <div id="ingredientCostHistoryList" className="purchasing-list">
                        <div className="manager-loading">Cargando historial…</div>
                    </div>
                </div>
                <p className="operations-footnote">
                    El coste medio se actualiza con el costo unitario neto de cada recepción. El transporte se registra
                    en el total de la orden y no se distribuye a cada ingrediente.
                </p>
            </section>

            {/* RESERVAS Y MESAS */}
            <section id="view-reservations" className="app-view" aria-labelledby="reservationsTitle">
                <div className="screen-heading">
                    <div>
                        <span className="section-eyebrow">PLANIFICACIÓN</span>
                        <h1 id="reservationsTitle">Reservas y mesas</h1>
                        <p>Disponibilidad y plano del restaurante.</p>
                    </div>
                    <div className="heading-actions">
                        <button type="button" className="secondary-button heading-button is-hidden" id="reservationCustomersButton">
                            ♙ Clientes
                        </button>
                        <button type="button" className="secondary-button heading-button" id="refreshReservationsButton">
                            ↻ Actualizar
                        </button>
                        <button type="button" className="secondary-button heading-button is-hidden" id="createTableButton">
                            ＋ Nueva mesa
                        </button>
                        <button type="button" className="cyan-button heading-button" id="createReservationButton">
                            ＋ Crear reserva
                        </button>
                    </div>
                </div>
                <div id="reservationsTablesWidget" />
                <div className="white-card table-management-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">PLANO DEL RESTAURANTE</span>
                            <h2>Mesas registradas</h2>
                            <p>Estados y capacidades gestionados desde MySQL.</p>
                        </div>
                        <span id="tableCount" className="counter-chip">
                            Cargando mesas…
                        </span>
                    </div>
                    <div id="tablesGrid" className="tables-grid">
                        <div className="manager-loading">Cargando mesas…</div>
                    </div>
                    <p className="operations-footnote">
                        El estado “Reservada” se calcula a partir de las reservas próximas. “Ocupada” y “Mantenimiento”
                        pueden cambiarse desde la administración de mesas.
                    </p>
                </div>
                <div className="white-card reservation-list-card">
                    <div className="card-heading">
                        <div>
                            <span className="section-eyebrow">AGENDA</span>
                            <h2>Reservas agendadas</h2>
                        </div>
                        <span id="reservationCount" className="counter-chip">
                            Cargando reservas…
                        </span>
                    </div>
                    <div id="reservationsList" className="reservation-list">
                        <div className="manager-loading">Cargando reservas…</div>
                    </div>
                </div>
            </section>
        </>
    );
}

