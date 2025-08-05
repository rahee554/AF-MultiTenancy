<?php

namespace ArtflowStudio\Tenancy\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     *
     * @return Application
     */
    public function createApplication()
    {
        $app = require __DIR__ . '/../../../../bootstrap/app.php';
        
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure the tenancy package is properly loaded
        $this->app->register(\ArtflowStudio\Tenancy\TenancyServiceProvider::class);
    }
}
