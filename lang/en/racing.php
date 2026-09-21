<?php

return [
    'title' => 'Racing Search',
    'eyebrow' => 'Racing Database',
    'subtitle' => 'Search horses, owners, jockeys and trainers.',
    'default_heading' => 'Search the racing database',

    'query_label' => 'Search term',
    'placeholder' => 'Enter a name…',
    'type_label' => 'Search in',
    'search' => 'Search',
    'hint' => 'Search using the English spelling of the name (at least :min characters).',
    'min_chars' => 'Please enter at least :min characters.',

    'types' => [
        'all' => 'All',
        'horse' => 'Horses',
        'owner' => 'Owners',
        'jockey' => 'Jockeys',
        'trainer' => 'Trainers',
    ],

    'profile_types' => [
        'horse' => 'Horse',
        'owner' => 'Owner',
        'jockey' => 'Jockey',
        'trainer' => 'Trainer',
    ],

    'results_for' => 'Results for “:q”',
    'no_results_title' => 'No results found',
    'no_results_body' => 'Try a different spelling or fewer letters.',
    'view_all' => 'View all :count',
    'showing' => 'Showing :from–:to of :total',

    'unavailable_title' => 'Racing data is temporarily unavailable',
    'unavailable_body' => 'Please try again in a few minutes.',

    'back' => 'Back to search',
    'rating' => 'Rating',
    'days_since_run' => 'Days since last run',
    'no_data' => 'No data available.',

    'nav' => [
        'search' => 'Search',
        'results' => 'Results',
        'entries' => 'Entries',
        'card' => 'Race Card',
        'form-guide' => 'Form Guide',
        'handicap' => 'Handicap Ratings',
        'calendar' => 'Calendar',
    ],

    'pages' => [
        'results' => ['title' => 'Race Results', 'unavailable' => 'Results are not available for this race yet.'],
        'entries' => ['title' => 'Race Entries', 'unavailable' => 'Entries are not available for this race.'],
        'card' => ['title' => 'Race Card', 'unavailable' => 'The race card is not available for this race yet.'],
        'form-guide' => ['title' => 'Form Guide', 'unavailable' => 'The form guide is not available for this race yet.'],
        'handicap' => ['title' => 'Handicap Ratings', 'subtitle' => 'Current ratings for every horse, with the latest change.'],
    ],

    'race_n' => 'Race :n',
    'races_in_meeting' => 'Races in this meeting',
    'watch_replay' => 'Watch replay',
    'prize_money' => 'Prize money',
    'owners' => 'Owners',
    'overweights' => 'Overweights',
    'no_meeting_title' => 'No meeting available',
    'no_meeting_body' => 'There is no meeting to show right now. Please check back soon.',
    'no_previous_runs' => 'No previous runs.',

    'card_labels' => [
        'no' => 'No',
        'gate' => 'Gate',
        'weight' => 'Weight (kg)',
        'owner' => 'Owner',
        'trainer' => 'Trainer',
        'jockey' => 'Jockey',
        'record' => 'Runs (1st-2nd-3rd)',
    ],

    'handicap' => [
        'search_horse' => 'Horse name',
        'breed' => 'Breed',
        'location' => 'Location',
        'breeds' => ['all' => 'All', 'tb' => 'Thoroughbred', 'pa' => 'Purebred Arabian'],
        'locations' => ['all' => 'All', 'local' => 'Local', 'non-local' => 'Non-Local'],
        'columns' => ['horse' => 'Horse', 'rating' => 'Rating', 'rating_date' => 'Rating date', 'last_ran' => 'Last ran'],
        'up' => 'rating went up',
        'down' => 'rating went down',
        'nor' => 'Not officially rated',
    ],

    'calendar' => [
        'title' => 'Race Calendar',
        'subtitle' => 'Pick a season, then a race day to see its results or entries.',
        'season' => 'Season',
        'show' => 'Show',
        'legend' => ':count race days this season. Select one to open it.',
        'no_meetings' => 'No race days are scheduled for this season.',
        'weekdays' => ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
    ],

    'widget' => [
        'title' => 'Races',
        'subtitle' => 'Choose a date to see that day\'s races. Days with races are highlighted.',
        'prev_month' => 'Previous month',
        'next_month' => 'Next month',
        'legend' => ':count race days this month',
        'no_meetings_month' => 'No race days this month',
        'choose' => 'Choose a date to see detailed racing information.',
        'race_count' => 'Races:',
        'meeting' => 'Meeting:',
        'first_race' => 'First race:',
        'no_races' => 'No races are listed for this day.',
        'not_yet' => 'Entries, race cards and results appear here once they are published.',
        'distance' => ':m m',
        'links' => [
            'video' => 'Video',
            'photo' => 'Photo',
            'entries' => 'Entries',
            'card' => 'Race card',
            'results' => 'Results',
        ],
    ],

    'tools' => [
        'print' => 'Print',
        'pdf' => 'PDF',
        'pdf_title' => 'Download as PDF',
        'share' => 'Share',
    ],

    'pdf' => [
        'generated' => 'Generated :date',
        'scan' => 'Scan to open online',
        'page' => 'Page',
    ],
];
