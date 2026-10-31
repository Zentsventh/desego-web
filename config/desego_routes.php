<?php

use App\Http\Controllers\Desego\AdminToolsController;
use App\Http\Controllers\Desego\AuthController;
use App\Http\Controllers\Desego\BillingReportsController;
use App\Http\Controllers\Desego\CashController;
use App\Http\Controllers\Desego\CatalogController;
use App\Http\Controllers\Desego\CommunicationsController;
use App\Http\Controllers\Desego\CustomersController;
use App\Http\Controllers\Desego\ExternalController;
use App\Http\Controllers\Desego\InventoryController;
use App\Http\Controllers\Desego\LoyaltyController;
use App\Http\Controllers\Desego\OperationsController;
use App\Http\Controllers\Desego\OrdersController;
use App\Http\Controllers\Desego\PasswordResetController;
use App\Http\Controllers\Desego\PaymentsController;
use App\Http\Controllers\Desego\ProfileController;
use App\Http\Controllers\Desego\PromotionsController;
use App\Http\Controllers\Desego\PurchasingController;
use App\Http\Controllers\Desego\ReservationsController;
use App\Http\Controllers\Desego\ReviewsController;
use App\Http\Controllers\Desego\RolesController;
use App\Http\Controllers\Desego\StorefrontController;
use App\Http\Controllers\Desego\UsersController;
use App\Http\Controllers\Desego\WorkforceController;

return [
    'admin-tools' => AdminToolsController::class,
    'auth' => AuthController::class,
    'billing-reports' => BillingReportsController::class,
    'cash' => CashController::class,
    'catalog' => CatalogController::class,
    'communications' => CommunicationsController::class,
    'customers' => CustomersController::class,
    'external' => ExternalController::class,
    'inventory' => InventoryController::class,
    'loyalty' => LoyaltyController::class,
    'operations' => OperationsController::class,
    'orders' => OrdersController::class,
    'password-reset' => PasswordResetController::class,
    'payments' => PaymentsController::class,
    'profile' => ProfileController::class,
    'promotions' => PromotionsController::class,
    'purchasing' => PurchasingController::class,
    'reservations' => ReservationsController::class,
    'reviews' => ReviewsController::class,
    'roles' => RolesController::class,
    'storefront' => StorefrontController::class,
    'users' => UsersController::class,
    'workforce' => WorkforceController::class,
];
