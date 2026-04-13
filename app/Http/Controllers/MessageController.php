<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    public function store(StoreMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('create', [Message::class, $conversation]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => Auth::id(),
            'body' => $request->validated('body'),
        ]);

        Log::info('Message sent', [
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'sender_id' => Auth::id(),
        ]);

        return redirect()->route('conversations.show', $conversation);
    }
}
