<?php

namespace SanderMuller\FluentValidationRector\Tests\ValidationArrayToFluentRule\Fixture;

use App\Widgets\Gadget;
use Illuminate\Foundation\Http\FormRequest;

class UnsafeTupleArgFormRequest extends FormRequest
{
    public function rules(): array
    {
        $obj = new Gadget();
        $a = 0;
        $i = 0;

        return [
            'a' => [
                'array',
                ['max', new Gadget()],
            ],
            'b' => [
                'array',
                ['max', clone $obj],
            ],
            'c' => [
                'array',
                ['max', fn (): int => 5],
            ],
            'd' => [
                'array',
                ['max', function (): int { return 5; }],
            ],
            'e' => [
                'array',
                ['max', [1, 2]],
            ],
            'f' => [
                'array',
                ['max', $a = 5],
            ],
            'g' => [
                'array',
                ['max', $i++],
            ],
            'h' => [
                'array',
                ['max', $a += 5],
            ],
            'i' => [
                'array',
                ['max', ++$i],
            ],
            'j' => [
                'array',
                ['max', $i--],
            ],
            'k' => [
                'array',
                ['max', throw new Gadget('unreachable')],
            ],
            'l' => [
                'array',
                ['max', 'prefix-' . new Gadget()],
            ],
        ];
    }
}

?>
