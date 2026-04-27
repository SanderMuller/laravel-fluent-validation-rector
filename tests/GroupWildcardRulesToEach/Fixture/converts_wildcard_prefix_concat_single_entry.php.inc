<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.19.0 single-entry fold. With only ONE wildcard-prefix-const
// sibling, the fold still fires — produces
// `'*' => array()->children([CONST => …])` with a single-element
// children body. Semantically equivalent at runtime; pinned here
// as the documented behavior. A future "skip-fold-on-single-entry"
// optimization would intentionally break this fixture.
final class WildcardPrefixSingleEntryRequest extends FormRequest
{
    public const string FIELD = 'field';

    public function rules(): array
    {
        return [
            '*.' . self::FIELD => FluentRule::string()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.19.0 single-entry fold. With only ONE wildcard-prefix-const
// sibling, the fold still fires — produces
// `'*' => array()->children([CONST => …])` with a single-element
// children body. Semantically equivalent at runtime; pinned here
// as the documented behavior. A future "skip-fold-on-single-entry"
// optimization would intentionally break this fixture.
final class WildcardPrefixSingleEntryRequest extends FormRequest
{
    public const string FIELD = 'field';

    public function rules(): array
    {
        return [
            '*' => FluentRule::array()->children([
                self::FIELD => FluentRule::string()->required(),
            ]),
        ];
    }
}

?>
