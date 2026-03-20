<?php

return [
    'name' => env('CHURCH_NAME', 'Flock'),
    'tagline' => env('CHURCH_TAGLINE', 'Church Management'),
    'colors' => [
        'primary' => env('CHURCH_COLOR_PRIMARY', '#4f46e5'),
        'secondary' => env('CHURCH_COLOR_SECONDARY', '#7c3aed'),
    ],
    'font' => env('CHURCH_FONT', 'Inter'),
    'logo_path' => env('CHURCH_LOGO', 'images/flock-logo.svg'),
    'dark_mode' => env('CHURCH_DARK_MODE', true),
    'timezone' => env('CHURCH_TIMEZONE', 'Europe/London'),
    'attendance_day' => env('CHURCH_ATTENDANCE_DAY', 0),
];
