<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class RegisterPaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'description' => FluentRule::string()->bail()->required()->max(150),
            'amount' => FluentRule::array()->bail()->required(),
            'amount.currency' => FluentRule::string()->bail()->required()->in([
                'SOL',
                'SOL_LAMPORTS', // For clarity, we can allow both SOL and lamports
            ]),
            'amount.value' => FluentRule::numeric()->bail()->required()->min(1),
            'payerWalletAddress' => FluentRule::string()->bail()->required()->exactly(44), // Solana wallet address length
            'articleId' => FluentRule::integer()->bail()->nullable(),
        ];
    }
}

?>
-----
<?php

namespace SanderMuller\FluentValidationRector\Tests\GroupWildcardRulesToEach\Fixture;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\FluentRule;

class RegisterPaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'description' => FluentRule::string()->bail()->required()->max(150),
            'amount' => FluentRule::array()->bail()->required()->children([
                'currency' => FluentRule::string()->bail()->required()->in([
                    'SOL',
                    'SOL_LAMPORTS', // For clarity, we can allow both SOL and lamports
                ]),
                'value' => FluentRule::numeric()->bail()->required()->min(1),
            ]),
            'payerWalletAddress' => FluentRule::string()->bail()->required()->exactly(44), // Solana wallet address length
            'articleId' => FluentRule::integer()->bail()->nullable(),
        ];
    }
}

?>
