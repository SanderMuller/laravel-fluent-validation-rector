<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.19.0 case (a) — all-same-const-host. Sibling wildcard rule keys
// `'*.' . self::TITLE` + `'*.' . self::SORT_ORDER` fold to a
// single `'*' => FluentRule::array()->children([self::TITLE => …,
// self::SORT_ORDER => …])` entry. ClassConstFetch keys preserved
// verbatim (identity-equal) — refactor safety + intent preservation.
final class WildcardPrefixConcatRequest extends FormRequest
{
    public const string TITLE = 'title';

    public const string SORT_ORDER = 'sort_order';

    public function rules(): array
    {
        return [
            '*.' . self::TITLE => FluentRule::string()->required()->max(255),
            '*.' . self::SORT_ORDER => FluentRule::integer()->required()->min(0),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.19.0 case (a) — all-same-const-host. Sibling wildcard rule keys
// `'*.' . self::TITLE` + `'*.' . self::SORT_ORDER` fold to a
// single `'*' => FluentRule::array()->children([self::TITLE => …,
// self::SORT_ORDER => …])` entry. ClassConstFetch keys preserved
// verbatim (identity-equal) — refactor safety + intent preservation.
final class WildcardPrefixConcatRequest extends FormRequest
{
    public const string TITLE = 'title';

    public const string SORT_ORDER = 'sort_order';

    public function rules(): array
    {
        return ['*' => FluentRule::array()->children([
            self::TITLE => FluentRule::string()->required()->max(255),
            self::SORT_ORDER => FluentRule::integer()->required()->min(0),
        ])];
    }
}

?>
