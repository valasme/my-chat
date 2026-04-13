<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Ignore;
use App\Models\Message;
use App\Models\Trash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(Request $request): View
    {
        $userId = Auth::id();
        $sort = in_array($request->query('sort'), ['az', 'za'], true) ? $request->query('sort') : null;

        $query = Conversation::forUser($userId)
            ->excludingIgnoredAndTrashed($userId)
            ->with(['userOne', 'userTwo', 'messages' => fn ($q) => $q->latest()->limit(1)]);

        if ($sort) {
            $direction = $sort === 'az' ? 'asc' : 'desc';
            $query->join('users as other_user', function ($join) use ($userId) {
                $join->whereRaw(
                    'other_user.id = CASE WHEN conversations.user_one_id = ? THEN conversations.user_two_id ELSE conversations.user_one_id END',
                    [$userId]
                );
            })->orderBy('other_user.name', $direction)->select('conversations.*');
        } else {
            $query->orderByDesc(
                Message::select('created_at')
                    ->whereColumn('conversation_id', 'conversations.id')
                    ->latest()
                    ->limit(1)
            );
        }

        $conversations = $query->paginate(15)->withQueryString();

        return view('conversations.index', compact('conversations', 'sort'));
    }

    public function show(Request $request, Conversation $conversation): View
    {
        Gate::authorize('view', $conversation);

        $conversation->load(['userOne', 'userTwo']);

        $messagesQuery = $conversation->messages()->with('sender')->oldest();

        if (! $request->has('page')) {
            $total = $messagesQuery->count();
            $lastPage = max(1, (int) ceil($total / 50));
            $messages = $messagesQuery->paginate(50, ['*'], 'page', $lastPage);
        } else {
            $messages = $messagesQuery->paginate(50);
        }

        $otherUser = $conversation->getOtherUser(Auth::id());

        $userId = Auth::id();
        $otherUserId = $otherUser->id;

        $isIgnored = Ignore::where('ignorer_id', $otherUserId)
            ->where('ignored_id', $userId)
            ->active()
            ->first();

        $isBlocked = Block::where(function ($q) use ($userId, $otherUserId) {
            $q->where('blocker_id', $userId)->where('blocked_id', $otherUserId);
        })->orWhere(function ($q) use ($userId, $otherUserId) {
            $q->where('blocker_id', $otherUserId)->where('blocked_id', $userId);
        })->exists();

        $contact = Contact::between($userId, $otherUserId)->accepted()->first();
        $isTrashed = $contact && Trash::where('user_id', $userId)->where('contact_id', $contact->id)->exists();

        return view('conversations.show', compact('conversation', 'messages', 'otherUser', 'isIgnored', 'isBlocked', 'isTrashed'));
    }
}
