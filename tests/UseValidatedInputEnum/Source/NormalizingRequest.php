<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source;

use Illuminate\Http\Request;
use Illuminate\Support\Stringable;

/**
 * Overrides str(), so enum() would skip the normalization.
 */
final class NormalizingRequest extends Request
{
    public function str($key, $default = null, $strict = false): Stringable
    {
        return parent::str($key, $default, $strict)->lower();
    }
}
