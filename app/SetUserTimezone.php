<?php

namespace App;

use Closure;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class SetUserTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->timezone && $this->isValidTimezone($user->timezone)) {
            Config::set('app.timezone', $user->timezone);
            date_default_timezone_set($user->timezone);
        }

        return $next($request);
    }

    private function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }
}
