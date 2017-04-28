<?php

return [

    /*
     |--------------------------------------------------------------------------
     | reporter Settings
     |--------------------------------------------------------------------------
     |
     | reporter is enabled by default, when debug is set to true in app.php.
     | You can override the value by setting enable to true or false instead of null.
     |
     */

    'enabled' => true,


    /*
     |--------------------------------------------------------------------------
     | DataCollectors
     |--------------------------------------------------------------------------
     |
     | Enable/disable DataCollectors
     |
     */

    'collectors' => [
        'phpinfo'         => true,  // Php version
        'time'            => true,  // Time Datalogger
        'memory'          => true,  // Memory usage
        'exceptions'      => true,  // Exception displayer
        'log'             => true,  // Logs from Monolog (merged in messages if enabled)
        'db'              => true,  // Show database (PDO) queries and bindings
        'route'           => true,  // Current route information
        'laravel'         => true, // Laravel version and environment
        'events'          => true, // All events fired
        'config'          => true, // Display config settings
        'auth'            => true, // Display Laravel authentication status
    ],

    /*
     |--------------------------------------------------------------------------
     | Reporter
     |--------------------------------------------------------------------------
     |
     | reporter configs
     |
     */

    'report'=>[
        'queue'=>true,

    ]


];
