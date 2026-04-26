<?php

namespace App\Livewire;

use App\Models\Note;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class NoteIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filter = 'all';

    public string $sort = 'latest';

    public string $view = 'active';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function updatedView(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function notes(): LengthAwarePaginator
    {
        $query = $this->view === 'trashed'
            ? Note::onlyTrashed()->forUser(Auth::id())
            : Note::forUser(Auth::id());

        if ($this->search !== '') {
            $query->where('title', 'like', '%'.$this->search.'%');
        }

        if ($this->filter === 'personal') {
            $query->whereNull('contact_id');
        } elseif ($this->filter === 'contact') {
            $query->whereNotNull('contact_id');
        }

        if ($this->sort === 'az') {
            $query->orderBy('title', 'asc');
        } elseif ($this->sort === 'za') {
            $query->orderBy('title', 'desc');
        } else {
            $query->latest();
        }

        return $query->with(['contact.user', 'contact.contactUser'])->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.note-index', [
            'notes' => $this->notes,
        ]);
    }
}
