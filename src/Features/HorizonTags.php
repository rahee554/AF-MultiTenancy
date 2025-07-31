<?php

declare(strict_types=1);

namespace ArtflowStudio\Tenancy\Features;

use Stancl\Tenancy\Contracts\Feature;
use Stancl\Tenancy\Tenancy;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\Tags\TaggedJob;

/**
 * Horizon Integration with Tenant Tagging
 * 
 * Automatically tags Horizon jobs with tenant information including:
 * - tenant:{id}
 * - tenant_name:{name}
 * - domain:{domain}
 */
class HorizonTags implements Feature
{
    public function bootstrap(Tenancy $tenancy): void
    {
        if (!class_exists(Horizon::class)) {
            return;
        }

        // Add tenant tagging to Horizon jobs
        Horizon::routeMailNotificationsTo(function () {
            // You can customize notification routing here
            return config('mail.from.address');
        });

        // Tag jobs with tenant information
        $this->registerJobTagging();
    }

    protected function registerJobTagging(): void
    {
        // Override the default job tagging behavior
        app()->singleton('horizon.job-tagger', function () {
            return new class {
                public function tags($payload)
                {
                    $tags = [];

                    if (tenancy()->initialized) {
                        $tenant = tenant();
                        
                        // Add tenant ID tag
                        $tags[] = "tenant:{$tenant->id}";
                        
                        // Add tenant name tag if available
                        if (!empty($tenant->name)) {
                            $tags[] = "tenant_name:" . str_replace(' ', '_', $tenant->name);
                        }
                        
                        // Add domain tag
                        $domain = $tenant->domains()->first();
                        if ($domain) {
                            $tags[] = "domain:{$domain->domain}";
                        }
                        
                        // Add project identifier if configured
                        $projectId = config('artflow-tenancy.project.id');
                        if ($projectId) {
                            $tags[] = "project:{$projectId}";
                        }
                    } else {
                        $tags[] = 'central';
                    }

                    return $tags;
                }
            };
        });
    }
}
