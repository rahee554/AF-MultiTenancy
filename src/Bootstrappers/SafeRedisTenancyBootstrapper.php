<?php

declare(strict_types=1);

namespace ArtflowStudio\Tenancy\Bootstrappers;

use Illuminate\Support\Facades\Redis;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;
use Illuminate\Support\Facades\Log;

class SafeRedisTenancyBootstrapper implements TenancyBootstrapper
{
    /** @var array Original Redis prefixes. */
    public $originalPrefixes = [];

    public function bootstrap(Tenant $tenant)
    {
        $prefix = app('config')['tenancy.redis.prefix_base'] . $tenant->getTenantKey();

        foreach ($this->prefixedConnections() as $connection) {
            try {
                $client = Redis::connection($connection)->client();
                
                // Store original prefix only if not already stored
                if (!isset($this->originalPrefixes[$connection])) {
                    $this->originalPrefixes[$connection] = $client->getOption(\Redis::OPT_PREFIX) ?: '';
                }

                $client->setOption(\Redis::OPT_PREFIX, $prefix);
            } catch (\Exception $e) {
                Log::warning("SafeRedisTenancyBootstrapper: Failed to set prefix for connection '{$connection}': " . $e->getMessage());
                // Store empty original prefix as fallback
                if (!isset($this->originalPrefixes[$connection])) {
                    $this->originalPrefixes[$connection] = '';
                }
            }
        }
    }

    public function revert()
    {
        foreach ($this->prefixedConnections() as $connection) {
            try {
                $client = Redis::connection($connection)->client();

                // Safety check to prevent "undefined array key" errors
                if (isset($this->originalPrefixes[$connection])) {
                    $client->setOption(\Redis::OPT_PREFIX, $this->originalPrefixes[$connection]);
                } else {
                    // Fallback - set empty prefix
                    $client->setOption(\Redis::OPT_PREFIX, '');
                    Log::warning("SafeRedisTenancyBootstrapper: Missing original prefix for connection '{$connection}', using empty prefix");
                }
            } catch (\Exception $e) {
                Log::error("SafeRedisTenancyBootstrapper: Failed to revert prefix for connection '{$connection}': " . $e->getMessage());
            }
        }

        $this->originalPrefixes = [];
    }

    protected function prefixedConnections(): array
    {
        return app('config')['tenancy.redis.prefixed_connections'] ?? ['default'];
    }
}
