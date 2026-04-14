<?php

namespace App\Livewire;

use App\Concerns\MessageValidationRules;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Ignore;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class ConversationShow extends Component
{
    use MessageValidationRules, WithPagination;

    #[Locked]
    public Conversation $conversation;

    #[Locked]
    public User $otherUser;

    #[Locked]
    public ?Ignore $isIgnored = null;

    #[Locked]
    public bool $isTrashed = false;

    #[Locked]
    public bool $isBlocked = false;

    public string $body = '';

    public function mount(
        Conversation $conversation,
        User $otherUser,
        ?Ignore $isIgnored,
        bool $isTrashed,
        bool $isBlocked,
    ): void {
        $this->conversation = $conversation;
        $this->otherUser = $otherUser;
        $this->isIgnored = $isIgnored;
        $this->isTrashed = $isTrashed;
        $this->isBlocked = $isBlocked;
    }

    public function sendMessage(): void
    {
        $this->body = trim($this->body);

        $this->validate($this->messageRules());

        Gate::authorize('create', [Message::class, $this->conversation]);

        $rateLimitKey = 'chat-message:'.Auth::id();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 60)) {
            $this->addError('body', __('Too many messages. Please wait before sending another.'));

            return;
        }
        RateLimiter::hit($rateLimitKey, 60);

        try {
            $message = Message::create([
                'conversation_id' => $this->conversation->id,
                'sender_id' => Auth::id(),
                'body' => $this->body,
            ]);

            MessageSent::dispatch($message);
        } catch (\Throwable $e) {
            report($e);
            $this->addError('body', __('Failed to send message. Please try again.'));

            return;
        }

        $this->reset('body');

        $this->dispatch('scroll-to-bottom');
    }

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return [
            "echo-private:conversation.{$this->conversation->id},MessageSent" => 'onMessageReceived',
        ];
    }

    /**
     * @param  array{id: int, conversation_id: int, sender_id: int, sender_name: string, body: string, created_at: string}  $event
     */
    public function onMessageReceived(array $event): void
    {
        if (! isset($event['sender_id'], $event['conversation_id'])) {
            return;
        }

        if ((int) $event['sender_id'] === Auth::id()) {
            return;
        }

        if ((int) $event['conversation_id'] !== $this->conversation->id) {
            return;
        }

        $this->dispatch('scroll-to-bottom');
    }

    public function render(): View
    {
        $messages = $this->conversation->messages()->with('sender')->oldest()->paginate(50);

        if ($this->getPage() === 1 && $messages->lastPage() > 1 && ! request()->has('page')) {
            $this->setPage($messages->lastPage());
            $messages = $this->conversation->messages()->with('sender')->oldest()->paginate(50);
        }

        return view('livewire.conversation-show', [
            'messages' => $messages,
        ]);
    }
}
