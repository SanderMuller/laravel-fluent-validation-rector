<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// Dead-code assignment sits after the return. `extractReturnArray` requires
// the final statement to BE the return, so the whole method shape rejects
// and the rector is a no-op — preserving user intent even when the AST is
// non-canonical.
final class SkipVariableSpreadAssignedAfterReturn extends FormRequest
{
    public function rules(): array
    {
        $base = [
            'one' => 'required|string',
        ];

        return [
            ...$base,
            'tail' => 'required|string',
        ];

        $base = ['unreachable' => 'ignored'];
    }
}

?>
