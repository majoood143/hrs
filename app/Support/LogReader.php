<?php

namespace App\Support;

/**
 * Reads Laravel log files from the end, entry by entry, without loading the
 * whole file (the logs can be hundreds of MB). An entry is a header line
 * ("[2026-10-18 18:48:00] production.ERROR: message") plus the lines under it
 * (stack trace, context).
 */
class LogReader
{
    public const LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    private const HEADER = '/^\[(\d{4}-\d{2}-\d{2}[T ][0-9:.+\-]+)\]\s+([\w-]+)\.(\w+):\s?(.*)$/';

    private const CHUNK = 256 * 1024;

    /** Stop looking for more matches after this much of the file. */
    private const MAX_SCAN = 64 * 1024 * 1024;

    private const MAX_LINE = 2000;

    private const MAX_DETAIL_LINES = 300;

    private const MAX_DETAILS = 16 * 1024;

    /**
     * The *.log files in storage/logs, newest first.
     *
     * @return array<string, array{size: int, modified: int}>
     */
    public static function files(?string $directory = null): array
    {
        $directory ??= storage_path('logs');
        $files = [];

        foreach (glob($directory.'/*.log') ?: [] as $path) {
            $files[basename($path)] = ['size' => (int) @filesize($path), 'modified' => (int) @filemtime($path)];
        }

        uasort($files, fn (array $a, array $b) => $b['modified'] <=> $a['modified']);

        return $files;
    }

    /**
     * The last $limit entries (newest first) matching the level and search.
     *
     * @return array{entries: list<array{time: string, env: string, level: string, message: string, details: string}>, truncated: bool}
     */
    public static function tail(string $path, int $limit = 50, ?string $level = null, ?string $search = null): array
    {
        $handle = is_file($path) && is_readable($path) ? @fopen($path, 'rb') : false;

        if (! $handle) {
            return ['entries' => [], 'truncated' => false];
        }

        $level = $level ? strtolower($level) : null;
        $search = $search !== null && trim($search) !== '' ? trim($search) : null;

        $position = (int) filesize($path);
        $scanned = 0;
        $carry = '';
        $pending = [];
        $entries = [];

        while ($position > 0 && count($entries) < $limit) {
            if ($scanned >= self::MAX_SCAN) {
                fclose($handle);

                return ['entries' => $entries, 'truncated' => true];
            }

            $read = min(self::CHUNK, $position);
            $position -= $read;
            fseek($handle, $position);
            $buffer = fread($handle, $read).$carry;
            $scanned += $read;

            $lines = explode("\n", $buffer);
            // The first line may continue in the previous chunk.
            $carry = $position > 0 ? array_shift($lines) : '';

            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $line = rtrim($lines[$i], "\r");

                if (! preg_match(self::HEADER, $line, $m)) {
                    if ($line !== '' || $pending !== []) {
                        $pending[] = mb_strimwidth($line, 0, self::MAX_LINE, '…');

                        if (count($pending) > self::MAX_DETAIL_LINES) {
                            array_shift($pending);
                        }
                    }

                    continue;
                }

                $entry = [
                    'time' => $m[1],
                    'env' => $m[2],
                    'level' => strtolower($m[3]),
                    'message' => mb_strimwidth($m[4], 0, self::MAX_LINE, '…'),
                    'details' => mb_strcut(rtrim(implode("\n", array_reverse($pending))), 0, self::MAX_DETAILS),
                ];
                $pending = [];

                if (self::matches($entry, $level, $search)) {
                    $entries[] = $entry;

                    if (count($entries) >= $limit) {
                        break;
                    }
                }
            }
        }

        fclose($handle);

        return ['entries' => $entries, 'truncated' => false];
    }

    /**
     * @param  array{level: string, message: string, details: string}  $entry
     */
    private static function matches(array $entry, ?string $level, ?string $search): bool
    {
        if ($level && $entry['level'] !== $level) {
            return false;
        }

        return $search === null
            || mb_stripos($entry['message'], $search) !== false
            || mb_stripos($entry['details'], $search) !== false;
    }
}
