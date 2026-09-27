<?php

namespace App\Http\Middleware;

use App\Models\CustomerUser;
use App\Support\Locales;
use Illuminate\Http\Request;
use Inertia\Middleware;

/** Inertia for the customer portal. A future public website gets its own root view. */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'portal';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'locale' => fn () => app()->getLocale(),
            'locales' => fn () => collect(Locales::all())->map(fn ($label, $code) => ['code' => $code, 'label' => $label])->values(),
            'auth' => fn () => $this->user($request),
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            // The whole portal dictionary; components read `t.order.submit` etc.
            't' => fn () => trans('portal'),
        ];
    }

    private function user(Request $request): ?array
    {
        /** @var CustomerUser|null $user */
        $user = $request->user('customer');

        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'customer' => $user->customer->name,
        ];
    }
}
