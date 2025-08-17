# 🔌 REST API Reference

**ArtFlow Studio Tenancy Package v0.7.2.4 - Complete API Documentation**

Compatible with: Laravel 10+ & 11+, stancl/tenancy v3.9.1+, Livewire 3+

---

## 🔐 Authentication

### API Key Authentication (Required)

All API endpoints require authentication via API key parameter:

```bash
# Using API key parameter (recommended)
curl -X GET "https://your-app.com/api/tenancy/tenants?api_key=your_api_key_here" \
  -H "Content-Type: application/json"

# POST requests with API key in body
curl -X POST "https://your-app.com/api/tenancy/tenants" \
  -H "Content-Type: application/json" \
  -d '{
    "api_key": "your_api_key_here",
    "name": "New Tenant",
    "domain": "newtenant.example.com"
  }'
```

### Environment Configuration
```env
# Required: Set your API key in artflow-tenancy config
TENANT_API_KEY=your_secure_api_key_here

# Security settings
API_RATE_LIMIT_ENABLED=true
API_RATE_LIMIT_ATTEMPTS=60
API_RATE_LIMIT_DECAY=1
```

### Security Features
- ✅ **Middleware-enforced authentication** - All routes protected by TenantApiController
- ✅ **Parameter-based auth** - API key via query parameter or request body
- ✅ **Standardized errors** - Consistent 401 responses for invalid keys
- ✅ **Production enforcement** - API key always required

---

## 🏢 Tenant Management API

### Base URL
```
https://your-app.com/tenancy/
```

---

## 📋 Tenant Management Endpoints

### 📄 List All Tenants
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
- `api_key` (string) - Your API key (required)

**Example Request:**
```bash
curl -X GET "https://your-app.com/tenancy/tenants?api_key=your_api_key&page=1&per_page=20&status=active&sort=created_at&order=desc" \
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
        "homepage": "https://acme.example.com",
        "is_homepage_active": true,
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
```

### 👁️ Get Single Tenant
```http
GET /tenancy/tenants/{id}
```

**Parameters:**
- `id` (string) - Tenant UUID or ID
- `api_key` (string) - Your API key (required)

**Example Request:**
```bash
curl -X GET "https://your-app.com/tenancy/tenants/550e8400-e29b-41d4-a716-446655440000?api_key=your_api_key" \
  -H "Content-Type: application/json"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "name": "ACME Corporation",
    "database_name": "tenant_550e8400_db123",
    "status": "active",
    "homepage": "https://acme.example.com",
    "is_homepage_active": true,
    "tenant_config": {
      "features": ["billing", "analytics"],
      "limits": {
        "users": 100,
        "storage_gb": 50
      }
    },
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
  },
  "timestamp": "2024-08-01T15:30:00Z"
}
```
### ➕ Create New Tenant
```http
POST /tenancy/tenants/create
```

**Request Body:**
```json
{
  "api_key": "your_api_key",
  "name": "New Company Ltd",
  "domain": "newcompany.example.com",
  "status": "active",
  "homepage": "https://newcompany.example.com",
  "is_homepage_active": true,
  "tenant_config": {
    "features": ["billing", "analytics"],
    "limits": {
      "users": 100,
      "storage_gb": 50
    }
  },
  "run_migrations": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 2,
    "uuid": "new-uuid-here",
    "name": "New Company Ltd",
    "database_name": "tenant_new_uuid_db456",
    "status": "active",
    "homepage": "https://newcompany.example.com",
    "is_homepage_active": true,
    "tenant_config": {
      "features": ["billing", "analytics"],
      "limits": {
        "users": 100,
        "storage_gb": 50
      }
    },
    "created_at": "2024-08-01T15:30:00Z",
    "updated_at": "2024-08-01T15:30:00Z",
    "domains": [
      {
        "id": 2,
        "domain": "newcompany.example.com",
        "tenant_id": "new-uuid-here",
        "created_at": "2024-08-01T15:30:00Z"
      }
    ]
  },
  "message": "Tenant created successfully",
  "timestamp": "2024-08-01T15:30:00Z"
}
```

### ✏️ Update Tenant
```http
PUT /tenancy/tenants/{id}
```

**Request Body:**
```json
{
  "api_key": "your_api_key",
  "name": "Updated Company Name",
  "status": "active",
  "homepage": "https://updated.example.com",
  "is_homepage_active": false,
  "tenant_config": {
    "features": ["billing", "analytics", "reporting"],
    "limits": {
      "users": 200,
      "storage_gb": 100
    }
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Updated Company Name",
    "database_name": "tenant_550e8400_db123",
    "status": "active",
    "homepage": "https://updated.example.com",
    "is_homepage_active": false,
    "tenant_config": {
      "features": ["billing", "analytics", "reporting"],
      "limits": {
        "users": 200,
        "storage_gb": 100
      }
    },
    "created_at": "2024-08-01T10:00:00.000000Z",
    "updated_at": "2024-08-01T16:00:00.000000Z"
  },
  "message": "Tenant updated successfully",
  "timestamp": "2024-08-01T16:00:00Z"
}
```

### ❌ Delete Tenant
```http
DELETE /tenancy/tenants/{id}
```

**Parameters:**
- `id` (string) - Tenant UUID or ID
- `api_key` (string) - Your API key (required)

**Example Request:**
```bash
curl -X DELETE "https://your-app.com/tenancy/tenants/550e8400-e29b-41d4-a716-446655440000?api_key=your_api_key" \
  -H "Content-Type: application/json"
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

### 📋 Get Tenant Domains
```http
GET /tenancy/tenants/{tenant_id}/domains
```

**Parameters:**
- `tenant_id` (string) - Tenant UUID or ID
- `api_key` (string) - Your API key (required)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "domain": "acme.example.com",
      "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
      "created_at": "2024-08-01T10:00:00.000000Z",
      "updated_at": "2024-08-01T10:00:00.000000Z"
    },
    {
      "id": 2,
      "domain": "acme-alt.example.com",
      "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
      "created_at": "2024-08-01T11:00:00.000000Z",
      "updated_at": "2024-08-01T11:00:00.000000Z"
    }
  ],
  "timestamp": "2024-08-01T15:30:00Z"
}
```

### ➕ Add Domain to Tenant
```http
POST /tenancy/tenants/{tenant_id}/domains/create
```

**Request Body:**
```json
{
  "api_key": "your_api_key",
  "domain": "newdomain.example.com"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 3,
    "domain": "newdomain.example.com",
    "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
    "created_at": "2024-08-01T16:00:00.000000Z",
    "updated_at": "2024-08-01T16:00:00.000000Z"
  },
  "message": "Domain added successfully",
  "timestamp": "2024-08-01T16:00:00Z"
}
```

### ❌ Remove Domain from Tenant
```http
DELETE /tenancy/tenants/{tenant_id}/domains/{domain_id}
```

**Parameters:**
- `tenant_id` (string) - Tenant UUID or ID
- `domain_id` (string) - Domain ID
- `api_key` (string) - Your API key (required)

**Response:**
```json
{
  "success": true,
  "message": "Domain removed successfully",
  "timestamp": "2024-08-01T16:00:00Z"
}
```

---

## 📊 Real-Time Monitoring & Analytics API

### 📈 System Statistics
```http
GET /tenancy/monitoring/system-stats
```

**Query Parameters:**
- `api_key` (string) - Your API key (required)
- `cache_ttl` (integer) - Cache time in seconds (default: 30)

**Example Request:**
```bash
curl -X GET "https://your-app.com/tenancy/monitoring/system-stats?api_key=your_api_key&cache_ttl=60" \
  -H "Content-Type: application/json"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "timestamp": "2024-08-01T15:30:00Z",
    "system": {
      "php_version": "8.2.12",
      "laravel_version": "11.x",
      "memory_usage": {
        "current_mb": 124.5,
        "peak_mb": 145.2,
        "limit": "512M",
        "percentage": 24.3
      },
      "uptime": "15 days, 8 hours",
      "load_average": [0.85, 0.72, 0.68]
    },
    "database": {
      "version": "8.0.33",
      "uptime": "1,296,000 seconds",
      "total_queries": 1247890,
      "slow_queries": 12,
      "connection_count": 23,
      "database_size_mb": 2048.5
    },
    "tenants": {
      "total_tenants": 145,
      "active_tenants": 142,
      "suspended_tenants": 2,
      "blocked_tenants": 1,
      "recently_accessed_1h": 67,
      "recently_accessed_24h": 128
    },
    "performance": {
      "average_response_time_ms": 95.3,
      "requests_per_minute": 1247,
      "cache_hit_ratio": 94.2,
      "memory_usage_trend": "stable"
    },
    "connections": {
      "total_connections": 23,
      "active_connections": 18,
      "sleeping_connections": 5,
      "max_connections": 151
    }
  },
  "cache_info": {
    "cached_at": "2024-08-01T15:29:30Z",
    "expires_at": "2024-08-01T15:30:30Z",
    "cache_hit": true
  }
}
```

### 🏢 Individual Tenant Statistics
```http
GET /tenancy/monitoring/tenant-stats/{tenant_id}
```

**Parameters:**
- `tenant_id` (string) - Tenant UUID or ID
- `api_key` (string) - Your API key (required)
- `period` (string) - Time period: `1h`, `24h`, `7d`, `30d` (default: `24h`)

**Example Request:**
```bash
curl -X GET "https://your-app.com/tenancy/monitoring/tenant-stats/550e8400-e29b-41d4-a716-446655440000?api_key=your_api_key&period=24h" \
  -H "Content-Type: application/json"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
    "tenant_name": "ACME Corporation",
    "status": "active",
    "period": "24h",
    "timestamp": "2024-08-01T15:30:00Z",
    "database_stats": {
      "database_name": "tenant_550e8400_db123",
      "size_mb": 45.7,
      "table_count": 24,
      "record_count": 15678,
      "queries_executed": 3456,
      "slow_queries": 2,
      "average_query_time_ms": 23.5
    },
    "memory_usage": {
      "current_mb": 8.2,
      "peak_mb": 12.1,
      "average_mb": 9.7,
      "percentage_of_limit": 8.2
    },
    "activity_stats": {
      "page_views": 1234,
      "unique_visitors": 89,
      "api_calls": 567,
      "last_accessed": "2024-08-01T15:25:00Z",
      "active_sessions": 5
    },
    "performance": {
      "average_response_time_ms": 145.3,
      "cache_hit_ratio": 92.1,
      "error_rate": 0.02
    },
    "resource_limits": {
      "max_users": 100,
      "current_users": 47,
      "max_storage_gb": 50,
      "current_storage_gb": 2.3
    }
  }
}
```

### 📊 All Tenants Analytics Overview
```http
GET /tenancy/monitoring/tenant-stats
```

**Query Parameters:**
- `api_key` (string) - Your API key (required)
- `page` (integer) - Page number (default: 1)
- `per_page` (integer) - Results per page (default: 15, max: 100)
- `sort` (string) - Sort by: `memory_usage`, `database_size`, `activity`, `name` (default: `memory_usage`)
- `order` (string) - Sort order: `asc`, `desc` (default: `desc`)
- `status` (string) - Filter by status: `active`, `suspended`, `blocked`

**Response:**
```json
{
  "success": true,
  "data": {
    "summary": {
      "total_tenants": 145,
      "total_memory_mb": 1247.8,
      "total_database_size_gb": 23.4,
      "average_response_time_ms": 125.3,
      "total_queries_24h": 45678
    },
    "tenants": [
      {
        "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
        "tenant_name": "ACME Corporation",
        "status": "active",
        "memory_usage_mb": 8.2,
        "database_size_mb": 45.7,
        "queries_24h": 3456,
        "last_accessed": "2024-08-01T15:25:00Z",
        "response_time_ms": 145.3,
        "active_users": 47,
        "page_views_24h": 1234
      },
      {
        "tenant_id": "another-uuid-here",
        "tenant_name": "Beta Corp",
        "status": "active",
        "memory_usage_mb": 12.1,
        "database_size_mb": 67.3,
        "queries_24h": 5432,
        "last_accessed": "2024-08-01T15:20:00Z",
        "response_time_ms": 98.7,
        "active_users": 23,
        "page_views_24h": 891
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 145,
      "last_page": 10
    }
  },
  "timestamp": "2024-08-01T15:30:00Z"
}
```

### 📈 Memory Usage Graphs (Admin Analytics)
```http
GET /tenancy/monitoring/memory-usage
```

**Query Parameters:**
- `api_key` (string) - Your API key (required)
- `tenant_id` (string) - Specific tenant UUID (optional, if omitted returns all)
- `period` (string) - Time period: `1h`, `6h`, `24h`, `7d`, `30d` (default: `24h`)
- `interval` (string) - Data interval: `5m`, `15m`, `1h`, `6h`, `1d` (default: `1h`)

**Example Request (All Tenants):**
```bash
curl -X GET "https://your-app.com/tenancy/monitoring/memory-usage?api_key=your_api_key&period=24h&interval=1h" \
  -H "Content-Type: application/json"
```

**Response (Graph Data):**
```json
{
  "success": true,
  "data": {
    "period": "24h",
    "interval": "1h",
    "timestamps": [
      "2024-07-31T15:00:00Z",
      "2024-07-31T16:00:00Z",
      "2024-07-31T17:00:00Z"
    ],
    "total_system_memory": {
      "data": [245.7, 267.3, 289.1],
      "unit": "MB",
      "peak": 328.9,
      "average": 267.4
    },
    "tenant_breakdown": [
      {
        "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
        "tenant_name": "ACME Corporation",
        "memory_data": [8.2, 9.1, 7.8],
        "peak": 12.1,
        "average": 8.4
      },
      {
        "tenant_id": "another-uuid-here",
        "tenant_name": "Beta Corp",
        "memory_data": [12.1, 13.5, 11.9],
        "peak": 15.2,
        "average": 12.5
      }
    ],
    "metadata": {
      "total_tenants": 145,
      "active_tenants": 142,
      "generation_time_ms": 234
    }
  },
  "timestamp": "2024-08-01T15:30:00Z"
}
```

### 🔄 Database Connections Monitor
```http
GET /tenancy/monitoring/connections
```

**Query Parameters:**
- `api_key` (string) - Your API key (required)
- `include_details` (boolean) - Include connection details (default: false)

**Response:**
```json
{
  "success": true,
  "data": {
    "summary": {
      "total_connections": 23,
      "active_connections": 18,
      "sleeping_connections": 5,
      "max_connections": 151,
      "threads_connected": 23,
      "threads_running": 5,
      "connection_utilization_percent": 15.2
    },
    "by_tenant": [
      {
        "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
        "tenant_name": "ACME Corporation",
        "database_name": "tenant_550e8400_db123",
        "active_connections": 3,
        "queries_running": 1,
        "last_query_time": "2024-08-01T15:29:45Z"
      }
    ],
    "connection_details": [
      {
        "id": 1234,
        "user": "tenant_550e8400",
        "host": "localhost:3306",
        "database": "tenant_550e8400_db123",
        "command": "Query",
        "time_seconds": 0.234,
        "state": "executing",
        "info": "SELECT * FROM users WHERE active = 1"
      }
    ]
  },
  "timestamp": "2024-08-01T15:30:00Z"
}
```

### 📊 Admin Dashboard Overview (Complete Data)
```http
GET /tenancy/monitoring/dashboard
```

**Query Parameters:**
- `api_key` (string) - Your API key (required)
- `refresh` (boolean) - Force refresh cache (default: false)
- `period` (string) - Data period: `1h`, `24h`, `7d`, `30d` (default: `24h`)

**Example Request:**
```bash
curl -X GET "https://your-app.com/tenancy/monitoring/dashboard?api_key=your_api_key&refresh=true&period=24h" \
  -H "Content-Type: application/json"
```

**Response (Complete Admin Analytics):**
```json
{
  "success": true,
  "data": {
    "overview": {
      "total_tenants": 145,
      "active_tenants": 142,
      "suspended_tenants": 2,
      "blocked_tenants": 1,
      "total_databases": 145,
      "total_connections": 23,
      "period": "24h"
    },
    "system_health": {
      "status": "healthy",
      "uptime": "15 days, 8 hours",
      "memory_usage": {
        "current_mb": 245.7,
        "peak_mb": 328.9,
        "percentage": 24.3,
        "limit": "1024M"
      },
      "cpu_usage": {
        "current_percent": 15.8,
        "average_percent": 12.4,
        "peak_percent": 34.2
      },
      "disk_usage": {
        "total_gb": 500,
        "used_gb": 156.7,
        "available_gb": 343.3,
        "percentage": 31.3
      }
    },
    "tenant_analytics": {
      "memory_distribution": [
        {
          "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
          "tenant_name": "ACME Corporation",
          "memory_mb": 8.2,
          "percentage": 3.3
        },
        {
          "tenant_id": "another-uuid-here",
          "tenant_name": "Beta Corp",
          "memory_mb": 12.1,
          "percentage": 4.9
        }
      ],
      "database_sizes": [
        {
          "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
          "tenant_name": "ACME Corporation",
          "size_mb": 45.7,
          "tables": 24,
          "records": 15678
        }
      ],
      "activity_summary": {
        "total_page_views_24h": 45678,
        "total_api_calls_24h": 12345,
        "unique_visitors_24h": 3456,
        "peak_concurrent_users": 89,
        "most_active_tenant": {
          "id": "550e8400-e29b-41d4-a716-446655440000",
          "name": "ACME Corporation",
          "activity_score": 95.7
        }
      }
    },
    "performance_metrics": {
      "queries_per_second": 1247.5,
      "slow_queries_24h": 45,
      "cache_hit_ratio": 94.2,
      "average_response_time_ms": 125.3,
      "error_rate_percent": 0.02,
      "database_connections": {
        "active": 18,
        "idle": 5,
        "max": 151,
        "utilization_percent": 15.2
      }
    },
    "recent_activity": {
      "new_tenants_24h": [
        {
          "id": "new-uuid-1",
          "name": "New Company Ltd",
          "status": "active",
          "created_at": "2024-08-01T14:00:00Z",
          "initial_setup_complete": true
        }
      ],
      "recently_accessed": [
        {
          "id": "550e8400-e29b-41d4-a716-446655440000",
          "name": "ACME Corporation",
          "last_accessed": "2024-08-01T15:25:00Z",
          "active_users": 47
        }
      ],
      "alerts": [
        {
          "level": "warning",
          "message": "Tenant 'xyz-corp' approaching memory limit",
          "tenant_id": "warning-uuid",
          "timestamp": "2024-08-01T15:20:00Z"
        }
      ]
    },
    "resource_usage_trends": {
      "memory_trend": "stable",
      "cpu_trend": "increasing",
      "disk_trend": "stable",
      "connection_trend": "stable",
      "tenant_growth_rate": "+2.3% this month"
    }
  },
  "metadata": {
    "generated_at": "2024-08-01T15:30:00Z",
    "cache_hit": false,
    "generation_time_ms": 567,
    "next_refresh": "2024-08-01T15:35:00Z"
  }
}
```

---

## 🔧 Tenant Operations & Management API

### 🔄 Update Tenant Status
```http
PUT /tenancy/tenants/{tenant_id}/status
```

**Parameters:**
- `tenant_id` (string) - Tenant UUID or ID
- `api_key` (string) - Your API key (required)

**Request Body:**
```json
{
  "api_key": "your_api_key",
  "status": "blocked"
}
```

**Available Status Values:**
- `active` - Tenant is fully operational
- `inactive` - Tenant is temporarily disabled
- `suspended` - Tenant access is suspended
- `blocked` - Tenant is permanently blocked

**Response:**
```json
{
  "success": true,
  "data": {
    "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
    "previous_status": "active",
    "new_status": "blocked",
    "updated_at": "2024-08-01T16:00:00.000000Z"
  },
  "message": "Tenant status updated successfully",
  "timestamp": "2024-08-01T16:00:00Z"
}
```

### 🚫 Block Tenant
```http
POST /tenancy/tenants/{tenant_id}/block
```

**Parameters:**
- `tenant_id` (string) - Tenant UUID or ID
- `api_key` (string) - Your API key (required)

**Request Body:**
```json
{
  "api_key": "your_api_key",
  "reason": "Policy violation"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "blocked",
    "blocked_at": "2024-08-01T16:00:00.000000Z",
    "reason": "Policy violation"
  },
  "message": "Tenant blocked successfully",
  "timestamp": "2024-08-01T16:00:00Z"
}
```

### 🔄 Migrate Tenant Database
```http
POST /tenancy/tenants/{tenant_id}/migrate
```

**Parameters:**
- `tenant_id` (string) - Tenant UUID or ID
- `api_key` (string) - Your API key (required)

**Request Body:**
```json
{
  "api_key": "your_api_key",
  "fresh": false,
  "seed": false,
  "force": false
}
```

**Parameters:**
- `fresh` (boolean) - Drop all tables before migrating (default: false)
- `seed` (boolean) - Run seeders after migration (default: false)
- `force` (boolean) - Force migration in production (default: false)

**Response:**
```json
{
  "success": true,
  "data": {
    "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
    "migrations_run": 15,
    "seeders_run": 3,
    "database_name": "tenant_550e8400_db123",
    "completed_at": "2024-08-01T16:05:00.000000Z",
    "execution_time_seconds": 12.4
  },
  "message": "Database migration completed successfully",
  "timestamp": "2024-08-01T16:05:00Z"
}
```

### 🏠 Enable Tenant Homepage
```http
POST /tenancy/tenants/{tenant_id}/enable-homepage
```

**Parameters:**
- `tenant_id` (string) - Tenant UUID or ID
- `api_key` (string) - Your API key (required)

**Request Body:**
```json
{
  "api_key": "your_api_key",
  "homepage_url": "https://tenant.example.com"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
    "tenant_name": "ACME Corporation",
    "homepage": "https://tenant.example.com",
    "is_homepage_active": true,
    "enabled_at": "2024-08-01T16:00:00.000000Z"
  },
  "message": "Homepage enabled successfully for tenant",
  "timestamp": "2024-08-01T16:00:00Z"
}
```

### 🚫 Disable Tenant Homepage
```http
POST /tenancy/tenants/{tenant_id}/disable-homepage
```

**Parameters:**
- `tenant_id` (string) - Tenant UUID or ID
- `api_key` (string) - Your API key (required)

**Request Body:**
```json
{
  "api_key": "your_api_key"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
    "tenant_name": "ACME Corporation",
    "homepage": null,
    "is_homepage_active": false,
    "disabled_at": "2024-08-01T16:00:00.000000Z"
  },
  "message": "Homepage disabled successfully for tenant",
  "timestamp": "2024-08-01T16:00:00Z"
}
```

### 🌱 Seed Tenant Database
```http
POST /tenancy/tenants/{tenant_id}/seed
```

**Parameters:**
- `tenant_id` (string) - Tenant UUID or ID
- `api_key` (string) - Your API key (required)

**Request Body:**
```json
{
  "api_key": "your_api_key",
  "seeder_class": "DatabaseSeeder",
  "force": false
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
    "seeder_class": "DatabaseSeeder",
    "records_created": 1250,
    "execution_time_seconds": 3.7,
    "completed_at": "2024-08-01T16:02:00.000000Z"
  },
  "message": "Database seeding completed successfully",
  "timestamp": "2024-08-01T16:02:00Z"
}
```

### 🔄 Reset Tenant (Full Reset)
```http
POST /tenancy/tenants/{tenant_id}/reset
```

**Parameters:**
- `tenant_id` (string) - Tenant UUID or ID
- `api_key` (string) - Your API key (required)

**Request Body:**
```json
{
  "api_key": "your_api_key",
  "confirm_reset": true,
  "backup_before_reset": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
    "backup_created": true,
    "backup_file": "tenant_550e8400_backup_20240801_160300.sql",
    "reset_completed_at": "2024-08-01T16:03:00.000000Z",
    "new_status": "active"
  },
  "message": "Tenant reset completed successfully",
  "timestamp": "2024-08-01T16:03:00Z"
}
```

---

## 📈 System Health & Monitoring API

### 🔍 Health Check
```http
GET /tenancy/health
```

**Query Parameters:**
- `api_key` (string) - Your API key (required)
- `detailed` (boolean) - Include detailed check results (default: false)

**Response:**
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "overall_score": 98.5,
    "checks": {
      "database": {
        "status": "ok",
        "response_time_ms": 12.3,
        "message": "Database connection successful"
      },
      "tenants": {
        "status": "ok",
        "accessible_count": 142,
        "total_count": 145,
        "message": "142/145 tenant databases accessible"
      },
      "cache": {
        "status": "ok",
        "hit_ratio": 94.2,
        "message": "Cache system operational"
      },
      "storage": {
        "status": "ok",
        "free_space_gb": 343.3,
        "message": "Storage system operational"
      },
      "memory": {
        "status": "ok",
        "usage_percent": 24.3,
        "message": "Memory usage within normal range"
      }
    },
    "metrics": {
      "total_tenants": 145,
      "active_tenants": 142,
      "response_time_ms": 45.2,
      "memory_usage_mb": 245.7,
      "uptime_hours": 368
    }
  },
  "timestamp": "2024-08-01T15:30:00Z"
}
```

### ⚡ Performance Metrics
```http
GET /tenancy/performance
```

**Query Parameters:**
- `api_key` (string) - Your API key (required)
- `period` (string) - Time period: `1h`, `24h`, `7d` (default: `24h`)

**Response:**
```json
{
  "success": true,
  "data": {
    "period": "24h",
    "metrics": {
      "response_times": {
        "average_ms": 125.3,
        "median_ms": 98.7,
        "p95_ms": 245.6,
        "p99_ms": 456.2
      },
      "throughput": {
        "requests_per_second": 1247.5,
        "api_calls_per_minute": 890.2,
        "page_views_per_hour": 45678
      },
      "errors": {
        "error_rate_percent": 0.02,
        "total_errors": 23,
        "5xx_errors": 5,
        "4xx_errors": 18
      },
      "database": {
        "queries_per_second": 2345.6,
        "slow_queries_count": 45,
        "connection_pool_usage": 15.2
      }
    }
  },
  "timestamp": "2024-08-01T15:30:00Z"
}
```

### 🖥️ System Information
```http
GET /tenancy/system-info
```

**Query Parameters:**
- `api_key` (string) - Your API key (required)

**Response:**
```json
{
  "success": true,
  "data": {
    "environment": {
      "app_env": "production",
      "debug_mode": false,
      "php_version": "8.2.12",
      "laravel_version": "11.x",
      "package_version": "0.7.2.4"
    },
    "server": {
      "os": "Linux",
      "web_server": "nginx/1.22.1",
      "database": "MySQL 8.0.33",
      "cache_driver": "redis",
      "queue_driver": "database"
    },
    "resources": {
      "cpu_cores": 8,
      "total_memory_gb": 16,
      "total_disk_gb": 500,
      "max_execution_time": 30,
      "memory_limit": "1024M"
    },
    "configuration": {
      "tenancy_enabled": true,
      "total_tenants": 145,
      "max_tenants": 1000,
      "homepage_feature": true,
      "api_enabled": true
    }
  },
  "timestamp": "2024-08-01T15:30:00Z"
}
```

---

## 📊 Analytics & Reporting API

### 📈 Analytics Overview
```http
GET /tenancy/analytics/overview
```

**Query Parameters:**
- `api_key` (string) - Your API key (required)
- `period` (string) - Time period: `24h`, `7d`, `30d`, `90d` (default: `30d`)
- `timezone` (string) - Timezone for data (default: UTC)

**Response:**
```json
{
  "success": true,
  "data": {
    "period": "30d",
    "summary": {
      "total_tenants": 145,
      "new_tenants": 12,
      "growth_rate": "+8.9%",
      "churn_rate": "2.1%",
      "active_rate": "97.9%"
    },
    "usage_statistics": {
      "total_page_views": 1234567,
      "total_api_calls": 456789,
      "unique_visitors": 23456,
      "average_session_duration": "8m 34s"
    },
    "resource_consumption": {
      "total_memory_usage_gb": 24.7,
      "total_database_size_gb": 123.4,
      "total_storage_used_gb": 456.7,
      "backup_storage_gb": 78.9
    },
    "top_tenants": [
      {
        "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
        "tenant_name": "ACME Corporation",
        "activity_score": 95.7,
        "memory_usage_mb": 45.2,
        "page_views": 12345
      }
    ]
  },
  "timestamp": "2024-08-01T15:30:00Z"
}
```

### 📊 Usage Analytics (Detailed)
```http
GET /tenancy/analytics/usage
```

**Query Parameters:**
- `api_key` (string) - Your API key (required)
- `tenant_id` (string) - Specific tenant UUID (optional)
- `metric` (string) - Specific metric: `memory`, `database`, `api_calls`, `page_views`
- `granularity` (string) - Data granularity: `hour`, `day`, `week` (default: `day`)
- `period` (string) - Time period: `7d`, `30d`, `90d` (default: `30d`)

**Response:**
```json
{
  "success": true,
  "data": {
    "metric": "memory",
    "granularity": "day",
    "period": "30d",
    "data_points": [
      {
        "date": "2024-07-02",
        "total_usage_mb": 234.5,
        "tenant_breakdown": [
          {
            "tenant_id": "550e8400-e29b-41d4-a716-446655440000",
            "tenant_name": "ACME Corporation",
            "usage_mb": 8.2
          }
        ]
      }
    ],
    "trends": {
      "direction": "increasing",
      "change_percent": "+12.3%",
      "prediction_next_30d": 278.9
    }
  },
  "timestamp": "2024-08-01T15:30:00Z"
}
```
---

## ❌ Error Responses

### Standard Error Format
All API endpoints return consistent error responses:

```json
{
  "success": false,
  "error": {
    "code": 401,
    "type": "authentication_error",
    "message": "Invalid API key provided",
    "details": "The API key is either missing, invalid, or expired"
  },
  "timestamp": "2024-08-01T15:30:00Z"
}
```

### Common Error Codes

| Code | Type | Description |
|------|------|-------------|
| `401` | `authentication_error` | Invalid or missing API key |
| `403` | `authorization_error` | Insufficient permissions |
| `404` | `not_found` | Tenant or resource not found |
| `422` | `validation_error` | Invalid request parameters |
| `429` | `rate_limit_error` | Too many requests |
| `500` | `server_error` | Internal server error |

### Validation Error Example
```json
{
  "success": false,
  "error": {
    "code": 422,
    "type": "validation_error",
    "message": "The given data was invalid",
    "details": {
      "name": ["The name field is required"],
      "domain": ["The domain has already been taken"]
    }
  },
  "timestamp": "2024-08-01T15:30:00Z"
}
```

### Rate Limit Error Example
```json
{
  "success": false,
  "error": {
    "code": 429,
    "type": "rate_limit_error",
    "message": "Too many requests",
    "details": "Rate limit exceeded. Try again in 60 seconds.",
    "retry_after": 60
  },
  "timestamp": "2024-08-01T15:30:00Z"
}
```

---

## � SDKs & Integration

### JavaScript/TypeScript SDK Example
```typescript
import { TenancyAPI } from '@artflow-studio/tenancy-sdk';

const api = new TenancyAPI({
  baseUrl: 'https://your-app.com',
  apiKey: 'your_api_key_here'
});

// List all tenants
const tenants = await api.tenants.list({
  page: 1,
  per_page: 20,
  status: 'active'
});

// Get system statistics
const stats = await api.monitoring.systemStats({
  cache_ttl: 60
});

// Get memory usage graph data
const memoryData = await api.monitoring.memoryUsage({
  period: '24h',
  interval: '1h'
});
```

### PHP SDK Example
```php
use ArtFlowStudio\Tenancy\SDK\TenancyAPI;

$api = new TenancyAPI([
    'base_url' => 'https://your-app.com',
    'api_key' => 'your_api_key_here'
]);

// Create new tenant
$tenant = $api->tenants()->create([
    'name' => 'New Company Ltd',
    'domain' => 'newcompany.example.com',
    'status' => 'active'
]);

// Get admin dashboard data
$dashboard = $api->monitoring()->dashboard([
    'period' => '24h',
    'refresh' => true
]);
```

### cURL Examples Collection
```bash
# Get all tenants with complete data
curl -X GET "https://your-app.com/tenancy/tenants?api_key=your_api_key&per_page=100" \
  -H "Content-Type: application/json"

# Get memory usage graphs for admin dashboard
curl -X GET "https://your-app.com/tenancy/monitoring/memory-usage?api_key=your_api_key&period=24h&interval=1h" \
  -H "Content-Type: application/json"

# Get complete admin dashboard overview
curl -X GET "https://your-app.com/tenancy/monitoring/dashboard?api_key=your_api_key&refresh=true&period=24h" \
  -H "Content-Type: application/json"

# Create new tenant with full configuration
curl -X POST "https://your-app.com/tenancy/tenants/create" \
  -H "Content-Type: application/json" \
  -d '{
    "api_key": "your_api_key",
    "name": "Enterprise Corp",
    "domain": "enterprise.example.com",
    "status": "active",
    "homepage": "https://enterprise.example.com",
    "is_homepage_active": true,
    "tenant_config": {
      "features": ["billing", "analytics", "reporting"],
      "limits": {
        "users": 500,
        "storage_gb": 100
      }
    },
    "run_migrations": true
  }'
```

---

## 📝 API Changelog

### v0.7.2.4 (Latest)
- ✅ **Added**: Complete admin analytics dashboard endpoint
- ✅ **Added**: Memory usage graph data for tenant monitoring
- ✅ **Added**: Individual tenant statistics with detailed metrics
- ✅ **Added**: System health monitoring with comprehensive checks
- ✅ **Enhanced**: Authentication via API key parameter
- ✅ **Enhanced**: All endpoints now return detailed tenant configuration
- ✅ **Enhanced**: Homepage management for tenants
- ✅ **Enhanced**: Real-time connection monitoring

### v0.7.2.3
- ✅ **Added**: Tenant operations API (block, migrate, seed, reset)
- ✅ **Added**: Domain management endpoints
- ✅ **Enhanced**: Error handling and validation
- ✅ **Enhanced**: Performance monitoring

### v0.7.2.2
- ✅ **Added**: Basic tenant CRUD operations
- ✅ **Added**: System statistics monitoring
- ✅ **Added**: Authentication middleware

---

## 🎯 Next Version (v0.7.3.0) Preview

### Planned Features
- 🚀 **WebSocket Real-time Updates**: Live dashboard updates
- 🚀 **Advanced Analytics**: Predictive analytics and forecasting
- 🚀 **Automated Alerts**: Smart threshold-based notifications
- 🚀 **Backup Management**: Automated backup scheduling and restoration
- 🚀 **Performance Optimization**: Enhanced caching and query optimization
- 🚀 **Multi-language Support**: Internationalization for API responses

---

*📖 For more information, visit the [ArtFlow Studio Tenancy Package Documentation](https://github.com/artflow-studio/tenancy)*

*📧 Support: support@artflow-studio.com*

*🌐 Website: https://artflow-studio.com*
