<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.20.0 mijntp dogfood pin: `'literal.' . CONST` concat keys are
// genuinely out-of-scope for the wildcard-prefix fold (the prefix
// isn't `'*.'`). Pre-0.20.0 the skip message read "concat key too
// complex to parse for grouping" which inverted which side was the
// disqualifier — the const is fine, the literal prefix is the
// reason. Post-0.20.0 the message names the actual prefix in
// single quotes so the consumer can grep their codebase for
// similar shapes, and frames the bail as by-design.
final class LiteralPrefixHost
{
    public const string APP_KEY = 'app_key';

    public const string TENANT_ID = 'tenant_id';
}

class LiteralPrefixConcatRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'credentials.' . LiteralPrefixHost::APP_KEY => FluentRule::string()->required()->max(191),
            'credentials.' . LiteralPrefixHost::TENANT_ID => FluentRule::string()->required(),
        ];
    }
}

?>
