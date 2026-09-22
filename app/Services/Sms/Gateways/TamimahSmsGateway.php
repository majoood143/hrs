<?php

namespace App\Services\Sms\Gateways;

use App\Models\SiteSetting;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsResult;
use App\Support\SecretSetting;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Tamimah Telecom's bulk SMS web service (SOAP 1.1, https://tamimahsms.com/user/bulkpush.asmx).
 *
 * The request is the SendSMS operation exactly as their service description shows it. What their
 * public pages do not say (the meaning of each StatusCode, how Priority and Schdate are used) is
 * left configurable, and the whole response is kept in the notification log for diagnosis.
 * A message counts as sent when the service answers without a SOAP fault and reports at least
 * one message processed; if "success codes" are set in the settings, the StatusCode must be one of them too.
 */
class TamimahSmsGateway implements SmsGateway
{
    private const NAMESPACE = 'https://www.tamimahsms.com/';

    private const DEFAULT_ENDPOINT = 'https://tamimahsms.com/user/bulkpush.asmx';

    public function isConfigured(): bool
    {
        return $this->username() !== '' && $this->password() !== '' && $this->sender() !== '';
    }

    private function username(): string
    {
        return (string) SiteSetting::get('sms.tamimah.username', '');
    }

    private function password(): string
    {
        return SecretSetting::get('sms.tamimah.password');
    }

    private function sender(): string
    {
        return (string) SiteSetting::get('sms.tamimah.sender', '');
    }

    private function endpoint(): string
    {
        return (string) (SiteSetting::get('sms.tamimah.endpoint') ?: self::DEFAULT_ENDPOINT);
    }

    /** @return list<string> */
    private function successCodes(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) SiteSetting::get('sms.tamimah.success_codes', '')))));
    }

    public function send(string $to, string $message): SmsResult
    {
        if (! $this->isConfigured()) {
            return SmsResult::failed('Tamimah SMS is not configured.');
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['SOAPAction' => '"'.self::NAMESPACE.'SendSMS"'])
                ->withBody($this->envelope($to, $message), 'text/xml; charset=utf-8')
                ->post($this->endpoint());
        } catch (Throwable $e) {
            return SmsResult::failed('Could not reach Tamimah: '.$e->getMessage());
        }

        return $this->interpret($response->status(), $response->body());
    }

    /** The SendSMS request. Every value is XML-escaped, so a message can hold any character. */
    public function envelope(string $to, string $message): string
    {
        $x = fn (mixed $value): string => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $ns = self::NAMESPACE;

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <SendSMS xmlns="{$ns}">
      <UserName>{$x($this->username())}</UserName>
      <Password>{$x($this->password())}</Password>
      <Message>{$x($message)}</Message>
      <Priority>{$x((int) SiteSetting::get('sms.tamimah.priority', 0))}</Priority>
      <Schdate>{$x('')}</Schdate>
      <Sender>{$x($this->sender())}</Sender>
      <AppID>{$x(SiteSetting::get('sms.tamimah.app_id', ''))}</AppID>
      <SourceRef>{$x('')}</SourceRef>
      <MSISDNs>{$x($to)}</MSISDNs>
    </SendSMS>
  </soap:Body>
</soap:Envelope>
XML;
    }

    /** Turn the service's answer into a result. Public so the parsing can be tested on its own. */
    public function interpret(int $httpStatus, string $body): SmsResult
    {
        $fields = $this->parse($body);

        if (isset($fields['faultstring'])) {
            return SmsResult::failed('Tamimah fault: '.$fields['faultstring'], $fields);
        }

        if ($httpStatus < 200 || $httpStatus >= 300) {
            return SmsResult::failed("Tamimah answered HTTP {$httpStatus}.", $fields + ['http_status' => $httpStatus]);
        }

        if (! array_key_exists('Proccessed', $fields)) {
            return SmsResult::failed('Tamimah sent an answer we could not read.', $fields + ['raw' => mb_substr($body, 0, 500)]);
        }

        $description = $fields['StatusDesc'] ?? $fields['StatusCode'] ?? 'no description';
        $codes = $this->successCodes();

        if ((int) $fields['Proccessed'] < 1) {
            return SmsResult::failed("Tamimah processed no message: {$description}", $fields);
        }

        if ($codes !== [] && ! in_array($fields['StatusCode'] ?? '', $codes, true)) {
            return SmsResult::failed("Tamimah status {$fields['StatusCode']}: {$description}", $fields);
        }

        return new SmsResult(true, $fields['BatchRefCode'] ?? null, null, $fields);
    }

    /**
     * The values inside the response, whatever the namespace prefixes are.
     *
     * @return array<string, string>
     */
    private function parse(string $body): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, options: LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return [];
        }

        $found = [];

        foreach (['Proccessed', 'StatusCode', 'BatchRefCode', 'StatusDesc', 'faultstring'] as $name) {
            $nodes = $xml->xpath("//*[local-name()='{$name}']");

            if (! empty($nodes)) {
                $found[$name] = trim((string) $nodes[0]);
            }
        }

        return $found;
    }
}
