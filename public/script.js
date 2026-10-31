/* DESEGO CEVICHERÍA — Vanilla JS SPA, sin dependencias externas. */
(() => {
  'use strict';

  const KEY = {
    session: 'desego_mobile_session',
    user: 'desego_mobile_user',
    registered: 'desego_mobile_registered_user',
    remember: 'desego_mobile_remember',
    products: 'desego_mobile_products',
    reservations: 'desego_mobile_reservations',
    orders: 'desego_mobile_orders'
  };
  let csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const state = {
    online: true,
    currentUser: null,
    profilePreferences: { theme_tone: 72, show_notification_badge: true, desktop_notifications: false, sound_notifications: false, default_view: 'home' },
    themePreviewOriginal: null,
    lastUnreadNotificationCount: null,
    themeTone: 72,
    currentView: 'home',
    orderFilter: 'Todos',
    menuFilter: 'Todos',
    search: '',
    toastTimer: null,
    orders: [],
    kitchenOrders: [],
    deliveryOrders: [],
    deliveryCouriers: [],
    operationPermissions: { kitchen: false, kitchenManage: false, delivery: false, deliveryManage: false, deliveryUpdate: false, isCourier: false },
    orderPermissions: { can_pay: false, can_cancel: false, can_update: false, can_cash: false },
    products: [],
    categories: [],
    ingredients: [],
    allergens: [],
    inventoryData: [],
    inventoryMovements: [],
    reservations: [],
    tables: [],
    customers: [],
    customerPermissions: { can_manage: false, can_manage_addresses: false },
    loyalty: null,
    loyaltySearchTimer: null,
    reviewsStatus: 'pending',
    invoices: [],
    eligibleInvoiceOrders: [],
    reportData: null,
    analyticsData: null,
    auditPage: 1,
    auditTotal: 0,
    auditFilters: {},
    backupRows: [],
    purchasingData: null,
    purchaseFilter: 'all',
    workforceData: { branches: [], employees: [], shifts: [], users: [] },
    shiftRange: null,
    communications: { notifications: [], tickets: [], ticketId: null, ticket: null, integrations: [], tokens: [], supportFilter: 'all', canManageSupport: false, canManageIntegrations: false, canManageTokens: false }
  }

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const money = value => `S/ ${Number(value).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

  function safeGet(key) {
    try { return localStorage.getItem(key); } catch (_) { return null; }
  }
  function safeSet(key, value) {
    try { localStorage.setItem(key, value); return true; } catch (_) { return false; }
  }
  function safeRemove(key) {
    try { localStorage.removeItem(key); } catch (_) { /* Almacenamiento no disponible */ }
  }
  function loadStoredArray(key) {
    try {
      const parsed = JSON.parse(safeGet(key) || 'null');
      return Array.isArray(parsed) ? parsed : null;
    } catch (_) { return null; }
  }
  function escapeHTML(value) {
    return String(value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
  }
  function firstName(name) { return String(name || 'Juan').trim().split(/\s+/)[0] || 'Juan'; }
  function initials(name) { return String(name || 'JD').trim().split(/\s+/).slice(0, 2).map(part => part[0] || '').join('').toUpperCase(); }

  /* API de backend. No se almacena la contraseña ni la identidad de sesión en localStorage. */
  function updateCsrf(token) {
    if (!token) return;
    csrfToken = token;
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) meta.content = token;
  }
  async function apiRequest(url, options = {}) {
    const headers = { ...(options.headers || {}) };
    if (options.method && options.method.toUpperCase() !== 'GET') {
      headers['Content-Type'] = 'application/json';
      headers['X-CSRF-Token'] = csrfToken;
    }
    const response = await fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store',
      ...options,
      headers
    });
    let data;
    try { data = await response.json(); }
    catch (_) { throw new Error('El servidor devolvió una respuesta no válida.'); }
    if (data.csrfToken) updateCsrf(data.csrfToken);
    if (!response.ok || data.ok === false) {
      if (response.status === 401) {
        state.currentUser = null;
      }
      throw new Error(data.message || 'No se pudo completar la operación.');
    }
    return data;
  }
  function getUser() { return state.currentUser; }
  function initAuth() {
    $('#showRegister').addEventListener('click', () => switchAuth('register'));
    $('#showLogin').addEventListener('click', () => switchAuth('login'));
    $('#loginForm').addEventListener('submit', handleLogin);
    $('#registerForm').addEventListener('submit', handleRegister);
    $('#forgotPassword').addEventListener('click', () => {
      $('#forgotEmail').value = $('#loginEmail').value.trim();
      $('#forgotFeedback').textContent = '';
      $('#forgotFeedback').classList.remove('is-success');
      $('#forgotDebugWrap').classList.add('is-hidden');
      switchAuth('forgot');
    });
    $('#forgotBackToLogin').addEventListener('click', () => switchAuth('login'));
    $('#resetBackToLogin').addEventListener('click', returnToLoginFromReset);
    $('#forgotPasswordForm').addEventListener('submit', handleForgotPassword);
    $('#resetPasswordForm').addEventListener('submit', handleResetPassword);
    $$('.password-eye').forEach(button => button.addEventListener('click', () => {
      const field = document.getElementById(button.dataset.password);
      if (!field) return;
      field.type = field.type === 'password' ? 'text' : 'password';
      button.setAttribute('aria-label', field.type === 'password' ? 'Mostrar contraseña' : 'Ocultar contraseña');
    }));
  }
  function switchAuth(which) {
    const panels = { login: '#loginPanel', register: '#registerPanel', forgot: '#forgotPanel', reset: '#resetPanel' };
    Object.entries(panels).forEach(([name, selector]) => $(selector).classList.toggle('is-hidden', name !== which));
    $('#loginError').textContent = '';
    $('#registerError').textContent = '';
    if (which === 'forgot') setTimeout(() => $('#forgotEmail')?.focus(), 40);
    if (which === 'reset') setTimeout(() => $('#newPassword')?.focus(), 40);
  }
  async function handleForgotPassword(event) {
    event.preventDefault();
    const form = $('#forgotPasswordForm');
    const button = form.querySelector('button[type="submit"]');
    const feedback = $('#forgotFeedback');
    const debugWrap = $('#forgotDebugWrap');
    feedback.textContent = '';
    feedback.classList.remove('is-success');
    debugWrap.classList.add('is-hidden');
    button.disabled = true;
    try {
      const result = await apiRequest('api/password-reset.php?action=request', {
        method: 'POST',
        body: JSON.stringify({ email: $('#forgotEmail').value.trim().toLowerCase() })
      });
      feedback.textContent = result.message || 'Si el correo corresponde a una cuenta activa, recibirás instrucciones para restablecer la contraseña.';
      feedback.classList.add('is-success');
      if (result.debugUrl) {
        $('#forgotDebugLink').href = result.debugUrl;
        debugWrap.classList.remove('is-hidden');
      }
    } catch (error) {
      feedback.textContent = error.message || 'No se pudo procesar la solicitud. Inténtalo nuevamente.';
    } finally {
      button.disabled = false;
    }
  }
  async function handleResetPassword(event) {
    event.preventDefault();
    const password = $('#newPassword').value;
    const confirm = $('#confirmNewPassword').value;
    const feedback = $('#resetFeedback');
    const button = $('#resetPasswordForm button[type="submit"]');
    feedback.textContent = '';
    feedback.classList.remove('is-success');
    if (password.length < 12) { feedback.textContent = 'La contraseña debe tener al menos 12 caracteres.'; return; }
    if (password !== confirm) { feedback.textContent = 'Las contraseñas no coinciden.'; return; }
    button.disabled = true;
    try {
      const result = await apiRequest('api/password-reset.php?action=complete', {
        method: 'POST',
        body: JSON.stringify({ token: $('#resetToken').value, password, confirmPassword: confirm })
      });
      $('#resetPasswordForm').reset();
      feedback.textContent = result.message || 'Contraseña actualizada. Ya puedes iniciar sesión con tu nueva contraseña.';
      feedback.classList.add('is-success');
      button.textContent = 'CONTRASEÑA ACTUALIZADA';
      button.disabled = true;
      $('#resetBackToLogin').textContent = 'Volver al inicio de sesión';
    } catch (error) {
      feedback.textContent = error.message || 'El enlace no es válido o ha caducado. Solicita otro.';
      button.disabled = false;
    }
  }
  function returnToLoginFromReset() {
    const cleanUrl = new URL(window.location.href);
    cleanUrl.searchParams.delete('reset_token');
    window.history.replaceState({}, document.title, cleanUrl.pathname + cleanUrl.search + cleanUrl.hash);
    $('#resetPasswordForm').reset();
    const button = $('#resetPasswordForm button[type="submit"]');
    button.disabled = false;
    button.textContent = 'GUARDAR CONTRASEÑA →';
    $('#resetFeedback').textContent = '';
    $('#resetFeedback').classList.remove('is-success');
    switchAuth('login');
  }
  async function handleLogin(event) {
    event.preventDefault();
    const button = $('#loginForm button[type="submit"]');
    const errorBox = $('#loginError');
    errorBox.textContent = '';
    button.disabled = true;
    button.classList.add('is-loading');
    try {
      const result = await apiRequest('api/auth.php?action=login', {
        method: 'POST',
        body: JSON.stringify({
          email: $('#loginEmail').value.trim().toLowerCase(),
          password: $('#loginPassword').value,
          remember: $('#rememberMe').checked
        })
      });
      $('#loginPassword').value = '';
      enterDashboard(result.user);
      showToast('¡Bienvenido!', `Hola ${firstName(result.user.name)}. Sesión iniciada correctamente.`);
    } catch (error) {
      errorBox.textContent = error.message;
      $('#loginPassword').value = '';
    } finally {
      button.disabled = false;
      button.classList.remove('is-loading');
    }
  }
  async function handleRegister(event) {
    event.preventDefault();
    const name = $('#registerName').value.trim();
    const email = $('#registerEmail').value.trim().toLowerCase();
    const password = $('#registerPassword').value;
    const confirm = $('#confirmPassword').value;
    const feedback = $('#registerError');
    feedback.textContent = '';
    if (name.length < 3) { feedback.textContent = 'Escribe tu nombre completo.'; return; }
    if (password.length < 10) { feedback.textContent = 'La contraseña debe tener al menos 10 caracteres.'; return; }
    if (password !== confirm) { feedback.textContent = 'Las contraseñas no coinciden.'; return; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { feedback.textContent = 'Ingresa un correo electrónico válido.'; return; }
    const button = $('#registerForm button[type="submit"]');
    button.disabled = true;
    try {
      const result = await apiRequest('api/auth.php?action=register', {
        method: 'POST',
        body: JSON.stringify({ name, email, password, password_confirmation: confirm })
      });
      $('#registerForm').reset();
      enterDashboard(result.user);
      showToast('Cuenta creada', 'Tu cuenta de cliente ya está lista.');
    } catch (error) {
      feedback.textContent = error.message;
    } finally {
      button.disabled = false;
    }
  }
  function enterDashboard(user) {
    if (!user) return;
    if (user.role_code === 'customer') { window.location.assign('/cuenta'); return; }
    state.currentUser = user;
    $('#authScreen').classList.add('is-hidden');
    $('#dashboardScreen').classList.remove('is-hidden');
    $('#welcomeName').textContent = firstName(user.name);
    $('#profileName').textContent = user.name || 'Usuario DESEGO';
    $('#profileEmail').textContent = user.email || '';
    $('#profileInitials').textContent = initials(user.name);
    const roleLabel = $('#profileRole');
    if (roleLabel) roleLabel.textContent = user.role_name || 'Colaborador';
    if ($('#sidebarUserName')) $('#sidebarUserName').textContent = user.name || 'Usuario DESEGO';
    if ($('#sidebarUserRole')) $('#sidebarUserRole').textContent = user.role_name || 'Colaborador';
    if ($('#sidebarUserInitials')) $('#sidebarUserInitials').textContent = initials(user.name);
    if ($('#topbarUserName')) $('#topbarUserName').textContent = firstName(user.name);
    if ($('#topbarGreetingName')) $('#topbarGreetingName').textContent = firstName(user.name);
    if ($('#topbarUserRole')) $('#topbarUserRole').textContent = user.role_name || 'Colaborador';
    if ($('#topbarUserInitials')) $('#topbarUserInitials').textContent = initials(user.name);
    const permissionMap = { home: ['dashboard.view'], orders: ['orders.view'], menu: ['menu.view'], reservations: ['reservations.view', 'tables.view', 'reservations.manage', 'tables.manage'], profile: ['profile.view'] };
    $$('.bottom-tab').forEach(tab => {
      const needed = permissionMap[tab.dataset.view] || [];
      tab.classList.toggle('is-hidden', needed.length > 0 && !needed.some(permission => (user.permissions || []).includes(permission)));
    });
    const sidebarPermissionMap = {
      home: ['dashboard.view'],
      orders: ['orders.view'],
      kitchen: ['kitchen.view'],
      delivery: ['delivery.view'],
      reservations: ['reservations.view', 'tables.view', 'reservations.manage', 'tables.manage'],
      menu: ['menu.view'],
      suppliers: ['suppliers.view', 'suppliers.manage', 'purchases.view', 'purchases.manage'],
      purchasing: ['purchases.view', 'purchases.manage', 'purchases.receive', 'inventory.costs.view', 'inventory.costs.manage'],
      customers: ['customers.view', 'customers.manage', 'customer_addresses.manage'],
      loyalty: ['loyalty.view', 'loyalty.manage'],
      reviews: ['reviews.view', 'reviews.manage'],
      billing: ['invoices.view', 'invoices.manage'],
      reports: ['reports.view'],
      analytics: ['analytics.view'],
      branches: ['branches.view', 'branches.manage'],
      employees: ['employees.view', 'employees.manage'],
      shifts: ['shifts.view', 'shifts.manage', 'attendance.manage', 'attendance.clock'],
      notifications: ['notifications.view', 'notifications.manage', 'support.create', 'support.manage'],
      support: ['support.view', 'support.create', 'support.manage'],
      integrations: ['integrations.view', 'integrations.manage'],
      audit: ['audit.view'],
      backups: ['backups.view'],
      profile: ['profile.view']
    };
    $$('[data-admin-nav]').forEach(btn => {
      const needed = sidebarPermissionMap[btn.dataset.adminNav] || [];
      btn.classList.toggle('is-hidden', needed.length > 0 && !needed.some(permission => (user.permissions || []).includes(permission)));
    });
    $('#sidebarUsersModalBtn')?.classList.toggle('is-hidden', !(user.permissions || []).includes('users.view'));
    $('#sidebarCashModalBtn')?.classList.toggle('is-hidden', !can('cash.manage'));
    const managerButton = $('#usersManagementButton');
    if (managerButton) managerButton.classList.toggle('is-hidden', !(user.permissions || []).includes('users.view'));
    $('#editProfileButton').classList.toggle('is-hidden', !(user.permissions || []).includes('profile.update'));
    ['quickSale', 'newOrderButton'].forEach(id => { const node = $('#' + id); if (node) node.classList.toggle('is-hidden', !(user.permissions || []).includes('orders.create')); });
    $('#cashManagementButton')?.classList.toggle('is-hidden', !can('cash.manage'));
    $('#paymentHistoryButton')?.classList.toggle('is-hidden', !can('payments.view'));
    $('#invoiceManagementButton')?.classList.toggle('is-hidden', !(can('invoices.view') || can('invoices.manage')));
    $('#reportsManagementButton')?.classList.toggle('is-hidden', !can('reports.view'));
    $('#analyticsManagementButton')?.classList.toggle('is-hidden', !can('analytics.view'));
    $('#auditManagementButton')?.classList.toggle('is-hidden', !can('audit.view'));
    $('#backupsManagementButton')?.classList.toggle('is-hidden', !can('backups.view'));
    $('#analyticsProfileButton')?.classList.toggle('is-hidden', !can('analytics.view'));
    $('#auditProfileButton')?.classList.toggle('is-hidden', !can('audit.view'));
    $('#backupsProfileButton')?.classList.toggle('is-hidden', !can('backups.view'));
    $('#notificationsProfileButton')?.classList.toggle('is-hidden', !(can('notifications.view') || can('notifications.manage') || can('support.create') || can('support.manage')));
    $('#supportProfileButton')?.classList.toggle('is-hidden', !(can('support.view') || can('support.create') || can('support.manage')));
    $('#integrationsProfileButton')?.classList.toggle('is-hidden', !(can('integrations.view') || can('integrations.manage')));
    const notifButton = $('#notificationsButton');
    if (notifButton) notifButton.classList.toggle('is-hidden', !(can('notifications.view') || can('notifications.manage') || can('support.create') || can('support.manage')));
    if (can('notifications.view') || can('notifications.manage') || can('support.create') || can('support.manage')) refreshCommunicationsNotifications().catch(() => {});

    $('#saveAnalyticsPreferences')?.classList.toggle('is-hidden', !can('analytics.preferences'));
    $('#managePromotionsButton')?.classList.toggle('is-hidden', !(can('promotions.view') || can('promotions.manage')));
    $('#manageReviewsButton')?.classList.toggle('is-hidden', !(can('reviews.view') || can('reviews.manage')));
    $('#kitchenBoardButton')?.classList.toggle('is-hidden', !can('kitchen.view'));
    $('#deliveryBoardButton')?.classList.toggle('is-hidden', !can('delivery.view'));
    ['quickReserve', 'createReservationButton'].forEach(id => { const node = $('#' + id); if (node) node.classList.toggle('is-hidden', !(user.permissions || []).includes('reservations.manage')); });
    $('#createTableButton')?.classList.toggle('is-hidden', !can('tables.manage'));
    const canViewCustomers = can('customers.view') || can('customers.manage') || can('customer_addresses.manage');
    $('#customersManagementButton')?.classList.toggle('is-hidden', !canViewCustomers);
    $('#reservationCustomersButton')?.classList.toggle('is-hidden', !canViewCustomers);
    $('#newCustomerButton')?.classList.toggle('is-hidden', !can('customers.manage'));
    $('#loyaltyManagementButton')?.classList.toggle('is-hidden', !(can('loyalty.view') || can('loyalty.manage')));
    $('#newLoyaltyRewardButton')?.classList.toggle('is-hidden', !can('loyalty.manage'));
    const productButton = $('#newProductButton');
    if (productButton) productButton.classList.toggle('is-hidden', !can('products.manage'));
    $('#manageCategoriesButton')?.classList.toggle('is-hidden', !can('categories.manage'));
    $('#manageIngredientsButton')?.classList.toggle('is-hidden', !can('ingredients.manage') && !can('allergens.manage'));
    $('#manageInventoryButton')?.classList.toggle('is-hidden', !can('inventory.view'));
    $('#manageSuppliersButton')?.classList.toggle('is-hidden', !(can('suppliers.view') || can('suppliers.manage')));
    $('#managePurchasesButton')?.classList.toggle('is-hidden', !(can('purchases.view') || can('purchases.manage') || can('purchases.receive')));
    $('#manageCostsButton')?.classList.toggle('is-hidden', !(can('inventory.costs.view') || can('inventory.costs.manage')));
    $('#branchesProfileButton')?.classList.toggle('is-hidden', !(can('branches.view') || can('branches.manage')));
    $('#employeesProfileButton')?.classList.toggle('is-hidden', !(can('employees.view') || can('employees.manage')));
    $('#shiftsProfileButton')?.classList.toggle('is-hidden', !(can('shifts.view') || can('shifts.manage') || can('attendance.manage') || can('attendance.clock')));
    $('#branchFormCard')?.classList.toggle('is-hidden', !can('branches.manage'));
    $('#employeeFormCard')?.classList.toggle('is-hidden', !can('employees.manage'));
    $('#shiftFormCard')?.classList.toggle('is-hidden', !can('shifts.manage'));
    $('#employeeRateField')?.classList.toggle('is-hidden', !can('employees.compensation.view'));
    $('#supplierFormCard')?.classList.toggle('is-hidden', !can('suppliers.manage'));
    $('#purchaseFormCard')?.classList.toggle('is-hidden', !can('purchases.manage'));
    $('#costingMethodSelect')?.toggleAttribute('disabled', !can('inventory.costs.manage'));
    $('#saveCostingMethodButton')?.classList.toggle('is-hidden', !can('inventory.costs.manage'));
    $('#profileName').closest('.profile-main')?.classList.toggle('is-admin', user.role_code === 'admin');
    renderAll();
    switchView('home');
    if (can('menu.view')) refreshCatalogMenu(true).catch(error => showToast('No se pudo cargar el catálogo', error.message, '!'));
    if (can('orders.view')) refreshOrders().catch(error => showToast('No se pudieron cargar los pedidos', error.message, '!'));
    refreshDashboardStats();
    if (can('reservations.view') || can('tables.view') || can('reservations.manage') || can('tables.manage')) {
      refreshReservations().catch(error => showToast('No se pudieron cargar las reservas y mesas', error.message, '!'));
    }
    if (can('customers.view') || can('customers.manage') || can('customer_addresses.manage')) {
      refreshCustomers().catch(() => {});
    }
    loadProfilePreferences().catch(error => console.warn('No se pudieron cargar las preferencias del perfil:', error.message));
  }
  async function logout() {
    try {
      await apiRequest('api/auth.php?action=logout', { method: 'POST', body: JSON.stringify({}) });
    } catch (error) {
      showToast('No se pudo cerrar sesión', error.message, '!');
      return;
    }
    state.currentUser = null;
    $('#loginForm').reset();
    $('#dashboardScreen').classList.add('is-hidden');
    $('#authScreen').classList.remove('is-hidden');
    switchAuth('login');
    showToast('Sesión cerrada', 'Has salido de DESEGO correctamente.');
  }
  async function checkServerSession() {
    $('#authScreen').classList.remove('is-hidden');
    $('#dashboardScreen').classList.add('is-hidden');
    const resetTokenFromUrl = new URLSearchParams(window.location.search).get('reset_token');
    if (resetTokenFromUrl) {
      $('#resetToken').value = resetTokenFromUrl;
      switchAuth('reset');
      return;
    }
    try {
      const result = await apiRequest('api/auth.php?action=session', { method: 'GET' });
      if (result.authenticated && result.user) enterDashboard(result.user);
    } catch (error) {
      $('#loginError').textContent = error.message + ' Verifica config/database.php e importa database/schema.sql.';
    }
  }

  /* Navegación SPA. */
  const VIEW_META = {
    home: { group: 'Operación General', title: 'Panel General' },
    orders: { group: 'Operación General', title: 'Centro de Pedidos' },
    kitchen: { group: 'Operación General', title: 'Pantalla de Cocina (KDS)' },
    delivery: { group: 'Operación General', title: 'Centro de Delivery' },
    reservations: { group: 'Operación General', title: 'Reservas y Mesas' },
    menu: { group: 'Catálogo e Inventario', title: 'Menú, Recetas e Inventario' },
    suppliers: { group: 'Catálogo e Inventario', title: 'Proveedores' },
    purchasing: { group: 'Catálogo e Inventario', title: 'Compras y Valoración de Costos' },
    customers: { group: 'CRM y Marketing', title: 'Clientes y Direcciones' },
    loyalty: { group: 'CRM y Marketing', title: 'Programa de Fidelización' },
    reviews: { group: 'CRM y Marketing', title: 'Reseñas y Calificaciones' },
    billing: { group: 'Finanzas e Inteligencia', title: 'Facturación y Comprobantes' },
    reports: { group: 'Finanzas e Inteligencia', title: 'Reportes Operativos' },
    analytics: { group: 'Finanzas e Inteligencia', title: 'Analítica Avanzada' },
    branches: { group: 'Organización y RR.HH.', title: 'Sucursales' },
    employees: { group: 'Organización y RR.HH.', title: 'Personal y Colaboradores' },
    shifts: { group: 'Organización y RR.HH.', title: 'Turnos y Control de Asistencia' },
    notifications: { group: 'Sistema y Seguridad', title: 'Centro de Notificaciones' },
    support: { group: 'Sistema y Seguridad', title: 'Mesa de Ayuda y Soporte' },
    integrations: { group: 'Sistema y Seguridad', title: 'Integraciones y Tokens API' },
    audit: { group: 'Sistema y Seguridad', title: 'Auditoría y Actividad' },
    backups: { group: 'Sistema y Seguridad', title: 'Copias de Seguridad Cifradas' },
    profile: { group: 'Cuenta y Accesos', title: 'Mi Perfil y Configuración' }
  };

  function initNavigation() {
    $$('.bottom-tab').forEach(button => button.addEventListener('click', () => switchView(button.dataset.view)));
    $$('[data-admin-nav]').forEach(button => button.addEventListener('click', () => {
      switchView(button.dataset.adminNav);
      $('#dashboardScreen')?.classList.remove('is-sidebar-mobile-open');
    }));
    $$('[data-go]').forEach(button => button.addEventListener('click', () => switchView(button.dataset.go)));
    $('#customersManagementButton')?.addEventListener('click', () => switchView('customers'));
    $('#reservationCustomersButton')?.addEventListener('click', () => switchView('customers'));
    $('#loyaltyManagementButton')?.addEventListener('click', () => switchView('loyalty'));
    $('#manageReviewsButton')?.addEventListener('click', () => switchView('reviews'));
    $('#manageSuppliersButton')?.addEventListener('click', () => switchView('suppliers'));
    $('#managePurchasesButton')?.addEventListener('click', () => switchView('purchasing'));
    $('#manageCostsButton')?.addEventListener('click', () => switchView('purchasing'));
    $('#analyticsManagementButton')?.addEventListener('click', () => switchView('analytics'));
    $('#auditManagementButton')?.addEventListener('click', () => switchView('audit'));
    $('#backupsManagementButton')?.addEventListener('click', () => switchView('backups'));
    $$('[data-admin-view]').forEach(button => button.addEventListener('click', () => switchView(button.dataset.adminView)));
    $('#sidebarUsersModalBtn')?.addEventListener('click', () => openUserManagementModal('users'));
    $('#sidebarCashModalBtn')?.addEventListener('click', () => openCashModal());
    $('#sidebarCollapseToggle')?.addEventListener('click', () => {
      $('#dashboardScreen')?.classList.toggle('is-sidebar-collapsed');
    });
    $('#mobileSidebarToggle')?.addEventListener('click', () => {
      $('#dashboardScreen')?.classList.toggle('is-sidebar-mobile-open');
    });
    $('#sidebarMobileBackdrop')?.addEventListener('click', () => {
      $('#dashboardScreen')?.classList.remove('is-sidebar-mobile-open');
    });
  }
  function switchView(view) {
    if (view === 'kitchen' && !can('kitchen.view')) { showToast('Acceso restringido', 'Tu rol no puede consultar cocina.', '!'); return; }
    if (view === 'delivery' && !can('delivery.view')) { showToast('Acceso restringido', 'Tu rol no puede consultar el centro de delivery.', '!'); return; }
    if (view === 'customers' && !(can('customers.view') || can('customers.manage') || can('customer_addresses.manage'))) { showToast('Acceso restringido', 'Tu rol no puede consultar clientes.', '!'); return; }
    if (view === 'loyalty' && !(can('loyalty.view') || can('loyalty.manage'))) { showToast('Acceso restringido', 'Tu rol no puede consultar fidelización.', '!'); return; }
    if (view === 'reviews' && !(can('reviews.view') || can('reviews.manage'))) { showToast('Acceso restringido', 'Tu rol no puede consultar reseñas.', '!'); return; }
    if (view === 'billing' && !(can('invoices.view') || can('invoices.manage'))) { showToast('Acceso restringido', 'Tu rol no puede consultar comprobantes.', '!'); return; }
    if (view === 'reports' && !can('reports.view')) { showToast('Acceso restringido', 'Tu rol no puede consultar reportes.', '!'); return; }
    if (view === 'analytics' && !can('analytics.view')) { showToast('Acceso restringido', 'Tu rol no puede consultar analítica avanzada.', '!'); return; }
    if (view === 'audit' && !can('audit.view')) { showToast('Acceso restringido', 'Tu rol no puede consultar auditoría.', '!'); return; }
    if (view === 'backups' && !can('backups.view')) { showToast('Acceso restringido', 'Tu rol no puede consultar copias de seguridad.', '!'); return; }
    if (view === 'suppliers' && !(can('suppliers.view') || can('suppliers.manage') || can('purchases.view') || can('purchases.manage'))) { showToast('Acceso restringido', 'Tu rol no puede consultar proveedores.', '!'); return; }
    if (view === 'purchasing' && !(can('purchases.view') || can('purchases.manage') || can('purchases.receive') || can('inventory.costs.view') || can('inventory.costs.manage'))) { showToast('Acceso restringido', 'Tu rol no puede consultar compras ni costos.', '!'); return; }
    if (view === 'branches' && !(can('branches.view') || can('branches.manage'))) { showToast('Acceso restringido', 'Tu rol no puede consultar sucursales.', '!'); return; }
    if (view === 'employees' && !(can('employees.view') || can('employees.manage'))) { showToast('Acceso restringido', 'Tu rol no puede consultar personal.', '!'); return; }
    if (view === 'shifts' && !(can('shifts.view') || can('shifts.manage') || can('attendance.manage') || can('attendance.clock'))) { showToast('Acceso restringido', 'Tu rol no puede consultar turnos ni asistencia.', '!'); return; }
    if (view === 'notifications' && !(can('notifications.view') || can('notifications.manage') || can('support.create') || can('support.manage'))) { showToast('Acceso restringido', 'Tu rol no puede consultar notificaciones.', '!'); return; }
    if (view === 'support' && !(can('support.view') || can('support.create') || can('support.manage'))) { showToast('Acceso restringido', 'Tu rol no puede consultar soporte.', '!'); return; }
    if (view === 'integrations' && !(can('integrations.view') || can('integrations.manage'))) { showToast('Acceso restringido', 'Tu rol no puede consultar integraciones.', '!'); return; }
    state.currentView = view;
    $$('.app-view').forEach(section => section.classList.toggle('is-active', section.id === `view-${view}`));
    const navView = ['kitchen','delivery','customers','loyalty','billing','reports','analytics','audit','backups'].includes(view) ? 'orders' : (['reviews','suppliers','purchasing'].includes(view) ? 'menu' : (['branches','employees','shifts','notifications','support','integrations'].includes(view) ? 'profile' : view));
    $$('.bottom-tab').forEach(button => {
      const active = button.dataset.view === navView;
      button.classList.toggle('is-active', active);
      if (active) button.setAttribute('aria-current', 'page'); else button.removeAttribute('aria-current');
    });
    $$('[data-admin-nav]').forEach(button => {
      const active = button.dataset.adminNav === view;
      button.classList.toggle('is-active', active);
      if (active) button.setAttribute('aria-current', 'page'); else button.removeAttribute('aria-current');
    });
    const meta = VIEW_META[view] || { group: 'Administración', title: 'Panel General' };
    if ($('#topbarCurrentViewGroup')) $('#topbarCurrentViewGroup').textContent = meta.group;
    if ($('#topbarCurrentViewTitle')) $('#topbarCurrentViewTitle').textContent = meta.title;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (view === 'menu' && state.currentUser) refreshCatalogMenu(false).catch(() => {});
    if (view === 'orders' && state.currentUser && can('orders.view')) refreshOrders().catch(error => showToast('No se pudieron cargar los pedidos', error.message, '!'));
    if (view === 'kitchen' && state.currentUser && can('kitchen.view')) refreshKitchenBoard().catch(error => showToast('No se pudo cargar cocina', error.message, '!'));
    if (view === 'delivery' && state.currentUser && can('delivery.view')) refreshDeliveryBoard().catch(error => showToast('No se pudo cargar delivery', error.message, '!'));
    if (view === 'reservations' && state.currentUser) refreshReservations().catch(error => showToast('No se pudieron cargar las reservas', error.message, '!'));
    if (view === 'customers' && state.currentUser) refreshCustomers($('#customerSearch')?.value || '').catch(error => showToast('No se pudieron cargar los clientes', error.message, '!'));
    if (view === 'loyalty' && state.currentUser) refreshLoyalty($('#loyaltySearch')?.value || '').catch(error => showToast('No se pudo cargar fidelización', error.message, '!'));
    if (view === 'reviews' && state.currentUser) refreshAdminReviews().catch(error => showToast('No se pudieron cargar las reseñas', error.message, '!'));
    if (view === 'billing' && state.currentUser) refreshBilling().catch(error => showToast('No se pudieron cargar los comprobantes', error.message, '!'));
    if (view === 'reports' && state.currentUser) loadReports().catch(error => showToast('No se pudieron cargar los reportes', error.message, '!'));
    if (view === 'analytics' && state.currentUser) loadAnalytics().catch(error => showToast('No se pudo cargar la analítica', error.message, '!'));
    if (view === 'audit' && state.currentUser) loadAudit(state.auditPage || 1).catch(error => showToast('No se pudo cargar la auditoría', error.message, '!'));
    if (view === 'backups' && state.currentUser) loadBackups().catch(error => showToast('No se pudieron cargar los backups', error.message, '!'));
    if ((view === 'suppliers' || view === 'purchasing') && state.currentUser) refreshPurchasingData().catch(error => showToast('No se pudo cargar compras/proveedores', error.message, '!'));
    if (view === 'branches' && state.currentUser) refreshBranches().catch(error => showToast('No se pudieron cargar las sucursales', error.message, '!'));
    if (view === 'employees' && state.currentUser) refreshEmployees().catch(error => showToast('No se pudo cargar el personal', error.message, '!'));
    if (view === 'shifts' && state.currentUser) refreshShifts().catch(error => showToast('No se pudieron cargar los turnos', error.message, '!'));
    if (view === 'notifications' && state.currentUser) refreshCommunicationsNotifications().catch(error => showToast('No se pudieron cargar las notificaciones', error.message, '!'));
    if (view === 'support' && state.currentUser) refreshSupportTickets().catch(error => showToast('No se pudieron cargar los tickets', error.message, '!'));
    if (view === 'integrations' && state.currentUser) refreshIntegrations().catch(error => showToast('No se pudieron cargar las integraciones', error.message, '!'));
    if (view === 'home' && state.currentUser) {
      refreshDashboardStats();
      if (can('tables.view') || can('reservations.view')) refreshReservations().catch(() => {});
    }
  }

  /* Reservas y mesas: toda la información operativa procede de MySQL. */
  function reservationStatusLabel(status) {
    return ({ 'Pendiente': 'Pendiente', 'Confirmada': 'Confirmada', 'Sentada': 'En mesa', 'Completada': 'Completada', 'Cancelada': 'Cancelada', 'No asistió': 'No asistió' })[status] || status;
  }
  function reservationStatusClass(status) {
    return ({ 'Pendiente': 'is-pending', 'Confirmada': 'is-confirmed', 'Sentada': 'is-seated', 'Completada': 'is-completed', 'Cancelada': 'is-cancelled', 'No asistió': 'is-cancelled' })[status] || '';
  }
  function tableStatusLabel(status) {
    return ({ available: 'Disponible', occupied: 'Ocupada', reserved: 'Reservada', maintenance: 'Mantenimiento' })[status] || 'Disponible';
  }
  function activeTables() { return state.tables.filter(table => Number(table.is_active) === 1); }
  function tableCounts() {
    const active = activeTables();
    const counts = { available: 0, occupied: 0, reserved: 0, maintenance: 0 };
    active.forEach(table => { const status = table.effective_status || table.operational_status || 'available'; if (Object.prototype.hasOwnProperty.call(counts, status)) counts[status]++; });
    return { ...counts, total: active.length };
  }
  function tablesWidget() {
    const counts = tableCounts();
    const denominator = counts.total - counts.maintenance;
    const occupied = counts.occupied + counts.reserved;
    const occupancy = denominator > 0 ? Math.round((occupied / denominator) * 100) : 0;
    return `<article class="white-card tables-widget"><div class="card-heading"><div class="table-title"><span class="section-eyebrow">SALÓN PRINCIPAL</span><h2>Estado de mesas</h2><p>${counts.total} ${counts.total === 1 ? 'mesa registrada' : 'mesas registradas'}</p></div><div class="occupancy-number">${occupancy}%<small>ocupación actual</small></div></div><div class="table-stats"><div class="table-stat stat-available"><div class="table-stat-top"><i class="table-stat-dot"></i><span>Disponible</span></div><strong>${counts.available}</strong></div><div class="table-stat stat-occupied"><div class="table-stat-top"><i class="table-stat-dot"></i><span>Ocupada</span></div><strong>${counts.occupied}</strong></div><div class="table-stat stat-reserved"><div class="table-stat-top"><i class="table-stat-dot"></i><span>Reservada</span></div><strong>${counts.reserved}</strong></div><div class="table-stat stat-maintenance"><div class="table-stat-top"><i class="table-stat-dot"></i><span>Mantenimiento</span></div><strong>${counts.maintenance}</strong></div></div><div class="progress-area"><div class="progress-label"><span>Ocupación del salón</span><strong>${occupied} de ${denominator} mesas operativas</strong></div><div class="progress-track" role="progressbar" aria-label="Ocupación del salón" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${occupancy}"><div class="progress-fill" style="width:${occupancy}%"></div></div></div></article>`;
  }
  function renderTables() {
    const widget = tablesWidget();
    if ($('#homeTablesWidget')) $('#homeTablesWidget').innerHTML = widget;
    if ($('#reservationsTablesWidget')) $('#reservationsTablesWidget').innerHTML = widget;
    const grid = $('#tablesGrid');
    if (!grid) return;
    const tables = state.tables;
    const count = $('#tableCount');
    if (count) count.textContent = `${activeTables().length} activas · ${tables.length} registradas`;
    if (!tables.length) {
      grid.innerHTML = '<div class="empty-state"><span>▦</span><strong>No hay mesas registradas</strong><p>Importa la migración del Paso 5 para crear las 24 mesas iniciales.</p></div>';
      return;
    }
    grid.innerHTML = tables.map(table => {
      const effective = table.effective_status || table.operational_status || 'available';
      const inactive = Number(table.is_active) !== 1;
      const controls = can('tables.manage') ? `<div class="table-card-controls">${!inactive ? `<label class="table-status-control"><span>Estado operativo</span><select data-table-status="${Number(table.id)}" aria-label="Cambiar estado de ${escapeHTML(table.label)}"><option value="available" ${table.operational_status === 'available' ? 'selected' : ''}>Disponible</option><option value="occupied" ${table.operational_status === 'occupied' ? 'selected' : ''}>Ocupada</option><option value="maintenance" ${table.operational_status === 'maintenance' ? 'selected' : ''}>Mantenimiento</option></select></label>` : '<small class="table-inactive-note">Mesa inactiva</small>'}<button type="button" class="table-edit-button" data-edit-table="${Number(table.id)}">${inactive ? 'Reactivar / editar' : 'Editar'}</button></div>` : '';
      return `<article class="restaurant-table-card ${inactive ? 'is-inactive' : ''}"><div class="restaurant-table-top"><span class="restaurant-table-icon">▦</span><span class="table-state-badge table-state-${effective}">${inactive ? 'Inactiva' : tableStatusLabel(effective)}</span></div><h3>${escapeHTML(table.label)}</h3><p>${escapeHTML(table.zone)} · ${Number(table.capacity)} ${Number(table.capacity) === 1 ? 'persona' : 'personas'}</p><small class="table-number-label">Mesa N.º ${Number(table.table_number)}</small>${controls}</article>`;
    }).join('');
  }
  function parseReservationDate(value) {
    let normalized = String(value || '').trim();
    if (!normalized) return null;
    if (!/(Z|[+-]\d{2}:?\d{2})$/i.test(normalized)) normalized = normalized.replace(' ', 'T') + 'Z';
    const date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? null : date;
  }
  function reservationSlot(value) {
    const date = parseReservationDate(value);
    if (!date) return { day: 'Fecha inválida', time: '--:--' };
    const now = new Date();
    const todayKey = `${now.getFullYear()}-${now.getMonth()}-${now.getDate()}`;
    const tomorrow = new Date(now); tomorrow.setDate(now.getDate() + 1);
    const dateKey = `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
    const tomorrowKey = `${tomorrow.getFullYear()}-${tomorrow.getMonth()}-${tomorrow.getDate()}`;
    const day = dateKey === todayKey ? 'Hoy' : dateKey === tomorrowKey ? 'Mañana' : new Intl.DateTimeFormat('es-PE', { day: '2-digit', month: 'short', year: date.getFullYear() === now.getFullYear() ? undefined : 'numeric' }).format(date);
    const time = new Intl.DateTimeFormat('es-PE', { hour: '2-digit', minute: '2-digit', hour12: false }).format(date);
    return { day, time };
  }
  function renderReservations() {
    const list = $('#reservationsList');
    if (!list) return;
    const count = $('#reservationCount');
    if (count) count.textContent = `${state.reservations.length} ${state.reservations.length === 1 ? 'reserva' : 'reservas'}`;
    if (!state.reservations.length) {
      list.innerHTML = '<div class="empty-state"><span>▦</span><strong>No hay reservas agendadas</strong><p>Crea una reserva para verla aquí.</p></div>';
      return;
    }
    list.innerHTML = state.reservations.map(reservation => {
      const slot = reservationSlot(reservation.reservation_at);
      const code = reservation.reservation_code || `RES-${reservation.id}`;
      let actions = '';
      if (can('reservations.manage')) {
        if (reservation.status === 'Pendiente') actions = `<button type="button" class="reservation-action-button" data-reservation-status="Confirmada" data-reservation-id="${Number(reservation.id)}">Confirmar</button><button type="button" class="reservation-action-button" data-edit-reservation="${Number(reservation.id)}">Editar</button><button type="button" class="reservation-action-button is-danger" data-reservation-status="Cancelada" data-reservation-id="${Number(reservation.id)}">Cancelar</button>`;
        else if (reservation.status === 'Confirmada') actions = `<button type="button" class="reservation-action-button" data-reservation-status="Sentada" data-reservation-id="${Number(reservation.id)}">Registrar llegada</button><button type="button" class="reservation-action-button" data-edit-reservation="${Number(reservation.id)}">Editar</button><button type="button" class="reservation-action-button is-danger" data-reservation-status="Cancelada" data-reservation-id="${Number(reservation.id)}">Cancelar</button>`;
        else if (reservation.status === 'Sentada') actions = `<button type="button" class="reservation-action-button" data-reservation-status="Completada" data-reservation-id="${Number(reservation.id)}">Completar reserva</button>`;
      }
      return `<article class="reservation-row reservation-row-expanded"><div class="reservation-time"><strong>${escapeHTML(slot.time)}</strong><small>${escapeHTML(slot.day)}</small></div><div class="reservation-main"><strong>${escapeHTML(reservation.customer_name)} · ${escapeHTML(reservation.table_label)}</strong><small>${Number(reservation.guest_count)} ${Number(reservation.guest_count) === 1 ? 'persona' : 'personas'}${reservation.customer_phone ? ' · ' + escapeHTML(reservation.customer_phone) : ''} · ${escapeHTML(code)}</small>${reservation.notes ? `<small>${escapeHTML(reservation.notes)}</small>` : ''}</div><span class="reservation-badge ${reservationStatusClass(reservation.status)}">${escapeHTML(reservationStatusLabel(reservation.status))}</span>${actions ? `<div class="reservation-row-actions">${actions}</div>` : ''}</article>`;
    }).join('');
  }
  async function refreshReservations(showLoading = false) {
    if (!state.currentUser) return;
    if (showLoading) {
      if ($('#reservationsList')) $('#reservationsList').innerHTML = '<div class="manager-loading">Actualizando reservas…</div>';
      if ($('#tablesGrid')) $('#tablesGrid').innerHTML = '<div class="manager-loading">Actualizando mesas…</div>';
    }
    const result = await apiRequest('api/reservations.php?view=overview', { method: 'GET' });
    state.tables = Array.isArray(result.tables) ? result.tables : [];
    state.reservations = Array.isArray(result.reservations) ? result.reservations : [];
    renderTables();
    renderReservations();
  }
  /* Pedidos reales de MySQL, carrito y ventas. */
  function orderStatusClass(status) {
    return ({ 'Preparando': 'status-preparing', 'En reparto': 'status-delivering', 'Listo': 'status-ready', 'Entregado': 'status-ready', 'Pendiente': 'status-pending', 'Cancelado': 'status-cancelled' })[status] || 'status-pending';
  }
  function filterOrders(order) {
    if (state.orderFilter === 'Todos') return true;
    if (state.orderFilter === 'Pendientes') return order.status === 'Pendiente';
    if (state.orderFilter === 'Preparando') return order.status === 'Preparando';
    if (state.orderFilter === 'Listos') return order.status === 'Listo' || order.status === 'Entregado';
    if (state.orderFilter === 'Delivery') return order.order_type === 'Delivery';
    return true;
  }
  function renderOrders() {
    const list = $('#ordersList');
    if (!list) return;
    const filtered = state.orders.filter(filterOrders);
    const perms = state.orderPermissions || {};
    const statusOptions = ['Pendiente', 'Preparando', 'Listo', 'En reparto', 'Entregado'];
    list.innerHTML = filtered.length ? filtered.map(order => {
      const location = order.order_type === 'Mesa' ? (order.table_label || 'Mesa sin asignar') : order.order_type;
      const paymentTag = order.payment_status === 'Pagado'
        ? `<span class="payment-tag payment-paid">Pagado · ${escapeHTML(order.payment_method || '')}</span>`
        : order.payment_status === 'Reembolsado'
          ? '<span class="payment-tag payment-refunded">Reembolsado</span>'
          : `<span class="payment-tag payment-pending">Pago pendiente${order.payment_preference ? ' · Prefiere ' + escapeHTML(order.payment_preference) : ''}</span>`;
      const transitions = order.order_type === 'Delivery'
        ? ({ 'Pendiente': ['Pendiente','Preparando'], 'Preparando': ['Preparando','Listo'], 'Listo': ['Listo'], 'En reparto': ['En reparto'], 'Entregado': ['Entregado'] })[order.status] || [order.status]
        : ({ 'Pendiente': ['Pendiente','Preparando'], 'Preparando': ['Preparando','Listo'], 'Listo': ['Listo','Entregado'], 'Entregado': ['Entregado'] })[order.status] || [order.status];
      const statusSelect = perms.can_update && order.status !== 'Cancelado'
        ? `<label class="order-status-control"><span>Estado</span><select data-order-status="${Number(order.id)}" aria-label="Cambiar estado de ${escapeHTML(order.order_number)}">${transitions.map(status => `<option value="${status}" ${status === order.status ? 'selected' : ''}>${status}</option>`).join('')}</select></label>`
        : '';
      const payButton = perms.can_cash && perms.can_pay && order.payment_status === 'Pendiente' && order.status !== 'Cancelado'
        ? `<button type="button" class="order-action-button" data-order-pay="${Number(order.id)}">Cobrar</button>` : '';
      const refundButton = perms.can_cash && perms.can_cancel && order.payment_status === 'Pagado' && order.status !== 'Cancelado'
        ? `<button type="button" class="order-action-button is-warning" data-order-refund="${Number(order.id)}">Reembolsar</button>` : '';
      const cancelButton = perms.can_cancel && order.status !== 'Cancelado' && order.payment_status !== 'Pagado'
        ? `<button type="button" class="order-action-button is-danger" data-order-cancel="${Number(order.id)}">Cancelar</button>` : '';
      const cancelAfterRefund = perms.can_cancel && order.status !== 'Cancelado' && order.payment_status === 'Reembolsado'
        ? `<button type="button" class="order-action-button is-danger" data-order-cancel="${Number(order.id)}">Cancelar</button>` : '';
      return `<article class="white-card order-card order-card-live">
        <div class="order-left"><div class="order-symbol ${order.status === 'En reparto' ? 'symbol-green' : order.status === 'Listo' || order.status === 'Entregado' ? 'symbol-cyan' : order.status === 'Pendiente' || order.status === 'Cancelado' ? 'symbol-red' : ''}">${order.order_type === 'Delivery' ? '⌁' : order.order_type === 'Mesa' ? '▤' : '▣'}</div>
        <div class="order-details"><strong class="order-number">#${escapeHTML(order.order_number || ('DES-' + order.id))}</strong><span class="order-location">${escapeHTML(location)} · ${escapeHTML(order.customer_name || 'Consumidor final')}</span><p title="${escapeHTML(order.item_summary || '')}">${escapeHTML(order.item_summary || 'Sin detalle')}</p><div class="order-minor-meta">${paymentTag}<small>${escapeHTML(order.created_at || '')}</small></div>${order.promotion_code ? `<small class="order-promotion-code">Cupón aplicado: ${escapeHTML(order.promotion_code)}</small>` : ''}${Number(order.discount || 0) > 0 ? `<small class="order-promotion-code">Descuento: −${money(order.discount)}</small>` : ''}${order.notes ? `<small class="order-notes">${escapeHTML(order.notes)}</small>` : ''}</div></div>
        <div class="order-right"><strong class="order-total">${money(order.total)}</strong><span class="status-tag ${orderStatusClass(order.status)}">${escapeHTML(order.status)}</span>${statusSelect}<div class="order-actions">${payButton}${refundButton}${cancelButton}${cancelAfterRefund}</div></div>
      </article>`;
    }).join('') : `<div class="empty-state"><span>▤</span><strong>${state.orders.length ? 'No hay pedidos en este filtro' : 'Todavía no hay pedidos guardados'}</strong><p>${state.orders.length ? 'Prueba otro filtro.' : 'Crea un pedido para comenzar a registrar ventas reales.'}</p></div>`;
  }
  async function refreshOrders() {
    if (!can('orders.view')) return;
    const result = await apiRequest('api/orders.php?view=orders', { method: 'GET' });
    state.orders = result.orders || [];
    state.orderPermissions = result.permissions || state.orderPermissions;
    renderOrders();
    updateDashboardStats(result.stats || {});
  }
  async function refreshDashboardStats() {
    if (!state.currentUser) return;
    try {
      const result = await apiRequest('api/orders.php?view=stats', { method: 'GET' });
      updateDashboardStats(result.stats || {});
    } catch (_) { /* El panel mantiene su estado si un rol no tiene permiso de consulta. */ }
  }
  function updateDashboardStats(stats) {
    const sales = $('#salesTodayValue');
    const orders = $('#ordersTodayValue');
    const pending = $('#pendingOrdersBadge');
    const average = $('#averageTicketValue');
    if (sales) sales.textContent = money(stats.sales_today || 0);
    if (orders) orders.textContent = String(stats.orders_today || 0);
    if (pending) pending.textContent = `${Number(stats.pending_orders || 0)} pendientes`;
    if (average) average.textContent = money(stats.average_ticket || 0);
  }
  function initOrderFilters() {
    $('#orderFilters').addEventListener('click', event => {
      const button = event.target.closest('[data-order-filter]');
      if (!button) return;
      state.orderFilter = button.dataset.orderFilter;
      $$('#orderFilters .filter-chip').forEach(chip => chip.classList.toggle('is-selected', chip === button));
      renderOrders();
    });
    $('#newOrderButton').addEventListener('click', openNewOrderModal);
    $('#quickSale').addEventListener('click', openNewOrderModal);
    $('#cashManagementButton')?.addEventListener('click', openCashManagementModal);
    $('#paymentHistoryButton')?.addEventListener('click', openPaymentHistoryModal);
    $('#invoiceManagementButton')?.addEventListener('click', () => switchView('billing'));
    $('#reportsManagementButton')?.addEventListener('click', () => switchView('reports'));
    $('#ordersList').addEventListener('change', async event => {
      const select = event.target.closest('[data-order-status]');
      if (!select) return;
      const previous = state.orders.find(row => Number(row.id) === Number(select.dataset.orderStatus))?.status;
      try {
        await apiRequest('api/orders.php', { method: 'POST', body: JSON.stringify({ action: 'status', id: Number(select.dataset.orderStatus), status: select.value }) });
        showToast('Pedido actualizado', `Estado cambiado a ${select.value}.`);
        await refreshOrders();
      } catch (error) {
        showToast('No se pudo cambiar el estado', error.message, '!');
        select.value = previous || 'Pendiente';
      }
    });
    $('#ordersList').addEventListener('click', async event => {
      const pay = event.target.closest('[data-order-pay]');
      const refund = event.target.closest('[data-order-refund]');
      const cancel = event.target.closest('[data-order-cancel]');
      if (pay) return openOrderPaymentModal(Number(pay.dataset.orderPay));
      if (refund) return refundOrder(Number(refund.dataset.orderRefund));
      if (cancel) return cancelOrder(Number(cancel.dataset.orderCancel));
    });
  }

  async function openNewOrderModal() {
    if (!can('orders.create')) return showToast('Acceso restringido', 'Tu rol no puede crear pedidos.', '!');
    let availableProducts = state.products.filter(product => product.status === 'available');
    let availableCustomers = [];
    try {
      const data = await apiRequest('api/orders.php?view=products', { method: 'GET' });
      availableProducts = data.products || [];
      state.products = availableProducts;
    } catch (error) {
      return showToast('No se pudo cargar el menú', error.message, '!');
    }
    if (can('customers.view') || can('customers.manage') || can('customer_addresses.manage')) {
      try { const customerData = await apiRequest('api/customers.php?view=list', { method: 'GET' }); availableCustomers = (customerData.customers || []).filter(item => item.status === 'active'); state.customers = customerData.customers || []; }
      catch (_) { availableCustomers = []; }
    }
    if (!availableProducts.length) return showToast('Menú vacío', 'Registra productos disponibles antes de crear una venta.', '!');
    let cart = [];
    const productOptions = availableProducts.map(product => `<option value="${Number(product.id)}">${escapeHTML(product.emoji || '🍽️')} ${escapeHTML(product.name)} — ${money(product.price)}${Number(product.recipe_count) === 0 ? ' · sin receta' : ''}</option>`).join('');
    const fields = `
      <div class="sales-form-grid">
        <div class="form-field form-field-wide"><label for="orderCustomerSelect">Cliente guardado (opcional)</label><select id="orderCustomerSelect"><option value="">Consumidor final / ingresar nombre manual</option>${availableCustomers.map(customer => `<option value="${Number(customer.id)}">${escapeHTML(customer.full_name)}${customer.phone ? ` · ${escapeHTML(customer.phone)}` : ""}</option>`).join("")}</select></div><div class="form-field"><label for="orderCustomer">Nombre para el pedido</label><input id="orderCustomer" maxlength="100" placeholder="Consumidor final" value="Consumidor final"></div>
        <div class="form-field"><label for="orderType">Tipo de pedido</label><select id="orderType"><option>Mesa</option><option>Delivery</option><option>Para llevar</option></select></div>
        <div class="form-field" id="orderTableField"><label for="orderTableLabel">Mesa</label><input id="orderTableLabel" maxlength="40" placeholder="Mesa 01" value="Mesa 01"></div>
        <div class="form-field"><label for="orderDiscount">Descuento (S/)</label><input id="orderDiscount" type="number" min="0" step="0.01" value="0"></div>
        <div id="orderDeliveryFields" class="form-field-wide delivery-order-fields is-hidden">
          <div class="sales-form-grid delivery-order-grid">
            <div class="form-field"><label for="orderDeliveryPhone">Teléfono del destinatario *</label><input id="orderDeliveryPhone" maxlength="30" placeholder="999 999 999"></div>
            <div class="form-field"><label for="orderDeliveryZone">Zona / distrito</label><input id="orderDeliveryZone" maxlength="100" placeholder="Ej. Huaycán"></div>
            <div class="form-field form-field-wide is-hidden" id="orderSavedAddressField"><label for="orderDeliverySavedAddress">Dirección guardada del cliente</label><select id="orderDeliverySavedAddress"><option value="">Elegir dirección guardada…</option></select></div><div class="form-field form-field-wide"><label for="orderDeliveryAddress">Dirección de entrega *</label><input id="orderDeliveryAddress" maxlength="255" placeholder="Calle, número, referencia"></div>
            <div class="form-field"><label for="orderDeliveryFee">Costo de envío (S/)</label><input id="orderDeliveryFee" type="number" min="0" max="1000" step="0.01" value="0"></div>
          </div>
        </div>
        <div class="form-field form-field-wide"><label for="orderNotes">Notas de preparación (opcional)</label><input id="orderNotes" maxlength="500" placeholder="Sin cebolla, bien picante…"></div>
      </div>
      <div class="sales-cart-add"><div class="form-field"><label for="orderProduct">Producto</label><select id="orderProduct" required>${productOptions}</select></div><div class="form-field sales-quantity"><label for="orderQuantity">Cant.</label><input id="orderQuantity" type="number" min="1" max="100" step="1" value="1"></div><button type="button" class="secondary-button" id="addCartItem">+ Agregar</button></div>
      <p id="salesFormError" class="modal-alert" role="alert"></p>
      <div class="sales-cart-wrap"><div class="sales-cart-heading"><strong>Carrito</strong><span id="orderCartCount">0 productos</span></div><div id="orderCartList" class="sales-cart-list"><p class="sales-cart-empty">Agrega productos al pedido.</p></div></div>
      <div class="sales-total-box"><div><span>Subtotal</span><strong id="orderSubtotal">S/ 0.00</strong></div><div><span>Descuento</span><strong id="orderDiscountView">S/ 0.00</strong></div><div id="orderDeliveryFeeRow" class="is-hidden"><span>Envío</span><strong id="orderDeliveryFeeView">S/ 0.00</strong></div><div class="sales-grand-total"><span>Total</span><strong id="orderTotal">S/ 0.00</strong></div></div>
      <div class="form-field"><label for="orderPaymentStatus">Cobro</label><select id="orderPaymentStatus"><option value="pending">Registrar pedido · pago pendiente</option><option value="paid">Cobrar ahora</option></select><small class="cash-hint">Para cobrar ahora, abre Caja e inicia una sesión.</small></div>
      <div class="form-field is-hidden" id="orderPaymentMethodField"><label for="orderPaymentMethod">Medio de pago</label><select id="orderPaymentMethod"><option>Efectivo</option><option>Yape/Plin</option><option>Tarjeta</option><option>Transferencia</option></select></div>`;
    openWideModal(modalShell('PEDIDOS Y CAJA', 'Nuevo pedido', 'El pedido, sus productos y el consumo de recetas se guardarán en MySQL.', 'newOrderForm', fields, 'Registrar pedido'));

    const renderCart = () => {
      const list = $('#orderCartList');
      if (!cart.length) list.innerHTML = '<p class="sales-cart-empty">Agrega productos al pedido.</p>';
      else list.innerHTML = cart.map((line, index) => `<div class="sales-cart-row"><div><strong>${escapeHTML(line.name)}</strong><small>${line.quantity} × ${money(line.price)}${line.recipe_count === 0 ? ' · <b class="recipe-warning">sin receta</b>' : ''}</small></div><strong>${money(line.quantity * line.price)}</strong><button type="button" data-remove-cart="${index}" aria-label="Quitar ${escapeHTML(line.name)}">×</button></div>`).join('');
      const count = cart.reduce((sum, line) => sum + line.quantity, 0);
      const subtotal = cart.reduce((sum, line) => sum + line.quantity * line.price, 0);
      const discount = Math.min(Math.max(Number($('#orderDiscount')?.value || 0), 0), subtotal);
      const deliveryFee = $('#orderType')?.value === 'Delivery' ? Math.max(0, Number($('#orderDeliveryFee')?.value || 0)) : 0;
      $('#orderCartCount').textContent = `${count} ${count === 1 ? 'producto' : 'productos'}`;
      $('#orderSubtotal').textContent = money(subtotal);
      $('#orderDiscountView').textContent = money(discount);
      $('#orderTotal').textContent = money(Math.max(0, subtotal - discount + deliveryFee));
      const feeRow = $('#orderDeliveryFeeRow');
      if (feeRow) { feeRow.classList.toggle('is-hidden', $('#orderType')?.value !== 'Delivery'); $('#orderDeliveryFeeView').textContent = money(deliveryFee); }
    };
    $('#addCartItem').addEventListener('click', () => {
      const product = availableProducts.find(item => Number(item.id) === Number($('#orderProduct').value));
      const qty = Number($('#orderQuantity').value);
      if (!product || !Number.isInteger(qty) || qty < 1 || qty > 100) { $('#salesFormError').textContent = 'Selecciona un producto y una cantidad entre 1 y 100.'; return; }
      const line = cart.find(item => item.product_id === Number(product.id));
      if (line) {
        if (line.quantity + qty > 100) { $('#salesFormError').textContent = 'La cantidad máxima por producto es 100.'; return; }
        line.quantity += qty;
      } else cart.push({ product_id: Number(product.id), name: product.name, price: Number(product.price), quantity: qty, recipe_count: Number(product.recipe_count || 0) });
      $('#salesFormError').textContent = '';
      $('#orderQuantity').value = '1';
      renderCart();
    });
    $('#orderCartList').addEventListener('click', event => {
      const remove = event.target.closest('[data-remove-cart]');
      if (!remove) return;
      cart.splice(Number(remove.dataset.removeCart), 1);
      renderCart();
    });
    $('#orderDiscount').addEventListener('input', renderCart);
    $('#orderDeliveryFee').addEventListener('input', renderCart);
    const updateOrderTypeFields = () => {
      const type = $('#orderType').value;
      $('#orderTableField').classList.toggle('is-hidden', type !== 'Mesa');
      $('#orderDeliveryFields').classList.toggle('is-hidden', type !== 'Delivery');
      $('#orderDeliveryPhone').required = type === 'Delivery';
      $('#orderDeliveryAddress').required = type === 'Delivery';
      renderCart();
    };
    $('#orderType').addEventListener('change', updateOrderTypeFields);
    updateOrderTypeFields();
    let selectedCustomerAddresses = [];
    const fillDeliveryAddress = address => {
      if (!address) return;
      $('#orderDeliveryPhone').value = address.phone || $('#orderDeliveryPhone').value;
      $('#orderDeliveryZone').value = [address.district, address.province].filter(Boolean).join(', ');
      $('#orderDeliveryAddress').value = [address.address_line, address.reference].filter(Boolean).join(' · ');
    };
    $('#orderCustomerSelect')?.addEventListener('change', async () => {
      const customer = availableCustomers.find(item => Number(item.id) === Number($('#orderCustomerSelect').value));
      $('#orderCustomer').value = customer ? customer.full_name : 'Consumidor final';
      if (customer?.phone) $('#orderDeliveryPhone').value = customer.phone;
      selectedCustomerAddresses = [];
      const addressField = $('#orderSavedAddressField');
      if (!customer || !addressField) { addressField?.classList.add('is-hidden'); $('#orderDeliverySavedAddress').innerHTML = '<option value="">Elegir dirección guardada…</option>'; return; }
      try {
        const details = await apiRequest(`api/customers.php?view=detail&id=${Number(customer.id)}`, { method: 'GET' });
        selectedCustomerAddresses = (details.addresses || []).filter(address => address.status === 'active');
        $('#orderDeliverySavedAddress').innerHTML = '<option value="">Elegir dirección guardada…</option>' + selectedCustomerAddresses.map(address => `<option value="${Number(address.id)}" ${Number(address.is_default) === 1 ? 'selected' : ''}>${escapeHTML(address.label)} · ${escapeHTML(address.address_line)}${address.district ? ` · ${escapeHTML(address.district)}` : ''}</option>`).join('');
        addressField.classList.toggle('is-hidden', selectedCustomerAddresses.length === 0);
        const preferred = selectedCustomerAddresses.find(address => Number(address.is_default) === 1) || selectedCustomerAddresses[0];
        if (preferred) { $('#orderDeliverySavedAddress').value = String(preferred.id); fillDeliveryAddress(preferred); }
      } catch (error) { showToast('No se pudieron cargar las direcciones', error.message, '!'); }
    });
    $('#orderDeliverySavedAddress')?.addEventListener('change', () => fillDeliveryAddress(selectedCustomerAddresses.find(address => Number(address.id) === Number($('#orderDeliverySavedAddress').value))));
    $('#orderPaymentStatus').addEventListener('change', () => $('#orderPaymentMethodField').classList.toggle('is-hidden', $('#orderPaymentStatus').value !== 'paid'));
    $('#newOrderForm').addEventListener('submit', async event => {
      event.preventDefault();
      const errorBox = $('#salesFormError');
      errorBox.textContent = '';
      if (!cart.length) { errorBox.textContent = 'Agrega al menos un producto al carrito.'; return; }
      const submit = $('#newOrderForm button[type="submit"]'); submit.disabled = true;
      try {
        const result = await apiRequest('api/orders.php', { method: 'POST', body: JSON.stringify({
          action: 'create', customer_id: Number($('#orderCustomerSelect')?.value || 0) || null, customer_name: $('#orderCustomer').value.trim() || 'Consumidor final', order_type: $('#orderType').value,
          table_label: $('#orderType').value === 'Mesa' ? $('#orderTableLabel').value.trim() : '', notes: $('#orderNotes').value.trim(),
          delivery: { customer_address_id: $('#orderType').value === 'Delivery' ? (Number($('#orderDeliverySavedAddress')?.value || 0) || null) : null, recipient_phone: $('#orderDeliveryPhone').value.trim(), address_line: $('#orderDeliveryAddress').value.trim(), zone: $('#orderDeliveryZone').value.trim() },
          delivery_fee: $('#orderType').value === 'Delivery' ? Number($('#orderDeliveryFee').value || 0) : 0,
          discount: Number($('#orderDiscount').value || 0), payment_status: $('#orderPaymentStatus').value, payment_method: $('#orderPaymentMethod').value,
          items: cart.map(line => ({ product_id: line.product_id, quantity: line.quantity }))
        }) });
        closeModal();
        switchView('orders');
        await refreshOrders();
        await refreshDashboardStats();
        showToast('Pedido registrado', `${result.order_number} · ${money(result.total)}. Se descontó el consumo de sus recetas.`);
      } catch (error) { errorBox.textContent = error.message; }
      finally { submit.disabled = false; }
    });
    renderCart();
  }

  async function openOrderPaymentModal(orderId) {
    const order = state.orders.find(row => Number(row.id) === Number(orderId));
    if (!order) return;
    const fields = `<div class="form-field"><label for="orderPayMethod">Medio de pago</label><select id="orderPayMethod"><option>Efectivo</option><option>Yape/Plin</option><option>Tarjeta</option><option>Transferencia</option></select></div><div class="cash-hint">Importe a cobrar: <strong>${money(order.total)}</strong>. Debe existir una caja abierta.</div>`;
    openModal(modalShell('COBRO', `Cobrar ${escapeHTML(order.order_number)}`, 'El cobro se registrará en la sesión de caja abierta.', 'orderPaymentForm', fields, 'Confirmar cobro'));
    $('#orderPaymentForm').addEventListener('submit', async event => {
      event.preventDefault(); const submit = $('#orderPaymentForm button[type="submit"]'); submit.disabled = true;
      try {
        const result = await apiRequest('api/orders.php', { method: 'POST', body: JSON.stringify({ action: 'pay', id: orderId, payment_method: $('#orderPayMethod').value }) });
        closeModal(); await refreshOrders(); await refreshDashboardStats(); showToast('Cobro registrado', result.message);
      } catch (error) { showModalError(error.message); }
      finally { submit.disabled = false; }
    });
  }
  async function refundOrder(orderId) {
    const order = state.orders.find(row => Number(row.id) === Number(orderId));
    if (!order || !window.confirm(`¿Registrar un reembolso de ${money(order.total)} para ${order.order_number}? Debe haber una caja abierta.`)) return;
    try {
      const result = await apiRequest('api/orders.php', { method: 'POST', body: JSON.stringify({ action: 'refund', id: orderId }) });
      await refreshOrders(); await refreshDashboardStats(); showToast('Reembolso registrado', result.message, '!');
    } catch (error) { showToast('No se pudo reembolsar', error.message, '!'); }
  }
  async function cancelOrder(orderId) {
    const order = state.orders.find(row => Number(row.id) === Number(orderId));
    if (!order || !window.confirm(`¿Cancelar ${order.order_number}? Si aún está pendiente, su stock se restaurará. Si ya pasó a preparación, no se devolverá automáticamente el consumo.`)) return;
    try {
      const result = await apiRequest('api/orders.php', { method: 'POST', body: JSON.stringify({ action: 'cancel', id: orderId, reason: 'Cancelado desde el centro de pedidos' }) });
      await refreshOrders(); await refreshDashboardStats(); showToast('Pedido cancelado', result.message, '!');
    } catch (error) { showToast('No se pudo cancelar', error.message, '!'); }
  }

  async function openCashManagementModal() {
    if (!can('cash.manage')) return showToast('Acceso restringido', 'Tu rol no tiene permisos de caja.', '!');
    try {
      const data = await apiRequest('api/cash.php', { method: 'GET' });
      const session = data.session;
      const totals = data.totals || {};
      const history = (data.history || []).map(row => `<div class="cash-history-row"><div><strong>Caja #${Number(row.id)}</strong><small>${escapeHTML(row.opened_at || '')} · ${escapeHTML(row.opened_by_name || '')}</small></div><span class="cash-history-status ${row.status === 'open' ? 'is-open' : ''}">${row.status === 'open' ? 'Abierta' : 'Cerrada'}</span><strong>${row.counted_closing === null ? 'En curso' : money(row.counted_closing)}</strong></div>`).join('');
      let content = `<span class="modal-eyebrow">CONTROL DE CAJA</span><h2 class="modal-title">${session ? 'Caja abierta' : 'Abrir caja'}</h2><p class="modal-description">Registro real de apertura, cobros, movimientos, cierre y diferencias.</p>`;
      if (!session) {
        content += `<form id="openCashForm" class="modal-form"><div class="cash-status-panel is-closed"><span class="cash-status-dot"></span><div><strong>No hay caja abierta</strong><small>Abre una sesión para registrar ventas cobradas y movimientos de efectivo.</small></div></div><div class="form-field"><label for="cashOpeningAmount">Efectivo inicial (S/)</label><input id="cashOpeningAmount" type="number" min="0" step="0.01" value="0.00" required></div><div class="form-field"><label for="cashOpeningNotes">Observaciones</label><input id="cashOpeningNotes" maxlength="500" placeholder="Fondo inicial de caja"></div><div class="modal-actions"><button type="submit" class="modal-primary">Abrir caja</button></div></form>`;
      } else {
        content += `<div class="cash-status-panel"><span class="cash-status-dot"></span><div><strong>Sesión #${Number(session.id)} · En curso</strong><small>Abierta por ${escapeHTML(session.opened_by_name)} · ${escapeHTML(session.opened_at)}</small></div></div>
          <div class="cash-metrics-grid"><div><span>Fondo inicial</span><strong>${money(session.opening_amount)}</strong></div><div><span>Ventas efectivo netas</span><strong>${money(totals.cash_net)}</strong></div><div><span>Ventas digitales netas</span><strong>${money(totals.digital_net)}</strong></div><div><span>Ingresos / egresos</span><strong>${money(totals.incomes)} / ${money(totals.expenses)}</strong></div><div class="cash-metric-expected"><span>Efectivo esperado</span><strong>${money(totals.expected_closing)}</strong></div></div>
          <details class="cash-detail"><summary>Registrar ingreso o egreso</summary><form id="cashMovementForm" class="modal-form"><div class="form-field"><label for="cashMoveType">Movimiento</label><select id="cashMoveType"><option value="income">Ingreso de efectivo</option><option value="expense">Egreso de efectivo</option></select></div><div class="form-field"><label for="cashMoveAmount">Monto (S/)</label><input id="cashMoveAmount" type="number" min="0.01" step="0.01" required></div><div class="form-field"><label for="cashMoveReference">Referencia (opcional)</label><input id="cashMoveReference" maxlength="120" placeholder="Boleta, compra, etc."></div><div class="form-field"><label for="cashMoveNotes">Descripción</label><input id="cashMoveNotes" maxlength="500" required placeholder="Motivo del movimiento"></div><button type="submit" class="secondary-button">Guardar movimiento</button></form></details>
          <details class="cash-detail" open><summary>Cerrar caja y cuadrar efectivo</summary><form id="closeCashForm" class="modal-form"><div class="form-field"><label for="cashCountedAmount">Efectivo contado (S/)</label><input id="cashCountedAmount" type="number" min="0" step="0.01" value="${Number(totals.expected_closing).toFixed(2)}" required></div><div class="form-field"><label for="cashClosingNotes">Observaciones de cierre</label><input id="cashClosingNotes" maxlength="500" placeholder="Sin incidencias"></div><p class="cash-hint">La diferencia será efectivo contado menos efectivo esperado.</p><button type="submit" class="modal-primary">Cerrar caja</button></form></details>`;
      }
      content += `<div class="cash-history"><h3>Sesiones recientes</h3>${history || '<p class="sales-cart-empty">Aún no hay sesiones registradas.</p>'}</div>`;
      openWideModal(content);
      if (!session) {
        $('#openCashForm').addEventListener('submit', async event => {
          event.preventDefault(); const submit = $('#openCashForm button[type="submit"]'); submit.disabled = true;
          try {
            const result = await apiRequest('api/cash.php', { method: 'POST', body: JSON.stringify({ action: 'open', opening_amount: Number($('#cashOpeningAmount').value), notes: $('#cashOpeningNotes').value.trim() }) });
            showToast('Caja abierta', result.message); await openCashManagementModal();
          } catch (error) { showModalError(error.message); }
          finally { submit.disabled = false; }
        });
      } else {
        $('#cashMovementForm').addEventListener('submit', async event => {
          event.preventDefault(); const submit = $('#cashMovementForm button[type="submit"]'); submit.disabled = true;
          try {
            const result = await apiRequest('api/cash.php', { method: 'POST', body: JSON.stringify({ action: 'movement', movement_type: $('#cashMoveType').value, amount: Number($('#cashMoveAmount').value), reference: $('#cashMoveReference').value.trim(), notes: $('#cashMoveNotes').value.trim() }) });
            showToast('Caja actualizada', result.message); await openCashManagementModal();
          } catch (error) { showModalError(error.message); }
          finally { submit.disabled = false; }
        });
        $('#closeCashForm').addEventListener('submit', async event => {
          event.preventDefault();
          if (!window.confirm('¿Confirmas el cierre de caja? Después no podrás registrar más cobros en esta sesión.')) return;
          const submit = $('#closeCashForm button[type="submit"]'); submit.disabled = true;
          try {
            const result = await apiRequest('api/cash.php', { method: 'POST', body: JSON.stringify({ action: 'close', counted_closing: Number($('#cashCountedAmount').value), notes: $('#cashClosingNotes').value.trim() }) });
            closeModal(); showToast('Caja cerrada', result.message, Math.abs(Number(result.difference)) > 0.009 ? '!' : '✓'); await refreshDashboardStats();
          } catch (error) { showModalError(error.message); }
          finally { submit.disabled = false; }
        });
      }
    } catch (error) { showToast('No se pudo abrir Caja', error.message, '!'); }
  }

  /* Catálogo real: productos y categorías desde MySQL. */
  function renderMenuFilters() {
    const filter = $('#menuFilters');
    const activeValue = state.menuFilter || 'Todos';
    filter.innerHTML = `<button class="filter-chip ${activeValue === 'Todos' ? 'is-selected' : ''}" data-menu-filter="Todos">Todos</button>` +
      state.categories.map(category => `<button class="filter-chip ${String(category.name) === activeValue ? 'is-selected' : ''}" data-menu-filter="${escapeHTML(category.name)}">${escapeHTML(category.name)}${category.status === 'inactive' ? ' · inactiva' : ''}</button>`).join('');
    if (activeValue !== 'Todos' && !state.categories.some(item => item.name === activeValue)) state.menuFilter = 'Todos';
    $$('#menuFilters .filter-chip').forEach(chip => chip.classList.toggle('is-selected', chip.dataset.menuFilter === state.menuFilter));
  }
  function renderProducts() {
    const grid = $('#productsGrid');
    const products = state.products.filter(product => {
      const categoryMatch = state.menuFilter === 'Todos' || product.category === state.menuFilter;
      const text = `${product.name || ''} ${product.description || ''} ${product.allergens || ''}`.toLocaleLowerCase('es');
      const searchMatch = text.includes(state.search.toLocaleLowerCase('es').trim());
      return categoryMatch && searchMatch;
    });
    const artByCategory = { Entradas: 'art-cyan', Platos: '', Bebidas: 'art-green', Postres: 'art-rose' };
    grid.innerHTML = products.map(product => {
      const available = product.status === 'available';
      const canEdit = can('products.manage');
      const ingredientNames = (product.ingredients || []).map(item => item.name).join(', ');
      return `<article class="white-card product-card ${available ? '' : 'product-unavailable'}">
        <div class="product-art ${escapeHTML(artByCategory[product.category] || '')}"><span>${escapeHTML(product.emoji || '🍽️')}</span><span class="product-available ${available ? '' : 'is-unavailable'}">${available ? 'Disponible' : 'No disponible'}</span></div>
        <div class="product-info">
          <div class="product-title-row"><h3>${escapeHTML(product.name)}</h3><strong class="product-price">${money(product.price)}</strong></div>
          <div class="product-meta-line"><span class="product-category-label">${escapeHTML(product.category || 'Sin categoría')}</span><span class="product-rating">${(product.allergen_list || []).length ? '⚠' : '✓'} ${available ? 'Activo' : 'Pausado'}</span></div>
          <p class="product-description">${escapeHTML(product.description || 'Producto disponible en el menú.')}</p>
          ${ingredientNames ? `<p class="product-recipe"><strong>Receta:</strong> ${escapeHTML(ingredientNames)}</p>` : '<p class="product-recipe product-recipe-warning">Receta no configurada</p>'}
          <span class="allergen-chip ${(product.allergen_list || []).length ? '' : 'is-safe'}">${escapeHTML(product.allergens || 'Sin alérgenos registrados')}</span>
          ${canEdit ? `<div class="product-card-actions"><button type="button" class="secondary-button" data-edit-product="${Number(product.id)}">Editar</button><button type="button" class="secondary-button" data-toggle-product="${Number(product.id)}" data-next-status="${available ? 'unavailable' : 'available'}">${available ? 'Pausar' : 'Activar'}</button></div>` : ''}
        </div></article>`;
    }).join('');
    $('#noProducts').classList.toggle('is-hidden', products.length > 0);
  }
  async function refreshCatalogMenu(showLoading = false) {
    const loading = $('#catalogLoading');
    if (showLoading && loading) loading.classList.remove('is-hidden');
    try {
      const result = await apiRequest('api/catalog.php?view=menu', { method: 'GET' });
      state.categories = Array.isArray(result.categories) ? result.categories : [];
      state.products = Array.isArray(result.products) ? result.products : [];
      renderMenuFilters();
      renderProducts();
    } finally {
      if (loading) loading.classList.add('is-hidden');
    }
  }
  async function refreshIngredients() {
    const result = await apiRequest('api/catalog.php?view=ingredients', { method: 'GET' });
    state.ingredients = Array.isArray(result.ingredients) ? result.ingredients : [];
    state.allergens = Array.isArray(result.allergens) ? result.allergens : [];
    return result;
  }
  /* Paso 4: cola de cocina KDS y seguimiento de delivery, con datos del servidor. */
  function initOperationsActions() {
    $('#kitchenBoardButton')?.addEventListener('click', () => switchView('kitchen'));
    $('#deliveryBoardButton')?.addEventListener('click', () => switchView('delivery'));
    $('#kitchenRefreshButton')?.addEventListener('click', () => refreshKitchenBoard().catch(error => showToast('No se pudo actualizar cocina', error.message, '!')));
    $('#deliveryRefreshButton')?.addEventListener('click', () => refreshDeliveryBoard().catch(error => showToast('No se pudo actualizar delivery', error.message, '!')));

    $('#kitchenBoard')?.addEventListener('click', async event => {
      const action = event.target.closest('[data-kitchen-next]');
      if (!action) return;
      action.disabled = true;
      try {
        const result = await apiRequest('api/operations.php', { method: 'POST', body: JSON.stringify({ action: 'kitchen_status', id: Number(action.dataset.orderId), status: action.dataset.kitchenNext }) });
        showToast('Cocina actualizada', result.message);
        await Promise.all([refreshKitchenBoard(), refreshOrders(), refreshDashboardStats()]);
      } catch (error) { showToast('No se pudo cambiar el estado', error.message, '!'); }
      finally { action.disabled = false; }
    });

    $('#deliveryBoard')?.addEventListener('click', async event => {
      const edit = event.target.closest('[data-edit-delivery]');
      const status = event.target.closest('[data-delivery-status]');
      const incident = event.target.closest('[data-delivery-incident]');
      if (edit) return openDeliveryEditModal(Number(edit.dataset.editDelivery));
      if (incident) return openDeliveryIncidentModal(Number(incident.dataset.deliveryIncident));
      if (!status) return;
      const orderId = Number(status.dataset.orderId);
      const nextStatus = status.dataset.deliveryStatus;
      if (nextStatus === 'Incidencia') return openDeliveryIncidentModal(orderId);
      status.disabled = true;
      try {
        const result = await apiRequest('api/operations.php', { method: 'POST', body: JSON.stringify({ action: 'delivery_status', id: orderId, status: nextStatus }) });
        showToast('Delivery actualizado', result.message);
        await Promise.all([refreshDeliveryBoard(), refreshOrders(), refreshDashboardStats()]);
      } catch (error) { showToast('No se pudo actualizar la entrega', error.message, '!'); }
      finally { status.disabled = false; }
    });
  }

  async function refreshKitchenBoard() {
    if (!can('kitchen.view')) return;
    const result = await apiRequest('api/operations.php?view=kitchen', { method: 'GET' });
    state.kitchenOrders = result.orders || [];
    state.operationPermissions.kitchenManage = Boolean(result.permissions?.can_manage);
    renderKitchenBoard();
  }

  function renderKitchenBoard() {
    const board = $('#kitchenBoard');
    if (!board) return;
    const orders = state.kitchenOrders || [];
    const lanes = [
      { status: 'Pendiente', title: 'Nuevos', subtitle: 'Por iniciar', className: 'lane-pending' },
      { status: 'Preparando', title: 'Preparando', subtitle: 'En cocina', className: 'lane-preparing' },
      { status: 'Listo', title: 'Listos', subtitle: 'Esperando entrega', className: 'lane-ready' }
    ];
    const countBy = status => orders.filter(order => order.status === status).length;
    $('#kitchenSummary').innerHTML = `
      <article class="operations-metric"><span class="operations-metric-icon metric-blue">▤</span><div><small>En cola</small><strong>${orders.length}</strong></div></article>
      <article class="operations-metric"><span class="operations-metric-icon metric-red">◷</span><div><small>Por iniciar</small><strong>${countBy('Pendiente')}</strong></div></article>
      <article class="operations-metric"><span class="operations-metric-icon metric-amber">♨</span><div><small>Preparando</small><strong>${countBy('Preparando')}</strong></div></article>
      <article class="operations-metric"><span class="operations-metric-icon metric-green">✓</span><div><small>Listos</small><strong>${countBy('Listo')}</strong></div></article>`;
    board.innerHTML = lanes.map(lane => {
      const laneOrders = orders.filter(order => order.status === lane.status);
      const cards = laneOrders.map(order => {
        const location = order.order_type === 'Mesa' ? (order.table_label || 'Mesa sin asignar') : order.order_type;
        const minutes = Math.max(0, Number(order.elapsed_minutes || 0));
        const ageLabel = minutes < 1 ? 'Hace un momento' : `Hace ${minutes} min`;
        const nextStatus = lane.status === 'Pendiente' ? 'Preparando' : lane.status === 'Preparando' ? 'Listo' : '';
        const buttonLabel = lane.status === 'Pendiente' ? 'Iniciar preparación →' : lane.status === 'Preparando' ? 'Marcar como listo ✓' : '';
        const action = nextStatus && state.operationPermissions.kitchenManage
          ? `<button type="button" class="operation-action-button ${lane.status === 'Preparando' ? 'action-success' : ''}" data-order-id="${Number(order.id)}" data-kitchen-next="${nextStatus}">${buttonLabel}</button>`
          : lane.status === 'Listo' && order.order_type === 'Delivery' && can('delivery.view')
            ? `<button type="button" class="operation-action-button" data-go-delivery>Ver delivery →</button>` : '';
        return `<article class="kitchen-order-card">
          <div class="kitchen-card-head"><strong>#${escapeHTML(order.order_number || `DES-${order.id}`)}</strong><span class="elapsed-chip ${minutes >= 20 ? 'is-overdue' : ''}">◷ ${ageLabel}</span></div>
          <div class="kitchen-order-location">${escapeHTML(location)} <span>·</span> ${escapeHTML(order.customer_name || 'Consumidor final')}</div>
          <p class="kitchen-items">${escapeHTML(order.item_summary || 'Sin detalle de productos')}</p>
          ${order.notes ? `<div class="kitchen-notes">Nota: ${escapeHTML(order.notes)}</div>` : ''}
          <div class="kitchen-card-footer"><span class="status-tag ${orderStatusClass(lane.status)}">${lane.status}</span>${action}</div>
        </article>`;
      }).join('');
      return `<section class="kitchen-lane ${lane.className}"><div class="kitchen-lane-heading"><div><h2>${lane.title}</h2><small>${lane.subtitle}</small></div><strong>${laneOrders.length}</strong></div><div class="kitchen-lane-list">${cards || `<div class="operation-empty">No hay pedidos ${lane.status === 'Pendiente' ? 'pendientes' : lane.status === 'Preparando' ? 'en preparación' : 'listos'}.</div>`}</div></section>`;
    }).join('');
    board.querySelectorAll('[data-go-delivery]').forEach(button => button.addEventListener('click', () => switchView('delivery')));
  }

  async function refreshDeliveryBoard() {
    if (!can('delivery.view')) return;
    const result = await apiRequest('api/operations.php?view=delivery', { method: 'GET' });
    state.deliveryOrders = result.orders || [];
    state.deliveryCouriers = result.couriers || [];
    state.operationPermissions.deliveryManage = Boolean(result.permissions?.can_manage);
    state.operationPermissions.deliveryUpdate = Boolean(result.permissions?.can_update);
    state.operationPermissions.isCourier = Boolean(result.permissions?.is_courier);
    renderDeliveryBoard();
  }

  function renderDeliveryBoard() {
    const board = $('#deliveryBoard');
    if (!board) return;
    const orders = state.deliveryOrders || [];
    const count = status => orders.filter(order => order.delivery_status === status).length;
    $('#deliverySummary').innerHTML = `
      <article class="operations-metric"><span class="operations-metric-icon metric-blue">⌁</span><div><small>Entregas visibles</small><strong>${orders.length}</strong></div></article>
      <article class="operations-metric"><span class="operations-metric-icon metric-amber">◷</span><div><small>Por asignar</small><strong>${count('Pendiente')}</strong></div></article>
      <article class="operations-metric"><span class="operations-metric-icon metric-green">➤</span><div><small>En reparto</small><strong>${count('En reparto')}</strong></div></article>
      <article class="operations-metric"><span class="operations-metric-icon metric-red">!</span><div><small>Incidencias</small><strong>${count('Incidencia')}</strong></div></article>`;
    if (!orders.length) {
      board.innerHTML = `<div class="empty-state"><span>⌁</span><strong>${state.operationPermissions.isCourier ? 'No tienes entregas asignadas' : 'No hay pedidos de delivery'}</strong><p>${state.operationPermissions.isCourier ? 'Cuando el administrador te asigne un pedido, aparecerá aquí.' : 'Los pedidos creados con tipo Delivery aparecerán en este tablero.'}</p></div>`;
      return;
    }
    board.innerHTML = orders.map(order => {
      const status = order.delivery_status || 'Pendiente';
      const statusClass = ({ Pendiente: 'delivery-pending', Asignado: 'delivery-assigned', 'En reparto': 'delivery-route', Entregado: 'delivery-done', Incidencia: 'delivery-incident' })[status] || 'delivery-pending';
      const canManage = state.operationPermissions.deliveryManage;
      const canUpdate = state.operationPermissions.deliveryUpdate;
      const assigned = Boolean(Number(order.courier_user_id || 0));
      const metaAddress = order.address_line ? escapeHTML(order.address_line) : '<span class="delivery-missing">Falta dirección de entrega</span>';
      const metadata = `<div class="delivery-data-grid">
        <div><small>Teléfono</small><strong>${order.recipient_phone ? escapeHTML(order.recipient_phone) : 'Sin registrar'}</strong></div>
        <div><small>Zona / distrito</small><strong>${order.zone ? escapeHTML(order.zone) : 'Sin especificar'}</strong></div>
        <div class="delivery-address-field"><small>Dirección</small><strong>${metaAddress}</strong></div>
        <div><small>Repartidor</small><strong>${order.courier_name ? escapeHTML(order.courier_name) : 'Sin asignar'}</strong></div>
        <div><small>Hora estimada</small><strong>${escapeHTML(formatDeliveryDateTime(order.estimated_delivery_at))}</strong></div>
        <div><small>Costo de envío</small><strong>${money(order.delivery_fee || 0)}</strong></div>
      </div>`;
      const incident = status === 'Incidencia' && order.incident_notes ? `<div class="delivery-incident-note"><strong>Incidencia</strong><span>${escapeHTML(order.incident_notes)}</span></div>` : '';
      const editButton = canManage && status !== 'Entregado' && order.status !== 'Cancelado' ? `<button type="button" class="operation-action-button action-secondary" data-edit-delivery="${Number(order.id)}">${assigned ? 'Editar entrega' : 'Completar dirección / asignar'}</button>` : '';
      const startButton = canUpdate && assigned && ['Asignado','Incidencia'].includes(status) && ['Listo','En reparto'].includes(order.status) ? `<button type="button" class="operation-action-button action-success" data-order-id="${Number(order.id)}" data-delivery-status="En reparto">${status === 'Incidencia' ? 'Reanudar reparto →' : 'Marcar en reparto →'}</button>` : '';
      const doneButton = canUpdate && status === 'En reparto' ? `<button type="button" class="operation-action-button action-success" data-order-id="${Number(order.id)}" data-delivery-status="Entregado">Confirmar entrega ✓</button>` : '';
      const incidentButton = canUpdate && ['Asignado','En reparto'].includes(status) ? `<button type="button" class="operation-action-button action-danger" data-delivery-incident="${Number(order.id)}">Registrar incidencia</button>` : '';
      return `<article class="delivery-card">
        <div class="delivery-card-head"><div><span class="section-eyebrow">${escapeHTML(order.order_number || `DES-${order.id}`)}</span><h2>${escapeHTML(order.customer_name || 'Cliente')}</h2><p>${escapeHTML(order.item_summary || 'Sin detalle')} · ${money(order.total)}</p></div><span class="delivery-state ${statusClass}">${escapeHTML(status)}</span></div>
        ${metadata}${incident}
        <div class="delivery-card-actions">${editButton}${startButton}${doneButton}${incidentButton}</div>
      </article>`;
    }).join('');
  }

  function toLocalDateTimeInput(value) {
    if (!value) return '';
    const parsed = new Date(`${String(value).replace(' ', 'T')}Z`);
    if (Number.isNaN(parsed.getTime())) return '';
    const pad = number => String(number).padStart(2, '0');
    return `${parsed.getFullYear()}-${pad(parsed.getMonth() + 1)}-${pad(parsed.getDate())}T${pad(parsed.getHours())}:${pad(parsed.getMinutes())}`;
  }
  function toUtcDateTimePayload(value) {
    if (!value) return '';
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? '' : parsed.toISOString().slice(0, 16);
  }
  function formatDeliveryDateTime(value) {
    if (!value) return 'Sin estimar';
    const parsed = new Date(`${String(value).replace(' ', 'T')}Z`);
    return Number.isNaN(parsed.getTime()) ? escapeHTML(value) : parsed.toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' });
  }

  async function openDeliveryEditModal(orderId) {
    if (!can('delivery.manage')) return showToast('Acceso restringido', 'Tu rol no puede asignar repartidores ni editar direcciones.', '!');
    let order = state.deliveryOrders.find(item => Number(item.id) === Number(orderId));
    if (!order) {
      try { await refreshDeliveryBoard(); order = state.deliveryOrders.find(item => Number(item.id) === Number(orderId)); }
      catch (error) { return showToast('No se pudo abrir la entrega', error.message, '!'); }
    }
    if (!order) return showToast('Pedido no encontrado', 'Actualiza el tablero de delivery e inténtalo de nuevo.', '!');
    const courierOptions = `<option value="">Sin asignar</option>${state.deliveryCouriers.map(courier => `<option value="${Number(courier.id)}" ${Number(order.courier_user_id) === Number(courier.id) ? 'selected' : ''}>${escapeHTML(courier.name)} · ${escapeHTML(courier.email)}</option>`).join('')}`;
    const etaValue = toLocalDateTimeInput(order.estimated_delivery_at);
    const fields = `<div class="sales-form-grid">
      <div class="form-field"><label for="deliveryPhoneInput">Teléfono del destinatario *</label><input id="deliveryPhoneInput" required maxlength="30" value="${escapeHTML(order.recipient_phone || '')}" placeholder="999 999 999"></div>
      <div class="form-field"><label for="deliveryZoneInput">Zona / distrito</label><input id="deliveryZoneInput" maxlength="100" value="${escapeHTML(order.zone || '')}" placeholder="Ej. Huaycán"></div>
      <div class="form-field form-field-wide"><label for="deliveryAddressInput">Dirección de entrega *</label><input id="deliveryAddressInput" required maxlength="255" value="${escapeHTML(order.address_line || '')}" placeholder="Calle, número y referencia"></div>
      <div class="form-field form-field-wide"><label for="deliveryCourierInput">Asignar repartidor</label><select id="deliveryCourierInput">${courierOptions}</select><small class="cash-hint">Para que aparezca en la lista, el usuario debe tener el rol Repartidor y estar activo.</small></div>
      <div class="form-field"><label for="deliveryEtaInput">Hora estimada</label><input id="deliveryEtaInput" type="datetime-local" value="${escapeHTML(etaValue)}"></div>
    </div><p id="deliveryEditError" class="modal-alert" role="alert"></p>`;
    openWideModal(modalShell('CENTRO DE DELIVERY', `Entrega ${escapeHTML(order.order_number || `DES-${order.id}`)}`, 'Completa los datos y asigna el pedido a un repartidor activo.', 'deliveryEditForm', fields, 'Guardar entrega'));
    $('#deliveryEditForm').addEventListener('submit', async event => {
      event.preventDefault();
      const submit = $('#deliveryEditForm button[type="submit"]'); submit.disabled = true;
      try {
        const result = await apiRequest('api/operations.php', { method: 'POST', body: JSON.stringify({
          action: 'save_delivery', id: Number(order.id), recipient_phone: $('#deliveryPhoneInput').value.trim(),
          address_line: $('#deliveryAddressInput').value.trim(), zone: $('#deliveryZoneInput').value.trim(),
          courier_user_id: $('#deliveryCourierInput').value ? Number($('#deliveryCourierInput').value) : null,
          estimated_delivery_at: toUtcDateTimePayload($('#deliveryEtaInput').value)
        }) });
        closeModal(); showToast('Entrega guardada', result.message);
        await refreshDeliveryBoard();
      } catch (error) { $('#deliveryEditError').textContent = error.message; }
      finally { submit.disabled = false; }
    });
  }

  function openDeliveryIncidentModal(orderId) {
    const order = state.deliveryOrders.find(item => Number(item.id) === Number(orderId));
    if (!order) return;
    const fields = `<div class="form-field"><label for="deliveryIncidentNotes">Describe la incidencia</label><textarea id="deliveryIncidentNotes" required maxlength="500" rows="4" placeholder="Ej. Cliente no responde, dirección incorrecta…"></textarea></div><p id="deliveryIncidentError" class="modal-alert" role="alert"></p>`;
    openModal(modalShell('CENTRO DE DELIVERY', `Incidencia ${escapeHTML(order.order_number || `DES-${order.id}`)}`, 'Describe el problema para que administración pueda revisarlo.', 'deliveryIncidentForm', fields, 'Guardar incidencia'));
    $('#deliveryIncidentForm').addEventListener('submit', async event => {
      event.preventDefault();
      const submit = $('#deliveryIncidentForm button[type="submit"]'); submit.disabled = true;
      try {
        const result = await apiRequest('api/operations.php', { method: 'POST', body: JSON.stringify({ action: 'delivery_status', id: Number(order.id), status: 'Incidencia', incident_notes: $('#deliveryIncidentNotes').value.trim() }) });
        closeModal(); showToast('Incidencia registrada', result.message, '!'); await refreshDeliveryBoard();
      } catch (error) { $('#deliveryIncidentError').textContent = error.message; }
      finally { submit.disabled = false; }
    });
  }

  function initMenuFilters() {
    $('#menuFilters').addEventListener('click', event => {
      const button = event.target.closest('[data-menu-filter]');
      if (!button) return;
      state.menuFilter = button.dataset.menuFilter;
      $$('#menuFilters .filter-chip').forEach(chip => chip.classList.toggle('is-selected', chip === button));
      renderProducts();
    });
    $('#productSearch').addEventListener('input', event => { state.search = event.target.value; renderProducts(); });
    $('#newProductButton').addEventListener('click', () => openProductForm());
    $('#manageCategoriesButton').addEventListener('click', openCategoriesModal);
    $('#manageIngredientsButton').addEventListener('click', () => openIngredientsModal());
    $('#manageInventoryButton').addEventListener('click', openInventoryModal);
    $('#managePromotionsButton')?.addEventListener('click', openPromotionsModal);
    $('#productsGrid').addEventListener('click', async event => {
      const edit = event.target.closest('[data-edit-product]');
      const toggle = event.target.closest('[data-toggle-product]');
      if (edit) await openProductForm(state.products.find(item => Number(item.id) === Number(edit.dataset.editProduct)) || null);
      if (toggle) {
        try {
          await apiRequest('api/catalog.php', { method: 'POST', body: JSON.stringify({ action: 'product.status', id: Number(toggle.dataset.toggleProduct), status: toggle.dataset.nextStatus }) });
          await refreshCatalogMenu();
          showToast('Producto actualizado', toggle.dataset.nextStatus === 'available' ? 'El producto está disponible.' : 'El producto quedó pausado.');
        } catch (error) { showToast('No se pudo cambiar el estado', error.message, '!'); }
      }
    });
  }

  /* Reservas y mesas conectadas a MySQL. */
  function initReservationActions() {
    $('#quickReserve').addEventListener('click', () => openReservationModal());
    $('#createReservationButton').addEventListener('click', () => openReservationModal());
    $('#createTableButton')?.addEventListener('click', () => openTableModal());
    $('#refreshReservationsButton')?.addEventListener('click', () => refreshReservations(true).then(() => showToast('Datos actualizados', 'Se recargaron las reservas y mesas.')).catch(error => showToast('No se pudo actualizar', error.message, '!')));

    $('#reservationsList').addEventListener('click', async event => {
      const editButton = event.target.closest('[data-edit-reservation]');
      if (editButton) {
        const reservation = state.reservations.find(item => Number(item.id) === Number(editButton.dataset.editReservation));
        if (reservation) openReservationModal(reservation);
        return;
      }
      const statusButton = event.target.closest('[data-reservation-status]');
      if (!statusButton) return;
      if (!can('reservations.manage')) return showToast('Acceso restringido', 'No tienes permiso para gestionar reservas.', '!');
      statusButton.disabled = true;
      try {
        await apiRequest('api/reservations.php', { method: 'POST', body: JSON.stringify({ action: 'reservation.status', id: Number(statusButton.dataset.reservationId), status: statusButton.dataset.reservationStatus }) });
        await refreshReservations();
        showToast('Reserva actualizada', `Estado: ${statusButton.dataset.reservationStatus}.`);
      } catch (error) { showToast('No se pudo actualizar la reserva', error.message, '!'); }
      finally { statusButton.disabled = false; }
    });

    $('#tablesGrid').addEventListener('change', async event => {
      const select = event.target.closest('[data-table-status]');
      if (!select) return;
      if (!can('tables.manage')) return showToast('Acceso restringido', 'No tienes permiso para cambiar mesas.', '!');
      select.disabled = true;
      try {
        await apiRequest('api/reservations.php', { method: 'POST', body: JSON.stringify({ action: 'table.status', id: Number(select.dataset.tableStatus), status: select.value }) });
        await refreshReservations();
        showToast('Mesa actualizada', 'El estado operativo se guardó en MySQL.');
      } catch (error) { showToast('No se pudo cambiar la mesa', error.message, '!'); await refreshReservations().catch(() => {}); }
      finally { select.disabled = false; }
    });
    $('#tablesGrid').addEventListener('click', event => {
      const editButton = event.target.closest('[data-edit-table]');
      if (!editButton) return;
      const table = state.tables.find(item => Number(item.id) === Number(editButton.dataset.editTable));
      if (table) openTableModal(table);
    });
  }
  /* Perfil, conexión y alertas. */
  function initDashboardActions() {
    $('#connectionToggle').addEventListener('click', () => {
      state.online = !state.online;
      $('#connectionDot').classList.toggle('is-online', state.online);
      $('#connectionDot').classList.toggle('is-offline', !state.online);
      $('#connectionLabel').textContent = state.online ? 'En línea' : 'Sin conexión';
      showToast(state.online ? 'Conexión restaurada' : 'Modo sin conexión', state.online ? 'La interfaz vuelve a mostrar el estado online.' : 'Se activó el indicador visual offline.', state.online ? '✓' : '!');
    });
    $('#notificationsButton').addEventListener('click', () => switchView('notifications'));
    $('#logoutButton').addEventListener('click', logout);
    $('#editProfileButton').addEventListener('click', openEditProfileModal);
    const usersButton = $('#usersManagementButton');
    if (usersButton) usersButton.addEventListener('click', openUserManagementModal);
    $$('.profile-option[data-profile-option]').forEach(button => button.addEventListener('click', () => openInfoModal(button.dataset.profileOption)));
  }

  /* Modal accesible, con formularios funcionales para la demo. */
  function openModal(html) {
    $('#modalBody').innerHTML = html;
    $('#modalRoot .modal-dialog')?.classList.remove('is-wide');
    $('#modalRoot').classList.remove('is-hidden');
    document.body.style.overflow = 'hidden';
    const focusable = $('#modalBody input, #modalBody select, #modalBody button');
    if (focusable) setTimeout(() => focusable.focus(), 40);
  }
  function closeModal() {
    if (state.themePreviewOriginal !== null) {
      const originalTone = state.themePreviewOriginal;
      state.themePreviewOriginal = null;
      applyThemeTone(originalTone, true);
    }
    $('#modalRoot').classList.add('is-hidden');
    $('#modalRoot .modal-dialog')?.classList.remove('is-wide');
    $('#modalBody').innerHTML = '';
    document.body.style.overflow = '';
  }
  function initModal() {
    $('#modalRoot').addEventListener('click', event => { if (event.target.closest('[data-close-modal]')) closeModal(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !$('#modalRoot').classList.contains('is-hidden')) closeModal(); });
  }
  function modalShell(kicker, title, description, formId, fields, submitText) {
    return `<span class="modal-eyebrow">${escapeHTML(kicker)}</span><h2 class="modal-title" id="modalTitle">${escapeHTML(title)}</h2><p class="modal-description">${escapeHTML(description)}</p><form id="${formId}" class="modal-form">${fields}<div class="modal-actions"><button type="button" class="secondary-button" data-close-modal>Cancelar</button><button type="submit" class="modal-primary">${escapeHTML(submitText)}</button></div></form>`;
  }
  function openNewOrderModal() {
    const productOptions = state.products.map(product => `<option value="${product.id}">${escapeHTML(product.name)} — ${money(product.price)}</option>`).join('');
    const fields = `<div class="form-field"><label for="orderCustomer">Nombre del cliente</label><input id="orderCustomer" name="customer" required placeholder="Nombre del cliente" maxlength="70"></div><div class="form-field"><label for="orderType">Tipo de pedido</label><select id="orderType" name="type"><option>Mesa</option><option>Delivery</option><option>Para llevar</option></select></div><div class="form-field"><label for="orderProduct">Producto principal</label><select id="orderProduct" name="product">${productOptions}</select></div>`;
    openModal(modalShell('PEDIDOS', 'Nueva venta', 'Registra un pedido de prueba en la lista de pedidos de este navegador.', 'newOrderForm', fields, 'Registrar pedido'));
    $('#newOrderForm').addEventListener('submit', event => {
      event.preventDefault();
      const name = $('#orderCustomer').value.trim();
      const type = $('#orderType').value;
      const product = state.products.find(item => String(item.id) === $('#orderProduct').value) || state.products[0];
      const next = 1049 + state.orders.filter(item => item.id.startsWith('DES-')).length - 4;
      state.orders.unshift({ id: `DES-${Math.max(1049, next)}`, location: type === 'Mesa' ? 'Mesa nueva' : type, items: `${product.name} · ${name}`, total: product.price, status: 'Pendiente', type, symbol: '◷', age: 'Justo ahora' });
      saveArrays(); renderOrders(); closeModal(); switchView('orders'); showToast('Pedido registrado', `${product.name} se añadió al centro de pedidos.`);
    });
  }
  async function openProductForm(product = null) {
    if (!can('products.manage')) return showToast('Acceso restringido', 'No tienes permiso para administrar productos.', '!');
    try {
      if (!state.categories.length) await refreshCatalogMenu();
      const categoriesResult = await apiRequest('api/catalog.php?view=menu', { method: 'GET' });
      state.categories = categoriesResult.categories || state.categories;
      const ingredientData = await refreshIngredients();
      const categories = state.categories.filter(item => item.status === 'active');
      const ingredients = ingredientData.ingredients.filter(item => item.status === 'active');
      if (!categories.length) throw new Error('Crea primero una categoría activa.');
      const recipeById = new Map((product?.ingredients || []).map(item => [Number(item.ingredient_id), Number(item.quantity)]));
      const categoryOptions = categories.map(item => `<option value="${Number(item.id)}" ${Number(product?.category_id) === Number(item.id) ? 'selected' : ''}>${escapeHTML(item.name)}</option>`).join('');
      const ingredientRows = ingredients.map(item => {
        const amount = recipeById.get(Number(item.id));
        return `<label class="recipe-select-row"><input type="checkbox" data-recipe-check="${Number(item.id)}" ${amount ? 'checked' : ''}><span class="recipe-select-name"><strong>${escapeHTML(item.name)}</strong><small>Stock: ${formatQuantity(item.stock_quantity)} ${escapeHTML(item.unit)}</small></span><input aria-label="Cantidad de ${escapeHTML(item.name)} por plato" type="number" min="0.001" step="0.001" data-recipe-quantity="${Number(item.id)}" value="${amount || ''}" placeholder="Cantidad" ${amount ? '' : 'disabled'}><small>${escapeHTML(item.unit)}</small></label>`;
      }).join('');
      const fields = `<div class="catalog-form-grid">
        <div class="form-field"><label for="catalogProductName">Nombre del producto</label><input id="catalogProductName" required maxlength="100" value="${escapeHTML(product?.name || '')}" placeholder="Ej. Tiradito DESEGO"></div>
        <div class="form-field"><label for="catalogProductCategory">Categoría</label><select id="catalogProductCategory" required>${categoryOptions}</select></div>
        <div class="form-field"><label for="catalogProductPrice">Precio (S/)</label><input id="catalogProductPrice" type="number" min="0" max="99999999" step="0.01" required value="${product ? Number(product.price).toFixed(2) : ''}" placeholder="25.00"></div>
        <div class="form-field"><label for="catalogProductEmoji">Icono/emoji</label><input id="catalogProductEmoji" maxlength="8" value="${escapeHTML(product?.emoji || '🍽️')}" placeholder="🍽️"></div>
        <div class="form-field form-field-wide"><label for="catalogProductDescription">Descripción</label><input id="catalogProductDescription" maxlength="500" value="${escapeHTML(product?.description || '')}" placeholder="Descripción breve del plato"></div>
        <div class="form-field"><label for="catalogProductStatus">Disponibilidad</label><select id="catalogProductStatus"><option value="available" ${!product || product.status === 'available' ? 'selected' : ''}>Disponible</option><option value="unavailable" ${product?.status === 'unavailable' ? 'selected' : ''}>No disponible</option></select></div>
      </div>
      <div class="recipe-section"><div class="recipe-section-heading"><div><strong>Receta e ingredientes</strong><small>Selecciona ingredientes y la cantidad usada por porción. Los alérgenos se heredan del ingrediente.</small></div></div><div class="recipe-select-list">${ingredientRows || '<p class="catalog-note">Primero registra ingredientes para poder crear recetas.</p>'}</div></div>`;
      openWideModal(modalShell('MENÚ DESEGO', product ? 'Editar producto' : 'Nuevo producto', 'Los cambios se guardan en MySQL.', 'catalogProductForm', fields, product ? 'Guardar cambios' : 'Crear producto'));
      $('#catalogProductForm').addEventListener('change', event => {
        const checkbox = event.target.closest('[data-recipe-check]');
        if (!checkbox) return;
        const quantity = $(`[data-recipe-quantity="${checkbox.dataset.recipeCheck}"]`);
        if (quantity) { quantity.disabled = !checkbox.checked; if (!checkbox.checked) quantity.value = ''; }
      });
      $('#catalogProductForm').addEventListener('submit', async event => {
        event.preventDefault();
        const submit = $('#catalogProductForm button[type="submit"]'); submit.disabled = true;
        const recipe = $$('#catalogProductForm [data-recipe-check]:checked').map(check => ({ ingredient_id: Number(check.dataset.recipeCheck), quantity: Number($(`[data-recipe-quantity="${check.dataset.recipeCheck}"]`).value) }));
        const body = { action: product ? 'product.update' : 'product.create', ...(product ? { id: Number(product.id) } : {}), name: $('#catalogProductName').value.trim(), category_id: Number($('#catalogProductCategory').value), price: Number($('#catalogProductPrice').value), emoji: $('#catalogProductEmoji').value.trim() || '🍽️', description: $('#catalogProductDescription').value.trim(), status: $('#catalogProductStatus').value, ingredients: recipe };
        try {
          await apiRequest('api/catalog.php', { method: 'POST', body: JSON.stringify(body) });
          closeModal(); await refreshCatalogMenu(); showToast(product ? 'Producto actualizado' : 'Producto creado', `${body.name} se guardó en la base de datos.`); switchView('menu');
        } catch (error) { showModalError(error.message); }
        finally { submit.disabled = false; }
      });
    } catch (error) { showToast('No se pudo abrir el formulario', error.message, '!'); }
  }

  function openWideModal(html) {
    openModal(html);
    $('#modalRoot .modal-dialog')?.classList.add('is-wide');
  }

  function formatQuantity(value) {
    return Number(value || 0).toLocaleString('es-PE', { maximumFractionDigits: 3 });
  }

  function utcSqlToLocalInput(value) {
    if (!value) return '';
    const parsed = new Date(String(value).replace(' ', 'T') + 'Z');
    if (Number.isNaN(parsed.getTime())) return '';
    return new Date(parsed.getTime() - parsed.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
  }
  function localInputToUtcApi(value) {
    if (!value) return '';
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? '' : parsed.toISOString().slice(0, 16);
  }
  function promotionDateLabel(value) {
    if (!value) return 'Sin fecha de fin';
    const parsed = new Date(String(value).replace(' ', 'T') + 'Z');
    return Number.isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' });
  }
  async function openPromotionsModal() {
    if (!(can('promotions.view') || can('promotions.manage'))) return showToast('Acceso restringido', 'Tu rol no puede consultar promociones.', '!');
    try {
      const result = await apiRequest('api/promotions.php?view=list', { method: 'GET' });
      const rows = (result.promotions || []).map(item => {
        const discount = item.discount_type === 'percentage' ? `${Number(item.discount_value)}%` : money(item.discount_value);
        const limit = item.usage_limit === null ? 'sin límite total' : `${Number(item.usage_count)}/${Number(item.usage_limit)} usos`;
        const type = item.promotion_type === 'coupon' ? `Cupón · ${escapeHTML(item.code || '')}` : 'Automática';
        const status = item.status === 'active' ? 'Activa' : 'Inactiva';
        const controls = can('promotions.manage') ? `<button type="button" class="secondary-button" data-edit-promotion="${Number(item.id)}">Editar</button><button type="button" class="secondary-button" data-toggle-promotion="${Number(item.id)}" data-next-status="${item.status === 'active' ? 'inactive' : 'active'}">${item.status === 'active' ? 'Desactivar' : 'Activar'}</button>` : '';
        return `<div class="manager-data-row promotion-data-row"><div class="manager-data-main"><strong>${escapeHTML(item.name)} <span class="promotion-discount-label">${escapeHTML(discount)}</span></strong><small>${type} · mínimo ${money(item.min_subtotal)} · ${limit}</small><small>${promotionDateLabel(item.starts_at)} → ${promotionDateLabel(item.ends_at)}</small></div><span class="mini-status ${item.status === 'active' ? 'status-on' : 'status-off'}">${status}</span>${controls}</div>`;
      }).join('');
      state.promotions = result.promotions || [];
      const create = can('promotions.manage') ? '<button type="button" class="modal-primary" id="createPromotionButton">＋ Nueva promoción</button>' : '';
      openWideModal(`<span class="modal-eyebrow">VENTAS · MARKETING</span><h2 class="modal-title" id="modalTitle">Promociones y cupones</h2><p class="modal-description">Crea campañas automáticas o códigos que el cliente pueda aplicar en la tienda. El servidor valida fechas, mínimos y límites antes de confirmar cada pedido.</p><div class="manager-toolbar">${create}<span class="counter-chip">${state.promotions.length} campañas</span></div><div class="manager-data-list">${rows || '<p class="catalog-note">Todavía no hay promociones. Crea una campaña para empezar.</p>'}</div><p class="operations-footnote">Las promociones descuentan únicamente el subtotal de productos; no se combinan entre sí. El total y el descuento se recalculan en el servidor.</p>`);
      $('#createPromotionButton')?.addEventListener('click', () => openPromotionForm());
      $('#modalBody').onclick = async event => {
        const edit = event.target.closest('[data-edit-promotion]');
        const toggle = event.target.closest('[data-toggle-promotion]');
        if (edit) {
          const item = state.promotions.find(row => Number(row.id) === Number(edit.dataset.editPromotion));
          if (item) openPromotionForm(item);
          return;
        }
        if (toggle) {
          toggle.disabled = true;
          try {
            const res = await apiRequest('api/promotions.php', { method: 'POST', body: JSON.stringify({ action: 'toggle', id: Number(toggle.dataset.togglePromotion), status: toggle.dataset.nextStatus }) });
            showToast('Promoción actualizada', res.message);
            await openPromotionsModal();
          } catch (error) { showModalError(error.message); }
          finally { toggle.disabled = false; }
        }
      };
    } catch (error) { showToast('No se pudieron cargar las promociones', error.message, '!'); }
  }

  function openPromotionForm(item = null) {
    if (!can('promotions.manage')) return showToast('Acceso restringido', 'Tu rol no puede crear ni editar promociones.', '!');
    const nowLocal = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    const fields = `
      <div class="form-grid"><div class="form-field"><label for="promotionName">Nombre de campaña *</label><input id="promotionName" required minlength="3" maxlength="120" value="${escapeHTML(item?.name || '')}" placeholder="Ej. Promo cevichera"></div><div class="form-field"><label for="promotionType">Aplicación</label><select id="promotionType"><option value="coupon" ${!item || item.promotion_type === 'coupon' ? 'selected' : ''}>Cupón con código</option><option value="automatic" ${item?.promotion_type === 'automatic' ? 'selected' : ''}>Automática</option></select></div></div>
      <div class="form-grid" id="promotionCodeWrap"><div class="form-field"><label for="promotionCode">Código *</label><input id="promotionCode" maxlength="40" pattern="[A-Za-z0-9_-]{3,40}" value="${escapeHTML(item?.code || '')}" placeholder="DESEGO10"><small>Se convierte a mayúsculas al guardarlo.</small></div><div class="form-field"><label for="promotionDiscountType">Tipo de descuento</label><select id="promotionDiscountType"><option value="percentage" ${!item || item.discount_type === 'percentage' ? 'selected' : ''}>Porcentaje (%)</option><option value="fixed" ${item?.discount_type === 'fixed' ? 'selected' : ''}>Importe fijo (S/)</option></select></div></div>
      <div class="form-grid"><div class="form-field"><label for="promotionValue">Valor del descuento *</label><input id="promotionValue" type="number" min="0.01" step="0.01" max="1000000" required value="${item ? Number(item.discount_value) : '10'}"></div><div class="form-field"><label for="promotionMaxDiscount">Descuento máximo (opcional)</label><input id="promotionMaxDiscount" type="number" min="0.01" step="0.01" value="${item?.max_discount ?? ''}" placeholder="Ej. 15.00"></div></div>
      <div class="form-grid"><div class="form-field"><label for="promotionMinimum">Compra mínima (S/)</label><input id="promotionMinimum" type="number" min="0" step="0.01" value="${item ? Number(item.min_subtotal) : '0'}" required></div><div class="form-field"><label for="promotionUsageLimit">Límite total de usos</label><input id="promotionUsageLimit" type="number" min="1" step="1" value="${item?.usage_limit ?? ''}" placeholder="Vacío = sin límite"></div></div>
      <div class="form-grid"><div class="form-field"><label for="promotionCustomerLimit">Usos por cliente (0 = ilimitado)</label><input id="promotionCustomerLimit" type="number" min="0" step="1" required value="${item ? Number(item.per_customer_limit) : '1'}"></div><div class="form-field"><label for="promotionStart">Inicio *</label><input id="promotionStart" type="datetime-local" required value="${item ? utcSqlToLocalInput(item.starts_at) : nowLocal}"></div></div>
      <div class="form-field"><label for="promotionEnd">Fin (opcional)</label><input id="promotionEnd" type="datetime-local" value="${item ? utcSqlToLocalInput(item.ends_at) : ''}"></div>
      <div class="form-field"><label for="promotionDescription">Descripción (opcional)</label><input id="promotionDescription" maxlength="255" value="${escapeHTML(item?.description || '')}" placeholder="Condiciones breves de la campaña"></div>`;
    openModal(modalShell('VENTAS · MARKETING', item ? 'Editar promoción' : 'Nueva promoción', 'Las reglas se guardan en MySQL y se validan de nuevo al confirmar el pedido.', 'promotionForm', fields, item ? 'Guardar cambios' : 'Crear promoción'));
    const typeInput = $('#promotionType');
    const syncType = () => { const isCoupon = typeInput.value === 'coupon'; $('#promotionCodeWrap').classList.toggle('is-hidden', !isCoupon); $('#promotionCode').required = isCoupon; };
    syncType(); typeInput.addEventListener('change', syncType);
    $('#promotionForm').addEventListener('submit', async event => {
      event.preventDefault();
      const submit = $('#promotionForm button[type="submit"]'); submit.disabled = true;
      const payload = {
        action: item ? 'update' : 'create', ...(item ? { id: Number(item.id) } : {}),
        name: $('#promotionName').value.trim(), description: $('#promotionDescription').value.trim(),
        promotion_type: typeInput.value, code: $('#promotionCode').value.trim().toUpperCase(),
        discount_type: $('#promotionDiscountType').value, discount_value: Number($('#promotionValue').value),
        max_discount: $('#promotionMaxDiscount').value, min_subtotal: Number($('#promotionMinimum').value),
        usage_limit: $('#promotionUsageLimit').value, per_customer_limit: Number($('#promotionCustomerLimit').value),
        starts_at: localInputToUtcApi($('#promotionStart').value), ends_at: localInputToUtcApi($('#promotionEnd').value)
      };
      try {
        const result = await apiRequest('api/promotions.php', { method: 'POST', body: JSON.stringify(payload) });
        showToast('Promoción guardada', result.message);
        await openPromotionsModal();
      } catch (error) { showModalError(error.message); }
      finally { submit.disabled = false; }
    });
  }

  async function openPaymentHistoryModal() {
    if (!can('payments.view')) return showToast('Acceso restringido', 'Tu rol no puede consultar el historial de pagos.', '!');
    try {
      const result = await apiRequest('api/payments.php', { method: 'GET' });
      const summary = result.summary || {};
      const methodRows = (result.methods || []).map(row => `<div class="payment-method-row"><strong>${escapeHTML(row.method)}</strong><span>Cobros ${money(row.payments)}</span><span>Reembolsos ${money(row.refunds)}</span></div>`).join('');
      const rows = (result.payments || []).map(row => `<div class="manager-data-row payment-history-row"><div class="manager-data-main"><strong>${row.payment_type === 'refund' ? '↩ Reembolso' : '✓ Cobro'} · ${escapeHTML(row.order_number || ('Pedido #' + row.order_id))}</strong><small>${escapeHTML(row.customer_name || 'Cliente')} · ${escapeHTML(row.created_at || '')}</small><small>${escapeHTML(row.method)} · Caja #${Number(row.cash_session_id)} · ${escapeHTML(row.user_name || '')}${row.notes ? ' · ' + escapeHTML(row.notes) : ''}</small></div><strong class="payment-history-amount ${row.payment_type === 'refund' ? 'is-refund' : ''}">${row.payment_type === 'refund' ? '−' : ''}${money(row.amount)}</strong></div>`).join('');
      openWideModal(`<span class="modal-eyebrow">CAJA · AUDITORÍA</span><h2 class="modal-title" id="modalTitle">Historial de pagos</h2><p class="modal-description">Cobros y reembolsos registrados por el módulo de caja. Los métodos Yape/Plin y tarjeta se registran manualmente; no representan una confirmación de una pasarela bancaria.</p><div class="payment-summary-grid"><div><small>Operaciones</small><strong>${Number(summary.count_total || 0)}</strong></div><div><small>Cobros</small><strong>${money(summary.gross_payments || 0)}</strong></div><div><small>Reembolsos</small><strong>${money(summary.gross_refunds || 0)}</strong></div><div><small>Neto</small><strong>${money(summary.net_total || 0)}</strong></div></div><h3 class="payment-section-heading">Totales por medio</h3><div class="payment-method-list">${methodRows || '<p class="catalog-note">No hay movimientos por medio de pago.</p>'}</div><h3 class="payment-section-heading">Movimientos recientes</h3><div class="manager-data-list">${rows || '<p class="catalog-note">Todavía no hay pagos registrados.</p>'}</div>`);
    } catch (error) { showToast('No se pudo cargar el historial de pagos', error.message, '!'); }
  }

  async function openCategoriesModal() {
    if (!can('categories.manage')) return showToast('Acceso restringido', 'No puedes gestionar categorías.', '!');
    try {
      const result = await apiRequest('api/catalog.php?view=categories', { method: 'GET' });
      const rows = result.categories.map(category => `<div class="manager-data-row"><div class="manager-data-main"><strong>${escapeHTML(category.name)}</strong><small>${escapeHTML(category.description || 'Sin descripción')} · ${Number(category.product_count)} productos</small></div><span class="mini-status ${category.status === 'active' ? 'status-on' : 'status-off'}">${category.status === 'active' ? 'Activa' : 'Inactiva'}</span><button type="button" class="secondary-button" data-edit-category="${Number(category.id)}">Editar</button><button type="button" class="secondary-button" data-toggle-category="${Number(category.id)}" data-next-status="${category.status === 'active' ? 'inactive' : 'active'}">${category.status === 'active' ? 'Desactivar' : 'Activar'}</button></div>`).join('');
      openWideModal(`<span class="modal-eyebrow">CATÁLOGO</span><h2 class="modal-title" id="modalTitle">Categorías</h2><p class="modal-description">Administra las categorías que aparecen en el menú.</p><div class="manager-toolbar"><button type="button" class="modal-primary" id="createCategoryButton">＋ Nueva categoría</button></div><div class="manager-data-list">${rows || '<p class="catalog-note">No hay categorías registradas.</p>'}</div>`);
      $('#createCategoryButton').addEventListener('click', () => openCategoryForm());
      $('#modalBody').onclick = async event => {
        const edit = event.target.closest('[data-edit-category]'); const toggle = event.target.closest('[data-toggle-category]');
        if (edit) { const item = result.categories.find(row => Number(row.id) === Number(edit.dataset.editCategory)); openCategoryForm(item); }
        if (toggle) {
          try { await apiRequest('api/catalog.php', { method: 'POST', body: JSON.stringify({ action: 'category.status', id: Number(toggle.dataset.toggleCategory), status: toggle.dataset.nextStatus }) }); await refreshCatalogMenu(); showToast('Categoría actualizada', 'Se guardó el estado de la categoría.'); await openCategoriesModal(); }
          catch (error) { showToast('No se pudo actualizar', error.message, '!'); }
        }
      };
    } catch (error) { showToast('No se pudieron cargar las categorías', error.message, '!'); }
  }

  function openCategoryForm(category = null) {
    const fields = `<div class="form-field"><label for="categoryNameInput">Nombre</label><input id="categoryNameInput" required minlength="2" maxlength="80" value="${escapeHTML(category?.name || '')}" placeholder="Ej. Platos marinos"></div><div class="form-field"><label for="categoryDescriptionInput">Descripción</label><input id="categoryDescriptionInput" maxlength="255" value="${escapeHTML(category?.description || '')}" placeholder="¿Qué productos incluye?"></div>`;
    openModal(modalShell('CATÁLOGO', category ? 'Editar categoría' : 'Nueva categoría', 'Las categorías quedan guardadas en MySQL.', 'categoryForm', fields, category ? 'Guardar cambios' : 'Crear categoría'));
    $('#categoryForm').addEventListener('submit', async event => {
      event.preventDefault(); const submit = $('#categoryForm button[type="submit"]'); submit.disabled = true;
      try { await apiRequest('api/catalog.php', { method: 'POST', body: JSON.stringify({ action: category ? 'category.update' : 'category.create', ...(category ? { id: Number(category.id) } : {}), name: $('#categoryNameInput').value.trim(), description: $('#categoryDescriptionInput').value.trim() }) }); closeModal(); await refreshCatalogMenu(); showToast('Categoría guardada', 'La lista de categorías se actualizó.'); await openCategoriesModal(); }
      catch (error) { showModalError(error.message); }
      finally { submit.disabled = false; }
    });
  }

  async function openIngredientsModal() {
    if (!can('ingredients.manage') && !can('allergens.manage')) return showToast('Acceso restringido', 'No tienes permiso para administrar ingredientes.', '!');
    try {
      const result = await refreshIngredients();
      const rows = result.ingredients.map(item => `<div class="manager-data-row"><div class="manager-data-main"><strong>${escapeHTML(item.name)}</strong><small>${formatQuantity(item.stock_quantity)} ${escapeHTML(item.unit)} disponibles · mínimo ${formatQuantity(item.min_stock)} ${escapeHTML(item.unit)}${item.allergen_names.length ? ' · Alérgenos: ' + escapeHTML(item.allergen_names.join(', ')) : ''}</small></div><span class="mini-status ${item.status === 'active' ? (item.stock_quantity <= item.min_stock ? 'status-low' : 'status-on') : 'status-off'}">${item.status === 'inactive' ? 'Inactivo' : item.stock_quantity <= item.min_stock ? 'Stock bajo' : 'Activo'}</span>${can('ingredients.manage') ? `<button type="button" class="secondary-button" data-edit-ingredient="${Number(item.id)}">Editar</button><button type="button" class="secondary-button" data-toggle-ingredient="${Number(item.id)}" data-next-status="${item.status === 'active' ? 'inactive' : 'active'}">${item.status === 'active' ? 'Desactivar' : 'Activar'}</button>` : ''}</div>`).join('');
      const allergenCount = result.allergens.filter(item => item.status === 'active').length;
      openWideModal(`<span class="modal-eyebrow">RECETAS Y SEGURIDAD ALIMENTARIA</span><h2 class="modal-title" id="modalTitle">Ingredientes y alérgenos</h2><p class="modal-description">Asocia alérgenos a los ingredientes para mostrarlos automáticamente en las fichas de los productos.</p><div class="manager-toolbar"><span class="counter-chip">${result.ingredients.length} ingredientes · ${allergenCount} alérgenos activos</span><div class="manager-toolbar-actions">${can('allergens.manage') ? '<button type="button" class="secondary-button" id="manageAllergensButton">Gestionar alérgenos</button>' : ''}${can('ingredients.manage') ? '<button type="button" class="modal-primary" id="createIngredientButton">＋ Ingrediente</button>' : ''}</div></div><div class="manager-data-list">${rows || '<p class="catalog-note">Todavía no hay ingredientes.</p>'}</div>`);
      $('#createIngredientButton')?.addEventListener('click', () => openIngredientForm());
      $('#manageAllergensButton')?.addEventListener('click', openAllergensModal);
      $('#modalBody').onclick = async event => {
        const edit = event.target.closest('[data-edit-ingredient]'); const toggle = event.target.closest('[data-toggle-ingredient]');
        if (edit) openIngredientForm(state.ingredients.find(item => Number(item.id) === Number(edit.dataset.editIngredient)) || null);
        if (toggle) {
          try { await apiRequest('api/catalog.php', { method: 'POST', body: JSON.stringify({ action: 'ingredient.status', id: Number(toggle.dataset.toggleIngredient), status: toggle.dataset.nextStatus }) }); await refreshIngredients(); await refreshCatalogMenu(); showToast('Ingrediente actualizado', 'Se actualizó el estado.'); await openIngredientsModal(); }
          catch (error) { showToast('No se pudo actualizar', error.message, '!'); }
        }
      };
    } catch (error) { showToast('No se pudo cargar el catálogo de ingredientes', error.message, '!'); }
  }

  function openIngredientForm(ingredient = null) {
    const activeAllergens = state.allergens.filter(item => item.status === 'active');
    const selected = new Set(ingredient?.allergen_ids || []);
    const allergenChecks = activeAllergens.map(item => `<label class="permission-check"><input type="checkbox" data-ingredient-allergen="${Number(item.id)}" ${selected.has(Number(item.id)) ? 'checked' : ''}><span><strong>${escapeHTML(item.name)}</strong><small>${escapeHTML(item.description || '')}</small></span></label>`).join('');
    const fields = `<div class="form-field"><label for="ingredientNameInput">Nombre</label><input id="ingredientNameInput" required minlength="2" maxlength="100" value="${escapeHTML(ingredient?.name || '')}" placeholder="Ej. Pescado fresco"></div><div class="form-field"><label for="ingredientDescriptionInput">Descripción</label><input id="ingredientDescriptionInput" maxlength="255" value="${escapeHTML(ingredient?.description || '')}" placeholder="Detalle opcional"></div><div class="catalog-form-grid"><div class="form-field"><label for="ingredientUnitInput">Unidad base</label><select id="ingredientUnitInput">${[['g','Gramos (g)'],['kg','Kilogramos (kg)'],['ml','Mililitros (ml)'],['l','Litros (l)'],['unidad','Unidad'],['porción','Porción']].map(([value,label])=>`<option value="${value}" ${(ingredient?.unit || 'g') === value ? 'selected' : ''}>${label}</option>`).join('')}</select></div><div class="form-field"><label for="ingredientMinimumInput">Stock mínimo</label><input id="ingredientMinimumInput" type="number" min="0" step="0.001" required value="${ingredient ? ingredient.min_stock : '0'}"></div><div class="form-field"><label for="ingredientCostInput">Costo por unidad (S/)</label><input id="ingredientCostInput" type="number" min="0" step="0.0001" required value="${ingredient ? ingredient.cost_per_unit : '0'}"></div>${!ingredient && can('inventory.manage') ? '<div class="form-field"><label for="ingredientInitialStock">Stock inicial</label><input id="ingredientInitialStock" type="number" min="0" step="0.001" value="0" required></div>' : ''}</div>${ingredient ? `<p class="catalog-note">Stock actual: <strong>${formatQuantity(ingredient.stock_quantity)} ${escapeHTML(ingredient.unit)}</strong>. Para cambiarlo, usa el módulo Inventario.</p>` : ''}<div class="recipe-section"><div class="recipe-section-heading"><div><strong>Alérgenos asociados</strong><small>Marca todos los alérgenos que correspondan a este ingrediente.</small></div></div><div class="allergen-check-grid">${allergenChecks || '<p class="catalog-note">No hay alérgenos activos. Un administrador puede registrarlos desde “Gestionar alérgenos”.</p>'}</div></div>`;
    openWideModal(modalShell('INGREDIENTES', ingredient ? 'Editar ingrediente' : 'Nuevo ingrediente', 'El ingrediente y sus alérgenos se guardarán en MySQL.', 'ingredientForm', fields, ingredient ? 'Guardar cambios' : 'Crear ingrediente'));
    $('#ingredientForm').addEventListener('submit', async event => {
      event.preventDefault(); const submit = $('#ingredientForm button[type="submit"]'); submit.disabled = true;
      const allergenIds = $$('#ingredientForm [data-ingredient-allergen]:checked').map(node => Number(node.dataset.ingredientAllergen));
      const body = { action: ingredient ? 'ingredient.update' : 'ingredient.create', ...(ingredient ? { id: Number(ingredient.id) } : {}), name: $('#ingredientNameInput').value.trim(), description: $('#ingredientDescriptionInput').value.trim(), unit: $('#ingredientUnitInput').value, min_stock: Number($('#ingredientMinimumInput').value), cost_per_unit: Number($('#ingredientCostInput').value), allergen_ids: allergenIds, ...(!ingredient ? { initial_stock: Number($('#ingredientInitialStock')?.value || 0) } : {}) };
      try { await apiRequest('api/catalog.php', { method: 'POST', body: JSON.stringify(body) }); closeModal(); await refreshIngredients(); await refreshCatalogMenu(); showToast('Ingrediente guardado', `${body.name} quedó registrado.`); await openIngredientsModal(); }
      catch (error) { showModalError(error.message); }
      finally { submit.disabled = false; }
    });
  }

  async function openAllergensModal() {
    try {
      const result = await refreshIngredients();
      const rows = result.allergens.map(item => `<div class="manager-data-row"><div class="manager-data-main"><strong>${escapeHTML(item.name)}</strong><small>${escapeHTML(item.description || 'Sin descripción')}</small></div><span class="mini-status ${item.status === 'active' ? 'status-on' : 'status-off'}">${item.status === 'active' ? 'Activo' : 'Inactivo'}</span><button type="button" class="secondary-button" data-toggle-allergen="${Number(item.id)}" data-next-status="${item.status === 'active' ? 'inactive' : 'active'}">${item.status === 'active' ? 'Desactivar' : 'Activar'}</button></div>`).join('');
      openWideModal(`<span class="modal-eyebrow">SEGURIDAD ALIMENTARIA</span><h2 class="modal-title" id="modalTitle">Alérgenos</h2><p class="modal-description">Administra el catálogo utilizado por las recetas de productos.</p><div class="manager-toolbar"><span class="counter-chip">${result.allergens.length} registros</span><button type="button" class="modal-primary" id="createAllergenButton">＋ Nuevo alérgeno</button></div><div class="manager-data-list">${rows}</div>`);
      $('#createAllergenButton').addEventListener('click', openAllergenForm);
      $('#modalBody').onclick = async event => {
        const button = event.target.closest('[data-toggle-allergen]'); if (!button) return;
        try { await apiRequest('api/catalog.php', { method: 'POST', body: JSON.stringify({ action: 'allergen.status', id: Number(button.dataset.toggleAllergen), status: button.dataset.nextStatus }) }); await refreshIngredients(); showToast('Alérgeno actualizado', 'Se guardó el estado.'); await openAllergensModal(); }
        catch (error) { showToast('No se pudo actualizar', error.message, '!'); }
      };
    } catch (error) { showToast('No se pudieron cargar los alérgenos', error.message, '!'); }
  }

  function openAllergenForm() {
    const fields = `<div class="form-field"><label for="allergenNameInput">Nombre</label><input id="allergenNameInput" required minlength="2" maxlength="80" placeholder="Ej. Sésamo"></div><div class="form-field"><label for="allergenDescriptionInput">Descripción</label><input id="allergenDescriptionInput" maxlength="255" placeholder="Fuentes o aclaraciones"></div>`;
    openModal(modalShell('SEGURIDAD ALIMENTARIA', 'Nuevo alérgeno', 'Los alérgenos se vinculan a los ingredientes.', 'allergenForm', fields, 'Crear alérgeno'));
    $('#allergenForm').addEventListener('submit', async event => {
      event.preventDefault(); const submit = $('#allergenForm button[type="submit"]'); submit.disabled = true;
      try { await apiRequest('api/catalog.php', { method: 'POST', body: JSON.stringify({ action: 'allergen.create', name: $('#allergenNameInput').value.trim(), description: $('#allergenDescriptionInput').value.trim() }) }); closeModal(); await refreshIngredients(); showToast('Alérgeno creado', 'Ya puedes relacionarlo con ingredientes.'); await openAllergensModal(); }
      catch (error) { showModalError(error.message); }
      finally { submit.disabled = false; }
    });
  }

  async function openInventoryModal() {
    if (!can('inventory.view')) return showToast('Acceso restringido', 'No tienes permiso para consultar el inventario.', '!');
    try {
      const result = await apiRequest('api/inventory.php', { method: 'GET' });
      state.inventoryData = result.ingredients || []; state.inventoryMovements = result.movements || [];
      const rows = state.inventoryData.map(item => `<div class="inventory-row"><div class="manager-data-main"><strong>${escapeHTML(item.name)}</strong><small>Mínimo ${formatQuantity(item.min_stock)} ${escapeHTML(item.unit)} · ${item.status === 'active' ? (item.is_low ? 'Reponer pronto' : 'Stock suficiente') : 'Ingrediente inactivo'}</small></div><div class="inventory-quantity ${item.is_low ? 'inventory-low' : ''}"><strong>${formatQuantity(item.stock_quantity)}</strong><small>${escapeHTML(item.unit)}</small></div>${result.can_manage && item.status === 'active' ? `<button type="button" class="secondary-button" data-adjust-stock="${Number(item.id)}">Ajustar</button>` : ''}</div>`).join('');
      const movements = state.inventoryMovements.slice(0, 25).map(item => `<div class="movement-row"><div><strong>${escapeHTML(item.ingredient_name)}</strong><small>${escapeHTML(movementLabel(item.movement_type))} · ${escapeHTML(item.user_name || 'Sistema')} · ${escapeHTML(item.created_at)}</small>${item.notes ? `<small>${escapeHTML(item.notes)}</small>` : ''}</div><span class="movement-delta ${Number(item.quantity_delta) < 0 ? 'movement-negative' : 'movement-positive'}">${Number(item.quantity_delta) > 0 ? '+' : ''}${formatQuantity(item.quantity_delta)} ${escapeHTML(item.unit)}</span></div>`).join('');
      openWideModal(`<span class="modal-eyebrow">CONTROL DE EXISTENCIAS</span><h2 class="modal-title" id="modalTitle">Inventario</h2><p class="modal-description">Existencias actuales, niveles mínimos y trazabilidad de movimientos. Las cantidades usan la unidad base de cada ingrediente.</p><div class="inventory-summary"><div><strong>${state.inventoryData.length}</strong><small>Ingredientes</small></div><div><strong>${state.inventoryData.filter(item => item.is_low && item.status === 'active').length}</strong><small>Stock bajo</small></div><div><strong>${state.inventoryMovements.length}</strong><small>Movimientos recientes</small></div></div><div class="manager-data-list inventory-list">${rows || '<p class="catalog-note">No hay ingredientes registrados.</p>'}</div><div class="movement-section"><div class="recipe-section-heading"><div><strong>Historial de movimientos</strong><small>Se muestran los últimos 100 movimientos; aquí aparecen los 25 más recientes.</small></div></div><div class="movement-list">${movements || '<p class="catalog-note">Aún no hay movimientos registrados.</p>'}</div></div>`);
      $('#modalBody').onclick = event => { const button = event.target.closest('[data-adjust-stock]'); if (button) openStockAdjustment(Number(button.dataset.adjustStock)); };
    } catch (error) { showToast('No se pudo abrir inventario', error.message, '!'); }
  }

  function movementLabel(type) {
    return ({ initial: 'Stock inicial', entry: 'Entrada', exit: 'Salida', adjustment: 'Ajuste', waste: 'Merma', sale: 'Venta' })[type] || type;
  }

  function openStockAdjustment(ingredientId) {
    const item = state.inventoryData.find(row => Number(row.id) === Number(ingredientId));
    if (!item || !can('inventory.manage')) return;
    const fields = `<div class="modal-info-row">Stock actual: <strong>${formatQuantity(item.stock_quantity)} ${escapeHTML(item.unit)}</strong></div><div class="form-field"><label for="stockMovementType">Tipo de movimiento</label><select id="stockMovementType"><option value="entry">Entrada de stock</option><option value="exit">Salida de stock</option><option value="waste">Merma / desperdicio</option><option value="adjustment">Ajuste por conteo físico</option></select></div><div class="form-field"><label for="stockMovementQuantity">Cantidad ${escapeHTML(item.unit === 'unidad' ? '(unidades)' : `(${item.unit})`)}</label><input id="stockMovementQuantity" type="number" min="0" step="0.001" required value="1"></div><div class="form-field"><label for="stockMovementReference">Referencia (opcional)</label><input id="stockMovementReference" maxlength="120" placeholder="Ej. Compra, merma, conteo"></div><div class="form-field"><label for="stockMovementNotes">Observación (opcional)</label><input id="stockMovementNotes" maxlength="500" placeholder="Motivo del movimiento"></div>`;
    openModal(modalShell('INVENTARIO', `Ajustar: ${item.name}`, 'En un ajuste, la cantidad ingresada será el nuevo stock final. En entradas, salidas y mermas será la cantidad que se mueve.', 'stockAdjustmentForm', fields, 'Guardar movimiento'));
    $('#stockAdjustmentForm').addEventListener('submit', async event => {
      event.preventDefault(); const submit = $('#stockAdjustmentForm button[type="submit"]'); submit.disabled = true;
      try {
        await apiRequest('api/inventory.php', { method: 'POST', body: JSON.stringify({ action: 'adjust', ingredient_id: item.id, movement_type: $('#stockMovementType').value, quantity: Number($('#stockMovementQuantity').value), reference: $('#stockMovementReference').value.trim(), notes: $('#stockMovementNotes').value.trim() }) });
        closeModal(); await refreshIngredients(); await refreshCatalogMenu(); showToast('Inventario actualizado', `Nuevo stock de ${item.name} guardado.`); await openInventoryModal();
      } catch (error) { showModalError(error.message); }
      finally { submit.disabled = false; }
    });
  }

  function dateTimeLocalValue(date) {
    const pad = value => String(value).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
  }
  function reservationDateTimeInput(value) {
    const date = parseReservationDate(value);
    return date ? dateTimeLocalValue(date) : dateTimeLocalValue(new Date(Date.now() + 60 * 60 * 1000));
  }
  async function openReservationModal(reservation = null) {
    if (!can('reservations.manage')) return showToast('Acceso restringido', 'No tienes permiso para crear o editar reservas.', '!');
    try {
      if (!state.tables.length) await refreshReservations();
      let reservationCustomers = state.customers.filter(item => item.status === 'active');
      if ((can('customers.view') || can('customers.manage') || can('customer_addresses.manage')) && !reservationCustomers.length) { try { const customerData = await apiRequest('api/customers.php?view=list', { method: 'GET' }); state.customers = customerData.customers || []; reservationCustomers = state.customers.filter(item => item.status === 'active'); } catch (_) {} }
      const customerOptions = reservationCustomers.map(customer => `<option value="${Number(customer.id)}" ${Number(customer.id) === Number(reservation?.customer_id) ? 'selected' : ''}>${escapeHTML(customer.full_name)}${customer.phone ? ` · ${escapeHTML(customer.phone)}` : ''}</option>`).join('');
      const options = state.tables.filter(table => Number(table.is_active) === 1 && table.operational_status !== 'maintenance' && (table.operational_status !== 'occupied' || Number(table.id) === Number(reservation?.table_id)));
      if (!options.length) return showToast('No hay mesas disponibles', 'Activa una mesa disponible o registra una nueva.', '!');
      const tableOptions = options.map(table => `<option value="${Number(table.id)}" ${Number(table.id) === Number(reservation?.table_id) ? 'selected' : ''}>${escapeHTML(table.label)} · ${Number(table.capacity)} personas · ${escapeHTML(table.zone)}</option>`).join('');
      const fields = `<div class="form-grid">${reservationCustomers.length ? `<div class="form-field form-field-wide"><label for="reservationCustomerSelect">Cliente guardado (opcional)</label><select id="reservationCustomerSelect"><option value="">Ingresar cliente manualmente</option>${customerOptions}</select></div>` : ''}<div class="form-field"><label for="reservationCustomer">Nombre del cliente</label><input id="reservationCustomer" required maxlength="100" value="${escapeHTML(reservation?.customer_name || '')}" placeholder="Nombre completo"></div><div class="form-field"><label for="reservationPhone">Teléfono (opcional)</label><input id="reservationPhone" maxlength="30" value="${escapeHTML(reservation?.customer_phone || '')}" placeholder="999 999 999"></div><div class="form-field"><label for="reservationEmail">Correo (opcional)</label><input id="reservationEmail" type="email" maxlength="190" value="${escapeHTML(reservation?.customer_email || '')}" placeholder="cliente@correo.com"></div><div class="form-field"><label for="reservationTable">Mesa</label><select id="reservationTable" required>${tableOptions}</select></div><div class="form-field"><label for="reservationGuests">Número de personas</label><input id="reservationGuests" type="number" min="1" max="30" required value="${Number(reservation?.guest_count || 4)}"></div><div class="form-field"><label for="reservationDuration">Duración</label><select id="reservationDuration">${[60,90,120,150,180,240,300,360].map(n => `<option value="${n}" ${Number(reservation?.duration_minutes || 120) === n ? 'selected' : ''}>${n / 60 === 1 ? '1 hora' : n % 60 === 0 ? `${n / 60} horas` : `${Math.floor(n / 60)} h ${n % 60} min`}</option>`).join('')}</select></div><div class="form-field form-field-wide"><label for="reservationAt">Fecha y hora</label><input id="reservationAt" type="datetime-local" required min="${dateTimeLocalValue(new Date())}" value="${reservationDateTimeInput(reservation?.reservation_at)}"></div>${!reservation ? `<div class="form-field form-field-wide"><label for="reservationInitialStatus">Estado inicial</label><select id="reservationInitialStatus"><option value="Confirmada" selected>Confirmada</option><option value="Pendiente">Pendiente de confirmación</option></select></div>` : ''}<div class="form-field form-field-wide"><label for="reservationNotes">Notas (opcional)</label><textarea id="reservationNotes" maxlength="500" rows="2" placeholder="Cumpleaños, silla para bebé, observaciones…">${escapeHTML(reservation?.notes || '')}</textarea></div></div>`;
      openWideModal(modalShell('RESERVAS', reservation ? 'Editar reserva' : 'Crear reserva', 'El horario, la capacidad y los cruces de mesa se validan en el servidor antes de guardar.', 'reservationForm', fields, reservation ? 'Guardar cambios' : 'Crear reserva'));
      $('#reservationCustomerSelect')?.addEventListener('change', () => {
        const customer = reservationCustomers.find(item => Number(item.id) === Number($('#reservationCustomerSelect').value));
        if (customer) { $('#reservationCustomer').value = customer.full_name || ''; $('#reservationPhone').value = customer.phone || ''; $('#reservationEmail').value = customer.email || ''; }
      });
      $('#reservationGuests').addEventListener('change', () => {
        const guests = Number($('#reservationGuests').value);
        const selected = options.find(table => Number(table.id) === Number($('#reservationTable').value));
        if (selected && guests > Number(selected.capacity)) showModalError(`La mesa elegida admite hasta ${selected.capacity} personas.`);
      });
      $('#reservationForm').addEventListener('submit', async event => {
        event.preventDefault();
        const submit = $('#reservationForm button[type="submit"]');
        const inputDate = $('#reservationAt').value;
        const localDate = new Date(inputDate);
        if (!inputDate || Number.isNaN(localDate.getTime())) return showModalError('Selecciona una fecha y hora válida.');
        submit.disabled = true;
        try {
          const payload = {
            action: reservation ? 'reservation.update' : 'reservation.create',
            ...(reservation ? { id: Number(reservation.id) } : { initial_status: $('#reservationInitialStatus').value }),
            customer_id: $('#reservationCustomerSelect') ? (Number($('#reservationCustomerSelect').value || 0) || null) : (Number(reservation?.customer_id || 0) || null),
            customer_name: $('#reservationCustomer').value.trim(),
            customer_phone: $('#reservationPhone').value.trim(),
            customer_email: $('#reservationEmail').value.trim(),
            table_id: Number($('#reservationTable').value),
            guest_count: Number($('#reservationGuests').value),
            duration_minutes: Number($('#reservationDuration').value),
            reservation_at: localDate.toISOString(),
            notes: $('#reservationNotes').value.trim()
          };
          const result = await apiRequest('api/reservations.php', { method: 'POST', body: JSON.stringify(payload) });
          closeModal();
          await refreshReservations();
          switchView('reservations');
          showToast(reservation ? 'Reserva actualizada' : 'Reserva creada', result.message || `${payload.customer_name} · ${options.find(table => Number(table.id) === payload.table_id)?.label || 'Mesa'}.`);
        } catch (error) { showModalError(error.message); }
        finally { submit.disabled = false; }
      });
    } catch (error) { showToast('No se pudo abrir el formulario', error.message, '!'); }
  }
  function openTableModal(table = null) {
    if (!can('tables.manage')) return showToast('Acceso restringido', 'No tienes permiso para administrar mesas.', '!');
    const suggestedNumber = Math.max(0, ...state.tables.map(item => Number(item.table_number) || 0)) + 1;
    const number = Number(table?.table_number || suggestedNumber);
    const fields = `<div class="form-grid"><div class="form-field"><label for="tableNumber">Número</label><input id="tableNumber" type="number" min="1" max="999" required value="${number}"></div><div class="form-field"><label for="tableLabel">Nombre de la mesa</label><input id="tableLabel" maxlength="40" required value="${escapeHTML(table?.label || `Mesa ${String(number).padStart(2, '0')}`)}"></div><div class="form-field"><label for="tableCapacity">Capacidad (personas)</label><input id="tableCapacity" type="number" min="1" max="30" required value="${Number(table?.capacity || 4)}"></div><div class="form-field"><label for="tableZone">Zona</label><input id="tableZone" maxlength="80" required value="${escapeHTML(table?.zone || 'Salón principal')}"></div>${table ? `<div class="form-field"><label for="tableActive">Disponibilidad administrativa</label><select id="tableActive"><option value="1" ${Number(table.is_active) === 1 ? 'selected' : ''}>Activa</option><option value="0" ${Number(table.is_active) !== 1 ? 'selected' : ''}>Inactiva</option></select></div>` : ''}</div>`;
    openModal(modalShell('MESAS', table ? `Editar ${table.label}` : 'Registrar mesa', 'La mesa se guardará en la base de datos y podrá usarse en futuras reservas.', 'tableForm', fields, table ? 'Guardar cambios' : 'Crear mesa'));
    $('#tableForm').addEventListener('submit', async event => {
      event.preventDefault();
      const submit = $('#tableForm button[type="submit"]'); submit.disabled = true;
      const payload = {
        action: table ? 'table.update' : 'table.create',
        ...(table ? { id: Number(table.id), is_active: Number($('#tableActive').value) } : {}),
        table_number: Number($('#tableNumber').value),
        label: $('#tableLabel').value.trim(),
        capacity: Number($('#tableCapacity').value),
        zone: $('#tableZone').value.trim()
      };
      try {
        const result = await apiRequest('api/reservations.php', { method: 'POST', body: JSON.stringify(payload) });
        closeModal(); await refreshReservations();
        showToast(table ? 'Mesa actualizada' : 'Mesa creada', result.message || payload.label);
      } catch (error) { showModalError(error.message); }
      finally { submit.disabled = false; }
    });
  }
  function openEditProfileModal() {
    const user = getUser();
    if (!user) return;
    const fields = `<div class="form-field"><label for="editName">Nombre completo</label><input id="editName" required minlength="3" maxlength="100" value="${escapeHTML(user.name)}"></div><div class="form-field"><label for="editEmail">Correo electrónico</label><input id="editEmail" type="email" required maxlength="190" value="${escapeHTML(user.email)}"></div>`;
    openModal(modalShell('TU CUENTA', 'Mi cuenta', 'Edita tu nombre y correo. Los cambios se guardan en MySQL.', 'editProfileForm', fields, 'Guardar cambios'));
    $('#editProfileForm').addEventListener('submit', async event => {
      event.preventDefault();
      const submit = $('#editProfileForm button[type="submit"]');
      submit.disabled = true;
      try {
        const result = await apiRequest('api/profile.php', {
          method: 'POST',
          body: JSON.stringify({ action: 'profile.update', name: $('#editName').value.trim(), email: $('#editEmail').value.trim().toLowerCase() })
        });
        state.currentUser = result.user;
        $('#welcomeName').textContent = firstName(result.user.name);
        $('#profileName').textContent = result.user.name;
        $('#profileEmail').textContent = result.user.email;
        $('#profileInitials').textContent = initials(result.user.name);
        if ($('#profileRole')) $('#profileRole').textContent = result.user.role_name || 'Colaborador';
        closeModal();
        showToast('Perfil actualizado', 'Tus datos se guardaron en la base de datos.');
      } catch (error) {
        showModalError(error.message);
      } finally {
        submit.disabled = false;
      }
    });
  }
  function openInfoModal(type) {
    if (type === 'account') {
      if (can('profile.update')) return openEditProfileModal();
      const user = getUser();
      return openModal(`<span class="modal-eyebrow">TU CUENTA</span><h2 class="modal-title" id="modalTitle">Mi cuenta</h2><p class="modal-description">Información de la cuenta autenticada en el servidor.</p><div class="modal-info-list"><div class="modal-info-row">Nombre: ${escapeHTML(user?.name || '')}</div><div class="modal-info-row">Correo: ${escapeHTML(user?.email || '')}</div><div class="modal-info-row">Rol: ${escapeHTML(user?.role_name || 'Colaborador')}</div></div><div class="modal-actions"><button type="button" class="modal-primary" data-close-modal>Entendido</button></div>`);
    }
    if (type === 'security') return openSecurityModal();
    if (type === 'settings') return openPreferencesModal();
    const content = {
      notifications: { kicker: 'CENTRO DE AVISOS', title: 'Notificaciones', description: 'Abre la bandeja para consultar y marcar tus avisos.', rows: ['Los avisos disponibles proceden de la base de datos.'] }
    }[type];
    if (!content) return;
    openModal(`<span class="modal-eyebrow">${escapeHTML(content.kicker)}</span><h2 class="modal-title" id="modalTitle">${escapeHTML(content.title)}</h2><p class="modal-description">${escapeHTML(content.description)}</p><div class="modal-info-list">${content.rows.map(row => `<div class="modal-info-row">${escapeHTML(row)}</div>`).join('')}</div><div class="modal-actions"><button type="button" class="modal-primary" data-close-modal>Entendido</button></div>`);
  }

  function normalizeProfilePreferences(prefs = {}) {
    return {
      theme_tone: Math.max(0, Math.min(100, Number(prefs.theme_tone ?? 72))),
      show_notification_badge: Boolean(Number(prefs.show_notification_badge ?? 1)),
      desktop_notifications: Boolean(Number(prefs.desktop_notifications ?? 0)),
      sound_notifications: Boolean(Number(prefs.sound_notifications ?? 0)),
      default_view: ['home', 'orders', 'menu', 'reservations', 'profile'].includes(prefs.default_view) ? prefs.default_view : 'home'
    };
  }

  async function loadProfilePreferences() {
    const result = await apiRequest('api/profile.php?action=preferences', { method: 'GET' });
    state.profilePreferences = normalizeProfilePreferences(result.preferences || {});
    applyThemeTone(state.profilePreferences.theme_tone, true);
    const badge = $('#notificationsButton .notification-badge');
    if (badge) badge.classList.toggle('is-hidden', !state.profilePreferences.show_notification_badge || Number(badge.textContent) === 0);
    // Solo aplica la pantalla inicial mientras la app sigue en Inicio, no si el usuario ya navegó.
    if (state.currentView === 'home' && state.profilePreferences.default_view !== 'home') {
      const allowed = {
        home: can('dashboard.view'),
        orders: can('orders.view'),
        menu: can('menu.view'),
        reservations: can('reservations.view') || can('tables.view') || can('reservations.manage') || can('tables.manage'),
        profile: can('profile.view')
      };
      if (allowed[state.profilePreferences.default_view]) switchView(state.profilePreferences.default_view);
    }
    return state.profilePreferences;
  }

  function openSecurityModal() {
    const fields = `
      <div class="form-field"><label for="currentPassword">Contraseña actual</label><input id="currentPassword" type="password" autocomplete="current-password" required maxlength="4096"></div>
      <div class="form-grid"><div class="form-field"><label for="newProfilePassword">Nueva contraseña</label><input id="newProfilePassword" type="password" autocomplete="new-password" required minlength="12" maxlength="4096"><small class="form-field-note">Mínimo 12 caracteres.</small></div><div class="form-field"><label for="confirmProfilePassword">Confirmar nueva contraseña</label><input id="confirmProfilePassword" type="password" autocomplete="new-password" required minlength="12" maxlength="4096"></div></div>
      <div class="modal-info-list"><div class="modal-info-row">La contraseña se guarda como hash seguro. Al cambiarla, se revocan los accesos recordados de la cuenta.</div></div>`;
    openModal(modalShell('PROTECCIÓN', 'Cambiar contraseña', 'Confirma tu contraseña actual y establece una nueva.', 'changeProfilePasswordForm', fields, 'Actualizar contraseña'));
    $('#changeProfilePasswordForm').addEventListener('submit', async event => {
      event.preventDefault();
      const form = event.currentTarget;
      const submit = form.querySelector('button[type="submit"]');
      const nextPassword = $('#newProfilePassword').value;
      const confirmPassword = $('#confirmProfilePassword').value;
      if (nextPassword.length < 12) return showModalError('La nueva contraseña debe tener al menos 12 caracteres.');
      if (nextPassword !== confirmPassword) return showModalError('Las contraseñas nuevas no coinciden.');
      submit.disabled = true;
      try {
        const result = await apiRequest('api/profile.php', { method: 'POST', body: JSON.stringify({ action: 'password.change', current_password: $('#currentPassword').value, new_password: nextPassword, confirm_password: confirmPassword }) });
        form.reset(); closeModal(); showToast('Seguridad actualizada', result.message || 'Tu contraseña fue cambiada.');
      } catch (error) {
        showModalError(error.message);
      } finally { submit.disabled = false; }
    });
  }

  async function openPreferencesModal() {
    try { await loadProfilePreferences(); } catch (error) { showToast('No se pudieron cargar los ajustes', error.message, '!'); return; }
    const prefs = state.profilePreferences;
    state.themePreviewOriginal = prefs.theme_tone;
    const fields = `
      <div class="profile-setting-block"><div class="profile-setting-head"><label for="profileThemeTone">Tonalidad del fondo</label><strong id="profileThemeToneValue">${prefs.theme_tone}% oscuro</strong></div><input id="profileThemeTone" class="profile-range" type="range" min="0" max="100" step="1" value="${prefs.theme_tone}"><small class="form-field-note">Vista previa inmediata. El cambio se guarda en tu cuenta al pulsar Guardar ajustes.</small></div>
      <div class="form-field"><label for="profileDefaultView">Pantalla inicial después de iniciar sesión</label><select id="profileDefaultView"><option value="home" ${prefs.default_view==='home'?'selected':''}>Inicio</option><option value="orders" ${prefs.default_view==='orders'?'selected':''} ${can('orders.view')?'':'disabled'}>Pedidos</option><option value="menu" ${prefs.default_view==='menu'?'selected':''} ${can('menu.view')?'':'disabled'}>Menú</option><option value="reservations" ${prefs.default_view==='reservations'?'selected':''} ${(can('reservations.view')||can('tables.view')||can('reservations.manage')||can('tables.manage'))?'':'disabled'}>Reservas</option><option value="profile" ${prefs.default_view==='profile'?'selected':''} ${can('profile.view')?'':'disabled'}>Perfil</option></select></div>
      <div class="profile-setting-block"><label class="communications-check"><input id="profileShowBadge" type="checkbox" ${prefs.show_notification_badge?'checked':''}> Mostrar el contador de notificaciones sin leer</label><label class="communications-check"><input id="profileDesktopNotifications" type="checkbox" ${prefs.desktop_notifications?'checked':''}> Permitir avisos del navegador cuando DESEGO esté en segundo plano</label><label class="communications-check"><input id="profileSoundNotifications" type="checkbox" ${prefs.sound_notifications?'checked':''}> Reproducir un sonido cuando llegue una notificación nueva</label><small class="form-field-note" id="desktopNotificationNote">Los avisos del navegador requieren permiso y HTTPS (o localhost).</small></div>
      <div class="modal-info-list"><div class="modal-info-row"><strong>Moneda contable: Sol peruano (S/).</strong><br>La moneda es fija porque los pedidos, pagos y comprobantes se registran en soles. No se cambia aquí para evitar alterar importes históricos.</div></div>`;
    openModal(modalShell('PREFERENCIAS', 'Ajustes', 'Personaliza el fondo, las notificaciones y la pantalla inicial. Se guarda en tu cuenta de DESEGO.', 'profilePreferencesForm', fields, 'Guardar ajustes'));
    const toneSlider = $('#profileThemeTone');
    const toneLabel = $('#profileThemeToneValue');
    toneSlider.addEventListener('input', () => { applyThemeTone(toneSlider.value, false); toneLabel.textContent = `${toneSlider.value}% oscuro`; });
    const desktopToggle = $('#profileDesktopNotifications');
    if (!('Notification' in window)) {
      desktopToggle.disabled = true;
      $('#desktopNotificationNote').textContent = 'Este navegador no admite notificaciones de escritorio.';
    }
    $('#profilePreferencesForm').addEventListener('submit', async event => {
      event.preventDefault();
      const form = event.currentTarget;
      const submit = form.querySelector('button[type="submit"]');
      submit.disabled = true;
      let desktopEnabled = desktopToggle.checked;
      let permissionMessage = '';
      try {
        if (desktopEnabled && 'Notification' in window && Notification.permission === 'default') {
          const permission = await Notification.requestPermission();
          if (permission !== 'granted') { desktopEnabled = false; desktopToggle.checked = false; permissionMessage = ' El navegador no concedió permiso para avisos de escritorio; esa opción quedó desactivada.'; }
        }
        if (desktopEnabled && 'Notification' in window && Notification.permission !== 'granted') {
          desktopEnabled = false; desktopToggle.checked = false;
          permissionMessage = ' Activa los permisos de notificación en el navegador para habilitar avisos de escritorio.';
        }
        const payload = {
          action: 'preferences.update',
          theme_tone: Number(toneSlider.value),
          show_notification_badge: $('#profileShowBadge').checked,
          desktop_notifications: desktopEnabled,
          sound_notifications: $('#profileSoundNotifications').checked,
          default_view: $('#profileDefaultView').value
        };
        const result = await apiRequest('api/profile.php', { method: 'POST', body: JSON.stringify(payload) });
        state.profilePreferences = normalizeProfilePreferences(result.preferences || payload);
        state.themePreviewOriginal = null;
        applyThemeTone(state.profilePreferences.theme_tone, true);
        const badge = $('#notificationsButton .notification-badge');
        if (badge) badge.classList.toggle('is-hidden', !state.profilePreferences.show_notification_badge || Number(badge.textContent) === 0);
        closeModal();
        showToast('Ajustes guardados', `${result.message || 'Preferencias actualizadas.'}${permissionMessage}`);
      } catch (error) {
        showModalError(error.message);
      } finally { submit.disabled = false; }
    });
  }

  function playNotificationSound() {
    if (!state.profilePreferences.sound_notifications) return;
    try {
      const AudioContextClass = window.AudioContext || window.webkitAudioContext;
      if (!AudioContextClass) return;
      const audio = new AudioContextClass();
      const oscillator = audio.createOscillator();
      const gain = audio.createGain();
      oscillator.type = 'sine'; oscillator.frequency.value = 880;
      gain.gain.setValueAtTime(0.0001, audio.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.12, audio.currentTime + 0.025);
      gain.gain.exponentialRampToValueAtTime(0.0001, audio.currentTime + 0.22);
      oscillator.connect(gain); gain.connect(audio.destination);
      oscillator.start(); oscillator.stop(audio.currentTime + 0.23);
      oscillator.onended = () => audio.close().catch(() => {});
    } catch (_) { /* El navegador puede bloquear audio. */ }
  }

  function sendDesktopNotification(title, message) {
    if (!state.profilePreferences.desktop_notifications || !('Notification' in window) || Notification.permission !== 'granted' || !document.hidden) return;
    try { new Notification(title, { body: message, icon: 'assets/desego-logo.png', tag: 'desego-notification' }); } catch (_) { /* Permiso o entorno no compatible. */ }
  }

  /* Selector de tono del azul, conservando la preferencia visual en este navegador. */
  function hexToRgb(hex) {
    const value = String(hex).replace('#', '');
    return { r: parseInt(value.slice(0, 2), 16), g: parseInt(value.slice(2, 4), 16), b: parseInt(value.slice(4, 6), 16) };
  }
  function mixHex(start, end, amount) {
    const a = hexToRgb(start), b = hexToRgb(end);
    const part = key => Math.round(a[key] + (b[key] - a[key]) * amount).toString(16).padStart(2, '0');
    return `#${part('r')}${part('g')}${part('b')}`;
  }
  function applyThemeTone(value, persist = true) {
    const tone = Math.max(0, Math.min(100, Number(value) || 0));
    const amount = tone / 100;
    const root = document.documentElement;
    const primary = mixHex('#004797', '#002E63', amount * 0.5);
    root.style.setProperty('--bg-screen-blue', '#F8FAFC');
    root.style.setProperty('--color-primary-blue', primary);
    root.style.setProperty('--bg-login-gradient', 'linear-gradient(135deg, #F8FAFC 0%, #EEF4FB 100%)');
    state.themeTone = tone;
    const slider = $('#themeDarkness');
    const label = $('#themeToneLabel');
    if (slider) slider.value = String(tone);
    if (label) label.textContent = tone < 18 ? 'Azul corporativo' : tone > 88 ? 'Azul ejecutivo' : 'Azul institucional';
    $$('.theme-preset').forEach(button => button.classList.toggle('is-active', Number(button.dataset.themePreset) === tone));
    const themeMeta = document.querySelector('meta[name="theme-color"]');
    if (themeMeta) themeMeta.content = primary;
    if (persist) safeSet('desego_mobile_theme_tone', String(tone));
  }
  function initThemeSelector() {
    const popover = $('#themePopover');
    const toggle = $('#themeToggleButton');
    const close = $('#themeCloseButton');
    const slider = $('#themeDarkness');
    if (!popover || !toggle || !slider) return;
    const savedTone = Number(safeGet('desego_mobile_theme_tone'));
    applyThemeTone(Number.isFinite(savedTone) && safeGet('desego_mobile_theme_tone') !== null ? savedTone : Number(slider.value), false);
    const setOpen = open => {
      popover.classList.toggle('is-hidden', !open);
      toggle.setAttribute('aria-expanded', String(open));
    };
    toggle.addEventListener('click', () => setOpen(popover.classList.contains('is-hidden')));
    if (close) close.addEventListener('click', () => setOpen(false));
    slider.addEventListener('input', () => applyThemeTone(slider.value));
    $$('.theme-preset').forEach(button => button.addEventListener('click', () => applyThemeTone(Number(button.dataset.themePreset))));
    document.addEventListener('click', event => {
      if (!popover.classList.contains('is-hidden') && !popover.contains(event.target) && !toggle.contains(event.target)) setOpen(false);
    });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') setOpen(false); });
  }

  /* Administración de usuarios, roles y permisos (backend MySQL). */
  function can(permission) {
    return Boolean(state.currentUser && (state.currentUser.permissions || []).includes(permission));
  }
  function showModalError(message) {
    let box = $('#modalBody .manager-error');
    if (!box) {
      box = document.createElement('p');
      box.className = 'manager-error';
      box.setAttribute('role', 'alert');
      $('#modalBody').prepend(box);
    }
    box.textContent = message;
  }
  async function openUserManagementModal(tab = 'users') {
    if (!can('users.view')) {
      showToast('Acceso restringido', 'Tu rol no tiene permiso para consultar usuarios.', '!');
      return;
    }
    openModal(`<div class="admin-manager"><span class="modal-eyebrow">ADMINISTRACIÓN</span><h2 class="modal-title" id="modalTitle">Usuarios y permisos</h2><p class="modal-description">Cuentas de acceso, roles y permisos almacenados en MySQL.</p><div class="manager-tabs"><button type="button" data-manager-tab="users" class="manager-tab is-active">Usuarios</button>${can('roles.manage') ? '<button type="button" data-manager-tab="roles" class="manager-tab">Roles y permisos</button>' : ''}</div><div id="managerContent"><p class="manager-loading">Cargando datos del servidor…</p></div></div>`);
    $('#modalBody').removeEventListener('click', managerClickHandler);
    $('#modalBody').addEventListener('click', managerClickHandler);
    await refreshManagerTab(tab);
  }
  async function refreshManagerTab(tab = 'users') {
    const content = $('#managerContent');
    if (!content) return;
    content.innerHTML = '<p class="manager-loading">Cargando datos del servidor…</p>';
    $$('.manager-tab').forEach(button => button.classList.toggle('is-active', button.dataset.managerTab === tab));
    try {
      if (tab === 'roles') {
        if (!can('roles.manage')) throw new Error('No tienes permiso para administrar roles.');
        const result = await apiRequest('api/roles.php', { method: 'GET' });
        renderRoleManager(result);
      } else {
        const result = await apiRequest('api/users.php', { method: 'GET' });
        renderUserManager(result);
      }
    } catch (error) {
      content.innerHTML = `<p class="manager-error" role="alert">${escapeHTML(error.message)}</p><button type="button" class="secondary-button" data-manager-reload="${escapeHTML(tab)}">Reintentar</button>`;
    }
  }
  function managerClickHandler(event) {
    const tab = event.target.closest('[data-manager-tab]');
    if (tab) { refreshManagerTab(tab.dataset.managerTab); return; }
    const reload = event.target.closest('[data-manager-reload]');
    if (reload) { refreshManagerTab(reload.dataset.managerReload); return; }
    const editUser = event.target.closest('[data-edit-user]');
    if (editUser) { openEditUserModal(Number(editUser.dataset.editUser)); return; }
    const toggleUser = event.target.closest('[data-toggle-user]');
    if (toggleUser) { changeUserStatus(Number(toggleUser.dataset.toggleUser), toggleUser.dataset.status); return; }
    const editRole = event.target.closest('[data-edit-role]');
    if (editRole) { openRoleEditor(Number(editRole.dataset.editRole)); return; }
    if (event.target.closest('[data-create-role]')) { openRoleEditor(null); }
  }
  function renderUserManager(data) {
    const content = $('#managerContent');
    if (!content) return;
    const canCreate = can('users.create');
    const canEdit = can('users.update');
    const canDeactivate = can('users.deactivate');
    const roleOptions = (data.roles || []).filter(role => role.code !== 'admin' || can('roles.manage'))
      .map(role => `<option value="${Number(role.id)}">${escapeHTML(role.name)}</option>`).join('');
    const createForm = canCreate ? `<form id="newSystemUserForm" class="manager-create-form"><h3>Crear usuario</h3><div class="form-field"><label for="newSystemUserName">Nombre completo</label><input id="newSystemUserName" required minlength="3" maxlength="100" placeholder="Nombre del colaborador"></div><div class="form-field"><label for="newSystemUserEmail">Correo</label><input id="newSystemUserEmail" type="email" required maxlength="190" placeholder="correo@desego.com"></div><div class="form-field"><label for="newSystemUserPassword">Contraseña temporal</label><input id="newSystemUserPassword" type="password" required minlength="10" autocomplete="new-password" placeholder="Mínimo 10 caracteres"></div><div class="form-field"><label for="newSystemUserRole">Rol</label><select id="newSystemUserRole" required>${roleOptions}</select></div><button class="modal-primary manager-full-button" type="submit">Crear cuenta</button></form>` : '';
    const rows = (data.users || []).map(user => `<article class="manager-user-card"><div class="manager-user-heading"><div class="manager-avatar">${escapeHTML(initials(user.name))}</div><div class="manager-user-copy"><strong>${escapeHTML(user.name)}</strong><small>${escapeHTML(user.email)}</small><div class="manager-user-meta"><span class="manager-role">${escapeHTML(user.role_name)}</span><span class="manager-state ${user.status === 'active' ? 'is-active' : 'is-inactive'}">${user.status === 'active' ? 'Activo' : 'Inactivo'}</span></div></div></div><div class="manager-user-actions">${canEdit ? `<button type="button" class="secondary-button" data-edit-user="${Number(user.id)}">Editar</button>` : ''}${canDeactivate && !user.is_self ? `<button type="button" class="secondary-button ${user.status === 'active' ? 'manager-danger' : ''}" data-toggle-user="${Number(user.id)}" data-status="${user.status === 'active' ? 'inactive' : 'active'}">${user.status === 'active' ? 'Desactivar' : 'Activar'}</button>` : ''}</div></article>`).join('');
    content.innerHTML = `${createForm}<div class="manager-list-heading"><h3>Usuarios registrados</h3><span>${(data.users || []).length}</span></div><div class="manager-user-list">${rows || '<p class="manager-empty">No hay usuarios registrados.</p>'}</div>`;
    if (canCreate) {
      $('#newSystemUserForm').addEventListener('submit', async event => {
        event.preventDefault();
        const submit = $('#newSystemUserForm button[type="submit"]');
        submit.disabled = true;
        try {
          await apiRequest('api/users.php', { method: 'POST', body: JSON.stringify({
            action: 'create',
            name: $('#newSystemUserName').value.trim(),
            email: $('#newSystemUserEmail').value.trim().toLowerCase(),
            password: $('#newSystemUserPassword').value,
            role_id: Number($('#newSystemUserRole').value)
          }) });
          showToast('Usuario creado', 'La cuenta quedó guardada en la base de datos.');
          await refreshManagerTab('users');
        } catch (error) { showModalError(error.message); }
        finally { submit.disabled = false; }
      });
    }
  }
  async function changeUserStatus(id, status) {
    const actionWord = status === 'inactive' ? 'desactivar' : 'activar';
    if (!window.confirm(`¿Deseas ${actionWord} esta cuenta?`)) return;
    try {
      const result = await apiRequest('api/users.php', { method: 'POST', body: JSON.stringify({ action: 'status', id, status }) });
      showToast('Usuarios', result.message);
      await refreshManagerTab('users');
    } catch (error) { showModalError(error.message); }
  }
  async function openEditUserModal(id) {
    try {
      const result = await apiRequest('api/users.php', { method: 'GET' });
      const user = result.users.find(item => Number(item.id) === Number(id));
      if (!user) throw new Error('No se encontró el usuario.');
      const roles = result.roles.filter(role => role.code !== 'admin' || can('roles.manage'));
      const options = roles.map(role => `<option value="${Number(role.id)}" ${Number(role.id) === Number(user.role_id) ? 'selected' : ''}>${escapeHTML(role.name)}</option>`).join('');
      const fields = `<input type="hidden" id="editSystemUserId" value="${Number(user.id)}"><div class="form-field"><label for="editSystemUserName">Nombre</label><input id="editSystemUserName" required minlength="3" maxlength="100" value="${escapeHTML(user.name)}"></div><div class="form-field"><label for="editSystemUserEmail">Correo</label><input id="editSystemUserEmail" type="email" required maxlength="190" value="${escapeHTML(user.email)}"></div><div class="form-field"><label for="editSystemUserRole">Rol</label><select id="editSystemUserRole" required>${options}</select></div>`;
      openModal(modalShell('USUARIOS', 'Editar usuario', 'Los cambios se aplicarán a la cuenta real.', 'editSystemUserForm', fields, 'Guardar cambios'));
      $('#editSystemUserForm').addEventListener('submit', async event => {
        event.preventDefault();
        try {
          await apiRequest('api/users.php', { method: 'POST', body: JSON.stringify({ action: 'update', id: Number($('#editSystemUserId').value), name: $('#editSystemUserName').value.trim(), email: $('#editSystemUserEmail').value.trim().toLowerCase(), role_id: Number($('#editSystemUserRole').value) }) });
          closeModal();
          showToast('Usuario actualizado', 'Cambios guardados en la base de datos.');
          await openUserManagementModal('users');
        } catch (error) { showModalError(error.message); }
      });
    } catch (error) { showToast('No se pudo abrir el usuario', error.message, '!'); }
  }
  function renderRoleManager(data) {
    const content = $('#managerContent');
    if (!content) return;
    const rolesHtml = (data.roles || []).map(role => {
      const count = (role.permission_ids || []).length;
      return `<article class="manager-role-card"><div><strong>${escapeHTML(role.name)}</strong><small>${escapeHTML(role.description || role.code)}</small><span class="manager-role-count">${count} permisos</span></div>${role.code === 'admin' ? '<span class="manager-protected">Protegido</span>' : `<button type="button" class="secondary-button" data-edit-role="${Number(role.id)}">Editar</button>`}</article>`;
    }).join('');
    content.innerHTML = `<div class="manager-role-toolbar"><p>Los permisos se comprueban en el servidor. El rol administrador está protegido.</p><button type="button" class="modal-primary" data-create-role>+ Crear rol</button></div><div class="manager-role-list">${rolesHtml}</div><p class="manager-footnote">Hay ${data.permissions.length} permisos definidos en esta primera fase. Los permisos de módulos futuros se agregarán al implementar cada módulo.</p>`;
  }
  async function openRoleEditor(roleId = null) {
    try {
      const result = await apiRequest('api/roles.php', { method: 'GET' });
      const role = roleId ? result.roles.find(item => Number(item.id) === Number(roleId)) : null;
      if (role && role.code === 'admin') throw new Error('El rol administrador está protegido.');
      const groups = {};
      result.permissions.forEach(permission => (groups[permission.module] ||= []).push(permission));
      const checks = Object.entries(groups).map(([module, permissions]) => `<fieldset class="permission-group"><legend>${escapeHTML(module)}</legend>${permissions.map(permission => `<label class="permission-check"><input type="checkbox" value="${escapeHTML(permission.code)}" ${role && (role.permission_ids || []).includes(Number(permission.id)) ? 'checked' : ''}><span><strong>${escapeHTML(permission.name)}</strong><small>${escapeHTML(permission.code)}</small></span></label>`).join('')}</fieldset>`).join('');
      const fields = `<div class="form-field"><label for="roleNameInput">Nombre del rol</label><input id="roleNameInput" required minlength="3" maxlength="80" value="${escapeHTML(role?.name || '')}" placeholder="Ej. Supervisor"></div><div class="form-field"><label for="roleDescriptionInput">Descripción</label><input id="roleDescriptionInput" maxlength="255" value="${escapeHTML(role?.description || '')}" placeholder="Responsabilidades del rol"></div><div class="permissions-heading">Permisos habilitados</div><div class="permission-groups">${checks}</div>`;
      openModal(modalShell('ROLES Y PERMISOS', role ? 'Editar rol' : 'Crear rol', 'Selecciona únicamente los permisos que necesita este rol.', 'roleEditorForm', fields, 'Guardar rol'));
      $('#roleEditorForm').addEventListener('submit', async event => {
        event.preventDefault();
        const submit = $('#roleEditorForm button[type="submit"]');
        submit.disabled = true;
        const permissionCodes = $$('#roleEditorForm input[type="checkbox"]:checked').map(input => input.value);
        try {
          await apiRequest('api/roles.php', { method: 'POST', body: JSON.stringify({ action: role ? 'update' : 'create', ...(role ? { id: role.id } : {}), name: $('#roleNameInput').value.trim(), description: $('#roleDescriptionInput').value.trim(), permissions: permissionCodes }) });
          closeModal();
          showToast('Roles y permisos', role ? 'Rol actualizado.' : 'Rol creado.');
          await openUserManagementModal('roles');
        } catch (error) { showModalError(error.message); }
        finally { submit.disabled = false; }
      });
    } catch (error) { showToast('No se pudo abrir el editor', error.message, '!'); }
  }


  /* Clientes y direcciones: datos persistidos en MySQL con permisos de servidor. */
  function initCustomerActions() {
    $('#newCustomerButton')?.addEventListener('click', () => openCustomerForm());
    $('#refreshCustomersButton')?.addEventListener('click', () => refreshCustomers($('#customerSearch')?.value || '').then(() => showToast('Clientes actualizados', 'Se recargaron los datos desde MySQL.')).catch(error => showToast('No se pudo actualizar', error.message, '!')));
    $('#customerSearch')?.addEventListener('input', () => {
      clearTimeout(state.customerSearchTimer);
      state.customerSearchTimer = setTimeout(() => refreshCustomers($('#customerSearch').value).catch(error => showToast('No se pudo buscar', error.message, '!')), 220);
    });
    $('#customersList')?.addEventListener('click', async event => {
      const edit = event.target.closest('[data-edit-customer]');
      const addresses = event.target.closest('[data-customer-addresses]');
      const status = event.target.closest('[data-customer-status]');
      const loyalty = event.target.closest('[data-customer-loyalty]');
      if (loyalty) { switchView('loyalty'); openCustomerLoyaltyHistory(Number(loyalty.dataset.customerLoyalty)).catch(error => showToast('No se pudo cargar la cuenta', error.message, '!')); return; }
      if (edit) { const customer = state.customers.find(row => Number(row.id) === Number(edit.dataset.editCustomer)); if (customer) openCustomerForm(customer); return; }
      if (addresses) { const customer = state.customers.find(row => Number(row.id) === Number(addresses.dataset.customerAddresses)); if (customer) openCustomerAddressesModal(customer); return; }
      if (status) { await toggleCustomerStatus(Number(status.dataset.customerStatus), status.dataset.nextStatus); }
    });
  }

  async function refreshCustomers(query = '') {
    if (!state.currentUser || !(can('customers.view') || can('customers.manage') || can('customer_addresses.manage'))) return;
    const list = $('#customersList');
    if (list && state.currentView === 'customers') list.innerHTML = '<div class="manager-loading">Cargando clientes desde MySQL…</div>';
    const result = await apiRequest(`api/customers.php?view=list&q=${encodeURIComponent(query)}`, { method: 'GET' });
    state.customers = result.customers || [];
    state.customerPermissions = { can_manage: Boolean(result.can_manage), can_manage_addresses: Boolean(result.can_manage_addresses) };
    renderCustomers();
  }

  function renderCustomers() {
    const list = $('#customersList');
    if (!list) return;
    const customers = state.customers || [];
    const active = customers.filter(customer => customer.status === 'active').length;
    const addresses = customers.reduce((sum, customer) => sum + Number(customer.addresses_count || 0), 0);
    $('#customerSummary').innerHTML = `
      <article class="operations-metric"><span class="operations-metric-icon metric-blue">♙</span><div><small>Clientes encontrados</small><strong>${customers.length}</strong></div></article>
      <article class="operations-metric"><span class="operations-metric-icon metric-green">⌂</span><div><small>Direcciones activas</small><strong>${addresses}</strong></div></article>
      <article class="operations-metric"><span class="operations-metric-icon metric-amber">✓</span><div><small>Clientes activos</small><strong>${active}</strong></div></article>`;
    if (!customers.length) {
      list.innerHTML = '<div class="empty-state"><span>♙</span><strong>No se encontraron clientes</strong><p>Registra un cliente o ajusta tu búsqueda.</p></div>';
      return;
    }
    list.innerHTML = customers.map(customer => {
      const active = customer.status === 'active';
      const initialsText = initials(customer.full_name || 'CL');
      const documentText = customer.document_number ? `${customer.document_type} ${customer.document_number}` : 'Sin documento';
      const edit = can('customers.manage') ? `<button type="button" class="secondary-button" data-edit-customer="${Number(customer.id)}">Editar ficha</button>` : '';
      const status = can('customers.manage') ? `<button type="button" class="secondary-button ${active ? 'manager-danger' : ''}" data-customer-status="${Number(customer.id)}" data-next-status="${active ? 'inactive' : 'active'}">${active ? 'Desactivar' : 'Activar'}</button>` : '';
      const addressButton = (can('customers.view') || can('customers.manage') || can('customer_addresses.manage')) ? `<button type="button" class="customer-primary-action" data-customer-addresses="${Number(customer.id)}">Direcciones (${Number(customer.addresses_count || 0)})</button>` : '';
      const loyaltyButton = (can('loyalty.view') || can('loyalty.manage')) ? `<button type="button" class="secondary-button" data-customer-loyalty="${Number(customer.id)}">✦ ${Number(customer.points_balance || 0)} puntos</button>` : '';
      return `<article class="white-card customer-card ${active ? '' : 'is-inactive'}">
        <div class="customer-card-head"><span class="customer-avatar">${escapeHTML(initialsText)}</span><div class="customer-heading-copy"><span class="section-eyebrow">${escapeHTML(customer.customer_code || `CLI-${customer.id}`)}</span><h2>${escapeHTML(customer.full_name)}</h2><span class="customer-status ${active ? 'customer-status-active' : 'customer-status-inactive'}">${active ? 'Activo' : 'Inactivo'}</span></div></div>
        <div class="customer-contact-list"><div><small>Documento</small><strong>${escapeHTML(documentText)}</strong></div><div><small>Teléfono</small><strong>${escapeHTML(customer.phone || 'No registrado')}</strong></div><div><small>Correo</small><strong>${escapeHTML(customer.email || 'No registrado')}</strong></div></div>
        <div class="customer-card-stats"><span>⌂ ${Number(customer.addresses_count || 0)} direcciones</span><span>▤ ${Number(customer.orders_count || 0)} pedidos</span><span>▦ ${Number(customer.reservations_count || 0)} reservas</span></div>
        <div class="customer-card-actions">${addressButton}${loyaltyButton}${edit}${status}</div>
      </article>`;
    }).join('');
  }

  function openCustomerForm(customer = null) {
    if (!can('customers.manage')) return showToast('Acceso restringido', 'Tu rol no puede crear ni editar fichas de clientes.', '!');
    const customerName = customer?.full_name || '';
    const fields = `<div class="form-grid">
      <div class="form-field form-field-wide"><label for="customerFullName">Nombre completo *</label><input id="customerFullName" required minlength="2" maxlength="120" value="${escapeHTML(customerName)}" placeholder="Nombre y apellidos"></div>
      <div class="form-field"><label for="customerDocumentType">Tipo de documento</label><select id="customerDocumentType">${['SIN_DOCUMENTO','DNI','RUC','CE','PASAPORTE','OTRO'].map(type => `<option value="${type}" ${(customer?.document_type || 'SIN_DOCUMENTO') === type ? 'selected' : ''}>${type === 'SIN_DOCUMENTO' ? 'Sin documento' : type}</option>`).join('')}</select></div>
      <div class="form-field"><label for="customerDocumentNumber">N.º de documento</label><input id="customerDocumentNumber" maxlength="30" value="${escapeHTML(customer?.document_number || '')}" placeholder="DNI / RUC / CE"></div>
      <div class="form-field"><label for="customerPhone">Teléfono</label><input id="customerPhone" maxlength="30" value="${escapeHTML(customer?.phone || '')}" placeholder="999 999 999"></div>
      <div class="form-field"><label for="customerEmail">Correo electrónico</label><input id="customerEmail" type="email" maxlength="190" value="${escapeHTML(customer?.email || '')}" placeholder="cliente@correo.com"></div>
      <div class="form-field"><label for="customerBirthDate">Fecha de nacimiento</label><input id="customerBirthDate" type="date" max="${new Date().toISOString().slice(0,10)}" value="${escapeHTML(customer?.birth_date || '')}"></div>
      <div class="form-field form-field-wide"><label for="customerNotes">Notas internas</label><textarea id="customerNotes" rows="2" maxlength="500" placeholder="Preferencias, observaciones…">${escapeHTML(customer?.notes || '')}</textarea></div>
    </div>`;
    openModal(modalShell('CLIENTES', customer ? 'Editar cliente' : 'Nuevo cliente', 'La ficha se guarda en MySQL y podrá asociarse a pedidos, reservas y direcciones.', 'customerForm', fields, customer ? 'Guardar cambios' : 'Crear cliente'));
    $('#customerDocumentType').addEventListener('change', () => {
      if ($('#customerDocumentType').value === 'SIN_DOCUMENTO') $('#customerDocumentNumber').value = '';
    });
    $('#customerForm').addEventListener('submit', async event => {
      event.preventDefault();
      const submit = $('#customerForm button[type="submit"]');
      submit.disabled = true;
      const payload = {
        action: customer ? 'customer.update' : 'customer.create', ...(customer ? { id: Number(customer.id) } : {}),
        full_name: $('#customerFullName').value.trim(), document_type: $('#customerDocumentType').value,
        document_number: $('#customerDocumentNumber').value.trim() || null, phone: $('#customerPhone').value.trim(),
        email: $('#customerEmail').value.trim().toLowerCase(), birth_date: $('#customerBirthDate').value || null,
        notes: $('#customerNotes').value.trim()
      };
      try {
        const result = await apiRequest('api/customers.php', { method: 'POST', body: JSON.stringify(payload) });
        closeModal(); await refreshCustomers($('#customerSearch')?.value || '');
        showToast(customer ? 'Cliente actualizado' : 'Cliente creado', result.message || 'Cambios guardados en MySQL.');
        switchView('customers');
      } catch (error) { showModalError(error.message); }
      finally { submit.disabled = false; }
    });
  }

  async function toggleCustomerStatus(id, status) {
    if (!can('customers.manage')) return showToast('Acceso restringido', 'Tu rol no puede cambiar el estado de clientes.', '!');
    const customer = state.customers.find(item => Number(item.id) === Number(id));
    if (!customer) return;
    if (!window.confirm(`¿Deseas ${status === 'inactive' ? 'desactivar' : 'activar'} a ${customer.full_name}?`)) return;
    try {
      const result = await apiRequest('api/customers.php', { method: 'POST', body: JSON.stringify({ action: 'customer.status', id, status }) });
      await refreshCustomers($('#customerSearch')?.value || '');
      showToast('Estado del cliente', result.message);
    } catch (error) { showToast('No se pudo cambiar el estado', error.message, '!'); }
  }

  async function openCustomerAddressesModal(customer) {
    if (!(can('customers.view') || can('customers.manage') || can('customer_addresses.manage'))) return showToast('Acceso restringido', 'Tu rol no puede consultar clientes.', '!');
    try {
      const result = await apiRequest(`api/customers.php?view=detail&id=${Number(customer.id)}`, { method: 'GET' });
      const item = result.customer;
      renderCustomerAddressesModal(item, result.addresses || []);
    } catch (error) { showToast('No se pudieron cargar las direcciones', error.message, '!'); }
  }

  function renderCustomerAddressesModal(customer, addresses) {
    const canManage = can('customers.manage') || can('customer_addresses.manage');
    const rows = addresses.map(address => {
      const fullAddress = [address.address_line,address.reference,address.district,address.province,address.department].filter(Boolean).join(' · ');
      const inactive = address.status !== 'active';
      return `<article class="customer-address-row ${inactive ? 'is-inactive' : ''}"><div class="customer-address-symbol">⌂</div><div class="customer-address-copy"><div class="customer-address-title"><strong>${escapeHTML(address.label)}</strong>${Number(address.is_default) === 1 ? '<span class="address-default-badge">Predeterminada</span>' : ''}${inactive ? '<span class="customer-status customer-status-inactive">Inactiva</span>' : ''}</div><p>${escapeHTML(fullAddress)}</p><small>${escapeHTML(address.recipient_name || customer.full_name)} · ${escapeHTML(address.phone || customer.phone || 'Sin teléfono')}</small></div><div class="customer-address-actions">${canManage && !inactive && Number(address.is_default) !== 1 ? `<button type="button" class="secondary-button" data-address-default="${Number(address.id)}">Predeterminada</button>` : ''}${canManage ? `<button type="button" class="secondary-button" data-edit-address="${Number(address.id)}">Editar</button><button type="button" class="secondary-button ${inactive ? '' : 'manager-danger'}" data-address-status="${Number(address.id)}" data-next-status="${inactive ? 'active' : 'inactive'}">${inactive ? 'Activar' : 'Desactivar'}</button>` : ''}</div></article>`;
    }).join('');
    openWideModal(`<div class="customer-address-manager"><span class="modal-eyebrow">LIBRETA DE DIRECCIONES</span><h2 class="modal-title" id="modalTitle">${escapeHTML(customer.full_name)}</h2><p class="modal-description">Direcciones asociadas a la ficha. La dirección predeterminada se sugiere para pedidos Delivery.</p><div class="customer-address-toolbar"><span>${addresses.filter(address => address.status === 'active').length} activas de ${addresses.length}</span>${canManage ? '<button type="button" id="newCustomerAddressButton" class="modal-primary">＋ Nueva dirección</button>' : ''}</div><div id="customerAddressRows" class="customer-address-list">${rows || '<p class="catalog-note">Este cliente todavía no tiene direcciones guardadas.</p>'}</div></div>`);
    $('#newCustomerAddressButton')?.addEventListener('click', () => openCustomerAddressForm(customer, null));
    $('#modalBody').onclick = async event => {
      const edit = event.target.closest('[data-edit-address]');
      const status = event.target.closest('[data-address-status]');
      const makeDefault = event.target.closest('[data-address-default]');
      if (edit) { const address = addresses.find(row => Number(row.id) === Number(edit.dataset.editAddress)); if (address) openCustomerAddressForm(customer, address); return; }
      if (status) {
        try {
          await apiRequest('api/customers.php', { method: 'POST', body: JSON.stringify({ action: 'address.status', id: Number(status.dataset.addressStatus), customer_id: Number(customer.id), status: status.dataset.nextStatus }) });
          const detail = await apiRequest(`api/customers.php?view=detail&id=${Number(customer.id)}`, { method: 'GET' }); renderCustomerAddressesModal(detail.customer, detail.addresses || []); await refreshCustomers($('#customerSearch')?.value || ''); showToast('Dirección actualizada', 'Se guardó el estado de la dirección.');
        } catch (error) { showModalError(error.message); }
        return;
      }
      if (makeDefault) {
        try {
          const result = await apiRequest('api/customers.php', { method: 'POST', body: JSON.stringify({ action: 'address.default', id: Number(makeDefault.dataset.addressDefault), customer_id: Number(customer.id) }) });
          const detail = await apiRequest(`api/customers.php?view=detail&id=${Number(customer.id)}`, { method: 'GET' }); renderCustomerAddressesModal(detail.customer, detail.addresses || []); showToast('Dirección predeterminada', result.message);
        } catch (error) { showModalError(error.message); }
      }
    };
  }

  function openCustomerAddressForm(customer, address = null) {
    if (!(can('customers.manage') || can('customer_addresses.manage'))) return showToast('Acceso restringido', 'Tu rol no puede administrar direcciones.', '!');
    const fields = `<input type="hidden" id="customerAddressCustomerId" value="${Number(customer.id)}"><div class="form-grid">
      <div class="form-field"><label for="addressLabel">Etiqueta *</label><input id="addressLabel" required maxlength="60" value="${escapeHTML(address?.label || 'Casa')}" placeholder="Casa, trabajo…"></div>
      <div class="form-field"><label for="addressRecipient">Destinatario</label><input id="addressRecipient" maxlength="120" value="${escapeHTML(address?.recipient_name || customer.full_name)}"></div>
      <div class="form-field"><label for="addressPhone">Teléfono de entrega</label><input id="addressPhone" maxlength="30" value="${escapeHTML(address?.phone || customer.phone || '')}"></div>
      <div class="form-field form-field-wide"><label for="addressLine">Dirección *</label><input id="addressLine" required maxlength="255" value="${escapeHTML(address?.address_line || '')}" placeholder="Av./Calle, número, interior"></div>
      <div class="form-field form-field-wide"><label for="addressReference">Referencia</label><input id="addressReference" maxlength="255" value="${escapeHTML(address?.reference || '')}" placeholder="Frente al parque, portón negro…"></div>
      <div class="form-field"><label for="addressDistrict">Distrito</label><input id="addressDistrict" maxlength="100" value="${escapeHTML(address?.district || '')}"></div>
      <div class="form-field"><label for="addressProvince">Provincia</label><input id="addressProvince" maxlength="100" value="${escapeHTML(address?.province || 'Lima')}"></div>
      <div class="form-field"><label for="addressDepartment">Departamento</label><input id="addressDepartment" maxlength="100" value="${escapeHTML(address?.department || 'Lima')}"></div>
      <div class="form-field"><label for="addressPostal">Código postal</label><input id="addressPostal" maxlength="20" value="${escapeHTML(address?.postal_code || '')}"></div>
      <div class="form-field"><label for="addressLatitude">Latitud (opcional)</label><input id="addressLatitude" type="number" min="-90" max="90" step="0.0000001" value="${address?.latitude ?? ''}"></div>
      <div class="form-field"><label for="addressLongitude">Longitud (opcional)</label><input id="addressLongitude" type="number" min="-180" max="180" step="0.0000001" value="${address?.longitude ?? ''}"></div>
      <div class="form-field form-field-wide"><label class="address-default-check"><input id="addressIsDefault" type="checkbox" ${Number(address?.is_default || 0) === 1 || !address ? 'checked' : ''}> Usar como dirección predeterminada</label></div>
    </div>`;
    openModal(modalShell('CLIENTES · DIRECCIONES', address ? 'Editar dirección' : 'Nueva dirección', 'La dirección puede reutilizarse en futuros pedidos Delivery.', 'customerAddressForm', fields, address ? 'Guardar dirección' : 'Agregar dirección'));
    $('#customerAddressForm').addEventListener('submit', async event => {
      event.preventDefault(); const submit = $('#customerAddressForm button[type="submit"]'); submit.disabled = true;
      const payload = { action: address ? 'address.update' : 'address.create', customer_id: Number(customer.id), ...(address ? { id: Number(address.id) } : {}), label: $('#addressLabel').value.trim(), recipient_name: $('#addressRecipient').value.trim(), phone: $('#addressPhone').value.trim(), address_line: $('#addressLine').value.trim(), reference: $('#addressReference').value.trim(), district: $('#addressDistrict').value.trim(), province: $('#addressProvince').value.trim(), department: $('#addressDepartment').value.trim(), postal_code: $('#addressPostal').value.trim(), latitude: $('#addressLatitude').value || null, longitude: $('#addressLongitude').value || null, is_default: $('#addressIsDefault').checked };
      try {
        const result = await apiRequest('api/customers.php', { method: 'POST', body: JSON.stringify(payload) });
        const detail = await apiRequest(`api/customers.php?view=detail&id=${Number(customer.id)}`, { method: 'GET' });
        renderCustomerAddressesModal(detail.customer, detail.addresses || []); await refreshCustomers($('#customerSearch')?.value || ''); showToast('Dirección guardada', result.message);
      } catch (error) { showModalError(error.message); }
      finally { submit.disabled = false; }
    });
  }

  /* Limpia únicamente datos operativos de antiguas demos; reservas y mesas viven en MySQL. */
  function saveArrays() {
    safeRemove(KEY.reservations);
  }
  function loadArrays() {
    safeRemove(KEY.reservations);
    safeRemove(KEY.orders); // limpia los pedidos antiguos almacenados localmente en versiones demo
    state.orders = [];
  }
  function renderAll() {
    renderTables(); renderOrders(); renderProducts(); renderReservations();
  }
  function updateToday() {
    const value = new Intl.DateTimeFormat('es-PE', { weekday: 'short', day: 'numeric', month: 'short' }).format(new Date());
    $('#todayChip').textContent = value.charAt(0).toUpperCase() + value.slice(1).replace('.', '');
  }
  function showToast(title, message, symbol = '✓') {
    const toast = $('#toast');
    $('#toastTitle').textContent = title;
    $('#toastMessage').textContent = message;
    $('#toastIcon').textContent = symbol;
    $('#toastIcon').classList.toggle('is-alert', symbol === '!');
    toast.classList.add('is-visible');
    clearTimeout(state.toastTimer);
    state.toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 3200);
  }


  /* PASO 9 · Programa de fidelización, saldos y recompensas guardados en MySQL. */
  function initLoyaltyActions() {
    $('#refreshLoyaltyButton')?.addEventListener('click', () => refreshLoyalty($('#loyaltySearch')?.value || '').then(() => showToast('Fidelización actualizada', 'Saldos y movimientos recargados desde MySQL.')).catch(error => showToast('No se pudo actualizar', error.message, '!')));
    $('#newLoyaltyRewardButton')?.addEventListener('click', () => openLoyaltyRewardForm());
    $('#loyaltySettingsForm')?.addEventListener('submit', async event => {
      event.preventDefault();
      if (!can('loyalty.manage')) return showToast('Acceso restringido', 'Tu rol no puede modificar las reglas.', '!');
      const button = event.currentTarget.querySelector('button[type="submit"]'); button.disabled = true;
      try {
        const result = await apiRequest('api/loyalty.php', { method: 'POST', body: JSON.stringify({ action: 'settings.update', soles_per_point: Number($('#loyaltySolesPerPoint').value), minimum_eligible_amount: Number($('#loyaltyMinAmount').value), points_expiry_days: Number($('#loyaltyExpiryDays').value) }) });
        showToast('Reglas guardadas', result.message); await refreshLoyalty($('#loyaltySearch')?.value || '');
      } catch (error) { showToast('No se pudieron guardar las reglas', error.message, '!'); }
      finally { button.disabled = false; }
    });
    $('#loyaltySearch')?.addEventListener('input', () => {
      clearTimeout(state.loyaltySearchTimer);
      state.loyaltySearchTimer = setTimeout(() => refreshLoyalty($('#loyaltySearch').value).catch(error => showToast('No se pudo buscar', error.message, '!')), 220);
    });
    $('#loyaltyRewardsGrid')?.addEventListener('click', async event => {
      const toggle = event.target.closest('[data-loyalty-toggle]');
      const edit = event.target.closest('[data-loyalty-edit]');
      if (edit) { const reward = state.loyalty?.rewards?.find(row => Number(row.id) === Number(edit.dataset.loyaltyEdit)); if (reward) openLoyaltyRewardForm(reward); return; }
      if (toggle) {
        try { const result = await apiRequest('api/loyalty.php', { method: 'POST', body: JSON.stringify({ action: 'reward.toggle', id: Number(toggle.dataset.loyaltyToggle), status: toggle.dataset.nextStatus }) }); await refreshLoyalty($('#loyaltySearch')?.value || ''); showToast('Recompensa actualizada', result.message); }
        catch (error) { showToast('No se pudo cambiar la recompensa', error.message, '!'); }
      }
    });
    $('#loyaltyAccountsList')?.addEventListener('click', event => {
      const adjust = event.target.closest('[data-loyalty-adjust]');
      const redeem = event.target.closest('[data-loyalty-redeem]');
      const history = event.target.closest('[data-loyalty-history]');
      if (adjust) { const customer = state.loyalty?.accounts?.find(row => Number(row.customer_id) === Number(adjust.dataset.loyaltyAdjust)); if (customer) openLoyaltyAdjustForm(customer); }
      if (redeem) { const customer = state.loyalty?.accounts?.find(row => Number(row.customer_id) === Number(redeem.dataset.loyaltyRedeem)); if (customer) openLoyaltyRedeemForm(customer); }
      if (history) { const customerId = Number(history.dataset.loyaltyHistory); openCustomerLoyaltyHistory(customerId).catch(error => showToast('No se pudo cargar el historial', error.message, '!')); }
    });
    $('#loyaltyRedemptionsList')?.addEventListener('click', async event => {
      const button = event.target.closest('[data-redemption-status]'); if (!button) return;
      const next = button.dataset.nextStatus;
      const question = next === 'cancelled' ? '¿Cancelar el vale y devolver los puntos al cliente?' : '¿Marcar este vale como utilizado?';
      if (!window.confirm(question)) return;
      try { const result = await apiRequest('api/loyalty.php', { method: 'POST', body: JSON.stringify({ action: 'redemption.status', id: Number(button.dataset.redemptionStatus), status: next }) }); await refreshLoyalty($('#loyaltySearch')?.value || ''); showToast('Canje actualizado', result.message); }
      catch (error) { showToast('No se pudo actualizar el canje', error.message, '!'); }
    });
  }

  async function refreshLoyalty(query = '') {
    if (!state.currentUser || !(can('loyalty.view') || can('loyalty.manage'))) return;
    if ($('#loyaltyAccountsList') && state.currentView === 'loyalty') $('#loyaltyAccountsList').innerHTML = '<div class="manager-loading">Cargando saldos de puntos desde MySQL…</div>';
    const result = await apiRequest(`api/loyalty.php?view=dashboard&q=${encodeURIComponent(query)}`, { method: 'GET' });
    state.loyalty = result;
    renderLoyalty();
  }

  function renderLoyalty() {
    const data = state.loyalty; if (!data) return;
    const summary = data.summary || {};
    $('#loyaltySummary').innerHTML = `
      <article class="operations-metric"><span class="operations-metric-icon metric-blue">✦</span><div><small>Clientes inscritos</small><strong>${Number(summary.customers_total || 0).toLocaleString('es-PE')}</strong></div></article>
      <article class="operations-metric"><span class="operations-metric-icon metric-green">★</span><div><small>Saldo de puntos</small><strong>${Number(summary.points_balance || 0).toLocaleString('es-PE')}</strong></div></article>
      <article class="operations-metric"><span class="operations-metric-icon metric-purple">↗</span><div><small>Puntos acumulados</small><strong>${Number(summary.points_earned || 0).toLocaleString('es-PE')}</strong></div></article>
      <article class="operations-metric"><span class="operations-metric-icon metric-amber">♢</span><div><small>Canjes registrados</small><strong>${(data.redemptions || []).length}</strong></div></article>`;
    $('#loyaltySolesPerPoint').value = Number(data.settings?.soles_per_point || 10).toFixed(2).replace(/\.00$/, '');
    $('#loyaltyMinAmount').value = Number(data.settings?.minimum_eligible_amount || 0).toFixed(2);
    $('#loyaltyExpiryDays').value = Number(data.settings?.points_expiry_days || 0);
    $('#loyaltySettingsForm').querySelectorAll('input,button').forEach(node => node.disabled = !can('loyalty.manage'));
    $('#loyaltyExpiryDays').disabled = true;
    $('#loyaltyExpiryDays').value = 0;
    $('#newLoyaltyRewardButton').classList.toggle('is-hidden', !can('loyalty.manage'));

    const rewards = data.rewards || [];
    $('#loyaltyRewardsGrid').innerHTML = rewards.length ? rewards.map(reward => {
      const available = Number(reward.is_available) === 1;
      const rewardType = reward.reward_type === 'free_product' ? `Producto: ${escapeHTML(reward.product_name || 'No disponible')}` : `Vale de ${money(reward.discount_amount)}`;
      const stock = reward.stock_limit === null ? `${Number(reward.redeemed_count)} canjes registrados` : `${Math.max(0, Number(reward.stock_limit) - Number(reward.redeemed_count))} de ${Number(reward.stock_limit)} disponibles`;
      const actions = can('loyalty.manage') ? `<div class="loyalty-card-actions"><button type="button" class="secondary-button" data-loyalty-edit="${Number(reward.id)}">Editar</button><button type="button" class="secondary-button ${reward.status === 'active' ? 'manager-danger' : ''}" data-loyalty-toggle="${Number(reward.id)}" data-next-status="${reward.status === 'active' ? 'inactive' : 'active'}">${reward.status === 'active' ? 'Desactivar' : 'Activar'}</button></div>` : '';
      return `<article class="loyalty-reward-card ${reward.status === 'inactive' ? 'is-inactive' : ''}"><div class="loyalty-reward-top"><span class="loyalty-reward-symbol">✦</span><span class="loyalty-state ${available ? 'is-good' : 'is-muted'}">${available ? 'Disponible' : (reward.status === 'active' ? 'Fuera de vigencia/stock' : 'Inactiva')}</span></div><span class="section-eyebrow">${escapeHTML(reward.reward_code)}</span><h3>${escapeHTML(reward.name)}</h3><p>${escapeHTML(reward.description || 'Recompensa del programa DESEGO.')}</p><div class="loyalty-points-cost"><strong>${Number(reward.points_cost).toLocaleString('es-PE')}</strong><span>puntos</span></div><div class="loyalty-reward-meta"><span>${rewardType}</span><span>${escapeHTML(stock)}</span></div>${actions}</article>`;
    }).join('') : '<div class="empty-state"><span>✦</span><strong>Aún no hay recompensas</strong><p>Crea la primera recompensa para el programa.</p></div>';

    const accounts = data.accounts || [];
    $('#loyaltyAccountsList').innerHTML = accounts.length ? `<div class="loyalty-account-table-wrap"><table class="loyalty-account-table"><thead><tr><th>Cliente</th><th>Saldo</th><th>Acumulados</th><th>Canjeados</th><th>Pedidos pagados</th><th>Acciones</th></tr></thead><tbody>${accounts.map(customer => `<tr><td><strong>${escapeHTML(customer.full_name)}</strong><small>${escapeHTML(customer.customer_code || '')} · ${escapeHTML(customer.phone || customer.email || 'Sin contacto')}</small></td><td><span class="loyalty-balance ${Number(customer.points_balance) < 0 ? 'is-negative' : ''}">${Number(customer.points_balance).toLocaleString('es-PE')} pts</span></td><td>${Number(customer.lifetime_earned).toLocaleString('es-PE')}</td><td>${Number(customer.lifetime_redeemed).toLocaleString('es-PE')}</td><td>${Number(customer.paid_orders).toLocaleString('es-PE')}</td><td><div class="loyalty-inline-actions">${can('loyalty.manage') ? `<button type="button" class="secondary-button" data-loyalty-adjust="${Number(customer.customer_id)}">Ajustar</button><button type="button" class="customer-primary-action" data-loyalty-redeem="${Number(customer.customer_id)}">Canjear</button>` : ''}<button type="button" class="secondary-button" data-loyalty-history="${Number(customer.customer_id)}">Historial</button></div></td></tr>`).join('')}</tbody></table></div>` : '<div class="empty-state"><span>♙</span><strong>No hay clientes que coincidan</strong><p>Crea una ficha de cliente o cambia la búsqueda.</p></div>';

    const redemptions = data.redemptions || [];
    $('#loyaltyRedemptionCount').textContent = `${redemptions.length} vales`;
    $('#loyaltyRedemptionsList').innerHTML = redemptions.length ? redemptions.map(redemption => {
      const statusText = ({issued:'Emitido',used:'Utilizado',cancelled:'Cancelado'})[redemption.status] || redemption.status;
      const actions = can('loyalty.manage') && redemption.status === 'issued' ? `<button type="button" class="secondary-button" data-redemption-status="${Number(redemption.id)}" data-next-status="used">Marcar usado</button><button type="button" class="secondary-button manager-danger" data-redemption-status="${Number(redemption.id)}" data-next-status="cancelled">Cancelar</button>` : '';
      return `<article class="loyalty-history-row"><div class="loyalty-history-icon">♢</div><div class="loyalty-history-main"><strong>${escapeHTML(redemption.reward_name)} · ${escapeHTML(redemption.full_name)}</strong><small>Código ${escapeHTML(redemption.redemption_code)} · ${Number(redemption.points_spent)} puntos · ${formatDateTime(redemption.issued_at)}</small></div><span class="loyalty-state ${redemption.status === 'used' ? 'is-good' : redemption.status === 'issued' ? 'is-active' : 'is-muted'}">${statusText}</span><div class="loyalty-inline-actions">${actions}</div></article>`;
    }).join('') : '<div class="empty-state"><span>♢</span><strong>Aún no hay canjes</strong><p>Los vales que emitas aparecerán aquí.</p></div>';

    const transactions = data.transactions || [];
    $('#loyaltyTransactionCount').textContent = `${transactions.length} movimientos`;
    const typeLabel = ({earn:'Puntos ganados',redeem:'Canje de recompensa',adjustment:'Ajuste manual',order_refund:'Reversión por reembolso',reward_cancel:'Devolución por vale cancelado'});
    $('#loyaltyTransactionsList').innerHTML = transactions.length ? transactions.map(tx => `<article class="loyalty-history-row"><div class="loyalty-history-icon">${Number(tx.points_delta) >= 0 ? '＋' : '−'}</div><div class="loyalty-history-main"><strong>${escapeHTML(tx.full_name)} · ${escapeHTML(typeLabel[tx.transaction_type] || tx.transaction_type)}</strong><small>${escapeHTML(tx.description || '')} · ${formatDateTime(tx.created_at)}${tx.user_name ? ' · ' + escapeHTML(tx.user_name) : ''}</small></div><strong class="loyalty-transaction-points ${Number(tx.points_delta) < 0 ? 'is-negative' : 'is-positive'}">${Number(tx.points_delta) > 0 ? '+' : ''}${Number(tx.points_delta)} pts</strong></article>`).join('') : '<div class="empty-state"><span>↗</span><strong>Sin movimientos todavía</strong><p>Los puntos se acumularán cuando los pedidos vinculados a clientes queden pagados.</p></div>';
  }

  function formatDateTime(value) {
    if (!value) return '—';
    const date = new Date(String(value).replace(' ', 'T') + (String(value).endsWith('Z') ? '' : 'Z'));
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' });
  }

  function openLoyaltyRewardForm(reward = null) {
    if (!can('loyalty.manage')) return showToast('Acceso restringido', 'Tu rol no puede gestionar recompensas.', '!');
    const products = state.products || [];
    const options = products.map(product => `<option value="${Number(product.id)}" ${Number(reward?.product_id) === Number(product.id) ? 'selected' : ''}>${escapeHTML(product.name)}</option>`).join('');
    const dt = new Date().toISOString().slice(0,16);
    const fields = `<div class="form-grid">
      <div class="form-field"><label for="loyaltyRewardCode">Código *</label><input id="loyaltyRewardCode" required maxlength="32" pattern="[A-Za-z0-9_-]{3,32}" value="${escapeHTML(reward?.reward_code || '')}" placeholder="DESEGO-S10"></div>
      <div class="form-field"><label for="loyaltyRewardPoints">Costo en puntos *</label><input id="loyaltyRewardPoints" type="number" min="1" max="10000000" step="1" required value="${Number(reward?.points_cost || 50)}"></div>
      <div class="form-field form-field-wide"><label for="loyaltyRewardName">Nombre *</label><input id="loyaltyRewardName" required minlength="3" maxlength="120" value="${escapeHTML(reward?.name || '')}" placeholder="Descuento de S/ 10"></div>
      <div class="form-field form-field-wide"><label for="loyaltyRewardDescription">Descripción</label><input id="loyaltyRewardDescription" maxlength="255" value="${escapeHTML(reward?.description || '')}" placeholder="Condiciones de uso"></div>
      <div class="form-field"><label for="loyaltyRewardType">Tipo</label><select id="loyaltyRewardType"><option value="discount" ${!reward || reward.reward_type === 'discount' ? 'selected' : ''}>Descuento</option><option value="free_product" ${reward?.reward_type === 'free_product' ? 'selected' : ''}>Producto gratis</option></select></div>
      <div class="form-field" id="loyaltyRewardDiscountWrap"><label for="loyaltyRewardDiscount">Importe del descuento (S/)</label><input id="loyaltyRewardDiscount" type="number" min="0" max="100000" step="0.50" value="${Number(reward?.discount_amount || 5).toFixed(2)}"></div>
      <div class="form-field form-field-wide is-hidden" id="loyaltyRewardProductWrap"><label for="loyaltyRewardProduct">Producto</label><select id="loyaltyRewardProduct"><option value="">Seleccionar producto…</option>${options}</select></div>
      <div class="form-field"><label for="loyaltyRewardStock">Límite de canjes</label><input id="loyaltyRewardStock" type="number" min="1" max="100000000" step="1" value="${reward?.stock_limit ?? ''}" placeholder="Sin límite"></div>
      <div class="form-field"><label for="loyaltyRewardStarts">Vigente desde (UTC) *</label><input id="loyaltyRewardStarts" type="datetime-local" required value="${escapeHTML((reward?.starts_at || dt).replace(' ', 'T').slice(0,16))}"></div>
      <div class="form-field"><label for="loyaltyRewardEnds">Vigente hasta (UTC)</label><input id="loyaltyRewardEnds" type="datetime-local" value="${escapeHTML((reward?.ends_at || '').replace(' ', 'T').slice(0,16))}"></div>
    </div>`;
    openModal(modalShell('FIDELIZACIÓN', reward ? 'Editar recompensa' : 'Nueva recompensa', 'Al canjear se descuentan puntos y se emite un vale único. Los descuentos/productos se validan en caja antes de aplicarse.', 'loyaltyRewardForm', fields, reward ? 'Guardar cambios' : 'Crear recompensa'));
    const typeInput = $('#loyaltyRewardType');
    const syncType = () => { const free = typeInput.value === 'free_product'; $('#loyaltyRewardDiscountWrap').classList.toggle('is-hidden', free); $('#loyaltyRewardProductWrap').classList.toggle('is-hidden', !free); $('#loyaltyRewardProduct').required = free; $('#loyaltyRewardDiscount').required = !free; };
    typeInput.addEventListener('change', syncType); syncType();
    $('#loyaltyRewardForm').addEventListener('submit', async event => {
      event.preventDefault(); const button = $('#loyaltyRewardForm button[type="submit"]'); button.disabled = true;
      const payload = { action:'reward.save', ...(reward ? { id:Number(reward.id) } : {}), reward_code:$('#loyaltyRewardCode').value.trim().toUpperCase(), points_cost:Number($('#loyaltyRewardPoints').value), name:$('#loyaltyRewardName').value.trim(), description:$('#loyaltyRewardDescription').value.trim(), reward_type:typeInput.value, discount_amount:Number($('#loyaltyRewardDiscount').value || 0), product_id:$('#loyaltyRewardProduct').value || null, stock_limit:$('#loyaltyRewardStock').value || null, starts_at:$('#loyaltyRewardStarts').value, ends_at:$('#loyaltyRewardEnds').value };
      try { const result = await apiRequest('api/loyalty.php', { method:'POST', body:JSON.stringify(payload) }); closeModal(); await refreshLoyalty($('#loyaltySearch')?.value || ''); showToast('Recompensa guardada', result.message); }
      catch (error) { showModalError(error.message); }
      finally { button.disabled = false; }
    });
  }

  function openLoyaltyAdjustForm(customer, showHistory = false) {
    if (!can('loyalty.manage')) return showToast('Acceso restringido', 'Tu rol no puede ajustar puntos.', '!');
    const fields = `<div class="loyalty-customer-preview"><strong>${escapeHTML(customer.full_name)}</strong><span>Saldo actual: ${Number(customer.points_balance).toLocaleString('es-PE')} puntos</span></div><div class="form-field"><label for="loyaltyAdjustDelta">Ajuste de puntos *</label><input id="loyaltyAdjustDelta" type="number" min="-1000000" max="1000000" step="1" required placeholder="Ej. 25 o -10"><small>Usa número positivo para abonar y negativo para descontar.</small></div><div class="form-field"><label for="loyaltyAdjustReason">Motivo *</label><textarea id="loyaltyAdjustReason" rows="2" maxlength="255" minlength="3" required placeholder="Compensación, corrección, campaña…"></textarea></div>`;
    openModal(modalShell('CARTERA DE CLIENTES', 'Ajustar puntos', 'El ajuste queda registrado en el historial de auditoría.', 'loyaltyAdjustForm', fields, 'Guardar ajuste'));
    $('#loyaltyAdjustForm').addEventListener('submit', async event => { event.preventDefault(); const button = $('#loyaltyAdjustForm button[type="submit"]'); button.disabled = true;
      try { const result = await apiRequest('api/loyalty.php', { method:'POST', body:JSON.stringify({ action:'points.adjust', customer_id:Number(customer.customer_id || customer.id), points_delta:Number($('#loyaltyAdjustDelta').value), description:$('#loyaltyAdjustReason').value.trim() }) }); closeModal(); await refreshLoyalty($('#loyaltySearch')?.value || ''); showToast('Puntos actualizados', result.message); }
      catch (error) { showModalError(error.message); }
      finally { button.disabled = false; }
    });
  }

  function openLoyaltyRedeemForm(customer) {
    if (!can('loyalty.manage')) return showToast('Acceso restringido', 'Tu rol no puede emitir recompensas.', '!');
    const rewards = (state.loyalty?.rewards || []).filter(reward => Number(reward.is_available) === 1 && Number(reward.points_cost) <= Number(customer.points_balance));
    if (!rewards.length) return showToast('Sin recompensas disponibles', 'Este cliente no tiene puntos suficientes o no hay recompensas vigentes.', '!');
    const options = rewards.map(reward => `<option value="${Number(reward.id)}">${escapeHTML(reward.name)} · ${Number(reward.points_cost)} pts</option>`).join('');
    const fields = `<div class="loyalty-customer-preview"><strong>${escapeHTML(customer.full_name)}</strong><span>Saldo: ${Number(customer.points_balance).toLocaleString('es-PE')} puntos</span></div><div class="form-field"><label for="loyaltyRedeemReward">Recompensa *</label><select id="loyaltyRedeemReward" required>${options}</select></div><p class="operations-footnote">Al confirmar, los puntos se descontarán y se generará un vale único. Caja deberá marcarlo como utilizado al entregar el beneficio.</p>`;
    openModal(modalShell('CANJEAR PUNTOS', 'Emitir recompensa', 'Este canje no cobra ni aplica automáticamente un descuento al pedido; genera un vale rastreable.', 'loyaltyRedeemForm', fields, 'Emitir vale'));
    $('#loyaltyRedeemForm').addEventListener('submit', async event => { event.preventDefault(); const button = $('#loyaltyRedeemForm button[type="submit"]'); button.disabled = true;
      try { const result = await apiRequest('api/loyalty.php', { method:'POST', body:JSON.stringify({ action:'reward.redeem', customer_id:Number(customer.customer_id), reward_id:Number($('#loyaltyRedeemReward').value) }) }); closeModal(); await refreshLoyalty($('#loyaltySearch')?.value || ''); openModal(`<span class="modal-eyebrow">VALE DE RECOMPENSA</span><h2 class="modal-title">${escapeHTML(result.redemption_code)}</h2><p class="modal-description">${escapeHTML(result.message)}<br>Se descontaron ${Number(result.points_spent)} puntos. Saldo restante: ${Number(result.balance)}.</p><div class="loyalty-voucher-code">${escapeHTML(result.redemption_code)}</div><div class="modal-actions"><button type="button" class="modal-primary" data-close-modal>Entendido</button></div>`); }
      catch (error) { showModalError(error.message); }
      finally { button.disabled = false; }
    });
  }

  async function openCustomerLoyaltyHistory(customerId) {
    const result = await apiRequest(`api/loyalty.php?view=customer&customer_id=${Number(customerId)}`, { method:'GET' });
    const tx = result.transactions || [];
    const rows = tx.map(item => `<article class="loyalty-history-row"><div class="loyalty-history-icon">${Number(item.points_delta) >= 0 ? '＋' : '−'}</div><div class="loyalty-history-main"><strong>${escapeHTML(item.description || item.transaction_type)}</strong><small>${formatDateTime(item.created_at)} · saldo ${Number(item.balance_after)} pts</small></div><strong class="loyalty-transaction-points ${Number(item.points_delta) < 0 ? 'is-negative' : 'is-positive'}">${Number(item.points_delta) > 0 ? '+' : ''}${Number(item.points_delta)} pts</strong></article>`).join('');
    openWideModal(`<div><span class="modal-eyebrow">HISTORIAL DE FIDELIZACIÓN</span><h2 class="modal-title" id="modalTitle">${escapeHTML(result.customer.full_name)}</h2><p class="modal-description">Saldo actual: <strong>${Number(result.account.points_balance)} puntos</strong> · acumulados ${Number(result.account.lifetime_earned)} · canjeados ${Number(result.account.lifetime_redeemed)}.</p><div class="loyalty-transactions-list">${rows || '<p class="catalog-note">El cliente todavía no tiene movimientos de puntos.</p>'}</div></div>`);
  }

  function initReviewActions() {
    $('#refreshReviewsButton')?.addEventListener('click', () => refreshAdminReviews().catch(error => showToast('No se pudieron cargar las reseñas', error.message, '!')));
    $('#reviewAdminFilters')?.addEventListener('click', event => {
      const button = event.target.closest('[data-review-status]');
      if (!button) return;
      state.reviewsStatus = button.dataset.reviewStatus;
      $$('#reviewAdminFilters [data-review-status]').forEach(item => item.classList.toggle('is-selected', item === button));
      refreshAdminReviews().catch(error => showToast('No se pudieron cargar las reseñas', error.message, '!'));
    });
    $('#adminReviewsList')?.addEventListener('click', async event => {
      const button = event.target.closest('[data-review-action]');
      if (!button) return;
      if (!can('reviews.manage')) return showToast('Acceso restringido', 'Tu rol no puede moderar reseñas.', '!');
      const id = Number(button.dataset.reviewId);
      const status = button.dataset.reviewAction;
      const label = status === 'approved' ? 'aprobar' : 'rechazar';
      if (!id || !['approved','rejected'].includes(status) || !window.confirm(`¿Deseas ${label} esta reseña?`)) return;
      button.disabled = true;
      try {
        const result = await apiRequest('api/reviews.php', { method:'POST', body:JSON.stringify({ action:'review.moderate', id, status }) });
        showToast('Moderación actualizada', result.message || 'Se guardó la decisión.');
        await refreshAdminReviews();
      } catch (error) { showToast('No se pudo moderar', error.message, '!'); }
      finally { button.disabled = false; }
    });
  }

  async function refreshAdminReviews() {
    if (!state.currentUser || !(can('reviews.view') || can('reviews.manage'))) return;
    const list = $('#adminReviewsList');
    if (list) list.innerHTML = '<div class="manager-loading">Cargando reseñas desde MySQL…</div>';
    const status = state.reviewsStatus || 'pending';
    const result = await apiRequest(`api/reviews.php?view=moderation&status=${encodeURIComponent(status)}`, { method:'GET' });
    const reviews = result.reviews || [];
    const summary = result.summary || {};
    if ($('#reviewsSummary')) {
      $('#reviewsSummary').innerHTML = `
        <article class="operations-metric"><span class="operations-metric-icon metric-amber">★</span><div><small>Pendientes</small><strong>${Number(summary.pending || 0)}</strong></div></article>
        <article class="operations-metric"><span class="operations-metric-icon metric-green">✓</span><div><small>Aprobadas</small><strong>${Number(summary.approved || 0)}</strong></div></article>
        <article class="operations-metric"><span class="operations-metric-icon metric-red">×</span><div><small>Rechazadas</small><strong>${Number(summary.rejected || 0)}</strong></div></article>`;
    }
    $('#adminReviewsCount').textContent = `${reviews.length} ${reviews.length === 1 ? 'reseña' : 'reseñas'}`;
    if (!reviews.length) {
      list.innerHTML = '<div class="reviews-admin-empty"><span>★</span><strong>No hay reseñas en esta vista</strong><p>Cuando los clientes envíen opiniones verificadas, aparecerán aquí para moderación.</p></div>';
      return;
    }
    list.innerHTML = reviews.map(review => {
      const stars = '★'.repeat(Number(review.rating)) + '☆'.repeat(5 - Number(review.rating));
      const buttons = review.status === 'pending' && can('reviews.manage') ? `<div class="review-admin-actions"><button type="button" class="cyan-button" data-review-action="approved" data-review-id="${Number(review.id)}">✓ Aprobar</button><button type="button" class="secondary-button" data-review-action="rejected" data-review-id="${Number(review.id)}">Rechazar</button></div>` : '';
      return `<article class="admin-review-item"><div class="admin-review-top"><div><span class="review-stars">${stars}</span><span class="review-status-pill status-${escapeHTML(review.status)}">${review.status === 'pending' ? 'Pendiente' : review.status === 'approved' ? 'Aprobada' : 'Rechazada'}</span></div><small>${escapeHTML(formatDateTime(review.created_at))}</small></div><h3>${escapeHTML(review.title || review.product_name)}</h3><p class="admin-review-comment">${escapeHTML(review.comment)}</p><div class="admin-review-meta"><span><strong>${escapeHTML(review.product_name)}</strong></span><span>Pedido ${escapeHTML(review.order_number || '')}</span><span>${escapeHTML(review.reviewer_name)} · ${escapeHTML(review.customer_phone || 'Sin teléfono')}</span></div>${review.moderator_note ? `<p class="review-moderator-note">Nota: ${escapeHTML(review.moderator_note)}</p>` : ''}${buttons}</article>`;
    }).join('');
  }

  /* Facturación interna y reportes gerenciales del Paso 11. */
  function initBillingReports() {
    $('#refreshBillingButton')?.addEventListener('click', () => refreshBilling().catch(error => showToast('No se pudieron cargar los comprobantes', error.message, '!')));
    $('#invoiceIssueForm')?.addEventListener('submit', handleIssueInvoice);
    $('#invoiceOrderSelect')?.addEventListener('change', () => {
      const option = $('#invoiceOrderSelect').selectedOptions[0];
      const name = option?.dataset.customer || '';
      if (name && !$('#invoiceBuyerName').value.trim()) $('#invoiceBuyerName').value = name;
    });
    $('#invoiceDocumentType')?.addEventListener('change', () => {
      const isFactura = $('#invoiceDocumentType').value === 'factura';
      if (isFactura) {
        $('#invoiceBuyerDocType').value = 'RUC';
        $('#invoiceBuyerDoc').setAttribute('inputmode', 'numeric');
        $('#invoiceBuyerDoc').placeholder = 'RUC de 11 dígitos';
      } else {
        $('#invoiceBuyerDoc').placeholder = 'DNI o RUC';
      }
    });
    $('#invoicesList')?.addEventListener('click', async event => {
      const printButton = event.target.closest('[data-print-invoice]');
      if (printButton) { printInternalInvoice(Number(printButton.dataset.printInvoice)); return; }
      const voidButton = event.target.closest('[data-void-invoice]');
      if (voidButton) {
        if (!can('invoices.manage')) return showToast('Acceso restringido', 'Tu rol no puede anular comprobantes internos.', '!');
        const reason = window.prompt('Motivo de anulación del registro interno (mínimo 5 caracteres):');
        if (reason === null) return;
        try {
          const result = await apiRequest('api/billing-reports.php', { method: 'POST', body: JSON.stringify({ action: 'void', invoice_id: Number(voidButton.dataset.voidInvoice), reason: reason.trim() }) });
          showToast('Comprobante anulado', result.message || 'Se registró la anulación interna.');
          await refreshBilling();
        } catch (error) { showToast('No se pudo anular', error.message, '!'); }
      }
    });
    $('#reportFilterForm')?.addEventListener('submit', event => { event.preventDefault(); loadReports().catch(error => showToast('No se pudieron cargar los reportes', error.message, '!')); });
    $('#exportReportsButton')?.addEventListener('click', exportReportsCsv);
    $('#issueInvoiceCard')?.classList.toggle('is-hidden', !can('invoices.manage'));
  }

  async function refreshBilling() {
    if (!(can('invoices.view') || can('invoices.manage'))) return;
    const data = await apiRequest('api/billing-reports.php?view=invoices', { method: 'GET' });
    state.invoices = data.invoices || [];
    state.eligibleInvoiceOrders = data.eligibleOrders || [];
    $('#issueInvoiceCard')?.classList.toggle('is-hidden', !can('invoices.manage'));
    renderInvoiceOrders();
    renderInvoices();
  }

  function renderInvoiceOrders() {
    const select = $('#invoiceOrderSelect');
    if (!select) return;
    const previous = select.value;
    if (!state.eligibleInvoiceOrders.length) {
      select.innerHTML = '<option value="">No hay pedidos pagados pendientes de comprobante</option>';
      select.disabled = true;
      $('#issueInvoiceSubmit')?.setAttribute('disabled', 'disabled');
      return;
    }
    select.disabled = false;
    $('#issueInvoiceSubmit')?.removeAttribute('disabled');
    select.innerHTML = '<option value="">Selecciona un pedido pagado…</option>' + state.eligibleInvoiceOrders.map(order => `<option value="${Number(order.id)}" data-customer="${escapeHTML(order.customer_name || '')}">${escapeHTML(order.order_number || ('Pedido #' + order.id))} · ${escapeHTML(order.customer_name || 'Consumidor final')} · ${money(order.total)} · ${escapeHTML(order.paid_at || '')}</option>`).join('');
    if (previous && [...select.options].some(option => option.value === previous)) select.value = previous;
  }

  function invoiceStatusLabel(status) { return status === 'issued' ? 'Emitido' : 'Anulado'; }
  function renderInvoices() {
    const list = $('#invoicesList');
    if (!list) return;
    $('#invoiceCount').textContent = `${state.invoices.length} ${state.invoices.length === 1 ? 'comprobante' : 'comprobantes'}`;
    if (!state.invoices.length) {
      list.innerHTML = '<div class="billing-empty"><span>▤</span><strong>Aún no hay comprobantes internos</strong><p>Al emitir un documento para un pedido pagado, aparecerá aquí.</p></div>';
      return;
    }
    list.innerHTML = state.invoices.map(invoice => {
      const canVoid = can('invoices.manage') && invoice.status === 'issued';
      const typeLabel = invoice.document_type === 'factura' ? 'Factura interna' : 'Boleta interna';
      return `<article class="billing-invoice-row"><div class="billing-invoice-mark">${invoice.document_type === 'factura' ? 'F' : 'B'}</div><div class="billing-invoice-main"><div class="billing-invoice-title"><strong>${escapeHTML(invoice.document_number)}</strong><span class="billing-status ${invoice.status === 'issued' ? 'status-issued' : 'status-voided'}">${invoiceStatusLabel(invoice.status)}</span></div><p>${escapeHTML(typeLabel)} · ${escapeHTML(invoice.order_number || ('Pedido #' + invoice.order_id))} · ${escapeHTML(invoice.buyer_name)}</p><small>${escapeHTML(invoice.buyer_document_type || 'Sin documento')} ${escapeHTML(invoice.buyer_document || '')} · ${escapeHTML(invoice.issued_at || '')} · Emitido por ${escapeHTML(invoice.issuer_name || '')}</small><div class="billing-invoice-amount">${money(invoice.total)} <span>IGV ref.: ${money(invoice.igv_amount)}</span></div>${invoice.status === 'voided' ? `<small class="billing-void-note">Motivo: ${escapeHTML(invoice.void_reason || 'No indicado')}</small>` : ''}</div><div class="billing-invoice-actions"><button type="button" class="secondary-button" data-print-invoice="${Number(invoice.id)}">Imprimir</button>${canVoid ? `<button type="button" class="secondary-button manager-danger" data-void-invoice="${Number(invoice.id)}">Anular registro</button>` : ''}</div></article>`;
    }).join('');
  }

  async function handleIssueInvoice(event) {
    event.preventDefault();
    if (!can('invoices.manage')) return showToast('Acceso restringido', 'Tu rol no puede emitir comprobantes.', '!');
    const feedback = $('#invoiceFormFeedback');
    const button = $('#issueInvoiceSubmit');
    feedback.textContent = '';
    button.disabled = true;
    const payload = {
      action: 'issue', order_id: Number($('#invoiceOrderSelect').value),
      document_type: $('#invoiceDocumentType').value,
      buyer_name: $('#invoiceBuyerName').value.trim(),
      buyer_document_type: $('#invoiceBuyerDocType').value,
      buyer_document: $('#invoiceBuyerDoc').value.trim(),
      buyer_address: $('#invoiceBuyerAddress').value.trim(),
      notes: $('#invoiceNotes').value.trim()
    };
    try {
      const result = await apiRequest('api/billing-reports.php', { method: 'POST', body: JSON.stringify(payload) });
      showToast('Comprobante creado', `${result.document_number}: ${result.message || 'Registro interno guardado.'}`);
      $('#invoiceIssueForm').reset();
      $('#invoiceDocumentType').dispatchEvent(new Event('change'));
      await refreshBilling();
    } catch (error) {
      feedback.textContent = error.message;
      showToast('No se pudo emitir', error.message, '!');
    } finally { button.disabled = false; }
  }

  function printInternalInvoice(invoiceId) {
    const invoice = state.invoices.find(item => Number(item.id) === Number(invoiceId));
    if (!invoice) return showToast('Comprobante no encontrado', 'Actualiza la lista e inténtalo de nuevo.', '!');
    const printWindow = window.open('', '_blank', 'width=800,height=900');
    if (!printWindow) return showToast('Ventana bloqueada', 'Permite las ventanas emergentes para imprimir el comprobante.', '!');
    const rows = (invoice.items || []).map(item => `<tr><td>${escapeHTML(item.product_name)}</td><td>${Number(item.quantity)}</td><td>${money(item.unit_price)}</td><td>${money(item.line_total)}</td></tr>`).join('');
    const logoUrl = new URL('assets/desego-logo.png', document.baseURI).href;
    const typeLabel = invoice.document_type === 'factura' ? 'FACTURA INTERNA' : 'BOLETA INTERNA';
    printWindow.document.open();
    printWindow.document.write(`<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${escapeHTML(invoice.document_number)} - DESEGO</title><style>body{font:13px Arial,sans-serif;color:#16315f;max-width:760px;margin:28px auto;padding:0 22px}header{display:flex;gap:14px;align-items:center;border-bottom:2px solid #0845d8;padding-bottom:16px}img{width:76px;height:76px;object-fit:contain}h1{font-size:20px;margin:0 0 5px}p{line-height:1.5;margin:4px 0}h2{font-size:15px;margin-top:24px}table{border-collapse:collapse;width:100%;margin-top:12px}th,td{text-align:left;border-bottom:1px solid #e4ecf8;padding:9px 6px}th{background:#f2f7ff}.totals{max-width:300px;margin:15px 0 0 auto}.totals div{display:flex;justify-content:space-between;padding:5px 0}.grand{font-size:17px;font-weight:bold;border-top:2px solid #0845d8;margin-top:6px}.notice{margin-top:24px;padding:12px;border:1px solid #e4ecf8;border-radius:9px;background:#f7faff;font-size:11px}@media print{body{margin:0 auto;padding:0 4mm}button{display:none}}</style></head><body><header><img src="${logoUrl}" alt="Logo DESEGO"><div><h1>DESEGO CEVICHERÍA</h1><strong>${typeLabel}</strong><p>${escapeHTML(invoice.document_number)}</p><p>Fecha: ${escapeHTML(invoice.issued_at || '')}</p></div></header><h2>Datos del comprador</h2><p><strong>${escapeHTML(invoice.buyer_name)}</strong></p><p>${escapeHTML(invoice.buyer_document_type || '')}: ${escapeHTML(invoice.buyer_document || '—')}</p><p>${escapeHTML(invoice.buyer_address || 'Sin dirección registrada')}</p><p>Pedido: ${escapeHTML(invoice.order_number || ('#' + invoice.order_id))}</p><table><thead><tr><th>Descripción</th><th>Cant.</th><th>Precio</th><th>Importe</th></tr></thead><tbody>${rows || '<tr><td colspan="4">Sin detalle de productos</td></tr>'}</tbody></table><div class="totals"><div><span>Subtotal pedido</span><strong>${money(invoice.order_subtotal)}</strong></div><div><span>Descuento</span><strong>−${money(invoice.order_discount)}</strong></div><div><span>Base imponible referencial</span><strong>${money(invoice.taxable_amount)}</strong></div><div><span>IGV referencial (${Number(invoice.igv_rate).toFixed(2)}%)</span><strong>${money(invoice.igv_amount)}</strong></div><div class="grand"><span>Total</span><strong>${money(invoice.total)}</strong></div></div>${invoice.notes ? `<p><strong>Notas:</strong> ${escapeHTML(invoice.notes)}</p>` : ''}<div class="notice"><strong>DOCUMENTO DE CONTROL INTERNO — NO VÁLIDO COMO COMPROBANTE ELECTRÓNICO SUNAT.</strong><br>Este documento es una representación interna de DESEGO CEVICHERÍA. El cálculo del IGV es referencial y no implica emisión ni validación tributaria oficial.</div><script>window.onload=()=>{window.focus();window.print();};<\/script></body></html>`);
    printWindow.document.close();
  }

  function localDateInput(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }
  function initReportDates() {
    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), 1);
    if ($('#reportFrom') && !$('#reportFrom').value) $('#reportFrom').value = localDateInput(start);
    if ($('#reportTo') && !$('#reportTo').value) $('#reportTo').value = localDateInput(now);
  }
  async function loadReports() {
    if (!can('reports.view')) return showToast('Acceso restringido', 'Tu rol no puede consultar reportes.', '!');
    initReportDates();
    const from = $('#reportFrom').value;
    const to = $('#reportTo').value;
    if (!from || !to || from > to) throw new Error('Selecciona un período válido; la fecha inicial no puede ser posterior a la final.');
    const params = new URLSearchParams({ view: 'reports', from, to });
    const data = await apiRequest(`api/billing-reports.php?${params.toString()}`, { method: 'GET' });
    state.reportData = data;
    renderReports(data);
  }
  function renderReports(data) {
    const summary = data.summary || {};
    $('#reportPeriodLabel').textContent = `Período: ${data.period.from} al ${data.period.to}. Los cobros netos se calculan a partir de pagos menos reembolsos.`;
    $('#reportSummary').innerHTML = `
      <article class="white-card report-kpi"><small>Cobros netos</small><strong>${money(summary.net_total || 0)}</strong><span>${money(summary.gross_payments || 0)} cobros − ${money(summary.gross_refunds || 0)} reembolsos</span></article>
      <article class="white-card report-kpi"><small>Pedidos pagados</small><strong>${Number(summary.paid_orders || 0)}</strong><span>Total en pedidos: ${money(summary.paid_order_total || 0)}</span></article>
      <article class="white-card report-kpi"><small>Ticket promedio</small><strong>${money(summary.average_ticket || 0)}</strong><span>Promedio de pedidos pagados</span></article>
      <article class="white-card report-kpi"><small>Comprobantes emitidos</small><strong>${Number(summary.invoice_count || 0)}</strong><span>Valor registrado: ${money(summary.invoice_total || 0)}</span></article>`;

    const daily = data.daily || [];
    const maximum = Math.max(1, ...daily.map(row => Math.abs(Number(row.net_total || 0))));
    $('#reportDailyChart').innerHTML = daily.length ? daily.map(row => {
      const width = Math.max(Number(row.net_total) !== 0 ? 2 : 0, Math.min(100, Math.abs(Number(row.net_total || 0)) / maximum * 100));
      return `<div class="daily-chart-row"><span>${escapeHTML(row.day)}</span><div class="daily-chart-track"><i class="${Number(row.net_total) < 0 ? 'is-negative' : ''}" style="width:${width}%"></i></div><strong>${money(row.net_total)}</strong></div>`;
    }).join('') : '<div class="billing-empty compact-empty"><strong>Sin movimientos de pago</strong><p>No hay cobros ni reembolsos en ese período.</p></div>';

    const methods = data.methods || [];
    $('#reportPaymentMethods').innerHTML = methods.length ? methods.map(row => `<div class="report-data-row"><div><strong>${escapeHTML(row.method)}</strong><small>${Number(row.operations)} operaciones · ${money(row.payments)} cobros · ${money(row.refunds)} reembolsos</small></div><b>${money(row.net_total)}</b></div>`).join('') : '<p class="catalog-note">No hay movimientos por método de pago.</p>';
    const products = data.topProducts || [];
    $('#reportTopProducts').innerHTML = products.length ? products.map((row, index) => `<div class="report-data-row"><div class="report-ranked"><span>${index + 1}</span><strong>${escapeHTML(row.product_name)}</strong><small>${Number(row.units_sold)} unidades</small></div><b>${money(row.sales_total)}</b></div>`).join('') : '<p class="catalog-note">No hay productos vendidos en el período.</p>';
    const statuses = data.orderStatuses || [];
    $('#reportOrderStatuses').innerHTML = statuses.length ? statuses.map(row => `<div class="report-data-row"><div><strong>${escapeHTML(row.status)}</strong><small>${Number(row.order_count)} pedidos</small></div><b>${money(row.amount)}</b></div>`).join('') : '<p class="catalog-note">No hay pedidos en ese período.</p>';
  }
  function csvCell(value) {
    if (typeof value === 'number' && Number.isFinite(value)) return String(value);
    let text = String(value ?? '');
    if (/^[=+@\-]/.test(text)) text = "'" + text;
    return '"' + text.replace(/"/g, '""') + '"';
  }
  function exportReportsCsv() {
    if (!state.reportData) return showToast('No hay reporte', 'Aplica un período antes de exportar.', '!');
    const report = state.reportData;
    const rows = [
      ['DESEGO CEVICHERÍA — REPORTE INTERNO'], ['Desde', report.period.from, 'Hasta', report.period.to], [],
      ['Resumen'], ['Cobros netos', report.summary.net_total], ['Cobros brutos', report.summary.gross_payments], ['Reembolsos', report.summary.gross_refunds], ['Pedidos pagados', report.summary.paid_orders], ['Total pedidos pagados', report.summary.paid_order_total], ['Ticket promedio', report.summary.average_ticket], ['Comprobantes emitidos', report.summary.invoice_count], ['Total comprobantes', report.summary.invoice_total], [],
      ['Cobros por día'], ['Fecha', 'Cobros brutos', 'Reembolsos', 'Neto', 'Operaciones'], ...(report.daily || []).map(row => [row.day, row.gross_payments, row.gross_refunds, row.net_total, row.operation_count]), [],
      ['Cobros por método'], ['Método', 'Cobros', 'Reembolsos', 'Neto', 'Operaciones'], ...(report.methods || []).map(row => [row.method, row.payments, row.refunds, row.net_total, row.operations]), [],
      ['Productos más vendidos'], ['Producto', 'Unidades', 'Importe'], ...(report.topProducts || []).map(row => [row.product_name, row.units_sold, row.sales_total]), [],
      ['Pedidos por estado'], ['Estado', 'Pedidos', 'Importe'], ...(report.orderStatuses || []).map(row => [row.status, row.order_count, row.amount]), [],
      ['Aviso', 'Reporte interno de gestión. No sustituye los libros contables ni reportes tributarios oficiales.']
    ];
    const csv = '\uFEFF' + rows.map(row => row.map(csvCell).join(';')).join('\r\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `desego-reporte-${report.period.from}-${report.period.to}.csv`;
    document.body.appendChild(link);
    link.click(); link.remove(); URL.revokeObjectURL(url);
    showToast('Reporte exportado', 'Se descargó el archivo CSV del período seleccionado.');
  }

  /* =========================================================
     PASO 12 — ANALÍTICA AVANZADA, AUDITORÍA Y BACKUPS
  ========================================================= */
  function initAdminTools() {
    $('#analyticsFilterForm')?.addEventListener('submit', event => { event.preventDefault(); loadAnalytics().catch(error => showToast('No se pudo cargar la analítica', error.message, '!')); });
    $('#refreshAnalyticsButton')?.addEventListener('click', () => loadAnalytics().catch(error => showToast('No se pudo actualizar', error.message, '!')));
    $('#saveAnalyticsPreferences')?.addEventListener('click', async () => {
      const metrics = $$('#analyticsMetricPrefs input[type="checkbox"]:checked').map(input => input.value);
      try { await apiRequest('api/admin-tools.php', { method: 'POST', body: JSON.stringify({ action: 'save_analytics_preferences', metrics }) }); showToast('Preferencias guardadas', 'Se actualizaron tus indicadores visibles.'); if (state.analyticsData) { state.analyticsData.preferences = Object.fromEntries($$('#analyticsMetricPrefs input[type="checkbox"]').map(input => [input.value, input.checked])); renderAnalytics(state.analyticsData); } }
      catch (error) { showToast('No se pudieron guardar las preferencias', error.message, '!'); }
    });
    $('#auditFilterForm')?.addEventListener('submit', event => {
      event.preventDefault();
      state.auditFilters = { from: $('#auditFrom').value, to: $('#auditTo').value, event: $('#auditEvent').value.trim(), actor: $('#auditActor').value.trim(), q: $('#auditQuery').value.trim() };
      loadAudit(1).catch(error => showToast('No se pudo filtrar la auditoría', error.message, '!'));
    });
    $('#refreshAuditButton')?.addEventListener('click', () => loadAudit(state.auditPage || 1).catch(error => showToast('No se pudo actualizar la auditoría', error.message, '!')));
    $('#auditPrevPage')?.addEventListener('click', () => { if (state.auditPage > 1) loadAudit(state.auditPage - 1).catch(error => showToast('Error de auditoría', error.message, '!')); });
    $('#auditNextPage')?.addEventListener('click', () => { if (state.auditPage * 100 < state.auditTotal) loadAudit(state.auditPage + 1).catch(error => showToast('Error de auditoría', error.message, '!')); });
    $('#exportAuditButton')?.addEventListener('click', exportAuditCsv);
    $('#retentionSettingsForm')?.addEventListener('submit', async event => {
      event.preventDefault();
      const body = { action: 'update_settings', audit_retention_days: Number($('#auditRetentionDays').value), backup_retention_days: Number($('#backupRetentionDays').value), suspicious_login_threshold: Number($('#suspiciousThreshold').value) };
      try { const result = await apiRequest('api/admin-tools.php', { method: 'POST', body: JSON.stringify(body) }); showToast('Política guardada', result.message || 'La configuración se guardó.'); await loadAudit(state.auditPage || 1); }
      catch (error) { showToast('No se guardó la política', error.message, '!'); }
    });
    $('#applyRetentionButton')?.addEventListener('click', async () => {
      if (!window.confirm('Esto eliminará permanentemente los logs anteriores al período de retención y marcará los backups vencidos como eliminados. ¿Continuar?')) return;
      try { const result = await apiRequest('api/admin-tools.php', { method: 'POST', body: JSON.stringify({ action: 'apply_retention' }) }); showToast('Retención aplicada', `Logs eliminados: ${Number(result.result?.audit_logs_removed || 0)} · Backups vencidos: ${Number(result.result?.backups_expired || 0)}`); await loadAudit(1); if (state.currentView === 'backups') await loadBackups(); }
      catch (error) { showToast('No se aplicó la retención', error.message, '!'); }
    });
    $('#createBackupButton')?.addEventListener('click', createBackupNow);
    $('#refreshBackupsButton')?.addEventListener('click', () => loadBackups().catch(error => showToast('No se pudieron cargar las copias', error.message, '!')));
    $('#backupList')?.addEventListener('click', async event => {
      const verify = event.target.closest('[data-backup-verify]');
      const remove = event.target.closest('[data-backup-delete]');
      if (verify) {
        verify.disabled = true;
        try { const result = await apiRequest('api/admin-tools.php', { method: 'POST', body: JSON.stringify({ action: 'verify_backup', id: Number(verify.dataset.backupVerify) }) }); showToast('Integridad verificada', result.message || 'La copia es válida.'); await loadBackups(); }
        catch (error) { showToast('La verificación falló', error.message, '!'); await loadBackups().catch(() => {}); }
        finally { verify.disabled = false; }
      } else if (remove) {
        const id = Number(remove.dataset.backupDelete);
        if (!window.confirm('¿Eliminar este archivo cifrado? Se conservará la fila de historial, pero el archivo ya no podrá restaurarse.')) return;
        remove.disabled = true;
        try { const result = await apiRequest('api/admin-tools.php', { method: 'POST', body: JSON.stringify({ action: 'delete_backup', id }) }); showToast('Copia eliminada', result.message); await loadBackups(); }
        catch (error) { showToast('No se pudo eliminar', error.message, '!'); }
        finally { remove.disabled = false; }
      }
    });
  }

  function initAnalyticsDates() {
    const now = new Date(); const start = new Date(now.getFullYear(), now.getMonth(), 1);
    if ($('#analyticsFrom') && !$('#analyticsFrom').value) $('#analyticsFrom').value = localDateInput(start);
    if ($('#analyticsTo') && !$('#analyticsTo').value) $('#analyticsTo').value = localDateInput(now);
  }
  async function loadAnalytics() {
    if (!can('analytics.view')) throw new Error('Tu rol no tiene permiso de analítica.');
    initAnalyticsDates();
    const from = $('#analyticsFrom').value; const to = $('#analyticsTo').value;
    if (!from || !to || from > to) throw new Error('Elige un período válido.');
    const params = new URLSearchParams({ view: 'analytics', from, to });
    const data = await apiRequest(`api/admin-tools.php?${params.toString()}`, { method: 'GET' });
    state.analyticsData = data; renderAnalytics(data);
  }
  function renderAnalytics(data) {
    const summary = data.summary || {}; const prefs = data.preferences || {};
    $('#analyticsPeriodLabel').textContent = `Período: ${data.period.from} al ${data.period.to}. Comparación con los ${Number(data.period.days)} días anteriores; horario local de Perú.`;
    const metrics = [
      ['net_sales','Cobros netos',money(summary.net_total || 0),`${money(summary.gross_payments || 0)} cobros − ${money(summary.refunds || 0)} reembolsos`],
      ['paid_orders','Pedidos pagados',Number(summary.paid_orders || 0),`${Number(summary.total_orders || 0)} pedidos totales del período`],
      ['average_ticket','Ticket promedio',money(summary.average_ticket || 0),'Por pedido pagado'],
      ['customers','Clientes',Number(summary.unique_customers || 0),`${Number(summary.new_customers || 0)} nuevos · ${Number(summary.returning_customers || 0)} recurrentes`],
      ['conversion','Conversión de tienda',`${Number(summary.conversion_rate || 0).toFixed(1)}%`,`${Number(summary.web_paid_orders || 0)} pedidos web pagados / ${Number(summary.web_orders || 0)} pedidos web`],
      ['estimated_margin','Margen estimado',money(summary.estimated_margin || 0),'Solo productos con receta calculable'],
      ['cancellations','Cancelaciones',Number(summary.cancelled_orders || 0),`${Number(summary.refunded_orders || 0)} pedidos reembolsados`]
    ];
    $('#analyticsSummary').innerHTML = metrics.filter(item => prefs[item[0]] !== false).map(item => `<article class="white-card report-kpi"><small>${escapeHTML(item[1])}</small><strong>${escapeHTML(item[2])}</strong><span>${escapeHTML(item[3])}</span></article>`).join('') || '<article class="white-card report-kpi"><strong>Sin indicadores</strong><span>Selecciona una o más métricas en el panel personalizable.</span></article>';
    $$('#analyticsMetricPrefs input[type="checkbox"]').forEach(input => { input.checked = prefs[input.value] !== false; });
    const delta = summary.change_percent;
    $('#analyticsComparison').textContent = delta === null || delta === undefined ? 'Sin período comparable' : `${Number(delta) >= 0 ? '+' : ''}${Number(delta).toFixed(1)}% vs. período anterior`;
    const daily = data.daily || []; const maximum = Math.max(1, ...daily.map(row => Math.abs(Number(row.net_total || 0))));
    $('#analyticsDailyChart').innerHTML = daily.length ? daily.map(row => { const value = Number(row.net_total || 0); const width = Math.max(value !== 0 ? 2 : 0, Math.min(100, Math.abs(value) / maximum * 100)); return `<div class="daily-chart-row"><span>${escapeHTML(row.day)}</span><div class="daily-chart-track"><i class="${value < 0 ? 'is-negative' : ''}" style="width:${width}%"></i></div><strong>${money(value)}</strong></div>`; }).join('') : '<div class="billing-empty compact-empty"><strong>Sin movimientos</strong><p>No se han registrado pagos en este período.</p></div>';
    $('#analyticsCustomerBehavior').innerHTML = `<div class="report-data-row"><div><strong>Clientes identificados</strong><small>Con compras pagadas en el período</small></div><b>${Number(summary.unique_customers || 0)}</b></div><div class="report-data-row"><div><strong>Nuevos</strong><small>Alta de ficha dentro del período</small></div><b>${Number(summary.new_customers || 0)}</b></div><div class="report-data-row"><div><strong>Recurrentes</strong><small>Con compra pagada anterior al período</small></div><b>${Number(summary.returning_customers || 0)}</b></div>`;
    $('#analyticsForecast').textContent = `Referencia de cobros para 7 días: ${money(summary.forecast_7_days || 0)}. Estimación simple usando el promedio diario del período.`;
    const products = data.product_profitability || [];
    $('#analyticsProfitability').innerHTML = products.length ? products.map((row,index) => `<div class="report-data-row"><div><div class="report-ranked"><span>${index+1}</span><strong>${escapeHTML(row.product_name)}</strong></div><small>${Number(row.units_sold)} unidades · ventas ${money(row.revenue)} · costo estimado ${row.has_recipe ? money(row.estimated_cost) : 'pendiente de receta'}</small></div><b>${row.has_recipe ? money(row.estimated_margin) : '—'}</b></div>`).join('') : '<p class="catalog-note">No hay productos pagados en el período.</p>';
    const peak = data.peak_hours || []; const map = new Map(peak.map(row => [`${Number(row.weekday)}-${Number(row.hour)}`, row])); const hours = [8,10,12,13,14,15,17,18,19,20,21,22]; const days = [[1,'Domingo'],[2,'Lunes'],[3,'Martes'],[4,'Miércoles'],[5,'Jueves'],[6,'Viernes'],[7,'Sábado']]; const maxCount = Math.max(1, ...peak.map(row => Number(row.orders_count || 0)));
    $('#analyticsPeakHours').innerHTML = peak.length ? `<table class="peak-table"><thead><tr><th>Día</th>${hours.map(hour => `<th>${String(hour).padStart(2,'0')}</th>`).join('')}</tr></thead><tbody>${days.map(([day,label]) => `<tr><td>${label}</td>${hours.map(hour => { const row = map.get(`${day}-${hour}`); const count = Number(row?.orders_count || 0); const heat = count ? (0.08 + (count/maxCount)*0.62).toFixed(2) : '0.035'; return `<td style="--heat:${heat}" title="${label} ${hour}:00 · ${count} pedidos">${count || ''}</td>`; }).join('')}</tr>`).join('')}</tbody></table>` : '<p class="catalog-note">No hay pedidos pagados por hora en el período.</p>';
  }

  function initAuditDates() {
    const now = new Date(); const start = new Date(now.getFullYear(), now.getMonth(), 1);
    if ($('#auditFrom') && !$('#auditFrom').value) $('#auditFrom').value = localDateInput(start);
    if ($('#auditTo') && !$('#auditTo').value) $('#auditTo').value = localDateInput(now);
  }
  function currentAuditFilters() {
    const f = state.auditFilters || {};
    return { from: f.from ?? $('#auditFrom')?.value ?? '', to: f.to ?? $('#auditTo')?.value ?? '', event: f.event ?? $('#auditEvent')?.value.trim() ?? '', actor: f.actor ?? $('#auditActor')?.value.trim() ?? '', q: f.q ?? $('#auditQuery')?.value.trim() ?? '' };
  }
  async function loadAudit(page = 1) {
    if (!can('audit.view')) throw new Error('Tu rol no tiene permiso para consultar auditoría.');
    initAuditDates(); state.auditPage = Math.max(1,page);
    if (!state.auditFilters || !Object.keys(state.auditFilters).length) state.auditFilters = { from: $('#auditFrom').value, to: $('#auditTo').value, event: '', actor: '', q: '' };
    const filters = state.auditFilters; const params = new URLSearchParams({ view: 'audit', page: String(state.auditPage) });
    for (const key of ['from','to','event','actor','q']) if (filters[key]) params.set(key, filters[key]);
    const data = await apiRequest(`api/admin-tools.php?${params.toString()}`, { method: 'GET' }); state.auditTotal = Number(data.total || 0); renderAudit(data);
  }
  function renderAudit(data) {
    $('#auditFrom').value = data.filters.from || ''; $('#auditTo').value = data.filters.to || ''; $('#auditEvent').value = data.filters.event || ''; $('#auditActor').value = data.filters.actor || ''; $('#auditQuery').value = data.filters.q || '';
    $('#auditCountLabel').textContent = `${Number(data.total)} registros coinciden con los filtros. Las fechas y marcas del registro se muestran en UTC.`;
    const alerts = data.alerts || [];
    $('#auditAlerts').innerHTML = alerts.length ? alerts.map(alert => `<article class="analytics-alert"><span>⚠</span><div><strong>Actividad de acceso sospechosa</strong><p>IP ${escapeHTML(alert.ip_address || 'no identificada')}: ${Number(alert.attempts)} intentos fallidos en 15 minutos. Último intento: ${escapeHTML(alert.last_attempt)} UTC.</p></div></article>`).join('') : '<article class="analytics-alert is-clear"><span>✓</span><div><strong>Sin alertas por umbral</strong><p>No hay IP que supere el umbral configurado de intentos fallidos en los últimos 15 minutos.</p></div></article>';
    const rows = data.rows || [];
    $('#auditLogList').innerHTML = rows.length ? rows.map(row => { const details = JSON.stringify(row.details_json || {}); const short = details.length > 420 ? details.slice(0,417) + '…' : details; return `<article class="audit-log-row"><div class="audit-log-main"><div class="audit-log-top"><span class="audit-event-pill">${escapeHTML(row.event_code)}</span><strong>${escapeHTML(row.actor_name || 'Sistema/Invitado')}</strong></div><p>${escapeHTML(short || '{}')}</p><small>${escapeHTML(row.actor_email || '')}${row.user_id ? ` · ID ${Number(row.user_id)}` : ''}${row.ip_address ? ` · IP ${escapeHTML(row.ip_address)}` : ''}</small></div><time class="audit-log-time">${escapeHTML(row.created_at)} UTC</time></article>`; }).join('') : '<div class="billing-empty compact-empty"><strong>Sin registros</strong><p>No hay auditoría que coincida con estos filtros.</p></div>';
    const pages = Math.max(1, Math.ceil(Number(data.total || 0) / Number(data.page_size || 100)));
    $('#auditPageLabel').textContent = `Página ${Number(data.page || 1)} de ${pages}`; $('#auditPrevPage').disabled = Number(data.page || 1) <= 1; $('#auditNextPage').disabled = Number(data.page || 1) >= pages;
    if (data.settings) { $('#auditRetentionDays').value = data.settings.audit_retention_days; $('#backupRetentionDays').value = data.settings.backup_retention_days; $('#suspiciousThreshold').value = data.settings.suspicious_login_threshold; }
    $('#exportAuditButton').classList.toggle('is-hidden', !data.can_export); $('#retentionSettingsForm').classList.toggle('is-hidden', !data.can_manage_retention);
    $('#applyRetentionButton').classList.toggle('is-hidden', !data.can_manage_retention);
  }
  function exportAuditCsv() {
    if (!can('audit.export')) return showToast('Acceso restringido', 'Tu rol no puede exportar auditoría.', '!');
    const filters = currentAuditFilters(); const params = new URLSearchParams({ view: 'audit-export' }); for (const key of ['from','to','event','actor','q']) if (filters[key]) params.set(key, filters[key]);
    const link = document.createElement('a'); link.href = `api/admin-tools.php?${params.toString()}`; link.download = ''; document.body.appendChild(link); link.click(); link.remove();
    showToast('Exportación preparada', 'Se solicitó la descarga de la auditoría filtrada.');
  }

  function formatBytes(bytes) {
    const amount = Number(bytes || 0); if (amount < 1024) return `${amount} B`; if (amount < 1024*1024) return `${(amount/1024).toFixed(1)} KB`; if (amount < 1024*1024*1024) return `${(amount/(1024*1024)).toFixed(2)} MB`; return `${(amount/(1024*1024*1024)).toFixed(2)} GB`;
  }
  async function loadBackups() {
    if (!can('backups.view')) throw new Error('Tu rol no tiene permiso para consultar backups.');
    const data = await apiRequest('api/admin-tools.php?view=backups', { method: 'GET' }); state.backupRows = data.backups || []; renderBackups(data);
  }
  function renderBackups(data) {
    const rows = data.backups || []; const complete = rows.filter(row => row.status === 'completed'); const latest = complete[0];
    $('#backupStatusSummary').innerHTML = `<article class="white-card report-kpi"><small>Copias completas</small><strong>${complete.length}</strong><span>Últimas 100 filas del historial</span></article><article class="white-card report-kpi"><small>Última copia</small><strong>${latest ? escapeHTML(latest.created_at) : '—'}</strong><span>${latest ? `${formatBytes(latest.size_bytes)} · ${latest.backup_kind === 'automatic' ? 'Automática' : 'Manual'}` : 'Sin copias completadas'}</span></article><article class="white-card report-kpi"><small>Almacenamiento secundario</small><strong>${complete.some(row => row.has_secondary_copy) ? 'Configurado' : 'No configurado'}</strong><span>Usa un directorio distinto para redundancia</span></article>`;
    const canManage = Boolean(data.permissions?.manage); const canCreate = Boolean(data.permissions?.create);
    $('#createBackupButton').classList.toggle('is-hidden', !canCreate);
    $('#backupList').innerHTML = rows.length ? rows.map(row => {
      const deleted = row.status === 'deleted'; const invalid = row.verification_status === 'invalid'; const label = deleted ? 'Eliminado' : invalid ? 'Integridad fallida' : row.verification_status === 'valid' ? 'Verificado' : row.status === 'failed' ? 'Falló' : 'Pendiente de verificar';
      const statusClass = deleted || invalid || row.status === 'failed' ? 'is-invalid' : '';
      const actions = !deleted && row.status === 'completed' ? `<a class="backup-download" href="api/admin-tools.php?view=download&id=${Number(row.id)}">↓ Descargar</a>${canManage ? `<button type="button" class="backup-verify" data-backup-verify="${Number(row.id)}">✓ Verificar</button><button type="button" class="backup-delete" data-backup-delete="${Number(row.id)}">Eliminar</button>` : ''}` : '';
      return `<article class="backup-row"><div class="backup-row-icon">▣</div><div class="backup-row-main"><div class="backup-row-title"><strong>${escapeHTML(row.file_name || `Backup #${row.id}`)}</strong><span class="backup-state-pill ${statusClass}">${label}</span></div><p>${escapeHTML(row.created_at)} UTC · ${formatBytes(row.size_bytes)} · ${row.is_encrypted ? 'Cifrado AES-256-GCM' : 'Sin cifrar'} · ${row.backup_kind === 'automatic' ? 'Automático' : row.backup_kind === 'pre_restore' ? 'Previo a restauración' : 'Manual'}</p><small>Usuario: ${escapeHTML(row.created_by_name || 'Programación/CLI')} · SHA-256: ${escapeHTML(row.sha256_hash || 'sin hash')}${row.has_secondary_copy ? ' · Copia secundaria disponible' : ''}${row.error_message ? ` · ${escapeHTML(row.error_message)}` : ''}</small></div><div class="backup-row-actions">${actions}</div></article>`;
    }).join('') : '<div class="billing-empty compact-empty"><strong>Aún no hay copias</strong><p>Configura la clave de cifrado y crea el primer backup desde este panel.</p></div>';
  }
  async function createBackupNow() {
    if (!can('backups.create')) return showToast('Acceso restringido', 'No tienes permiso para crear copias.', '!');
    const button = $('#createBackupButton'); button.disabled = true; const original = button.textContent; button.textContent = 'Creando copia…';
    try { const result = await apiRequest('api/admin-tools.php', { method: 'POST', body: JSON.stringify({ action: 'create_backup' }) }); showToast('Backup creado', `${result.backup.file_name} · ${formatBytes(result.backup.size_bytes)}`); await loadBackups(); }
    catch (error) { showToast('No se pudo crear el backup', error.message, '!'); }
    finally { button.disabled = false; button.textContent = original; }
  }

  /* PASO 13: proveedores, compras, recepción y costeo del inventario. */
  function initPurchasing() {
    $('#refreshSuppliersButton')?.addEventListener('click', () => refreshPurchasingData().catch(error => showToast('No se pudo actualizar proveedores', error.message, '!')));
    $('#refreshPurchasingButton')?.addEventListener('click', () => refreshPurchasingData().catch(error => showToast('No se pudo actualizar compras', error.message, '!')));
    $('#supplierForm')?.addEventListener('submit', saveSupplier);
    $('#supplierResetButton')?.addEventListener('click', resetSupplierForm);
    $('#supplierSearch')?.addEventListener('input', renderSuppliers);
    $('#supplierList')?.addEventListener('click', handleSupplierListClick);
    $('#purchaseOrderForm')?.addEventListener('submit', createPurchaseOrder);
    $('#addPurchaseLineButton')?.addEventListener('click', addPurchaseLineRow);
    $('#purchaseResetButton')?.addEventListener('click', resetPurchaseForm);
    $('#purchaseLines')?.addEventListener('input', updatePurchaseSubtotalPreview);
    $('#purchaseLines')?.addEventListener('change', updatePurchaseSubtotalPreview);
    $('#purchaseLines')?.addEventListener('click', event => {
      const button = event.target.closest('[data-remove-purchase-line]');
      if (button) { const rows = $$('#purchaseLines [data-purchase-line]'); if (rows.length > 1) button.closest('[data-purchase-line]').remove(); else button.closest('[data-purchase-line]').querySelector('[data-purchase-quantity]').value = '1'; updatePurchaseSubtotalPreview(); }
    });
    $('#purchaseOrdersList')?.addEventListener('click', handlePurchaseListClick);
    $('#purchaseStatusFilters')?.querySelectorAll('[data-purchase-filter]').forEach(button => button.addEventListener('click', () => {
      state.purchaseFilter = button.dataset.purchaseFilter;
      $('#purchaseStatusFilters').querySelectorAll('[data-purchase-filter]').forEach(item => item.classList.toggle('is-selected', item === button));
      renderPurchaseOrders();
    }));
    $('#saveCostingMethodButton')?.addEventListener('click', saveCostingMethod);
  }

  async function refreshPurchasingData() {
    const data = await apiRequest('api/purchasing.php?view=dashboard', { method: 'GET' });
    state.purchasingData = data;
    renderSupplierSummary(data);
    renderSuppliers();
    renderPurchaseSummary(data);
    renderPurchaseOrders();
    renderCosting(data);
    renderSupplierOptions();
    if ($('#purchaseLines') && !$('#purchaseLines [data-purchase-line]')) resetPurchaseLines();
    $('#supplierFormCard')?.classList.toggle('is-hidden', !data.permissions?.suppliers_manage);
    $('#purchaseFormCard')?.classList.toggle('is-hidden', !data.permissions?.purchases_manage);
    $('#inventoryCostCard')?.classList.toggle('is-hidden', !data.permissions?.costs_view);
    $('#costHistoryCard')?.classList.toggle('is-hidden', !data.permissions?.costs_view);
    $('#manageCostsButton')?.classList.toggle('is-hidden', !data.permissions?.costs_view);
  }

  function purchasingStatusText(status) {
    return ({ draft: 'Borrador', sent: 'Enviada', partially_received: 'Recepción parcial', received: 'Recibida', cancelled: 'Cancelada' })[status] || status;
  }
  function purchasingStatusClass(status) {
    return ({ draft: 'purchase-status-draft', sent: 'purchase-status-sent', partially_received: 'purchase-status-partial', received: 'purchase-status-received', cancelled: 'purchase-status-cancelled' })[status] || '';
  }

  function renderSupplierSummary(data) {
    const suppliers = data.suppliers || [];
    const active = suppliers.filter(s => s.status === 'active').length;
    const totalOrders = suppliers.reduce((sum, s) => sum + Number(s.orders_count || 0), 0);
    const volume = suppliers.reduce((sum, s) => sum + Number(s.purchase_volume || 0), 0);
    $('#supplierSummary').innerHTML = `<article class="white-card report-kpi"><small>Proveedores activos</small><strong>${active}</strong><span>De ${suppliers.length} registrados</span></article><article class="white-card report-kpi"><small>Órdenes históricas</small><strong>${totalOrders}</strong><span>Compras vinculadas</span></article><article class="white-card report-kpi"><small>Compras recibidas</small><strong>${money(volume)}</strong><span>Volumen histórico de órdenes recibidas o parciales</span></article>`;
    $('#supplierCount').textContent = `${suppliers.length} proveedor${suppliers.length === 1 ? '' : 'es'}`;
  }

  function renderSuppliers() {
    const root = $('#supplierList');
    if (!root) return;
    const data = state.purchasingData;
    if (!data) return;
    const query = ($('#supplierSearch')?.value || '').trim().toLowerCase();
    const rows = (data.suppliers || []).filter(s => [s.name,s.tax_id,s.contact_name,s.email,s.phone,s.city].some(value => String(value || '').toLowerCase().includes(query)));
    const canManage = Boolean(data.permissions?.suppliers_manage);
    root.innerHTML = rows.length ? rows.map(s => {
      const stars = s.rating ? `★ ${Number(s.rating).toFixed(1)}/5` : 'Sin calificar';
      const actions = canManage ? `<div class="purchasing-row-actions"><button type="button" class="secondary-button" data-supplier-edit="${Number(s.id)}">Editar</button><button type="button" class="secondary-button ${s.status === 'active' ? 'manager-danger' : ''}" data-supplier-status="${Number(s.id)}" data-next-status="${s.status === 'active' ? 'inactive' : 'active'}">${s.status === 'active' ? 'Desactivar' : 'Activar'}</button></div>` : '';
      return `<article class="purchasing-row supplier-row"><div class="purchasing-row-icon">♧</div><div class="purchasing-row-main"><div class="purchasing-row-title"><strong>${escapeHTML(s.name)}</strong><span class="purchase-status ${s.status === 'active' ? 'purchase-status-received' : 'purchase-status-cancelled'}">${s.status === 'active' ? 'Activo' : 'Inactivo'}</span></div><p>${escapeHTML(s.tax_id || 'Sin documento')} · ${escapeHTML(s.contact_name || 'Sin contacto')} · ${escapeHTML(s.phone || 'Sin teléfono')}</p><small>${escapeHTML(s.email || 'Sin correo')} ${s.city ? ' · ' + escapeHTML(s.city) : ''} · ${stars} · ${Number(s.orders_count || 0)} órdenes · ${money(s.purchase_volume || 0)}</small></div>${actions}</article>`;
    }).join('') : '<div class="billing-empty compact-empty"><strong>No hay proveedores para mostrar</strong><p>Registra un proveedor o cambia la búsqueda.</p></div>';
  }

  function resetSupplierForm() {
    const form = $('#supplierForm'); if (!form) return;
    form.reset(); $('#supplierId').value = ''; $('#supplierFormTitle').textContent = 'Registrar proveedor'; $('#supplierSaveButton').textContent = 'Guardar proveedor';
  }

  async function saveSupplier(event) {
    event.preventDefault();
    if (!can('suppliers.manage')) return showToast('Acceso restringido', 'No tienes permiso para gestionar proveedores.', '!');
    const payload = { action: 'supplier.save', id: Number($('#supplierId').value) || null, name: $('#supplierName').value.trim(), tax_id: $('#supplierTaxId').value.trim(), contact_name: $('#supplierContact').value.trim(), email: $('#supplierEmail').value.trim(), phone: $('#supplierPhone').value.trim(), rating: $('#supplierRating').value, address: $('#supplierAddress').value.trim(), city: $('#supplierCity').value.trim(), notes: $('#supplierNotes').value.trim() };
    try {
      const result = await apiRequest('api/purchasing.php', { method: 'POST', body: JSON.stringify(payload) });
      resetSupplierForm(); await refreshPurchasingData(); showToast('Proveedor guardado', result.message || 'Se actualizaron los datos del proveedor.');
    } catch (error) { showToast('No se pudo guardar el proveedor', error.message, '!'); }
  }

  function handleSupplierListClick(event) {
    const edit = event.target.closest('[data-supplier-edit]');
    const status = event.target.closest('[data-supplier-status]');
    if (edit) {
      const supplier = (state.purchasingData?.suppliers || []).find(item => Number(item.id) === Number(edit.dataset.supplierEdit));
      if (!supplier) return;
      $('#supplierId').value = supplier.id; $('#supplierName').value = supplier.name || ''; $('#supplierTaxId').value = supplier.tax_id || ''; $('#supplierContact').value = supplier.contact_name || ''; $('#supplierEmail').value = supplier.email || ''; $('#supplierPhone').value = supplier.phone || ''; $('#supplierRating').value = supplier.rating ?? ''; $('#supplierAddress').value = supplier.address || ''; $('#supplierCity').value = supplier.city || ''; $('#supplierNotes').value = supplier.notes || ''; $('#supplierFormTitle').textContent = 'Editar proveedor'; $('#supplierSaveButton').textContent = 'Guardar cambios'; $('#supplierFormCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else if (status) {
      apiRequest('api/purchasing.php', { method: 'POST', body: JSON.stringify({ action: 'supplier.status', id: Number(status.dataset.supplierStatus), status: status.dataset.nextStatus }) }).then(async result => { await refreshPurchasingData(); showToast('Proveedor actualizado', result.message); }).catch(error => showToast('No se pudo actualizar', error.message, '!'));
    }
  }

  function renderPurchaseSummary(data) {
    const summary = data.summary || {};
    $('#purchaseSummary').innerHTML = `<article class="white-card report-kpi"><small>Órdenes abiertas</small><strong>${Number(summary.open_orders || 0)}</strong><span>Borrador, enviadas o recepción parcial</span></article><article class="white-card report-kpi"><small>Recepciones pendientes</small><strong>${Number(summary.pending_receipts || 0)}</strong><span>Órdenes con cantidades por recibir</span></article><article class="white-card report-kpi"><small>Compras del mes</small><strong>${money(summary.month_spend || 0)}</strong><span>Órdenes creadas este mes (importe registrado)</span></article>`;
    $('#purchaseOrderCount').textContent = `${(data.orders || []).length} órdenes`;
  }

  function renderSupplierOptions() {
    const select = $('#purchaseSupplier'); if (!select) return;
    const current = select.value;
    const list = (state.purchasingData?.suppliers || []).filter(s => s.status === 'active');
    select.innerHTML = '<option value="">Selecciona proveedor…</option>' + list.map(s => `<option value="${Number(s.id)}">${escapeHTML(s.name)}${s.tax_id ? ' · ' + escapeHTML(s.tax_id) : ''}</option>`).join('');
    if (list.some(s => String(s.id) === current)) select.value = current;
  }

  function resetPurchaseLines() {
    const root = $('#purchaseLines'); if (!root) return;
    root.innerHTML = '';
    addPurchaseLineRow();
  }

  function addPurchaseLineRow() {
    const root = $('#purchaseLines'); if (!root) return;
    const ingredients = state.purchasingData?.ingredients || [];
    if (!ingredients.length) {
      root.innerHTML = '<p class="catalog-note purchase-line-empty">No hay ingredientes activos. Registra ingredientes y stock en Menú → Ingredientes antes de crear una orden.</p>';
      updatePurchaseSubtotalPreview(); return;
    }
    const row = document.createElement('div'); row.className = 'purchase-line'; row.dataset.purchaseLine = '1';
    row.innerHTML = `<div class="form-field purchase-line-ingredient"><label>Ingrediente</label><select data-purchase-ingredient required>${ingredients.map(i => `<option value="${Number(i.id)}">${escapeHTML(i.name)} · ${escapeHTML(i.unit)}</option>`).join('')}</select><small>Stock actual: <span data-stock-label>${Number(ingredients[0].stock_quantity).toLocaleString('es-PE')} ${escapeHTML(ingredients[0].unit)}</span></small></div><div class="form-field"><label>Cantidad</label><input type="number" min="0.001" step="0.001" max="10000000" value="1" data-purchase-quantity required></div><div class="form-field"><label>Costo unit. (S/)</label><input type="number" min="0" max="100000000" step="0.0001" value="0.0000" data-purchase-cost required></div><button type="button" class="remove-purchase-line" data-remove-purchase-line aria-label="Eliminar línea">×</button>`;
    root.appendChild(row);
    const select = row.querySelector('[data-purchase-ingredient]');
    select.addEventListener('change', () => {
      const chosen = ingredients.find(i => Number(i.id) === Number(select.value));
      row.querySelector('[data-stock-label]').textContent = chosen ? `${Number(chosen.stock_quantity).toLocaleString('es-PE')} ${chosen.unit}` : '—';
    });
    updatePurchaseSubtotalPreview();
  }

  function updatePurchaseSubtotalPreview() {
    if (!$('#purchaseSubtotalPreview')) return;
    const subtotal = $$('#purchaseLines [data-purchase-line]').reduce((sum, row) => sum + Math.max(0, Number(row.querySelector('[data-purchase-quantity]')?.value || 0)) * Math.max(0, Number(row.querySelector('[data-purchase-cost]')?.value || 0)), 0);
    $('#purchaseSubtotalPreview').textContent = money(subtotal);
  }

  function resetPurchaseForm() {
    const form = $('#purchaseOrderForm'); if (!form) return;
    form.reset(); $('#purchaseTaxRate').value = '18'; $('#purchaseShippingCost').value = '0'; resetPurchaseLines(); updatePurchaseSubtotalPreview();
  }

  async function createPurchaseOrder(event) {
    event.preventDefault();
    if (!can('purchases.manage')) return showToast('Acceso restringido', 'No tienes permiso para crear órdenes de compra.', '!');
    const items = $$('#purchaseLines [data-purchase-line]').map(row => ({ ingredient_id: Number(row.querySelector('[data-purchase-ingredient]').value), quantity: Number(row.querySelector('[data-purchase-quantity]').value), unit_cost: Number(row.querySelector('[data-purchase-cost]').value) }));
    const payload = { action: 'order.create', supplier_id: Number($('#purchaseSupplier').value), expected_date: $('#purchaseExpectedDate').value, tax_rate: Number($('#purchaseTaxRate').value), shipping_cost: Number($('#purchaseShippingCost').value), notes: $('#purchaseNotes').value.trim(), items };
    const button = $('#purchaseSaveButton'); button.disabled = true;
    try { const result = await apiRequest('api/purchasing.php', { method: 'POST', body: JSON.stringify(payload) }); resetPurchaseForm(); await refreshPurchasingData(); showToast('Orden creada', `${result.order_number} quedó en borrador.`); }
    catch (error) { showToast('No se pudo crear la orden', error.message, '!'); }
    finally { button.disabled = false; }
  }

  function renderPurchaseOrders() {
    const root = $('#purchaseOrdersList'); if (!root || !state.purchasingData) return;
    const all = state.purchasingData.orders || [];
    const rows = state.purchaseFilter === 'all' ? all : all.filter(row => row.status === state.purchaseFilter);
    const permissions = state.purchasingData.permissions || {};
    root.innerHTML = rows.length ? rows.map(order => {
      let actions = `<button type="button" class="secondary-button" data-po-print="${Number(order.id)}">Imprimir</button>`;
      if (order.status === 'draft' && permissions.purchases_manage) {
        if (order.supplier_email) actions += `<button type="button" class="secondary-button" data-po-send="${Number(order.id)}">Enviar correo</button>`;
        actions += `<button type="button" class="secondary-button" data-po-mark-sent="${Number(order.id)}">Marcar enviada</button><button type="button" class="secondary-button manager-danger" data-po-cancel="${Number(order.id)}">Cancelar</button>`;
      } else if (['sent','partially_received'].includes(order.status)) {
        if (permissions.purchases_receive) actions += `<button type="button" class="cyan-button" data-po-receive="${Number(order.id)}">Recepcionar</button>`;
        if (order.status === 'sent' && permissions.purchases_manage) actions += `<button type="button" class="secondary-button manager-danger" data-po-cancel="${Number(order.id)}">Cancelar</button>`;
      }
      return `<article class="purchasing-row po-row"><div class="purchasing-row-icon">▣</div><div class="purchasing-row-main"><div class="purchasing-row-title"><strong>${escapeHTML(order.order_number)}</strong><span class="purchase-status ${purchasingStatusClass(order.status)}">${purchasingStatusText(order.status)}</span></div><p>${escapeHTML(order.supplier_name)} · Fecha ${escapeHTML(order.order_date || '')}${order.expected_date ? ' · Entrega prevista ' + escapeHTML(order.expected_date) : ''}</p><small>${Number(order.items_count)} líneas · Pendiente por recibir: ${Number(order.pending_quantity).toLocaleString('es-PE')} unidades · Creada por ${escapeHTML(order.created_by_name || 'Usuario')}</small><div class="purchasing-row-actions inline-purchase-actions">${actions}</div></div><div class="purchase-order-total"><strong>${money(order.total_amount)}</strong><small>${order.received_at ? 'Recibida ' + escapeHTML(order.received_at) : order.sent_at ? 'Enviada ' + escapeHTML(order.sent_at) : 'Borrador'}</small></div></article>`;
    }).join('') : '<div class="billing-empty compact-empty"><strong>No hay órdenes en este filtro</strong><p>Crea una orden para un proveedor e ingredientes activos.</p></div>';
  }

  async function handlePurchaseListClick(event) {
    const print = event.target.closest('[data-po-print]'); const send = event.target.closest('[data-po-send]'); const manual = event.target.closest('[data-po-mark-sent]'); const receive = event.target.closest('[data-po-receive]'); const cancel = event.target.closest('[data-po-cancel]');
    try {
      if (print) { await printPurchaseOrder(Number(print.dataset.poPrint)); return; }
      if (send) { send.disabled = true; const result = await apiRequest('api/purchasing.php', { method: 'POST', body: JSON.stringify({ action: 'order.send', id: Number(send.dataset.poSend) }) }); await refreshPurchasingData(); showToast('Orden enviada', result.message); }
      else if (manual) { if (!confirm('¿Confirmas que la orden se envió al proveedor por un canal externo (impresa, teléfono o correo manual)?')) return; const result = await apiRequest('api/purchasing.php', { method: 'POST', body: JSON.stringify({ action: 'order.mark_sent', id: Number(manual.dataset.poMarkSent) }) }); await refreshPurchasingData(); showToast('Orden actualizada', result.message); }
      else if (receive) await openReceivePurchaseModal(Number(receive.dataset.poReceive));
      else if (cancel) { if (!confirm('¿Cancelar esta orden de compra? Solo se pueden cancelar órdenes sin recepción registrada.')) return; const result = await apiRequest('api/purchasing.php', { method: 'POST', body: JSON.stringify({ action: 'order.cancel', id: Number(cancel.dataset.poCancel) }) }); await refreshPurchasingData(); showToast('Orden cancelada', result.message); }
    } catch (error) { showToast('No se pudo completar la acción', error.message, '!'); }
    finally { if (send) send.disabled = false; }
  }

  async function printPurchaseOrder(id) {
    const win = window.open('', '_blank');
    if (!win) { showToast('Ventana bloqueada', 'Permite ventanas emergentes para imprimir la orden.', '!'); return; }
    try {
      win.opener = null;
      const data = await apiRequest(`api/purchasing.php?view=order&id=${id}`, { method: 'GET' });
      const o = data.order; const items = data.items || [];
      const rows = items.map(i => `<tr><td>${escapeHTML(i.ingredient_name_snapshot)}</td><td>${Number(i.ordered_quantity).toLocaleString('es-PE')} ${escapeHTML(i.unit)}</td><td>${money(i.unit_cost)}</td><td>${money(i.line_total)}</td></tr>`).join('');
      const logo = new URL('assets/desego-logo.png', window.location.href).href;
      win.document.write(`<!doctype html><html lang="es"><head><meta charset="utf-8"><title>${escapeHTML(o.order_number)} · DESEGO</title><style>body{font:13px Arial,sans-serif;color:#16315f;max-width:820px;margin:28px auto;padding:0 22px}header{display:flex;align-items:center;gap:15px;border-bottom:2px solid #0845d8;padding-bottom:15px}img{width:80px;height:80px;object-fit:contain}h1{font-size:22px;margin:0 0 5px}p{line-height:1.5;margin:5px 0}table{width:100%;border-collapse:collapse;margin-top:20px}th,td{text-align:left;padding:10px 7px;border-bottom:1px solid #e4ecf8}th{background:#f2f7ff}.total{margin:20px 0 0 auto;width:300px}.total div{display:flex;justify-content:space-between;padding:5px 0}.grand{border-top:2px solid #0845d8;font-size:18px;font-weight:bold;margin-top:7px}@media print{body{margin:0 auto;padding:0 4mm}}</style></head><body><header><img src="${logo}" alt="DESEGO"><div><h1>DESEGO CEVICHERÍA</h1><strong>ORDEN DE COMPRA ${escapeHTML(o.order_number)}</strong><p>Proveedor: ${escapeHTML(o.supplier_name)}</p><p>RUC/documento: ${escapeHTML(o.supplier_reference || o.tax_id || '—')}</p><p>Fecha: ${escapeHTML(o.order_date)} · Fecha estimada: ${escapeHTML(o.expected_date || '—')}</p></div></header><p><strong>Estado:</strong> ${purchasingStatusText(o.status)}</p><table><thead><tr><th>Ingrediente</th><th>Cantidad</th><th>Costo unitario</th><th>Total</th></tr></thead><tbody>${rows}</tbody></table><div class="total"><div><span>Subtotal</span><strong>${money(o.subtotal)}</strong></div><div><span>Impuesto (${Number(o.tax_rate).toFixed(2)}%)</span><strong>${money(o.tax_amount)}</strong></div><div><span>Transporte</span><strong>${money(o.shipping_cost)}</strong></div><div class="grand"><span>Total</span><strong>${money(o.total_amount)}</strong></div></div><p><strong>Notas:</strong> ${escapeHTML(o.notes || '—')}</p><script>window.onload=()=>{window.focus();window.print();};<\/script></body></html>`);
      win.document.close();
    } catch (error) { win.close(); throw error; }
  }

  async function openReceivePurchaseModal(id) {
    const data = await apiRequest(`api/purchasing.php?view=order&id=${id}`, { method: 'GET' });
    const order = data.order; const items = data.items || [];
    const itemRows = items.map(item => {
      const remaining = Math.max(0, Number(item.ordered_quantity) - Number(item.received_quantity));
      return `<div class="purchase-receive-row"><div><strong>${escapeHTML(item.ingredient_name_snapshot)}</strong><small>Solicitado ${Number(item.ordered_quantity).toLocaleString('es-PE')} ${escapeHTML(item.unit)} · recibido ${Number(item.received_quantity).toLocaleString('es-PE')} · pendiente ${remaining.toLocaleString('es-PE')}</small></div><label><span>Recibir</span><input type="number" min="0" max="${remaining}" step="0.001" value="${remaining}" data-receive-qty="${Number(item.id)}" ${remaining <= 0 ? 'disabled' : ''}></label><label><span>Costo unit. S/</span><input type="number" min="0" max="100000000" step="0.0001" value="${Number(item.unit_cost).toFixed(4)}" data-receive-cost="${Number(item.id)}" ${remaining <= 0 ? 'disabled' : ''}></label></div>`;
    }).join('');
    openModal(`<span class="modal-eyebrow">RECEPCIÓN DE MERCADERÍA</span><h2 class="modal-title">Recepcionar ${escapeHTML(order.order_number)}</h2><p class="modal-description">Proveedor: ${escapeHTML(order.supplier_name)}. Indica la cantidad físicamente recibida y el costo unitario neto que figura en la compra. Las cantidades pueden recibirse parcialmente.</p><form id="purchaseReceiveForm" class="modal-form"><div class="modal-field"><label for="purchaseReceiptInvoice">Documento / factura del proveedor</label><input id="purchaseReceiptInvoice" maxlength="120" placeholder="N.º de factura o guía (opcional)"></div><div class="modal-field"><label for="purchaseReceiptNotes">Notas de recepción</label><input id="purchaseReceiptNotes" maxlength="1000" placeholder="Lote, diferencia o incidencia"></div><div class="purchase-receive-items">${itemRows}</div><div class="modal-actions"><button type="button" class="secondary-button" data-close-modal>Cancelar</button><button type="submit" class="modal-primary">Guardar recepción</button></div></form>`);
    $('#modalRoot .modal-dialog')?.classList.add('is-wide');
    $('#purchaseReceiveForm').addEventListener('submit', async event => {
      event.preventDefault(); const submit = event.currentTarget.querySelector('button[type="submit"]'); submit.disabled = true;
      const receiptItems = items.map(item => ({ item_id: Number(item.id), quantity: Number($(`[data-receive-qty="${Number(item.id)}"]`)?.value || 0), unit_cost: Number($(`[data-receive-cost="${Number(item.id)}"]`)?.value ?? item.unit_cost) }));
      try { const result = await apiRequest('api/purchasing.php', { method: 'POST', body: JSON.stringify({ action: 'order.receive', id, supplier_invoice: $('#purchaseReceiptInvoice').value.trim(), notes: $('#purchaseReceiptNotes').value.trim(), items: receiptItems }) }); closeModal(); await refreshPurchasingData(); showToast('Recepción registrada', result.message); }
      catch (error) { showModalError(error.message); submit.disabled = false; }
    });
  }

  function renderCosting(data) {
    if (!$('#inventoryCostList')) return;
    if (!data.permissions?.costs_view) return;
    $('#costingMethodSelect').value = data.costing_method || 'FIFO';
    const rows = data.cost_rows || [];
    const stockValue = rows.reduce((sum, row) => sum + Number(data.costing_method === 'AVG' ? Number(row.stock_quantity) * Number(row.cost_per_unit) : Number(row.layered_value), 0), 0);
    const totalUnits = rows.reduce((sum, row) => sum + Number(row.stock_quantity), 0);
    $('#inventoryCostSummary').innerHTML = `<div><small>Ingredientes valorados</small><strong>${rows.length}</strong></div><div><small>Unidades en stock*</small><strong>${totalUnits.toLocaleString('es-PE',{maximumFractionDigits:3})}</strong></div><div><small>Valor estimado de stock</small><strong>${money(stockValue)}</strong></div>`;
    $('#inventoryCostList').innerHTML = rows.length ? rows.map(row => {
      const value = data.costing_method === 'AVG' ? Number(row.stock_quantity) * Number(row.cost_per_unit) : Number(row.layered_value);
      return `<article class="purchasing-row cost-row"><div class="purchasing-row-icon">S/</div><div class="purchasing-row-main"><div class="purchasing-row-title"><strong>${escapeHTML(row.name)}</strong><span class="purchase-status ${Number(row.stock_quantity) <= Number(row.min_stock) ? 'purchase-status-cancelled' : 'purchase-status-received'}">${Number(row.stock_quantity) <= Number(row.min_stock) ? 'Stock bajo' : 'En stock'}</span></div><p>${Number(row.stock_quantity).toLocaleString('es-PE',{maximumFractionDigits:3})} ${escapeHTML(row.unit)} · mínimo ${Number(row.min_stock).toLocaleString('es-PE',{maximumFractionDigits:3})} ${escapeHTML(row.unit)}</p><small>${Number(row.layer_count)} capas disponibles · Costo medio actual ${money(row.cost_per_unit)} por ${escapeHTML(row.unit)}</small></div><div class="purchase-order-total"><strong>${money(value)}</strong><small>Valor estimado</small></div></article>`;
    }).join('') : '<div class="billing-empty compact-empty"><strong>No hay ingredientes activos</strong><p>Registra ingredientes y recibe compras para valorar existencias.</p></div>';
    const history = data.cost_history || []; $('#costHistoryCount').textContent = `${history.length} movimientos`;
    $('#ingredientCostHistoryList').innerHTML = history.length ? history.map(row => `<article class="purchasing-row cost-history-row"><div class="purchasing-row-icon">↗</div><div class="purchasing-row-main"><div class="purchasing-row-title"><strong>${escapeHTML(row.ingredient_name)}</strong><span class="purchase-status purchase-status-sent">${escapeHTML(row.costing_method)}</span></div><p>${escapeHTML(row.reason)}</p><small>${escapeHTML(row.created_at)} · Proveedor: ${escapeHTML(row.supplier_name || '—')} · Orden: ${escapeHTML(row.order_number || '—')} · Usuario: ${escapeHTML(row.user_name || '—')} · Cantidad: ${Number(row.quantity_received).toLocaleString('es-PE',{maximumFractionDigits:3})}</small></div><div class="purchase-order-total"><strong>${money(row.previous_unit_cost)} → ${money(row.new_unit_cost)}</strong><small>Costo unitario</small></div></article>`).join('') : '<div class="billing-empty compact-empty"><strong>Aún no hay cambios de costo</strong><p>El historial se registrará cuando una recepción cambie el costo promedio.</p></div>';
  }

  async function saveCostingMethod() {
    if (!can('inventory.costs.manage')) return showToast('Acceso restringido', 'No tienes permiso para cambiar el método de costeo.', '!');
    try { const result = await apiRequest('api/purchasing.php', { method: 'POST', body: JSON.stringify({ action: 'costing.set_method', method: $('#costingMethodSelect').value }) }); await refreshPurchasingData(); showToast('Costeo actualizado', result.message); }
    catch (error) { showToast('No se pudo cambiar el método', error.message, '!'); }
  }

  /* =======================================================
     PASO 14 — PERSONAL, TURNOS, ASISTENCIA Y SUCURSALES
  ======================================================= */
  const wfText = value => String(value ?? '').trim();
  const wfStatus = status => ({ active: 'Activo', inactive: 'Inactivo', scheduled: 'Programado', in_progress: 'En curso', completed: 'Completado', cancelled: 'Cancelado' })[status] || status;
  const wfEmployment = type => ({ full_time: 'Tiempo completo', part_time: 'Medio tiempo', temporary: 'Temporal', contractor: 'Servicios / contratista' })[type] || type || '—';
  const wfDate = value => value ? new Date(`${String(value).slice(0,10)}T12:00:00`).toLocaleDateString('es-PE',{day:'2-digit',month:'short',year:'numeric'}) : '—';
  function wfTodayISO() { const now = new Date(); return `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`; }
  function wfShiftRangeDefault() { const from = wfTodayISO(); const d = new Date(); d.setDate(d.getDate()+13); const to = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; return {from,to}; }
  function initWorkforce() {
    const dateRange = wfShiftRangeDefault();
    if ($('#shiftFilterFrom')) $('#shiftFilterFrom').value = dateRange.from;
    if ($('#shiftFilterTo')) $('#shiftFilterTo').value = dateRange.to;
    if ($('#shiftDate')) $('#shiftDate').value = dateRange.from;
    $('#refreshBranchesButton')?.addEventListener('click', () => refreshBranches().catch(error => showToast('Error al actualizar sucursales', error.message, '!')));
    $('#refreshEmployeesButton')?.addEventListener('click', () => refreshEmployees().catch(error => showToast('Error al actualizar personal', error.message, '!')));
    $('#refreshShiftsButton')?.addEventListener('click', () => refreshShifts().catch(error => showToast('Error al actualizar turnos', error.message, '!')));
    $('#branchSearch')?.addEventListener('input', renderBranches);
    $('#employeeSearch')?.addEventListener('input', renderEmployees);
    $('#branchResetButton')?.addEventListener('click', resetBranchForm);
    $('#employeeResetButton')?.addEventListener('click', resetEmployeeForm);
    $('#shiftResetButton')?.addEventListener('click', resetShiftForm);
    $('#branchForm')?.addEventListener('submit', saveBranchForm);
    $('#employeeForm')?.addEventListener('submit', saveEmployeeForm);
    $('#shiftForm')?.addEventListener('submit', saveShiftForm);
    $('#shiftFilterForm')?.addEventListener('submit', event => { event.preventDefault(); refreshShifts().catch(error => showToast('No se pudieron filtrar los turnos', error.message, '!')); });
    $('#branchList')?.addEventListener('click', handleBranchListAction);
    $('#employeeList')?.addEventListener('click', handleEmployeeListAction);
    $('#shiftList')?.addEventListener('click', handleShiftListAction);
    $('#employeeBranch')?.addEventListener('change', () => { const id = Number($('#employeeBranch').value); if (id && $('#shiftBranch') && !$('#shiftBranch').value) $('#shiftBranch').value = String(id); });
    $('#shiftEmployee')?.addEventListener('change', () => { const employee = state.workforceData.employees.find(item => Number(item.id) === Number($('#shiftEmployee').value)); if (employee?.branch_id) $('#shiftBranch').value = String(employee.branch_id); });
  }
  async function refreshBranches() {
    const result = await apiRequest('api/workforce.php?view=branches', {method:'GET'});
    state.workforceData.branches = result.branches || [];
    renderBranches();
    populateWorkforceBranchSelects();
  }
  async function refreshEmployees() {
    const result = await apiRequest('api/workforce.php?view=employees', {method:'GET'});
    state.workforceData.employees = result.employees || [];
    state.workforceData.users = result.users || [];
    state.workforceData.branches = result.branches || state.workforceData.branches || [];
    state.workforceData.canManageEmployees = Boolean(result.can_manage);
    state.workforceData.canViewRate = Boolean(result.can_view_rate);
    renderEmployees();
    populateWorkforceBranchSelects();
    populateEmployeeUserSelect();
    populateShiftEmployeeSelect();
  }
  async function refreshShifts() {
    const from = $('#shiftFilterFrom')?.value || wfShiftRangeDefault().from;
    const to = $('#shiftFilterTo')?.value || wfShiftRangeDefault().to;
    const branchId = $('#shiftFilterBranch')?.value || '';
    const params = new URLSearchParams({view:'shifts',from,to});
    if (branchId) params.set('branch_id', branchId);
    const result = await apiRequest(`api/workforce.php?${params.toString()}`, {method:'GET'});
    state.workforceData.shifts = result.shifts || [];
    state.workforceData.branches = result.branches || state.workforceData.branches || [];
    state.workforceData.employees = result.employees?.length ? result.employees : (state.workforceData.employees || []);
    state.workforceData.canManageShifts = Boolean(result.can_manage);
    state.workforceData.canAttendance = Boolean(result.can_attendance);
    state.workforceData.canViewAllShifts = Boolean(result.can_view_all);
    state.shiftRange = {from,to,branchId};
    renderShifts(result.summary || {});
    populateWorkforceBranchSelects();
    populateShiftEmployeeSelect();
  }
  function renderBranches() {
    const all = state.workforceData.branches || [];
    const query = ($('#branchSearch')?.value || '').trim().toLowerCase();
    const rows = all.filter(b => [b.code,b.name,b.address,b.district,b.province,b.region,b.phone,b.tax_id].some(v => String(v||'').toLowerCase().includes(query)));
    const active = all.filter(b => b.status === 'active').length;
    const main = all.find(b => Number(b.is_main)===1);
    const employeeTotal = all.reduce((sum,b)=>sum+Number(b.active_employees||0),0);
    if ($('#branchSummary')) $('#branchSummary').innerHTML = `<article class="white-card report-kpi"><small>Sucursales activas</small><strong>${active}</strong><span>De ${all.length} registradas</span></article><article class="white-card report-kpi"><small>Sede principal</small><strong class="workforce-kpi-text">${escapeHTML(main?.name || 'Sin definir')}</strong><span>Código ${escapeHTML(main?.code || '—')}</span></article><article class="white-card report-kpi"><small>Personal activo asignado</small><strong>${employeeTotal}</strong><span>Sumado por sede</span></article><article class="white-card report-kpi"><small>Turnos de hoy</small><strong>${all.reduce((sum,b)=>sum+Number(b.todays_shifts||0),0)}</strong><span>Programados o en curso</span></article>`;
    if ($('#branchCount')) $('#branchCount').textContent = `${rows.length} de ${all.length}`;
    if (!$('#branchList')) return;
    $('#branchList').innerHTML = rows.length ? rows.map(b => `<article class="purchasing-row workforce-row"><div class="purchasing-row-icon">⌂</div><div class="purchasing-row-main"><div class="purchasing-row-title"><strong>${escapeHTML(b.name)}</strong><span class="workforce-status ${escapeHTML(b.status)}">${wfStatus(b.status)}</span>${Number(b.is_main)===1?'<span class="workforce-main-badge">Principal</span>':''}</div><p>${escapeHTML(b.code)} · ${escapeHTML([b.district,b.province,b.region].filter(Boolean).join(', ') || b.address || 'Ubicación sin registrar')}</p><div class="workforce-submeta"><span>☎ ${escapeHTML(b.phone || 'Sin teléfono')}</span><span>✉ ${escapeHTML(b.email || 'Sin correo')}</span><span>${Number(b.active_employees||0)} empleado(s) activo(s)</span></div><small>Atención: ${escapeHTML((b.opening_time||'').slice(0,5) || '—')} a ${escapeHTML((b.closing_time||'').slice(0,5) || '—')}</small></div><div class="workforce-row-actions">${can('branches.manage')?`<button type="button" class="workforce-action" data-branch-edit="${Number(b.id)}">Editar</button><button type="button" class="workforce-action ${b.status==='active'?'is-danger':'is-primary'}" data-branch-status="${Number(b.id)}" data-next-status="${b.status==='active'?'inactive':'active'}">${b.status==='active'?'Desactivar':'Activar'}</button>`:''}</div></article>`).join('') : '<div class="workforce-blank"><strong>No hay sucursales que coincidan</strong>Registra una sucursal o modifica la búsqueda.</div>';
  }
  function populateWorkforceBranchSelects() {
    const branches = state.workforceData.branches || [];
    const selects = [['#employeeBranch','Sin asignar'],['#shiftBranch','Selecciona una sucursal…'],['#shiftFilterBranch','Todas las sucursales']];
    for (const [selector,placeholder] of selects) { const select=$(selector); if(!select)continue; const previous=select.value;const options = branches.filter(b=>b.status==='active').map(b=>`<option value="${Number(b.id)}">${escapeHTML(b.name)}${Number(b.is_main)===1?' · Principal':''}</option>`).join('');select.innerHTML=`<option value="">${placeholder}</option>${options}`;if([...select.options].some(o=>o.value===previous))select.value=previous; }
  }
  function populateEmployeeUserSelect() {
    const select=$('#employeeUser');if(!select)return;const previous=select.value;const users=state.workforceData.users||[];select.innerHTML='<option value="">Sin cuenta vinculada</option>'+users.map(u=>`<option value="${Number(u.id)}">${escapeHTML(u.name)} · ${escapeHTML(u.email)} (${escapeHTML(u.role_name)})</option>`).join('');if([...select.options].some(o=>o.value===previous))select.value=previous;
  }
  function populateShiftEmployeeSelect() {
    const select=$('#shiftEmployee');if(!select)return;const previous=select.value;const employees=(state.workforceData.employees||[]).filter(e=>!e.status||e.status==='active');select.innerHTML='<option value="">Selecciona un empleado…</option>'+employees.map(e=>`<option value="${Number(e.id)}">${escapeHTML(e.full_name)} · ${escapeHTML(e.job_title)}</option>`).join('');if([...select.options].some(o=>o.value===previous))select.value=previous;
  }
  function resetBranchForm() { $('#branchForm')?.reset();$('#branchId').value='';$('#branchFormTitle').textContent='Registrar sucursal'; }
  function editBranch(id) { const b=(state.workforceData.branches||[]).find(row=>Number(row.id)===Number(id));if(!b)return;$('#branchId').value=b.id;$('#branchCode').value=b.code||'';$('#branchName').value=b.name||'';$('#branchTaxId').value=b.tax_id||'';$('#branchPhone').value=b.phone||'';$('#branchEmail').value=b.email||'';$('#branchDistrict').value=b.district||'';$('#branchProvince').value=b.province||'';$('#branchRegion').value=b.region||'';$('#branchAddress').value=b.address||'';$('#branchOpeningTime').value=(b.opening_time||'').slice(0,5);$('#branchClosingTime').value=(b.closing_time||'').slice(0,5);$('#branchIsMain').checked=Number(b.is_main)===1;$('#branchFormTitle').textContent='Editar sucursal';$('#branchFormCard').scrollIntoView({behavior:'smooth',block:'start'}); }
  async function saveBranchForm(event) { event.preventDefault();if(!can('branches.manage'))return showToast('Acceso restringido','No tienes permiso para editar sucursales.','!');const button=$('#branchForm').querySelector('button[type="submit"]');button.disabled=true;const payload={action:'branch.save',id:$('#branchId').value?Number($('#branchId').value):null,code:$('#branchCode').value.trim(),name:$('#branchName').value.trim(),tax_id:$('#branchTaxId').value.trim(),phone:$('#branchPhone').value.trim(),email:$('#branchEmail').value.trim(),district:$('#branchDistrict').value.trim(),province:$('#branchProvince').value.trim(),region:$('#branchRegion').value.trim(),address:$('#branchAddress').value.trim(),opening_time:$('#branchOpeningTime').value,closing_time:$('#branchClosingTime').value,is_main:$('#branchIsMain').checked};try{const r=await apiRequest('api/workforce.php',{method:'POST',body:JSON.stringify(payload)});resetBranchForm();await refreshBranches();showToast('Sucursal guardada',r.message);}catch(error){showToast('No se pudo guardar la sucursal',error.message,'!');}finally{button.disabled=false;} }
  async function handleBranchListAction(event) { const edit=event.target.closest('[data-branch-edit]');const status=event.target.closest('[data-branch-status]');if(edit){editBranch(Number(edit.dataset.branchEdit));return;}if(status){const next=status.dataset.nextStatus;const label=next==='inactive'?'desactivar':'activar';if(!confirm(`¿Deseas ${label} esta sucursal?`))return;try{const r=await apiRequest('api/workforce.php',{method:'POST',body:JSON.stringify({action:'branch.status',id:Number(status.dataset.branchStatus),status:next})});await refreshBranches();showToast('Sucursal actualizada',r.message);}catch(error){showToast('No se pudo cambiar el estado',error.message,'!');}} }
  function renderEmployees() {
    const all=state.workforceData.employees||[];const query=($('#employeeSearch')?.value||'').trim().toLowerCase();const rows=all.filter(e=>[e.employee_code,e.full_name,e.job_title,e.phone,e.email,e.branch_name,e.account_email].some(v=>String(v||'').toLowerCase().includes(query)));const active=all.filter(e=>e.status==='active').length;const linked=all.filter(e=>Number(e.user_id)>0).length;const future=all.reduce((n,e)=>n+Number(e.upcoming_shifts||0),0);
    if($('#employeeSummary'))$('#employeeSummary').innerHTML=`<article class="white-card report-kpi"><small>Personal activo</small><strong>${active}</strong><span>De ${all.length} fichas</span></article><article class="white-card report-kpi"><small>Cuentas vinculadas</small><strong>${linked}</strong><span>Acceso DESEGO asociado</span></article><article class="white-card report-kpi"><small>Sin cuenta</small><strong>${all.length-linked}</strong><span>Ficha operativa únicamente</span></article><article class="white-card report-kpi"><small>Turnos próximos</small><strong>${future}</strong><span>Asignaciones pendientes</span></article>`;
    if($('#employeeCount'))$('#employeeCount').textContent=`${rows.length} de ${all.length}`;
    if(!$('#employeeList'))return;
    $('#employeeList').innerHTML=rows.length?rows.map(e=>`<article class="purchasing-row workforce-row"><div class="purchasing-row-icon">${escapeHTML(initials(e.full_name))}</div><div class="purchasing-row-main"><div class="purchasing-row-title"><strong>${escapeHTML(e.full_name)}</strong><span class="workforce-status ${escapeHTML(e.status)}">${wfStatus(e.status)}</span></div><p>${escapeHTML(e.employee_code||'Sin código')} · ${escapeHTML(e.job_title)} · ${escapeHTML(wfEmployment(e.employment_type))}</p><div class="workforce-submeta"><span>⌂ ${escapeHTML(e.branch_name||'Sin sucursal')}</span><span>☎ ${escapeHTML(e.phone||'Sin teléfono')}</span><span>✉ ${escapeHTML(e.email||'Sin correo')}</span></div><small>Cuenta: ${escapeHTML(e.account_name?`${e.account_name} (${e.account_email})`:'Sin cuenta vinculada')} · Contratación: ${wfDate(e.hire_date)} · ${Number(e.upcoming_shifts||0)} turno(s) próximo(s)</small></div><div class="purchase-order-total"><strong>${e.hourly_rate===null?'—':money(e.hourly_rate)}</strong><small>${e.hourly_rate===null?'Tarifa oculta/no registrada':'Tarifa por hora'}</small><div class="workforce-row-actions">${can('employees.manage')?`<button type="button" class="workforce-action" data-employee-edit="${Number(e.id)}">Editar</button><button type="button" class="workforce-action ${e.status==='active'?'is-danger':'is-primary'}" data-employee-status="${Number(e.id)}" data-next-status="${e.status==='active'?'inactive':'active'}">${e.status==='active'?'Desactivar':'Activar'}</button>`:''}</div></div></article>`).join(''):'<div class="workforce-blank"><strong>No hay empleados que coincidan</strong>Registra una ficha de personal o ajusta la búsqueda.</div>';
  }
  function resetEmployeeForm() { $('#employeeForm')?.reset();$('#employeeId').value='';$('#employeeStatus').value='active';$('#employeeType').value='full_time';$('#employeeFormTitle').textContent='Registrar empleado'; }
  function editEmployee(id) { const e=(state.workforceData.employees||[]).find(row=>Number(row.id)===Number(id));if(!e)return;$('#employeeId').value=e.id;$('#employeeCode').value=e.employee_code||'';$('#employeeName').value=e.full_name||'';$('#employeeJobTitle').value=e.job_title||'';$('#employeeBranch').value=e.branch_id?String(e.branch_id):'';$('#employeeUser').value=e.user_id?String(e.user_id):'';$('#employeePhone').value=e.phone||'';$('#employeeEmail').value=e.email||'';$('#employeeType').value=e.employment_type||'full_time';$('#employeeHireDate').value=e.hire_date||'';$('#employeeHourlyRate').value=e.hourly_rate??'';$('#employeeStatus').value=e.status||'active';$('#employeeNotes').value=e.notes||'';$('#employeeFormTitle').textContent='Editar empleado';$('#employeeFormCard').scrollIntoView({behavior:'smooth',block:'start'}); }
  async function saveEmployeeForm(event) { event.preventDefault();if(!can('employees.manage'))return showToast('Acceso restringido','No tienes permiso para gestionar personal.','!');const button=$('#employeeForm').querySelector('button[type="submit"]');button.disabled=true;const payload={action:'employee.save',id:$('#employeeId').value?Number($('#employeeId').value):null,employee_code:$('#employeeCode').value.trim(),full_name:$('#employeeName').value.trim(),job_title:$('#employeeJobTitle').value.trim(),branch_id:$('#employeeBranch').value||null,user_id:$('#employeeUser').value||null,phone:$('#employeePhone').value.trim(),email:$('#employeeEmail').value.trim(),employment_type:$('#employeeType').value,hire_date:$('#employeeHireDate').value||null,hourly_rate:$('#employeeHourlyRate').value,status:$('#employeeStatus').value,notes:$('#employeeNotes').value.trim()};try{const r=await apiRequest('api/workforce.php',{method:'POST',body:JSON.stringify(payload)});resetEmployeeForm();await refreshEmployees();showToast('Personal guardado',r.message);}catch(error){showToast('No se pudo guardar el empleado',error.message,'!');}finally{button.disabled=false;} }
  async function handleEmployeeListAction(event) { const edit=event.target.closest('[data-employee-edit]');const status=event.target.closest('[data-employee-status]');if(edit){editEmployee(Number(edit.dataset.employeeEdit));return;}if(status){const next=status.dataset.nextStatus;if(!confirm(`¿Deseas ${next==='inactive'?'desactivar':'activar'} esta ficha de personal?`))return;try{const r=await apiRequest('api/workforce.php',{method:'POST',body:JSON.stringify({action:'employee.status',id:Number(status.dataset.employeeStatus),status:next})});await refreshEmployees();showToast('Personal actualizado',r.message);}catch(error){showToast('No se pudo cambiar el estado',error.message,'!');}} }
  function renderShifts(summary={}) {
    const shifts=state.workforceData.shifts||[];const all=shifts.filter(s=>s.status!=='cancelled');const scheduled=shifts.filter(s=>s.status==='scheduled').length;const running=shifts.filter(s=>s.status==='in_progress').length;const completed=shifts.filter(s=>s.status==='completed').length;
    if($('#shiftSummary'))$('#shiftSummary').innerHTML=`<article class="white-card report-kpi"><small>Turnos programados</small><strong>${scheduled}</strong><span>Esperando inicio</span></article><article class="white-card report-kpi"><small>En curso</small><strong>${running}</strong><span>Entrada registrada</span></article><article class="white-card report-kpi"><small>Completados</small><strong>${completed}</strong><span>Salida registrada</span></article><article class="white-card report-kpi"><small>Turnos del día</small><strong>${Number(summary.today||0)}</strong><span>En el rango consultado</span></article>`;
    if($('#shiftCount'))$('#shiftCount').textContent=`${shifts.length} turnos`;
    if(!$('#shiftList'))return;
    $('#shiftList').innerHTML=shifts.length?shifts.map(s=>{const start=String(s.start_time||'').slice(0,5);const end=String(s.end_time||'').slice(0,5);const clockIn=s.status==='scheduled';const clockOut=s.status==='in_progress';const ownOrManaged=Boolean(s.can_clock);let actions='';if(s.status==='scheduled'&&can('shifts.manage'))actions+=`<button type="button" class="workforce-action" data-shift-edit="${Number(s.id)}">Editar</button><button type="button" class="workforce-action is-danger" data-shift-cancel="${Number(s.id)}">Cancelar</button>`;if(ownOrManaged&&clockIn&&state.workforceData.canAttendance)actions+=`<button type="button" class="workforce-action is-primary" data-shift-clock="${Number(s.id)}" data-clock-action="shift.clock_in">Registrar entrada</button>`;if(ownOrManaged&&clockOut&&state.workforceData.canAttendance)actions+=`<button type="button" class="workforce-action is-primary" data-shift-clock="${Number(s.id)}" data-clock-action="shift.clock_out">Registrar salida</button>`;const actual=s.actual_start_at?`Entrada ${escapeHTML(s.actual_start_at)}`:'';const actualEnd=s.actual_end_at?`Salida ${escapeHTML(s.actual_end_at)}`:'';return `<article class="purchasing-row workforce-row"><div class="purchasing-row-icon">▦</div><div class="purchasing-row-main"><div class="purchasing-row-title"><strong>${escapeHTML(s.employee_name)}</strong><span class="workforce-status ${escapeHTML(s.status)}">${wfStatus(s.status)}</span></div><p>${wfDate(s.shift_date)} · ${escapeHTML(start)}–${escapeHTML(end)} · ${Number(s.break_minutes)} min de descanso</p><div class="workforce-submeta"><span>⌂ ${escapeHTML(s.branch_name)}</span><span>♙ ${escapeHTML(s.job_title||'Personal')}</span><span>${escapeHTML(s.employee_code||'')}</span></div><small>${escapeHTML([actual,actualEnd,s.notes].filter(Boolean).join(' · ')||'Sin registros de asistencia todavía')}</small></div><div class="workforce-row-actions">${actions||'<span class="workforce-field-note">Sin acciones disponibles</span>'}</div></article>`;}).join(''):'<div class="workforce-blank"><strong>No hay turnos en este periodo</strong>Amplía el rango de fechas o programa un nuevo turno.</div>';
  }
  function resetShiftForm() { $('#shiftForm')?.reset();$('#shiftId').value='';$('#shiftDate').value=$('#shiftFilterFrom')?.value||wfTodayISO();$('#shiftStart').value='09:00';$('#shiftEnd').value='17:00';$('#shiftBreak').value='30';$('#shiftFormTitle').textContent='Programar turno'; }
  function editShift(id) { const s=(state.workforceData.shifts||[]).find(row=>Number(row.id)===Number(id));if(!s||s.status!=='scheduled')return;$('#shiftId').value=s.id;$('#shiftEmployee').value=String(s.employee_id);$('#shiftBranch').value=String(s.branch_id);$('#shiftDate').value=s.shift_date;$('#shiftStart').value=String(s.start_time).slice(0,5);$('#shiftEnd').value=String(s.end_time).slice(0,5);$('#shiftBreak').value=s.break_minutes;$('#shiftNotes').value=s.notes||'';$('#shiftFormTitle').textContent='Editar turno programado';$('#shiftFormCard').scrollIntoView({behavior:'smooth',block:'start'}); }
  async function saveShiftForm(event) { event.preventDefault();if(!can('shifts.manage'))return showToast('Acceso restringido','No tienes permiso para programar turnos.','!');const button=$('#shiftForm').querySelector('button[type="submit"]');button.disabled=true;const payload={action:'shift.save',id:$('#shiftId').value?Number($('#shiftId').value):null,employee_id:Number($('#shiftEmployee').value),branch_id:Number($('#shiftBranch').value),shift_date:$('#shiftDate').value,start_time:$('#shiftStart').value,end_time:$('#shiftEnd').value,break_minutes:Number($('#shiftBreak').value),notes:$('#shiftNotes').value.trim()};try{const r=await apiRequest('api/workforce.php',{method:'POST',body:JSON.stringify(payload)});resetShiftForm();await refreshShifts();showToast('Turno guardado',r.message);}catch(error){showToast('No se pudo guardar el turno',error.message,'!');}finally{button.disabled=false;} }
  async function handleShiftListAction(event) { const edit=event.target.closest('[data-shift-edit]');const cancel=event.target.closest('[data-shift-cancel]');const clock=event.target.closest('[data-shift-clock]');try{if(edit){editShift(Number(edit.dataset.shiftEdit));return;}if(cancel){if(!confirm('¿Cancelar este turno programado?'))return;const r=await apiRequest('api/workforce.php',{method:'POST',body:JSON.stringify({action:'shift.status',id:Number(cancel.dataset.shiftCancel),status:'cancelled'})});await refreshShifts();showToast('Turno cancelado',r.message);return;}if(clock){const action=clock.dataset.clockAction;const label=action==='shift.clock_in'?'Registrar tu entrada':'Registrar tu salida';if(!confirm(`${label}? Se guardará la fecha y hora del servidor.`))return;clock.disabled=true;const r=await apiRequest('api/workforce.php',{method:'POST',body:JSON.stringify({action,id:Number(clock.dataset.shiftClock)})});await refreshShifts();showToast('Asistencia registrada',r.message);}}catch(error){showToast('No se pudo actualizar el turno',error.message,'!');}finally{if(clock)clock.disabled=false;} }

  /* =======================================================
     PASO 15 — NOTIFICACIONES, SOPORTE E INTEGRACIONES
  ======================================================= */
  const comm = () => state.communications;
  const commStatus = value => ({open:'Abierto',in_progress:'En proceso',waiting_user:'Esperando respuesta',resolved:'Resuelto',closed:'Cerrado'})[value] || value;
  const commPriority = value => ({low:'Baja',normal:'Normal',high:'Alta',urgent:'Urgente'})[value] || value;
  const commCategory = value => ({technical:'Técnico',orders:'Pedidos y ventas',inventory:'Inventario',billing:'Pagos y comprobantes',access:'Acceso y permisos',other:'Otro'})[value] || value;
  function initCommunications() {
    $('#refreshNotificationsButton')?.addEventListener('click', () => refreshCommunicationsNotifications().catch(e => showToast('Error',e.message,'!')));
    $('#markAllNotificationsReadButton')?.addEventListener('click', markAllNotificationsRead);
    $('#notificationList')?.addEventListener('click', handleNotificationClick);
    $('#broadcastNotificationForm')?.addEventListener('submit', publishBroadcastNotification);
    $('#supportCreateForm')?.addEventListener('submit', createSupportTicket);
    $('#refreshSupportButton')?.addEventListener('click', () => refreshSupportTickets().catch(e=>showToast('Error',e.message,'!')));
    $('#refreshTicketsButton')?.addEventListener('click', () => refreshSupportTickets().catch(e=>showToast('Error',e.message,'!')));
    $('#supportStatusFilters')?.addEventListener('click', event => { const button=event.target.closest('[data-support-filter]'); if(!button)return; $$('#supportStatusFilters .filter-chip').forEach(b=>b.classList.toggle('is-selected',b===button)); comm().supportFilter=button.dataset.supportFilter; refreshSupportTickets().catch(e=>showToast('Error',e.message,'!')); });
    $('#supportTicketList')?.addEventListener('click', handleSupportTicketListClick);
    $('#supportReplyForm')?.addEventListener('submit', replySupportTicket);
    $('#supportResolveButton')?.addEventListener('click', () => updateSupportStatus('resolved'));
    $('#supportCloseButton')?.addEventListener('click', () => updateSupportStatus('closed'));
    $('#supportStartButton')?.addEventListener('click', () => updateSupportStatus('in_progress'));
    $('#supportAssignUser')?.addEventListener('change', assignSupportTicket);
    $('#refreshIntegrationsButton')?.addEventListener('click', () => refreshIntegrations().catch(e=>showToast('Error',e.message,'!')));
    $('#integrationList')?.addEventListener('submit', saveIntegrationForm);
    $('#integrationList')?.addEventListener('click', handleIntegrationListClick);
    $('#apiTokenForm')?.addEventListener('submit', createApiToken);
    $('#apiTokenList')?.addEventListener('click', handleApiTokenClick);
    $('#copyApiTokenButton')?.addEventListener('click', copyNewApiToken);
    $('#hideApiTokenButton')?.addEventListener('click', () => { $('#newApiTokenReveal')?.classList.add('is-hidden'); if($('#newApiTokenValue'))$('#newApiTokenValue').value=''; });
  }
  async function refreshCommunicationsNotifications(notifyNew = false) {
    const result=await apiRequest('api/communications.php?view=notifications',{method:'GET'});
    comm().notifications=result.notifications||[];
    const summary=$('#notificationsSummary'); if(summary)summary.innerHTML=`<article class="white-card report-kpi"><small>Sin leer</small><strong>${Number(result.unread||0)}</strong><span>De ${comm().notifications.length} avisos recientes</span></article>`;
    if($('#notificationCount'))$('#notificationCount').textContent=`${comm().notifications.length} aviso(s)`;
    if($('#broadcastNotificationCard'))$('#broadcastNotificationCard').classList.toggle('is-hidden',!result.can_manage);
    const unread = Number(result.unread || 0);
    const previousUnread = state.lastUnreadNotificationCount;
    state.lastUnreadNotificationCount = unread;
    const badge=$('#notificationsButton .notification-badge');if(badge){badge.textContent=unread>99?'99+':String(unread);badge.classList.toggle('is-hidden',unread===0 || !state.profilePreferences.show_notification_badge);}
    if (notifyNew && previousUnread !== null && unread > previousUnread && (state.profilePreferences.show_notification_badge || state.profilePreferences.desktop_notifications || state.profilePreferences.sound_notifications)) {
      const noticeMessage = unread - previousUnread === 1 ? 'Tienes una notificación nueva.' : `Tienes ${unread - previousUnread} notificaciones nuevas.`;
      showToast('Nueva notificación', noticeMessage, 'i');
      playNotificationSound();
      sendDesktopNotification('DESEGO · Nueva notificación', noticeMessage);
    }
    renderNotifications();
  }
  function renderNotifications() {
    const root=$('#notificationList');if(!root)return;const rows=comm().notifications||[];
    if(!rows.length){root.innerHTML='<div class="communications-empty"><strong>Todo al día</strong><span>No tienes notificaciones todavía.</span></div>';return;}
    root.innerHTML=rows.map(n=>`<article class="communication-row notification-row ${n.is_read?'is-read':'is-unread'}"><span class="communication-kind kind-${escapeHTML(n.category)}">${n.category==='success'?'✓':n.category==='warning'?'!':n.category==='error'?'×':'i'}</span><div class="communication-row-main"><div class="communication-row-title"><strong>${escapeHTML(n.title)}</strong><span class="communication-meta">${escapeHTML(n.created_at||'')}</span></div><p>${escapeHTML(n.message)}</p><small>${n.author_name?'Publicado por '+escapeHTML(n.author_name):'Aviso del sistema'}${n.is_read?' · Leído':' · Sin leer'}</small></div><div class="communication-row-actions">${!n.is_read?`<button type="button" class="workforce-action is-primary" data-notification-read="${Number(n.id)}">Marcar leído</button>`:''}${n.target_view?`<button type="button" class="workforce-action" data-notification-go="${escapeHTML(n.target_view)}" data-notification-id="${Number(n.id)}">Abrir</button>`:''}</div></article>`).join('');
  }
  async function handleNotificationClick(event) {
    const read=event.target.closest('[data-notification-read]');const go=event.target.closest('[data-notification-go]');
    try{if(read){await apiRequest('api/communications.php',{method:'POST',body:JSON.stringify({action:'notification.read',id:Number(read.dataset.notificationRead)})});await refreshCommunicationsNotifications();showToast('Notificación','Marcada como leída.');return;}if(go){const id=Number(go.dataset.notificationId);if(id)await apiRequest('api/communications.php',{method:'POST',body:JSON.stringify({action:'notification.read',id})});await refreshCommunicationsNotifications();const target=go.dataset.notificationGo;if(['home','orders','menu','reservations','profile','customers','loyalty','reviews','billing','reports','analytics','audit','backups','kitchen','delivery','suppliers','purchasing','branches','employees','shifts','notifications','support','integrations'].includes(target))switchView(target);}}
    catch(error){showToast('No se pudo actualizar',error.message,'!');}
  }
  async function markAllNotificationsRead(){try{const r=await apiRequest('api/communications.php',{method:'POST',body:JSON.stringify({action:'notification.read_all'})});await refreshCommunicationsNotifications();showToast('Notificaciones',r.message);}catch(error){showToast('No se pudieron marcar',error.message,'!');}}
  async function publishBroadcastNotification(event){event.preventDefault();if(!can('notifications.manage'))return showToast('Acceso restringido','No puedes publicar avisos.','!');const button=$('#broadcastNotificationForm button[type="submit"]');button.disabled=true;try{const r=await apiRequest('api/communications.php',{method:'POST',body:JSON.stringify({action:'notification.broadcast',title:$('#broadcastTitle').value.trim(),message:$('#broadcastMessage').value.trim(),category:$('#broadcastCategory').value})});$('#broadcastNotificationForm').reset();await refreshCommunicationsNotifications();showToast('Aviso publicado',r.message);}catch(error){showToast('No se pudo publicar',error.message,'!');}finally{button.disabled=false;}}
  async function refreshSupportTickets(){const filter=comm().supportFilter||'all';const r=await apiRequest(`api/communications.php?view=tickets&status=${encodeURIComponent(filter)}`,{method:'GET'});comm().tickets=r.tickets||[];comm().canManageSupport=Boolean(r.can_manage);comm().supportUsers=r.users||[];renderSupportTickets();if(comm().ticketId){const remains=comm().tickets.some(t=>Number(t.id)===Number(comm().ticketId));if(remains)await openSupportTicket(comm().ticketId);else{comm().ticketId=null;$('#supportThreadCard')?.classList.add('is-hidden');}}}
  function renderSupportTickets(){const root=$('#supportTicketList');if(!root)return;const rows=comm().tickets||[];if($('#supportManageHint'))$('#supportManageHint').classList.toggle('is-hidden',!comm().canManageSupport);if(!rows.length){root.innerHTML='<div class="communications-empty"><strong>No hay tickets en este filtro</strong><span>Crea una solicitud con el formulario para iniciar el seguimiento.</span></div>';return;}root.innerHTML=rows.map(t=>`<article class="communication-row ticket-row"><div class="communication-row-main"><div class="communication-row-title"><strong>${escapeHTML(t.ticket_number||('Ticket #'+t.id))} · ${escapeHTML(t.subject)}</strong><span class="ticket-status status-${escapeHTML(t.status)}">${commStatus(t.status)}</span></div><p>${escapeHTML(commCategory(t.category))} · Prioridad ${escapeHTML(commPriority(t.priority))}</p><small>${comm().canManageSupport?'Creado por '+escapeHTML(t.creator_name):'Actualizado'} · ${escapeHTML(t.updated_at||t.created_at||'')} · ${Number(t.message_count)} mensaje(s)${t.assignee_name?' · Asignado: '+escapeHTML(t.assignee_name):''}</small></div><div class="communication-row-actions"><button type="button" class="workforce-action is-primary" data-support-open="${Number(t.id)}">Abrir</button>${comm().canManageSupport&&t.status!=='closed'?`<button type="button" class="workforce-action" data-support-quick-close="${Number(t.id)}">Cerrar</button>`:''}</div></article>`).join('');}
  async function handleSupportTicketListClick(event){const open=event.target.closest('[data-support-open]');const close=event.target.closest('[data-support-quick-close]');try{if(open){await openSupportTicket(Number(open.dataset.supportOpen));return;}if(close){if(!confirm('¿Cerrar este ticket?'))return;await apiRequest('api/communications.php',{method:'POST',body:JSON.stringify({action:'support.status',id:Number(close.dataset.supportQuickClose),status:'closed'})});await refreshSupportTickets();showToast('Soporte','Ticket cerrado.');}}catch(error){showToast('No se pudo abrir el ticket',error.message,'!');}}
  async function openSupportTicket(id){const r=await apiRequest(`api/communications.php?view=thread&id=${Number(id)}`,{method:'GET'});comm().ticketId=Number(id);comm().ticket=r.ticket;$('#supportThreadCard')?.classList.remove('is-hidden');$('#supportThreadId').value=String(id);$('#supportThreadTitle').textContent=`${r.ticket.ticket_number||'Ticket'} · ${r.ticket.subject}`;$('#supportThreadMeta').textContent=`${commCategory(r.ticket.category)} · Prioridad ${commPriority(r.ticket.priority)} · ${commStatus(r.ticket.status)}`;$('#supportThreadMessages').innerHTML=(r.messages||[]).length?r.messages.map(m=>`<article class="support-message ${m.is_internal?'is-internal':''}"><div><strong>${escapeHTML(m.author_name)}</strong><time>${escapeHTML(m.created_at||'')}</time>${m.is_internal?'<span class="internal-note-label">Nota interna</span>':''}</div><p>${escapeHTML(m.message)}</p></article>`).join(''):'<div class="communications-empty"><span>Aún no hay mensajes.</span></div>';$('#supportInternalNoteWrap')?.classList.toggle('is-hidden',!r.can_manage);$('#supportResolveButton')?.classList.toggle('is-hidden',!r.can_manage||['resolved','closed'].includes(r.ticket.status));$('#supportCloseButton')?.classList.toggle('is-hidden',!r.can_manage||r.ticket.status==='closed');$('#supportStartButton')?.classList.toggle('is-hidden',!r.can_manage||r.ticket.status==='in_progress'||r.ticket.status==='closed');$('#supportReplyForm')?.classList.toggle('is-hidden',!r.can_reply||r.ticket.status==='closed');$('#supportAssignWrap')?.classList.toggle('is-hidden',!r.can_manage);if(r.can_manage){const select=$('#supportAssignUser');select.innerHTML='<option value="">Sin asignar</option>'+(r.users||[]).map(u=>`<option value="${Number(u.id)}" ${Number(u.id)===Number(r.ticket.assigned_to)?'selected':''}>${escapeHTML(u.name)} · ${escapeHTML(u.email)}</option>`).join('');}$('#supportThreadCard').scrollIntoView({behavior:'smooth',block:'start'});}
  async function createSupportTicket(event){event.preventDefault();const button=$('#supportCreateForm button[type="submit"]');button.disabled=true;try{const r=await apiRequest('api/communications.php',{method:'POST',body:JSON.stringify({action:'support.create',subject:$('#supportSubject').value.trim(),category:$('#supportCategory').value,priority:$('#supportPriority').value,message:$('#supportMessage').value.trim()})});$('#supportCreateForm').reset();comm().supportFilter='all';$$('#supportStatusFilters [data-support-filter]').forEach(b=>b.classList.toggle('is-selected',b.dataset.supportFilter==='all'));await refreshSupportTickets();await openSupportTicket(Number(r.ticket_id));showToast('Ticket creado',`${r.ticket_number}: ${r.message}`);}catch(error){showToast('No se pudo crear el ticket',error.message,'!');}finally{button.disabled=false;}}
  async function replySupportTicket(event){event.preventDefault();const id=Number($('#supportThreadId').value);const message=$('#supportReplyMessage').value.trim();if(!id||!message)return;const button=$('#supportReplyForm button[type="submit"]');button.disabled=true;try{const r=await apiRequest('api/communications.php',{method:'POST',body:JSON.stringify({action:'support.reply',ticket_id:id,message,is_internal:Boolean($('#supportInternalNote')?.checked)})});$('#supportReplyMessage').value='';if($('#supportInternalNote'))$('#supportInternalNote').checked=false;await refreshSupportTickets();showToast('Respuesta guardada',r.message);}catch(error){showToast('No se pudo responder',error.message,'!');}finally{button.disabled=false;}}
  async function updateSupportStatus(status){const id=comm().ticketId;if(!id)return;try{const r=await apiRequest('api/communications.php',{method:'POST',body:JSON.stringify({action:'support.status',id,status})});await refreshSupportTickets();showToast('Estado actualizado',r.message);}catch(error){showToast('No se pudo actualizar',error.message,'!');}}
  async function assignSupportTicket(){if(!comm().ticketId||!can('support.manage'))return;try{const r=await apiRequest('api/communications.php',{method:'POST',body:JSON.stringify({action:'support.assign',id:comm().ticketId,assigned_to:$('#supportAssignUser').value||null})});await refreshSupportTickets();showToast('Asignación actualizada',r.message);}catch(error){showToast('No se pudo asignar',error.message,'!');}}
  async function refreshIntegrations(){const r=await apiRequest('api/communications.php?view=integrations',{method:'GET'});comm().integrations=r.integrations||[];comm().tokens=r.tokens||[];comm().canManageIntegrations=Boolean(r.can_manage);comm().canManageTokens=Boolean(r.can_manage_tokens);renderIntegrations();renderApiTokens();renderIntegrationEvents(r.events||[]);$('#apiTokenForm')?.classList.toggle('is-hidden',!comm().canManageTokens);}
  function renderIntegrations(){const root=$('#integrationList');if(!root)return;root.innerHTML=(comm().integrations||[]).map(i=>`<article class="white-card integration-card"><div class="card-heading"><div><span class="section-eyebrow">${escapeHTML(i.category.toUpperCase())}</span><h2>${escapeHTML(i.name)}</h2><p>${escapeHTML(i.description||'Sin descripción')}</p></div><span class="integration-badge ${i.is_enabled?'is-enabled':'is-disabled'}">${i.is_enabled?'Activada':'Desactivada'}</span></div><form class="integration-form" data-integration-form="${escapeHTML(i.integration_code)}"><input type="hidden" name="integration_code" value="${escapeHTML(i.integration_code)}"><div class="form-field"><label>Nombre</label><input name="name" maxlength="120" value="${escapeHTML(i.name)}" required ${comm().canManageIntegrations?'':'disabled'}></div><div class="form-field"><label>Categoría</label><select name="category" ${comm().canManageIntegrations?'':'disabled'}>${['email','messaging','maps','payments','webhook','api','other'].map(c=>`<option value="${c}" ${i.category===c?'selected':''}>${({email:'Correo',messaging:'Mensajería',maps:'Mapas',payments:'Pagos',webhook:'Webhook',api:'API',other:'Otro'})[c]}</option>`).join('')}</select></div><div class="form-field communications-wide"><label>Endpoint HTTPS (opcional)</label><input name="endpoint_url" type="url" maxlength="500" placeholder="https://proveedor.example/api" value="${escapeHTML(i.endpoint_url||'')}" ${comm().canManageIntegrations?'':'disabled'}></div><div class="form-field"><label>Variable de entorno que contiene el secreto</label><input name="environment_key" maxlength="100" placeholder="DESEGO_PROVEEDOR_SECRET" value="${escapeHTML(i.environment_key||'')}" ${comm().canManageIntegrations?'':'disabled'}></div><div class="form-field"><label class="communications-check"><input type="checkbox" name="is_enabled" value="1" ${i.is_enabled?'checked':''} ${comm().canManageIntegrations?'':'disabled'}> Activar integración</label><small class="form-field-note">Variable presente: <b>${i.environment_configured?'Sí':'No'}</b></small></div><div class="integration-status-line"><span class="ticket-status status-${i.last_check_status==='configured'?'resolved':i.last_check_status==='invalid'?'closed':'open'}">${escapeHTML(i.last_check_status||'not_checked')}</span><small>${escapeHTML(i.last_check_message||'Todavía no comprobada')}</small></div><div class="heading-actions integration-actions">${comm().canManageIntegrations?'<button type="submit" class="cyan-button">Guardar configuración</button>':''}<button type="button" class="secondary-button" data-integration-check="${escapeHTML(i.integration_code)}" ${comm().canManageIntegrations?'':'disabled'}>Comprobar</button></div></form></article>`).join('')||'<div class="communications-empty">No hay integraciones en el registro.</div>';}
  async function saveIntegrationForm(event){const form=event.target.closest('[data-integration-form]');if(!form||event.target!==form)return;event.preventDefault();if(!can('integrations.manage'))return showToast('Acceso restringido','No puedes modificar integraciones.','!');const fd=new FormData(form);try{const r=await apiRequest('api/communications.php',{method:'POST',body:JSON.stringify({action:'integration.save',integration_code:fd.get('integration_code'),name:fd.get('name'),category:fd.get('category'),endpoint_url:fd.get('endpoint_url'),environment_key:fd.get('environment_key'),is_enabled:fd.get('is_enabled')==='1'})});await refreshIntegrations();showToast('Integración guardada',r.message);}catch(error){showToast('No se pudo guardar',error.message,'!');}}
  async function handleIntegrationListClick(event){const button=event.target.closest('[data-integration-check]');if(!button)return;button.disabled=true;try{const r=await apiRequest('api/communications.php',{method:'POST',body:JSON.stringify({action:'integration.check',integration_code:button.dataset.integrationCheck})});await refreshIntegrations();showToast(r.configured?'Configuración correcta':'Revisa la configuración',r.message,r.configured?'✓':'!');}catch(error){showToast('No se pudo comprobar',error.message,'!');}finally{button.disabled=false;}}
  function renderIntegrationEvents(events){const root=$('#integrationEventsList');if(!root)return;root.innerHTML=events.length?events.map(e=>`<article class="communication-row"><span class="communication-kind kind-${e.status==='success'?'success':e.status==='error'?'error':'warning'}">${e.status==='success'?'✓':'!'}</span><div class="communication-row-main"><div class="communication-row-title"><strong>${escapeHTML(e.integration_code)} · ${escapeHTML(e.event_code)}</strong><span class="communication-meta">${escapeHTML(e.created_at||'')}</span></div><p>${escapeHTML(e.message)}</p></div></article>`).join(''):'<div class="communications-empty">Todavía no hay comprobaciones registradas.</div>';}
  async function createApiToken(event){event.preventDefault();if(!can('api_tokens.manage'))return showToast('Acceso restringido','No puedes crear tokens API.','!');const scopes=$$('[name="apiScope"]:checked').map(n=>n.value);const button=$('#apiTokenForm button[type="submit"]');button.disabled=true;try{const r=await apiRequest('api/communications.php',{method:'POST',body:JSON.stringify({action:'api_token.create',name:$('#apiTokenName').value.trim(),expires_days:Number($('#apiTokenExpiry').value),scopes})});$('#newApiTokenValue').value=r.token;$('#newApiTokenReveal').classList.remove('is-hidden');$('#newApiTokenReveal').scrollIntoView({behavior:'smooth',block:'center'});$('#apiTokenName').value='';await refreshIntegrations();showToast('Token generado','Cópialo ahora; no volverá a mostrarse.','✓');}catch(error){showToast('No se pudo crear el token',error.message,'!');}finally{button.disabled=false;}}
  function renderApiTokens(){const root=$('#apiTokenList');if(!root)return;if(!comm().canManageTokens){root.innerHTML='<div class="communications-empty">No tienes permiso para administrar tokens.</div>';return;}const rows=comm().tokens||[];root.innerHTML=rows.length?rows.map(t=>`<article class="communication-row api-token-row"><span class="communication-kind kind-info">⌘</span><div class="communication-row-main"><div class="communication-row-title"><strong>${escapeHTML(t.name)}</strong><span class="ticket-status status-${t.status==='active'?'resolved':'closed'}">${t.status==='active'?'Activo':'Revocado'}</span></div><p><code>${escapeHTML(t.token_prefix)}…</code> · Alcances: ${escapeHTML((t.scopes||[]).join(', '))}</p><small>Caduca: ${escapeHTML(t.expires_at||'Sin fecha')} · Último uso: ${escapeHTML(t.last_used_at||'Nunca')}</small></div><div class="communication-row-actions">${t.status==='active'?`<button type="button" class="workforce-action is-danger" data-token-revoke="${Number(t.id)}">Revocar</button>`:''}</div></article>`).join(''):'<div class="communications-empty">No hay tokens API creados.</div>';}
  async function handleApiTokenClick(event){const button=event.target.closest('[data-token-revoke]');if(!button)return;if(!confirm('¿Revocar este token? Las aplicaciones que lo usan dejarán de acceder inmediatamente.'))return;button.disabled=true;try{const r=await apiRequest('api/communications.php',{method:'POST',body:JSON.stringify({action:'api_token.revoke',id:Number(button.dataset.tokenRevoke)})});await refreshIntegrations();showToast('Token revocado',r.message);}catch(error){showToast('No se pudo revocar',error.message,'!');}finally{button.disabled=false;}}
  async function copyNewApiToken(){const value=$('#newApiTokenValue').value;if(!value)return;try{await navigator.clipboard.writeText(value);showToast('Token copiado','Guárdalo en un lugar seguro.');}catch(_){$('#newApiTokenValue').focus();$('#newApiTokenValue').select();showToast('Copia manual','Selecciona el texto y usa Ctrl+C.','!');}}

  async function init() {
    const authRoot = document.getElementById('authScreen');
    if (!authRoot || authRoot.dataset.desegoBound === '1') return;
    authRoot.dataset.desegoBound = '1';
    // Elimina claves de autenticación antiguas de la demo que almacenaba usuarios en localStorage.
    safeRemove(KEY.session); safeRemove(KEY.user); safeRemove(KEY.registered); safeRemove(KEY.remember);
    loadArrays();
    initAuth();
    initNavigation();
    initOrderFilters();
    initOperationsActions();
    initMenuFilters();
    initReservationActions();
    initCustomerActions();
    initLoyaltyActions();
    initReviewActions();
    initBillingReports();
    initAdminTools();
    initPurchasing();
    initWorkforce();
    initCommunications();
    initReportDates();
    initDashboardActions();
    initThemeSelector();
    initModal();
    updateToday();
    await checkServerSession();
    if (!window.__desegoNotificationPollerBound) {
      window.__desegoNotificationPollerBound = true;
      window.setInterval(() => {
        if (!state.currentUser || !(state.profilePreferences.show_notification_badge || state.profilePreferences.desktop_notifications || state.profilePreferences.sound_notifications)) return;
        if (!(can('notifications.view') || can('notifications.manage') || can('support.create') || can('support.manage'))) return;
        refreshCommunicationsNotifications(true).catch(() => {});
      }, 60000);
    }
  }
  window.desegoInitAdmin = init;
  window.desegoSwitchAdminView = switchView;
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
