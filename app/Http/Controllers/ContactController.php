<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ContactController extends Controller
{
    /**
     * Display a listing of contacts and pending requests.
     */
    public function index(Request $request): View
    {
        $userId = Auth::id();
        $sort = in_array($request->query('sort'), ['az', 'za'], true) ? $request->query('sort') : null;

        $query = Contact::forUser($userId)
            ->accepted()
            ->with(['user', 'contactUser']);

        if ($sort) {
            $direction = $sort === 'az' ? 'asc' : 'desc';
            $query->join('users as other_user', function ($join) use ($userId) {
                $join->whereRaw(
                    'other_user.id = CASE WHEN contacts.user_id = ? THEN contacts.contact_user_id ELSE contacts.user_id END',
                    [$userId]
                );
            })->orderBy('other_user.name', $direction)->select('contacts.*');
        } else {
            $query->latest();
        }

        $accepted = $query->paginate(15)->withQueryString();

        $incoming = Contact::incoming($userId)
            ->pending()
            ->with('user')
            ->latest()
            ->get();

        $outgoing = Contact::outgoing($userId)
            ->pending()
            ->with('contactUser')
            ->latest()
            ->get();

        return view('contacts.index', compact('accepted', 'incoming', 'outgoing', 'sort'));
    }

    /**
     * Show the form for creating a new contact request.
     */
    public function create(): View
    {
        return view('contacts.create');
    }

    /**
     * Store a newly created contact request.
     */
    public function store(StoreContactRequest $request): RedirectResponse
    {
        $targetUser = $request->targetUser();

        Contact::create([
            'user_id' => Auth::id(),
            'contact_user_id' => $targetUser->id,
            'status' => 'pending',
        ]);

        Log::info('Contact request sent', [
            'user_id' => Auth::id(),
            'target_user_id' => $targetUser->id,
        ]);

        return redirect()->route('contacts.index')
            ->with('status', __('Contact request sent.'));
    }

    /**
     * Display the specified contact.
     */
    public function show(Contact $contact): View
    {
        Gate::authorize('view', $contact);

        $contact->load(['user', 'contactUser']);

        $otherUser = $contact->getOtherUser(Auth::id());

        $conversation = Conversation::betweenUsers(Auth::id(), $otherUser->id)->first();

        return view('contacts.show', compact('contact', 'otherUser', 'conversation'));
    }

    /**
     * Update the specified contact (accept or decline).
     */
    public function update(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        $action = $request->validated('action');

        if ($action === 'accept') {
            $contact->update(['status' => 'accepted']);

            $userOneId = min($contact->user_id, $contact->contact_user_id);
            $userTwoId = max($contact->user_id, $contact->contact_user_id);

            Conversation::firstOrCreate([
                'user_one_id' => $userOneId,
                'user_two_id' => $userTwoId,
            ]);

            return redirect()->route('contacts.index')
                ->with('status', __('Contact request accepted.'));
        }

        $contact->delete();

        return redirect()->route('contacts.index')
            ->with('status', __('Contact request declined.'));
    }

    /**
     * Remove the specified contact (symmetric delete for both users).
     */
    public function destroy(Contact $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);

        Log::info('Contact deleted', [
            'user_id' => Auth::id(),
            'contact_user_id' => $contact->user_id,
            'contact_target_id' => $contact->contact_user_id,
        ]);

        DB::transaction(function () use ($contact) {
            $conversation = Conversation::betweenUsers($contact->user_id, $contact->contact_user_id)->first();

            if ($conversation) {
                $conversation->messages()->delete();
                $conversation->delete();
            }

            $contact->delete();
        });

        return redirect()->route('contacts.index')
            ->with('status', __('Contact deleted.'));
    }
}
