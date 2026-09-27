<?php

namespace App\Http\Middleware;

use App\Models\CustomerUser;
use App\Support\Locales;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The portal's language: the signed-in login's preference, else what the
 * visitor picked on the login page, else their browser's language, else the base language.
 */
class SetPortalLocale
{
    public const SESSION_KEY = 'portal_locale';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var CustomerUser|null $user */
        $user = $request->user('customer');

        $locale = $user?->preferredLocale()
            ?? (Locales::supports($request->session()->get(self::SESSION_KEY)) ? $request->session()->get(self::SESSION_KEY) : null)
            ?? $request->getPreferredLanguage(Locales::codes())
            ?? Locales::default();

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
