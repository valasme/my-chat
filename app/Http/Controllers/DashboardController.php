<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Ignore;
use App\Models\Message;
use App\Models\Trash;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $userId = Auth::id();

        // Stats
        $contactsCount = Contact::forUser($userId)->accepted()->count();
        $blocksCount = Block::forBlocker($userId)->count();
        $ignoresCount = Ignore::forIgnorer($userId)->active()->count();

        $conversationsCount = Conversation::forUser($userId)
            ->excludingIgnoredAndTrashed($userId)
            ->count();

        // Incoming pending requests (limit 5)
        $incomingRequests = Contact::incoming($userId)
            ->pending()
            ->with('user')
            ->latest()
            ->limit(5)
            ->get();
        $incomingTotal = Contact::incoming($userId)->pending()->count();

        // Recent conversations (limit 5)
        $recentConversations = Conversation::forUser($userId)
            ->excludingIgnoredAndTrashed($userId)
            ->with(['userOne', 'userTwo', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc(
                Message::select('created_at')
                    ->whereColumn('conversation_id', 'conversations.id')
                    ->latest()
                    ->limit(1)
            )
            ->limit(5)
            ->get();

        // Expiring soon: ignores within 24h + trash within 7d
        $expiringIgnores = Ignore::forIgnorer($userId)
            ->active()
            ->where('expires_at', '<=', now()->addDay())
            ->with('ignored')
            ->get()
            ->map(fn ($ignore) => (object) [
                'name' => $ignore->ignored->name,
                'type' => 'Ignore',
                'expires_at' => $ignore->expires_at,
                'link' => route('ignores.index'),
            ]);

        $expiringTrashes = Trash::forUser($userId)
            ->where('expires_at', '<=', now()->addWeek())
            ->with(['contact.user', 'contact.contactUser'])
            ->get()
            ->map(fn ($trash) => (object) [
                'name' => $trash->contact->getOtherUser($userId)->name,
                'type' => 'Trash',
                'expires_at' => $trash->expires_at,
                'link' => route('trashes.index'),
            ]);

        $expiringSoon = collect($expiringIgnores)->merge($expiringTrashes)
            ->sortBy('expires_at')
            ->take(5);

        return view('dashboard', compact(
            'contactsCount',
            'conversationsCount',
            'blocksCount',
            'ignoresCount',
            'incomingRequests',
            'incomingTotal',
            'recentConversations',
            'expiringSoon',
        ));
    }
}
