<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTrashRequest;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Trash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class TrashController extends Controller
{
    public function index(Request $request): View
    {
        $userId = Auth::id();
        $sort = in_array($request->query('sort'), ['az', 'za'], true) ? $request->query('sort') : null;

        $query = Trash::forUser($userId)->with(['contact.user', 'contact.contactUser']);

        if ($sort) {
            $direction = $sort === 'az' ? 'asc' : 'desc';
            $query->join('contacts', 'contacts.id', '=', 'trashes.contact_id')
                ->join('users as other_user', function ($join) use ($userId) {
                    $join->whereRaw(
                        'other_user.id = CASE WHEN contacts.user_id = ? THEN contacts.contact_user_id ELSE contacts.user_id END',
                        [$userId]
                    );
                })
                ->orderBy('other_user.name', $direction)
                ->select('trashes.*');
        } else {
            $query->latest();
        }

        $trashes = $query->paginate(15)->withQueryString();

        return view('trashes.index', compact('trashes', 'sort'));
    }

    public function store(StoreTrashRequest $request): RedirectResponse
    {
        Gate::authorize('create', Trash::class);

        $isQuickDelete = (bool) $request->validated('is_quick_delete', false);

        if ($isQuickDelete) {
            $contact = Contact::find($request->validated('contact_id'));

            if (! $contact) {
                return redirect()->route('contacts.index')
                    ->withErrors(['contact_id' => __('Contact not found.')]);
            }

            DB::transaction(function () use ($contact) {
                $conversation = Conversation::betweenUsers($contact->user_id, $contact->contact_user_id)->first();

                if ($conversation) {
                    $conversation->messages()->delete();
                }

                Trash::create([
                    'user_id' => Auth::id(),
                    'contact_id' => $contact->id,
                    'expires_at' => now()->addDays(7),
                    'is_quick_delete' => true,
                ]);
            });

            Log::info('Contact quick-deleted', [
                'user_id' => Auth::id(),
                'contact_id' => $contact->id,
            ]);

            return redirect()->route('contacts.index')
                ->with('status', __('Contact quick-deleted. Messages erased.'));
        }

        $duration = $request->validated('duration');
        $expiresAt = match ($duration) {
            '7d' => now()->addDays(7),
            '14d' => now()->addDays(14),
            '30d' => now()->addDays(30),
            '60d' => now()->addDays(60),
            'custom' => $request->validated('expires_at'),
            default => throw new \InvalidArgumentException("Invalid duration: {$duration}"),
        };

        Trash::create([
            'user_id' => Auth::id(),
            'contact_id' => $request->validated('contact_id'),
            'expires_at' => $expiresAt,
        ]);

        Log::info('Contact trashed', [
            'user_id' => Auth::id(),
            'contact_id' => $request->validated('contact_id'),
            'expires_at' => $expiresAt,
        ]);

        return redirect()->route('contacts.index')
            ->with('status', __('Contact moved to trash.'));
    }

    public function destroy(Trash $trash): RedirectResponse
    {
        Gate::authorize('delete', $trash);

        $trash->delete();

        return redirect()->route('trashes.index')
            ->with('status', __('Contact restored from trash.'));
    }

    public function forceDelete(Trash $trash): RedirectResponse
    {
        Gate::authorize('forceDelete', $trash);

        Log::info('Contact force-deleted from trash', [
            'user_id' => Auth::id(),
            'trash_id' => $trash->id,
            'contact_id' => $trash->contact_id,
        ]);

        DB::transaction(function () use ($trash) {
            $contact = $trash->contact;

            if ($contact) {
                $conversation = Conversation::betweenUsers($contact->user_id, $contact->contact_user_id)->first();
                if ($conversation) {
                    $conversation->messages()->delete();
                    $conversation->delete();
                }

                $contact->delete();
            }

            $trash->delete();
        });

        return redirect()->route('trashes.index')
            ->with('status', __('Contact permanently deleted.'));
    }
}
