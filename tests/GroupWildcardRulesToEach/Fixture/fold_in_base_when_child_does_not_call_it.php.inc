<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// The child replaces rules() without calling the parent's version, so the
// parent's fold cannot collide with anything.
class BaseLabelRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'labels' => FluentRule::array()->required(),
            'labels.*' => FluentRule::string()->max(20),
        ];
    }
}

class StoreLabelRequest extends BaseLabelRequest
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

// The child replaces rules() without calling the parent's version, so the
// parent's fold cannot collide with anything.
class BaseLabelRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'labels' => FluentRule::array()->required()
                ->each(FluentRule::string()->max(20)),
        ];
    }
}

class StoreLabelRequest extends BaseLabelRequest
{
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
