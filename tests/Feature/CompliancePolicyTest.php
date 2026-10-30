<?php

namespace Tests\Feature;

use App\Models\CompliancePolicy;
use App\Models\Dealer;
use App\Models\StaffUser;
use App\Support\StaffPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompliancePolicyTest extends TestCase
{
    #[Test]
    public function admin_can_upload_a_compliance_policy_pdf(): void
    {
        $this->installSchema();
        $dealer = $this->seedDealer();
        $admin = $this->seedAdmin($dealer);
        Storage::fake('public');

        $pdf = UploadedFile::fake()->create('privacy-policy.pdf', 120, 'application/pdf');

        $this->actingAs($admin, 'staff')
            ->from(route('dealer.policies'))
            ->post(route('dealer.policies.store'), [
                'name' => 'Privacy Policy',
                'pdf' => $pdf,
            ])
            ->assertRedirect(route('dealer.policies'));

        $policy = CompliancePolicy::query()->first();
        $this->assertNotNull($policy);
        $this->assertSame('Privacy Policy', $policy->name);
        $this->assertSame($dealer->id, $policy->dealer_id);
        Storage::disk('public')->assertExists($policy->pdf_path);

        $this->actingAs($admin, 'staff')
            ->get(route('dealer.policies'))
            ->assertOk()
            ->assertSee('Privacy Policy');
    }

    #[Test]
    public function about_page_lists_policies_as_download_buttons(): void
    {
        $this->installSchema();
        $dealer = $this->seedDealer();
        Storage::fake('public');

        $path = UploadedFile::fake()
            ->create('terms.pdf', 40, 'application/pdf')
            ->store('compliance-policies/'.$dealer->id, 'public');

        $policy = CompliancePolicy::query()->create([
            'dealer_id' => $dealer->id,
            'name' => 'Terms of Business',
            'pdf_path' => $path,
            'original_filename' => 'terms.pdf',
            'sort_order' => 1,
        ]);

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Compliance Policies')
            ->assertSee('Terms of Business')
            ->assertSee(route('compliance-policies.download', $policy), false);
    }

    #[Test]
    public function public_download_returns_the_pdf_for_current_dealer(): void
    {
        $this->installSchema();
        $dealer = $this->seedDealer();
        Storage::fake('public');

        $path = 'compliance-policies/'.$dealer->id.'/policy.pdf';
        Storage::disk('public')->put($path, '%PDF-1.4 fake');

        $policy = CompliancePolicy::query()->create([
            'dealer_id' => $dealer->id,
            'name' => 'Complaints Procedure',
            'pdf_path' => $path,
            'original_filename' => 'complaints.pdf',
            'sort_order' => 1,
        ]);

        $this->get(route('compliance-policies.download', $policy))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    #[Test]
    public function non_admin_staff_cannot_manage_policies(): void
    {
        $this->installSchema();
        $dealer = $this->seedDealer();
        Storage::fake('public');

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
            ->get(route('dealer.policies'))
            ->assertForbidden();

        $pdf = UploadedFile::fake()->create('policy.pdf', 20, 'application/pdf');

        $this->actingAs($sales, 'staff')
            ->post(route('dealer.policies.store'), [
                'name' => 'Should Fail',
                'pdf' => $pdf,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('compliance_policies', 0);
    }

    #[Test]
    public function download_rejects_policies_from_another_dealer(): void
    {
        $this->installSchema();
        $this->seedDealer();
        Storage::fake('public');

        $other = Dealer::query()->create([
            'integration_id' => 1,
            'name' => 'Other Motors',
            'website_host' => 'other.test',
            'phone' => '020 1111 1111',
        ]);

        $path = 'compliance-policies/'.$other->id.'/secret.pdf';
        Storage::disk('public')->put($path, '%PDF-1.4 secret');

        $policy = CompliancePolicy::query()->create([
            'dealer_id' => $other->id,
            'name' => 'Other Dealer Policy',
            'pdf_path' => $path,
            'original_filename' => 'secret.pdf',
            'sort_order' => 1,
        ]);

        $this->get(route('compliance-policies.download', $policy))
            ->assertNotFound();
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

    private function seedAdmin(Dealer $dealer): StaffUser
    {
        return StaffUser::query()->create([
            'dealer_id' => $dealer->id,
            'name' => 'Site Admin',
            'email' => 'admin@example.com',
            'password_hash' => 'Password1!',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    private function installSchema(): void
    {
        foreach (['compliance_policies', 'staff_users', 'dealers', 'integrations'] as $table) {
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
            $table->timestamps();
        });

        Schema::create('compliance_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dealer_id');
            $table->string('name', 160);
            $table->string('pdf_path');
            $table->string('original_filename');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }
}
