<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\RuleSet;

// 1.1.0 fixture (TDD pre-implementation): string-rector skip case —
// `RuleSet::from` arg is a Variable (non-literal), not an Array_.
// Descent target is not statically determinable; converter must NOT
// mutate the wrapper and SHOULD emit a skip-log entry per
// `LogsSkipReasons` mirroring GroupWildcardRulesToEachRector's
// existing skip-message wording. Mirrors the array-rector's fixture
// 4.4 shape but isolated to the string-rector test surface.
//
// No `-----` separator: input == output (skip fixture). Skip-log
// emit assertion lives in the dedicated `StringConverterSkipLogTest`
// per spec §4.4a.
//
// Spec reference: specs/1.1.0-ruleset-from-conversion.md §4.4b.
class StringInjectedRulesRequest extends FormRequest
{
    public function __construct(private readonly array $injectedRules) {}

    public function rules(): RuleSet
    {
        return RuleSet::from($this->injectedRules);
    }
}

?>
