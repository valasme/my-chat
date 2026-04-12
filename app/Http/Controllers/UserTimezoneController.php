<?php

namespace App\Http\Controllers;

use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class UserTimezoneController extends Controller
{
    private const VALID_TIMEZONES_CACHE_KEY = 'valid_timezones';

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'timezone' => ['required', 'string', 'max:64'],
        ]);

        $timezone = $request->input('timezone');

        // Cache the timezone list to avoid regenerating on every request
        $validTimezones = Cache::remember(
            self::VALID_TIMEZONES_CACHE_KEY,
            now()->addDay(),
            fn () => DateTimeZone::listIdentifiers()
        );

        if (! in_array($timezone, $validTimezones, true)) {
            return response()->json(['error' => 'Invalid timezone'], 422);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Only update if changed to avoid unnecessary DB writes
        if ($user->timezone !== $timezone) {
            $user->update(['timezone' => $timezone]);
        }

        return response()->json(['success' => true]);
    }
}
