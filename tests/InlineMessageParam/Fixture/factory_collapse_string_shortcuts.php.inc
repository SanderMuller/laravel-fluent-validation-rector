<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class FactoryCollapseStringShortcuts
{
    public function rules(): array
    {
        return [
            'site' => FluentRule::url()->message('Bad URL.'),
            'id' => FluentRule::uuid()->message('Bad UUID.'),
            'token' => FluentRule::ulid()->message('Bad ULID.'),
            'ip_v4' => FluentRule::ipv4()->message('Bad IPv4.'),
            'ip_v6' => FluentRule::ipv6()->message('Bad IPv6.'),
            'mac' => FluentRule::macAddress()->message('Bad MAC.'),
            'payload' => FluentRule::json()->message('Bad JSON.'),
            'tz' => FluentRule::timezone()->message('Bad timezone.'),
            'color' => FluentRule::hexColor()->message('Bad hex.'),
            'homepage' => FluentRule::activeUrl()->message('Not reachable.'),
            'tags' => FluentRule::list()->message('Must be a list.'),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\InlineMessageParam\Fixture;

use SanderMuller\FluentValidation\FluentRule;

class FactoryCollapseStringShortcuts
{
    public function rules(): array
    {
        return [
            'site' => FluentRule::url(message: 'Bad URL.'),
            'id' => FluentRule::uuid(message: 'Bad UUID.'),
            'token' => FluentRule::ulid(message: 'Bad ULID.'),
            'ip_v4' => FluentRule::ipv4(message: 'Bad IPv4.'),
            'ip_v6' => FluentRule::ipv6(message: 'Bad IPv6.'),
            'mac' => FluentRule::macAddress(message: 'Bad MAC.'),
            'payload' => FluentRule::json(message: 'Bad JSON.'),
            'tz' => FluentRule::timezone(message: 'Bad timezone.'),
            'color' => FluentRule::hexColor(message: 'Bad hex.'),
            'homepage' => FluentRule::activeUrl(message: 'Not reachable.'),
            'tags' => FluentRule::list(message: 'Must be a list.'),
        ];
    }
}

?>
