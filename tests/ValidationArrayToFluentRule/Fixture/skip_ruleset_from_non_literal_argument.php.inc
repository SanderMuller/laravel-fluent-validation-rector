<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\RuleSet;

// 1.1.0 fixture (TDD pre-implementation): skip case — `RuleSet::from`
// arg is a Variable (non-literal), not an Array_. Descent target is
// not statically determinable; converter must NOT mutate the wrapper
// and SHOULD emit a skip-log entry per `LogsSkipReasons` mirroring
// GroupWildcardRulesToEachRector's existing skip-message wording.
//
// No `-----` separator: input == output (skip fixture).
//
// Spec reference: specs/1.1.0-ruleset-from-conversion.md §4.4.
class InjectedRulesRequest extends FormRequest
{
    public function __construct(private readonly array $injectedRules) {}

    public function rules(): RuleSet
    {
        return RuleSet::from($this->injectedRules);
    }
}

?>
