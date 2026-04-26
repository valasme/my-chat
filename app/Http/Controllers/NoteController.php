<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Models\Contact;
use App\Models\Note;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NoteController extends Controller
{
    public function index(): View
    {
        return view('notes.index');
    }

    public function create(): View
    {
        $contacts = Contact::forUser(Auth::id())
            ->accepted()
            ->with(['user', 'contactUser'])
            ->get();

        return view('notes.create', compact('contacts'));
    }

    public function store(StoreNoteRequest $request): RedirectResponse
    {
        Note::create([
            ...$request->validated(),
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('notes.index')
            ->with('status', __('Note created.'));
    }

    public function show(Note $note): View
    {
        Gate::authorize('view', $note);

        $note->load('contact.user', 'contact.contactUser');

        return view('notes.show', compact('note'));
    }

    public function edit(Note $note): View
    {
        Gate::authorize('update', $note);

        $contacts = Contact::forUser(Auth::id())
            ->accepted()
            ->with(['user', 'contactUser'])
            ->get();

        return view('notes.edit', compact('note', 'contacts'));
    }

    public function update(UpdateNoteRequest $request, Note $note): RedirectResponse
    {
        $note->update($request->validated());

        return redirect()->route('notes.show', $note)
            ->with('status', __('Note updated.'));
    }

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
}
