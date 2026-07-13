<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentFormRequest;
use SanderMuller\FluentValidation\FluentRule;

class ConvertViaFluentFormRequestAncestor extends FluentFormRequest
{
    public function rules(): array
    {
        return [
            'title' => FluentRule::string()->required()->max(120),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ConvertToFluentSchema\Fixture;

use SanderMuller\FluentValidation\FluentFormRequest;
use SanderMuller\FluentValidation\FluentSchema;

class ConvertViaFluentFormRequestAncestor extends FluentFormRequest
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'title' => $rules->string()->required()->max(120),
        ];
    }
}

?>
