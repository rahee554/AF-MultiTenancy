# Performance Testing Commands Index

## 📂 Directory Structure

```
PerformanceTesting/
├── README.md                              # Complete documentation
├── MasterPerformanceTestCommand.php       # Master test suite runner
├── TenancyPerformanceTestCommand.php      # Comprehensive performance tests
├── DatabaseStressTestCommand.php          # Database stress testing
├── ConnectionPoolTestCommand.php          # Connection pool validation
└── CachePerformanceTestCommand.php        # Cache performance tests
```

## 🎯 Quick Start

### Run All Tests
```bash
php artisan tenancy:performance-test-all
```

### Quick Mode (Fast)
```bash
php artisan tenancy:performance-test-all --quick
```

### Full Mode (Comprehensive)
```bash
php artisan tenancy:performance-test-all --full --report
```

## 📋 Commands Summary

| Command | Purpose | Key Features |
|---------|---------|--------------|
| `tenancy:performance-test-all` | Run all tests | Master suite with reporting |
| `tenancy:performance-test` | General performance | Connection, queries, cache, memory |
| `tenancy:stress-test-database` | Load testing | High concurrency, duration-based |
| `tenancy:test-connection-pool` | Connection validation | Leak detection, cleanup testing |
| `tenancy:test-cache-performance` | Cache testing | Isolation, read/write performance |

## ⚡ Command Details

### 1. Master Performance Test Suite
**Command:** `php artisan tenancy:performance-test-all`

**Options:**
- `--quick` - Fast tests (5 tenants, 50 queries)
- `--full` - Comprehensive tests (20 tenants, 500 queries)
- `--report` - Generate HTML report

**What it does:**
- Runs all performance test commands
- Aggregates results
- Provides overall system rating
- Generates detailed reports

---

### 2. Comprehensive Performance Test
**Command:** `php artisan tenancy:performance-test`

**Options:**
- `--tenants=10` - Number of test tenants
- `--queries=100` - Queries per tenant
- `--concurrent=5` - Concurrent operations
- `--cleanup` - Clean up after test

**Tests Performed:**
- ✅ Database connection pool behavior
- ✅ Tenant switching speed (avg, min, max)
- ✅ Concurrent tenant access
- ✅ Query performance (simple, complex, joins)
- ✅ Cache operations (read, write, delete)
- ✅ Memory usage tracking
- ✅ Connection cleanup validation

---

### 3. Database Stress Test
**Command:** `php artisan tenancy:stress-test-database`

**Options:**
- `--tenants=5` - Tenants to test
- `--connections=50` - Concurrent connections
- `--duration=60` - Test duration (seconds)
- `--query-type=mixed` - Query complexity

**Tests Performed:**
- ✅ High concurrent connection handling
- ✅ Query execution under load
- ✅ Error rate monitoring
- ✅ Performance degradation detection
- ✅ Per-tenant metrics

---

### 4. Connection Pool Test
**Command:** `php artisan tenancy:test-connection-pool`

**Options:**
- `--tenants=10` - Number of tenants
- `--iterations=100` - Test iterations
- `--check-leaks` - Enable leak detection

**Tests Performed:**
- ✅ Connection pool size analysis
- ✅ Connection reuse efficiency
- ✅ Persistent connection detection
- ✅ Memory leak detection
- ✅ Cleanup after tenant switch
- ✅ Connection lifecycle validation

---

### 5. Cache Performance Test
**Command:** `php artisan tenancy:test-cache-performance`

**Options:**
- `--tenants=5` - Number of tenants
- `--operations=1000` - Operations per test
- `--key-size=small` - Value size (small/medium/large)

**Tests Performed:**
- ✅ Cache write performance
- ✅ Cache read performance & hit rate
- ✅ Cache miss handling
- ✅ Cache delete operations
- ✅ Tenant cache isolation
- ✅ Cache key prefix validation

---

## 📊 Test Output Features

### Visual Elements
- 🎨 Color-coded status (✅ ❌ ⚠️)
- 📈 Progress bars for long tests
- 📊 Summary tables
- ⭐ Performance ratings

### Metrics Provided
- Execution times (ms/seconds)
- Operations per second
- Success/failure rates
- Memory usage
- Connection statistics
- Error details

### Report Generation
- HTML reports with charts
- Executive summaries
- Detailed breakdowns
- Historical comparisons
- Recommendations

---

## 🎯 Use Cases

### Daily Health Check
```bash
php artisan tenancy:performance-test-all --quick
```

### Pre-Production Validation
```bash
php artisan tenancy:performance-test-all --full --report
```

### Debug Performance Issues
```bash
# Check connections
php artisan tenancy:test-connection-pool --check-leaks

# Check cache
php artisan tenancy:test-cache-performance --tenants=10

# Stress test
php artisan tenancy:stress-test-database --duration=300
```

### CI/CD Integration
```bash
# Run quick tests in pipeline
php artisan tenancy:performance-test-all --quick --no-interaction
```

---

## 🔧 Configuration Tips

### Optimize for Performance Testing

**database.php:**
```php
'options' => [
    PDO::ATTR_PERSISTENT => false, // CRITICAL for multi-tenancy
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false, // Reduce memory
]
```

**cache.php:**
```php
'prefix' => env('CACHE_PREFIX', 'tenant'),
```

### Recommended Test Environments

**Development:**
- Tenants: 5-10
- Queries: 50-100
- Duration: 30-60s

**Staging:**
- Tenants: 10-15
- Queries: 100-200
- Duration: 60-120s

**Production Validation:**
- Tenants: 20-50
- Queries: 500-1000
- Duration: 300-600s

---

## 📈 Performance Benchmarks

### Excellent Performance Targets

| Metric | Target |
|--------|--------|
| Tenant Switch | <5ms |
| Queries/Second | >1000 |
| Cache Writes/Second | >5000 |
| Cache Reads/Second | >10000 |
| Memory/Tenant | <2MB |
| Connection Leaks | 0 |
| Error Rate | 0% |

### Good Performance Targets

| Metric | Target |
|--------|--------|
| Tenant Switch | <10ms |
| Queries/Second | >500 |
| Cache Writes/Second | >1000 |
| Cache Reads/Second | >2000 |
| Memory/Tenant | <5MB |
| Connection Leaks | 0 |
| Error Rate | <1% |

---

## 🐛 Troubleshooting

### Common Issues & Solutions

**Issue: Persistent connection warning**
```
⚠️ Persistent connections detected
```
**Solution:**
```php
// config/database.php
PDO::ATTR_PERSISTENT => false
```

**Issue: Cache isolation failure**
```
❌ Cache isolation breach detected
```
**Solution:**
- Enable cache key prefixes
- Verify tenant context switching

**Issue: Connection leaks**
```
❌ Connection leaks: X leaked connections
```
**Solution:**
- Ensure `tenancy()->end()` is called
- Add `DB::purge('tenant')` after tenant context

**Issue: Slow performance**
```
⭐⭐ Slow rating
```
**Solution:**
- Review database indexes
- Check query optimization
- Monitor server resources

---

## 📚 Additional Resources

- **Full Documentation:** See README.md in this directory
- **Package Documentation:** Check main package README
- **Laravel Tenancy Docs:** Official Laravel multi-tenancy guides

---

## ✅ Testing Checklist

Before deploying to production:

- [ ] Run full performance test suite
- [ ] Check for connection leaks
- [ ] Verify cache isolation
- [ ] Stress test with expected load
- [ ] Generate and review HTML report
- [ ] Document any performance issues
- [ ] Optimize based on recommendations

---

**Version:** 1.0.0  
**Package:** artflow-studio/tenancy  
**Last Updated:** 2025-10-01
