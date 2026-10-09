<?php

use App\Http\Controllers\Api\V1\Account\MeController;
use App\Http\Controllers\Api\V1\Account\PasswordCodeController;
use App\Http\Controllers\Api\V1\Account\PasswordController;
use App\Http\Controllers\Api\V1\Account\SessionController;
use App\Http\Controllers\Api\V1\Account\TwoFactorController;
use App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Api\V1\Admin\AdminMeController;
use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Api\V1\Storefront\CartController;
use App\Http\Controllers\Api\V1\Storefront\CatalogController;
use App\Http\Controllers\Api\V1\Storefront\CheckoutController;
use App\Http\Controllers\Api\V1\Storefront\DownloadController;
use App\Http\Controllers\Api\V1\Storefront\HeroSlideController;
use App\Http\Controllers\Api\V1\Storefront\OrderController;
use App\Http\Controllers\Api\V1\Storefront\PageController;
use App\Http\Controllers\Api\V1\Storefront\WishlistController;
use App\Http\Controllers\Api\V1\System\HealthController;
use App\Http\Controllers\Api\V1\System\PublicSettingsController;
use Illuminate\Support\Facades\Route;

/*
| /api/v1 — see docs/API.md §3. Every route declares its authorization:
| public, owner-scoped (cart cookie / auth / order token), or admin + can:<permission>.
*/

// System
Route::middleware('throttle:public')->group(function () {
    Route::get('health', HealthController::class)->name('health');
    Route::get('settings/public', PublicSettingsController::class)->name('settings.public');
});

// Catalog & content (public, cacheable)
Route::middleware('throttle:public')->group(function () {
    Route::get('categories', [CatalogController::class, 'categories'])->name('categories.index');
    Route::get('categories/{slug}', [CatalogController::class, 'category'])->name('categories.show');
    Route::get('products', [CatalogController::class, 'products'])->name('products.index');
    Route::get('products/{slug}', [CatalogController::class, 'product'])->name('products.show');
    Route::get('pages/{slug}', [PageController::class, 'show'])->name('pages.show');
    Route::get('hero-slides', [HeroSlideController::class, 'index'])->name('hero-slides.index');
});

// Cart (guest cookie or signed-in user)
Route::prefix('cart')->name('cart.')->middleware('throttle:cart')->group(function () {
    Route::get('/', [CartController::class, 'show'])->name('show');
    Route::post('items', [CartController::class, 'addItem'])->name('items.store');
    Route::patch('items/{item}', [CartController::class, 'updateItem'])->name('items.update');
    Route::delete('items/{item}', [CartController::class, 'removeItem'])->name('items.destroy');
    Route::post('coupon', [CartController::class, 'applyCoupon'])->name('coupon.store');
    Route::delete('coupon', [CartController::class, 'removeCoupon'])->name('coupon.destroy');
});

// Checkout
Route::prefix('checkout')->name('checkout.')->middleware('throttle:checkout')->group(function () {
    Route::get('/', [CheckoutController::class, 'show'])->name('show');
    Route::put('contact', [CheckoutController::class, 'contact'])->name('contact');
    Route::put('address', [CheckoutController::class, 'address'])->name('address');
    Route::put('shipping-method', [CheckoutController::class, 'shippingMethod'])->name('shipping-method');
    Route::post('place-order', [CheckoutController::class, 'placeOrder'])->middleware('idempotent')->name('place-order');
});

// Orders (owner: signed-in user or guest with access token)
Route::prefix('orders')->name('orders.')->middleware('throttle:checkout')->group(function () {
    Route::get('/', [OrderController::class, 'index'])->middleware('auth:sanctum')->name('index');
    Route::get('{orderNumber}', [OrderController::class, 'show'])->name('show');
    Route::get('{orderNumber}/invoice', [OrderController::class, 'invoice'])->name('invoice');
    Route::post('{orderNumber}/cancel', [OrderController::class, 'cancel'])->name('cancel');
    Route::post('{orderNumber}/payment/confirm', [OrderController::class, 'confirmPayment'])->middleware('idempotent')->name('payment.confirm');
    Route::post('{orderNumber}/payment/retry', [OrderController::class, 'retryPayment'])->name('payment.retry');
});

// Digital downloads
Route::middleware('throttle:downloads')->group(function () {
    Route::get('account/downloads', [DownloadController::class, 'index'])->middleware('auth:sanctum')->name('downloads.index');
    Route::post('account/downloads/{entitlement}/files/{file}/link', [DownloadController::class, 'link'])->name('downloads.link');
    Route::get('downloads/{token}', [DownloadController::class, 'stream'])->name('downloads.stream');
});

// Authentication
Route::prefix('auth')->name('auth.')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('register', RegisterController::class)->name('register');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login');
        Route::post('two-factor/challenge', TwoFactorChallengeController::class)->name('two-factor.challenge');
        Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->name('password.forgot');
        Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.reset');
    });

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth:sanctum')->name('logout');

    Route::post('email/resend', [EmailVerificationController::class, 'resend'])
        ->middleware(['auth:sanctum', 'throttle:auth'])->name('verification.resend');
});

// Signed link from the verification email (route name used by VerifyEmailNotification).
Route::get('auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:auth'])
    ->name('verification.verify');

// Customer account (any authenticated user)
Route::middleware(['auth:sanctum', 'throttle:account'])->group(function () {
    Route::get('me', MeController::class)->name('me');

    Route::prefix('account')->name('account.')->group(function () {
        Route::put('password', [PasswordController::class, 'update'])->name('password.update');
        Route::post('password/code', [PasswordCodeController::class, 'send'])->middleware('throttle:3,10')->name('password.code');
        Route::put('password/with-code', [PasswordCodeController::class, 'update'])->middleware('throttle:10,10')->name('password.with-code');
        Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
        Route::post('sessions/logout-others', [SessionController::class, 'destroyOthers'])->name('sessions.logout-others');
        Route::post('two-factor', [TwoFactorController::class, 'store'])->name('two-factor.enable');
        Route::post('two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
        Route::delete('two-factor', [TwoFactorController::class, 'destroy'])->name('two-factor.disable');
        Route::post('two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('two-factor.recovery-codes');
    });

    Route::get('wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('wishlist/items', [WishlistController::class, 'store'])->name('wishlist.store');
    Route::delete('wishlist/items/{product}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');
});

// Admin (staff + 2FA + idle timeout; each route adds its own `can:` permission)
Route::prefix('admin')->name('admin.')
    ->middleware(['auth:sanctum', 'admin', 'throttle:admin'])
    ->group(function () {
        Route::get('me', AdminMeController::class)->name('me');
        Route::get('dashboard', Admin\DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');
        Route::get('lookups', [Admin\TaxonomyController::class, 'lookups'])->middleware('can:products.view')->name('lookups');

        Route::get('products', [Admin\ProductController::class, 'index'])->middleware('can:products.view')->name('products.index');
        Route::post('products', [Admin\ProductController::class, 'store'])->middleware('can:products.create')->name('products.store');
        Route::get('products/{product}', [Admin\ProductController::class, 'show'])->middleware('can:products.view')->name('products.show');
        Route::put('products/{product}', [Admin\ProductController::class, 'update'])->middleware('can:products.edit')->name('products.update');
        Route::delete('products/{product}', [Admin\ProductController::class, 'destroy'])->middleware('can:products.delete')->name('products.destroy');
        Route::post('products/{product}/media', [Admin\ProductController::class, 'uploadMedia'])->middleware('can:products.edit')->name('products.media.store');
        Route::delete('products/{product}/media/{media}', [Admin\ProductController::class, 'deleteMedia'])->middleware('can:products.edit')->name('products.media.destroy');
        Route::post('products/{product}/files', [Admin\ProductController::class, 'uploadFile'])->middleware('can:digital.manage')->name('products.files.store');
        Route::delete('products/{product}/files/{file}', [Admin\ProductController::class, 'deleteFile'])->middleware('can:digital.manage')->name('products.files.destroy');

        Route::get('categories', [Admin\TaxonomyController::class, 'categories'])->middleware('can:categories.manage')->name('categories.index');
        Route::post('categories', [Admin\TaxonomyController::class, 'saveCategory'])->middleware('can:categories.manage')->name('categories.store');
        Route::put('categories/{category}', [Admin\TaxonomyController::class, 'saveCategory'])->middleware('can:categories.manage')->name('categories.update');
        Route::delete('categories/{category}', [Admin\TaxonomyController::class, 'deleteCategory'])->middleware('can:categories.manage')->name('categories.destroy');
        Route::get('brands', [Admin\TaxonomyController::class, 'brands'])->middleware('can:brands.manage')->name('brands.index');
        Route::post('brands', [Admin\TaxonomyController::class, 'saveBrand'])->middleware('can:brands.manage')->name('brands.store');
        Route::put('brands/{brand}', [Admin\TaxonomyController::class, 'saveBrand'])->middleware('can:brands.manage')->name('brands.update');
        Route::delete('brands/{brand}', [Admin\TaxonomyController::class, 'deleteBrand'])->middleware('can:brands.manage')->name('brands.destroy');

        Route::get('inventory', [Admin\InventoryController::class, 'index'])->middleware('can:inventory.view')->name('inventory.index');
        Route::post('inventory/{variant}/adjust', [Admin\InventoryController::class, 'adjust'])->middleware('can:inventory.edit')->name('inventory.adjust');
        Route::get('inventory/{variant}/transactions', [Admin\InventoryController::class, 'transactions'])->middleware('can:inventory.view')->name('inventory.transactions');

        Route::get('orders', [Admin\OrderController::class, 'index'])->middleware('can:orders.view')->name('orders.index');
        Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->middleware('can:orders.view')->name('orders.show');
        Route::get('orders/{order}/invoice', [Admin\OrderController::class, 'invoice'])->middleware('can:orders.view')->name('orders.invoice');
        Route::post('orders/{order}/status', [Admin\OrderController::class, 'transition'])->middleware('can:orders.edit')->name('orders.status');
        Route::post('orders/{order}/courier-booking', [Admin\OrderController::class, 'bookCourier'])->middleware('can:shipments.manage')->name('orders.courier');
        Route::post('orders/{order}/shipments', [Admin\OrderController::class, 'ship'])->middleware('can:shipments.manage')->name('orders.ship');
        Route::post('orders/{order}/refunds', [Admin\OrderController::class, 'refund'])->middleware(['can:orders.refund', 'idempotent'])->name('orders.refund');
        Route::post('orders/{order}/cancel', [Admin\OrderController::class, 'cancel'])->middleware('can:orders.cancel')->name('orders.cancel');

        Route::get('customers', [Admin\CustomerController::class, 'index'])->middleware('can:customers.view')->name('customers.index');
        Route::get('customers/{customer}', [Admin\CustomerController::class, 'show'])->middleware('can:customers.view')->name('customers.show');
        Route::patch('customers/{customer}', [Admin\CustomerController::class, 'update'])->middleware('can:customers.edit')->name('customers.update');

        Route::get('coupons', [Admin\CouponController::class, 'index'])->middleware('can:coupons.manage')->name('coupons.index');
        Route::post('coupons', [Admin\CouponController::class, 'save'])->middleware('can:coupons.manage')->name('coupons.store');
        Route::put('coupons/{coupon}', [Admin\CouponController::class, 'save'])->middleware('can:coupons.manage')->name('coupons.update');
        Route::delete('coupons/{coupon}', [Admin\CouponController::class, 'destroy'])->middleware('can:coupons.manage')->name('coupons.destroy');

        Route::get('digital/entitlements', [Admin\DigitalController::class, 'entitlements'])->middleware('can:downloads.view')->name('digital.entitlements');
        Route::patch('digital/entitlements/{entitlement}', [Admin\DigitalController::class, 'update'])->middleware('can:entitlements.revoke')->name('digital.entitlements.update');
        Route::get('digital/downloads', [Admin\DigitalController::class, 'downloads'])->middleware('can:downloads.view')->name('digital.downloads');

        Route::get('reports/sales', [Admin\ReportController::class, 'sales'])->middleware('can:reports.view')->name('reports.sales');

        Route::get('pages', [Admin\ContentController::class, 'index'])->middleware('can:content.manage')->name('pages.index');
        Route::post('pages', [Admin\ContentController::class, 'save'])->middleware('can:content.manage')->name('pages.store');
        Route::get('pages/{page}', [Admin\ContentController::class, 'show'])->middleware('can:content.manage')->name('pages.show');
        Route::put('pages/{page}', [Admin\ContentController::class, 'save'])->middleware('can:content.manage')->name('pages.update');
        Route::delete('pages/{page}', [Admin\ContentController::class, 'destroy'])->middleware('can:content.manage')->name('pages.destroy');

        Route::get('staff', [Admin\StaffController::class, 'index'])->middleware('can:users.manage')->name('staff.index');
        Route::get('staff/roles', [Admin\StaffController::class, 'roles'])->middleware('can:users.manage')->name('staff.roles');
        Route::post('staff', [Admin\StaffController::class, 'store'])->middleware(['can:users.manage', 'throttle:20,10'])->name('staff.store');
        Route::patch('staff/{staff}', [Admin\StaffController::class, 'update'])->middleware('can:users.manage')->name('staff.update');
        Route::post('staff/{staff}/two-factor-reset', [Admin\StaffController::class, 'resetTwoFactor'])->middleware('can:users.manage')->name('staff.two-factor-reset');
        Route::post('staff/{staff}/invitation', [Admin\StaffController::class, 'resendInvite'])->middleware(['can:users.manage', 'throttle:6,10'])->name('staff.invitation');

        Route::get('hero-slides', [Admin\HeroSlideController::class, 'index'])->middleware('can:content.manage')->name('hero-slides.index');
        Route::post('hero-slides', [Admin\HeroSlideController::class, 'save'])->middleware('can:content.manage')->name('hero-slides.store');
        Route::post('hero-slides/reorder', [Admin\HeroSlideController::class, 'reorder'])->middleware('can:content.manage')->name('hero-slides.reorder');
        Route::post('hero-slides/{slide}', [Admin\HeroSlideController::class, 'save'])->middleware('can:content.manage')->name('hero-slides.update');
        Route::delete('hero-slides/{slide}', [Admin\HeroSlideController::class, 'destroy'])->middleware('can:content.manage')->name('hero-slides.destroy');

        Route::get('settings', [Admin\SettingsController::class, 'show'])->middleware('can:settings.manage')->name('settings.show');
        Route::put('settings', [Admin\SettingsController::class, 'update'])->middleware('can:settings.manage')->name('settings.update');
        Route::get('exports/{type}', Admin\ExportController::class)->middleware(['can:exports.run', 'throttle:20,1'])->name('exports');
        Route::post('settings/shiprocket/test', [Admin\SettingsController::class, 'testShiprocket'])->middleware(['can:settings.manage', 'throttle:6,1'])->name('settings.shiprocket-test');
        Route::put('settings/security', [Admin\SettingsController::class, 'security'])->middleware('can:settings.manage')->name('settings.security');
        Route::get('audit-logs', Admin\AuditLogController::class)->middleware('can:audit.view')->name('audit-logs');
    });
