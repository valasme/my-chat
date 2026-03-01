<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ContactController extends Controller
{
    /**
     * Display a listing of the user's contacts.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Contact::class);

        $query = $request->user()->contacts()->with('person');

        if ($search = $request->input('search')) {
            $query->whereHas('person', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $sortField = $request->input('sort', 'name');
        $sortDirection = $request->input('direction', 'asc');

        $allowedSortFields = ['name', 'email'];
        $validSortField = in_array($sortField, $allowedSortFields) ? $sortField : 'name';
        $validDirection = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'asc';

        $query->join('users as contact_user', 'contacts.contact_id', '=', 'contact_user.id')
            ->orderBy("contact_user.{$validSortField}", $validDirection)
            ->select('contacts.*');

        $contacts = $query->paginate(25)->withQueryString();

        return view('contacts.index', [
            'contacts' => $contacts,
            'search' => $search,
            'sort' => $validSortField,
            'direction' => $validDirection,
        ]);
    }

    /**
     * Show the form for adding a new contact by email.
     */
    public function create(): View
    {
        Gate::authorize('create', Contact::class);

        return view('contacts.create');
    }

    /**
     * Search for a user by email and add them as a contact.
     */
    public function store(StoreContactRequest $request): RedirectResponse
    {
        Gate::authorize('create', Contact::class);

        /** @var User $currentUser */
        $currentUser = $request->user();
        $email = $request->validated('email');

        $targetUser = User::where('email', $email)->first();

        if (! $targetUser) {
            return back()
                ->withInput()
                ->withErrors(['email' => 'No user found with that email address.']);
        }

        if ($targetUser->id === $currentUser->id) {
            return back()
                ->withInput()
                ->withErrors(['email' => 'You cannot add yourself as a contact.']);
        }

        if ($currentUser->hasContact($targetUser)) {
            return back()
                ->withInput()
                ->withErrors(['email' => 'This user is already in your contacts.']);
        }

        try {
            $currentUser->contacts()->create([
                'contact_id' => $targetUser->id,
            ]);
        } catch (QueryException $e) {
            return back()
                ->withInput()
                ->withErrors(['email' => 'Unable to add this contact. Please try again.']);
        }

        return redirect()->route('contacts.index')
            ->with('success', "{$targetUser->name} has been added to your contacts.");
    }

    /**
     * Display the specified contact.
     */
    public function show(Contact $contact): View
    {
        Gate::authorize('view', $contact);

        $contact->load('person');

        return view('contacts.show', [
            'contact' => $contact,
        ]);
    }

    /**
     * Remove the specified contact.
     */
    public function destroy(Contact $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);

        $name = $contact->person->name;

        $contact->delete();

        return redirect()->route('contacts.index')
            ->with('success', "{$name} has been removed from your contacts.");
    }
}
