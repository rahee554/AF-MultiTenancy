# ArtFlow Studio Tenancy Package v0.7.0.2

## Enhanced Testing Commands Documentation

### Overview

The ArtFlow Studio Tenancy package v0.7.0.2 introduces a comprehensive suite of testing commands designed to ensure your multi-tenant Laravel application is running optimally. These commands provide detailed insights into performance, isolation, and connection health.

## Available Testing Commands

### 1. Enhanced Performance Testing
```bash
php artisan tenancy:test-performance-enhanced
```

**Purpose**: Comprehensive performance testing with intelligent resource management and progress tracking.

**Features**:
- **Resource Limits**: Prevents system overload by limiting concurrent operations
- **Progress Tracking**: Real-time progress bars showing test completion
- **Batch Processing**: Intelligent batching for large tenant sets
- **Comprehensive Metrics**: Response times, throughput, error rates
- **Smart Tenant Selection**: Automatically selects appropriate tenant subset for deep tests

**Options**:
- `--max-tenants=10`: Maximum number of tenants to test (default: 10)
- `--max-users=20`: Maximum concurrent users per test (default: 20)
- `--operations=10`: Operations per user (default: 10)
- `--skip-stress`: Skip stress testing phase
- `--detailed`: Show detailed test results

**Example**:
```bash
# Quick performance test
php artisan tenancy:test-performance-enhanced --max-tenants=5 --max-users=10

# Comprehensive test with details
php artisan tenancy:test-performance-enhanced --detailed

# Light test without stress testing
php artisan tenancy:test-performance-enhanced --skip-stress --max-tenants=3
```

**Sample Output**:
```
🚀 Enhanced Tenant Performance Test Suite

Testing performance across 5 tenants
Max concurrent users: 20, Operations per user: 10

🏃 Test 1: Basic CRUD Performance
 5/5 [████████████████████████████] 100% 0:02/0:02 12MB - Testing tenant5...
✅ Average response time: 23.4ms

🔥 Test 2: Concurrent User Simulation  
 20/20 [███████████████████████████] 100% 0:15/0:15 18MB - User operations...
✅ Throughput: 850 operations/minute

📊 Final Results:
┌─────────────────────┬──────────┬─────────────┐
│ Test                │ Result   │ Performance │
├─────────────────────┼──────────┼─────────────┤
│ Basic CRUD          │ ✅ PASSED │ 23.4ms avg  │
│ Concurrent Users    │ ✅ PASSED │ 850 ops/min │
│ Data Isolation      │ ✅ PASSED │ 100% clean  │
│ Connection Pool     │ ✅ PASSED │ 15.2ms avg  │
└─────────────────────┴──────────┴─────────────┘

🎉 All performance tests PASSED!
```

### 2. Tenant Isolation Testing
```bash
php artisan tenancy:test-isolation
```

**Purpose**: Ensures complete data isolation between tenants with comprehensive validation.

**Features**:
- **Data Isolation**: Verifies tenants can only access their own data
- **Schema Isolation**: Confirms database schema separation
- **User Data Cross-Contamination**: Tests for data leaks between tenant users
- **Connection State Isolation**: Validates session and connection separation

**Options**:
- `--tenants=5`: Number of tenants to test (max 10)
- `--operations=20`: Operations per tenant (max 100)
- `--detailed`: Show detailed test results

**Example**:
```bash
# Standard isolation test
php artisan tenancy:test-isolation --tenants=5 --operations=10

# Comprehensive detailed test
php artisan tenancy:test-isolation --detailed --tenants=8 --operations=50
```

**Sample Output**:
```
🔒 Tenant Isolation Test Suite

Testing isolation across 5 tenants with 20 operations each

📊 Test 1: Basic Data Isolation
  ✅ Data isolation: PASSED

🗃️  Test 2: Schema Isolation  
  ✅ Schema isolation: PASSED

👥 Test 3: User Data Cross-Contamination
  ✅ User data isolation: PASSED

🔌 Test 4: Connection State Isolation
  ✅ Connection isolation: PASSED

📋 Final Isolation Test Results
┌──────────────────────┬───────────┬─────────┐
│ Test                 │ Result    │ Details │
├──────────────────────┼───────────┼─────────┤
│ Data Isolation       │ ✅ PASSED │ 15 checks │
│ Schema Isolation     │ ✅ PASSED │ 8 checks  │
│ User Isolation       │ ✅ PASSED │ 25 checks │
│ Connection Isolation │ ✅ PASSED │ 6 checks  │
└──────────────────────┴───────────┴─────────┘

🎉 All isolation tests PASSED! Your tenant isolation is working correctly.
```

### 3. Connection Testing
```bash
php artisan tenancy:test-connections
```

**Purpose**: Validates database connections for all tenants with performance analysis.

**Features**:
- **Connection Health**: Tests all tenant database connections
- **Response Time Analysis**: Measures and analyzes connection performance
- **Retry Logic**: Configurable retry attempts for failed connections
- **Performance Rating**: Automatic performance classification
- **Detailed Connection Info**: MySQL version, charset, table counts

**Options**:
- `--timeout=30`: Connection timeout in seconds
- `--retry=3`: Number of retry attempts
- `--detailed`: Show detailed connection information

**Example**:
```bash
# Quick connection test
php artisan tenancy:test-connections

# Detailed test with custom timeout
php artisan tenancy:test-connections --timeout=60 --detailed --retry=5
```

**Sample Output**:
```
🔌 Tenant Connection Test Suite

Testing connections for 12 tenants
Timeout: 30s, Retries: 3

 12/12 [████████████████████████████] 100% 0:08/0:08 15MB - Testing tenant12...

📊 Connection Test Summary
┌─────────────────────────┬───────┐
│ Metric                  │ Value │
├─────────────────────────┼───────┤
│ Total Tenants          │ 12    │
│ Successful Connections │ 12    │
│ Failed Connections     │ 0     │
│ Success Rate           │ 100%  │
└─────────────────────────┴───────┘

✅ Successful Connections:
┌──────────┬────────────────┬──────────────┬──────────┐
│ Tenant   │ Database       │ Response Time│ Attempts │
├──────────┼────────────────┼──────────────┼──────────┤
│ tenant1  │ tenant_tenant1 │ 12.4ms       │ 1/3      │
│ tenant2  │ tenant_tenant2 │ 15.8ms       │ 1/3      │
│ tenant3  │ tenant_tenant3 │ 11.2ms       │ 1/3      │
└──────────┴────────────────┴──────────────┴──────────┘

⚡ Performance Analysis:
┌─────────────────────────┬──────────────────────┐
│ Metric                  │ Value                │
├─────────────────────────┼──────────────────────┤
│ Average Response Time   │ 13.1ms               │
│ Fastest Connection      │ 11.2ms               │
│ Slowest Connection      │ 15.8ms               │
│ Performance Rating      │ 🚀 Excellent (< 10ms)│
└─────────────────────────┴──────────────────────┘

💡 Recommendations:
  ✅ All connections are working perfectly!
  • Continue monitoring connection health regularly
  • Consider setting up automated health checks
```

## Command Recommendations by Use Case

### For Daily Development
```bash
# Quick health check
php artisan tenancy:validate

# Test connections for all tenants
php artisan tenancy:test-connections
```

### For Performance Monitoring
```bash
# Comprehensive performance test
php artisan tenancy:test-performance-enhanced --detailed

# Light performance check
php artisan tenancy:test-performance-enhanced --max-tenants=3 --skip-stress
```

### For Security Validation
```bash
# Complete isolation testing
php artisan tenancy:test-isolation --detailed

# Quick isolation check
php artisan tenancy:test-isolation --tenants=3 --operations=10
```

### For Troubleshooting
```bash
# Fix any database issues first
php artisan tenancy:fix-databases

# Validate the fix
php artisan tenancy:validate

# Test specific aspects
php artisan tenancy:test-connections --detailed
php artisan tenancy:test-isolation --tenants=2
```

## Performance Benchmarks

### Expected Response Times
- **Excellent**: < 10ms average response time
- **Good**: < 50ms average response time  
- **Fair**: < 100ms average response time
- **Slow**: < 500ms average response time
- **Very Slow**: > 500ms average response time

### Recommended Test Frequency
- **Connection Tests**: Daily in production, after each deployment
- **Performance Tests**: Weekly in production, after major changes
- **Isolation Tests**: After code changes affecting tenant data, monthly in production

## Troubleshooting Guide

### Common Issues and Solutions

#### Test Hanging or Taking Too Long
```bash
# Use resource-limited testing
php artisan tenancy:test-performance-enhanced --max-tenants=3 --max-users=5

# Skip stress tests
php artisan tenancy:test-performance-enhanced --skip-stress
```

#### Connection Failures
```bash
# Fix databases first
php artisan tenancy:fix-databases

# Test with retries
php artisan tenancy:test-connections --retry=5 --timeout=60
```

#### Performance Issues
```bash
# Test with detailed output to identify bottlenecks
php artisan tenancy:test-performance-enhanced --detailed --max-tenants=5

# Check connection performance specifically
php artisan tenancy:test-connections --detailed
```

#### Isolation Problems
```bash
# Detailed isolation testing
php artisan tenancy:test-isolation --detailed --tenants=2 --operations=5

# Validate system integrity
php artisan tenancy:validate
```

## Best Practices

### 1. Testing Strategy
- **Start Small**: Begin with limited tenants and operations
- **Incremental Testing**: Gradually increase load to find limits  
- **Regular Monitoring**: Schedule automated testing
- **Environment Parity**: Test in production-like environments

### 2. Performance Optimization
- **Connection Pooling**: Use database connection pooling
- **Caching**: Implement appropriate caching strategies
- **Database Optimization**: Regular index and query optimization
- **Resource Limits**: Set appropriate limits for concurrent operations

### 3. Monitoring and Maintenance
- **Regular Health Checks**: Daily connection and validation tests
- **Performance Baselines**: Establish and monitor performance baselines
- **Alerting**: Set up alerts for performance degradation
- **Documentation**: Keep test results for historical analysis

## Integration with CI/CD

### GitHub Actions Example
```yaml
name: Tenancy Tests
on: [push, pull_request]
jobs:
  tenancy-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.1
      - name: Install Dependencies
        run: composer install
      - name: Test Tenant Connections
        run: php artisan tenancy:test-connections
      - name: Test Tenant Isolation  
        run: php artisan tenancy:test-isolation --tenants=3
      - name: Performance Test
        run: php artisan tenancy:test-performance-enhanced --max-tenants=3 --skip-stress
```

This comprehensive testing suite ensures your multi-tenant application maintains high performance, security, and reliability standards.
