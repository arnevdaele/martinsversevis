<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetPortalLocale;
use App\Models\CustomerUser;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var CustomerUser $user */
        $user = $request->user('customer');
        $customer = $user->customer;

        return Inertia::render('Account/Edit', [
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'receivesOrderConfirmations' => $user->receives_order_confirmations,
                'locale' => $user->preferredLocale(),
            ],
            'customer' => [
                'name' => $customer->name,
                'vatNumber' => $customer->vat_number,
                'address' => $customer->address(),
                'phone' => $customer->phone,
            ],
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:customer'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        $request->user('customer')->update(['password' => $data['password']]);

        return back()->with('success', __('portal.account.saved'));
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'receives_order_confirmations' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', Rule::in(Locales::codes())],
        ]);

        $request->user('customer')->update($data);

        if (isset($data['locale'])) {
            $request->session()->put(SetPortalLocale::SESSION_KEY, $data['locale']);
            app()->setLocale($data['locale']);
        }

        return back()->with('success', __('portal.account.preferences_saved'));
    }
}
