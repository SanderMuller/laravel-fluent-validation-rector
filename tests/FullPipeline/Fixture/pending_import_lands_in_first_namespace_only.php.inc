<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
        ];
    }
}

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTagRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tags.*.name' => \SanderMuller\FluentValidation\FluentRule::string()->max(50),
            'tags.*.slug' => \SanderMuller\FluentValidation\FluentRule::string()->max(50),
        ];
    }
}

?>
-----
<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\HasFluentRules;

class StorePostRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): array
    {
        return [
            'title' => FluentRule::string()
                ->required(),
        ];
    }
}

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class StoreTagRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): array
    {
        return ['tags' => FluentRule::array()
            ->each([
            'name' => \SanderMuller\FluentValidation\FluentRule::string()->max(50),
            'slug' => \SanderMuller\FluentValidation\FluentRule::string()->max(50),
        ])];
    }
}

?>
