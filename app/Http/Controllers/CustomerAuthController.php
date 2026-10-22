<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Services\CustomerDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function __construct(private CustomerDirectory $directory) {}

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'saved_ids' => ['nullable', 'string'],
        ]);

        if (! Auth::attempt(['email' => strtolower($data['email']), 'password' => $data['password']], false)) {
            throw ValidationException::withMessages(['email' => 'Those details do not match an account.']);
        }

        $request->session()->regenerate();
        $this->merge($request, $request->user());

        return redirect()->intended(route('account'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'accepted_terms' => ['accepted'],
            'saved_ids' => ['nullable', 'string'],
        ]);

        $dealer = \App\Models\Dealer::current();
        $email = strtolower($data['email']);
        $existing = Customer::query()->where('email', $email)->first();

        if ($existing && $existing->password_hash) {
            throw ValidationException::withMessages(['email' => 'An account with that email already exists. Please log in.']);
        }

        if ($existing && (int) $existing->dealer_id !== (int) $dealer->id) {
            throw ValidationException::withMessages(['email' => 'That email is already used.']);
        }

        $customer = $existing ?? new Customer(['dealer_id' => $dealer->id, 'email' => $email, 'source' => 'website_enquiry']);
        $customer->name = $data['name'];
        $customer->phone = $data['phone'];
        $customer->password_hash = $data['password'];
        $customer->save();

        Lead::query()->create([
            'customer_id' => $customer->id,
            'type' => 'general',
            'subject' => 'Account created',
            'message' => 'Customer accepted the privacy terms while creating an account.',
            'status' => 'closed',
            'accepted_terms_at' => now(),
            'created_on' => now()->toDateString(),
        ]);

        Auth::login($customer);
        $request->session()->regenerate();
        $this->merge($request, $customer);

        return redirect()->route('account');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showForgot()
    {
        return view('auth.forgot');
    }

    public function sendReset(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $customer = Customer::query()->where('email', strtolower($data['email']))->first();
        $link = null;

        if ($customer) {
            $token = Password::broker('customers')->createToken($customer);
            $link = route('password.reset', ['token' => $token, 'email' => $customer->email]);
            $customer->sendPasswordResetNotification($token);
        }

        return view('auth.forgot', [
            'sent' => true,
            'debugLink' => config('app.debug') ? $link : null,
        ]);
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker('customers')->reset(
            ['email' => strtolower($data['email']), 'password' => $data['password'], 'token' => $data['token']],
            function (Customer $customer, string $password) {
                $customer->password_hash = $password;
                $customer->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', 'Your password has been updated.');
    }

    private function merge(Request $request, Customer $customer): void
    {
        $fromForm = array_filter(explode(',', (string) $request->input('saved_ids')));
        $ids = array_merge((array) $request->session()->pull('saved_vehicle_ids', []), $fromForm);
        $this->directory->mergeSaved($customer, $ids);
    }
}
