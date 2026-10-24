<?php

// Admin → YouTube channels / YouTube inbox (watched channels; App\Services\YouTube). Keep
// lang/ar/admin_youtube.php in step.
return [
    'channels' => [
        'label' => 'YouTube channel',
        'plural' => 'YouTube channels',
        'intro' => 'Public channels we watch (they need not be ours). Every 15 minutes new videos land in the YouTube inbox and the admins get a WhatsApp alert (General Settings → WhatsApp).',
        'empty_heading' => 'No channels watched yet',
        'empty_description' => 'Add a YouTube channel by its link, e.g. youtube.com/@name.',
    ],

    'inbox' => [
        'label' => 'YouTube video',
        'plural' => 'YouTube inbox',
        'empty_heading' => 'Nothing new',
        'empty_description' => 'New videos from the watched channels show up here.',
    ],

    'fields' => [
        'url' => 'Channel link',
        'url_helper' => 'A youtube.com/@name or youtube.com/channel/UC… link, or the @handle.',
        'channel' => 'Channel',
        'channel_id' => 'Channel ID',
        'folder' => 'Default video folder',
        'folder_helper' => 'Pre-selected when a video from this channel is added to the library, and where videos added automatically go.',
        'skip_shorts' => 'Skip Shorts',
        'skip_shorts_helper' => 'No alert and no inbox entry for Shorts.',
        'auto_add' => 'New videos',
        'auto_add_helper' => 'Not on the first check, nor for live streams until they have ended. Videos the channel does not allow on other sites stay in the inbox. Without a YouTube API key a video is always added hidden, since it is not known whether it can play here.',
        'notify' => 'WhatsApp alert',
        'notify_helper' => 'Send the admins a WhatsApp alert for this channel\'s new videos (also needs the alert switched on in General Settings → WhatsApp).',
        'is_active' => 'Watching',
        'checked_at' => 'Last checked',
        'never_checked' => 'Not checked yet',
        'last_error' => 'Problem',
        'waiting' => 'New',
        'title' => 'Title',
        'kind' => 'Type',
        'published_at' => 'Published',
        'scheduled_at' => 'Starts',
        'duration' => 'Length',
        'embeddable' => 'Plays on other sites',
        'status' => 'Status',
        'notified_at' => 'Alert sent',
    ],

    'automation' => [
        'heading' => 'Automation',
        'description' => 'What happens when this channel posts a new video.',
    ],

    'auto_add' => [
        'off' => 'Wait in the inbox',
        'hidden' => 'Add to the library, hidden',
        'publish' => 'Add to the library and publish',
    ],

    'kinds' => [
        'video' => 'Video',
        'short' => 'Short',
        'live' => 'Live now',
        'upcoming' => 'Scheduled live / premiere',
        'stream' => 'Live stream (ended)',
    ],

    'statuses' => [
        'new' => 'New',
        'added' => 'In the library',
        'ignored' => 'Ignored',
        'skipped' => 'Skipped (Short)',
    ],

    'actions' => [
        'check_now' => 'Check now',
        'open_youtube' => 'Open on YouTube',
        'add_to_library' => 'Add to library',
        'open_in_library' => 'Open in library',
        'ignore' => 'Ignore',
        'restore' => 'Back to new',
        'check_all' => 'Check all channels now',
    ],

    'bulk_add' => [
        'heading' => 'Add the selected videos to the library',
        'description' => 'They are added as they are on YouTube: the title and description go in both languages, so translate them in the video library afterwards.',
        'folder' => 'Video folder',
        'folder_helper' => 'Leave empty to use each channel\'s default folder.',
        'channel_folder' => 'Each channel\'s folder',
        'publish' => 'Publish them now',
        'publish_helper' => 'Off: added hidden, to check before publishing. Videos YouTube does not allow on other sites are always added hidden.',
        'done' => ':count added to the video library',
        'linked' => ':count were already in the library.',
        'not_yet' => ':count live or scheduled stream(s) left in the inbox until they end.',
        'no_folder' => ':count skipped: their channel has no default folder, choose one.',
    ],

    'notifications' => [
        'added' => 'Watching :channel',
        'added_body' => ':count recent video(s) are in the YouTube inbox. You will get a WhatsApp alert for new ones.',
        'checked' => ':channel checked',
        'checked_body' => ':found new, :alerted alerted.',
        'auto_added_body' => ':count added to the video library.',
        'check_failed' => ':channel could not be checked',
        'checked_all' => 'Channels checked',
        'checked_all_body' => ':found new video(s) in all.',
        'ignored' => 'Ignored',
        'restored' => 'Back to new',
    ],

    'validation' => [
        'duplicate' => 'This channel is already watched.',
    ],

    'no_key' => 'No YouTube API key is set (YOUTUBE_API_KEY in .env): new videos are still found, but without live/scheduled status, length or whether they may be embedded.',

    'errors' => [
        'not_found' => 'YouTube does not know this channel.',
        'unreachable' => 'YouTube could not be reached.',
        'feed_failed' => 'The channel feed answered with an error (:detail).',
        'bad_feed' => 'The channel feed could not be read.',
        'api_failed' => 'The YouTube API refused the request (:detail).',
        'unknown' => 'Something went wrong (:detail).',
    ],
];
