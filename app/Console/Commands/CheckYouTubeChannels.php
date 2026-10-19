<?php

namespace App\Console\Commands;

use App\Models\YouTubeChannel;
use App\Services\YouTube\ChannelWatcher;
use App\Services\YouTube\YouTubeException;
use Illuminate\Console\Command;

/**
 * Reads every active watched YouTube channel's feed: new videos go to the admin inbox and the
 * admins get a WhatsApp alert (App\Services\YouTube\ChannelWatcher). Scheduled every 15 minutes.
 */
class CheckYouTubeChannels extends Command
{
    protected $signature = 'youtube:check {channel? : a channel id (UC…) to check only that one}';

    protected $description = 'Check the watched YouTube channels for new videos and alert the admins';

    public function handle(ChannelWatcher $watcher): int
    {
        $channels = YouTubeChannel::query()
            ->when($this->argument('channel'), fn ($q, $id) => $q->where('channel_id', $id), fn ($q) => $q->active())
            ->orderBy('id')
            ->get();

        foreach ($channels as $channel) {
            $result = $watcher->check($channel);
            $line = "{$channel->title}: {$result['found']} new, {$result['alerted']} alerted";

            $result['error']
                ? $this->warn($line.' ('.YouTubeException::label($result['error']).')')
                : $this->info($line);
        }

        return self::SUCCESS;
    }
}
