<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Dealer;
use App\Models\StaffUser;
use App\Support\StaffPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    #[Test]
    public function customer_login_is_rate_limited(): void
    {
        $this->installSecuritySchema();
        $this->seedDealer();

        for ($i = 0; $i < 5; $i++) {
            $this->from(route('login'))
                ->post('/login', [
                    'email' => 'nobody@example.com',
                    'password' => 'wrong-password',
                ])
                ->assertRedirect('/login');
        }

        $this->from(route('login'))
            ->post('/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ])
            ->assertStatus(429);
    }

    #[Test]
    public function failed_customer_login_is_logged(): void
    {
        $this->installSecuritySchema();
        $this->seedDealer();

        Log::spy();

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => 'Nobody@Example.com',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'));

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Customer login failed'
                    && ($context['email'] ?? null) === 'nobody@example.com'
                    && array_key_exists('ip', $context);
            });
    }

    #[Test]
    public function web_responses_include_security_headers(): void
    {
        $this->installSecuritySchema();
        $this->seedDealer();

        $response = $this->get(route('login'))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-src 'self' https://maps.google.com https://www.google.com", $csp);
        $this->assertStringContainsString("font-src 'self' data:", $csp);
    }

    #[Test]
    public function robots_txt_includes_an_absolute_sitemap_url(): void
    {
        $this->installSecuritySchema();
        $this->seedDealer();

        $this->get(route('robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: *', false)
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false)
            ->assertDontSee('Sitemap: /sitemap.xml', false);
    }

    #[Test]
    public function login_form_includes_csrf_token_field(): void
    {
        $this->installSecuritySchema();
        $this->seedDealer();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="_token"', false);
    }

    #[Test]
    public function password_reset_tokens_expire_after_sixty_minutes(): void
    {
        $this->assertSame(60, (int) config('auth.passwords.customers.expire'));
        $this->assertSame(60, (int) config('auth.passwords.staff.expire'));
    }

    #[Test]
    public function forgot_password_does_not_expose_reset_link_when_debug_is_on(): void
    {
        $this->installSecuritySchema();
        $dealer = $this->seedDealer();

        config(['app.debug' => true]);

        Customer::query()->create([
            'dealer_id' => $dealer->id,
            'name' => 'Alex Customer',
            'email' => 'alex@example.com',
            'phone' => '07000000001',
            'password_hash' => 'Password1!',
            'source' => 'website_enquiry',
        ]);

        $this->post(route('password.email'), ['email' => 'alex@example.com'])
            ->assertOk()
            ->assertSee('If that email has an account')
            ->assertDontSee('Local link')
            ->assertDontSee('set a new password');
    }

    #[Test]
    public function browser_database_migrate_requires_admin_settings_permission(): void
    {
        $this->assertTrue(
            collect(Route::getRoutes())->contains(
                fn ($route) => $route->getName() === 'dealer.settings.migrate'
            )
        );

        $this->installSecuritySchema();
        $dealer = $this->seedDealer();

        $sales = StaffUser::query()->create([
            'dealer_id' => $dealer->id,
            'name' => 'Sales Person',
            'email' => 'sales@example.com',
            'password_hash' => 'Password1!',
            'role' => 'sales',
            'permissions' => StaffPermissions::preset('sales'),
            'is_active' => true,
        ]);

        $this->actingAs($sales, 'staff')
            ->post(route('dealer.settings.migrate'))
            ->assertForbidden();
    }

    #[Test]
    public function non_admin_staff_cannot_assign_admin_role(): void
    {
        $this->installSecuritySchema();
        $dealer = $this->seedDealer();

        $manager = StaffUser::query()->create([
            'dealer_id' => $dealer->id,
            'name' => 'Pat Manager',
            'email' => 'pat@example.com',
            'password_hash' => 'Password1!',
            'role' => 'sales',
            'permissions' => array_values(array_unique(array_merge(
                StaffPermissions::preset('sales'),
                ['staff.manage']
            ))),
            'is_active' => true,
        ]);

        $this->actingAs($manager, 'staff')
            ->from(route('dealer.staff'))
            ->post(route('dealer.staff.store'), [
                'name' => 'New Admin',
                'email' => 'new-admin@example.com',
                'role' => 'admin',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('staff_users', [
            'email' => 'new-admin@example.com',
        ]);
    }

    #[Test]
    public function svg_logo_uploads_are_rejected(): void
    {
        $this->installSecuritySchema();
        $dealer = $this->seedDealer();

        $admin = StaffUser::query()->create([
            'dealer_id' => $dealer->id,
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password_hash' => 'Password1!',
            'role' => 'admin',
            'is_active' => true,
        ]);

        Storage::fake('public');

        $svg = UploadedFile::fake()->create('logo.svg', 20, 'image/svg+xml');

        $this->actingAs($admin, 'staff')
            ->from(route('dealer.settings'))
            ->post(route('dealer.settings.update'), [
                'name' => $dealer->name,
                'phone' => '020 0000 0000',
                'logo' => $svg,
            ])
            ->assertSessionHasErrors('logo');
    }

    #[Test]
    public function invoice_totals_are_computed_server_side_not_from_client_grand_total(): void
    {
        $source = file_get_contents(app_path('Services/InvoiceService.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('private function buildLines', $source);
        $this->assertStringNotContainsString('grand_total', $source);
        $this->assertStringContainsString('balancePence', $source);
    }

    private function seedDealer(): Dealer
    {
        return Dealer::query()->create([
            'integration_id' => 1,
            'name' => 'Ghani Motors',
            'website_host' => 'localhost',
            'phone' => '020 0000 0000',
        ]);
    }

    private function installSecuritySchema(): void
    {
        foreach ([
            'password_reset_tokens',
            'activity_logs',
            'customers',
            'staff_users',
            'dealers',
            'integrations',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('dealers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integration_id');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('trading_name')->nullable();
            $table->string('tagline')->nullable();
            $table->string('email')->nullable();
            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('postcode')->nullable();
            $table->string('opening_hours')->nullable();
            $table->string('website_host')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->json('website_settings')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dealer_id');
            $table->string('name');
            $table->string('email');
            $table->string('password_hash');
            $table->string('role')->default('sales');
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dealer_id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('password_hash')->nullable();
            $table->string('source')->default('website_enquiry');
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_user_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->string('message');
            $table->timestamps();
        });
    }
}
