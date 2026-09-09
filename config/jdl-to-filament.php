<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Output paths
    |--------------------------------------------------------------------------
    |
    | Where jdl:migrations, jdl:models, and jdl:filament write generated
    | files by default, relative to your project root. Override per-run
    | with each command's --path option instead, if you only need a
    | one-off different location.
    |
    */

    'migrations_path' => 'database/migrations',

    'models_path' => 'app/Models',

    'filament_resources_path' => 'app/Filament/Resources',

    /*
    |--------------------------------------------------------------------------
    | Node parser script
    |--------------------------------------------------------------------------
    |
    | This package ships its own node/parse.js (a thin wrapper around
    | jhipster-core) and finds it automatically relative to wherever the
    | package is installed. You should not normally need to set this -
    | it's here in case you want to point at a different copy of the
    | script (e.g. a customized fork of parse.js), or the package's
    | on-disk layout changes in your setup.
    |
    */

    'node_script_path' => null,

];
