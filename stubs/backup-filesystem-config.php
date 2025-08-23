<?php

/*
|--------------------------------------------------------------------------
| Tenant Backup Filesystem Disk Configuration
|--------------------------------------------------------------------------
|
| This file provides the configuration for the tenant-backups disk
| that should be added to your config/filesystems.php file.
|
| Add this to your config/filesystems.php 'disks' array:
|
*/

return [
    'tenant-backups' => [
        'driver' => 'local',
        'root' => storage_path('app/tenant-backups'),
        'permissions' => [
            'file' => [
                'public' => 0644,
                'private' => 0644,
            ],
            'dir' => [
                'public' => 0755,
                'private' => 0755,
            ],
        ],
        'throw' => true,
    ],
];
