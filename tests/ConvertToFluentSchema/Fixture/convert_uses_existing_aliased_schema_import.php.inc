<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\FluentSchema as Schema;
use SanderMuller\FluentValidation\HasFluentRules;

// FluentSchema is already imported under an alias. The injected parameter must
// use that alias — emitting a bare `FluentSchema` would resolve to the current
// namespace (nonexistent) and break the request.
class AliasedSchemaImportRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }

    public function makeSchema(): Schema
    {
        return new Schema();
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema as Schema;
use SanderMuller\FluentValidation\HasFluentRules;

// FluentSchema is already imported under an alias. The injected parameter must
// use that alias — emitting a bare `FluentSchema` would resolve to the current
// namespace (nonexistent) and break the request.
class AliasedSchemaImportRequest extends FormRequest
{
    use HasFluentRules;

    public function schema(Schema $rules): array
    {
        return [
            'name' => $rules->string()->required(),
        ];
    }

    public function makeSchema(): Schema
    {
        return new Schema();
    }
}

?>
