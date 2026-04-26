<?php declare(strict_types=1);

use SanderMuller\FluentValidation\FluentRule;

/**
 * Full-match parity. Promotes `field()->required()->image()` to
 * `image()->required()`. Filament-heavy use case.
 *
 * Image validation requires `UploadedFile` instances; payloads here use
 * scalar values that exercise the "wrong type" branch — both forms must
 * reject with the same message shape.
 */
return [
    'rules_before' => [
        'avatar' => FluentRule::field()->required()->rule('image'),
    ],
    'rules_after' => [
        'avatar' => FluentRule::image()->required(),
    ],
    'payloads' => [
        'missing' => [],
        'plain string' => ['avatar' => '/path/to/file.jpg'],
        'integer' => ['avatar' => 1],
        'null' => ['avatar' => null],
    ],
];
