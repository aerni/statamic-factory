<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Publish Status
    |--------------------------------------------------------------------------
    |
    | The default publish status of the content created by the factory.
    | Tipp: Randomize the status by setting this to '(bool) random_int(0, 1)'.
    |
    */

    'published' => true,

    /*
    |--------------------------------------------------------------------------
    | Title Settings
    |--------------------------------------------------------------------------
    |
    | 'chars': The character count of the title will be in this range.
    | 'lorem': You may use real words by setting this to 'false'.
    |
    */

    'title' => [
        'chars' => [$min = 10, $max = 20],
        'lorem' => false,
    ],

];