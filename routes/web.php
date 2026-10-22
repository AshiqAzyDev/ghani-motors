<?php

use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\DealerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('/stock', [SiteController::class, 'stock'])->name('stock');
Route::get('/cars/{slug}', [SiteController::class, 'show'])->name('cars.show');
Route::post('/cars/{slug}/save', [SiteController::class, 'save'])->name('cars.save');
Route::post('/cars/{slug}/enquiry', [SiteController::class, 'enquiry'])->name('cars.enquiry');
Route::post('/cars/{slug}/reserve', [SiteController::class, 'reserve'])->name('cars.reserve');
Route::post('/cars/{slug}/test-drive', [SiteController::class, 'testDrive'])->name('cars.test-drive');
Route::post('/cars/{slug}/viewing', [SiteController::class, 'viewing'])->name('cars.viewing');
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
Route::get('/account', [SiteController::class, 'account'])->middleware('auth')->name('account');

Route::post('/webhooks/autotrader', WebhookController::class)->name('webhooks.autotrader');

Route::prefix('dealer')->group(function () {
    Route::get('/login', [DealerController::class, 'showLogin'])->name('dealer.login');
    Route::post('/login', [DealerController::class, 'login'])->name('dealer.login.store');

    Route::middleware('staff')->group(function () {
        Route::post('/logout', [DealerController::class, 'logout'])->name('dealer.logout');
        Route::get('/', [DealerController::class, 'home'])->name('dealer.home');
        Route::get('/vehicles', [DealerController::class, 'vehicles'])->name('dealer.vehicles');
        Route::get('/vehicles/create', [DealerController::class, 'createVehicle'])->name('dealer.vehicles.create');
        Route::get('/vehicles/{vehicle}/edit', [DealerController::class, 'editVehicle'])->name('dealer.vehicles.edit');
        Route::get('/bookings', [DealerController::class, 'bookings'])->name('dealer.bookings');
        Route::get('/customers', [DealerController::class, 'customers'])->name('dealer.customers');
        Route::get('/leads', [DealerController::class, 'leads'])->name('dealer.leads');
        Route::get('/sales', [DealerController::class, 'sales'])->name('dealer.sales');
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('dealer.invoices');
        Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('dealer.invoices.create');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('dealer.invoices.show');
        Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('dealer.invoices.print');
        Route::get('/valuation-photos/{photo}', [SiteController::class, 'photo'])->name('dealer.photos');

        Route::middleware('staff.mutate')->group(function () {
            Route::post('/vehicles', [DealerController::class, 'storeVehicle'])->name('dealer.vehicles.store');
            Route::put('/vehicles/{vehicle}', [DealerController::class, 'updateVehicle'])->name('dealer.vehicles.update');
            Route::delete('/vehicles/{vehicle}', [DealerController::class, 'destroyVehicle'])->name('dealer.vehicles.destroy');
            Route::post('/phone', [DealerController::class, 'updatePhone'])->name('dealer.phone');
            Route::post('/sync', [DealerController::class, 'sync'])->name('dealer.sync');
            Route::post('/bookings', [DealerController::class, 'storeBooking'])->name('dealer.bookings.store');
            Route::post('/bookings/{booking}/status', [DealerController::class, 'bookingStatus'])->name('dealer.bookings.status');
            Route::post('/customers', [DealerController::class, 'storeCustomer'])->name('dealer.customers.store');
            Route::post('/leads/{lead}', [DealerController::class, 'updateLead'])->name('dealer.leads.update');
            Route::post('/sales', [DealerController::class, 'storeSale'])->name('dealer.sales.store');
            Route::post('/invoices', [InvoiceController::class, 'store'])->name('dealer.invoices.store');
            Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('dealer.invoices.issue');
            Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'pay'])->name('dealer.invoices.pay');
            Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('dealer.invoices.void');
            Route::post('/invoices/{invoice}/credit', [InvoiceController::class, 'credit'])->name('dealer.invoices.credit');
        });
    });
});
