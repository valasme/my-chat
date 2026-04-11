<?php

namespace App\Http\Requests;

use App\Concerns\TrashValidationRules;
use App\Models\Contact;
use App\Models\Trash;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTrashRequest extends FormRequest
{
    use TrashValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->trashRules();
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $contactId = $this->input('contact_id');
                $contact = Contact::find($contactId);

                if (! $contact) {
                    return;
                }

                if (! $contact->involvesUser($this->user()->id)) {
                    $validator->errors()->add('contact_id', __('This contact does not belong to you.'));

                    return;
                }

                if ($contact->status !== 'accepted') {
                    $validator->errors()->add('contact_id', __('You can only trash accepted contacts.'));

                    return;
                }

                if (Trash::where('user_id', $this->user()->id)->where('contact_id', $contactId)->exists()) {
                    $validator->errors()->add('contact_id', __('This contact is already in trash.'));
                }
            },
        ];
    }
}
