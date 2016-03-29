<?php

return array(

    // this is the default model for revisionable
    // you can set model, connection and table name
    'revision' => [
        'model' => '\Venturecraft\Revisionable\Revision',
        'connection' => env('DB_CONNECTION', 'mysql'),,
        'table' => 'revisions',
    ],

    // this is the default model for user responsible
    'user' => [
        'model' => 'App\User',
    ],
);
