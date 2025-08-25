<?php

namespace ArtflowStudio\Tenancy\Events;

class TenantCacheAccessed
{
    public function __construct(
        public string $tenantName,
        public string $operation,
        public string $key,
        public bool $hit = false
    ) {}
}
