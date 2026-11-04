<?php

use App\Http\Controllers\Desego\PageController;
use App\Http\Controllers\Restaurant\AdminController;
use App\Http\Controllers\Restaurant\AdminReservationController;
use App\Http\Controllers\Restaurant\ChatbotController;
use App\Http\Controllers\Restaurant\ChatbotHistoryController;
use App\Http\Controllers\Restaurant\CustomerController;
use App\Http\Controllers\Restaurant\DiningController;
use App\Http\Controllers\Restaurant\DiningPaymentController;
use App\Http\Controllers\Restaurant\DocumentLookupController;
use App\Http\Controllers\Restaurant\GoogleAuthController;
use App\Http\Controllers\Restaurant\MobileController;
use App\Http\Controllers\Restaurant\OrderPaymentController;
use App\Http\Controllers\Restaurant\ReservationApiController;
use App\Http\Controllers\Restaurant\ReservationPaymentController;
use App\Http\Controllers\Restaurant\SessionController;
use App\Http\Controllers\Restaurant\StoreController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireRestaurantStaff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', [StoreController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/productos/{productId}/resenas', [StoreController::class, 'productReviews'])->whereNumber('productId')->middleware('throttle:120,1');

Route::middleware(HandleInertiaRequests::class)->group(function (): void {
    Route::get('/', [StoreController::class, 'index'])->name('store.home');
    Route::get('/carta', [StoreController::class, 'index'])->name('store.menu');
    Route::get('/nuestra-esencia', [StoreController::class, 'index'])->name('store.essence');
    Route::get('/reservar', [StoreController::class, 'index'])->name('store.reservations');
    Route::get('/ubicacion', [StoreController::class, 'index'])->name('store.location');
    Route::get('/mesa/{token}', [DiningController::class, 'show'])->name('dining.table');
    Route::get('/mesa-pago', [DiningController::class, 'payment'])->name('dining.payment.start');
    Route::get('/ingresar', [SessionController::class, 'create'])->name('restaurant.login');
    Route::get('/registro', [SessionController::class, 'registration']);
    Route::post('/ingresar', [SessionController::class, 'login'])->middleware('throttle:restaurant-auth');
    Route::post('/registro', [SessionController::class, 'register'])->middleware('throttle:restaurant-auth');
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
    Route::get('/recuperar-contrasena', [SessionController::class, 'forgotPassword'])->name('password.request');
    Route::post('/recuperar-contrasena', [SessionController::class, 'sendResetLink'])->middleware('throttle:restaurant-password-reset');
    Route::get('/restablecer-contrasena', [SessionController::class, 'resetPasswordForm'])->name('password.reset');
    Route::post('/restablecer-contrasena', [SessionController::class, 'resetPassword'])->middleware('throttle:restaurant-password-reset');
    Route::post('/salir', [SessionController::class, 'logout']);
    Route::post('/pedido', [StoreController::class, 'checkout'])->middleware('throttle:10,1');
    Route::post('/carrito/cotizar', [StoreController::class, 'quote'])->middleware('throttle:60,1');
    Route::post('/reservar', [StoreController::class, 'reserve'])->middleware('throttle:10,1');
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.home');
    Route::post('/admin', [SessionController::class, 'staffLogin'])->middleware('throttle:restaurant-auth')->name('staff.authenticate');
    Route::get('/panel/ingresar', fn () => redirect()->route('admin.home'))->name('staff.login');
    Route::post('/panel/ingresar', [SessionController::class, 'staffLogin'])->middleware('throttle:restaurant-auth');
    Route::get('/cuenta', [CustomerController::class, 'index']);
    Route::post('/cuenta/perfil', [CustomerController::class, 'profile']);
    Route::post('/cuenta/pedidos/{id}/pagar', [OrderPaymentController::class, 'start'])->whereNumber('id')->middleware('throttle:5,1')->name('customer.payment.start');
    Route::post('/cuenta/password', [CustomerController::class, 'password']);
    Route::post('/cuenta/direcciones', [CustomerController::class, 'address']);
    Route::post('/cuenta/direcciones/{id}/predeterminada', [CustomerController::class, 'defaultAddress']);
    Route::delete('/cuenta/direcciones/{id}', [CustomerController::class, 'deleteAddress']);
    Route::post('/cuenta/fidelizacion/canjear', [CustomerController::class, 'redeemReward']);
    Route::post('/cuenta/pedidos/{id}/cancelar', [CustomerController::class, 'cancelOrder']);
    Route::post('/cuenta/pedidos/{id}/modificar', [CustomerController::class, 'modifyOrder']);
    Route::get('/cuenta/pedidos/{id}/comprobante', [CustomerController::class, 'receipt'])->name('customer.order.receipt');
    Route::post('/cuenta/reservas/{id}/cancelar', [CustomerController::class, 'cancelReservation']);
    Route::post('/cuenta/reservas/{id}/reprogramar', [CustomerController::class, 'rescheduleReservation']);
    Route::post('/cuenta/preferencias', [CustomerController::class, 'preferences']);
    Route::post('/cuenta/resenas', [CustomerController::class, 'submitReview']);
    Route::post('/cuenta/soporte', [CustomerController::class, 'createSupportTicket']);
    Route::post('/cuenta/soporte/{id}/mensaje', [CustomerController::class, 'replySupportTicket']);
    Route::post('/cuenta/favoritos/{productId}/toggle', [CustomerController::class, 'toggleFavorite']);
    Route::post('/cuenta/verificar/enviar', [CustomerController::class, 'sendVerification'])->middleware('throttle:10,1');
    Route::post('/cuenta/verificar/confirmar', [CustomerController::class, 'confirmVerification'])->middleware('throttle:15,1');
    Route::post('/cuenta/desactivar', [CustomerController::class, 'deactivate']);
    Route::post('/cuenta/eliminar', [CustomerController::class, 'deleteAccount']);
    Route::middleware(RequireRestaurantStaff::class)->group(function (): void {
        Route::get('/admin/herramientas', [AdminController::class, 'tools'])->name('admin.tools');
        Route::get('/admin/chatbot/{conversation}', [ChatbotHistoryController::class, 'show'])->name('admin.chatbot.show');
        Route::post('/admin/chatbot/{conversation}/leido', [ChatbotHistoryController::class, 'markRead'])->name('admin.chatbot.read');
        Route::post('/admin/chatbot/{conversation}/cerrar', [ChatbotHistoryController::class, 'close'])->name('admin.chatbot.close');
        Route::post('/admin/chatbot/{conversation}/reabrir', [ChatbotHistoryController::class, 'reopen'])->name('admin.chatbot.reopen');
        Route::post('/admin/chatbot/{conversation}/derivar', [ChatbotHistoryController::class, 'handoff'])->name('admin.chatbot.handoff');
        Route::post('/admin/chatbot/{conversation}/tomar-control', [ChatbotHistoryController::class, 'takeOver'])->name('admin.chatbot.take-over');
        Route::post('/admin/chatbot/{conversation}/devolver-ia', [ChatbotHistoryController::class, 'returnToAi'])->name('admin.chatbot.return-to-ai');
        Route::post('/admin/chatbot/{conversation}/responder', [ChatbotHistoryController::class, 'reply'])->name('admin.chatbot.reply');
        Route::get('/admin/seccion/{section}', [AdminController::class, 'index']);
        Route::post('/admin/acciones/{module}', [AdminController::class, 'action']);
        Route::post('/admin/subir-imagen', [AdminController::class, 'uploadImage']);
    });
});
Route::post('/chatbot/mensaje', [ChatbotController::class, 'message'])->middleware('throttle:20,1')->name('chatbot.message');
Route::get('/chatbot/{conversation}/actualizaciones', [ChatbotController::class, 'updates'])->middleware('throttle:120,1')->name('chatbot.updates');
Route::post('/chatbot/{conversation}/leido', [ChatbotController::class, 'markRead'])->middleware('throttle:120,1')->name('chatbot.read');
Route::get('/reservas/disponibilidad', [StoreController::class, 'availability'])->middleware('throttle:120,1')->name('reservations.availability');

Route::prefix('api/reservas')->group(function (): void {
    Route::get('/disponibilidad', [ReservationApiController::class, 'disponibilidad'])->middleware('throttle:120,1');
    Route::post('/cotizacion', [ReservationApiController::class, 'cotizacion'])->middleware('throttle:60,1');
    Route::post('/verificar-cliente', [ReservationApiController::class, 'verificarCliente'])->middleware('throttle:10,1');
    Route::post('/validar-otp', [ReservationApiController::class, 'validarOtp'])->middleware('throttle:20,1');
    Route::post('/crear-bloqueo', [ReservationApiController::class, 'crearBloqueo'])->middleware('throttle:15,1');
    Route::post('/iniciar-pago', [ReservationApiController::class, 'iniciarPago'])->middleware('throttle:15,1');
    Route::post('/pagos/niubiz', [ReservationPaymentController::class, 'start'])->middleware('throttle:10,1');
    Route::post('/pagos/sin-anticipo', [ReservationPaymentController::class, 'confirmWithoutAdvance'])->middleware('throttle:15,1');
    Route::get('/{codigo}', [ReservationApiController::class, 'detalle'])->middleware('throttle:60,1');
    Route::post('/{codigo}/cancelar', [ReservationApiController::class, 'cancelar'])->middleware('throttle:10,1');
});

Route::prefix('api/admin')->group(function (): void {
    Route::get('/reservas', [AdminReservationController::class, 'index']);
    Route::get('/reservas/calendario', [AdminReservationController::class, 'calendario']);
    Route::post('/reservas/{id}/check-in', [AdminReservationController::class, 'checkIn']);
    Route::post('/reservas/{id}/asignar-mesa', [AdminReservationController::class, 'asignarMesa']);
    Route::post('/reservas/{id}/sentar', [AdminReservationController::class, 'sentar']);
    Route::post('/reservas/{id}/finalizar', [AdminReservationController::class, 'finalizar']);
    Route::post('/reservas/{id}/cancelar', [AdminReservationController::class, 'cancelar']);
    Route::post('/reservas/{id}/reembolsar', [AdminReservationController::class, 'reembolsar']);

    Route::get('/mesas', [AdminReservationController::class, 'mesas']);
    Route::post('/mesas', [AdminReservationController::class, 'crearMesa']);
    Route::patch('/mesas/{id}', [AdminReservationController::class, 'actualizarMesa']);
    Route::get('/reservas/mesas', [AdminReservationController::class, 'mesas']);
    Route::post('/reservas/mesas', [AdminReservationController::class, 'crearMesa']);
    Route::patch('/reservas/mesas/{id}', [AdminReservationController::class, 'actualizarMesa']);
    Route::get('/reservas/configuracion', [AdminReservationController::class, 'configuracion']);
    Route::put('/reservas/configuracion', [AdminReservationController::class, 'actualizarConfiguracion']);
});
Route::get('/dining/payments/{attempt}', [DiningPaymentController::class, 'checkout'])->middleware('signed')->name('dining.payment.checkout');
Route::post('/dining/payments/{attempt}/return', [DiningPaymentController::class, 'complete'])->middleware(['signed', 'throttle:10,1'])->name('dining.payment.return');
Route::get('/reservas/pagos/niubiz/{attempt}', [ReservationPaymentController::class, 'checkout'])->middleware('signed:relative')->name('reservation.payment.checkout');
Route::post('/reservas/pagos/niubiz/{attempt}/return', [ReservationPaymentController::class, 'complete'])->middleware(['signed:relative', 'throttle:10,1'])->name('reservation.payment.return');
Route::get('/consulta-documento', [DocumentLookupController::class, 'show'])->middleware('throttle:30,1')->name('document.lookup');
Route::get('/pagos/niubiz/{attempt}', [OrderPaymentController::class, 'checkout'])->middleware('signed')->name('customer.payment.checkout');
Route::post('/pagos/niubiz/{attempt}/retorno', [OrderPaymentController::class, 'complete'])->middleware(['signed', 'throttle:10,1'])->name('customer.payment.return');
Route::get('/pagos/niubiz/{attempt}/estado', [OrderPaymentController::class, 'status'])->middleware(['signed', 'throttle:10,1'])->name('customer.payment.status');
Route::get('/health.php', [MobileController::class, 'health']);
Route::get('/api/health', [MobileController::class, 'health']);
Route::prefix('api/mobile')->group(function (): void {
    Route::get('/dining/tables/{token}', [DiningController::class, 'preview']);
    Route::post('/dining/tables/{token}/join', [DiningController::class, 'join'])->middleware('throttle:10,1');
    Route::get('/dining/session', [DiningController::class, 'state']);
    Route::post('/dining/orders', [DiningController::class, 'order'])->middleware('throttle:10,1');
    Route::post('/dining/requests', [DiningController::class, 'attention'])->middleware('throttle:10,1');
    Route::post('/dining/payments', [DiningPaymentController::class, 'start'])->middleware('throttle:5,1');
    Route::get('/dining/floor', [DiningController::class, 'floor']);
    Route::post('/dining/actions', [DiningController::class, 'action'])->middleware('throttle:60,1');
    Route::get('/session', [MobileController::class, 'session']);
    Route::get('/catalog', [MobileController::class, 'catalog']);
    Route::post('/google/challenge', [GoogleAuthController::class, 'mobileChallenge'])->middleware('throttle:restaurant-auth');
    Route::post('/google/login', [GoogleAuthController::class, 'mobileLogin'])->middleware('throttle:restaurant-auth');
    Route::get('/sync', [MobileController::class, 'sync'])->middleware('throttle:60,1');
    Route::put('/favorites/{id}', [MobileController::class, 'favorite'])->whereNumber('id')->middleware('throttle:60,1');
    Route::post('/login', [MobileController::class, 'login'])->middleware('throttle:restaurant-auth');
    Route::post('/register', [MobileController::class, 'register'])->middleware('throttle:restaurant-auth');
    Route::post('/logout', [MobileController::class, 'logout']);
    Route::post('/checkout', [MobileController::class, 'checkout'])->middleware('throttle:10,1');
    Route::post('/quote', [MobileController::class, 'quote']);
    Route::get('/account', [MobileController::class, 'account']);
    Route::post('/profile', [MobileController::class, 'profile']);
    Route::post('/addresses', [MobileController::class, 'address']);
    Route::post('/addresses/{id}/default', [MobileController::class, 'defaultAddress'])->whereNumber('id');
    Route::delete('/addresses/{id}', [MobileController::class, 'deleteAddress'])->whereNumber('id');
    Route::post('/password', [MobileController::class, 'changePassword'])->middleware('throttle:10,1');
    Route::post('/loyalty/redeem', [MobileController::class, 'redeemReward'])->middleware('throttle:15,1');
    Route::post('/reviews', [MobileController::class, 'submitReview'])->middleware('throttle:15,1');
    Route::post('/reservations', [MobileController::class, 'reserve']);
    Route::post('/orders/{id}/cancel', [MobileController::class, 'cancelOrder']);
    Route::post('/reservations/{id}/cancel', [MobileController::class, 'cancelReservation']);
    Route::get('/dashboard', [MobileController::class, 'dashboard']);
});
Route::get('/api/mobile.php', [MobileController::class, 'session']);
Route::get('/admin/legacy', [PageController::class, 'admin'])->middleware(RequireRestaurantStaff::class)->name('desego.admin');
Route::get('/index.php', fn (Request $request) => $request->filled('reset_token') ? redirect()->route('password.reset', ['token' => $request->query('reset_token')]) : redirect('/admin'));
Route::get('/tienda', fn () => redirect('/'))->name('desego.store');
Route::get('/tienda.php', fn () => redirect('/'));
foreach (config('desego_routes', []) as $name => $controller) {
    $middleware = match ($name) {
        'auth' => ['throttle:restaurant-auth'],
        'password-reset' => ['throttle:restaurant-password-reset'],
        default => [],
    };
    Route::any('/api/'.$name.'.php', $controller)->middleware($middleware)->name('desego.api.'.$name);
    Route::any('/api/'.$name, $controller)->middleware($middleware);
}
