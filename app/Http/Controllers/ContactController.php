<?php

/**
 * Contact Controller
 *
 * Handles all CRUD operations for the user-to-user contact system.
 * Contacts work like Discord friends — you search by email, and if the
 * user exists in the database they get saved as your contact.
 *
 * Available actions:
 *   GET    /contacts           → index()   List contacts with search/sort/pagination.
 *   GET    /contacts/create    → create()  Show the "add contact by email" form.
 *   POST   /contacts           → store()   Look up a user by email and add as contact.
 *   GET    /contacts/{id}      → show()    Display a single contact's profile.
 *   DELETE /contacts/{id}      → destroy() Remove a contact relationship.
 *
 * Authorization is enforced via ContactPolicy through Gate::authorize().
 * All routes require 'auth' and 'verified' middleware (see routes/web.php).
 *
 * @see \App\Models\Contact
 * @see \App\Policies\ContactPolicy
 * @see \App\Http\Requests\StoreContactRequest
 */

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
     * Allowed columns for sorting contacts.
     *
     * This whitelist prevents SQL injection through sort parameters.
     * Only these column names are interpolated into the ORDER BY clause.
     *
     * @var list<string>
     */
    private const ALLOWED_SORT_FIELDS = ['name', 'email'];

    /**
     * Allowed sort directions.
     *
     * @var list<string>
     */
    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    /**
     * Number of contacts to display per page.
     */
    private const PER_PAGE = 25;

    /**
     * Maximum length of search input to process.
     *
     * Prevents excessively long search strings from reaching the database.
     */
    private const MAX_SEARCH_LENGTH = 100;

    /**
     * Display a paginated listing of the authenticated user's contacts.
     *
     * Supports searching by the contact person's name or email, sorting
     * by name or email in ascending/descending order, and pagination.
     * Invalid sort/direction values silently fall back to safe defaults.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Contact::class);

        $query = $request->user()->contacts()->with('person');

        $search = $this->sanitizeSearch($request->input('search'));

        if ($search !== null) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $pattern = '%'.$escaped.'%';

            $query->whereHas('person', function ($q) use ($pattern) {
                $q->whereRaw("name LIKE ? ESCAPE '\\'", [$pattern])
                    ->orWhereRaw("email LIKE ? ESCAPE '\\'", [$pattern]);
            });
        }

        $validSortField = $this->validateSortField($request->input('sort', 'name'));
        $validDirection = $this->validateDirection($request->input('direction', 'asc'));

        $query->join('users as contact_user', 'contacts.contact_id', '=', 'contact_user.id')
            ->orderBy("contact_user.{$validSortField}", $validDirection)
            ->select('contacts.*');

        $contacts = $query->paginate(self::PER_PAGE)->withQueryString();

        return view('contacts.index', [
            'contacts' => $contacts,
            'search' => $search,
            'sort' => $validSortField,
            'direction' => $validDirection,
        ]);
    }

    /**
     * Show the form for adding a new contact by email address.
     */
    public function create(): View
    {
        Gate::authorize('create', Contact::class);

        return view('contacts.create');
    }

    /**
     * Search for a user by email and add them as a contact.
     *
     * Validates the email, then performs three guard checks:
     *   1. User exists in the database.
     *   2. User is not the authenticated user (no self-add).
     *   3. User is not already in the authenticated user's contacts.
     *
     * A try/catch around the insert handles the rare race condition where
     * the unique constraint is violated between the hasContact() check
     * and the actual INSERT.
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
                ->withErrors(['email' => __('No user found with that email address.')]);
        }

        if ($targetUser->id === $currentUser->id) {
            return back()
                ->withInput()
                ->withErrors(['email' => __('You cannot add yourself as a contact.')]);
        }

        if ($currentUser->hasContact($targetUser)) {
            return back()
                ->withInput()
                ->withErrors(['email' => __('This user is already in your contacts.')]);
        }

        try {
            $currentUser->contacts()->create([
                'contact_id' => $targetUser->id,
            ]);
        } catch (QueryException) {
            return back()
                ->withInput()
                ->withErrors(['email' => __('Unable to add this contact. Please try again.')]);
        }

        return redirect()->route('contacts.index')
            ->with('success', __(':name has been added to your contacts.', ['name' => $targetUser->name]));
    }

    /**
     * Display the specified contact's profile.
     *
     * Eager-loads the person relationship to render their name,
     * email, and account dates on the detail page.
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
     * Remove the specified contact from the authenticated user's list.
     *
     * Loads the person name before deletion so it can be included in
     * the success flash message shown after redirect.
     */
    public function destroy(Contact $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);

        $name = $contact->person->name;

        $contact->delete();

        return redirect()->route('contacts.index')
            ->with('success', __(':name has been removed from your contacts.', ['name' => $name]));
    }

    /**
     * Sanitize and truncate the search input.
     *
     * Returns null if the input is empty or only whitespace, ensuring
     * the controller never runs a LIKE query with a blank pattern.
     */
    private function sanitizeSearch(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $cleaned = trim($input);

        if ($cleaned === '') {
            return null;
        }

        return mb_substr($cleaned, 0, self::MAX_SEARCH_LENGTH);
    }

    /**
     * Validate and return a safe sort field.
     *
     * Falls back to 'name' if the provided value isn't in the whitelist,
     * preventing any possibility of SQL injection through the sort param.
     */
    private function validateSortField(string $field): string
    {
        return in_array($field, self::ALLOWED_SORT_FIELDS, true) ? $field : 'name';
    }

    /**
     * Validate and return a safe sort direction.
     *
     * Falls back to 'asc' if the provided value isn't 'asc' or 'desc'.
     */
    private function validateDirection(string $direction): string
    {
        return in_array($direction, self::ALLOWED_DIRECTIONS, true) ? $direction : 'asc';
    }
}
