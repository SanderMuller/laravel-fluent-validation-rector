<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureGrouping;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class DuplicateVideosRequest extends FormRequest
{
    private const string VIDEO_IDS = 'id';

    public function rules(): array
    {
        return [
            self::VIDEO_IDS => FluentRule::array()->required(),
            self::VIDEO_IDS . '.*' => FluentRule::numeric()->required(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureGrouping;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class DuplicateVideosRequest extends FormRequest
{
    private const string VIDEO_IDS = 'id';

    public function rules(): array
    {
        return [
            self::VIDEO_IDS => FluentRule::array()->required()->each(FluentRule::numeric()->required()),
        ];
    }
}

?>
