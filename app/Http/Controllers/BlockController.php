<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBlockRequest;
use App\Models\Block;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Ignore;
use App\Models\Trash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BlockController extends Controller
{
    public function index(Request $request): View
    {
        $sort = in_array($request->query('sort'), ['az', 'za'], true) ? $request->query('sort') : null;

        $query = Block::forBlocker(Auth::id())->with('blocked');

        if ($sort) {
            $direction = $sort === 'az' ? 'asc' : 'desc';
            $query->join('users', 'users.id', '=', 'blocks.blocked_id')
                ->orderBy('users.name', $direction)
                ->select('blocks.*');
        } else {
            $query->latest();
        }

        $blocks = $query->paginate(15)->withQueryString();

        return view('blocks.index', compact('blocks', 'sort'));
    }

    public function store(StoreBlockRequest $request): RedirectResponse
    {
        Gate::authorize('create', Block::class);

        $userId = Auth::id();
        $targetUserId = (int) $request->validated('user_id');

        DB::transaction(function () use ($userId, $targetUserId) {
            Ignore::where(function ($q) use ($userId, $targetUserId) {
                $q->where('ignorer_id', $userId)->where('ignored_id', $targetUserId);
            })->orWhere(function ($q) use ($userId, $targetUserId) {
                $q->where('ignorer_id', $targetUserId)->where('ignored_id', $userId);
            })->delete();

            $contact = Contact::between($userId, $targetUserId)->first();
            if ($contact) {
                Trash::where('contact_id', $contact->id)->delete();
            }

            $conversation = Conversation::betweenUsers($userId, $targetUserId)->first();
            if ($conversation) {
                $conversation->messages()->delete();
                $conversation->delete();
            }

            if ($contact) {
                $contact->delete();
            }

            Block::create([
                'blocker_id' => $userId,
                'blocked_id' => $targetUserId,
            ]);
        });

        Log::info('User blocked', ['blocker_id' => $userId, 'blocked_id' => $targetUserId]);

        return redirect()->route('contacts.index')
            ->with('status', __('User blocked.'));
    }

    public function destroy(Block $block): RedirectResponse
    {
        Gate::authorize('delete', $block);

        Log::info('User unblocked', ['blocker_id' => $block->blocker_id, 'blocked_id' => $block->blocked_id]);

        $block->delete();

        return redirect()->route('blocks.index')
            ->with('status', __('User unblocked.'));
    }
}
