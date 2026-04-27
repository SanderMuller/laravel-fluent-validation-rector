<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.19.0 spec §3 children-ordering contract: the fold preserves
// SOURCE ORDER of the input rule keys. Non-alphabetical const names
// (Z_LAST first, A_FIRST last) — fold output stays in input order.
// Pins the contract: a future refactor introducing alphabetical
// sort would break this fixture.
final class WildcardPrefixSourceOrderRequest extends FormRequest
{
    public const string Z_LAST = 'z_last';

    public const string A_FIRST = 'a_first';

    public const string M_MIDDLE = 'm_middle';

    public function rules(): array
    {
        return [
            '*.' . self::Z_LAST => FluentRule::string()->required(),
            '*.' . self::A_FIRST => FluentRule::string()->required(),
            '*.' . self::M_MIDDLE => FluentRule::string()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// 0.19.0 spec §3 children-ordering contract: the fold preserves
// SOURCE ORDER of the input rule keys. Non-alphabetical const names
// (Z_LAST first, A_FIRST last) — fold output stays in input order.
// Pins the contract: a future refactor introducing alphabetical
// sort would break this fixture.
final class WildcardPrefixSourceOrderRequest extends FormRequest
{
    public const string Z_LAST = 'z_last';

    public const string A_FIRST = 'a_first';

    public const string M_MIDDLE = 'm_middle';

    public function rules(): array
    {
        return ['*' => FluentRule::array()->children([
            self::Z_LAST => FluentRule::string()->required(),
            self::A_FIRST => FluentRule::string()->required(),
            self::M_MIDDLE => FluentRule::string()->required(),
        ])];
    }
}

?>
