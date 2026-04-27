<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// Abstract class with `rules()` and NO `#[FluentRules]` attribute.
// The safety guard applies — subclasses may do
// `array_merge(parent::rules(), [...])` and silently break if the
// parent returns FluentRule objects. Skipped with the reworded
// "Add #[FluentRules] to the rules() method itself..." log message.
abstract class UnattributedAbstractRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }
}

?>
