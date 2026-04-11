<?php

namespace App\Http\Requests;

use App\Concerns\BlockValidationRules;
use App\Models\Block;
use App\Models\Contact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBlockRequest extends FormRequest
{
    use BlockValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->blockRules();
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $targetUserId = $this->input('user_id');

                if ((int) $targetUserId === $this->user()->id) {
                    $validator->errors()->add('user_id', __('You cannot block yourself.'));

                    return;
                }

                if (Block::where('blocker_id', $this->user()->id)->where('blocked_id', $targetUserId)->exists()) {
                    $validator->errors()->add('user_id', __('You have already blocked this user.'));

                    return;
                }

                $hasRelationship = Contact::between($this->user()->id, (int) $targetUserId)->exists();
                if (! $hasRelationship) {
                    $validator->errors()->add('user_id', __('You can only block users you have a contact relationship with.'));
                }
            },
        ];
    }
}
