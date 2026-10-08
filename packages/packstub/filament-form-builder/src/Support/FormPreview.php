<?php

namespace Packstub\FormBuilder\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\Models\Form;

/**
 * The editor's "Preview": the form as it stands in the editor, saved or not, drawn by the
 * plain Blade renderer in the hosted page's layout. The editor's state is parked in the cache
 * under a random token for the admin who asked, and an iframe loads the preview route with it
 * (one page per language, so each gets its own styles and script).
 */
class FormPreview
{
    public const ROUTE = 'packstub-form-builder.preview';

    public const TTL_MINUTES = 30;

    /**
     * @param  array<string, mixed>  $state  The editor's form state.
     */
    public static function store(array $state, Authenticatable $user): string
    {
        $token = Str::random(40);

        Cache::put(static::cacheKey($token), [
            'user' => (string) $user->getAuthIdentifier(),
            'state' => $state,
        ], now()->addMinutes(static::TTL_MINUTES));

        return $token;
    }

    /**
     * The parked state, only for the admin who parked it.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $token, ?Authenticatable $user): ?array
    {
        $entry = Cache::get(static::cacheKey($token));

        if (! is_array($entry) || $user === null || $entry['user'] !== (string) $user->getAuthIdentifier()) {
            return null;
        }

        return (array) $entry['state'];
    }

    public static function url(string $token, string $locale): string
    {
        return route(static::ROUTE, ['token' => $token, 'locale' => $locale]);
    }

    /**
     * An unsaved form built from the editor's state. Always open: the preview shows the
     * fields even when the form is switched off or outside its dates.
     *
     * @param  array<string, mixed>  $state
     */
    public static function form(array $state): Form
    {
        $model = FormBuilder::formModel();
        /** @var Form $form */
        $form = new $model;

        foreach ($form->translatable as $attribute) {
            $form->setTranslations($attribute, array_filter((array) ($state[$attribute] ?? []), 'filled'));
        }

        $slug = Str::slug((string) ($state['slug'] ?? '')) ?: Str::slug((string) ($state['name']['en'] ?? '')) ?: 'preview';

        $form->forceFill([
            'slug' => $slug,
            'fields' => $model::normalizeFieldDefinitions(array_values((array) ($state['fields'] ?? []))),
            'settings' => (array) ($state['settings'] ?? []),
            'is_active' => true,
            'opens_at' => null,
            'closes_at' => null,
        ]);

        return $form;
    }

    protected static function cacheKey(string $token): string
    {
        return 'packstub-form-builder:preview:'.$token;
    }
}
