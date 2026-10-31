<?php

use App\Http\Controllers\Desego\PageController;
use App\Http\Controllers\Restaurant\AdminController;
use App\Http\Controllers\Restaurant\ChatbotController;
use App\Http\Controllers\Restaurant\CustomerController;
use App\Http\Controllers\Restaurant\DiningController;
use App\Http\Controllers\Restaurant\DiningPaymentController;
use App\Http\Controllers\Restaurant\DocumentLookupController;
use App\Http\Controllers\Restaurant\MobileController;
use App\Http\Controllers\Restaurant\SessionController;
use App\Http\Controllers\Restaurant\StoreController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireRestaurantStaff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(HandleInertiaRequests::class)->group(function (): void {
    Route::get('/', [StoreController::class, 'index'])->name('store.home');
    Route::get('/mesa/{token}', [DiningController::class, 'show'])->name('dining.table');
    Route::get('/mesa-pago', [DiningController::class, 'payment'])->name('dining.payment.start');
    Route::get('/ingresar', [SessionController::class, 'create'])->name('restaurant.login');
    Route::get('/registro', [SessionController::class, 'registration']);
    Route::post('/ingresar', [SessionController::class, 'login'])->middleware('throttle:restaurant-auth');
    Route::post('/registro', [SessionController::class, 'register'])->middleware('throttle:restaurant-auth');
    Route::get('/recuperar-contrasena', [SessionController::class, 'forgotPassword'])->name('password.request');
    Route::post('/recuperar-contrasena', [SessionController::class, 'sendResetLink'])->middleware('throttle:restaurant-password-reset');
    Route::get('/restablecer-contrasena', [SessionController::class, 'resetPasswordForm'])->name('password.reset');
    Route::post('/restablecer-contrasena', [SessionController::class, 'resetPassword'])->middleware('throttle:restaurant-password-reset');
    Route::post('/salir', [SessionController::class, 'logout']);
    Route::post('/pedido', [StoreController::class, 'checkout'])->middleware('throttle:10,1');
    Route::post('/carrito/cotizar', [StoreController::class, 'quote'])->middleware('throttle:60,1');
    Route::post('/reservar', [StoreController::class, 'reserve'])->middleware('throttle:10,1');
    Route::get('/cuenta', [CustomerController::class, 'index']);
    Route::post('/cuenta/perfil', [CustomerController::class, 'profile']);
    Route::post('/cuenta/direcciones', [CustomerController::class, 'address']);
    Route::delete('/cuenta/direcciones/{id}', [CustomerController::class, 'deleteAddress']);
    Route::post('/cuenta/pedidos/{id}/cancelar', [CustomerController::class, 'cancelOrder']);
    Route::post('/cuenta/reservas/{id}/cancelar', [CustomerController::class, 'cancelReservation']);
    Route::middleware(RequireRestaurantStaff::class)->group(function (): void {
        Route::get('/admin', [AdminController::class, 'index'])->name('admin.home');
        Route::get('/admin/herramientas', [AdminController::class, 'tools'])->name('admin.tools');
        Route::get('/admin/seccion/{section}', [AdminController::class, 'index']);
        Route::post('/admin/acciones/{module}', [AdminController::class, 'action']);
    });
});
Route::post('/chatbot/mensaje', [ChatbotController::class, 'message'])->middleware('throttle:20,1')->name('chatbot.message');
Route::get('/dining/payments/{attempt}', [DiningPaymentController::class, 'checkout'])->middleware('signed')->name('dining.payment.checkout');
Route::post('/dining/payments/{attempt}/return', [DiningPaymentController::class, 'complete'])->middleware(['signed', 'throttle:10,1'])->name('dining.payment.return');
Route::get('/consulta-documento', [DocumentLookupController::class, 'show'])->middleware('throttle:30,1')->name('document.lookup');
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
    Route::post('/login', [MobileController::class, 'login'])->middleware('throttle:restaurant-auth');
    Route::post('/register', [MobileController::class, 'register'])->middleware('throttle:restaurant-auth');
    Route::post('/logout', [MobileController::class, 'logout']);
    Route::post('/checkout', [MobileController::class, 'checkout'])->middleware('throttle:10,1');
    Route::post('/quote', [MobileController::class, 'quote']);
    Route::get('/account', [MobileController::class, 'account']);
    Route::post('/profile', [MobileController::class, 'profile']);
    Route::post('/addresses', [MobileController::class, 'address']);
    Route::delete('/addresses/{id}', [MobileController::class, 'deleteAddress']);
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
