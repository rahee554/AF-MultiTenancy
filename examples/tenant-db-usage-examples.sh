#!/bin/bash

# =============================================================================
# Tenant Database Management Examples
# =============================================================================
# This script demonstrates common usage patterns for the new tenant:db command
# Usage: chmod +x tenant-db-examples.sh && ./tenant-db-examples.sh
# =============================================================================

echo "🗄️  Tenant Database Management Examples"
echo "======================================="
echo ""

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# =============================================================================
# Example 1: Interactive Database Operations
# =============================================================================
echo -e "${BLUE}📋 Example 1: Interactive Database Operations${NC}"
echo "The easiest way to get started - just run the command and it guides you:"
echo ""
echo -e "${GREEN}php artisan tenant:db${NC}"
echo "→ Shows all available operations"
echo "→ Lists all tenants to choose from"
echo "→ Guides you through the process"
echo ""
echo "Press Enter to continue to next example..."
read

# =============================================================================
# Example 2: Single Tenant Operations
# =============================================================================
echo -e "${BLUE}🎯 Example 2: Single Tenant Operations${NC}"
echo "Working with a specific tenant:"
echo ""
echo "# Run migrations for specific tenant"
echo -e "${GREEN}php artisan tenant:db migrate --tenant=550e8400-e29b-41d4${NC}"
echo ""
echo "# Run fresh migration with seeding"
echo -e "${GREEN}php artisan tenant:db migrate:fresh --seed --tenant=my-tenant-name${NC}"
echo ""
echo "# Run specific seeder class"
echo -e "${GREEN}php artisan tenant:db seed --class=UserSeeder --tenant=550e8400-e29b-41d4${NC}"
echo ""
echo "# Check migration status"
echo -e "${GREEN}php artisan tenant:db migrate:status --tenant=550e8400-e29b-41d4${NC}"
echo ""
echo "# Rollback 3 migration steps"
echo -e "${GREEN}php artisan tenant:db migrate:rollback --step=3 --tenant=550e8400-e29b-41d4${NC}"
echo ""
echo "Press Enter to continue..."
read

# =============================================================================
# Example 3: Bulk Operations
# =============================================================================
echo -e "${BLUE}🚀 Example 3: Bulk Operations (All Tenants)${NC}"
echo "Operating on multiple tenants at once:"
echo ""
echo "# Migrate all active tenants"
echo -e "${GREEN}php artisan tenant:db migrate --all${NC}"
echo ""
echo "# Seed all tenants with specific seeder"
echo -e "${GREEN}php artisan tenant:db seed --all --class=UpdateSeeder${NC}"
echo ""
echo "# Fresh migrate all tenants (with confirmation)"
echo -e "${YELLOW}php artisan tenant:db migrate:fresh --all${NC}"
echo -e "${RED}⚠️  This is destructive - will ask for confirmation${NC}"
echo ""
echo "# Force operation without confirmation (dangerous!)"
echo -e "${RED}php artisan tenant:db migrate:fresh --all --force${NC}"
echo -e "${RED}⚠️  Use --force carefully!${NC}"
echo ""
echo "Press Enter to continue..."
read

# =============================================================================
# Example 4: Safety and Testing
# =============================================================================
echo -e "${BLUE}🛡️  Example 4: Safety and Testing Features${NC}"
echo "Safe ways to test and preview changes:"
echo ""
echo "# Dry run - see what would be migrated"
echo -e "${GREEN}php artisan tenant:db migrate --pretend --tenant=550e8400-e29b-41d4${NC}"
echo "→ Shows migrations that would run without executing them"
echo ""
echo "# Check migration status before making changes"
echo -e "${GREEN}php artisan tenant:db migrate:status --tenant=550e8400-e29b-41d4${NC}"
echo ""
echo "# Test on single tenant before bulk operations"
echo -e "${GREEN}php artisan tenant:db migrate --tenant=test-tenant${NC}"
echo -e "${GREEN}# If successful, then run: php artisan tenant:db migrate --all${NC}"
echo ""
echo "Press Enter to continue..."
read

# =============================================================================
# Example 5: Development Workflow
# =============================================================================
echo -e "${BLUE}💻 Example 5: Development Workflow${NC}"
echo "Common patterns for development:"
echo ""
echo "# 1. Create new tenant for testing"
echo -e "${GREEN}php artisan tenant:manage create --name=\"Dev Tenant\" --domain=dev.local${NC}"
echo ""
echo "# 2. Set up database for development"
echo -e "${GREEN}TENANT_UUID=550e8400-e29b-41d4  # Use actual UUID from step 1${NC}"
echo -e "${GREEN}php artisan tenant:db migrate --tenant=\$TENANT_UUID${NC}"
echo -e "${GREEN}php artisan tenant:db seed --class=DevelopmentSeeder --tenant=\$TENANT_UUID${NC}"
echo ""
echo "# 3. Reset database during development (quick iteration)"
echo -e "${GREEN}php artisan tenant:db fresh-seed --tenant=\$TENANT_UUID --force${NC}"
echo ""
echo "# 4. Test new migrations"
echo -e "${GREEN}php artisan tenant:db migrate --pretend --tenant=\$TENANT_UUID${NC}"
echo -e "${GREEN}php artisan tenant:db migrate --tenant=\$TENANT_UUID${NC}"
echo ""
echo "Press Enter to continue..."
read

# =============================================================================
# Example 6: Production Deployment
# =============================================================================
echo -e "${BLUE}🚀 Example 6: Production Deployment${NC}"
echo "Safe production deployment process:"
echo ""
echo "# 1. Check current migration status for all tenants"
echo -e "${GREEN}php artisan tenant:db migrate:status --all${NC}"
echo ""
echo "# 2. Test migration on single tenant first"
echo -e "${GREEN}php artisan tenant:db migrate --pretend --tenant=test-tenant-uuid${NC}"
echo -e "${GREEN}php artisan tenant:db migrate --tenant=test-tenant-uuid${NC}"
echo ""
echo "# 3. If successful, migrate all tenants"
echo -e "${GREEN}php artisan tenant:db migrate --all${NC}"
echo ""
echo "# 4. Verify all tenants migrated successfully"
echo -e "${GREEN}php artisan tenant:db migrate:status --all${NC}"
echo ""
echo "Press Enter to continue..."
read

# =============================================================================
# Example 7: Troubleshooting and Recovery
# =============================================================================
echo -e "${BLUE}🔧 Example 7: Troubleshooting and Recovery${NC}"
echo "Fixing problems with tenant databases:"
echo ""
echo "# Check what went wrong with a tenant"
echo -e "${GREEN}php artisan tenant:db migrate:status --tenant=problem-tenant-uuid${NC}"
echo ""
echo "# Rollback problematic migration"
echo -e "${GREEN}php artisan tenant:db migrate:rollback --step=1 --tenant=problem-tenant-uuid${NC}"
echo ""
echo "# Reset tenant database completely and start over"
echo -e "${YELLOW}php artisan tenant:db reset --tenant=problem-tenant-uuid${NC}"
echo -e "${GREEN}php artisan tenant:db migrate --tenant=problem-tenant-uuid${NC}"
echo -e "${GREEN}php artisan tenant:db seed --tenant=problem-tenant-uuid${NC}"
echo ""
echo "# Check which tenants need attention"
echo -e "${GREEN}php artisan tenant:manage health${NC}"
echo ""
echo "Press Enter to continue..."
read

# =============================================================================
# Example 8: Advanced Filtering and Scripting
# =============================================================================
echo -e "${BLUE}⚙️  Example 8: Advanced Filtering and Scripting${NC}"
echo "Advanced usage patterns:"
echo ""
echo "# Work with tenants by status"
echo -e "${GREEN}php artisan tenant:db migrate --all --status=inactive${NC}"
echo -e "${GREEN}php artisan tenant:db seed --all --status=pending_setup${NC}"
echo ""
echo "# Script for automated tenant setup"
echo -e "${GREEN}#!/bin/bash${NC}"
echo -e "${GREEN}TENANT_UUID=\$1${NC}"
echo -e "${GREEN}echo \"Setting up tenant: \$TENANT_UUID\"${NC}"
echo -e "${GREEN}php artisan tenant:db migrate --tenant=\$TENANT_UUID --force${NC}"
echo -e "${GREEN}php artisan tenant:db seed --class=InitialDataSeeder --tenant=\$TENANT_UUID --force${NC}"
echo -e "${GREEN}echo \"Setup complete!\"${NC}"
echo ""
echo "Press Enter to continue..."
read

# =============================================================================
# Example 9: Migration from Old Commands
# =============================================================================
echo -e "${BLUE}🔄 Example 9: Migration from Old Commands${NC}"
echo "How to migrate from tenant:manage to tenant:db:"
echo ""
echo -e "${RED}OLD WAY (still works but not recommended):${NC}"
echo -e "${YELLOW}php artisan tenant:manage migrate --tenant=uuid-123${NC}"
echo -e "${YELLOW}php artisan tenant:manage seed --tenant=uuid-123${NC}"
echo ""
echo -e "${GREEN}NEW WAY (recommended):${NC}"
echo -e "${GREEN}php artisan tenant:db migrate --tenant=uuid-123${NC}"
echo -e "${GREEN}php artisan tenant:db seed --tenant=uuid-123${NC}"
echo ""
echo "Benefits of new command:"
echo "✅ Better tenant selection (interactive, search by name)"
echo "✅ More operations (rollback, status, fresh, etc.)"
echo "✅ Safety features (pretend mode, confirmations)"
echo "✅ Bulk operations with filtering"
echo "✅ Better error handling and reporting"
echo ""
echo "Press Enter to continue..."
read

# =============================================================================
# Summary
# =============================================================================
echo -e "${BLUE}📚 Summary - Key Takeaways${NC}"
echo "=========================="
echo ""
echo -e "${GREEN}✅ Use tenant:db for all database operations${NC}"
echo -e "${GREEN}✅ Start with interactive mode when learning${NC}"
echo -e "${GREEN}✅ Always test with --pretend first${NC}"
echo -e "${GREEN}✅ Use single tenant testing before --all operations${NC}"
echo -e "${GREEN}✅ Take advantage of smart tenant selection${NC}"
echo ""
echo -e "${YELLOW}⚠️  Remember:${NC}"
echo "• migrate:fresh, reset, refresh are DESTRUCTIVE"
echo "• Use --force carefully in production"  
echo "• Always backup before destructive operations"
echo "• Test on development tenants first"
echo ""
echo -e "${BLUE}📖 For complete documentation:${NC}"
echo "• docs/TENANT_DATABASE_COMMAND.md - Full documentation"
echo "• docs/TENANT_DB_QUICK_REFERENCE.md - Quick reference"
echo "• docs/COMMANDS.md - All available commands"
echo ""
echo "🎉 Happy tenant database management!"
