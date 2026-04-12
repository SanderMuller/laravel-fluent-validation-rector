<?php

namespace SanderMuller\FluentValidationRector\Tests\Fixture;

use App\Support\SomeHelper;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class SortedImportInsertionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use App\Support\SomeHelper;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class SortedImportInsertionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => FluentRule::email()->required(),
        ];
    }
}

?>
