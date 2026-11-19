<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Source;

use Illuminate\Http\Request;

/**
 * Overrides isNotFilled(), which date() calls, so the filled() check around
 * date() may matter.
 */
final class BlankCheckOverridingRequest extends Request
{
    public function isNotFilled($key): bool
    {
        return $key === 'always_filled' || parent::isNotFilled($key);
    }
}
