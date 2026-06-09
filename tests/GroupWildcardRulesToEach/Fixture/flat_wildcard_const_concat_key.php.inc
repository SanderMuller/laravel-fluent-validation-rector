<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class DuplicateItemsRequest extends FormRequest
{
    private const string ITEM_IDS = 'id';

    public function rules(): array
    {
        return [
            self::ITEM_IDS => FluentRule::array()->required(),
            self::ITEM_IDS . '.*' => FluentRule::numeric()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class DuplicateItemsRequest extends FormRequest
{
    private const string ITEM_IDS = 'id';

    public function rules(): array
    {
        return [
            self::ITEM_IDS => FluentRule::array()->required()->each(FluentRule::numeric()->required()),
        ];
    }
}

?>
