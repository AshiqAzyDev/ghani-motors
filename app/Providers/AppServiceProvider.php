<?php

namespace App\Providers;

use App\Models\Dealer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

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

        Password::defaults(fn () => Password::min(8)->letters()->mixedCase()->numbers());

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();

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

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::transliterate(
                Str::lower((string) $request->input('email')).'|'.$request->ip()
            ));
        });

        RateLimiter::for('password-email', function (Request $request) {
            return Limit::perMinute(5)->by(Str::transliterate(
                Str::lower((string) $request->input('email')).'|'.$request->ip()
            ));
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('public-forms', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
