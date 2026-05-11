<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Full-match parity for the leading-message variant: confirms that a
 * `->message()` placed BEFORE the spliced `->rule('accepted')` keeps its
 * binding to `required`. The trailing-message variant is held out as a
 * skip fixture (see `skip_field_accepted_with_trailing_message`) because
 * splicing would silently re-target the message.
 */
return [
    'rules_before' => [
        'agreed' => FluentRule::field()->required()->message('Required field.')->rule('accepted'),
    ],
    'rules_after' => [
        'agreed' => FluentRule::accepted()->required()->message('Required field.'),
    ],
    'payloads' => [
        'missing' => [],
        'valid yes' => ['agreed' => 'yes'],
        'invalid zero' => ['agreed' => 0],
        'null' => ['agreed' => null],
    ],
];
