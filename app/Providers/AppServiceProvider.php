<?php

namespace App\Providers;

use App\Models\Dealer;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('pagination.plain');
        Blade::directive('gbp', function ($expression) {
            return "<?php echo e(\\App\\Support\\Money::gbp($expression)); ?>";
        });

        View::composer(['layouts.site', 'auth.*'], function ($view): void {
            try {
                $host = request()->getHost();
                $dealer = Dealer::query()->where('website_host', $host)->first()
                    ?? Dealer::query()->orderBy('id')->first();
            } catch (\Throwable) {
                $dealer = null;
            }
            $view->with('siteDealer', $dealer);
        });

        View::composer(['layouts.dealer'], function ($view): void {
            $staff = null;
            $dealer = null;
            try {
                $staff = auth('staff')->user();
                $dealer = $staff?->dealer
                    ?? Dealer::query()->orderBy('id')->first();
            } catch (\Throwable) {
                $dealer = null;
            }
            $view->with('siteDealer', $dealer);
            $view->with('dealerStaff', $staff);
        });
    }
}
