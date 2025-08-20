<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Primary URL (one-line credentials)
    |--------------------------------------------------------------------------
    | Supports: CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME
    */
    'cloud_url' => env('CLOUDINARY_URL', null),
    'url'       => env('CLOUDINARY_URL', null),

    /*
    |--------------------------------------------------------------------------
    | Explicit credentials (some versions read these directly)
    |--------------------------------------------------------------------------
    */
    'cloud' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME', null),
        'api_key'    => env('CLOUDINARY_API_KEY', null),
        'api_secret' => env('CLOUDINARY_API_SECRET', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default upload options
    |--------------------------------------------------------------------------
    */
    'upload' => [
        'folder'        => env('CLOUDINARY_DEFAULT_FOLDER', 'profile_photos'),
        'resource_type' => 'image',
        'overwrite'     => true,
        'invalidate'    => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | URL settings
    |--------------------------------------------------------------------------
    */
    'asset' => [
        'secure' => true,
    ],
];
