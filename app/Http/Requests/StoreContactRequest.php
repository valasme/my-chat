<?php

namespace App\Http\Requests;

use App\Concerns\ContactValidationRules;
use App\Models\Block;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    use ContactValidationRules;

    private ?User $resolvedTargetUser = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->contactRequestRules();
    }

    /**
     * Returns the resolved target user, cached to avoid a second DB lookup in the controller.
     */
    public function targetUser(): ?User
    {
        return $this->resolvedTargetUser ??= User::where('email', $this->input('email'))->first();
    }

    /**
     * Configure the validator instance with additional checks.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $targetUser = $this->targetUser();

                if (! $targetUser) {
                    return;
                }

                if ($targetUser->id === $this->user()->id) {
                    $validator->errors()->add('email', __('You cannot send a contact request to yourself.'));

                    return;
                }

                if (Contact::between($this->user()->id, $targetUser->id)->exists()) {
                    $validator->errors()->add('email', __('A contact relationship already exists with this user.'));

                    return;
                }

                if (Block::where('blocker_id', $targetUser->id)->where('blocked_id', $this->user()->id)->exists()) {
                    $validator->errors()->add('email', __('You cannot send a contact request to this user.'));

                    return;
                }

                if (Block::where('blocker_id', $this->user()->id)->where('blocked_id', $targetUser->id)->exists()) {
                    $validator->errors()->add('email', __('You have blocked this user. Unblock them first.'));
                }
            },
        ];
    }
}
