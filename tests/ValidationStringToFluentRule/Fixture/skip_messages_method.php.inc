<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// `messages()` returns a string-keyed array shaped like rules but
// values are user-facing error messages, not rule strings. None of the
// values match KNOWN_RULE_NAMES — auto-detection bails. Exists to
// guard against false-positive widening into validation-adjacent
// methods on qualifying classes.
class SkipMessagesMethod extends FormRequest
{
    public function rules(): array
    {
        return [];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Title is required.',
            'body.required' => 'Body is required.',
            'tags.array' => 'Tags must be a list.',
        ];
    }
}

?>
