<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#112E68">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>DESEGO CEVICHERÍA · Tienda online</title>
  <link rel="stylesheet" href="{{ asset('tienda.css') }}">
</head>
<body>
  <header class="store-header">
    <a class="store-brand" href="{{ route('desego.store') }}" aria-label="DESEGO inicio">
      <img class="store-logo-image" src="{{ asset('assets/desego-logo.png') }}" alt="Logo DESEGO">
      <span><strong>DESEGO</strong><small>CEVICHERÍA</small></span>
    </a>
    <div class="header-message"><span class="online-dot"></span> Cocina fresca, sabor peruano</div>
    <a class="admin-link" href="{{ route('desego.admin') }}">Panel administrativo <span aria-hidden="true">↗</span></a>
  </header>

  <main class="store-main">
    <section class="store-hero">
      <div class="hero-copy">
        <span class="eyebrow">SABOR MARINO · HECHO AL MOMENTO</span>
        <h1>Tu antojo de mar,<br><span>en unos clics.</span></h1>
        <p>Explora el menú de DESEGO, arma tu pedido y recibe tus platos donde estés.</p>
        <a href="#catalogo" class="hero-cta">Ver menú <span aria-hidden="true">↓</span></a>
        <div class="hero-details"><span>✦ Ingredientes frescos</span><span>⌂ Delivery a domicilio</span></div>
      </div>
      <div class="hero-art" aria-hidden="true">
        <div class="hero-ring ring-one"></div><div class="hero-ring ring-two"></div>
        <div class="hero-plate"><span>🐟</span><i>✦</i><b>DESEGO</b></div>
        <div class="hero-mini mini-one">🌶️ <span>Sabor peruano</span></div>
        <div class="hero-mini mini-two">🍋 <span>Preparado al momento</span></div>
      </div>
    </section>

    <div class="store-content" id="catalogo">
      <section class="catalog-column">
        <div class="catalog-title-row">
          <div><span class="eyebrow dark-eyebrow">NUESTRO MENÚ</span><h2>Elige tus favoritos</h2><p>Productos y precios actualizados desde el catálogo de DESEGO.</p></div>
          <label class="store-search"><span aria-hidden="true">⌕</span><input id="storeSearch" type="search" placeholder="Buscar platos…" aria-label="Buscar platos"></label>
        </div>
        <div id="categoryChips" class="category-chips" aria-label="Filtrar por categoría"><button type="button" class="category-chip is-active" data-category="Todos">Todos</button></div>
        <div id="storeMessage" class="store-message" role="status" aria-live="polite">Cargando productos del menú…</div>
        <div id="activePromotions" class="active-promotions is-hidden" aria-label="Promociones disponibles"></div>
        <div id="storeProducts" class="store-products"></div>
        <div id="storeEmpty" class="empty-catalog is-hidden"><span>⌕</span><strong>No encontramos ese plato</strong><p>Prueba otra palabra o cambia la categoría.</p></div>
        <section class="reviews-panel" id="customerReviews" aria-labelledby="customerReviewsTitle">
          <div class="reviews-panel-heading"><div><span class="eyebrow dark-eyebrow">OPINIONES REALES</span><h2 id="customerReviewsTitle">Reseñas y calificaciones</h2><p>Comparte tu experiencia después de recibir y pagar tu pedido.</p></div><span class="reviews-shield">✓ Compra verificada</span></div>
          <div class="review-product-picker"><label for="reviewProductSelect">Producto</label><select id="reviewProductSelect"><option value="">Selecciona un producto…</option></select></div>
          <div id="reviewProductSummary" class="review-product-summary"><span>★ —</span><small>Selecciona un producto para leer las opiniones.</small></div>
          <div id="publicReviewsList" class="public-reviews-list"><p class="review-empty-note">Selecciona un producto para ver sus reseñas.</p></div>
          <details class="review-form-details" id="reviewFormDetails"><summary>Escribir una reseña de una compra</summary>
            <form id="submitReviewForm" class="review-form" novalidate>
              <p class="review-verification-note">Necesitas el código del pedido y el teléfono de compra. Solo se aceptan pedidos entregados y pagados.</p>
              <div class="form-grid"><div class="form-field"><label for="reviewOrderNumber">Código del pedido *</label><input id="reviewOrderNumber" maxlength="24" required placeholder="Ej. DES-WEB-000123"></div><div class="form-field"><label for="reviewPhone">Teléfono de compra *</label><input id="reviewPhone" type="tel" maxlength="30" required autocomplete="tel" placeholder="999 999 999"></div></div>
              <div class="form-grid"><div class="form-field"><label for="reviewRating">Calificación *</label><select id="reviewRating" required><option value="5">★★★★★ · Excelente</option><option value="4">★★★★☆ · Muy bueno</option><option value="3">★★★☆☆ · Bueno</option><option value="2">★★☆☆☆ · Regular</option><option value="1">★☆☆☆☆ · Malo</option></select></div><div class="form-field"><label for="reviewTitle">Título (opcional)</label><input id="reviewTitle" maxlength="120" placeholder="Resume tu experiencia"></div></div>
              <div class="form-field"><label for="reviewComment">Tu reseña *</label><textarea id="reviewComment" rows="4" minlength="10" maxlength="1000" required placeholder="¿Qué te pareció el plato, el sabor y la atención?"></textarea><small>Entre 10 y 1000 caracteres. Evita incluir información personal.</small></div>
              <p id="reviewFormFeedback" class="review-form-feedback" role="status" aria-live="polite"></p>
              <button type="submit" id="submitReviewButton" class="checkout-button">Enviar reseña <span>★</span></button>
            </form>
          </details>
        </section>
      </section>

      <aside class="cart-card" aria-labelledby="cartTitle">
        <div class="cart-header"><div class="cart-header-icon">▤</div><div><h2 id="cartTitle">Tu pedido</h2><p><span id="cartCount">0</span> productos en tu carrito</p></div></div>
        <div id="cartItems" class="cart-items"><div class="cart-empty"><span>🛒</span><strong>Tu carrito está vacío</strong><p>Agrega algún plato del menú para comenzar.</p></div></div>
        <div class="coupon-box">
          <label for="couponCode">Cupón o código promocional</label>
          <div class="coupon-entry"><input id="couponCode" type="text" maxlength="40" placeholder="Ej. DESEGO10" autocomplete="off"><button type="button" id="applyCouponButton">Aplicar</button></div>
          <p id="couponFeedback" class="coupon-feedback" aria-live="polite"></p>
        </div>
        <div class="cart-totals">
          <div><span>Subtotal</span><strong id="cartSubtotal">S/ 0.00</strong></div>
          <div id="cartDiscountRow" class="cart-discount-row is-hidden"><span>Descuento <small id="cartPromotionLabel"></small></span><strong id="cartDiscount">− S/ 0.00</strong></div>
          <div><span>Delivery</span><strong id="cartDelivery">S/ 5.00</strong></div>
          <div class="cart-total"><span>Total estimado</span><strong id="cartTotal">S/ 5.00</strong></div>
        </div>
        <p class="delivery-note">El pago se coordina al recibir el pedido. No se realiza un cobro en línea.</p>
        <button type="button" id="checkoutButton" class="checkout-button" disabled>Continuar con la entrega <span>→</span></button>
        <div id="checkoutPanel" class="checkout-panel is-hidden">
          <div class="checkout-panel-heading"><div><span class="eyebrow dark-eyebrow">CASI LISTO</span><h3>Datos de entrega</h3></div><button type="button" id="closeCheckout" class="close-checkout" aria-label="Cerrar formulario">×</button></div>
          <form id="checkoutForm" novalidate>
            <div class="form-field"><label for="customerName">Nombre completo *</label><input id="customerName" name="customer_name" autocomplete="name" maxlength="120" required placeholder="¿A nombre de quién? "></div>
            <div class="form-field"><label for="customerPhone">Teléfono *</label><input id="customerPhone" name="phone" type="tel" autocomplete="tel" maxlength="30" required placeholder="999 999 999"></div>
            <div class="form-field"><label for="customerEmail">Correo electrónico</label><input id="customerEmail" name="email" type="email" autocomplete="email" maxlength="190" placeholder="tu@correo.com"></div>
            <div id="savedAddressWrap" class="form-field is-hidden"><label for="savedAddress">Usar dirección guardada</label><select id="savedAddress"><option value="">Ingresar una dirección nueva</option></select><small>Las direcciones guardadas están disponibles en esta sesión del navegador.</small></div>
            <div id="newAddressFields">
              <div class="form-field"><label for="addressLine">Dirección exacta *</label><input id="addressLine" name="address_line" maxlength="255" placeholder="Av., calle, número e interior" required autocomplete="street-address"></div>
              <div class="form-grid"><div class="form-field"><label for="district">Distrito *</label><input id="district" name="district" maxlength="100" placeholder="Ej. Miraflores" required></div><div class="form-field"><label for="province">Provincia</label><input id="province" name="province" maxlength="100" value="Lima"></div></div>
              <div class="form-grid"><div class="form-field"><label for="department">Departamento</label><input id="department" name="department" maxlength="100" value="Lima"></div><div class="form-field"><label for="reference">Referencia</label><input id="reference" name="reference" maxlength="255" placeholder="Frente al parque…"></div></div>
            </div>
            <div class="form-field"><label for="paymentPreference">Preferencia de pago</label><select id="paymentPreference" name="payment_preference"><option value="Efectivo">Efectivo al recibir</option><option value="Yape/Plin">Yape / Plin (coordinar al confirmar)</option><option value="Tarjeta al recibir">Tarjeta al recibir, si está disponible</option></select></div>
            <div class="form-field"><label for="orderNotes">Indicaciones para el restaurante (opcional)</label><textarea id="orderNotes" name="notes" rows="2" maxlength="300" placeholder="Sin cebolla, referencia para el repartidor…"></textarea></div>
            <label class="consent-check"><input id="dataConsent" type="checkbox" required><span>Autorizo usar mis datos de contacto y entrega para gestionar este pedido.</span></label>
            <div class="honeypot" aria-hidden="true"><label for="website">No completar</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
            <p id="checkoutError" class="checkout-feedback" role="alert"></p>
            <button type="submit" id="submitOrderButton" class="checkout-button">Confirmar pedido <span>✓</span></button>
            <p class="form-legal">El importe final se confirma con el restaurante. La preferencia de pago no implica un cobro en línea.</p>
          </form>
        </div>
        <div id="orderSuccess" class="order-success is-hidden" role="status" aria-live="polite"></div>
      </aside>
    </div>

    <section class="store-benefits">
      <div><span>🐟</span><strong>Preparado al momento</strong><p>Recetas marinas y sabor criollo.</p></div>
      <div><span>⌂</span><strong>Entrega a domicilio</strong><p>Indica tus referencias para ayudarte a llegar.</p></div>
      <div><span>♡</span><strong>Atención cercana</strong><p>El equipo confirma el pedido y coordina contigo.</p></div>
    </section>
    <aside class="store-loyalty-note" aria-label="Programa de puntos DESEGO">
      <span aria-hidden="true">✦</span>
      <div><strong>Programa DESEGO Puntos</strong><p>Acumula puntos en pedidos asociados a tu ficha de cliente cuando el restaurante confirme el pago. Consulta tu saldo y canjea recompensas con el equipo de DESEGO.</p></div>
    </aside>
  </main>

  <footer class="store-footer"><div class="footer-brand"><img class="footer-logo-image" src="{{ asset('assets/desego-logo.png') }}" alt="Logo DESEGO"><span><strong>DESEGO CEVICHERÍA</strong><small>Un toque del mar en cada plato.</small></span></div><span>© <?= date('Y') ?> DESEGO · Tienda online</span></footer>
  <div id="storeToast" class="store-toast" role="status" aria-live="polite"></div>
  <script src="{{ asset('tienda.js') }}" defer></script>
</body>
</html>
