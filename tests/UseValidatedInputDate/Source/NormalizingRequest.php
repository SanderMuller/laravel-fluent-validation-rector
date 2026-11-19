<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Source;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/**
 * Overrides date(), so the filled() check around it may matter.
 */
final class NormalizingRequest extends Request
{
    public function date($key, $format = null, $tz = null): ?CarbonInterface
    {
        return parent::date($key, $format, $tz)?->startOfDay();
    }
}
