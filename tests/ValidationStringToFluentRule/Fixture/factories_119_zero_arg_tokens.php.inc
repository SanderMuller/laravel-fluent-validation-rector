<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

class Factories119Tokens extends FormRequest
{
    public function rules(): array
    {
        return [
            // Standalone tokens — TYPE_MAP entries map straight to factory.
            'ipv4' => 'ipv4',
            'ipv6' => 'ipv6',
            'mac' => 'mac_address',
            'json' => 'json',
            'tz' => 'timezone',
            'color' => 'hex_color',
            'site' => 'active_url',
            'tags' => 'list',
            'declined' => 'declined',
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationStringToFluentRule\Fixture;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;

class Factories119Tokens extends FormRequest
{
    public function rules(): array
    {
        return [
            // Standalone tokens — TYPE_MAP entries map straight to factory.
            'ipv4' => FluentRule::ipv4(),
            'ipv6' => FluentRule::ipv6(),
            'mac' => FluentRule::macAddress(),
            'json' => FluentRule::json(),
            'tz' => FluentRule::timezone(),
            'color' => FluentRule::hexColor(),
            'site' => FluentRule::activeUrl(),
            'tags' => FluentRule::list(),
            'declined' => FluentRule::declined(),
        ];
    }
}

?>
