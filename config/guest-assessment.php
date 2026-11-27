<?php

return [
    'rehearsal' => (bool) env('ASSESSMENT_GUEST_REHEARSAL', false),
    'production_accepted' => (bool) env('ASSESSMENT_GUEST_PRODUCTION_ACCEPTED', false),
    'production_enabled' => (bool) env('ASSESSMENT_GUEST_PRODUCTION_ENABLED', false),
];
