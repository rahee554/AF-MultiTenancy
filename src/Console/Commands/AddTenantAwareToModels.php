<?php

namespace ArtflowStudio\Tenancy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AddTenantAwareToModels extends Command
{
    protected $signature = 'af-tenancy:add-tenant-aware';
    protected $description = 'Add TenantAware trait to all business models';

    // Models that should NOT be tenant-aware (central/system models)
    protected $excludeModels = [
        // These stay in central database
        'CreateFlightDetailsTable.php', // Migration model
    ];

    // Models that should be tenant-aware (business models)
    protected $includeModels = [
        'Airline.php',
        'Airport.php', 
        'Booking.php',
        'BookingService.php',
        'BookingTransaction.php',
        'Customer.php',
        'Hotel.php',
        'HotelBooking.php',
        'Partner.php',
        'PartnerBooking.php',
        'PartnerTransaction.php',
        'Service.php',
        'Invoice.php',
        'TransportBooking.php',
        'FlightDetail.php',
        'Document.php',
        'City.php',
        'District.php',
        'Category.php',
        'Budget.php',
        'AuditTrail.php',
        // Add more as needed
    ];

    public function handle()
    {
        $this->info("🔧 Adding TenantAware trait to business models");
        $this->info(str_repeat('=', 50));

        $modelsPath = app_path('Models');
        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($this->includeModels as $modelFile) {
            $filePath = $modelsPath . '/' . $modelFile;
            
            if (!File::exists($filePath)) {
                $this->warn("⚠️  Model not found: {$modelFile}");
                continue;
            }

            $content = File::get($filePath);
            
            // Check if trait is already added
            if (strpos($content, 'use App\\Traits\\TenantAware;') !== false) {
                $this->info("✅ Already updated: {$modelFile}");
                $skippedCount++;
                continue;
            }

            // Add the trait import and usage
            $updatedContent = $this->addTenantAwareTrait($content, $modelFile);
            
            if ($updatedContent !== $content) {
                File::put($filePath, $updatedContent);
                $this->info("✅ Updated: {$modelFile}");
                $updatedCount++;
            } else {
                $this->warn("⚠️  Could not update: {$modelFile}");
                $skippedCount++;
            }
        }

        $this->info("\n🎯 Summary:");
        $this->info("- Updated: {$updatedCount} models");
        $this->info("- Skipped: {$skippedCount} models");
        
        if ($updatedCount > 0) {
            $this->info("\n🚀 All business models are now tenant-aware!");
            $this->info("   CRUD operations will use the correct tenant database.");
        }

        return 0;
    }

    protected function addTenantAwareTrait($content, $filename)
    {
        // Add import after other use statements
        if (strpos($content, 'use Illuminate\\Database\\Eloquent\\Model;') !== false) {
            $content = str_replace(
                'use Illuminate\\Database\\Eloquent\\Model;',
                "use Illuminate\\Database\\Eloquent\\Model;\nuse App\\Traits\\TenantAware;",
                $content
            );
        } else {
            // Add after namespace
            $content = preg_replace(
                '/namespace\s+App\\\\Models;/',
                "namespace App\\Models;\n\nuse App\\Traits\\TenantAware;",
                $content
            );
        }

        // Add trait usage in class
        $content = preg_replace(
            '/class\s+\w+\s+extends\s+Model\s*{/',
            '$0' . "\n    use TenantAware;\n",
            $content
        );

        return $content;
    }
}
