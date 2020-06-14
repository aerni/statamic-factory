<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Publish Status
    |--------------------------------------------------------------------------
    |
    | The publish status of collection entries and taxonomy terms
    | created by the factory.
    |
    | Tipp: Randomize the status by setting this to '(bool) random_int(0, 1)'.
    |
    */

    'published' => true,

    /*
    |--------------------------------------------------------------------------
    | Title Settings
    |--------------------------------------------------------------------------
    |
    | These settings will be used to create titles of collection entries
    | and taxonomy terms.
    |
    | 'chars': The character count of the title will be in this range.
    | 'real_text': Use real english words by setting this to 'true'.
    |
    */

    'title' => [
        'chars' => [$min = 10, $max = 20],
        'real_text' => false,
    ],

];