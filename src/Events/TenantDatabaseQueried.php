<?php

namespace ArtflowStudio\Tenancy\Events;

class TenantDatabaseQueried
{
    public function __construct(
        public string $tenantName,
        public float $queryTime,
        public string $query,
        public string $connection
    ) {}
}
