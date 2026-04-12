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

use App\Rules\SomeCustomRule;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

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
