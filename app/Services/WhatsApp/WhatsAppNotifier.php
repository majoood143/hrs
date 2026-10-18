<?php

namespace App\Services\WhatsApp;

use App\Models\NotificationLog;
use App\Support\PhoneNumber;
use App\Support\WhatsAppSettings;
use Throwable;
use WallaceMartinss\FilamentEvolution\Enums\StatusConnectionEnum;
use WallaceMartinss\FilamentEvolution\Models\WhatsappInstance;
use WallaceMartinss\FilamentEvolution\Models\WhatsappMessage;
use WallaceMartinss\FilamentEvolution\Services\EvolutionClient;

/**
 * Sends one WhatsApp text through the Evolution API (the filament-whatsapp-conector plugin) and
 * writes the attempt to notification_logs (channel "whatsapp"), like SMS and email. Never throws.
 * A sent text is also kept in the plugin's whatsapp_messages (admin → WhatsApp messages), where
 * the MESSAGES_UPDATE webhooks then move it to delivered / read.
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
     * @param  bool  $sensitive  keep the text out of the log (one-time codes)
     */
    public function send(string $to, string $message, string $type, bool $sensitive = false): NotificationLog
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
                $this->record($instance, $phone, $sensitive ? null : $message, $response);
            }
        } catch (Throwable $e) {
            $error = 'WhatsApp error: '.$e->getMessage();
        }

        return NotificationLog::create([
            'channel' => 'whatsapp',
            'type' => $type,
            'recipient' => $phone ?? $to,
            'status' => $error === null ? 'sent' : 'failed',
            'message' => $sensitive ? null : $message,
            'provider_reference' => $response['key']['id'] ?? null,
            'error' => $error,
            'response' => $response ?? ($instance ? ['instance' => $instance->name] : null),
        ]);
    }

    /** Never lets a bookkeeping error turn a sent message into a failed one. */
    private function record(WhatsappInstance $instance, string $phone, ?string $message, array $response): void
    {
        try {
            WhatsappMessage::create([
                'instance_id' => $instance->id,
                'message_id' => $response['key']['id'] ?? null,
                'remote_jid' => $response['key']['remoteJid'] ?? $phone.'@s.whatsapp.net',
                'phone' => $phone,
                'direction' => 'outgoing',
                'type' => 'text',
                'content' => ['text' => $message],
                'status' => 'sent',
                'raw_payload' => $message === null ? null : $response,
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
