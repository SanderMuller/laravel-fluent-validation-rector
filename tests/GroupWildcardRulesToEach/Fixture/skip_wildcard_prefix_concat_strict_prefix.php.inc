<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.19.0 strict-prefix routing pin. The new wildcard-prefix concat
// fold matches EXACTLY `'*.' . CONST`. Three malformed shapes that
// look superficially similar must fall through to the existing
// parser path and bail with the generic "concat key too complex"
// message — NOT the new "recognized + validated" or "not yet
// folded" messages:
//
//   - `'*. '` (trailing space)
//   - `'*.foo.'` (non-wildcard prefix segment)
//   - `'**.'` (recursive-wildcard double asterisk; out of 0.19 scope)
//
// Pre-fix behavior was identical (the parser rejected all of them
// at the existing String-before-ClassConstFetch path); this fixture
// pins the routing so a future relaxation of the strict-prefix
// check would fail loudly.
final class StrictPrefixRoutingRequest extends FormRequest
{
    public const string FIELD = 'field';

    public function rules(): array
    {
        return [
            '*. ' . self::FIELD => FluentRule::string()->required(),
            '*.foo.' . self::FIELD => FluentRule::string()->required(),
            '**.' . self::FIELD => FluentRule::string()->required(),
        ];
    }
}

?>
