<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

// #[\Override] (valid when a base request declares rules()) must be dropped on
// the rename: schema() is discovered reflectively by HasFluentRules and
// overrides nothing, so carrying the attribute would fatal at class load.
class StorePostRequest extends FormRequest
{
    use HasFluentRules;

    #[\Override]
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

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema;
use SanderMuller\FluentValidation\HasFluentRules;

// #[\Override] (valid when a base request declares rules()) must be dropped on
// the rename: schema() is discovered reflectively by HasFluentRules and
// overrides nothing, so carrying the attribute would fatal at class load.
class StorePostRequest extends FormRequest
{
    use HasFluentRules;

    public function schema(FluentSchema $rules): array
    {
        return [
            'name' => $rules->string()->required(),
        ];
    }
}

?>
