<?php

namespace App\Http\Middleware;

use App\Models\CustomerUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A login that was valid when the session started may have been switched off
 * since (or the whole customer archived). Check on every request, not only at
 * sign-in, so switching someone off in the admin takes effect immediately.
 */
class EnsureCustomerCanLogIn
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var CustomerUser|null $user */
        $user = Auth::guard('customer')->user();

        if ($user && ! $user->canLogIn()) {
            Auth::guard('customer')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('portal.login')->withErrors(['email' => __('portal.auth.inactive')]);
        }

        return $next($request);
    }
}
