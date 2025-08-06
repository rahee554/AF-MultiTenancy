# 🔌 REST API Reference

**ArtFlow Studio Tenancy Package v2.0 - Complete API Documentation**

Compatible with: Laravel 10+ & 11+, stancl/tenancy v3+, Livewire 3+

---

## 🔐 Authentication

### API Key Authentication (Required)

All API endpoints require authentication via the `X-API-Key` header:

```bash
# Using curl with API key
curl -X GET "https://your-app.com/tenancy/tenants" \
  -H "X-API-Key: sk_tenant_live_your_secure_api_key_here" \
  -H "Content-Type: application/json"

# Using Bearer Token (alternative)
curl -X GET "https://your-app.com/tenancy/tenants" \
  -H "Authorization: Bearer your_bearer_token" \
  -H "Content-Type: application/json"
```

### Environment Configuration
```env
# Required: Set your API key
TENANT_API_KEY=sk_tenant_live_your_secure_api_key_here

# Optional: Bearer token support
TENANT_BEARER_TOKEN=your_bearer_token_here

# Security settings
API_RATE_LIMIT_ENABLED=true
API_RATE_LIMIT_ATTEMPTS=60
API_RATE_LIMIT_DECAY=1
```

### Security Features
- ✅ **Middleware-enforced authentication** - All routes protected
- ✅ **Rate limiting** - Built-in throttling via `throttle:api`
- ✅ **Development mode** - Localhost bypass for testing
- ✅ **Production enforcement** - API key always required in production
- ✅ **Standardized errors** - Consistent 401/403 responses

---

## 🏢 Tenant Management API

### Base URL
```
https://your-app.com/tenancy/
```

### Authentication Headers (Required for all endpoints)
```http
X-API-Key: sk_tenant_live_your_secure_api_key_here
Content-Type: application/json
```

---

## 📋 Tenant Endpoints

### List All Tenants
```http
GET /tenancy/tenants
```

**Query Parameters:**
- `page` (integer) - Page number (default: 1)
- `per_page` (integer) - Results per page (default: 15, max: 100)
- `search` (string) - Search by tenant name or domain
- `status` (string) - Filter by status: `active`, `suspended`, `blocked`, `inactive`
- `sort` (string) - Sort by field: `name`, `created_at`, `status`, `database_name`
- `order` (string) - Sort order: `asc`, `desc` (default: `asc`)

**Example Request:**
```bash
curl -X GET "https://your-app.com/tenancy/tenants?page=1&per_page=20&status=active&sort=created_at&order=desc" \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "uuid": "550e8400-e29b-41d4-a716-446655440000",
        "name": "ACME Corporation",
        "database_name": "tenant_550e8400_db123",
        "status": "active",
        "created_at": "2024-08-01T10:00:00.000000Z",
        "updated_at": "2024-08-01T10:00:00.000000Z",
        "domains": [
          {
            "id": 1,
            "domain": "acme.example.com",
            "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
            "created_at": "2024-08-01T10:00:00.000000Z"
          }
        ]
      }
    ],
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1
  },
  "timestamp": "2024-08-01T15:30:00Z"
}
- `order` - Sort order: `asc`, `desc`

**Example Request:**
```bash
curl -H "X-API-Key: your_key" \
     "https://your-app.com/tenancy/tenants?status=active&sort=name&order=asc"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "uuid-here",
        "name": "Acme Corporation",
        "status": "active",
        "primary_domain": "acme.your-app.com",
        "last_accessed_at": "2024-08-01T10:30:00Z",
        "created_at": "2024-07-15T09:20:00Z",
        "updated_at": "2024-08-01T10:30:00Z",
        "domains": [
          {
            "id": 1,
            "domain": "acme.your-app.com",
            "created_at": "2024-07-15T09:20:00Z"
          }
        ]
      }
    ],
    "total": 25,
    "per_page": 15,
    "current_page": 1,
    "last_page": 2
  },
  "timestamp": "2024-08-01T15:30:00Z"
}
```

### Create New Tenant
```http
POST /tenancy/tenants/create
```

**Request Body:**
```json
{
  "name": "New Company Ltd",
  "domain": "newcompany.your-app.com",
  "status": "active",
  "run_migrations": true,
  "settings": {
    "timezone": "UTC",
    "currency": "USD"
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "new-uuid-here",
    "name": "New Company Ltd",
    "status": "active",
    "primary_domain": "newcompany.your-app.com",
    "created_at": "2024-08-01T15:30:00Z",
    "database_name": "tenant_new_uuid",
    "migrations_run": true
  },
  "message": "Tenant created successfully",
  "timestamp": "2024-08-01T15:30:00Z"
}
```

### Get Single Tenant
```http
GET /tenancy/tenants/{tenant_id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid-here",
    "name": "Acme Corporation",
    "status": "active",
    "primary_domain": "acme.your-app.com",
    "settings": {
      "timezone": "UTC",
      "currency": "USD"
    },
    "statistics": {
      "database_size_mb": 15.3,
      "tables_count": 12,
      "last_activity": "2024-08-01T10:30:00Z"
    },
    "domains": [
      {
        "id": 1,
        "domain": "acme.your-app.com",
        "created_at": "2024-07-15T09:20:00Z"
      }
    ],
    "created_at": "2024-07-15T09:20:00Z",
    "updated_at": "2024-08-01T10:30:00Z"
  },
  "timestamp": "2024-08-01T15:30:00Z"
}
```

### Update Tenant
```http
PUT /tenancy/tenants/{tenant_id}
```

**Request Body:**
```json
{
  "name": "Updated Company Name",
  "status": "active",
  "settings": {
    "timezone": "America/New_York",
    "currency": "USD"
  }
}
```

### Delete Tenant
```http
DELETE /tenancy/tenants/{tenant_id}
```

**Response:**
```json
{
  "success": true,
  "message": "Tenant deleted successfully",
  "timestamp": "2024-08-01T15:30:00Z"
}
```

---

## 🌐 Domain Management API

### Get Tenant Domains
```http
GET /tenancy/tenants/{tenant_id}/domains
```

### Add Domain to Tenant
```http
POST /tenancy/tenants/{tenant_id}/domains/create
```

**Request Body:**
```json
{
  "domain": "newdomain.your-app.com"
}
```

### Remove Domain from Tenant
```http
DELETE /tenancy/tenants/{tenant_id}/domains/{domain_id}
```

---

## 📊 Real-Time Monitoring API

### System Statistics
```http
GET /admin/monitoring/system-stats
```

**Query Parameters:**
- `cache_ttl` - Cache time in seconds (default: 30)

**Response:**
```json
{
  "success": true,
  "data": {
    "timestamp": "2024-08-01T15:30:00Z",
    "system": {
      "php_version": "8.2.0",
      "laravel_version": "11.x",
      "memory_usage": {
        "current_mb": 124.5,
        "peak_mb": 145.2,
        "limit": "512M"
      },
      "uptime": "15 days"
    },
    "database": {
      "version": "8.0.33",
      "uptime": "1,296,000",
      "queries": "1,247,890",
      "slow_queries": "12"
    },
    "tenants": {
      "total_tenants": 145,
      "active_tenants": 142,
      "blocked_tenants": 3,
      "recently_accessed": 67
    },
    "performance": {
      "queries_per_second": 1247.5,
      "slow_queries": 12,
      "cache_hit_ratio": 94.2
    },
    "connections": {
      "total_connections": 23,
      "active_connections": 18,
      "sleeping_connections": 5
    }
  }
}
```

### Tenant Statistics
```http
GET /admin/monitoring/tenant-stats
GET /admin/monitoring/tenant-stats/{tenant_id}
```

**Response (All Tenants):**
```json
{
  "success": true,
  "data": [
    {
      "tenant_id": "uuid-1",
      "tenant_name": "Acme Corp",
      "status": "active",
      "primary_domain": "acme.your-app.com",
      "database_name": "tenant_uuid_1",
      "database_stats": {
        "tables_count": 12,
        "total_size_mb": 15.3
      },
      "last_accessed_at": "2024-08-01T10:30:00Z"
    }
  ],
  "meta": {
    "total_tenants": 145,
    "generated_at": "2024-08-01T15:30:00Z"
  }
}
```

### Database Connections
```http
GET /admin/monitoring/connections
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_connections": 23,
    "active_connections": 18,
    "sleeping_connections": 5,
    "max_connections": 151,
    "threads_connected": 23,
    "threads_running": 5,
    "connection_details": [
      {
        "id": 1234,
        "user": "tenant_user",
        "host": "localhost:3306",
        "db": "tenant_uuid_1",
        "command": "Query",
        "time": 0,
        "state": "executing"
      }
    ]
  }
}
```

### Dashboard Overview
```http
GET /admin/monitoring/dashboard
```

**Query Parameters:**
- `refresh` - Force refresh cache (boolean)

**Response:**
```json
{
  "success": true,
  "data": {
    "summary": {
      "total_tenants": 145,
      "active_tenants": 142,
      "blocked_tenants": 3,
      "total_databases": 145,
      "total_connections": 23
    },
    "recent_activity": {
      "recent_tenants": [
        {
          "id": "uuid-1",
          "name": "Latest Company",
          "status": "active",
          "created_at": "2024-08-01T14:00:00Z"
        }
      ],
      "recently_accessed": [
        {
          "id": "uuid-2",
          "name": "Active Company",
          "last_accessed_at": "2024-08-01T15:25:00Z"
        }
      ]
    },
    "performance": {
      "queries_per_second": 1247.5,
      "cache_hit_ratio": 94.2,
      "avg_response_time": "45ms"
    },
    "system": {
      "memory_usage": {
        "current_mb": 124.5,
        "peak_mb": 145.2
      },
      "php_version": "8.2.0",
      "laravel_version": "11.x"
    }
  },
  "meta": {
    "generated_at": "2024-08-01T15:30:00Z",
    "cache_used": true
  }
}
```

---

## 🔧 Tenant Operations API

### Update Tenant Status
```http
PUT /tenancy/tenants/{tenant_id}/status
```

**Request Body:**
```json
{
  "status": "blocked"  // active, inactive, blocked
}
```

### Block Tenant
```http
POST /tenancy/tenants/{tenant_id}/block
```

### Migrate Tenant Database
```http
POST /tenancy/tenants/{tenant_id}/migrate
```

**Request Body:**
```json
{
  "fresh": false,  // Drop all tables before migrating
  "seed": false    // Run seeders after migration
}
```

### Enable Tenant Homepage
```http
POST /tenancy/tenants/{tenant_id}/enable-homepage
```

**Description:** Enables the homepage for a tenant, allowing them to see their custom homepage at the root URL.

**Response:**
```json
{
  "success": true,
  "message": "Homepage enabled successfully for tenant",
  "data": {
    "tenant_id": "uuid-here",
    "has_homepage": true,
    "tenant_name": "Example Tenant"
  },
  "timestamp": "2024-01-15T10:30:00.000000Z"
}
```

### Disable Tenant Homepage
```http
POST /tenancy/tenants/{tenant_id}/disable-homepage
```

**Description:** Disables the homepage for a tenant, redirecting them to the login page instead.

**Response:**
```json
{
  "success": true,
  "message": "Homepage disabled successfully for tenant",
  "data": {
    "tenant_id": "uuid-here",
    "has_homepage": false,
    "tenant_name": "Example Tenant"
  },
  "timestamp": "2024-01-15T10:30:00.000000Z"
}
```

### Seed Tenant Database
```http
POST /tenancy/tenants/{tenant_id}/seed
```

**Request Body:**
```json
{
  "class": "DatabaseSeeder"  // Optional: specific seeder class
}
```

### Reset Tenant
```http
POST /tenancy/tenants/{tenant_id}/reset
```

---

## 📈 System Health API

### Health Check
```http
GET /tenancy/health
```

**Response:**
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "checks": {
      "database": {
        "status": "ok",
        "message": "Database connection successful"
      },
      "tenants": {
        "status": "ok", 
        "message": "All tenant databases accessible"
      },
      "cache": {
        "status": "ok",
        "message": "Cache system operational"
      },
      "storage": {
        "status": "ok",
        "message": "Storage system operational"
      }
    },
    "metrics": {
      "total_tenants": 145,
      "active_tenants": 142,
      "response_time": "45ms",
      "memory_usage": "124.5MB"
    }
  },
  "timestamp": "2024-08-01T15:30:00Z"
}
```

### Performance Metrics
```http
GET /tenancy/performance
```

### System Information
```http
GET /tenancy/system-info
```

---

## 📊 Analytics & Reports API

### Analytics Overview
```http
GET /tenancy/analytics/overview
```

### Usage Analytics
```http
GET /tenancy/analytics/usage
```

### Performance Analytics
```http
GET /tenancy/analytics/performance
```

### Growth Analytics
```http
GET /tenancy/analytics/growth
```

---

## 🔄 Bulk Operations API

### Bulk Status Update
```http
PUT /tenancy/bulk-status-update
```

**Request Body:**
```json
{
  "tenant_ids": ["uuid-1", "uuid-2", "uuid-3"],
  "status": "active"
}
```

### Migrate All Tenants
```http
POST /tenancy/migrate-all-tenants
```

### Seed All Tenants
```http
POST /tenancy/seed-all-tenants
```

---

## 💾 Backup & Restore API

### Backup Tenant
```http
POST /tenancy/tenants/{tenant_id}/backup
```

### Restore Tenant
```http
POST /tenancy/tenants/{tenant_id}/restore
```

### Export Tenant
```http
POST /tenancy/tenants/{tenant_id}/export
```

### Import Tenant
```http
POST /tenancy/import-tenant
```

---

## 🧹 Cache Management API

### Clear Tenant Cache
```http
DELETE /tenancy/clear-cache
```

### Clear All Caches
```http
DELETE /tenancy/clear-all-caches
```

### Clear Monitoring Caches
```http
DELETE /admin/monitoring/clear-caches
```

---

## 🔧 Maintenance API

### Enable Maintenance Mode
```http
POST /tenancy/maintenance/on
```

### Disable Maintenance Mode
```http
POST /tenancy/maintenance/off
```

---

## 📝 Error Responses

### Standard Error Format
```json
{
  "success": false,
  "error": "Validation failed",
  "message": "The given data was invalid.",
  "errors": {
    "domain": ["The domain has already been taken."],
    "name": ["The name field is required."]
  },
  "timestamp": "2024-08-01T15:30:00Z"
}
```

### HTTP Status Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `429` - Too Many Requests
- `500` - Internal Server Error

---

## 🚀 API Examples

### Create and Setup Tenant
```bash
# 1. Create tenant
curl -X POST -H "X-API-Key: your_key" \
     -H "Content-Type: application/json" \
     -d '{"name":"Demo Corp","domain":"demo.local","run_migrations":true}' \
     https://your-app.com/tenancy/tenants/create

# 2. Get tenant info
curl -H "X-API-Key: your_key" \
     https://your-app.com/tenancy/tenants/uuid-here

# 3. Update tenant status
curl -X PUT -H "X-API-Key: your_key" \
     -H "Content-Type: application/json" \
     -d '{"status":"active"}' \
     https://your-app.com/tenancy/tenants/uuid-here/status
```

### Monitor System
```bash
# Get system stats
curl -H "X-API-Key: your_key" \
     https://your-app.com/admin/monitoring/system-stats

# Get tenant performance
curl -H "X-API-Key: your_key" \
     https://your-app.com/admin/monitoring/tenant-stats

# Check system health
curl -H "X-API-Key: your_key" \
     https://your-app.com/tenancy/health
```

---

## 🔗 Integration Examples

### JavaScript/Node.js
```javascript
const axios = require('axios');

const tenancyAPI = axios.create({
  baseURL: 'https://your-app.com/tenancy/',
  headers: {
    'X-API-Key': 'your_api_key',
    'Content-Type': 'application/json'
  }
});

// Create tenant
const tenant = await tenancyAPI.post('/tenants/create', {
  name: 'New Company',
  domain: 'new.local'
});

// Get tenants
const tenants = await tenancyAPI.get('/tenants');
```

### PHP/Laravel
```php
use Illuminate\Support\Facades\Http;

$response = Http::withHeaders([
    'X-API-Key' => config('tenancy.api_key'),
])->post('https://your-app.com/tenancy/tenants/create', [
    'name' => 'New Company',
    'domain' => 'new.local',
    'run_migrations' => true
]);

$tenant = $response->json();
```

### Python
```python
import requests

headers = {
    'X-API-Key': 'your_api_key',
    'Content-Type': 'application/json'
}

# Create tenant
response = requests.post(
    'https://your-app.com/tenancy/tenants/create',
    json={'name': 'New Company', 'domain': 'new.local'},
    headers=headers
)

tenant = response.json()
```
