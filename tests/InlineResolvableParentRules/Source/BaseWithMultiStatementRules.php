<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Source;

/**
 * Parent whose `rules()` does more than return a plain literal (variable
 * assignment + merge). Rector must leave the child's spread intact — only
 * pure-literal parents are safe to inline.
 */
abstract class BaseWithMultiStatementRules
{
    public function rules(): array
    {
        $base = ['id' => 'required|uuid'];

        return array_merge($base, ['timestamp' => 'required|date']);
    }
}
