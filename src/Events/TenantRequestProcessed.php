<?php

namespace ArtflowStudio\Tenancy\Events;

class TenantRequestProcessed
{
    public function __construct(
        public string $tenantName,
        public float $duration,
        public string $method,
        public string $uri,
        public int $statusCode
    ) {}
}
