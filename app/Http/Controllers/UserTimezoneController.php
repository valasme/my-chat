<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserTimezoneRequest;
use App\Models\User;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class UserTimezoneController extends Controller
{
    private const VALID_TIMEZONES_CACHE_KEY = 'valid_timezones';

    public function update(UpdateUserTimezoneRequest $request): JsonResponse
    {
        $timezone = $request->validated('timezone');

        // Cache the timezone list to avoid regenerating on every request
        $validTimezones = Cache::remember(
            self::VALID_TIMEZONES_CACHE_KEY,
            now()->addDay(),
            fn () => DateTimeZone::listIdentifiers()
        );

        if (! in_array($timezone, $validTimezones, true)) {
            return response()->json(['error' => 'Invalid timezone'], 422);
        }

        /** @var User $user */
        $user = Auth::user();

        // Only update if changed to avoid unnecessary DB writes
        if ($user->timezone !== $timezone) {
            $user->update(['timezone' => $timezone]);
        }

        return response()->json(['success' => true]);
    }
}
