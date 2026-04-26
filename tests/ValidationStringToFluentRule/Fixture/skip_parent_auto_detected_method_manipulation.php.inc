<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// Parent declares an auto-detected rules-shaped method (`editorRules`)
// that the converter would normally rewrite. The child overrides it
// and manipulates `parent::editorRules()` as a plain array — exactly
// the corruption shape the parent-safety guard exists for. Codex
// 2026-04-26 caught the gap: the original guard only watched
// `parent::rules()`, so widened auto-detect could rewrite the parent's
// `editorRules()` while the child still treated it as a plain array.
// Both classes must be left untouched.
class ParentAutoDetectedRequest extends FormRequest
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

class ChildAutoDetectedRequest extends ParentAutoDetectedRequest
{
    public function editorRules(): array
    {
        return array_merge(parent::editorRules(), [
            'extra' => 'required|string',
        ]);
    }
}

?>
