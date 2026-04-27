<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.19.0 case (b) — mixed-const-host. Different host classes for
// the consts in sibling wildcard keys. The fold accepts: each
// ClassConstFetch is preserved verbatim, Laravel resolves them
// to strings at runtime regardless of declaring class.
final class MixedConstHostA
{
    public const string TITLE = 'title';
}

final class MixedConstHostB
{
    public const string DESCRIPTION = 'description';
}

final class WildcardPrefixMixedHostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            '*.' . MixedConstHostA::TITLE => FluentRule::string()->required(),
            '*.' . MixedConstHostB::DESCRIPTION => FluentRule::string()->nullable(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.19.0 case (b) — mixed-const-host. Different host classes for
// the consts in sibling wildcard keys. The fold accepts: each
// ClassConstFetch is preserved verbatim, Laravel resolves them
// to strings at runtime regardless of declaring class.
final class MixedConstHostA
{
    public const string TITLE = 'title';
}

final class MixedConstHostB
{
    public const string DESCRIPTION = 'description';
}

final class WildcardPrefixMixedHostRequest extends FormRequest
{
    public function rules(): array
    {
        return ['*' => FluentRule::array()->children([
            MixedConstHostA::TITLE => FluentRule::string()->required(),
            MixedConstHostB::DESCRIPTION => FluentRule::string()->nullable(),
        ])];
    }
}

?>
