'use strict';

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
const csrfToken = $('meta[name="csrf-token"]')?.content || '';
const CART_KEY = 'desego_store_cart_v1';
const state = { categories: [], products: [], cart: loadCart(), activeCategory: 'Todos', deliveryFee: 5, profile: null, promotions: [], couponCode: '', quote: null, quoteSequence: 0, checkoutKey: makeCheckoutKey(), toastTimer: null, favoriteKey: getOrCreateFavoriteKey(), favorites: [], favoritesOnly: false, reviewSummary: {}, reviewProductId: 0 };

function getOrCreateFavoriteKey() {
  const keyName = 'desego_store_favorites_key_v1';
  try {
    const existing = localStorage.getItem(keyName);
    if (existing && /^[a-f0-9]{64}$/.test(existing)) return existing;
  } catch (_) { /* Se genera una clave temporal si el navegador bloquea el almacenamiento. */ }
  const bytes = new Uint8Array(32);
  if (window.crypto?.getRandomValues) crypto.getRandomValues(bytes);
  else for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
  const key = [...bytes].map(value => value.toString(16).padStart(2, '0')).join('');
  try { localStorage.setItem(keyName, key); } catch (_) { /* El favorito funcionará durante esta sesión. */ }
  return key;
}
function makeCheckoutKey() {
  if (window.crypto?.randomUUID) return crypto.randomUUID();
  const bytes = new Uint8Array(16);
  if (window.crypto?.getRandomValues) crypto.getRandomValues(bytes);
  else for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
  const hex = [...bytes].map(x => x.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
}
function loadCart() {
  try {
    const parsed = JSON.parse(sessionStorage.getItem(CART_KEY) || '{}');
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
  } catch (_) { return {}; }
}
function saveCart() { sessionStorage.setItem(CART_KEY, JSON.stringify(state.cart)); }
function money(value) { return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(Number(value) || 0).replace('PEN', 'S/'); }
function escapeHTML(value) { return String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;'); }
function toast(message) {
  const el = $('#storeToast'); el.textContent = message; el.classList.add('is-visible');
  clearTimeout(state.toastTimer); state.toastTimer = setTimeout(() => el.classList.remove('is-visible'), 3000);
}
async function apiGet(url) {
  const response = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
  const data = await response.json().catch(() => ({}));
  if (!response.ok || data.ok === false) throw new Error(data.message || 'No se pudo cargar la información.');
  return data;
}
async function apiPost(body) {
  const response = await fetch('./api/storefront.php', {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
    body: JSON.stringify({ ...body, _csrf: csrfToken })
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok || data.ok === false) throw new Error(data.message || 'No se pudo procesar la solicitud.');
  return data;
}

async function reviewsGet(url) {
  const response = await fetch(url, { headers:{ 'Accept':'application/json' }, credentials:'same-origin', cache:'no-store' });
  const data = await response.json().catch(() => ({}));
  if (!response.ok || data.ok === false) throw new Error(data.message || 'No se pudo cargar la sección de reseñas.');
  return data;
}
async function reviewsPost(body) {
  const response = await fetch('./api/reviews.php', { method:'POST', credentials:'same-origin', headers:{ 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-Token':csrfToken }, body:JSON.stringify({ ...body, _csrf:csrfToken }) });
  const data = await response.json().catch(() => ({}));
  if (!response.ok || data.ok === false) throw new Error(data.message || 'No se pudo completar la operación.');
  return data;
}
async function loadReviewData() {
  try {
    const [summary, favorites] = await Promise.all([
      reviewsGet('./api/reviews.php?view=summary'),
      reviewsGet(`./api/reviews.php?view=favorites&visitor_key=${encodeURIComponent(state.favoriteKey)}`)
    ]);
    state.reviewSummary = summary.summary || {};
    state.favorites = Array.isArray(favorites.favorites) ? favorites.favorites.map(Number) : [];
  } catch (error) {
    // La tienda y el carrito continúan funcionando aunque la migración de reseñas aún no esté instalada.
    state.reviewSummary = {}; state.favorites = [];
    console.warn('DESEGO reviews/favorites:', error.message);
  }
}
async function init() {
  try {
    const data = await apiGet('./api/storefront.php?view=products');
    state.categories = data.categories || [];
    state.products = data.products || [];
    state.deliveryFee = Number(data.delivery_fee ?? 5);
    state.promotions = Array.isArray(data.promotions) ? data.promotions : [];
    syncCartWithCatalog();
    await loadReviewData();
    renderCategories(); renderProducts(); renderActivePromotions(); renderCart(); populateReviewProductSelect();
    initReviewUI();
    $('#storeMessage').classList.remove('is-visible');
    await loadSavedProfile();
  } catch (error) {
    $('#storeMessage').textContent = error.message + ' Comprueba que la base de datos esté configurada y que las migraciones de los pasos 7, 8 y 10 estén importadas.';
    $('#storeMessage').classList.add('is-visible');
    $('#storeProducts').innerHTML = '';
  }
}

function syncCartWithCatalog() {
  for (const [key, line] of Object.entries(state.cart)) {
    const current = state.products.find(product => Number(product.id) === Number(key));
    const quantity = Number(line?.quantity);
    if (!current || !current.can_order || !Number.isInteger(quantity) || quantity < 1 || quantity > 20) {
      delete state.cart[key];
      continue;
    }
    // Actualiza nombre y precio desde MySQL para no mostrar importes antiguos del carrito guardado.
    state.cart[key] = { id: Number(current.id), name: current.name, price: Number(current.price), quantity };
  }
  saveCart();
}

function renderCategories() {
  const el = $('#categoryChips');
  const cats = [{ id: 'Todos', name: 'Todos' }, ...state.categories];
  el.innerHTML = cats.map(c => `<button type="button" class="category-chip ${!state.favoritesOnly && String(c.name) === state.activeCategory ? 'is-active' : ''}" data-category="${escapeHTML(c.name)}">${escapeHTML(c.name)}</button>`).join('') +
    `<button type="button" class="category-chip favorites-chip ${state.favoritesOnly ? 'is-active' : ''}" data-favorites-only="true">♡ Mis favoritos <span>${state.favorites.length}</span></button>`;
}
function handleCategoryClick(event) {
  const el = $('#categoryChips');
  const favButton = event.target.closest('[data-favorites-only]');
  if (favButton) {
    state.favoritesOnly = !state.favoritesOnly;
    renderCategories(); renderProducts(); return;
  }
  const button = event.target.closest('[data-category]'); if (!button) return;
  state.favoritesOnly = false;
  state.activeCategory = button.dataset.category;
  renderCategories(); renderProducts();
}
function renderProducts() {
  const query = $('#storeSearch').value.trim().toLocaleLowerCase('es');
  const products = state.products.filter(p =>
    (!state.favoritesOnly || state.favorites.includes(Number(p.id))) &&
    (state.activeCategory === 'Todos' || p.category === state.activeCategory) &&
    `${p.name} ${p.description || ''} ${p.category}`.toLocaleLowerCase('es').includes(query)
  );
  $('#storeProducts').innerHTML = products.map(product => {
    const canOrder = Boolean(product.can_order);
    const id = Number(product.id);
    const favorite = state.favorites.includes(id);
    const summary = state.reviewSummary[String(id)] || { count: 0, average: null };
    const rating = Number(summary.average || 0);
    const ratingLabel = Number(summary.count) ? rating.toFixed(1) : 'Nuevo';
    return `<article class="product-card">
      <div class="product-art"><span class="product-category-label">${escapeHTML(product.category)}</span><button type="button" class="favorite-toggle ${favorite ? 'is-favorite' : ''}" data-favorite="${id}" aria-pressed="${favorite ? 'true' : 'false'}" aria-label="${favorite ? 'Quitar de favoritos' : 'Añadir a favoritos'}">${favorite ? '♥' : '♡'}</button><span class="product-emoji">${escapeHTML(product.emoji || '🍽️')}</span></div>
      <div class="product-info"><div class="product-title-row"><h3>${escapeHTML(product.name)}</h3><strong class="product-price">${money(product.price)}</strong></div>
      <p>${escapeHTML(product.description || 'Preparado al momento en DESEGO.')}</p>
      <div class="product-rating"><span>★ ${ratingLabel}</span><small>${Number(summary.count)} ${Number(summary.count) === 1 ? 'reseña' : 'reseñas'}</small><button type="button" data-review-product="${id}">Ver opiniones</button></div>
      <div class="product-foot"><span class="allergen-label ${product.allergens ? '' : 'safe'}">${product.allergens ? 'Alérgenos: ' + escapeHTML(product.allergens) : 'Consulta alérgenos al equipo'}</span><button type="button" class="add-product" data-add="${id}" ${canOrder ? '' : 'disabled'}>${canOrder ? '+ Agregar' : 'No disponible'}</button></div>
      ${canOrder ? '' : '<div class="recipe-warning">Este plato todavía no tiene receta e ingredientes configurados; el restaurante debe completarlos antes de aceptar pedidos.</div>'}</div>
    </article>`;
  }).join('');
  $('#storeEmpty').classList.toggle('is-hidden', products.length > 0);
  if (!products.length && state.favoritesOnly) $('#storeEmpty').querySelector('p').textContent = 'Aún no guardaste favoritos o no coinciden con la búsqueda.';
  else $('#storeEmpty').querySelector('p').textContent = 'Prueba otra palabra o cambia la categoría.';
}

function populateReviewProductSelect() {
  const select = $('#reviewProductSelect');
  if (!select) return;
  const before = String(state.reviewProductId || select.value || '');
  select.innerHTML = '<option value="">Selecciona un producto…</option>' + state.products.map(product => `<option value="${Number(product.id)}">${escapeHTML(product.name)}</option>`).join('');
  if (before && state.products.some(product => Number(product.id) === Number(before))) select.value = before;
  if (select.value) loadProductReviews(Number(select.value)).catch(() => {});
}
async function loadProductReviews(productId) {
  if (!productId) return;
  state.reviewProductId = Number(productId);
  $('#reviewProductSelect').value = String(productId);
  $('#publicReviewsList').innerHTML = '<p class="review-empty-note">Cargando opiniones…</p>';
  try {
    const result = await reviewsGet(`./api/reviews.php?view=product&product_id=${Number(productId)}`);
    state.reviewSummary[String(productId)] = result.summary || { count:0, average:null };
    const summary = result.summary || { count:0, average:null };
    $('#reviewProductSummary').innerHTML = `<strong>★ ${summary.average === null ? 'Sin calificación' : Number(summary.average).toFixed(1) + ' / 5'}</strong><span>${Number(summary.count)} ${Number(summary.count)===1?'opinión aprobada':'opiniones aprobadas'}</span>`;
    const list = result.reviews || [];
    $('#publicReviewsList').innerHTML = list.length ? list.map(review => `<article class="public-review-card"><div class="public-review-top"><strong>${escapeHTML(review.reviewer_display || 'Cliente DESEGO')}</strong><span class="review-stars">${'★'.repeat(Number(review.rating))}${'☆'.repeat(5-Number(review.rating))}</span></div>${review.title ? `<h3>${escapeHTML(review.title)}</h3>` : ''}<p>${escapeHTML(review.comment)}</p><small>${escapeHTML(new Date(String(review.created_at).replace(' ', 'T') + 'Z').toLocaleDateString('es-PE'))} · Compra verificada</small></article>`).join('') : '<p class="review-empty-note">Todavía no hay opiniones publicadas para este plato. Si ya recibiste y pagaste tu pedido, puedes ser la primera persona en reseñarlo.</p>';
    renderProducts();
  } catch (error) {
    $('#publicReviewsList').innerHTML = `<p class="review-empty-note">${escapeHTML(error.message)}</p>`;
  }
}
async function toggleFavorite(productId) {
  const button = $(`[data-favorite="${Number(productId)}"]`);
  if (button) button.disabled = true;
  try {
    const result = await reviewsPost({ action:'favorite.toggle', visitor_key:state.favoriteKey, product_id:Number(productId) });
    if (result.favorite) state.favorites = [...new Set([...state.favorites, Number(productId)])];
    else state.favorites = state.favorites.filter(id => Number(id) !== Number(productId));
    renderCategories(); renderProducts();
    toast(result.message || 'Favoritos actualizados.');
  } catch (error) {
    toast(error.message);
  } finally {
    const current = $(`[data-favorite="${Number(productId)}"]`); if (current) current.disabled = false;
  }
}
function openProductReviews(productId) {
  state.reviewProductId = Number(productId);
  $('#reviewFormDetails').open = false;
  $('#reviewProductSelect').value = String(productId);
  loadProductReviews(Number(productId)).catch(() => {});
  $('#customerReviews').scrollIntoView({ behavior:'smooth', block:'start' });
}
function initReviewUI() {
  $('#categoryChips').addEventListener('click', handleCategoryClick);
  $('#storeProducts').addEventListener('click', event => {
    const favorite = event.target.closest('[data-favorite]');
    if (favorite) { toggleFavorite(Number(favorite.dataset.favorite)); return; }
    const reviewButton = event.target.closest('[data-review-product]');
    if (reviewButton) openProductReviews(Number(reviewButton.dataset.reviewProduct));
  });
  $('#reviewProductSelect').addEventListener('change', () => loadProductReviews(Number($('#reviewProductSelect').value)).catch(error => toast(error.message)));
  $('#submitReviewForm').addEventListener('submit', async event => {
    event.preventDefault();
    const feedback = $('#reviewFormFeedback'); feedback.textContent = ''; feedback.classList.remove('is-error');
    const productId = Number($('#reviewProductSelect').value);
    if (!productId) { feedback.textContent = 'Selecciona el producto que deseas reseñar.'; feedback.classList.add('is-error'); return; }
    if (!$('#submitReviewForm').checkValidity()) { $('#submitReviewForm').reportValidity(); return; }
    const button = $('#submitReviewButton'); button.disabled = true; button.textContent = 'Enviando…';
    try {
      const result = await reviewsPost({ action:'review.submit', order_number:$('#reviewOrderNumber').value.trim(), customer_phone:$('#reviewPhone').value.trim(), product_id:productId, rating:Number($('#reviewRating').value), title:$('#reviewTitle').value.trim(), comment:$('#reviewComment').value.trim() });
      feedback.textContent = result.message || 'Tu reseña fue enviada para revisión.';
      feedback.classList.add('is-success');
      $('#reviewTitle').value = ''; $('#reviewComment').value = '';
      $('#submitReviewForm').reset();
      $('#reviewRating').value = '5';
      toast('Gracias por compartir tu opinión.');
    } catch (error) {
      feedback.textContent = error.message; feedback.classList.add('is-error');
    } finally { button.disabled = false; button.innerHTML = 'Enviar reseña <span>★</span>'; }
  });
}

function cartLines() { return Object.values(state.cart).filter(line => line && Number(line.quantity) > 0); }
function cartFingerprint() {
  return cartLines().map(line => `${Number(line.id)}:${Number(line.quantity)}`).sort().join('|');
}
function renderCart() {
  const lines = cartLines();
  const count = lines.reduce((sum, line) => sum + Number(line.quantity), 0);
  $('#cartCount').textContent = String(count);
  $('#checkoutButton').disabled = !lines.length;
  $('#cartItems').innerHTML = lines.length ? lines.map(line => `<div class="cart-row">
    <div class="cart-row-name">${escapeHTML(line.name)}</div><strong class="cart-row-total">${money(line.price * line.quantity)}</strong>
    <div class="cart-row-bottom"><div class="quantity-control"><button type="button" data-qty="${Number(line.id)}" data-delta="-1" aria-label="Restar uno">−</button><span>${Number(line.quantity)}</span><button type="button" data-qty="${Number(line.id)}" data-delta="1" aria-label="Sumar uno">+</button></div><button type="button" class="remove-line" data-remove="${Number(line.id)}">Quitar</button></div>
    </div>`).join('') : '<div class="cart-empty"><span>🛒</span><strong>Tu carrito está vacío</strong><p>Agrega algún plato del menú para comenzar.</p></div>';
  saveCart();
  state.quote = null;
  updateCartTotals();
  refreshQuote(false).catch(() => {});
}
function updateCartTotals() {
  const lines = cartLines();
  const subtotal = lines.reduce((sum, line) => sum + Number(line.quantity) * Number(line.price), 0);
  const validQuote = state.quote && state.quote.cart_key === cartFingerprint() && state.quote.coupon_code === state.couponCode;
  const discount = validQuote ? Number(state.quote.discount || 0) : 0;
  const fee = lines.length ? state.deliveryFee : 0;
  const total = validQuote ? Number(state.quote.total) : subtotal + fee;
  $('#cartSubtotal').textContent = money(validQuote ? state.quote.subtotal : subtotal);
  $('#cartDelivery').textContent = money(validQuote ? state.quote.delivery_fee : fee);
  $('#cartTotal').textContent = money(total);
  $('#cartDiscount').textContent = `− ${money(discount)}`;
  $('#cartDiscountRow').classList.toggle('is-hidden', !validQuote || discount <= 0);
  $('#cartPromotionLabel').textContent = validQuote && state.quote.promotion ? `· ${state.quote.promotion.name}` : '';
}
async function refreshQuote(explicit = false) {
  const lines = cartLines();
  const sequence = ++state.quoteSequence;
  if (!lines.length) {
    state.quote = null;
    updateCartTotals();
    return;
  }
  const code = String(state.couponCode || '').trim().toUpperCase();
  try {
    const result = await apiPost({
      action: 'quote', coupon_code: code,
      customer_email: $('#customerEmail')?.value.trim() || '',
      customer_phone: $('#customerPhone')?.value.trim() || '',
      items: lines.map(line => ({ product_id: Number(line.id), quantity: Number(line.quantity) }))
    });
    if (sequence !== state.quoteSequence) return;
    state.quote = { ...(result.quote || {}), cart_key: cartFingerprint(), coupon_code: code };
    updateCartTotals();
    const feedback = $('#couponFeedback');
    if (feedback && explicit) {
      feedback.classList.remove('is-error');
      if (state.quote.promotion) {
        feedback.textContent = state.quote.promotion.code
          ? `Cupón ${state.quote.promotion.code} aplicado. Ahorras ${money(state.quote.discount)}.`
          : `Promoción aplicada: ${state.quote.promotion.name}. Ahorras ${money(state.quote.discount)}.`;
      } else {
        feedback.textContent = 'No hay una promoción automática disponible para este carrito.';
      }
    }
  } catch (error) {
    if (sequence !== state.quoteSequence) return;
    state.quote = null;
    updateCartTotals();
    const feedback = $('#couponFeedback');
    if (feedback && (explicit || code)) {
      feedback.textContent = error.message;
      feedback.classList.add('is-error');
    }
  }
}
function renderActivePromotions() {
  const wrap = $('#activePromotions');
  const promotions = state.promotions || [];
  if (!wrap) return;
  if (!promotions.length) { wrap.classList.add('is-hidden'); wrap.innerHTML = ''; return; }
  wrap.innerHTML = `<div class="active-promotions-heading"><strong>Promociones disponibles</strong><span>Aplicadas automáticamente según las condiciones</span></div>${promotions.map(p => {
    const value = p.discount_type === 'percentage' ? `${Number(p.discount_value)}% de descuento` : `${money(p.discount_value)} de descuento`;
    const max = p.max_discount !== null && p.max_discount !== '' ? ` · máximo ${money(p.max_discount)}` : '';
    const minimum = Number(p.min_subtotal || 0) > 0 ? ` en compras desde ${money(p.min_subtotal)}` : '';
    return `<div class="active-promotion"><span class="active-promotion-icon">％</span><div><strong>${escapeHTML(p.name)}</strong><small>${escapeHTML(value + max + minimum)}</small></div></div>`;
  }).join('')}`;
  wrap.classList.remove('is-hidden');
}
function addToCart(id) {
  const product = state.products.find(p => Number(p.id) === Number(id));
  if (!product || !product.can_order) { toast('Este producto no está listo para pedidos en línea.'); return; }
  const key = String(id); const current = Number(state.cart[key]?.quantity || 0);
  if (current >= 20) { toast('Máximo 20 unidades por producto.'); return; }
  state.cart[key] = { id: Number(product.id), name: product.name, price: Number(product.price), quantity: current + 1 };
  renderCart(); toast(`${product.name} agregado al carrito.`);
}
$('#storeProducts').addEventListener('click', event => {
  const button = event.target.closest('[data-add]'); if (button) addToCart(button.dataset.add);
});
$('#cartItems').addEventListener('click', event => {
  const qtyButton = event.target.closest('[data-qty]');
  if (qtyButton) {
    const key = String(qtyButton.dataset.qty); const line = state.cart[key]; if (!line) return;
    const updated = Number(line.quantity) + Number(qtyButton.dataset.delta);
    if (updated < 1) delete state.cart[key]; else if (updated <= 20) line.quantity = updated; else { toast('Máximo 20 unidades por producto.'); return; }
    renderCart(); return;
  }
  const removeButton = event.target.closest('[data-remove]');
  if (removeButton) { delete state.cart[String(removeButton.dataset.remove)]; renderCart(); }
});
$('#storeSearch').addEventListener('input', renderProducts);
$('#couponCode').addEventListener('input', () => {
  const candidate = $('#couponCode').value.trim().toUpperCase();
  if (candidate !== state.couponCode) {
    const feedback = $('#couponFeedback'); feedback.textContent = candidate ? 'Pulsa Aplicar para comprobar el código.' : '';
    feedback.classList.remove('is-error'); state.quote = null; updateCartTotals();
  }
});
$('#applyCouponButton').addEventListener('click', async () => {
  state.couponCode = $('#couponCode').value.trim().toUpperCase();
  if (!state.couponCode) $('#couponFeedback').textContent = 'Se calcularán las promociones automáticas disponibles.';
  await refreshQuote(true);
});
$('#customerEmail').addEventListener('change', () => { if (cartLines().length) refreshQuote(false).catch(() => {}); });
$('#customerPhone').addEventListener('change', () => { if (cartLines().length) refreshQuote(false).catch(() => {}); });

async function loadSavedProfile() {
  try {
    const data = await apiGet('./api/storefront.php?view=profile');
    state.profile = data.profile || null;
    if (!state.profile) return;
    const c = state.profile.customer;
    if (!$('#customerName').value) $('#customerName').value = c.name || '';
    if (!$('#customerPhone').value) $('#customerPhone').value = c.phone || '';
    if (!$('#customerEmail').value) $('#customerEmail').value = c.email || '';
    renderSavedAddresses(state.profile.addresses || []);
  } catch (_) { /* La tienda continúa disponible para clientes nuevos. */ }
}
function renderSavedAddresses(addresses) {
  if (!addresses.length) return;
  const select = $('#savedAddress');
  select.innerHTML = '<option value="">Ingresar una dirección nueva</option>' + addresses.map(address => {
    const town = [address.district, address.province].filter(Boolean).join(', ');
    const label = `${address.label || 'Dirección'} · ${address.address_line}${town ? ' (' + town + ')' : ''}${address.is_default ? ' · Predeterminada' : ''}`;
    return `<option value="${Number(address.id)}">${escapeHTML(label)}</option>`;
  }).join('');
  $('#savedAddressWrap').classList.remove('is-hidden');
}
function syncAddressFields() {
  const saved = $('#savedAddress').value !== '';
  $('#newAddressFields').classList.toggle('is-hidden', saved);
  ['addressLine','district'].forEach(id => { const field = $('#' + id); field.required = !saved; });
}
$('#savedAddress').addEventListener('change', syncAddressFields);
$('#checkoutButton').addEventListener('click', async () => {
  $('#checkoutPanel').classList.remove('is-hidden');
  $('#checkoutButton').classList.add('is-hidden');
  $('#orderSuccess').classList.add('is-hidden');
  if (!state.profile) await loadSavedProfile();
  syncAddressFields();
  $('#customerName').focus({ preventScroll: true });
});
$('#closeCheckout').addEventListener('click', () => { $('#checkoutPanel').classList.add('is-hidden'); $('#checkoutButton').classList.remove('is-hidden'); });

$('#checkoutForm').addEventListener('submit', async event => {
  event.preventDefault();
  const errorBox = $('#checkoutError'); errorBox.textContent = '';
  if (!Object.keys(state.cart).length) { errorBox.textContent = 'Agrega al menos un producto al carrito.'; return; }
  const form = event.currentTarget;
  if (!form.checkValidity()) { form.reportValidity(); return; }
  if (!$('#dataConsent').checked) { errorBox.textContent = 'Confirma que autorizas el uso de tus datos para gestionar la entrega.'; return; }

  const button = $('#submitOrderButton'); button.disabled = true; button.innerHTML = 'Registrando pedido…';
  const savedAddressId = $('#savedAddress').value;
  const body = {
    action: 'checkout', checkout_key: state.checkoutKey,
    customer_name: $('#customerName').value.trim(), phone: $('#customerPhone').value.trim(), email: $('#customerEmail').value.trim(),
    saved_address_id: savedAddressId || null,
    address_line: savedAddressId ? '' : $('#addressLine').value.trim(),
    reference: savedAddressId ? '' : $('#reference').value.trim(),
    district: savedAddressId ? '' : $('#district').value.trim(),
    province: savedAddressId ? '' : $('#province').value.trim(),
    department: savedAddressId ? '' : $('#department').value.trim(),
    payment_preference: $('#paymentPreference').value, coupon_code: state.couponCode, notes: $('#orderNotes').value.trim(), website: $('#website').value,
    consent: $('#dataConsent').checked,
    items: Object.values(state.cart).map(line => ({ product_id: Number(line.id), quantity: Number(line.quantity) }))
  };

  try {
    const result = await apiPost(body);
    state.cart = {}; state.couponCode = ''; state.quote = null; $('#couponCode').value = ''; $('#couponFeedback').textContent = ''; $('#couponFeedback').classList.remove('is-error'); saveCart(); renderCart(); state.checkoutKey = makeCheckoutKey();
    $('#checkoutPanel').classList.add('is-hidden'); $('#checkoutButton').classList.add('is-hidden');
    $('#orderSuccess').classList.remove('is-hidden');
    $('#orderSuccess').innerHTML = `<div class="success-mark">✓</div><h3>¡Pedido recibido!</h3><p>Tu código es <strong>${escapeHTML(result.order_number)}</strong>.</p><p>Subtotal: ${money(result.subtotal)}${Number(result.discount || 0) > 0 ? `<br>Descuento${result.promotion?.name ? ' (' + escapeHTML(result.promotion.name) + ')' : ''}: −${money(result.discount)}` : ''}<br>Delivery: ${money(result.delivery_fee)}<br><strong>Total: ${money(result.total)}</strong></p><p>Preferencia de pago: <strong>${escapeHTML(result.payment_preference || 'Por coordinar')}</strong></p><p>${escapeHTML(result.message || 'El restaurante confirmará tu pedido.')}</p><button type="button" id="continueShopping">Volver al menú</button>`;
    $('#continueShopping').addEventListener('click', () => { $('#orderSuccess').classList.add('is-hidden'); $('#checkoutButton').classList.remove('is-hidden'); $('#checkoutForm').reset(); $('#savedAddress').value = ''; syncAddressFields(); window.scrollTo({ top: $('#catalogo').offsetTop - 15, behavior: 'smooth' }); });
    await loadSavedProfile();
    toast(`Pedido ${result.order_number} registrado.`);
  } catch (error) {
    errorBox.textContent = error.message;
  } finally {
    button.disabled = false; button.innerHTML = 'Confirmar pedido <span>✓</span>';
  }
});

init();
