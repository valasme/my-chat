<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Models\Contact;
use App\Models\Note;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NoteController extends Controller
{
    /**
     * Display a listing of notes.
     */
    public function index(): View
    {
        return view('notes.index');
    }

    /**
     * Show the form for creating a new note.
     */
    public function create(): View
    {
        return view('notes.create', ['contacts' => $this->userContacts()]);
    }

    /**
     * Store a newly created note.
     */
    public function store(StoreNoteRequest $request): RedirectResponse
    {
        Note::create([
            ...$request->validated(),
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('notes.index')
            ->with('status', __('Note created.'));
    }

    /**
     * Display the specified note.
     */
    public function show(Note $note): View
    {
        Gate::authorize('view', $note);

        $note->load('contact.user', 'contact.contactUser');

        return view('notes.show', compact('note'));
    }

    /**
     * Show the form for editing the specified note.
     */
    public function edit(Note $note): View
    {
        Gate::authorize('update', $note);

        return view('notes.edit', ['note' => $note, 'contacts' => $this->userContacts()]);
    }

    /**
     * Update the specified note.
     */
    public function update(UpdateNoteRequest $request, Note $note): RedirectResponse
    {
        Gate::authorize('update', $note);

        $note->update($request->validated());

        return redirect()->route('notes.show', $note)
            ->with('status', __('Note updated.'));
    }

    /**
     * Soft-delete the specified note.
     */
    public function destroy(Note $note): RedirectResponse
    {
        Gate::authorize('delete', $note);

        $note->delete();

        return redirect()->route('notes.index')
            ->with('status', __('Note moved to trash.'));
    }

    /**
     * Restore a soft-deleted note. Uses manual fetch because model binding excludes trashed records.
     */
    public function restore(int $note): RedirectResponse
    {
        $noteModel = Note::withTrashed()->findOrFail($note);

        Gate::authorize('restore', $noteModel);

        $noteModel->restore();

        return redirect()->route('notes.index')
            ->with('status', __('Note restored.'));
    }

    /**
     * Permanently delete a soft-deleted note.
     */
    public function forceDelete(int $note): RedirectResponse
    {
        $noteModel = Note::withTrashed()->findOrFail($note);

        Gate::authorize('forceDelete', $noteModel);

        $noteModel->forceDelete();

        return redirect()->route('notes.index')
            ->with('status', __('Note permanently deleted.'));
    }

    /**
     * Fetch the authenticated user's accepted contacts for note forms.
     */
    private function userContacts(): Collection
    {
        return Contact::forUser(Auth::id())
            ->accepted()
            ->with(['user', 'contactUser'])
            ->get();
    }
}
