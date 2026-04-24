<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\Concerns\Support;

use Rector\ValueObject\Application\File;
use SanderMuller\FluentValidationRector\Rector\Concerns\LogsSkipReasons;

/**
 * Thin harness over {@see LogsSkipReasons} so the `verboseOnly` gate in
 * `writeSkipEntry` can be exercised without spinning up a full Rector
 * container. Stubs `getFile()` — the only AbstractRector API the trait
 * touches — with a fixed, caller-provided path.
 */
final class LogsSkipReasonsHarness
{
    use LogsSkipReasons {
        logSkipByName as public callLogSkipByName;
    }

    public function __construct(private readonly string $filePath) {}

    public function getFile(): File
    {
        return new File($this->filePath, '');
    }
}
