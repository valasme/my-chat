<?php

namespace App\Concerns;

use App\Models\Contact;
use Illuminate\Contracts\Validation\ValidationRule;

trait NoteValidationRules
{
    /**
     * Get the validation rules for creating/updating a note.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function noteRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
        ];
    }

    /**
     * Convert comma-separated tags string to array before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('tags') && is_string($this->input('tags'))) {
            $tags = array_values(
                array_filter(array_map('trim', explode(',', (string) $this->input('tags'))))
            );
            $this->merge(['tags' => $tags ?: []]);
        }
    }

    /**
     * Validate that contact_id, if provided, belongs to an accepted contact involving the user.
     *
     * @return array<int, \Closure>
     */
    protected function noteContactAfterCallbacks(): array
    {
        return [
            function ($validator) {
                if ($this->filled('contact_id')) {
                    $contact = Contact::find($this->input('contact_id'));

                    if (! $contact || ! $contact->involvesUser($this->user()?->id) || $contact->status !== 'accepted') {
                        $validator->errors()->add('contact_id', __('Invalid contact.'));
                    }
                }
            },
        ];
    }
}
