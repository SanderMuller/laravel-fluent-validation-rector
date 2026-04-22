<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipMessageForVariadic
{
    public function rules(): array
    {
        // Variadic-trailing methods — message: position would be ambiguous
        // with trailing ...args. Chain stays chained; peer §1.3 skip taxonomy.
        return [
            'a' => FluentRule::string()->requiredWith('b', 'c', 'd')->messageFor('required_with', 'Need one of.'),
            'b' => FluentRule::string()->startsWith('http', 'https', 'ftp')->messageFor('starts_with', 'Bad prefix.'),
            'c' => FluentRule::array()->contains('admin', 'editor')->messageFor('contains', 'Missing role.'),
            'd' => FluentRule::array()->requiredArrayKeys('id', 'name')->messageFor('required_array_keys', 'Missing keys.'),
        ];
    }
}

?>
