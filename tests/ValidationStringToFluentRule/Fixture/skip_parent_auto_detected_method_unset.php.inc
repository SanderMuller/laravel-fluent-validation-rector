<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// `unset()` is a language construct (`Stmt\Unset_`), not a `FuncCall`,
// so the legacy 'unset' string in the FuncCall list never fired. Codex
// 2026-04-26 catch: a child mutating `parent::editorRules()` via
// `unset(...)` previously slipped past the parent-safety guard and
// the parent's auto-detected method got rewritten. Both classes must
// be left untouched.
class ParentUnsetMutatedRequest extends FormRequest
{
    public function rules(): array
    {
        return [];
    }

    public function editorRules(): array
    {
        return [
            'title' => 'required|string',
            'body' => 'nullable|string',
        ];
    }
}

class ChildUnsetMutatedRequest extends ParentUnsetMutatedRequest
{
    public function editorRules(): array
    {
        $rules = parent::editorRules();
        unset($rules['body']);

        return $rules;
    }
}

?>
