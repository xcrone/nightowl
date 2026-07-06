<?php

namespace App\Domains\Settings\Actions\Concerns;

use Illuminate\Validation\Rule;

/**
 * Shared by StoreAlertChannel/UpdateAlertChannel: the `config` payload's
 * shape depends on `type`, so its validation rules are computed rather than
 * static. Ported as-is from `AlertChannelController::validated()`, just
 * merged into a single rules() array instead of the controller's original
 * two-phase `$request->validate()` calls — same final constraints, same
 * final `config` shape (only the type-specific sub-keys survive
 * validation), one pass instead of two.
 */
trait ValidatesAlertChannelConfig
{
    protected const TYPES = ['slack', 'discord', 'webhook', 'email'];

    /** @return array<string, array<int, mixed>> */
    protected function baseAlertChannelRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(self::TYPES)],
            'enabled' => ['sometimes', 'boolean'],
            'config' => ['required', 'array'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    protected function configRules(?string $type): array
    {
        return match ($type) {
            'slack', 'discord' => [
                'config.webhook_url' => ['required', 'url'],
            ],
            'webhook' => [
                'config.url' => ['required', 'url'],
                'config.secret' => ['sometimes', 'nullable', 'string'],
            ],
            'email' => [
                'config.recipients' => ['required', 'array', 'min:1'],
                'config.recipients.*' => ['email'],
            ],
            default => [],
        };
    }
}
