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
    | 'lorem': Use real english words by setting this to 'false'.
    |
    */

    'title' => [
        'chars' => [$min = 10, $max = 20],
        'lorem' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Assets Settings
    |--------------------------------------------------------------------------
    |
    | The settings that will be used to create image assets.
    |
    | 'width': The width of the generated image in pixels.
    | 'height': The height of the generated image in pixels.
    | 'category': The image category. Set to 'null' to get a random category.
    |
    */

    'assets' => [
        'width' => 200,
        'height' => 200,
        'category' => null,
    ],

];