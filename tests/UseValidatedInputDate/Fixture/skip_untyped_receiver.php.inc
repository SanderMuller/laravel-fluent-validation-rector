<?php

namespace SanderMuller\FluentValidationRector\Tests\UseValidatedInputDate\Fixture;


final class SkipUntypedReceiver
{
    public function handle($safe): mixed
    {
        return $safe->filled('published_at') ? $safe->date('published_at') : null;
    }
}

?>
