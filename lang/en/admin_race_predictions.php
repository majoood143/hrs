<?php

return [
    'type' => 'Race winner prediction',
    'type_description' => "A race's runners from the racing calendar, to pick the winner",
    'race_number' => 'Race :number',
    'form_name' => 'Race predictions · :date',

    'fields' => [
        'name' => 'Name',
        'mobile' => 'Mobile',
    ],

    'editor' => [
        'race_date' => 'Race day',
        'race_date_hint' => 'From the racing calendar (this season and the next).',
        'race' => 'Race',
        'runners' => 'Runners',
        'runners_hint' => 'Pick a race to fill in its runners.',
        'runners_from_card' => 'From the race card, updated :time (Muscat time).',
        'runners_from_entries' => 'From the entries (the race card is not out yet), updated :time (Muscat time).',
        'reload' => 'Reload runners',
        'sync_runners' => 'Keep the runners in step with the race card',
        'sync_runners_hint' => 'Every hour until the form closes, horses declared or withdrawn are added or removed. Turn it off to keep the list exactly as edited here.',
        'no_runners' => 'No runners are published for this race yet.',
        'unavailable' => 'The racing site is not answering right now. Try again in a few minutes.',
    ],

    'create' => [
        'action' => 'Race day predictions',
        'heading' => 'Predictions for a race day',
        'description' => 'Adds Name and Mobile, then one "who wins?" question per race with its runners, and closes the form when the first race starts.',
        'submit' => 'Use this race day',
        'no_races' => 'The racing calendar has no races for that day.',
        'runners_pending' => '{1} One race has no runners yet.|[2,*] :count races have no runners yet.',
        'runners_pending_body' => 'They fill in by themselves once the entries or race card are out (checked every hour), or use "Reload runners". Until then visitors see the form as closed.',
    ],

    'reload' => [
        'action' => 'Reload runners',
        'done' => '{1} One race updated from the race card.|[2,*] :count races updated from the race card.',
        'unchanged' => 'The runners are already up to date.',
    ],
];
