<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Revision Model
    |--------------------------------------------------------------------------
    */
    'route-prefix'               => 'revisions',
    'middleware'                 => ['web', 'auth'],
    'revisionable-model-binding' => []
];
