<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use Illuminate\Foundation\Http\FormRequest;

// Parent class should NOT be converted because the child manipulates parent::rules()
class VideoUploadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file'],
        ];
    }
}

class SpecificVideoUploadRequest extends VideoUploadRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        array_push($rules['title'], 'unique:videos,title');

        return $rules;
    }
}

?>
