<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;

/**
 * The admin and the portal share one session, and so one `url.intended`. A customer
 * who once opened /admin would otherwise land on the staff login after logging in to
 * the portal (and staff on the portal login the other way round). Only follow it
 * when it points into the area that was just logged in to.
 */
class IntendedUrl
{
    public static function redirect(string $area, string $default): RedirectResponse
    {
        $intended = session()->pull('url.intended');
        $path = '/'.trim((string) parse_url((string) $intended, PHP_URL_PATH), '/');
        $area = '/'.trim($area, '/');

        return redirect()->to($path === $area || str_starts_with($path, $area.'/') ? $intended : $default);
    }
}
