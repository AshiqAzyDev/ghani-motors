<?php

namespace App\Http\Requests;

use App\Models\Dealer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;

class StoreSetupDealerRequest extends FormRequest
{
    public function authorize(): bool
    {
        try {
            // Allow first-time setup only when no dealer row exists yet.
            if (! Schema::hasTable('dealers')) {
                return true;
            }

            return ! Dealer::query()->exists();
        } catch (\Throwable) {
            // Fail closed if the database check itself fails.
            return false;
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'trading_name' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'postcode' => ['nullable', 'string', 'max:16'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'opening_hours' => ['nullable', 'string', 'max:255'],
            'website_host' => ['required', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:160'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $host = trim((string) $this->input('website_host', ''));
        if ($host === '') {
            // FormRequest extends Request, so getHost() is available here.
            $host = $this->getHost();
        }

        $merge = [
            'website_host' => strtolower($host),
        ];

        // Only default trading_name once name is present; otherwise leave it
        // alone so the required `name` rule can fail cleanly.
        if (! $this->filled('trading_name') && $this->filled('name')) {
            $merge['trading_name'] = $this->input('name');
        }

        $this->merge($merge);
    }
}
