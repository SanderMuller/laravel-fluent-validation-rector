<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source;

enum PostPriority: int
{
    case Low = 1;
    case High = 2;
}
