<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\Config\Shared;

use PHPUnit\Framework\TestCase;
use SanderMuller\FluentValidationRector\Config\Shared\BaseClassRegistry;

final class BaseClassRegistryTest extends TestCase
{
    public function testNoneIsEmpty(): void
    {
        $this->assertSame([], BaseClassRegistry::none()->baseClasses);
    }

    public function testOfStoresGivenClasses(): void
    {
        $registry = BaseClassRegistry::of(['App\\Http\\Requests\\BaseRequest']);

        $this->assertSame(['App\\Http\\Requests\\BaseRequest'], $registry->baseClasses);
    }
}
