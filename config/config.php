<?php

return [
    "storageType" => env('REQUEST_LOG_STORAGE_TYPE', 'mongo'),

    "connection" => [
        "uri" => "mongodb://localhost:27017",
    ],
];
