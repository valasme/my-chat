<?php

namespace App\Http\Requests;

use App\Concerns\NoteValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreNoteRequest extends FormRequest
{
    use NoteValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->noteRules();
    }

    public function after(): array
    {
        return $this->noteContactAfterCallbacks();
    }
}
