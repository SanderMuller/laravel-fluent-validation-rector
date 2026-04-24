<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Source;

/**
 * Parent whose `rules()` array uses `self::FIELD_*` to name its keys. Inlining
 * these into a child would rebind `self::` against the child class, changing
 * which constant resolves — rector must bail here even though the shape is a
 * "plain array literal".
 */
abstract class BaseWithSelfConstKey
{
    public const string FIELD_ID = 'player_id';

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            self::FIELD_ID => 'required|string',
        ];
    }
}
