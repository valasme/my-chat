<?php

namespace App\Http\Requests;

use App\Concerns\IgnoreValidationRules;
use App\Models\Contact;
use App\Models\Ignore;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIgnoreRequest extends FormRequest
{
    use IgnoreValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->ignoreRules();
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $targetUserId = $this->input('user_id');

                if ((int) $targetUserId === $this->user()->id) {
                    $validator->errors()->add('user_id', __('You cannot ignore yourself.'));

                    return;
                }

                if (Ignore::where('ignorer_id', $this->user()->id)->where('ignored_id', $targetUserId)->active()->exists()) {
                    $validator->errors()->add('user_id', __('You are already ignoring this user.'));

                    return;
                }

                $contact = Contact::between($this->user()->id, (int) $targetUserId)->accepted()->first();
                if (! $contact) {
                    $validator->errors()->add('user_id', __('You can only ignore accepted contacts.'));
                }
            },
        ];
    }
}
