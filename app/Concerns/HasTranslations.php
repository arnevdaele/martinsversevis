<?php

namespace App\Concerns;

use App\Support\Locales;

/**
 * The base language lives in the model's normal columns, so every query,
 * search and sort keeps working on it. Other languages sit in a `translations`
 * JSON column: {"fr": {"name": "Filet de cabillaud"}}. Reading goes through
 * {@see t()}, which falls back to the base column when a translation is empty.
 *
 * Models declare `protected array $translatable = ['name', …];`.
 */
trait HasTranslations
{
    public function initializeHasTranslations(): void
    {
        $this->mergeCasts(['translations' => 'array']);
    }

    public function t(string $field, ?string $locale = null): mixed
    {
        $locale ??= app()->getLocale();

        if ($locale !== Locales::default()) {
            $translated = data_get($this->translations, "{$locale}.{$field}");

            if (filled($translated)) {
                return $translated;
            }
        }

        return $this->getAttribute($field);
    }

    /**
     * One field's translations in the same {"fr": {field: …}} shape, for
     * snapshotting onto another model — optionally under another field name.
     *
     * @return array<string, array<string, mixed>>
     */
    public function translationsOf(string $field, ?string $as = null): array
    {
        $values = [];

        foreach (Locales::translated() as $locale) {
            $value = data_get($this->translations, "{$locale}.{$field}");
            if (filled($value)) {
                $values[$locale] = [$as ?? $field => $value];
            }
        }

        return $values;
    }

    /** @return list<string> */
    public function translatableFields(): array
    {
        return $this->translatable;
    }
}
