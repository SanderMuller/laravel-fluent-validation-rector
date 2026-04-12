<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use App\Rules\SomeCustomRule;
use Illuminate\Foundation\Http\FormRequest;

class ArraySortedImportInsertionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureArray;

use SanderMuller\FluentValidation\FluentRule;
use App\Rules\SomeCustomRule;
use Illuminate\Foundation\Http\FormRequest;

class ArraySortedImportInsertionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => FluentRule::email()->required(),
        ];
    }
}

?>
