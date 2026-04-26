<?php

namespace SanderMuller\FluentValidationRector\Tests\AddHasFluentRulesTrait\Fixture;

use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;
use SanderMuller\FluentValidation\FluentRule;

// Nova resource with `fields()` chaining `->rules([FluentRule::*])`
// per field. Nova has its own validation pipeline that calls each
// Field's rule list directly — `HasFluentRules::createDefaultValidator`
// is never invoked on a Nova Resource. Hihaho dogfood (2026-04-26)
// found `Nova\Playlist\Playlist` got the trait inserted. The class is
// not on the FormRequest ancestor chain.
class Playlist extends NovaResource
{
    public static string $model = 'App\\Models\\Playlist';

    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Title')->rules([
                FluentRule::string()->required()->max(255),
            ]),
            Text::make('Description')->rules([
                FluentRule::string()->nullable(),
            ]),
        ];
    }
}

?>
