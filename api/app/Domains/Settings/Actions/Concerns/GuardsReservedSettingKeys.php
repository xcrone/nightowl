<?php

namespace App\Domains\Settings\Actions\Concerns;

/**
 * Shared by UpdateAppSetting/DestroyAppSetting: a handful of keys are
 * reserved because they're computed/derived fields on the settings payload
 * itself, not genuine settings — writing or deleting them here would
 * silently do nothing (or worse, get shadowed) on the next GET /settings.
 */
trait GuardsReservedSettingKeys
{
    private const RESERVED_KEYS = ['app_id', 'name', 'description', 'environments', 'agent_token', 'template'];

    private function abortIfReservedSettingKey(string $key): void
    {
        abort_if(in_array($key, self::RESERVED_KEYS, true), 422, "'{$key}' is a reserved setting key.");
    }
}
