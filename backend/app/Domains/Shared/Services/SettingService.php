<?php

namespace App\Domains\Shared\Services;

use App\Domains\Shared\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * SettingService — agency-scoped key/value configuration.
 * Future phases read late-fee rules, numbering series, currency, etc. from here
 * instead of hardcoding them in controllers.
 */
class SettingService extends DomainService
{
    public const ALLOWED_TYPES = ['string', 'integer', 'boolean', 'json'];

    /** All settings for an agency, grouped: [group => [key => typedValue]]. */
    public function allForAgency(int $agencyId): array
    {
        $this->ensureAgencyAccess($agencyId);

        return Setting::withoutAgencyScope()
            ->where('agency_id', $agencyId)
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group')
            ->map(fn ($items) => $items->mapWithKeys(
                fn (Setting $s) => [$s->key => $s->typedValue()]
            )->all())
            ->all();
    }

    public function get(int $agencyId, string $key, mixed $default = null): mixed
    {
        $this->ensureAgencyAccess($agencyId);

        $setting = Setting::withoutAgencyScope()
            ->where('agency_id', $agencyId)
            ->where('key', $key)
            ->first();

        return $setting ? $setting->typedValue() : $default;
    }

    /**
     * Bulk upsert. $items: [['key'=>..., 'value'=>..., 'type'=>..., 'group'=>...], ...]
     * Types are validated; unknown keys are allowed (future-proof) but logged.
     */
    public function updateMany(int $agencyId, array $items): array
    {
        $this->ensureAgencyAccess($agencyId);

        $saved = [];
        DB::transaction(function () use ($agencyId, $items, &$saved) {
            foreach ($items as $item) {
                $type = $item['type'] ?? 'string';
                if (! in_array($type, self::ALLOWED_TYPES, true)) {
                    abort(422, "Invalid setting type '{$type}' for key '{$item['key']}'.");
                }

                $setting = Setting::withoutAgencyScope()->updateOrCreate(
                    ['agency_id' => $agencyId, 'key' => $item['key']],
                    [
                        'group' => $item['group'] ?? 'general',
                        'value' => Setting::encodeValue($item['value'] ?? null, $type),
                        'type' => $type,
                    ]
                );
                $saved[] = $setting->fresh();
            }
        });

        $this->audit()->log(
            action: 'settings.update',
            extraContext: ['agency_id' => $agencyId, 'keys' => array_column($items, 'key')]
        );

        return $saved;
    }
}
