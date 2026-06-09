<?php declare(strict_types=1);

namespace SanderMuller\FluentValidationRector\Tests\InlineResolvableParentRules\Source;

/**
 * Minimal base class for the spread-inlining test. Its `rules()` returns a
 * plain array literal — the shape `InlineResolvableParentRulesRector` is
 * designed to inline. Must live under `tests/` PSR-4 so the rector's
 * reflection against the child class's parent hits a real autoloadable
 * file with a resolvable `getFileName()`.
 */
abstract class BasePlayerSessionHistoryRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'player_id' => 'required|string',
            'session_id' => 'required|string|uuid',
        ];
    }
}
