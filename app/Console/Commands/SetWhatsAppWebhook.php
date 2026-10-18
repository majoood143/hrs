<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use WallaceMartinss\FilamentEvolution\Models\WhatsappInstance;

/**
 * Points an Evolution instance's webhook at this site, with the secret header.
 *
 * The plugin's own setWebhook() uses the v1 body and neither it nor createInstance() sends
 * headers, while its WebhookController refuses any call without X-Webhook-Secret once
 * EVOLUTION_WEBHOOK_SECRET is set: every webhook then ends in a 401. Run this after
 * changing EVOLUTION_WEBHOOK_URL or the secret, and after creating an instance.
 */
class SetWhatsAppWebhook extends Command
{
    protected $signature = 'whatsapp:webhook
        {instance? : the instance name; every instance in the database when left out}
        {--url= : the address Evolution calls (default: EVOLUTION_WEBHOOK_URL)}
        {--show : only print what each instance has now}';

    protected $description = 'Set the Evolution API webhook (URL, events and secret header) of the WhatsApp instances';

    public function handle(): int
    {
        $baseUrl = rtrim((string) config('filament-evolution.api.base_url'), '/');
        $apiKey = (string) config('filament-evolution.api.api_key');

        if ($baseUrl === '' || $apiKey === '') {
            $this->error('The Evolution API is not set up (EVOLUTION_URL / EVOLUTION_API_KEY in .env).');

            return self::FAILURE;
        }

        $names = $this->argument('instance')
            ? [$this->argument('instance')]
            : WhatsappInstance::query()->pluck('name')->all();

        if ($names === []) {
            $this->error('No WhatsApp instance in the database.');

            return self::FAILURE;
        }

        $http = fn () => Http::baseUrl($baseUrl)->withHeaders(['apikey' => $apiKey])->acceptJson()->timeout(15);

        if ($this->option('show')) {
            foreach ($names as $name) {
                $current = $http()->get("/webhook/find/{$name}")->json() ?? [];
                $this->line(sprintf(
                    '%s: %s %s (secret header %s)',
                    $name,
                    ($current['enabled'] ?? false) ? 'on' : 'off',
                    $current['url'] ?? '-',
                    isset($current['headers']['X-Webhook-Secret']) ? 'set' : 'missing',
                ));
            }

            return self::SUCCESS;
        }

        $url = (string) ($this->option('url') ?: config('filament-evolution.webhook.url'));

        if ($url === '') {
            $this->error('No webhook URL: set EVOLUTION_WEBHOOK_URL or pass --url.');

            return self::FAILURE;
        }

        if (preg_match('#^https?://(localhost|127\.|0\.0\.0\.0|\[::1\])#i', $url)) {
            $this->warn("{$url} is this machine's own address: a remote Evolution server cannot reach it (use a tunnel).");
        }

        $secret = (string) config('filament-evolution.webhook.secret');

        $webhook = array_filter([
            'enabled' => true,
            'url' => $url,
            'byEvents' => (bool) config('filament-evolution.webhook.by_events', false),
            'base64' => (bool) config('filament-evolution.webhook.base64', false),
            'headers' => $secret !== '' ? ['X-Webhook-Secret' => $secret] : null,
            'events' => config('filament-evolution.webhook.events', []),
        ], fn ($value) => $value !== null);

        $failed = false;

        foreach ($names as $name) {
            try {
                $response = $http()->post("/webhook/set/{$name}", ['webhook' => $webhook]);
            } catch (ConnectionException $e) {
                $this->error("{$name}: {$e->getMessage()}");
                $failed = true;

                continue;
            }

            if ($response->failed()) {
                $this->error("{$name}: HTTP {$response->status()} ".json_encode($response->json('response.message') ?? $response->json('message') ?? $response->body()));
                $failed = true;

                continue;
            }

            $this->info("{$name}: webhook set to {$url}".($secret !== '' ? ' with the secret header.' : ' (no secret).'));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
