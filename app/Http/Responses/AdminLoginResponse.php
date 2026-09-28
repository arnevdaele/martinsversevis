<?php

namespace App\Http\Responses;

use App\Support\IntendedUrl;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;

/** Filament's own, but ignoring an intended URL that points into the portal. */
class AdminLoginResponse implements LoginResponse
{
    public function toResponse($request): RedirectResponse
    {
        return IntendedUrl::redirect(Filament::getCurrentOrDefaultPanel()->getPath(), Filament::getUrl());
    }
}
