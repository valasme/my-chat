<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIgnoreRequest;
use App\Models\Ignore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class IgnoreController extends Controller
{
    public function index(Request $request): View
    {
        $sort = in_array($request->query('sort'), ['az', 'za'], true) ? $request->query('sort') : null;

        $query = Ignore::forIgnorer(Auth::id())->active()->with('ignored');

        if ($sort) {
            $direction = $sort === 'az' ? 'asc' : 'desc';
            $query->join('users', 'users.id', '=', 'ignores.ignored_id')
                ->orderBy('users.name', $direction)
                ->select('ignores.*');
        } else {
            $query->latest();
        }

        $ignores = $query->paginate(15)->withQueryString();

        return view('ignores.index', compact('ignores', 'sort'));
    }

    public function store(StoreIgnoreRequest $request): RedirectResponse
    {
        Gate::authorize('create', Ignore::class);

        $duration = $request->validated('duration');
        $expiresAt = match ($duration) {
            '1h' => now()->addHour(),
            '8h' => now()->addHours(8),
            '24h' => now()->addDay(),
            '3d' => now()->addDays(3),
            '7d' => now()->addWeek(),
            'custom' => $request->validated('expires_at'),
        };

        Ignore::create([
            'ignorer_id' => Auth::id(),
            'ignored_id' => $request->validated('user_id'),
            'expires_at' => $expiresAt,
        ]);

        Log::info('User ignored', [
            'ignorer_id' => Auth::id(),
            'ignored_id' => $request->validated('user_id'),
            'expires_at' => $expiresAt,
        ]);

        return redirect()->route('contacts.index')
            ->with('status', __('User ignored.'));
    }

    public function destroy(Ignore $ignore): RedirectResponse
    {
        Gate::authorize('delete', $ignore);

        Log::info('Ignore cancelled', [
            'ignorer_id' => $ignore->ignorer_id,
            'ignored_id' => $ignore->ignored_id,
        ]);

        $ignore->delete();

        return redirect()->route('ignores.index')
            ->with('status', __('Ignore cancelled.'));
    }
}
