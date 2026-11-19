<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source;

use Illuminate\Http\Request;
use Illuminate\Support\Stringable;

/**
 * Overrides string(), which str() calls, so enum() would skip the
 * normalization.
 */
final class StringOverridingRequest extends Request
{
    public function string($key, $default = null, $strict = false): Stringable
    {
        return parent::string($key, $default, $strict)->lower();
    }
}
