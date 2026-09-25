<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Js;

/**
 * "View on website" and "Copy website link" row actions for a resource whose records have a
 * public page. Spread them into the table's ActionGroup:
 *
 *     ...WebsiteLinkActions::make(
 *         url: fn (Stable $record) => route('stables.show', $record->slug),
 *         isPublic: fn (Stable $record) => $record->is_active && filled($record->slug),
 *     ),
 *
 * `isPublic` must mirror the site controller's own scope (active/visible): a record the site
 * would 404 on gets both actions disabled, with a tooltip saying why.
 */
final class WebsiteLinkActions
{
    /**
     * @param  Closure(Model): string  $url
     * @param  Closure(Model): bool  $isPublic
     * @return array<Action>
     */
    public static function make(Closure $url, Closure $isPublic): array
    {
        $link = fn (Model $record): ?string => $isPublic($record) ? $url($record) : null;
        $notPublicReason = fn (Model $record): ?string => $isPublic($record) ? null : __('admin_website_link.not_public');

        return [
            Action::make('viewOnWebsite')
                ->label(__('admin_website_link.view'))
                ->icon('heroicon-o-globe-alt')
                ->color('gray')
                ->url($link)
                ->openUrlInNewTab()
                ->disabled(fn (Model $record): bool => ! $isPublic($record))
                ->tooltip($notPublicReason),

            Action::make('copyWebsiteLink')
                ->label(__('admin_website_link.copy'))
                ->icon('heroicon-o-link')
                ->color('gray')
                ->disabled(fn (Model $record): bool => ! $isPublic($record))
                ->tooltip($notPublicReason)
                ->alpineClickHandler(fn (Model $record): ?string => ($target = $link($record)) === null ? null : self::copyJs($target)),
        ];
    }

    /**
     * navigator.clipboard only exists on https/localhost, so fall back to execCommand('copy') for
     * an admin reached over plain http.
     */
    private static function copyJs(string $url): string
    {
        $urlJs = Js::from($url);
        $copiedJs = Js::from(__('admin_website_link.copied'));

        return <<<JS
            (async () => {
                try {
                    await window.navigator.clipboard.writeText({$urlJs})
                } catch (e) {
                    const field = document.createElement('textarea')
                    field.value = {$urlJs}
                    field.setAttribute('readonly', '')
                    field.style.position = 'fixed'
                    field.style.opacity = '0'
                    document.body.appendChild(field)
                    field.select()
                    document.execCommand('copy')
                    field.remove()
                }
                new FilamentNotification().title({$copiedJs}).body({$urlJs}).success().send()
            })()
            JS;
    }
}
