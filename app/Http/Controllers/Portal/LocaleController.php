<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetPortalLocale;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /** Remembered on the login itself, so mails follow the language they chose on screen. */
    public function update(Request $request, string $locale): RedirectResponse
    {
        abort_unless(Locales::supports($locale), 404);

        $request->session()->put(SetPortalLocale::SESSION_KEY, $locale);
        $request->user('customer')?->update(['locale' => $locale]);

        return back();
    }
}
