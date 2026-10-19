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
        'folder_helper' => 'Pre-selected when a video from this channel is added to the library.',
        'skip_shorts' => 'Skip Shorts',
        'skip_shorts_helper' => 'No alert and no inbox entry for Shorts.',
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

    'notifications' => [
        'added' => 'Watching :channel',
        'added_body' => ':count recent video(s) are in the YouTube inbox. You will get a WhatsApp alert for new ones.',
        'checked' => ':channel checked',
        'checked_body' => ':found new, :alerted alerted.',
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
