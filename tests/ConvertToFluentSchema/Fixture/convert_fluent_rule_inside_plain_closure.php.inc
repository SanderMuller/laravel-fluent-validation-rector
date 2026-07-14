<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

// A FluentRule chain built inside a plain closure: the converter rewrites the
// receiver onto the injected builder and gives the closure a `use ($rules)`
// capture so the builder is in scope there (a plain closure captures nothing
// without `use`). Arrow functions auto-capture; anonymous classes and nested
// named functions still bail (the builder can't be threaded in).
class ConvertFluentRuleInsidePlainClosure extends FormRequest
{
    use HasFluentRules;

    public function rules(): array
    {
        $build = function () {
            return FluentRule::string()->required();
        };

        return [
            'name' => $build(),
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

// A FluentRule chain built inside a plain closure: the converter rewrites the
// receiver onto the injected builder and gives the closure a `use ($rules)`
// capture so the builder is in scope there (a plain closure captures nothing
// without `use`). Arrow functions auto-capture; anonymous classes and nested
// named functions still bail (the builder can't be threaded in).
class ConvertFluentRuleInsidePlainClosure extends FormRequest
{
    use HasFluentRules;

    public function schema(FluentSchema $rules): array
    {
        $build = function () use ($rules) {
            return $rules->string()->required();
        };

        return [
            'name' => $build(),
        ];
    }
}

?>
