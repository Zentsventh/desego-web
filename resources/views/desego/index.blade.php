<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#0845D8">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>DESEGO CEVICHERÍA · Administración</title>
  <link rel="stylesheet" href="{{ asset('styles.css') }}">
</head>
<body>
  <div id="app" class="app-shell">
    <!-- Autenticación -->
    <main id="authScreen" class="auth-screen">
      <div class="auth-orb auth-orb-one"></div>
      <div class="auth-orb auth-orb-two"></div>
      <section id="loginPanel" class="auth-panel">
        <div class="auth-brand">
          <img class="auth-logo-image" src="{{ asset('assets/desego-logo.png') }}" alt="Logo de DESEGO CEVICHERÍA">
          <div class="auth-brand-name">DESEGO</div>
          <div class="auth-brand-subtitle">CEVICHERÍA</div>
        </div>
        <div class="auth-intro">
          <span class="auth-kicker">PANEL ADMINISTRATIVO</span>
          <h1>¡Qué bueno verte!</h1>
          <p>Inicia sesión para administrar tu restaurante.</p>
        </div>
        <form id="loginForm" class="auth-form">
          <label class="auth-label" for="loginEmail">Correo electrónico</label>
          <div class="auth-input-wrap"><span class="field-icon" aria-hidden="true">✉</span><input id="loginEmail" type="email" placeholder="admin@desego.com" autocomplete="username" required></div>
          <label class="auth-label" for="loginPassword">Contraseña</label>
          <div class="auth-input-wrap"><span class="field-icon" aria-hidden="true">▣</span><input id="loginPassword" type="password" placeholder="Ingresa tu contraseña" autocomplete="current-password" required><button type="button" class="password-eye" data-password="loginPassword" aria-label="Mostrar contraseña">◉</button></div>
          <div class="auth-helpers"><label class="remember-label"><input id="rememberMe" type="checkbox"><span class="fake-check"></span>Recordarme</label><button type="button" class="text-action" id="forgotPassword">¿Olvidaste tu contraseña?</button></div>
          <p id="loginError" class="auth-feedback" aria-live="polite"></p>
          <button type="submit" class="auth-submit">INICIAR SESIÓN <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-switch">¿No tienes una cuenta? <button type="button" id="showRegister" class="text-action text-action-strong">Regístrate</button></p>
        <div class="demo-access"><span>PRIMER ACCESO</span><strong>Accede a tu restaurante</strong><small>Usa tu cuenta existente. El administrador gestiona los accesos del equipo.</small></div>
      </section>
      <section id="registerPanel" class="auth-panel is-hidden">
        <div class="auth-brand auth-brand-compact"><img class="auth-logo-image" src="{{ asset('assets/desego-logo.png') }}" alt="Logo de DESEGO CEVICHERÍA"><div class="auth-brand-name">DESEGO</div><div class="auth-brand-subtitle">CREAR CUENTA</div></div>
        <div class="auth-intro"><span class="auth-kicker">NUEVO USUARIO</span><h1>Únete a DESEGO</h1><p>Crea tus credenciales para continuar.</p></div>
        <form id="registerForm" class="auth-form">
          <label class="auth-label" for="registerName">Nombre completo</label><div class="auth-input-wrap"><span class="field-icon" aria-hidden="true">♙</span><input id="registerName" type="text" placeholder="Juan Desego" autocomplete="name" required minlength="3"></div>
          <label class="auth-label" for="registerEmail">Correo electrónico</label><div class="auth-input-wrap"><span class="field-icon" aria-hidden="true">✉</span><input id="registerEmail" type="email" placeholder="correo@ejemplo.com" autocomplete="email" required></div>
          <label class="auth-label" for="registerPassword">Contraseña</label><div class="auth-input-wrap"><span class="field-icon" aria-hidden="true">▣</span><input id="registerPassword" type="password" placeholder="Mínimo 10 caracteres" autocomplete="new-password" required minlength="10"><button type="button" class="password-eye" data-password="registerPassword" aria-label="Mostrar contraseña">◉</button></div>
          <label class="auth-label" for="confirmPassword">Confirmar contraseña</label><div class="auth-input-wrap"><span class="field-icon" aria-hidden="true">▣</span><input id="confirmPassword" type="password" placeholder="Repite tu contraseña" autocomplete="new-password" required minlength="10"><button type="button" class="password-eye" data-password="confirmPassword" aria-label="Mostrar contraseña">◉</button></div>
          <p id="registerError" class="auth-feedback" aria-live="polite"></p>
          <button type="submit" class="auth-submit">CREAR CUENTA <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-switch">¿Ya tienes una cuenta? <button type="button" id="showLogin" class="text-action text-action-strong">Inicia sesión</button></p>
      </section>

      <!-- Recuperación de contraseña: solicitud de enlace por correo. -->
      <section id="forgotPanel" class="auth-panel is-hidden" aria-labelledby="forgotTitle">
        <div class="auth-brand auth-brand-compact">
          <img class="auth-logo-image" src="{{ asset('assets/desego-logo.png') }}" alt="Logo de DESEGO CEVICHERÍA"><div class="auth-brand-name">DESEGO</div><div class="auth-brand-subtitle">RECUPERAR ACCESO</div>
        </div>
        <div class="auth-intro">
          <span class="auth-kicker">ASISTENCIA DE CUENTA</span>
          <h1 id="forgotTitle">¿Olvidaste tu contraseña?</h1>
          <p>Escribe el correo asociado a tu cuenta y te enviaremos un enlace seguro para crear una contraseña nueva.</p>
        </div>
        <form id="forgotPasswordForm" class="auth-form" novalidate>
          <label class="auth-label" for="forgotEmail">Correo electrónico</label>
          <div class="auth-input-wrap"><span class="field-icon" aria-hidden="true">✉</span><input id="forgotEmail" type="email" placeholder="correo@ejemplo.com" autocomplete="email" required maxlength="190"></div>
          <p id="forgotFeedback" class="auth-feedback" aria-live="polite"></p>
          <p id="forgotDebugWrap" class="auth-feedback is-hidden">Prueba local: <a id="forgotDebugLink" href="#" target="_self">abrir enlace de recuperación</a></p>
          <button type="submit" class="auth-submit">ENVIAR ENLACE <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-switch"><button type="button" id="forgotBackToLogin" class="text-action text-action-strong">← Volver a iniciar sesión</button></p>
      </section>

      <!-- Formulario de nueva contraseña, abierto desde el enlace temporal. -->
      <section id="resetPanel" class="auth-panel is-hidden" aria-labelledby="resetTitle">
        <div class="auth-brand auth-brand-compact">
          <img class="auth-logo-image" src="{{ asset('assets/desego-logo.png') }}" alt="Logo de DESEGO CEVICHERÍA"><div class="auth-brand-name">DESEGO</div><div class="auth-brand-subtitle">NUEVA CONTRASEÑA</div>
        </div>
        <div class="auth-intro">
          <span class="auth-kicker">RECUPERACIÓN SEGURA</span>
          <h1 id="resetTitle">Crea una contraseña nueva</h1>
          <p>Usa al menos 12 caracteres. El enlace solo puede utilizarse una vez y vence después de 30 minutos.</p>
        </div>
        <form id="resetPasswordForm" class="auth-form" novalidate>
          <input type="hidden" id="resetToken" value="">
          <label class="auth-label" for="newPassword">Nueva contraseña</label>
          <div class="auth-input-wrap"><span class="field-icon" aria-hidden="true">▣</span><input id="newPassword" type="password" autocomplete="new-password" placeholder="Mínimo 12 caracteres" required minlength="12" maxlength="4096"><button type="button" class="password-eye" data-password="newPassword" aria-label="Mostrar contraseña">◉</button></div>
          <label class="auth-label" for="confirmNewPassword">Confirmar contraseña</label>
          <div class="auth-input-wrap"><span class="field-icon" aria-hidden="true">▣</span><input id="confirmNewPassword" type="password" autocomplete="new-password" placeholder="Repite la contraseña" required minlength="12" maxlength="4096"><button type="button" class="password-eye" data-password="confirmNewPassword" aria-label="Mostrar contraseña">◉</button></div>
          <p id="resetFeedback" class="auth-feedback" aria-live="polite"></p>
          <button type="submit" class="auth-submit">GUARDAR CONTRASEÑA <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-switch"><button type="button" id="resetBackToLogin" class="text-action text-action-strong">← Volver a iniciar sesión</button></p>
      </section>
    </main>

    <!-- Aplicación autenticada -->
    <div id="dashboardScreen" class="dashboard-screen is-hidden">
      <header class="topbar">
        <div class="topbar-brand">
          <img class="topbar-logo-image" src="{{ asset('assets/desego-logo.png') }}" alt="Logo DESEGO">
          <div class="brand-copy"><h2>DESEGO CEVICHERÍA</h2><div class="connection-line"><i id="connectionDot" class="connection-dot is-online"></i><span>Administración</span><b>·</b><span id="connectionLabel">Online</span></div></div>
        </div>
        <div class="topbar-actions">
          <button type="button" id="connectionToggle" class="topbar-icon-button" title="Cambiar conexión" aria-label="Cambiar estado de conexión"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.5 18.2h10.2a4.1 4.1 0 0 0 .4-8.2 6.2 6.2 0 0 0-11.8-1.2A4.8 4.8 0 0 0 7.5 18.2Z"/><path d="m9 13 3-3 3 3M12 10v7"/></svg></button>
          <button type="button" id="themeToggleButton" class="topbar-icon-button" title="Cambiar color del diseño" aria-label="Abrir selector de color" aria-expanded="false" aria-controls="themePopover"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 0 18h1.3a2.2 2.2 0 0 0 1.7-3.6 1.6 1.6 0 0 1 1.2-2.7H18a3 3 0 0 0 3-3C21 6.9 17 3 12 3Z"/><circle cx="7.5" cy="10" r="1"/><circle cx="10.5" cy="6.8" r="1"/><circle cx="15" cy="7.4" r="1"/></svg></button>
          <button type="button" id="notificationsButton" class="topbar-icon-button notification-button" title="Notificaciones" aria-label="Ver notificaciones"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><span class="notification-badge is-hidden">0</span></button>
        </div>
      </header>

      <!-- Selector de tono: desliza hacia la derecha para oscurecer el azul. -->
      <aside id="themePopover" class="theme-popover is-hidden" aria-label="Personalizar color del diseño">
        <div class="theme-popover-head">
          <div><span>APARIENCIA</span><h2>Color del diseño</h2></div>
          <button type="button" id="themeCloseButton" class="theme-close" aria-label="Cerrar selector de color">×</button>
        </div>
        <div class="theme-current-row"><label for="themeDarkness">Tono del fondo</label><strong id="themeToneLabel">Azul profundo</strong></div>
        <input id="themeDarkness" class="theme-range" type="range" min="0" max="100" step="1" value="72" aria-label="Nivel de oscuridad del color">
        <div class="theme-range-labels"><span>Azul vivo</span><span>Más oscuro</span></div>
        <div class="theme-presets" aria-label="Tonos predeterminados">
          <button type="button" class="theme-preset" data-theme-preset="0"><span class="preset-swatch swatch-original"></span><small>Original</small></button>
          <button type="button" class="theme-preset is-active" data-theme-preset="72"><span class="preset-swatch swatch-deep"></span><small>Profundo</small></button>
          <button type="button" class="theme-preset" data-theme-preset="100"><span class="preset-swatch swatch-night"></span><small>Noche</small></button>
        </div>
        <p class="theme-help">El cambio se aplica a toda la aplicación y se guarda en este navegador.</p>
      </aside>

      <main class="screen-content">
        <!-- INICIO -->
        <section id="view-home" class="app-view is-active" aria-labelledby="homeTitle">
          <div class="screen-heading"><div><span class="section-eyebrow">RESUMEN DEL RESTAURANTE</span><h1 id="homeTitle">Panel General</h1><p>Bienvenido de nuevo, <strong id="welcomeName">Juan</strong></p></div><span class="today-chip" id="todayChip">Hoy</span></div>
          <div class="quick-actions-grid">
            <button type="button" class="quick-action quick-sales" id="quickSale"><span class="quick-action-icon">＋</span><span class="quick-action-copy"><small>ACCIÓN RÁPIDA</small><strong>Nueva Venta</strong><em>Registrar un pedido</em></span><span class="quick-arrow">↗</span></button>
            <button type="button" class="quick-action quick-reserve" id="quickReserve"><span class="quick-action-icon">▦</span><span class="quick-action-copy"><small>ACCIÓN RÁPIDA</small><strong>Reserva rápida</strong><em>Asignar una mesa</em></span><span class="quick-arrow">↗</span></button>
          </div>
          <div class="kpi-grid">
            <article class="white-card kpi-card"><div class="kpi-card-top"><span class="kpi-icon kpi-icon-green">S/</span><span class="kpi-change change-positive" id="salesTodayBadge">MySQL</span></div><p>Ventas Hoy</p><h2 id="salesTodayValue">S/ 0.00</h2><small class="card-muted">Cobros netos de hoy</small></article>
            <article class="white-card kpi-card"><div class="kpi-card-top"><span class="kpi-icon kpi-icon-blue">▤</span><span class="kpi-change change-blue" id="pendingOrdersBadge">0 pendientes</span></div><p>Pedidos de hoy</p><h2 id="ordersTodayValue">0</h2><small class="card-muted">Pedidos guardados en MySQL</small></article>
            <article class="white-card kpi-card"><div class="kpi-card-top"><span class="kpi-icon kpi-icon-purple">◇</span><span class="kpi-change change-blue">Hoy</span></div><p>Ticket Promedio</p><h2 id="averageTicketValue">S/ 0.00</h2><small class="card-muted">Por pedido pagado</small></article>
          </div>
          <div id="homeTablesWidget"></div>
          <div class="white-card overview-note"><span class="overview-note-icon">✦</span><div><strong>Todo listo para el servicio</strong><p>Revisa tus pedidos pendientes y mantén el salón al día.</p></div><button type="button" class="inline-arrow" data-go="orders" aria-label="Ir a pedidos">→</button></div>
        </section>

        <!-- PEDIDOS -->
        <section id="view-orders" class="app-view" aria-labelledby="ordersTitle">
          <div class="screen-heading"><div><span class="section-eyebrow">OPERACIONES</span><h1 id="ordersTitle">Centro de pedidos</h1><p>Pedidos y seguimiento de ventas reales.</p></div><div class="heading-actions"><button type="button" class="secondary-button heading-button is-hidden" id="customersManagementButton">♙ Clientes</button><button type="button" class="secondary-button heading-button is-hidden" id="cashManagementButton">▣ Caja</button><button type="button" class="secondary-button heading-button is-hidden" id="paymentHistoryButton">↗ Pagos</button><button type="button" class="secondary-button heading-button is-hidden" id="invoiceManagementButton">▤ Comprobantes</button><button type="button" class="secondary-button heading-button is-hidden" id="reportsManagementButton">▥ Reportes</button><button type="button" class="secondary-button heading-button is-hidden" id="kitchenBoardButton">♨ Cocina KDS</button><button type="button" class="secondary-button heading-button is-hidden" id="deliveryBoardButton">⌁ Delivery</button><button type="button" class="cyan-button heading-button" id="newOrderButton">＋ Nuevo pedido</button></div></div>
          <div class="filter-row" id="orderFilters" aria-label="Filtrar pedidos"><button class="filter-chip is-selected" data-order-filter="Todos">Todos</button><button class="filter-chip" data-order-filter="Pendientes">Pendientes</button><button class="filter-chip" data-order-filter="Preparando">Preparando</button><button class="filter-chip" data-order-filter="Listos">Listos</button><button class="filter-chip" data-order-filter="Delivery">Delivery</button></div>
          <div id="ordersList" class="orders-list"></div>
        </section>

        <!-- COCINA / KDS -->
        <section id="view-kitchen" class="app-view" aria-labelledby="kitchenTitle">
          <div class="screen-heading"><div><span class="section-eyebrow">OPERACIONES · KDS</span><h1 id="kitchenTitle">Pantalla de cocina</h1><p>Cola de preparación actualizada desde MySQL.</p></div><div class="heading-actions"><button type="button" class="secondary-button heading-button" data-go="orders">← Pedidos</button><button type="button" class="cyan-button heading-button" id="kitchenRefreshButton">↻ Actualizar</button></div></div>
          <div id="kitchenSummary" class="operations-summary"></div>
          <div id="kitchenBoard" class="kitchen-board"><div class="manager-loading">Cargando cola de cocina…</div></div>
          <p class="operations-footnote">La cocina puede avanzar cada pedido de Pendiente a Preparando y luego a Listo. La salida a delivery se gestiona en el módulo Delivery.</p>
        </section>

        <!-- DELIVERY -->
        <section id="view-delivery" class="app-view" aria-labelledby="deliveryTitle">
          <div class="screen-heading"><div><span class="section-eyebrow">OPERACIONES · REPARTO</span><h1 id="deliveryTitle">Centro de Delivery</h1><p>Direcciones, repartidores, entregas e incidencias.</p></div><div class="heading-actions"><button type="button" class="secondary-button heading-button" data-go="orders">← Pedidos</button><button type="button" class="cyan-button heading-button" id="deliveryRefreshButton">↻ Actualizar</button></div></div>
          <div id="deliverySummary" class="operations-summary"></div>
          <div id="deliveryBoard" class="delivery-board"><div class="manager-loading">Cargando entregas…</div></div>
          <p class="operations-footnote">Los repartidores solo pueden consultar y actualizar las entregas que están asignadas a su cuenta. El administrador y el gerente pueden asignar o editar datos de entrega.</p>
        </section>

        <!-- MENU -->
        <section id="view-menu" class="app-view" aria-labelledby="menuTitle">
          <div class="screen-heading"><div><span class="section-eyebrow">CATÁLOGO</span><h1 id="menuTitle">Menú DESEGO</h1><p>Productos y disponibilidad.</p></div><div class="heading-actions"><a class="secondary-button heading-button" href="{{ route('desego.store') }}" target="_blank" rel="noopener">↗ Vista cliente</a><button type="button" class="cyan-button heading-button" id="newProductButton">＋ Producto</button></div></div>
          <label class="menu-search"><span aria-hidden="true">⌕</span><input id="productSearch" type="search" placeholder="Buscar producto..." aria-label="Buscar productos"><kbd>⌘ K</kbd></label>
          <div class="catalog-tools" aria-label="Administrar catálogo">
            <button type="button" id="manageCategoriesButton" class="catalog-tool is-hidden">▦ <span>Categorías</span></button>
            <button type="button" id="manageIngredientsButton" class="catalog-tool is-hidden">♧ <span>Ingredientes y alérgenos</span></button>
            <button type="button" id="manageInventoryButton" class="catalog-tool is-hidden">▤ <span>Inventario</span></button>
            <button type="button" id="managePromotionsButton" class="catalog-tool is-hidden">％ <span>Promociones y cupones</span></button>
            <button type="button" id="manageReviewsButton" class="catalog-tool is-hidden">★ <span>Reseñas y calificaciones</span></button>
            <button type="button" id="manageSuppliersButton" class="catalog-tool is-hidden">♧ <span>Proveedores</span></button>
            <button type="button" id="managePurchasesButton" class="catalog-tool is-hidden">▣ <span>Compras a proveedores</span></button>
            <button type="button" id="manageCostsButton" class="catalog-tool is-hidden">S/ <span>Costos de inventario</span></button>
          </div>
          <div class="filter-row" id="menuFilters" aria-label="Filtrar productos"><button class="filter-chip is-selected" data-menu-filter="Todos">Todos</button></div>
          <div id="catalogLoading" class="catalog-note is-hidden">Cargando catálogo de la base de datos…</div>
          <div id="productsGrid" class="products-grid"></div><div id="noProducts" class="empty-state is-hidden"><span>⌕</span><strong>No se encontraron productos</strong><p>Prueba con otra categoría o búsqueda.</p></div>
        </section>

        <!-- PASO 13: PROVEEDORES -->
        <section id="view-suppliers" class="app-view" aria-labelledby="suppliersTitle">
          <div class="screen-heading"><div><span class="section-eyebrow">COMPRAS · DIRECTORIO</span><h1 id="suppliersTitle">Proveedores</h1><p>Contactos, historial de compras y evaluación de proveedores.</p></div><div class="heading-actions"><button type="button" class="secondary-button heading-button" data-go="menu">← Menú</button><button type="button" class="cyan-button heading-button" id="refreshSuppliersButton">↻ Actualizar</button></div></div>
          <div id="supplierSummary" class="report-summary-grid supplier-summary-grid"><article class="white-card report-kpi"><small>Proveedores activos</small><strong>—</strong><span>Directorio de compra</span></article></div>
          <div id="supplierFormCard" class="white-card purchasing-form-card">
            <div class="card-heading"><div><span class="section-eyebrow">DIRECTORIO DE COMPRAS</span><h2 id="supplierFormTitle">Registrar proveedor</h2><p>Guarda datos fiscales y contacto para órdenes de compra.</p></div></div>
            <form id="supplierForm" class="purchasing-grid-form">
              <input type="hidden" id="supplierId" value="">
              <div class="form-field"><label for="supplierName">Razón social / nombre *</label><input id="supplierName" maxlength="160" required placeholder="Ej. Pesquera del Pacífico S.A.C."></div>
              <div class="form-field"><label for="supplierTaxId">RUC / documento</label><input id="supplierTaxId" maxlength="20" placeholder="Número fiscal (opcional)"></div>
              <div class="form-field"><label for="supplierContact">Persona de contacto</label><input id="supplierContact" maxlength="120" placeholder="Nombre de contacto"></div>
              <div class="form-field"><label for="supplierEmail">Correo de compras</label><input id="supplierEmail" type="email" maxlength="190" placeholder="ventas@proveedor.pe"></div>
              <div class="form-field"><label for="supplierPhone">Teléfono</label><input id="supplierPhone" maxlength="40" placeholder="Teléfono / WhatsApp"></div>
              <div class="form-field"><label for="supplierRating">Calificación interna</label><select id="supplierRating"><option value="">Sin calificar</option><option value="1">1 · Muy deficiente</option><option value="2">2 · Deficiente</option><option value="3">3 · Regular</option><option value="4">4 · Bueno</option><option value="5">5 · Excelente</option></select></div>
              <div class="form-field form-field-wide"><label for="supplierAddress">Dirección</label><input id="supplierAddress" maxlength="255" placeholder="Dirección fiscal o de despacho"></div>
              <div class="form-field"><label for="supplierCity">Ciudad / distrito</label><input id="supplierCity" maxlength="100" placeholder="Lima"></div>
              <div class="form-field form-field-wide"><label for="supplierNotes">Notas internas</label><textarea id="supplierNotes" maxlength="1000" rows="2" placeholder="Horario de entrega, condiciones, observaciones…"></textarea></div>
              <div class="purchasing-form-actions"><button type="button" id="supplierResetButton" class="secondary-button">Limpiar</button><button type="submit" id="supplierSaveButton" class="cyan-button">Guardar proveedor</button></div>
            </form>
          </div>
          <div class="white-card purchasing-list-card"><div class="card-heading"><div><span class="section-eyebrow">DIRECTORIO</span><h2>Proveedores registrados</h2><p>El estado inactivo impide seleccionarlo para nuevas órdenes.</p></div><span id="supplierCount" class="counter-chip">Cargando…</span></div><label class="menu-search"><span aria-hidden="true">⌕</span><input id="supplierSearch" type="search" placeholder="Buscar por nombre, documento o contacto…" aria-label="Buscar proveedores"></label><div id="supplierList" class="purchasing-list"><div class="manager-loading">Cargando proveedores…</div></div></div>
        </section>

        <!-- PASO 13: COMPRAS Y COSTOS -->
        <section id="view-purchasing" class="app-view" aria-labelledby="purchasingTitle">
          <div class="screen-heading"><div><span class="section-eyebrow">ABASTECIMIENTO · INVENTARIO</span><h1 id="purchasingTitle">Compras a proveedores</h1><p>Órdenes, recepción de mercadería y costos de ingredientes desde MySQL.</p></div><div class="heading-actions"><button type="button" class="secondary-button heading-button" data-go="suppliers">♧ Proveedores</button><button type="button" class="secondary-button heading-button" data-go="menu">← Menú</button><button type="button" class="cyan-button heading-button" id="refreshPurchasingButton">↻ Actualizar</button></div></div>
          <div id="purchaseSummary" class="report-summary-grid purchase-summary-grid"><article class="white-card report-kpi"><small>Órdenes abiertas</small><strong>—</strong><span>Compras por completar</span></article></div>
          <div id="purchaseFormCard" class="white-card purchasing-form-card">
            <div class="card-heading"><div><span class="section-eyebrow">NUEVA ORDEN</span><h2>Crear orden de compra</h2><p>El costo unitario ingresado es costo neto antes de impuestos; la recepción actualizará el costo medio ponderado y las capas de inventario.</p></div></div>
            <form id="purchaseOrderForm" class="purchasing-grid-form">
              <div class="form-field"><label for="purchaseSupplier">Proveedor *</label><select id="purchaseSupplier" required><option value="">Cargando proveedores…</option></select></div>
              <div class="form-field"><label for="purchaseExpectedDate">Fecha estimada de entrega</label><input id="purchaseExpectedDate" type="date"></div>
              <div class="form-field"><label for="purchaseTaxRate">Impuesto (%)</label><input id="purchaseTaxRate" type="number" min="0" max="25" step="0.01" value="18" required></div>
              <div class="form-field"><label for="purchaseShippingCost">Transporte (S/)</label><input id="purchaseShippingCost" type="number" min="0" max="100000" step="0.01" value="0" required></div>
              <div class="form-field form-field-wide"><label for="purchaseNotes">Notas de la orden</label><textarea id="purchaseNotes" maxlength="1000" rows="2" placeholder="Condiciones de despacho, lote esperado, etc."></textarea></div>
              <div class="purchase-lines-section form-field-wide"><div class="purchase-lines-heading"><div><strong>Ingredientes solicitados</strong><small>Elige cantidades y costo unitario neto en soles.</small></div><button type="button" class="secondary-button" id="addPurchaseLineButton">＋ Agregar ingrediente</button></div><div id="purchaseLines" class="purchase-lines"><div class="manager-loading">Cargando ingredientes…</div></div><div class="purchase-total-preview"><span>Subtotal estimado</span><strong id="purchaseSubtotalPreview">S/ 0.00</strong></div></div>
              <div class="purchasing-form-actions"><button type="button" class="secondary-button" id="purchaseResetButton">Limpiar</button><button type="submit" class="cyan-button" id="purchaseSaveButton">Crear borrador de orden</button></div>
            </form>
          </div>
          <div class="white-card purchasing-list-card"><div class="card-heading"><div><span class="section-eyebrow">SEGUIMIENTO</span><h2>Órdenes de compra</h2><p>Envía por correo, marca envíos externos y registra recepciones parciales o completas.</p></div><span id="purchaseOrderCount" class="counter-chip">Cargando…</span></div><div class="filter-row" id="purchaseStatusFilters"><button type="button" class="filter-chip is-selected" data-purchase-filter="all">Todas</button><button type="button" class="filter-chip" data-purchase-filter="draft">Borrador</button><button type="button" class="filter-chip" data-purchase-filter="sent">Enviadas</button><button type="button" class="filter-chip" data-purchase-filter="partially_received">Recepción parcial</button><button type="button" class="filter-chip" data-purchase-filter="received">Recibidas</button><button type="button" class="filter-chip" data-purchase-filter="cancelled">Canceladas</button></div><div id="purchaseOrdersList" class="purchasing-list"><div class="manager-loading">Cargando órdenes…</div></div></div>
          <div id="inventoryCostCard" class="white-card purchasing-list-card"><div class="card-heading"><div><span class="section-eyebrow">VALORACIÓN DE INVENTARIO</span><h2>Costos actuales por ingrediente</h2><p>FIFO y LIFO valoran las capas existentes; Promedio usa el costo medio ponderado registrado.</p></div><div class="cost-method-control"><label for="costingMethodSelect">Método</label><select id="costingMethodSelect"><option value="FIFO">FIFO · Primero en entrar</option><option value="LIFO">LIFO · Último en entrar</option><option value="AVG">Promedio ponderado</option></select><button type="button" class="secondary-button" id="saveCostingMethodButton">Guardar</button></div></div><div id="inventoryCostSummary" class="inventory-summary"></div><div id="inventoryCostList" class="purchasing-list"><div class="manager-loading">Cargando costos…</div></div></div>
          <div id="costHistoryCard" class="white-card purchasing-list-card"><div class="card-heading"><div><span class="section-eyebrow">TRAZABILIDAD DE COSTOS</span><h2>Historial de costo de ingredientes</h2><p>Costos anteriores y nuevos registrados al recibir mercancía.</p></div><span id="costHistoryCount" class="counter-chip">Cargando…</span></div><div id="ingredientCostHistoryList" class="purchasing-list"><div class="manager-loading">Cargando historial…</div></div></div>
          <p class="operations-footnote">El coste medio se actualiza con el costo unitario neto de cada recepción. El transporte se registra en el total de la orden y no se distribuye a cada ingrediente. Verifica la contabilización fiscal con tu responsable contable.</p>
        </section>

        <!-- RESERVAS Y MESAS: datos reales servidos por MySQL -->
        <section id="view-reservations" class="app-view" aria-labelledby="reservationsTitle">
          <div class="screen-heading">
            <div><span class="section-eyebrow">PLANIFICACIÓN</span><h1 id="reservationsTitle">Reservas y mesas</h1><p>Disponibilidad y plano del restaurante.</p></div>
            <div class="heading-actions">
              <button type="button" class="secondary-button heading-button is-hidden" id="reservationCustomersButton">♙ Clientes</button><button type="button" class="secondary-button heading-button" id="refreshReservationsButton">↻ Actualizar</button>
              <button type="button" class="secondary-button heading-button is-hidden" id="createTableButton">＋ Nueva mesa</button>
              <button type="button" class="cyan-button heading-button" id="createReservationButton">＋ Crear reserva</button>
            </div>
          </div>
          <div id="reservationsTablesWidget"></div>
          <div class="white-card table-management-card">
            <div class="card-heading"><div><span class="section-eyebrow">PLANO DEL RESTAURANTE</span><h2>Mesas registradas</h2><p>Estados y capacidades gestionados desde MySQL.</p></div><span id="tableCount" class="counter-chip">Cargando mesas…</span></div>
            <div id="tablesGrid" class="tables-grid"><div class="manager-loading">Cargando mesas…</div></div>
            <p class="operations-footnote">El estado “Reservada” se calcula a partir de las reservas próximas. “Ocupada” y “Mantenimiento” pueden cambiarse desde la administración de mesas.</p>
          </div>
          <div class="white-card reservation-list-card"><div class="card-heading"><div><span class="section-eyebrow">AGENDA</span><h2>Reservas agendadas</h2></div><span id="reservationCount" class="counter-chip">Cargando reservas…</span></div><div id="reservationsList" class="reservation-list"><div class="manager-loading">Cargando reservas…</div></div></div>
        </section>


        <!-- CLIENTES Y DIRECCIONES: datos de contacto almacenados en MySQL. -->
        <section id="view-customers" class="app-view" aria-labelledby="customersTitle">
          <div class="screen-heading">
            <div><span class="section-eyebrow">CRM · DATOS DE CLIENTES</span><h1 id="customersTitle">Clientes y direcciones</h1><p>Información de contacto y ubicaciones guardadas para pedidos y reservas.</p></div>
            <div class="heading-actions"><button type="button" class="secondary-button heading-button" data-go="orders">← Volver</button><button type="button" class="secondary-button heading-button is-hidden" id="loyaltyManagementButton">✦ Fidelización</button><button type="button" class="secondary-button heading-button" id="refreshCustomersButton">↻ Actualizar</button><button type="button" class="cyan-button heading-button is-hidden" id="newCustomerButton">＋ Nuevo cliente</button></div>
          </div>
          <label class="menu-search customer-search"><span aria-hidden="true">⌕</span><input id="customerSearch" type="search" placeholder="Buscar por nombre, documento, teléfono o correo…" aria-label="Buscar clientes"><kbd>⌕</kbd></label>
          <div id="customerSummary" class="operations-summary customer-summary"><article class="operations-metric"><span class="operations-metric-icon metric-blue">♙</span><div><small>Clientes cargados</small><strong>0</strong></div></article><article class="operations-metric"><span class="operations-metric-icon metric-green">⌂</span><div><small>Direcciones guardadas</small><strong>0</strong></div></article><article class="operations-metric"><span class="operations-metric-icon metric-amber">▤</span><div><small>Clientes activos</small><strong>0</strong></div></article></div>
          <div id="customersList" class="customers-grid"><div class="manager-loading">Cargando clientes…</div></div>
          <p class="operations-footnote">Los datos se guardan en MySQL. Las direcciones usadas en Delivery se copian al pedido como una instantánea para conservar el destino original, aunque la ficha cambie posteriormente.</p>
        </section>


        <!-- FIDELIZACIÓN: puntos, recompensas, canjes y movimientos registrados en MySQL. -->
        <section id="view-loyalty" class="app-view" aria-labelledby="loyaltyTitle">
          <div class="screen-heading">
            <div><span class="section-eyebrow">CRM · PROGRAMA DE PUNTOS</span><h1 id="loyaltyTitle">Fidelización DESEGO</h1><p>Configura puntos, administra recompensas y consulta el historial de cada cliente.</p></div>
            <div class="heading-actions"><button type="button" class="secondary-button heading-button" data-go="customers">← Clientes</button><button type="button" class="secondary-button heading-button" id="refreshLoyaltyButton">↻ Actualizar</button><button type="button" class="cyan-button heading-button" id="newLoyaltyRewardButton">＋ Recompensa</button></div>
          </div>
          <div id="loyaltySummary" class="operations-summary loyalty-summary"><article class="operations-metric"><span class="operations-metric-icon metric-blue">✦</span><div><small>Clientes inscritos</small><strong>0</strong></div></article><article class="operations-metric"><span class="operations-metric-icon metric-green">★</span><div><small>Puntos en circulación</small><strong>0</strong></div></article><article class="operations-metric"><span class="operations-metric-icon metric-purple">↗</span><div><small>Puntos acumulados</small><strong>0</strong></div></article><article class="operations-metric"><span class="operations-metric-icon metric-amber">♢</span><div><small>Canjes emitidos</small><strong>0</strong></div></article></div>
          <div class="white-card loyalty-settings-card">
            <div class="card-heading"><div><span class="section-eyebrow">REGLAS DEL PROGRAMA</span><h2>Configuración de puntos</h2><p>Los puntos se acreditan cuando el pedido queda pagado y se revierten ante un reembolso.</p></div></div>
            <form id="loyaltySettingsForm" class="loyalty-settings-form"><div class="form-field"><label for="loyaltySolesPerPoint">Importe elegible por punto (S/) *</label><input id="loyaltySolesPerPoint" type="number" min="1" max="10000" step="0.50" value="10" required><small>Ejemplo: S/ 10 de productos generan 1 punto.</small></div><div class="form-field"><label for="loyaltyMinAmount">Compra mínima elegible (S/)</label><input id="loyaltyMinAmount" type="number" min="0" max="1000000" step="0.50" value="0" required></div><div class="form-field"><label for="loyaltyExpiryDays">Vigencia de puntos (días)</label><input id="loyaltyExpiryDays" type="number" min="0" max="0" step="1" value="0" readonly aria-readonly="true"><small>La caducidad automática aún no está habilitada; los puntos no vencen en esta versión.</small></div><button type="submit" class="cyan-button">Guardar reglas</button></form>
          </div>
          <div class="white-card loyalty-rewards-card"><div class="card-heading"><div><span class="section-eyebrow">CATÁLOGO DE RECOMPENSAS</span><h2>Recompensas</h2><p>Los vales se emiten una sola vez y pueden marcarse usados o cancelarse desde el historial.</p></div></div><div id="loyaltyRewardsGrid" class="loyalty-rewards-grid"><div class="manager-loading">Cargando recompensas…</div></div></div>
          <div class="white-card loyalty-accounts-card"><div class="card-heading"><div><span class="section-eyebrow">CARTERA DE CLIENTES</span><h2>Saldos y puntos</h2><p>Busca una ficha para ajustar puntos o emitir una recompensa.</p></div></div><label class="menu-search"><span aria-hidden="true">⌕</span><input id="loyaltySearch" type="search" placeholder="Buscar por nombre, código, correo o teléfono…" aria-label="Buscar cuentas de fidelización"><kbd>⌕</kbd></label><div id="loyaltyAccountsList" class="loyalty-accounts-list"><div class="manager-loading">Cargando saldos…</div></div></div>
          <div class="white-card loyalty-redemptions-card"><div class="card-heading"><div><span class="section-eyebrow">VALES</span><h2>Canjes y recompensas emitidas</h2></div><span id="loyaltyRedemptionCount" class="counter-chip">Cargando…</span></div><div id="loyaltyRedemptionsList" class="loyalty-transactions-list"><div class="manager-loading">Cargando canjes…</div></div></div>
          <div class="white-card loyalty-transactions-card"><div class="card-heading"><div><span class="section-eyebrow">AUDITORÍA</span><h2>Últimos movimientos de puntos</h2></div><span id="loyaltyTransactionCount" class="counter-chip">Cargando…</span></div><div id="loyaltyTransactionsList" class="loyalty-transactions-list"><div class="manager-loading">Cargando movimientos…</div></div></div>
          <p class="operations-footnote">Los puntos se calculan sobre subtotal menos descuentos; el delivery no acumula puntos. Si un cliente ya canjeó puntos y luego se reembolsa la compra que los generó, el saldo puede quedar temporalmente negativo hasta compensarse con futuras compras.</p>
        </section>

        <!-- RESEÑAS Y CALIFICACIONES: todas las reseñas requieren una compra entregada y pagada. -->
        <section id="view-reviews" class="app-view" aria-labelledby="reviewsTitle">
          <div class="screen-heading">
            <div><span class="section-eyebrow">EXPERIENCIA DEL CLIENTE</span><h1 id="reviewsTitle">Reseñas y calificaciones</h1><p>Modera comentarios de compras verificadas y controla lo que aparece en la tienda.</p></div>
            <div class="heading-actions"><button type="button" class="secondary-button heading-button" data-go="menu">← Menú</button><button type="button" class="cyan-button heading-button" id="refreshReviewsButton">↻ Actualizar</button></div>
          </div>
          <div id="reviewsSummary" class="operations-summary reviews-summary"><article class="operations-metric"><span class="operations-metric-icon metric-amber">★</span><div><small>Pendientes</small><strong>—</strong></div></article><article class="operations-metric"><span class="operations-metric-icon metric-green">✓</span><div><small>Aprobadas</small><strong>—</strong></div></article><article class="operations-metric"><span class="operations-metric-icon metric-red">×</span><div><small>Rechazadas</small><strong>—</strong></div></article></div>
          <div class="white-card review-admin-card">
            <div class="card-heading"><div><span class="section-eyebrow">MODERACIÓN</span><h2>Opiniones de clientes</h2><p>Solo se aceptan reseñas asociadas a un pedido entregado, pagado y al producto comprado.</p></div><span id="adminReviewsCount" class="counter-chip">Cargando…</span></div>
            <div class="filter-row review-admin-filters" id="reviewAdminFilters" aria-label="Filtrar reseñas"><button type="button" class="filter-chip is-selected" data-review-status="pending">Pendientes</button><button type="button" class="filter-chip" data-review-status="approved">Aprobadas</button><button type="button" class="filter-chip" data-review-status="rejected">Rechazadas</button><button type="button" class="filter-chip" data-review-status="all">Todas</button></div>
            <div id="adminReviewsList" class="admin-reviews-list"><div class="manager-loading">Cargando reseñas…</div></div>
          </div>
          <p class="operations-footnote">Las opiniones aprobadas se publican en la tienda online. Rechazar una reseña la oculta al público, pero conserva el registro para auditoría.</p>
        </section>

        <!-- FACTURACIÓN Y COMPROBANTES INTERNOS -->
        <section id="view-billing" class="app-view" aria-labelledby="billingTitle">
          <div class="screen-heading">
            <div><span class="section-eyebrow">VENTAS · DOCUMENTOS</span><h1 id="billingTitle">Facturación y comprobantes</h1><p>Emite y consulta comprobantes internos de pedidos pagados.</p></div>
            <div class="heading-actions"><button type="button" class="secondary-button heading-button" data-go="orders">← Pedidos</button><button type="button" class="cyan-button heading-button" id="refreshBillingButton">↻ Actualizar</button></div>
          </div>
          <div class="billing-disclaimer white-card"><span class="billing-disclaimer-icon">i</span><div><strong>Documento de control interno</strong><p>Estos comprobantes y sus cálculos referenciales de IGV no son comprobantes electrónicos autorizados por SUNAT. Para facturación electrónica legal se requiere integrar un proveedor autorizado y completar la configuración tributaria.</p></div></div>
          <div id="issueInvoiceCard" class="white-card billing-form-card">
            <div class="card-heading"><div><span class="section-eyebrow">NUEVO COMPROBANTE</span><h2>Emitir para un pedido pagado</h2><p>Cada pedido puede tener un solo comprobante interno.</p></div></div>
            <form id="invoiceIssueForm" class="billing-form">
              <div class="form-field billing-field-full"><label for="invoiceOrderSelect">Pedido pagado *</label><select id="invoiceOrderSelect" required><option value="">Cargando pedidos pagados…</option></select></div>
              <div class="form-field"><label for="invoiceDocumentType">Tipo de documento *</label><select id="invoiceDocumentType" required><option value="boleta">Boleta interna</option><option value="factura">Factura interna</option></select></div>
              <div class="form-field"><label for="invoiceBuyerName">Nombre o razón social *</label><input id="invoiceBuyerName" maxlength="160" required placeholder="Nombre del comprador"></div>
              <div class="form-field"><label for="invoiceBuyerDocType">Tipo de documento</label><select id="invoiceBuyerDocType"><option value="Sin documento">Sin documento</option><option value="DNI">DNI</option><option value="RUC">RUC</option><option value="CE">Carné de extranjería</option><option value="Pasaporte">Pasaporte</option></select></div>
              <div class="form-field"><label for="invoiceBuyerDoc">Número de documento</label><input id="invoiceBuyerDoc" maxlength="20" placeholder="DNI o RUC"></div>
              <div class="form-field billing-field-full"><label for="invoiceBuyerAddress">Dirección del comprador</label><input id="invoiceBuyerAddress" maxlength="255" placeholder="Dirección (opcional)"></div>
              <div class="form-field billing-field-full"><label for="invoiceNotes">Notas internas</label><input id="invoiceNotes" maxlength="500" placeholder="Observaciones (opcional)"></div>
              <div class="billing-form-footer"><small>La información se guarda en MySQL y se conserva una copia de los productos del pedido.</small><button type="submit" class="cyan-button" id="issueInvoiceSubmit">Emitir comprobante interno</button></div>
              <p id="invoiceFormFeedback" class="billing-feedback" aria-live="polite"></p>
            </form>
          </div>
          <div class="white-card billing-list-card"><div class="card-heading"><div><span class="section-eyebrow">HISTORIAL</span><h2>Comprobantes emitidos</h2><p>Consulta, imprime o anula el registro interno.</p></div><span id="invoiceCount" class="counter-chip">Cargando…</span></div><div id="invoicesList" class="billing-invoices-list"><div class="manager-loading">Cargando comprobantes…</div></div></div>
        </section>

        <!-- REPORTES DEL NEGOCIO -->
        <section id="view-reports" class="app-view" aria-labelledby="reportsTitle">
          <div class="screen-heading"><div><span class="section-eyebrow">ANÁLISIS · OPERACIONES</span><h1 id="reportsTitle">Reportes del restaurante</h1><p>Resumen de cobros, pedidos, métodos de pago y productos.</p></div><div class="heading-actions"><button type="button" class="secondary-button heading-button" data-go="orders">← Pedidos</button><button type="button" class="secondary-button heading-button is-hidden" id="analyticsManagementButton">Analítica</button><button type="button" class="secondary-button heading-button is-hidden" id="auditManagementButton">Auditoría</button><button type="button" class="secondary-button heading-button is-hidden" id="backupsManagementButton">Backups</button><button type="button" class="cyan-button heading-button" id="exportReportsButton">↓ Exportar CSV</button></div></div>
          <div class="white-card report-filter-card"><form id="reportFilterForm" class="report-filter-form"><div class="form-field"><label for="reportFrom">Desde</label><input type="date" id="reportFrom" required></div><div class="form-field"><label for="reportTo">Hasta</label><input type="date" id="reportTo" required></div><button type="submit" class="cyan-button">Aplicar período</button></form><p id="reportPeriodLabel" class="report-period-label">Selecciona un período para consultar datos reales de MySQL.</p></div>
          <div id="reportSummary" class="report-summary-grid"><article class="white-card report-kpi"><small>Cobros netos</small><strong>—</strong><span>Pagos menos reembolsos</span></article><article class="white-card report-kpi"><small>Pedidos pagados</small><strong>—</strong><span>Según fecha de cobro</span></article><article class="white-card report-kpi"><small>Ticket promedio</small><strong>—</strong><span>Pedido pagado</span></article><article class="white-card report-kpi"><small>Comprobantes emitidos</small><strong>—</strong><span>Registro interno</span></article></div>
          <div class="report-layout-grid">
            <article class="white-card report-panel report-daily-panel"><div class="card-heading"><div><span class="section-eyebrow">TENDENCIA</span><h2>Cobros netos por día</h2></div></div><div id="reportDailyChart" class="report-daily-chart"><p class="catalog-note">Carga un período para ver el resumen.</p></div></article>
            <article class="white-card report-panel"><div class="card-heading"><div><span class="section-eyebrow">COBROS</span><h2>Por método de pago</h2></div></div><div id="reportPaymentMethods" class="report-data-list"><p class="catalog-note">Sin datos para mostrar.</p></div></article>
            <article class="white-card report-panel"><div class="card-heading"><div><span class="section-eyebrow">PRODUCTOS</span><h2>Más vendidos</h2></div></div><div id="reportTopProducts" class="report-data-list"><p class="catalog-note">Sin datos para mostrar.</p></div></article>
            <article class="white-card report-panel"><div class="card-heading"><div><span class="section-eyebrow">OPERACIÓN</span><h2>Pedidos por estado</h2></div></div><div id="reportOrderStatuses" class="report-data-list"><p class="catalog-note">Sin datos para mostrar.</p></div></article>
          </div>
          <p class="operations-footnote">Reportes calculados desde pagos, pedidos e ítems registrados en MySQL. Los valores son herramientas de gestión interna; no sustituyen libros contables, declaraciones tributarias ni reportes oficiales de SUNAT.</p>
        </section>

        <!-- ANALÍTICA AVANZADA -->
        <section id="view-analytics" class="app-view" aria-labelledby="analyticsTitle">
          <div class="screen-heading"><div><span class="section-eyebrow">ANÁLISIS · INTELIGENCIA COMERCIAL</span><h1 id="analyticsTitle">Analítica avanzada</h1><p>Tendencias, clientes, horarios pico y rentabilidad estimada desde MySQL.</p></div><div class="heading-actions"><button type="button" class="secondary-button heading-button" data-go="reports">← Reportes</button><button type="button" class="cyan-button heading-button" id="refreshAnalyticsButton">↻ Actualizar</button></div></div>
          <div class="white-card report-filter-card"><form id="analyticsFilterForm" class="report-filter-form"><div class="form-field"><label for="analyticsFrom">Desde</label><input type="date" id="analyticsFrom" required></div><div class="form-field"><label for="analyticsTo">Hasta</label><input type="date" id="analyticsTo" required></div><button type="submit" class="cyan-button">Analizar período</button></form><p id="analyticsPeriodLabel" class="report-period-label">Selecciona un período para cargar analítica.</p></div>
          <div class="white-card analytics-preferences"><div class="card-heading"><div><span class="section-eyebrow">PANEL PERSONALIZABLE</span><h2>Indicadores visibles</h2><p>Elige las métricas que deseas destacar.</p></div><button type="button" class="secondary-button" id="saveAnalyticsPreferences">Guardar selección</button></div><div id="analyticsMetricPrefs" class="analytics-metric-prefs"><label><input type="checkbox" value="net_sales" checked> Cobros netos</label><label><input type="checkbox" value="paid_orders" checked> Pedidos pagados</label><label><input type="checkbox" value="average_ticket" checked> Ticket promedio</label><label><input type="checkbox" value="customers" checked> Clientes</label><label><input type="checkbox" value="conversion" checked> Conversión web</label><label><input type="checkbox" value="estimated_margin" checked> Margen estimado</label><label><input type="checkbox" value="cancellations" checked> Cancelaciones</label></div></div>
          <div id="analyticsSummary" class="report-summary-grid analytics-summary-grid"><article class="white-card report-kpi"><small>Cobros netos</small><strong>—</strong><span>Pagos menos reembolsos</span></article></div>
          <div class="report-layout-grid analytics-report-grid">
            <article class="white-card report-panel report-daily-panel"><div class="card-heading"><div><span class="section-eyebrow">TENDENCIA</span><h2>Cobros netos por día</h2></div><span id="analyticsComparison" class="counter-chip">—</span></div><div id="analyticsDailyChart" class="report-daily-chart"><p class="catalog-note">Carga un período.</p></div></article>
            <article class="white-card report-panel"><div class="card-heading"><div><span class="section-eyebrow">CLIENTES</span><h2>Comportamiento de clientes</h2></div></div><div id="analyticsCustomerBehavior" class="report-data-list"><p class="catalog-note">Sin datos.</p></div><div class="analytics-forecast" id="analyticsForecast">Pronóstico estimado de 7 días: —</div></article>
            <article class="white-card report-panel"><div class="card-heading"><div><span class="section-eyebrow">RENTABILIDAD</span><h2>Margen estimado por producto</h2></div></div><div id="analyticsProfitability" class="report-data-list"><p class="catalog-note">Sin datos.</p></div><p class="analytics-disclaimer">El costo se calcula con la receta y el costo actual de ingredientes. Sin receta, el margen no puede estimarse.</p></article>
            <article class="white-card report-panel analytics-peak-panel"><div class="card-heading"><div><span class="section-eyebrow">OPERACIÓN</span><h2>Horarios pico</h2><p>Pedidos pagados por día y hora de Perú.</p></div></div><div id="analyticsPeakHours" class="analytics-peak-hours"><p class="catalog-note">Sin datos de horarios.</p></div></article>
          </div>
          <p class="operations-footnote">Las predicciones son una referencia de tendencia calculada por promedio; no son un modelo estadístico predictivo ni garantía de ventas futuras.</p>
        </section>

        <!-- AUDITORÍA Y ALERTAS -->
        <section id="view-audit" class="app-view" aria-labelledby="auditTitle">
          <div class="screen-heading"><div><span class="section-eyebrow">ADMINISTRACIÓN · SEGURIDAD</span><h1 id="auditTitle">Auditoría y actividad</h1><p>Consulta quién hizo qué, cuándo y desde qué dirección IP.</p></div><div class="heading-actions"><button type="button" class="secondary-button heading-button" data-go="reports">← Reportes</button><button type="button" class="cyan-button heading-button" id="exportAuditButton">↓ Exportar CSV</button></div></div>
          <div id="auditAlerts" class="analytics-alert-list"><div class="manager-loading">Comprobando actividad sospechosa…</div></div>
          <div class="white-card report-filter-card"><form id="auditFilterForm" class="audit-filter-form"><div class="form-field"><label for="auditFrom">Desde</label><input type="date" id="auditFrom"></div><div class="form-field"><label for="auditTo">Hasta</label><input type="date" id="auditTo"></div><div class="form-field"><label for="auditEvent">Evento</label><input type="text" id="auditEvent" placeholder="auth.login, orders…" maxlength="100"></div><div class="form-field"><label for="auditActor">ID de usuario</label><input type="number" min="1" id="auditActor" placeholder="Todos"></div><div class="form-field audit-search-field"><label for="auditQuery">Buscar en detalles, usuario o IP</label><input type="search" id="auditQuery" placeholder="Buscar actividad…" maxlength="100"></div><button type="submit" class="cyan-button">Filtrar</button></form><p id="auditCountLabel" class="report-period-label">Cargando registros…</p></div>
          <div class="white-card audit-list-card"><div class="card-heading"><div><span class="section-eyebrow">TRAZABILIDAD</span><h2>Registro de acciones</h2><p>Los logs detallados pueden contener información administrativa; exporta solo cuando sea necesario.</p></div><button type="button" class="secondary-button" id="refreshAuditButton">↻ Actualizar</button></div><div id="auditLogList" class="audit-log-list"><div class="manager-loading">Cargando auditoría…</div></div><div class="audit-pagination"><button type="button" class="secondary-button" id="auditPrevPage" disabled>← Anterior</button><span id="auditPageLabel">Página 1</span><button type="button" class="secondary-button" id="auditNextPage" disabled>Siguiente →</button></div></div>
          <div class="white-card audit-retention-card"><div class="card-heading"><div><span class="section-eyebrow">POLÍTICA DE RETENCIÓN</span><h2>Retención y alertas</h2><p>La limpieza elimina logs antiguos de forma permanente y marca backups antiguos como vencidos.</p></div></div><form id="retentionSettingsForm" class="retention-settings-form"><div class="form-field"><label for="auditRetentionDays">Auditoría (días)</label><input id="auditRetentionDays" type="number" min="30" max="3650" required value="365"></div><div class="form-field"><label for="backupRetentionDays">Backups (días)</label><input id="backupRetentionDays" type="number" min="7" max="3650" required value="90"></div><div class="form-field"><label for="suspiciousThreshold">Intentos fallidos por IP / 15 min</label><input id="suspiciousThreshold" type="number" min="3" max="100" required value="5"></div><button type="submit" class="cyan-button">Guardar política</button><button type="button" class="secondary-button" id="applyRetentionButton">Aplicar retención ahora</button></form></div>
        </section>

        <!-- COPIAS CIFRADAS Y RECUPERACIÓN -->
        <section id="view-backups" class="app-view" aria-labelledby="backupsTitle">
          <div class="screen-heading"><div><span class="section-eyebrow">ADMINISTRACIÓN · CONTINUIDAD</span><h1 id="backupsTitle">Copias de seguridad</h1><p>Backups cifrados, validación de integridad e historial de recuperación.</p></div><div class="heading-actions"><button type="button" class="secondary-button heading-button" data-go="audit">Auditoría</button><button type="button" class="cyan-button heading-button" id="createBackupButton">＋ Crear copia ahora</button></div></div>
          <div class="billing-disclaimer white-card"><span class="billing-disclaimer-icon">i</span><div><strong>Cifrado y custodia de la clave</strong><p>Los archivos se cifran con AES-256-GCM. Configura una clave de 64 caracteres hexadecimales en config/backup.php o DESEGO_BACKUP_KEY. Si pierdes esa clave, los backups no se podrán descifrar.</p></div></div>
          <div class="backup-status-grid" id="backupStatusSummary"><article class="white-card report-kpi"><small>Copias completas</small><strong>—</strong><span>Historial disponible</span></article><article class="white-card report-kpi"><small>Última copia</small><strong>—</strong><span>Fecha UTC</span></article><article class="white-card report-kpi"><small>Almacenamiento secundario</small><strong>—</strong><span>Configurable</span></article></div>
          <div class="white-card billing-list-card"><div class="card-heading"><div><span class="section-eyebrow">HISTORIAL</span><h2>Backups guardados</h2><p>Verifica el hash y el descifrado antes de conservar una copia como válida.</p></div><button type="button" class="secondary-button" id="refreshBackupsButton">↻ Actualizar</button></div><div id="backupList" class="backup-list"><div class="manager-loading">Cargando copias…</div></div></div>
          <div class="white-card backup-recovery-note"><div class="card-heading"><div><span class="section-eyebrow">RECUPERACIÓN</span><h2>Restaurar una copia</h2></div></div><p>Por seguridad, la restauración es una operación de consola y requiere confirmación explícita. Primero crea una copia previa; luego usa <code>scripts/restore_backup.php ID --confirm=RESTORE-DESEGO</code> en el servidor. Esta operación reemplaza los datos actuales por el snapshot elegido.</p><p>La recuperación a un minuto exacto requiere activar y custodiar los binary logs de MySQL. No se habilita restauración destructiva desde el navegador.</p></div>
          <p class="operations-footnote">El backup diario se ejecuta con cron/Task Scheduler invocando scripts/backup_daily.php. El almacenamiento redundante externo solo se activa si configuras DESEGO_BACKUP_REDUNDANT_DIR a una ubicación distinta.</p>
        </section>

        <!-- SUCURSALES -->
        <section id="view-branches" class="app-view" aria-labelledby="branchesTitle">
          <div class="screen-heading"><div><span class="section-eyebrow">ORGANIZACIÓN</span><h1 id="branchesTitle">Sucursales</h1><p>Gestiona sedes, horarios y datos de contacto del restaurante.</p></div><div class="heading-actions"><button type="button" class="secondary-button heading-button" data-go="profile">← Perfil</button><button type="button" class="cyan-button heading-button" id="refreshBranchesButton">↻ Actualizar</button></div></div>
          <div id="branchSummary" class="report-summary-grid workforce-summary"><article class="white-card report-kpi"><small>Sucursales activas</small><strong>—</strong><span>Ubicaciones operativas</span></article></div>
          <div id="branchFormCard" class="white-card purchasing-form-card">
            <div class="card-heading"><div><span class="section-eyebrow">CONFIGURACIÓN DE SEDE</span><h2 id="branchFormTitle">Registrar sucursal</h2><p>Define datos de contacto, ubicación y horario de atención.</p></div></div>
            <form id="branchForm" class="purchasing-grid-form workforce-form">
              <input type="hidden" id="branchId">
              <div class="form-field"><label for="branchCode">Código *</label><input id="branchCode" maxlength="30" required placeholder="DES-CENTRO"></div>
              <div class="form-field"><label for="branchName">Nombre de la sucursal *</label><input id="branchName" maxlength="150" required placeholder="DESEGO Sede Centro"></div>
              <div class="form-field"><label for="branchTaxId">RUC</label><input id="branchTaxId" maxlength="20" placeholder="RUC de la sede"></div>
              <div class="form-field"><label for="branchPhone">Teléfono</label><input id="branchPhone" maxlength="40" placeholder="01 555 5555"></div>
              <div class="form-field"><label for="branchEmail">Correo</label><input id="branchEmail" type="email" maxlength="190" placeholder="sede@desego.pe"></div>
              <div class="form-field"><label for="branchDistrict">Distrito</label><input id="branchDistrict" maxlength="100" placeholder="Ate"></div>
              <div class="form-field"><label for="branchProvince">Provincia</label><input id="branchProvince" maxlength="100" placeholder="Lima"></div>
              <div class="form-field"><label for="branchRegion">Región</label><input id="branchRegion" maxlength="100" placeholder="Lima"></div>
              <div class="form-field form-field-wide"><label for="branchAddress">Dirección</label><input id="branchAddress" maxlength="255" placeholder="Dirección de la sede"></div>
              <div class="form-field"><label for="branchOpeningTime">Hora de apertura</label><input id="branchOpeningTime" type="time"></div>
              <div class="form-field"><label for="branchClosingTime">Hora de cierre</label><input id="branchClosingTime" type="time"></div>
              <label class="workforce-check form-field-wide"><input type="checkbox" id="branchIsMain"><span>Marcar como sucursal principal</span></label>
              <div class="purchasing-form-actions form-field-wide"><button type="button" id="branchResetButton" class="secondary-button">Limpiar</button><button type="submit" class="cyan-button">Guardar sucursal</button></div>
            </form>
          </div>
          <div class="white-card purchasing-list-card"><div class="card-heading"><div><span class="section-eyebrow">DIRECTORIO</span><h2>Sucursales registradas</h2><p>Las sedes con personal activo o turnos futuros no pueden desactivarse hasta reasignarlos.</p></div><span id="branchCount" class="counter-chip">Cargando…</span></div><label class="menu-search"><span aria-hidden="true">⌕</span><input id="branchSearch" type="search" placeholder="Buscar por código, nombre o ubicación…" aria-label="Buscar sucursales"></label><div id="branchList" class="purchasing-list"><div class="manager-loading">Cargando sucursales…</div></div></div>
        </section>

        <!-- PERSONAL -->
        <section id="view-employees" class="app-view" aria-labelledby="employeesTitle">
          <div class="screen-heading"><div><span class="section-eyebrow">RECURSOS HUMANOS</span><h1 id="employeesTitle">Personal</h1><p>Fichas de empleados, puestos, cuenta vinculada y sucursal base.</p></div><div class="heading-actions"><button type="button" class="secondary-button heading-button" data-go="shifts">▦ Turnos</button><button type="button" class="secondary-button heading-button" data-go="branches">⌂ Sucursales</button><button type="button" class="secondary-button heading-button" data-go="profile">← Perfil</button><button type="button" class="cyan-button heading-button" id="refreshEmployeesButton">↻ Actualizar</button></div></div>
          <div id="employeeSummary" class="report-summary-grid workforce-summary"><article class="white-card report-kpi"><small>Personal activo</small><strong>—</strong><span>Fichas de empleados</span></article></div>
          <div id="employeeFormCard" class="white-card purchasing-form-card">
            <div class="card-heading"><div><span class="section-eyebrow">FICHA DEL COLABORADOR</span><h2 id="employeeFormTitle">Registrar empleado</h2><p>La cuenta del sistema es opcional; si se vincula, se podrá limitar la asistencia a su propia ficha.</p></div></div>
            <form id="employeeForm" class="purchasing-grid-form workforce-form">
              <input type="hidden" id="employeeId">
              <div class="form-field"><label for="employeeCode">Código de empleado *</label><input id="employeeCode" maxlength="30" required placeholder="EMP-001"></div>
              <div class="form-field"><label for="employeeName">Nombre completo *</label><input id="employeeName" maxlength="150" required placeholder="Nombre y apellidos"></div>
              <div class="form-field"><label for="employeeJobTitle">Puesto *</label><input id="employeeJobTitle" maxlength="100" required placeholder="Cajero, cocinero, mozo…"></div>
              <div class="form-field"><label for="employeeBranch">Sucursal base</label><select id="employeeBranch"><option value="">Sin asignar</option></select></div>
              <div class="form-field"><label for="employeeUser">Cuenta DESEGO vinculada</label><select id="employeeUser"><option value="">Sin cuenta vinculada</option></select></div>
              <div class="form-field"><label for="employeePhone">Teléfono</label><input id="employeePhone" maxlength="40" placeholder="Teléfono de contacto"></div>
              <div class="form-field"><label for="employeeEmail">Correo</label><input id="employeeEmail" type="email" maxlength="190" placeholder="empleado@desego.pe"></div>
              <div class="form-field"><label for="employeeType">Tipo de contrato</label><select id="employeeType"><option value="full_time">Tiempo completo</option><option value="part_time">Medio tiempo</option><option value="temporary">Temporal</option><option value="contractor">Servicios / contratista</option></select></div>
              <div class="form-field"><label for="employeeHireDate">Fecha de contratación</label><input id="employeeHireDate" type="date"></div>
              <div class="form-field" id="employeeRateField"><label for="employeeHourlyRate">Tarifa por hora (S/)</label><input id="employeeHourlyRate" type="number" min="0" max="1000000" step="0.01" placeholder="0.00"></div>
              <div class="form-field"><label for="employeeStatus">Estado</label><select id="employeeStatus"><option value="active">Activo</option><option value="inactive">Inactivo</option></select></div>
              <div class="form-field form-field-wide"><label for="employeeNotes">Notas internas</label><textarea id="employeeNotes" rows="2" maxlength="1000" placeholder="Observaciones administrativas…"></textarea></div>
              <div class="purchasing-form-actions form-field-wide"><button type="button" id="employeeResetButton" class="secondary-button">Limpiar</button><button type="submit" class="cyan-button">Guardar empleado</button></div>
            </form>
          </div>
          <div class="white-card purchasing-list-card"><div class="card-heading"><div><span class="section-eyebrow">DIRECTORIO DE PERSONAL</span><h2>Empleados registrados</h2><p>Los empleados vinculados a una cuenta pueden registrar su propia asistencia si tienen permiso.</p></div><span id="employeeCount" class="counter-chip">Cargando…</span></div><label class="menu-search"><span aria-hidden="true">⌕</span><input id="employeeSearch" type="search" placeholder="Buscar por nombre, código, puesto o sucursal…" aria-label="Buscar personal"></label><div id="employeeList" class="purchasing-list"><div class="manager-loading">Cargando personal…</div></div></div>
        </section>

        <!-- TURNOS Y ASISTENCIA -->
        <section id="view-shifts" class="app-view" aria-labelledby="shiftsTitle">
          <div class="screen-heading"><div><span class="section-eyebrow">PLANIFICACIÓN · ASISTENCIA</span><h1 id="shiftsTitle">Turnos y asistencia</h1><p>Programa horarios, controla entradas y salidas y revisa turnos por sede.</p></div><div class="heading-actions"><button type="button" class="secondary-button heading-button" data-go="employees">♙ Personal</button><button type="button" class="secondary-button heading-button" data-go="branches">⌂ Sucursales</button><button type="button" class="secondary-button heading-button" data-go="profile">← Perfil</button><button type="button" class="cyan-button heading-button" id="refreshShiftsButton">↻ Actualizar</button></div></div>
          <div id="shiftSummary" class="report-summary-grid workforce-summary"><article class="white-card report-kpi"><small>Turnos programados</small><strong>—</strong><span>Periodo seleccionado</span></article></div>
          <div class="white-card workforce-filter-card"><form id="shiftFilterForm" class="workforce-filter-form"><div class="form-field"><label for="shiftFilterFrom">Desde</label><input type="date" id="shiftFilterFrom" required></div><div class="form-field"><label for="shiftFilterTo">Hasta</label><input type="date" id="shiftFilterTo" required></div><div class="form-field"><label for="shiftFilterBranch">Sucursal</label><select id="shiftFilterBranch"><option value="">Todas las sucursales</option></select></div><button type="submit" class="cyan-button">Filtrar</button></form></div>
          <div id="shiftFormCard" class="white-card purchasing-form-card">
            <div class="card-heading"><div><span class="section-eyebrow">PROGRAMACIÓN</span><h2 id="shiftFormTitle">Programar turno</h2><p>Se rechazará cualquier horario que se cruce con otro turno no cancelado del mismo empleado.</p></div></div>
            <form id="shiftForm" class="purchasing-grid-form workforce-form">
              <input type="hidden" id="shiftId">
              <div class="form-field"><label for="shiftEmployee">Empleado *</label><select id="shiftEmployee" required><option value="">Selecciona un empleado…</option></select></div>
              <div class="form-field"><label for="shiftBranch">Sucursal *</label><select id="shiftBranch" required><option value="">Selecciona una sucursal…</option></select></div>
              <div class="form-field"><label for="shiftDate">Fecha *</label><input type="date" id="shiftDate" required></div>
              <div class="form-field"><label for="shiftStart">Hora de inicio *</label><input type="time" id="shiftStart" value="09:00" required></div>
              <div class="form-field"><label for="shiftEnd">Hora de fin *</label><input type="time" id="shiftEnd" value="17:00" required></div>
              <div class="form-field"><label for="shiftBreak">Descanso (minutos)</label><input id="shiftBreak" type="number" min="0" max="240" step="5" value="30" required></div>
              <div class="form-field form-field-wide"><label for="shiftNotes">Notas</label><textarea id="shiftNotes" rows="2" maxlength="1000" placeholder="Área, entrega de turno, indicaciones…"></textarea></div>
              <div class="purchasing-form-actions form-field-wide"><button type="button" id="shiftResetButton" class="secondary-button">Limpiar</button><button type="submit" class="cyan-button">Guardar turno</button></div>
            </form>
          </div>
          <div class="white-card purchasing-list-card"><div class="card-heading"><div><span class="section-eyebrow">AGENDA</span><h2>Programación de turnos</h2><p>Las entradas y salidas quedan registradas con fecha y hora del servidor.</p></div><span id="shiftCount" class="counter-chip">Cargando…</span></div><div id="shiftList" class="purchasing-list"><div class="manager-loading">Cargando turnos…</div></div></div>
          <div class="white-card attendance-history-card"><div class="card-heading"><div><span class="section-eyebrow">CONTROL HORARIO</span><h2>Registro de asistencia</h2><p>La bitácora de entradas y salidas queda en la base de datos; los administradores pueden consultarla desde Auditoría.</p></div></div><p class="operations-footnote">La versión actual registra hora real de entrada y salida, pero no calcula horas extras, descansos legales ni planilla salarial.</p></div>
        </section>

        <!-- PASO 15: NOTIFICACIONES -->
        <section id="view-notifications" class="app-view" aria-labelledby="notificationsTitle">
          <div class="screen-heading"><div><span class="section-eyebrow">COMUNICACIÓN INTERNA</span><h1 id="notificationsTitle">Notificaciones</h1><p>Avisos personales y comunicados del restaurante.</p></div><div class="heading-actions"><button type="button" class="secondary-button heading-button" data-go="profile">← Perfil</button><button type="button" class="cyan-button heading-button" id="refreshNotificationsButton">↻ Actualizar</button><button type="button" class="secondary-button heading-button" id="markAllNotificationsReadButton">Marcar todas como leídas</button></div></div>
          <div id="notificationsSummary" class="report-summary-grid"><article class="white-card report-kpi"><small>Sin leer</small><strong>—</strong><span>Notificaciones personales y globales</span></article></div>
          <div id="broadcastNotificationCard" class="white-card communications-form-card is-hidden"><div class="card-heading"><div><span class="section-eyebrow">ADMINISTRACIÓN</span><h2>Publicar aviso</h2><p>El aviso será visible para los usuarios activos.</p></div></div><form id="broadcastNotificationForm" class="communications-form"><div class="form-field"><label for="broadcastTitle">Título</label><input id="broadcastTitle" maxlength="150" required></div><div class="form-field"><label for="broadcastCategory">Tipo</label><select id="broadcastCategory"><option value="info">Información</option><option value="success">Éxito</option><option value="warning">Advertencia</option><option value="error">Alerta</option></select></div><div class="form-field communications-wide"><label for="broadcastMessage">Mensaje</label><textarea id="broadcastMessage" rows="3" maxlength="1000" required></textarea></div><button class="cyan-button" type="submit">Publicar aviso</button></form></div>
          <div class="white-card communications-list-card"><div class="card-heading"><div><span class="section-eyebrow">BANDEJA</span><h2>Tus notificaciones</h2><p>Los avisos leídos se conservan como historial.</p></div><span id="notificationCount" class="counter-chip">Cargando…</span></div><div id="notificationList" class="communications-list"><div class="manager-loading">Cargando notificaciones…</div></div></div>
        </section>

        <!-- PASO 15: SOPORTE -->
        <section id="view-support" class="app-view" aria-labelledby="supportTitle">
          <div class="screen-heading"><div><span class="section-eyebrow">AYUDA Y SEGUIMIENTO</span><h1 id="supportTitle">Soporte</h1><p>Registra consultas, incidencias y su resolución.</p></div><div class="heading-actions"><button type="button" class="secondary-button heading-button" data-go="profile">← Perfil</button><button type="button" class="cyan-button heading-button" id="refreshSupportButton">↻ Actualizar</button></div></div>
          <div class="communications-grid">
            <div class="white-card communications-form-card"><div class="card-heading"><div><span class="section-eyebrow">NUEVO TICKET</span><h2>Crear solicitud</h2><p>Describe el problema para dejar constancia y hacer seguimiento.</p></div></div><form id="supportCreateForm" class="communications-form"><div class="form-field communications-wide"><label for="supportSubject">Asunto</label><input id="supportSubject" maxlength="180" required placeholder="Ej. No puedo registrar una venta"></div><div class="form-field"><label for="supportCategory">Categoría</label><select id="supportCategory"><option value="technical">Problema técnico</option><option value="orders">Pedidos y ventas</option><option value="inventory">Inventario</option><option value="billing">Pagos y comprobantes</option><option value="access">Acceso y permisos</option><option value="other">Otro</option></select></div><div class="form-field"><label for="supportPriority">Prioridad</label><select id="supportPriority"><option value="low">Baja</option><option value="normal" selected>Normal</option><option value="high">Alta</option><option value="urgent">Urgente</option></select></div><div class="form-field communications-wide"><label for="supportMessage">Descripción</label><textarea id="supportMessage" rows="5" maxlength="5000" required placeholder="Indica qué ocurrió y qué esperabas que pasara…"></textarea></div><button type="submit" class="cyan-button">Crear ticket</button></form></div>
            <div class="white-card communications-list-card"><div class="card-heading"><div><span class="section-eyebrow">SEGUIMIENTO</span><h2>Tickets</h2><p>Selecciona un ticket para ver el hilo y responder.</p></div><button type="button" class="secondary-button" id="refreshTicketsButton">↻</button></div><div class="filter-row support-filter-row" id="supportStatusFilters"><button class="filter-chip is-selected" type="button" data-support-filter="all">Todos</button><button class="filter-chip" type="button" data-support-filter="open">Abiertos</button><button class="filter-chip" type="button" data-support-filter="in_progress">En proceso</button><button class="filter-chip" type="button" data-support-filter="resolved">Resueltos</button><button class="filter-chip" type="button" data-support-filter="closed">Cerrados</button></div><div id="supportTicketList" class="communications-list"><div class="manager-loading">Cargando tickets…</div></div></div>
          </div>
          <div id="supportThreadCard" class="white-card communications-list-card is-hidden"><div class="card-heading"><div><span class="section-eyebrow">CONVERSACIÓN</span><h2 id="supportThreadTitle">Ticket</h2><p id="supportThreadMeta"></p></div><div class="heading-actions"><label id="supportAssignWrap" class="support-assign-wrap is-hidden">Asignar a<select id="supportAssignUser"></select></label><button type="button" class="secondary-button" id="supportStartButton">En proceso</button><button type="button" class="secondary-button" id="supportResolveButton">Resolver</button><button type="button" class="secondary-button" id="supportCloseButton">Cerrar</button></div></div><div id="supportThreadMessages" class="support-thread"></div><form id="supportReplyForm" class="communications-form"><input type="hidden" id="supportThreadId"><div class="form-field communications-wide"><label for="supportReplyMessage">Tu respuesta</label><textarea id="supportReplyMessage" rows="3" maxlength="5000" required></textarea></div><label id="supportInternalNoteWrap" class="communications-check is-hidden"><input type="checkbox" id="supportInternalNote"> Nota interna (solo personal autorizado)</label><button type="submit" class="cyan-button">Enviar respuesta</button></form></div>
        </section>

        <!-- PASO 15: INTEGRACIONES Y TOKENS API -->
        <section id="view-integrations" class="app-view" aria-labelledby="integrationsTitle">
          <div class="screen-heading"><div><span class="section-eyebrow">CONEXIONES EXTERNAS</span><h1 id="integrationsTitle">Integraciones y API</h1><p>Gestiona referencias de configuración sin almacenar claves secretas en MySQL.</p></div><div class="heading-actions"><button type="button" class="secondary-button heading-button" data-go="profile">← Perfil</button><button type="button" class="cyan-button heading-button" id="refreshIntegrationsButton">↻ Actualizar</button></div></div>
          <div class="integration-notice"><strong>Seguridad de credenciales</strong><p>Los secretos deben configurarse en variables de entorno del servidor o en un gestor de secretos. Esta pantalla guarda solo el nombre de la variable y metadatos, nunca la clave real.</p></div>
          <div id="integrationList" class="integration-grid"><div class="manager-loading">Cargando integraciones…</div></div>
          <div class="white-card communications-list-card"><div class="card-heading"><div><span class="section-eyebrow">API REST</span><h2>Tokens de acceso</h2><p>Genera credenciales con permisos de solo lectura. El valor íntegro se muestra una sola vez.</p></div></div>
            <form id="apiTokenForm" class="communications-form api-token-form"><div class="form-field"><label for="apiTokenName">Nombre del token</label><input id="apiTokenName" maxlength="120" required placeholder="Ej. Integración de reportes"></div><div class="form-field"><label for="apiTokenExpiry">Caducidad</label><select id="apiTokenExpiry"><option value="30">30 días</option><option value="90" selected>90 días</option><option value="180">180 días</option><option value="365">365 días</option></select></div><div class="form-field communications-wide"><span class="form-field-label">Alcances</span><label class="communications-check"><input type="checkbox" name="apiScope" value="products:read" checked> Leer productos</label><label class="communications-check"><input type="checkbox" name="apiScope" value="orders:read"> Leer pedidos (sin datos personales)</label></div><button type="submit" class="cyan-button">＋ Crear token</button></form>
            <div id="apiTokenList" class="communications-list"><div class="manager-loading">Cargando tokens…</div></div>
          </div>
          <div class="white-card communications-list-card"><div class="card-heading"><div><span class="section-eyebrow">REGISTRO</span><h2>Comprobaciones recientes</h2><p>Comprueba la configuración local. No se realizan cobros ni mensajes reales desde esta prueba.</p></div></div><div id="integrationEventsList" class="communications-list"></div></div>
          <div id="newApiTokenReveal" class="white-card token-reveal is-hidden"><h2>Token generado</h2><p>Copia esta credencial ahora y guárdala en un gestor de secretos. No volverá a mostrarse.</p><textarea id="newApiTokenValue" rows="2" readonly></textarea><div class="heading-actions"><button type="button" class="secondary-button" id="copyApiTokenButton">Copiar token</button><button type="button" class="cyan-button" id="hideApiTokenButton">Ya lo guardé</button></div><p class="operations-footnote">API de solo lectura: <code>api/external.php?resource=products</code> o <code>api/external.php?resource=orders</code>.</p></div>
        </section>

        <!-- PERFIL -->
        <section id="view-profile" class="app-view" aria-labelledby="profileTitle">
          <div class="screen-heading"><div><span class="section-eyebrow">TU ESPACIO</span><h1 id="profileTitle">Mi perfil</h1><p>Tu cuenta y preferencias.</p></div></div>
          <article class="white-card profile-card"><div class="profile-cover"><span class="cover-bubble cover-bubble-one"></span><span class="cover-bubble cover-bubble-two"></span></div><div class="profile-main"><div id="profileInitials" class="profile-avatar">JD</div><div class="profile-details"><h2 id="profileName">Juan Desego</h2><p id="profileEmail">admin@desego.com</p><span class="role-label"><i></i><span id="profileRole">Colaborador</span></span></div><button type="button" id="editProfileButton" class="edit-profile-button">Editar</button></div></article>
          <div class="profile-option-list"><button type="button" class="white-card profile-option" data-profile-option="account"><span class="option-icon option-blue">♙</span><span class="option-copy"><strong>Mi cuenta</strong><small>Información personal</small></span><span class="option-chevron">›</span></button><button type="button" class="white-card profile-option" data-profile-option="security"><span class="option-icon option-green">▣</span><span class="option-copy"><strong>Seguridad</strong><small>Claves y accesos</small></span><span class="option-chevron">›</span></button><button type="button" class="white-card profile-option" data-profile-option="settings"><span class="option-icon option-purple">⚙</span><span class="option-copy"><strong>Ajustes</strong><small>Notificaciones y moneda</small></span><span class="option-chevron">›</span></button><button type="button" id="analyticsProfileButton" data-admin-view="analytics" class="white-card profile-option is-hidden"><span class="option-icon option-blue">⌁</span><span class="option-copy"><strong>Analítica avanzada</strong><small>Tendencias y rentabilidad estimada</small></span><span class="option-chevron">›</span></button><button type="button" id="auditProfileButton" data-admin-view="audit" class="white-card profile-option is-hidden"><span class="option-icon option-green">✓</span><span class="option-copy"><strong>Auditoría</strong><small>Registro de actividad y alertas</small></span><span class="option-chevron">›</span></button><button type="button" id="backupsProfileButton" data-admin-view="backups" class="white-card profile-option is-hidden"><span class="option-icon option-purple">▣</span><span class="option-copy"><strong>Copias de seguridad</strong><small>Crear, verificar y administrar backups</small></span><span class="option-chevron">›</span></button><button type="button" id="branchesProfileButton" data-admin-view="branches" class="white-card profile-option is-hidden"><span class="option-icon option-blue">⌂</span><span class="option-copy"><strong>Sucursales</strong><small>Sedes, contactos y horarios de atención</small></span><span class="option-chevron">›</span></button><button type="button" id="employeesProfileButton" data-admin-view="employees" class="white-card profile-option is-hidden"><span class="option-icon option-green">♙</span><span class="option-copy"><strong>Personal</strong><small>Empleados, puestos y cuentas vinculadas</small></span><span class="option-chevron">›</span></button><button type="button" id="shiftsProfileButton" data-admin-view="shifts" class="white-card profile-option is-hidden"><span class="option-icon option-purple">▦</span><span class="option-copy"><strong>Turnos y asistencia</strong><small>Programación, entradas y salidas</small></span><span class="option-chevron">›</span></button><button type="button" id="usersManagementButton" class="white-card profile-option is-hidden"><span class="option-icon option-blue">♙</span><span class="option-copy"><strong>Usuarios y permisos</strong><small>Administrar cuentas, roles y accesos</small></span><span class="option-chevron">›</span></button><button type="button" id="notificationsProfileButton" data-admin-view="notifications" class="white-card profile-option"><span class="option-icon option-blue">♢</span><span class="option-copy"><strong>Notificaciones</strong><small>Avisos y comunicados internos</small></span><span class="option-chevron">›</span></button><button type="button" id="supportProfileButton" data-admin-view="support" class="white-card profile-option"><span class="option-icon option-green">?</span><span class="option-copy"><strong>Soporte</strong><small>Tickets, incidencias y seguimiento</small></span><span class="option-chevron">›</span></button><button type="button" id="integrationsProfileButton" data-admin-view="integrations" class="white-card profile-option is-hidden"><span class="option-icon option-purple">↗</span><span class="option-copy"><strong>Integraciones y API</strong><small>Conexiones externas y tokens</small></span><span class="option-chevron">›</span></button><button type="button" id="logoutButton" class="white-card profile-option logout-option"><span class="option-icon option-red">↪</span><span class="option-copy"><strong>Cerrar sesión</strong><small>Salir de la aplicación</small></span><span class="option-chevron">›</span></button></div>
        </section>
      </main>

      <nav class="bottom-bar" aria-label="Navegación principal"><button type="button" class="bottom-tab is-active" data-view="home"><span class="tab-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/></svg></span><span>Inicio</span></button><button type="button" class="bottom-tab" data-view="orders"><span class="tab-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12a2 2 0 0 1 2 2v16H4V5a2 2 0 0 1 2-2Z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span><span>Pedidos</span></button><button type="button" class="bottom-tab" data-view="menu"><span class="tab-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/></svg></span><span>Menú</span></button><button type="button" class="bottom-tab" data-view="reservations"><span class="tab-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18M8 14h3M8 17h3"/></svg></span><span>Reservas</span></button><button type="button" class="bottom-tab" data-view="profile"><span class="tab-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span><span>Perfil</span></button></nav>
    </div>
  </div>

  <!-- Modales y feedback -->
  <div id="modalRoot" class="modal-root is-hidden" role="presentation"><div class="modal-scrim" data-close-modal></div><section class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="modalTitle"><button type="button" class="modal-x" data-close-modal aria-label="Cerrar ventana">×</button><div id="modalBody"></div></section></div>
  <div id="toast" class="toast" role="status" aria-live="polite"><span id="toastIcon" class="toast-symbol">✓</span><span class="toast-copy"><strong id="toastTitle">Listo</strong><small id="toastMessage">Operación realizada.</small></span></div>
  <script src="{{ asset('script.js') }}"></script>
</body>
</html>
