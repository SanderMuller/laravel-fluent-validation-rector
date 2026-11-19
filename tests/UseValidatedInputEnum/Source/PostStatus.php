<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputEnum\Source;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
