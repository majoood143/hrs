<?php

namespace App\Services\WhatsApp;

use App\Filament\Resources\FarrierResource;
use App\Filament\Resources\HorseSalePostResource;
use App\Filament\Resources\ToolSalePostResource;
use App\Filament\Resources\TransferPostResource;
use App\Models\Farrier;
use App\Models\HorseSalePost;
use App\Models\SiteSetting;
use App\Models\ToolSalePost;
use App\Models\TransferPost;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;

/**
 * The admin WhatsApp alert for a post a visitor just published on the site: what kind of post,
 * a short summary, the poster's number, and links to the public page and the admin record.
 */
final class NewPostAlert
{
    /** @var array<class-string<Model>, array{type: string, route: string, resource: class-string}> */
    private const POSTS = [
        TransferPost::class => ['type' => 'transfer_post', 'route' => 'transfer-board.show', 'resource' => TransferPostResource::class],
        HorseSalePost::class => ['type' => 'horse_sale_post', 'route' => 'horses-for-sale.show', 'resource' => HorseSalePostResource::class],
        ToolSalePost::class => ['type' => 'tool_sale_post', 'route' => 'tools-for-sale.show', 'resource' => ToolSalePostResource::class],
        Farrier::class => ['type' => 'farrier', 'route' => 'farriers.show', 'resource' => FarrierResource::class],
    ];

    /** The alert type (a WhatsAppSettings::ALERTS key), or null for a model that has no alert. */
    public static function typeOf(Model $post): ?string
    {
        return self::POSTS[$post::class]['type'] ?? null;
    }

    public static function publicUrl(Model $post): string
    {
        return route(self::POSTS[$post::class]['route'], $post->getKey());
    }

    public static function adminUrl(Model $post): string
    {
        return self::POSTS[$post::class]['resource']::getUrl('view', ['record' => $post], panel: 'admin');
    }

    public static function message(Model $post): string
    {
        $type = self::typeOf($post);
        $publicUrl = self::publicUrl($post);
        $adminUrl = self::adminUrl($post);
        $phone = PhoneNumber::normalize($post->contact_number);

        return BilingualMessage::make(fn () => implode("\n", array_filter([
            __('whatsapp.new_post.heading', ['site' => SiteSetting::siteName()]),
            __('whatsapp.new_post.type', ['type' => __('whatsapp.post_types.'.$type)]),
            '',
            ...self::summary($post),
            __('whatsapp.new_post.contact', ['contact' => $post->contact_number]).($phone ? ' (wa.me/'.$phone.')' : ''),
            '',
            __('whatsapp.new_post.view', ['link' => $publicUrl]),
            __('whatsapp.new_post.admin', ['link' => $adminUrl]),
        ], fn ($line) => $line !== null)));
    }

    /** @return array<int, string> the lines that describe the post, in the current locale */
    private static function summary(Model $post): array
    {
        return match (true) {
            $post instanceof TransferPost => array_filter([
                __('whatsapp.fields.kind', ['value' => __('whatsapp.transfer_kinds.'.$post->type)]),
                __('whatsapp.fields.route', ['from' => $post->fromCity?->name, 'to' => $post->toCity?->name]),
                __('whatsapp.fields.date', ['value' => $post->transfer_date?->format('Y-m-d')]),
                __('whatsapp.fields.capacity', ['value' => $post->capacity]),
                $post->price !== null ? self::price($post->price) : null,
            ]),
            $post instanceof HorseSalePost => [
                __('whatsapp.fields.name', ['value' => $post->name]),
                self::price($post->price, $post->price_negotiable),
            ],
            $post instanceof ToolSalePost => [
                __('whatsapp.fields.name', ['value' => $post->name]),
                __('whatsapp.fields.category', ['value' => $post->category_label]),
                self::price($post->price, $post->price_negotiable),
            ],
            $post instanceof Farrier => array_filter([
                __('whatsapp.fields.name', ['value' => $post->name]),
                $post->price !== null ? self::price($post->price) : null,
            ]),
            default => [],
        };
    }

    private static function price(mixed $amount, bool $negotiable = false): string
    {
        return __('whatsapp.fields.price', ['value' => SiteSetting::formatCurrency($amount ?? 0, 3)])
            .($negotiable ? ' '.__('whatsapp.fields.negotiable') : '');
    }
}
