<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.19.0 case (c) — const value collision. Two consts evaluating
// to the SAME string used as sibling wildcard keys. The fold can't
// deterministically pick which CONST to keep in `children([...])`
// because both produce key `'shared'` at runtime. Bail with the
// documented skip-log; consumer must remove the duplicate or
// switch to literal string keys.
final class WildcardPrefixCollisionRequest extends FormRequest
{
    public const string FOO = 'shared';

    public const string BAR = 'shared';

    public function rules(): array
    {
        return [
            '*.' . self::FOO => FluentRule::string()->required(),
            '*.' . self::BAR => FluentRule::integer()->required(),
        ];
    }
}

?>
