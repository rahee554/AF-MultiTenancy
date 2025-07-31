<?php

namespace ArtflowStudio\Tenancy\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantQuotaWarning
{
    use Dispatchable, SerializesModels;

    public string $tenantId;
    public string $resource;
    public $currentUsage;
    public $limit;

    public function __construct(string $tenantId, string $resource, $currentUsage, $limit)
    {
        $this->tenantId = $tenantId;
        $this->resource = $resource;
        $this->currentUsage = $currentUsage;
        $this->limit = $limit;
    }
}
