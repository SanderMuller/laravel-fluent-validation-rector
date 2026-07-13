<?php

namespace SanderMuller\FluentValidationRector\Tests\FullPipelineSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRules;

// #[FluentRules] on an abstract base's rules() opts the whole ALL + SCHEMA
// pipeline in: string rules → FluentRule chains, HasFluentRules trait added,
// then rules() → schema(FluentSchema $rules). Proves the abstract path now
// reaches all the way through, not just concrete FormRequests.
abstract class AbstractStoreRequest extends FormRequest
{
    #[FluentRules]
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\FullPipelineSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRules;
use SanderMuller\FluentValidation\HasFluentRules;
use SanderMuller\FluentValidation\FluentSchema;

// #[FluentRules] on an abstract base's rules() opts the whole ALL + SCHEMA
// pipeline in: string rules → FluentRule chains, HasFluentRules trait added,
// then rules() → schema(FluentSchema $rules). Proves the abstract path now
// reaches all the way through, not just concrete FormRequests.
abstract class AbstractStoreRequest extends FormRequest
{
    use HasFluentRules;

    #[FluentRules]
    public function schema(FluentSchema $rules): array
    {
        return [
            'name' => $rules->string()
                ->required()
                ->max(255),
        ];
    }
}

?>
