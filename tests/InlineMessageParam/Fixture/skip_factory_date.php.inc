<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class SkipFactoryDate
{
    public function rules(): array
    {
        // date/dateTime have no message: param on the factory — the emitted
        // key varies at build (date vs date_format:...). Chain must stay.
        return [
            'starts' => FluentRule::date()->message('Bad date.'),
            'ends' => FluentRule::dateTime()->message('Bad datetime.'),
        ];
    }
}

?>
