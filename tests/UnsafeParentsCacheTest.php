<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests;

use PHPUnit\Framework\TestCase;
use SanderMuller\FluentValidationRector\Internal\UnsafeParentsCache;

/**
 * The unsafe-parents file holds state for one Rector run. `RunSummary` calls
 * `clear()` in the parent process before the workers start and at shutdown;
 * these tests pin the path scoping and the clear itself.
 */
final class UnsafeParentsCacheTest extends TestCase
{
    private string $originalCwd;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCwd = getcwd() ?: sys_get_temp_dir();
        $this->tempDir = sys_get_temp_dir() . '/unsafe-parents-test-' . uniqid('', true);

        if (! mkdir($this->tempDir) && ! is_dir($this->tempDir)) {
            $this->fail('Failed to create temp dir for UnsafeParentsCache test');
        }

        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        UnsafeParentsCache::clear();
        chdir($this->originalCwd);
        @rmdir($this->tempDir);

        parent::tearDown();
    }

    public function testClearRemovesEntriesLeftByAnEarlierRun(): void
    {
        file_put_contents(UnsafeParentsCache::path(), "App\\Http\\Requests\\BaseRequest\n");

        UnsafeParentsCache::clear();

        $this->assertFileDoesNotExist(UnsafeParentsCache::path());
    }

    public function testClearIsANoOpWithoutAFile(): void
    {
        UnsafeParentsCache::clear();

        $this->assertFileDoesNotExist(UnsafeParentsCache::path());
    }

    public function testPathIsScopedToTheWorkingDirectory(): void
    {
        $here = UnsafeParentsCache::path();

        chdir($this->originalCwd);
        $elsewhere = UnsafeParentsCache::path();
        chdir($this->tempDir);

        $this->assertNotSame($here, $elsewhere);
        $this->assertStringStartsWith(sys_get_temp_dir(), $here);
    }
}
