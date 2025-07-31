<?php

/**
 * Post-Installation Setup for ArtflowStudio Tenancy Package
 * 
 * This file contains the essential steps to complete after installing
 * the ArtflowStudio Tenancy package with Universal Middleware.
 */

echo "🚀 ArtflowStudio Tenancy - Post Installation Setup\n";
echo "================================================\n\n";

// Step 1: Environment Configuration
echo "1. 📝 Update your .env file with these settings:\n";
echo "\n";
echo "# Database Root Credentials (REQUIRED for tenant creation)\n";
echo "DB_ROOT_USERNAME=root\n";
echo "DB_ROOT_PASSWORD=your_mysql_root_password\n";
echo "\n";
echo "# Tenant Configuration\n";
echo "TENANT_DB_PREFIX=tenant_\n";
echo "TENANT_AUTO_MIGRATE=false\n";
echo "TENANT_AUTO_SEED=false\n";
echo "\n";
echo "# Universal Middleware Configuration\n";
echo "APP_DOMAIN=localhost\n";
echo "UNKNOWN_DOMAIN_ACTION=central  # Options: central, redirect, 404\n";
echo "\n";
echo "# Redis Configuration (Optional)\n";
echo "REDIS_HOST=127.0.0.1\n";
echo "REDIS_PASSWORD=null\n";
echo "REDIS_PORT=6379\n";
echo "TENANT_REDIS_ENABLED=true\n";
echo "\n";

// Step 2: Route Configuration
echo "2. 🛣️  Update your routes to use Universal Middleware:\n";
echo "\n";
echo "In your routes/web.php, replace:\n";
echo "Route::group(['middleware' => 'web'], function () {\n";
echo "    // Your routes\n";
echo "});\n";
echo "\n";
echo "With:\n";
echo "Route::group(['middleware' => 'universal.web'], function () {\n";
echo "    // Same routes - now work for both central and tenant domains\n";
echo "    Route::get('/dashboard', [DashboardController::class, 'index']);\n";
echo "    Route::get('/profile', [ProfileController::class, 'show']);\n";
echo "});\n";
echo "\n";
echo "For admin/central-only routes:\n";
echo "Route::group(['middleware' => 'central.web'], function () {\n";
echo "    Route::get('/admin', [AdminController::class, 'index']);\n";
echo "    Route::get('/tenants', [TenantsController::class, 'index']);\n";
echo "});\n";
echo "\n";

// Step 3: Configuration Files
echo "3. ⚙️  Configuration files published:\n";
echo "\n";
echo "✅ config/tenancy.php - Core stancl/tenancy configuration\n";
echo "✅ config/artflow-tenancy.php - Enhanced features configuration\n";
echo "\n";
echo "Review and customize these files according to your needs.\n";
echo "\n";

// Step 4: Database Setup
echo "4. 🗄️  Database Setup:\n";
echo "\n";
echo "Run migrations:\n";
echo "php artisan migrate\n";
echo "\n";
echo "This creates the tenants and domains tables.\n";
echo "\n";

// Step 5: Test Installation
echo "5. 🧪 Test Your Installation:\n";
echo "\n";
echo "Run comprehensive tests:\n";
echo "php artisan tenancy:test --create-test-tenant --verbose\n";
echo "\n";
echo "This will:\n";
echo "- Create a test tenant\n";
echo "- Test Universal Middleware\n";
echo "- Test database isolation\n";
echo "- Test cache isolation\n";
echo "- Test Redis isolation (if enabled)\n";
echo "- Show detailed results\n";
echo "\n";

// Step 6: Create Your First Tenant
echo "6. 🏢 Create Your First Real Tenant:\n";
echo "\n";
echo "Use the tenant creation command:\n";
echo "php artisan tenants:create\n";
echo "\n";
echo "Or create programmatically:\n";
echo "\$tenant = \\Stancl\\Tenancy\\Database\\Models\\Tenant::create();\n";
echo "\$tenant->domains()->create(['domain' => 'tenant1.yourapp.com']);\n";
echo "\n";

// Step 7: Optional Integrations
echo "7. 🔧 Optional Integrations:\n";
echo "\n";
echo "Laravel Horizon (Queue Management):\n";
echo "composer require laravel/horizon\n";
echo "php artisan horizon:install\n";
echo "# Horizon integration is automatically configured\n";
echo "\n";
echo "Laravel Telescope (Debugging):\n";
echo "composer require laravel/telescope\n";
echo "php artisan telescope:install\n";
echo "# Telescope integration is automatically configured\n";
echo "\n";

// Step 8: Key Features
echo "8. ✨ Key Features Now Available:\n";
echo "\n";
echo "🌟 Universal Middleware:\n";
echo "   - Automatically detects tenant vs central domains\n";
echo "   - No more 'tenant identification failed' errors\n";
echo "   - Seamless context switching\n";
echo "\n";
echo "🗄️  Database Isolation:\n";
echo "   - Each tenant gets isolated database\n";
echo "   - Automatic database creation and migration\n";
echo "   - Central database for shared data\n";
echo "\n";
echo "💾 Cache & Redis Isolation:\n";
echo "   - Tenant-specific cache prefixes\n";
echo "   - Multi-database Redis support\n";
echo "   - Session scoping per tenant\n";
echo "\n";
echo "🧪 Comprehensive Testing:\n";
echo "   - Built-in test suite\n";
echo "   - Performance benchmarks\n";
echo "   - Isolation verification\n";
echo "\n";

// Step 9: Troubleshooting
echo "9. 🔍 Troubleshooting:\n";
echo "\n";
echo "If you encounter issues:\n";
echo "\n";
echo "1. Check logs:\n";
echo "   tail -f storage/logs/laravel.log\n";
echo "\n";
echo "2. Run diagnostics:\n";
echo "   php artisan tenancy:test --verbose\n";
echo "\n";
echo "3. Verify configuration:\n";
echo "   php artisan config:cache\n";
echo "   php artisan route:cache\n";
echo "\n";
echo "4. Check database permissions:\n";
echo "   Ensure DB_ROOT_USERNAME has CREATE DATABASE privileges\n";
echo "\n";

// Step 10: Documentation
echo "10. 📚 Documentation:\n";
echo "\n";
echo "Complete documentation available in:\n";
echo "- docs/INSTALLATION_GUIDE.md\n";
echo "- docs/MIDDLEWARE_USAGE_GUIDE.md\n";
echo "- docs/COMPLETE_INTEGRATION_GUIDE.md\n";
echo "- PACKAGE_ANALYSIS_AND_RESTRUCTURE.md (latest changes)\n";
echo "\n";

// Completion
echo "🎉 Installation Complete!\n";
echo "========================\n";
echo "\n";
echo "Your ArtflowStudio Tenancy package is now ready to use.\n";
echo "\n";
echo "Next steps:\n";
echo "1. Update your routes to use 'universal.web' middleware\n";
echo "2. Run: php artisan tenancy:test --create-test-tenant\n";
echo "3. Create your first tenant\n";
echo "4. Test tenant/central domain switching\n";
echo "\n";
echo "Need help? Check the documentation or run tests with --verbose flag.\n";
echo "\n";

?>