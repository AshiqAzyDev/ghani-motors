<?php

use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\DealerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('/stock', [SiteController::class, 'stock'])->name('stock');
Route::get('/cars/{slug}', [SiteController::class, 'show'])->name('cars.show');
Route::post('/cars/{slug}/enquiry', [SiteController::class, 'enquiry'])->name('cars.enquiry');
Route::post('/cars/{slug}/test-drive', [SiteController::class, 'testDrive'])->name('cars.test-drive');
Route::post('/enquire', [SiteController::class, 'generalEnquiry'])->name('enquiry.store');
Route::get('/sell', [SiteController::class, 'sell'])->name('sell');
Route::post('/sell', [SiteController::class, 'storeSell'])->name('sell.store');
Route::get('/finance', [SiteController::class, 'finance'])->name('finance');
Route::post('/finance', [SiteController::class, 'financeEnquiry'])->name('finance.store');
Route::get('/about', [SiteController::class, 'about'])->name('about');
Route::get('/privacy', [SiteController::class, 'privacy'])->name('privacy');
Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('sitemap');

Route::middleware('guest')->group(function () {
    Route::get('/login', [CustomerAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [CustomerAuthController::class, 'login']);
    Route::get('/register', [CustomerAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [CustomerAuthController::class, 'register']);
    Route::get('/forgot-password', [CustomerAuthController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [CustomerAuthController::class, 'sendReset'])->name('password.email');
    Route::get('/reset-password/{token}', [CustomerAuthController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [CustomerAuthController::class, 'reset'])->name('password.update');
});

Route::post('/logout', [CustomerAuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::post('/cars/{slug}/save', [SiteController::class, 'save'])->name('cars.save');
    Route::post('/cars/{slug}/reserve', [SiteController::class, 'reserve'])->name('cars.reserve');
    Route::post('/cars/{slug}/viewing', [SiteController::class, 'viewing'])->name('cars.viewing');
    Route::get('/account/{section?}', [SiteController::class, 'account'])
        ->where('section', 'overview|saved|bookings|enquiries|invoices|alerts|profile')
        ->name('account');
    Route::put('/account/profile', [SiteController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [SiteController::class, 'updatePassword'])->name('account.password');
    Route::post('/account/saved/{vehicle}/unsave', [SiteController::class, 'unsave'])->name('account.unsave');
    Route::get('/account/notifications/{notification}/read', [NotificationController::class, 'customerMarkRead'])->name('account.notifications.read');
    Route::post('/account/notifications/read-all', [NotificationController::class, 'customerMarkAll'])->name('account.notifications.read-all');
});

Route::post('/webhooks/autotrader', WebhookController::class)->name('webhooks.autotrader');

Route::prefix('dealer')->group(function () {
    Route::get('/login', [DealerController::class, 'showLogin'])->name('dealer.login');
    Route::post('/login', [DealerController::class, 'login'])->name('dealer.login.store');
    Route::get('/forgot-password', [DealerController::class, 'showForgotPassword'])->name('dealer.password.request');
    Route::post('/forgot-password', [DealerController::class, 'sendResetLink'])->name('dealer.password.email');
    Route::get('/reset-password/{token}', [DealerController::class, 'showResetPassword'])->name('dealer.password.reset');
    Route::post('/reset-password', [DealerController::class, 'resetPassword'])->name('dealer.password.update');

    Route::middleware('staff')->group(function () {
        Route::post('/logout', [DealerController::class, 'logout'])->name('dealer.logout');
        Route::get('/', [DealerController::class, 'home'])->name('dealer.home');
        Route::get('/notifications', [NotificationController::class, 'dealerIndex'])->name('dealer.notifications');
        Route::get('/notifications/unread', [NotificationController::class, 'dealerUnread'])->name('dealer.notifications.unread');
        Route::get('/notifications/{notification}/read', [NotificationController::class, 'dealerMarkRead'])->name('dealer.notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'dealerMarkAll'])->name('dealer.notifications.read-all');

        Route::get('/settings', [DealerController::class, 'settings'])->middleware('staff.can:settings.view')->name('dealer.settings');
        Route::get('/vehicles', [DealerController::class, 'vehicles'])->middleware('staff.can:vehicles.view')->name('dealer.vehicles');
        Route::get('/vehicles/create', [DealerController::class, 'createVehicle'])->middleware('staff.can:vehicles.edit_manual')->name('dealer.vehicles.create');
        Route::get('/vehicles/{vehicle}/edit', [DealerController::class, 'editVehicle'])->middleware('staff.can:vehicles.edit_manual')->name('dealer.vehicles.edit');
        Route::get('/bookings', [DealerController::class, 'bookings'])->middleware('staff.can:bookings.view')->name('dealer.bookings');
        Route::get('/bookings/{booking}', [DealerController::class, 'showBooking'])->middleware('staff.can:bookings.view')->name('dealer.bookings.show');
        Route::get('/customers', [DealerController::class, 'customers'])->middleware('staff.can:customers.view')->name('dealer.customers');
        Route::get('/customers/{customer}', [DealerController::class, 'showCustomer'])->middleware('staff.can:customers.view')->name('dealer.customers.show');
        Route::get('/leads', [DealerController::class, 'leads'])->middleware('staff.can:leads.view')->name('dealer.leads');
        Route::get('/leads/{lead}', [DealerController::class, 'showLead'])->middleware('staff.can:leads.view')->name('dealer.leads.show');
        Route::get('/sales', [DealerController::class, 'sales'])->middleware('staff.can:sales.view')->name('dealer.sales');
        Route::get('/sales/{sale}', [DealerController::class, 'showSale'])->middleware('staff.can:sales.view')->name('dealer.sales.show');
        Route::get('/export/{type}', [DealerController::class, 'export'])->where('type', 'customers|leads|bookings|sales|vehicles')->name('dealer.export');
        Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('staff.can:invoices.view')->name('dealer.invoices');
        Route::get('/invoices/create', [InvoiceController::class, 'create'])->middleware('staff.can:invoices.manage')->name('dealer.invoices.create');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('staff.can:invoices.view')->name('dealer.invoices.show');
        Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->middleware('staff.can:invoices.view')->name('dealer.invoices.print');
        Route::get('/valuation-photos/{photo}', [SiteController::class, 'photo'])->name('dealer.photos');
        Route::get('/staff', [StaffController::class, 'index'])->middleware('staff.can:staff.manage')->name('dealer.staff');

        Route::middleware('staff.mutate')->group(function () {
            Route::post('/vehicles', [DealerController::class, 'storeVehicle'])->middleware('staff.can:vehicles.edit_manual')->name('dealer.vehicles.store');
            Route::put('/vehicles/{vehicle}', [DealerController::class, 'updateVehicle'])->middleware('staff.can:vehicles.edit_manual')->name('dealer.vehicles.update');
            Route::post('/vehicles/{vehicle}/featured', [DealerController::class, 'featureVehicle'])->middleware('staff.can:vehicles.feature')->name('dealer.vehicles.featured');
            Route::delete('/vehicles/{vehicle}', [DealerController::class, 'destroyVehicle'])->middleware('staff.can:vehicles.edit_manual')->name('dealer.vehicles.destroy');
            Route::post('/settings', [DealerController::class, 'updateSettings'])->middleware('staff.can:settings.manage')->name('dealer.settings.update');
            Route::post('/settings/migrate', [DealerController::class, 'migrateDatabase'])->middleware('staff.can:settings.manage')->name('dealer.settings.migrate');
            Route::post('/sync', [DealerController::class, 'sync'])->middleware('staff.can:sync.run')->name('dealer.sync');
            Route::post('/bookings', [DealerController::class, 'storeBooking'])->middleware('staff.can:bookings.manage')->name('dealer.bookings.store');
            Route::post('/bookings/{booking}/status', [DealerController::class, 'bookingStatus'])->middleware('staff.can:bookings.manage')->name('dealer.bookings.status');
            Route::post('/customers', [DealerController::class, 'storeCustomer'])->middleware('staff.can:customers.manage')->name('dealer.customers.store');
            Route::put('/customers/{customer}', [DealerController::class, 'updateCustomer'])->middleware('staff.can:customers.manage')->name('dealer.customers.update');
            Route::post('/leads/{lead}', [DealerController::class, 'updateLead'])->middleware('staff.can:leads.manage')->name('dealer.leads.update');
            Route::post('/leads/{lead}/requirements', [DealerController::class, 'storeLeadRequirement'])->middleware('staff.can:leads.manage')->name('dealer.leads.requirements.store');
            Route::post('/leads/{lead}/requirements/{key}', [DealerController::class, 'updateLeadRequirement'])->middleware('staff.can:leads.manage')->name('dealer.leads.requirements.update');
            Route::delete('/leads/{lead}/requirements/{key}', [DealerController::class, 'destroyLeadRequirement'])->middleware('staff.can:leads.manage')->name('dealer.leads.requirements.destroy');
            Route::post('/sales', [DealerController::class, 'storeSale'])->middleware('staff.can:sales.manage')->name('dealer.sales.store');
            Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('staff.can:invoices.manage')->name('dealer.invoices.store');
            Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->middleware('staff.can:invoices.manage')->name('dealer.invoices.issue');
            Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'pay'])->middleware('staff.can:invoices.manage')->name('dealer.invoices.pay');
            Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->middleware('staff.can:invoices.manage')->name('dealer.invoices.void');
            Route::post('/invoices/{invoice}/credit', [InvoiceController::class, 'credit'])->middleware('staff.can:invoices.manage')->name('dealer.invoices.credit');
            Route::post('/staff', [StaffController::class, 'store'])->middleware('staff.can:staff.manage')->name('dealer.staff.store');
            Route::put('/staff/{member}', [StaffController::class, 'update'])->middleware('staff.can:staff.manage')->name('dealer.staff.update');
            Route::post('/staff/{member}/invite', [StaffController::class, 'inviteAgain'])->middleware('staff.can:staff.manage')->name('dealer.staff.invite');
        });
    });
});
