<?php

namespace ArtflowStudio\Tenancy\Console\Commands;

use Illuminate\Console\Command;
use ArtflowStudio\Tenancy\Models\Tenant;
use App\Models\User;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Customer;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ComprehensiveDatabaseTest extends Command
{
    protected $signature = 'af-tenancy:test-database {domain?}';
    protected $description = 'Comprehensive test of database isolation for all operations';

    public function handle()
    {
        $domain = $this->argument('domain') ?? 'tenancy1.local';
        
        $this->info("🗄️ COMPREHENSIVE DATABASE ISOLATION TEST");
        $this->info("Domain: {$domain}");
        $this->info(str_repeat('=', 60));

        // 1. Setup tenant context
        $tenant = $this->setupTenantContext($domain);
        if (!$tenant) return 1;

        // 2. Test read operations
        $this->testReadOperations();

        // 3. Test write operations
        $this->testWriteOperations();

        // 4. Test relationship operations
        $this->testRelationshipOperations();

        // 5. Test authentication operations
        $this->testAuthenticationOperations();

        // 6. Verify data isolation
        $this->verifyDataIsolation($tenant);

        $this->info("\n🎉 Database isolation test completed!");
        return 0;
    }

    protected function setupTenantContext($domain)
    {
        $this->info("\n1️⃣ Setting up tenant context:");
        
        try {
            $tenant = Tenant::whereHas('domains', function($query) use ($domain) {
                $query->where('domain', $domain);
            })->first();

            if (!$tenant) {
                $this->error("❌ No tenant found for domain: {$domain}");
                return null;
            }

            $this->info("✅ Tenant found: {$tenant->name}");
            
            tenancy()->initialize($tenant);
            
            $this->info("✅ Tenant context initialized");
            $this->info("   Current DB: " . DB::connection()->getDatabaseName());
            
            return $tenant;
            
        } catch (\Exception $e) {
            $this->error("❌ Tenant setup failed: " . $e->getMessage());
            return null;
        }
    }

    protected function testReadOperations()
    {
        $this->info("\n2️⃣ Testing READ operations:");
        
        $models = [
            'User' => User::class,
            'Airline' => Airline::class,
            'Airport' => Airport::class,
            'Customer' => Customer::class,
            'Booking' => Booking::class,
        ];

        foreach ($models as $name => $class) {
            try {
                $count = $class::count();
                $dbName = (new $class)->getConnection()->getDatabaseName();
                
                $this->info("✅ {$name}: {$count} records (DB: {$dbName})");
                
                // Verify it's using tenant database
                if (strpos($dbName, 'tenant_') === 0) {
                    $this->info("   ✅ Correctly using tenant database");
                } else {
                    $this->error("   ❌ Using wrong database: {$dbName}");
                }
                
            } catch (\Exception $e) {
                $this->error("❌ {$name} read failed: " . $e->getMessage());
            }
        }
    }

    protected function testWriteOperations()
    {
        $this->info("\n3️⃣ Testing WRITE operations:");
        
        try {
            // Test Airline creation
            $airline = new Airline();
            $airline->name = 'Test Airline ' . time();
            $airline->code = 'TST' . rand(100, 999);
            $airline->country = 'Pakistan';
            $airline->active = true;
            $airline->save();
            
            $airlineDb = $airline->getConnection()->getDatabaseName();
            $this->info("✅ Airline created (DB: {$airlineDb})");
            
            if (strpos($airlineDb, 'tenant_') === 0) {
                $this->info("   ✅ Correctly saved to tenant database");
            } else {
                $this->error("   ❌ Saved to wrong database: {$airlineDb}");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Write operation failed: " . $e->getMessage());
        }

        try {
            // Test Customer creation
            $customer = new Customer();
            $customer->name = 'Test Customer ' . time();
            $customer->email = 'test' . time() . '@example.com';
            $customer->phone = '0300-' . rand(1000000, 9999999);
            $customer->save();
            
            $customerDb = $customer->getConnection()->getDatabaseName();
            $this->info("✅ Customer created (DB: {$customerDb})");
            
            if (strpos($customerDb, 'tenant_') === 0) {
                $this->info("   ✅ Correctly saved to tenant database");
            } else {
                $this->error("   ❌ Saved to wrong database: {$customerDb}");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Customer creation failed: " . $e->getMessage());
        }
    }

    protected function testRelationshipOperations()
    {
        $this->info("\n4️⃣ Testing RELATIONSHIP operations:");
        
        try {
            // Find a customer and create a booking
            $customer = Customer::first();
            if ($customer) {
                $booking = new Booking();
                $booking->customer_id = $customer->id;
                $booking->unique_id = 'TEST-' . time();
                $booking->date = now();
                $booking->status = 'confirmed';
                $booking->subtotal = 1000;
                $booking->grand_total = 1000;
                $booking->amount_paid = 1000;
                $booking->balance = 0;
                $booking->save();
                
                $bookingDb = $booking->getConnection()->getDatabaseName();
                $this->info("✅ Booking created (DB: {$bookingDb})");
                
                // Test relationship
                $relatedCustomer = $booking->customer;
                if ($relatedCustomer && $relatedCustomer->id === $customer->id) {
                    $this->info("✅ Relationship working correctly");
                } else {
                    $this->error("❌ Relationship not working");
                }
                
            } else {
                $this->warn("⚠️  No customers found for relationship test");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Relationship test failed: " . $e->getMessage());
        }
    }

    protected function testAuthenticationOperations()
    {
        $this->info("\n5️⃣ Testing AUTHENTICATION operations:");
        
        try {
            // Count users
            $userCount = User::count();
            $this->info("✅ Found {$userCount} users in tenant database");
            
            // Test user lookup
            $testUser = User::where('email', 'admin@tenant.local')->first();
            if ($testUser) {
                $userDb = $testUser->getConnection()->getDatabaseName();
                $this->info("✅ Test user found (DB: {$userDb})");
                
                // Test password verification
                if (Hash::check('password', $testUser->password)) {
                    $this->info("✅ Password verification working");
                } else {
                    $this->error("❌ Password verification failed");
                }
            } else {
                $this->warn("⚠️  Test user not found");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Authentication test failed: " . $e->getMessage());
        }
    }

    protected function verifyDataIsolation($tenant)
    {
        $this->info("\n6️⃣ Verifying DATA ISOLATION:");
        
        try {
            // Count records in tenant database
            $tenantAirlines = Airline::count();
            $tenantCustomers = Customer::count();
            $tenantBookings = Booking::count();
            
            $this->info("📊 Tenant Database ({$tenant->database}):");
            $this->info("   Airlines: {$tenantAirlines}");
            $this->info("   Customers: {$tenantCustomers}");
            $this->info("   Bookings: {$tenantBookings}");
            
            // Count records in central database
            $centralAirlines = DB::connection('mysql')->table('airlines')->count();
            $centralCustomers = DB::connection('mysql')->table('customers')->count();
            $centralBookings = DB::connection('mysql')->table('bookings')->count();
            
            $this->info("\n📊 Central Database (mysql):");
            $this->info("   Airlines: {$centralAirlines}");
            $this->info("   Customers: {$centralCustomers}");
            $this->info("   Bookings: {$centralBookings}");
            
            // Verify isolation
            if ($tenantAirlines != $centralAirlines || 
                $tenantCustomers != $centralCustomers || 
                $tenantBookings != $centralBookings) {
                $this->info("\n✅ DATA ISOLATION WORKING - Different counts confirm separate databases");
            } else {
                $this->warn("\n⚠️  DATA ISOLATION QUESTIONABLE - Same counts might indicate shared data");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Data isolation verification failed: " . $e->getMessage());
        }
    }
}
