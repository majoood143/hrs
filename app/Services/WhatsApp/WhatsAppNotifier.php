<?php

namespace App\Services\WhatsApp;

use App\Models\NotificationLog;
use App\Support\PhoneNumber;
use App\Support\WhatsAppSettings;
use Throwable;
use WallaceMartinss\FilamentEvolution\Enums\StatusConnectionEnum;
use WallaceMartinss\FilamentEvolution\Models\WhatsappInstance;
use WallaceMartinss\FilamentEvolution\Services\EvolutionClient;

/**
 * Sends one WhatsApp text through the Evolution API (the filament-whatsapp-conector plugin) and
 * writes the attempt to notification_logs (channel "whatsapp"), like SMS and email. Never throws.
 *
 * Goes through EvolutionClient, not the plugin's WhatsappService/Whatsapp facade: that one's
 * formatNumber() takes any 10-11 digit number for a Brazilian one and prefixes 55, and an Omani
 * number with its country code (968 + 8 digits) is exactly 11 digits.
 */
class WhatsAppNotifier
{
    public function __construct(private readonly EvolutionClient $client) {}

    /** The instance set in the settings, else the first connected one. */
    public function instance(): ?WhatsappInstance
    {
        $id = WhatsAppSettings::instanceId();

        return ($id ? WhatsappInstance::find($id) : null)
            ?? WhatsappInstance::where('status', StatusConnectionEnum::OPEN)->first();
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * @param  string  $type  what the message is for (new_horse_sale_post, test, ...), for the log
     */
    public function send(string $to, string $message, string $type): NotificationLog
    {
        $phone = PhoneNumber::normalize($to);
        $instance = null;
        $response = null;
        $error = null;

        try {
            $instance = $this->instance();

            $error = match (true) {
                $phone === null => 'Invalid phone number.',
                ! $this->client->isConfigured() => 'The Evolution API is not set up (EVOLUTION_URL / EVOLUTION_API_KEY in .env).',
                $instance === null => 'No WhatsApp instance is connected.',
                default => null,
            };

            if ($error === null) {
                $response = $this->client->sendText($instance->name, $phone, $message);
            }
        } catch (Throwable $e) {
            $error = 'WhatsApp error: '.$e->getMessage();
        }

        return NotificationLog::create([
            'channel' => 'whatsapp',
            'type' => $type,
            'recipient' => $phone ?? $to,
            'status' => $error === null ? 'sent' : 'failed',
            'message' => $message,
            'provider_reference' => $response['key']['id'] ?? null,
            'error' => $error,
            'response' => $response ?? ($instance ? ['instance' => $instance->name] : null),
        ]);
    }
}
