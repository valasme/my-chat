<?php

namespace App\Http\Requests;

use App\Concerns\MessageValidationRules;
use App\Models\Contact;
use App\Models\Ignore;
use App\Models\Trash;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    use MessageValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->messageRules();
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $conversation = $this->route('conversation');
                $userId = $this->user()->id;
                $otherUserId = $conversation->user_one_id === $userId
                    ? $conversation->user_two_id
                    : $conversation->user_one_id;

                $otherUser = User::find($otherUserId);
                if (! $otherUser) {
                    $validator->errors()->add('body', __('This user no longer exists.'));

                    return;
                }

                if ($this->user()->hasBlockedUser($otherUser) || $this->user()->isBlockedByUser($otherUser)) {
                    $validator->errors()->add('body', __('You cannot send messages to this user.'));

                    return;
                }

                $ignore = Ignore::where('ignorer_id', $otherUserId)->where('ignored_id', $userId)->active()->first();
                if ($ignore) {
                    $validator->errors()->add('body', __('This user is unavailable until :date.', ['date' => $ignore->expires_at->format('M d, Y H:i')]));

                    return;
                }

                $contact = Contact::between($userId, $otherUserId)->accepted()->first();
                if (! $contact) {
                    $validator->errors()->add('body', __('You must be contacts to send messages.'));

                    return;
                }

                if (Trash::where('user_id', $userId)->where('contact_id', $contact->id)->exists()) {
                    $validator->errors()->add('body', __('Restore this contact from trash to send messages.'));
                }
            },
        ];
    }
}
