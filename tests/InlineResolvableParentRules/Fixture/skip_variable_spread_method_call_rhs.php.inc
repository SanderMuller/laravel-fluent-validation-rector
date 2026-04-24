<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// `$base` is assigned from a method call — we cannot statically resolve
// the runtime shape without evaluating `baseRules()`. Bail.
final class SkipVariableSpreadMethodCallRhs extends FormRequest
{
    public function rules(): array
    {
        $base = $this->baseRules();

        return [
            ...$base,
            'action' => 'required|string',
        ];
    }

    private function baseRules(): array
    {
        return [
            'one' => 'required|string',
        ];
    }
}

?>
