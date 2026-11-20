<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentSchema;

// The child spreads the parent's schema() and adds its own 'filters' key.
class BaseListingRequest extends FormRequest
{
    public function schema(FluentSchema $rules): array
    {
        return [
            'filters.status' => $rules->string()->nullable(),
        ];
    }
}

class SearchListingRequest extends BaseListingRequest
{
    public function schema(FluentSchema $rules): array
    {
        return [
            ...parent::schema($rules),
            'filters' => $rules->array()->nullable(),
        ];
    }
}

?>
