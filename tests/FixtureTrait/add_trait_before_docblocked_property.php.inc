<?php

namespace SanderMuller\FluentValidationRector\Tests\FixtureTrait;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class DocblockedFirstMemberRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    public array $allowed = [];

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

namespace SanderMuller\FluentValidationRector\Tests\FixtureTrait;

use SanderMuller\FluentValidation\HasFluentRules;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class DocblockedFirstMemberRequest extends FormRequest
{
    use HasFluentRules;

    /**
     * @var list<string>
     */
    public array $allowed = [];

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}

?>
