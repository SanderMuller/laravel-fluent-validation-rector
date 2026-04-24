<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// RHS contains a method call with side effects. After inlining, the
// original `$base = ...` assignment MUST be stripped — otherwise
// `$this->computeLimit()` would execute twice (once when `$base` is
// assigned, once in the inlined items). Codex review must-have.
final class InlineVariableSpreadImpureRhs extends FormRequest
{
    public function rules(): array
    {
        $base = [
            'quantity' => 'required|integer|max:' . $this->computeLimit(),
        ];

        return [
            ...$base,
            'name' => 'required|string',
        ];
    }

    private function computeLimit(): int
    {
        return 10;
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// RHS contains a method call with side effects. After inlining, the
// original `$base = ...` assignment MUST be stripped — otherwise
// `$this->computeLimit()` would execute twice (once when `$base` is
// assigned, once in the inlined items). Codex review must-have.
final class InlineVariableSpreadImpureRhs extends FormRequest
{
    public function rules(): array
    {
        return [
            'quantity' => 'required|integer|max:' . $this->computeLimit(),
            'name' => 'required|string',
        ];
    }

    private function computeLimit(): int
    {
        return 10;
    }
}

?>
