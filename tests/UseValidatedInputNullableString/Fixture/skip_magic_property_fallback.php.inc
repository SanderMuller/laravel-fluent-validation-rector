<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Fixture;

use SanderMuller\FluentValidation\ValidatedInput;
use SanderMuller\FluentValidationRector\Tests\UseValidatedInputNullableString\Source\Post;

final class SkipMagicPropertyFallback
{
    public function read(ValidatedInput $safe, Post $post): mixed
    {
        // An Eloquent attribute can throw on read. The ternary read it only
        // when the field was blank; the call would read it every time.
        return $safe->filled('title') ? $safe->string('title')->value() : $post->title;
    }
}

?>
