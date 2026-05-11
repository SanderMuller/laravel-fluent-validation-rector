<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Full-match parity. Sibling of the `accepted` parity fixture; verifies
 * Laravel's `declined` semantics survive the splice — accepts `"no"`/
 * `"off"`/`"false"`/`0`/`"0"`/`false`, rejects everything else.
 */
return [
    'rules_before' => [
        'opted_out' => FluentRule::field()->required()->rule('declined'),
    ],
    'rules_after' => [
        'opted_out' => FluentRule::declined()->required(),
    ],
    'payloads' => [
        'missing' => [],
        'valid no' => ['opted_out' => 'no'],
        'valid off' => ['opted_out' => 'off'],
        'valid false string' => ['opted_out' => 'false'],
        'valid zero' => ['opted_out' => 0],
        'valid zero string' => ['opted_out' => '0'],
        'valid false bool' => ['opted_out' => false],
        'rejects yes' => ['opted_out' => 'yes'],
        'rejects one' => ['opted_out' => 1],
        'null' => ['opted_out' => null],
    ],
];
