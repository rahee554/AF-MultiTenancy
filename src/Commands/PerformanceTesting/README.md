# 🚀 Tenancy Performance Testing Suite

Comprehensive performance testing commands for the **artflow-studio/tenancy** package.

## 📋 Table of Contents

- [Overview](#overview)
- [Installation](#installation)
- [Available Commands](#available-commands)
- [Usage Examples](#usage-examples)
- [Test Results](#test-results)
- [Performance Benchmarks](#performance-benchmarks)

## Overview

This performance testing suite provides comprehensive tools to test, benchmark, and validate the multi-tenancy package under various conditions:

- **Performance Testing**: Measure tenant switching speed, query performance, and resource usage
- **Stress Testing**: Test system behavior under heavy load with concurrent tenants
- **Connection Pool Testing**: Validate database connection management and detect leaks
- **Cache Performance**: Test cache isolation and performance across tenants
- **Master Test Suite**: Run all tests with a single command

## Installation

These commands are included in the `artflow-studio/tenancy` package. No additional installation required.

## Available Commands

### 1. Master Performance Test Suite

Run all performance tests with a single command.

```bash
php artisan tenancy:performance-test-all
```

**Options:**
- `--quick`: Run quick tests with reduced iterations
- `--full`: Run comprehensive tests with maximum coverage
- `--report`: Generate detailed HTML report

**Examples:**
```bash
# Standard test suite
php artisan tenancy:performance-test-all

# Quick mode (faster, less comprehensive)
php artisan tenancy:performance-test-all --quick

# Full mode (comprehensive, takes longer)
php artisan tenancy:performance-test-all --full

# Generate HTML report
php artisan tenancy:performance-test-all --report
```

---

### 2. Comprehensive Performance Test

Test overall tenancy performance including connection pools, tenant switching, queries, cache, and memory usage.

```bash
php artisan tenancy:performance-test
```

**Options:**
- `--tenants=10`: Number of test tenants to create
- `--queries=100`: Number of queries per tenant
- `--concurrent=5`: Number of concurrent tenant switches
- `--cleanup`: Clean up test tenants after test

**What it tests:**
- ✅ Database connection pool behavior
- ✅ Tenant switching speed
- ✅ Concurrent tenant access
- ✅ Database query performance
- ✅ Cache performance
- ✅ Memory usage tracking
- ✅ Connection cleanup validation

**Example:**
```bash
php artisan tenancy:performance-test --tenants=20 --queries=200 --concurrent=10 --cleanup
```

---

### 3. Database Stress Test

Stress test the database with high concurrent connections and queries.

```bash
php artisan tenancy:stress-test-database
```

**Options:**
- `--tenants=5`: Number of tenants to stress test
- `--connections=50`: Number of concurrent connections per tenant
- `--duration=60`: Test duration in seconds
- `--query-type=mixed`: Query type (simple|complex|mixed)

**What it tests:**
- ✅ High concurrent connection handling
- ✅ Query performance under load
- ✅ Error rates and failure detection
- ✅ Performance degradation over time

**Example:**
```bash
php artisan tenancy:stress-test-database --tenants=10 --connections=100 --duration=120 --query-type=complex
```

---

### 4. Connection Pool Test

Test database connection pool behavior and detect connection leaks.

```bash
php artisan tenancy:test-connection-pool
```

**Options:**
- `--tenants=10`: Number of tenants
- `--iterations=100`: Number of iterations
- `--check-leaks`: Enable connection leak detection

**What it tests:**
- ✅ Connection pool size and behavior
- ✅ Connection reuse efficiency
- ✅ Persistent connection detection
- ✅ Connection leak detection
- ✅ Connection cleanup after tenant switch

**Example:**
```bash
php artisan tenancy:test-connection-pool --tenants=15 --iterations=200 --check-leaks
```

---

### 5. Cache Performance Test

Test cache performance and isolation across tenants.

```bash
php artisan tenancy:test-cache-performance
```

**Options:**
- `--tenants=5`: Number of tenants
- `--operations=1000`: Number of cache operations per test
- `--key-size=small`: Cache key size (small|medium|large)

**What it tests:**
- ✅ Cache write performance
- ✅ Cache read performance
- ✅ Cache miss handling
- ✅ Cache delete performance
- ✅ Cache isolation between tenants
- ✅ Cache key prefix validation

**Example:**
```bash
php artisan tenancy:test-cache-performance --tenants=10 --operations=2000 --key-size=large
```

---

## Usage Examples

### Quick Health Check

```bash
# Run a quick performance check
php artisan tenancy:performance-test-all --quick
```

### Full System Validation

```bash
# Run comprehensive tests with report generation
php artisan tenancy:performance-test-all --full --report
```

### Stress Test Production Load

```bash
# Simulate production load
php artisan tenancy:stress-test-database \
  --tenants=20 \
  --connections=200 \
  --duration=300 \
  --query-type=mixed
```

### Debug Connection Issues

```bash
# Check for connection leaks
php artisan tenancy:test-connection-pool \
  --tenants=20 \
  --iterations=500 \
  --check-leaks
```

### Validate Cache Setup

```bash
# Test cache isolation and performance
php artisan tenancy:test-cache-performance \
  --tenants=10 \
  --operations=5000 \
  --key-size=medium
```

---

## Test Results

All commands provide detailed, beautified results with:

### ✨ Visual Output
- Color-coded status indicators
- Progress bars for long-running tests
- Summary tables with key metrics
- Performance ratings (⭐⭐⭐⭐⭐)

### 📊 Metrics Tracked
- Execution time (ms/s)
- Queries per second
- Cache operations per second
- Memory usage
- Connection pool statistics
- Error rates
- Success rates

### 📈 Performance Ratings

**Overall Performance:**
- ⭐⭐⭐⭐⭐ Excellent (100% pass rate)
- ⭐⭐⭐⭐ Good (80%+ pass rate)
- ⭐⭐⭐ Fair (60%+ pass rate)
- ⭐⭐ Needs Improvement (<60% pass rate)

**Speed Rating:**
- ⭐⭐⭐⭐⭐ Very Fast (<5s per test)
- ⭐⭐⭐⭐ Fast (<10s per test)
- ⭐⭐⭐ Moderate (<20s per test)
- ⭐⭐ Slow (>20s per test)

---

## Performance Benchmarks

### Expected Performance (Standard Configuration)

| Metric | Target | Excellent |
|--------|--------|-----------|
| Tenant Switch Time | <10ms | <5ms |
| Queries/Second | >500 | >1000 |
| Cache Writes/Second | >1000 | >5000 |
| Cache Reads/Second | >2000 | >10000 |
| Memory per Tenant | <5MB | <2MB |
| Connection Leaks | 0 | 0 |

### Recommended Test Parameters

**Development:**
```bash
--tenants=5 --queries=50 --concurrent=3
```

**Staging:**
```bash
--tenants=10 --queries=100 --concurrent=5
```

**Production Validation:**
```bash
--tenants=20 --queries=500 --concurrent=10
```

---

## Troubleshooting

### Common Issues

**Issue: Connection Leaks Detected**
```
Solution: Set PDO::ATTR_PERSISTENT => false in config/database.php
```

**Issue: Cache Isolation Failures**
```
Solution: Enable cache key prefixes in tenancy configuration
```

**Issue: Slow Tenant Switching**
```
Solution: Check database connection pooling configuration
```

**Issue: High Memory Usage**
```
Solution: Review query buffering settings and result set sizes
```

---

## Report Generation

HTML reports are saved to `storage/logs/` with the following information:

- Executive summary with key metrics
- Detailed test results table
- Performance ratings and charts
- Recommendations for optimization
- Timestamp and system information

**View Report:**
```bash
# Generate report
php artisan tenancy:performance-test-all --report

# Report location
ls -lh storage/logs/tenancy-performance-report-*.html
```

---

## Contributing

To add new performance tests:

1. Create a new command in `Commands/PerformanceTesting/`
2. Extend the base test structure
3. Add to the master test suite
4. Update this documentation

---

## Support

For issues or questions:
- Package: artflow-studio/tenancy
- Documentation: Check package README
- Issues: Create a GitHub issue

---

**Made with ❤️ for optimal multi-tenancy performance**
