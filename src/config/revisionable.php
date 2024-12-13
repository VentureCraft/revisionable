<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Revision Model
    |--------------------------------------------------------------------------
    */
    'model' => App\Services\Revisionable\Revision::class,

    'additional_fields' => [],

    'db_connection' => env('LOGS_DB_CONNECTION'),

];
