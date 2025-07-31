<?php

namespace ArtflowStudio\Tenancy\Commands\Testing\Performance;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MasterPerformanceTestCommand extends Command
{
    protected $signature = 'tenancy:performance-test-all 
                            {--quick : Run quick tests with reduced iterations}
                            {--full : Run comprehensive tests with maximum coverage}
                            {--report : Generate detailed HTML report}';

    protected $description = '🚀 Run all performance tests for the tenancy package';

    protected array $testResults = [];
    protected float $startTime;

    public function handle(): int
    {
        $this->startTime = microtime(true);
        
        $this->displayWelcome();

        $isQuick = $this->option('quick');
        $isFull = $this->option('full');

        // Define test suite
        $tests = $this->getTestSuite($isQuick, $isFull);

        $this->info('📋 Test Suite:');
        foreach ($tests as $index => $test) {
            $this->line("   " . ($index + 1) . ". {$test['name']}");
        }
        $this->newLine();

        if (!$this->option('no-interaction') && !$this->confirm('Start performance test suite?', true)) {
            $this->warn('Test suite cancelled.');
            return 0;
        }

        $this->newLine();
        $progressBar = $this->output->createProgressBar(count($tests));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
        $progressBar->setMessage('Initializing...');
        $progressBar->start();

        // Run each test
        foreach ($tests as $index => $test) {
            $progressBar->setMessage("Running: {$test['name']}");
            
            $testStart = microtime(true);
            $exitCode = Artisan::call($test['command'], $test['options']);
            $testDuration = round((microtime(true) - $testStart) * 1000, 2);

            $this->testResults[] = [
                'name' => $test['name'],
                'command' => $test['command'],
                'exit_code' => $exitCode,
                'duration' => $testDuration,
                'status' => $exitCode === 0 ? 'PASSED' : 'FAILED',
            ];

            $progressBar->advance();
        }

        $progressBar->setMessage('Completed!');
        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->displayResults();

        // Generate report if requested
        if ($this->option('report')) {
            $this->generateReport();
        }

        return 0;
    }

    private function displayWelcome(): void
    {
        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║                                                                ║');
        $this->info('║        🚀 TENANCY PERFORMANCE TEST SUITE                      ║');
        $this->info('║           artflow-studio/tenancy Package                      ║');
        $this->info('║                                                                ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->newLine();
        
        $mode = $this->option('quick') ? 'Quick Mode' : ($this->option('full') ? 'Full Mode' : 'Standard Mode');
        $this->comment("📊 Test Mode: {$mode}");
        $this->comment("🕐 Started: " . now()->format('Y-m-d H:i:s'));
        $this->newLine();
    }

    private function getTestSuite(bool $isQuick, bool $isFull): array
    {
        if ($isQuick) {
            return [
                [
                    'name' => 'Quick Performance Test',
                    'command' => 'tenancy:performance-test',
                    'options' => [
                        '--tenants' => 5,
                        '--queries' => 50,
                        '--concurrent' => 3,
                    ],
                ],
                [
                    'name' => 'Quick Connection Pool Test',
                    'command' => 'tenancy:test-connection-pool',
                    'options' => [
                        '--tenants' => 5,
                        '--iterations' => 50,
                    ],
                ],
            ];
        }

        if ($isFull) {
            return [
                [
                    'name' => 'Comprehensive Performance Test',
                    'command' => 'tenancy:performance-test',
                    'options' => [
                        '--tenants' => 20,
                        '--queries' => 500,
                        '--concurrent' => 10,
                        '--cleanup' => true,
                    ],
                ],
                [
                    'name' => 'Full Connection Pool Test',
                    'command' => 'tenancy:test-connection-pool',
                    'options' => [
                        '--tenants' => 20,
                        '--iterations' => 500,
                        '--check-leaks' => true,
                    ],
                ],
                [
                    'name' => 'Full Cache Performance Test',
                    'command' => 'tenancy:test-cache-performance',
                    'options' => [
                        '--tenants' => 10,
                        '--operations' => 2000,
                        '--key-size' => 'large',
                    ],
                ],
                [
                    'name' => 'Heavy Database Stress Test',
                    'command' => 'tenancy:stress-test-database',
                    'options' => [
                        '--tenants' => 10,
                        '--connections' => 100,
                        '--duration' => 120,
                        '--query-type' => 'complex',
                    ],
                ],
            ];
        }

        // Standard test suite
        return [
            [
                'name' => 'Standard Performance Test',
                'command' => 'tenancy:performance-test',
                'options' => [
                    '--tenants' => 10,
                    '--queries' => 100,
                    '--concurrent' => 5,
                ],
            ],
            [
                'name' => 'Connection Pool Test',
                'command' => 'tenancy:test-connection-pool',
                'options' => [
                    '--tenants' => 10,
                    '--iterations' => 100,
                    '--check-leaks' => true,
                ],
            ],
            [
                'name' => 'Cache Performance Test',
                'command' => 'tenancy:test-cache-performance',
                'options' => [
                    '--tenants' => 5,
                    '--operations' => 1000,
                    '--key-size' => 'medium',
                ],
            ],
            [
                'name' => 'Database Stress Test',
                'command' => 'tenancy:stress-test-database',
                'options' => [
                    '--tenants' => 5,
                    '--connections' => 50,
                    '--duration' => 60,
                    '--query-type' => 'mixed',
                ],
            ],
        ];
    }

    private function displayResults(): void
    {
        $totalDuration = round((microtime(true) - $this->startTime) * 1000, 2);
        
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║                  📊 TEST SUITE RESULTS                        ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->newLine();

        // Summary statistics
        $passed = count(array_filter($this->testResults, fn($r) => $r['status'] === 'PASSED'));
        $failed = count(array_filter($this->testResults, fn($r) => $r['status'] === 'FAILED'));
        $totalTests = count($this->testResults);

        $summaryTable = [
            ['Total Tests Run', $totalTests],
            ['Tests Passed', $passed > 0 ? "✅ {$passed}" : "0"],
            ['Tests Failed', $failed > 0 ? "❌ {$failed}" : "✅ 0"],
            ['Success Rate', round(($passed / $totalTests) * 100, 2) . '%'],
            ['Total Duration', $this->formatDuration($totalDuration)],
            ['Started At', date('Y-m-d H:i:s', (int)$this->startTime)],
            ['Completed At', now()->format('Y-m-d H:i:s')],
        ];

        $this->table(['Metric', 'Value'], $summaryTable);
        $this->newLine();

        // Detailed test results
        $this->info('📋 Detailed Test Results:');
        $this->newLine();

        $detailedResults = [];
        foreach ($this->testResults as $index => $result) {
            $statusIcon = $result['status'] === 'PASSED' ? '✅' : '❌';
            $detailedResults[] = [
                $index + 1,
                $result['name'],
                $statusIcon . ' ' . $result['status'],
                $this->formatDuration($result['duration']),
            ];
        }

        $this->table(
            ['#', 'Test Name', 'Status', 'Duration'],
            $detailedResults
        );

        $this->newLine();

        // Performance ratings
        $this->displayPerformanceRatings($totalDuration, $passed, $totalTests);

        // Final verdict
        $this->newLine();
        if ($failed === 0) {
            $this->info('🎉 All performance tests passed successfully!');
            $this->info('✨ Your multi-tenancy setup is performing optimally.');
        } else {
            $this->warn("⚠️  {$failed} test(s) failed.");
            $this->comment('💡 Review the failed tests and check the logs for details.');
        }
        
        $this->newLine();
    }

    private function displayPerformanceRatings(float $totalDuration, int $passed, int $totalTests): void
    {
        $this->info('⭐ Performance Ratings:');
        $this->newLine();

        $ratings = [];

        // Overall Performance Rating
        if ($passed === $totalTests) {
            $overallRating = '⭐⭐⭐⭐⭐ Excellent';
        } elseif ($passed >= $totalTests * 0.8) {
            $overallRating = '⭐⭐⭐⭐ Good';
        } elseif ($passed >= $totalTests * 0.6) {
            $overallRating = '⭐⭐⭐ Fair';
        } else {
            $overallRating = '⭐⭐ Needs Improvement';
        }
        $ratings[] = ['Overall', $overallRating];

        // Speed Rating
        $avgTestDuration = $totalDuration / $totalTests;
        if ($avgTestDuration < 5000) {
            $speedRating = '⭐⭐⭐⭐⭐ Very Fast';
        } elseif ($avgTestDuration < 10000) {
            $speedRating = '⭐⭐⭐⭐ Fast';
        } elseif ($avgTestDuration < 20000) {
            $speedRating = '⭐⭐⭐ Moderate';
        } else {
            $speedRating = '⭐⭐ Slow';
        }
        $ratings[] = ['Speed', $speedRating];

        // Reliability Rating
        $successRate = ($passed / $totalTests) * 100;
        if ($successRate === 100) {
            $reliabilityRating = '⭐⭐⭐⭐⭐ Perfect';
        } elseif ($successRate >= 90) {
            $reliabilityRating = '⭐⭐⭐⭐ Reliable';
        } elseif ($successRate >= 75) {
            $reliabilityRating = '⭐⭐⭐ Acceptable';
        } else {
            $reliabilityRating = '⭐⭐ Unstable';
        }
        $ratings[] = ['Reliability', $reliabilityRating];

        $this->table(['Category', 'Rating'], $ratings);
    }

    private function generateReport(): void
    {
        $this->newLine();
        $this->info('📄 Generating HTML report...');

        try {
            $reportPath = storage_path('logs/tenancy-performance-report-' . date('Y-m-d-H-i-s') . '.html');
            
            $html = $this->buildHtmlReport();
            file_put_contents($reportPath, $html);

            $this->info("✅ Report generated: {$reportPath}");
            $this->comment('💡 Open this file in a browser to view the detailed report.');
        } catch (\Exception $e) {
            $this->error("❌ Failed to generate report: {$e->getMessage()}");
        }
    }

    private function buildHtmlReport(): string
    {
        $totalDuration = round((microtime(true) - $this->startTime) * 1000, 2);
        $passed = count(array_filter($this->testResults, fn($r) => $r['status'] === 'PASSED'));
        $failed = count(array_filter($this->testResults, fn($r) => $r['status'] === 'FAILED'));
        $successRate = round(($passed / count($this->testResults)) * 100, 2);

        $testsHtml = '';
        foreach ($this->testResults as $index => $result) {
            $statusClass = $result['status'] === 'PASSED' ? 'success' : 'danger';
            $statusIcon = $result['status'] === 'PASSED' ? '✅' : '❌';
            
            $testsHtml .= "
                <tr>
                    <td>" . ($index + 1) . "</td>
                    <td>{$result['name']}</td>
                    <td><span class='badge badge-{$statusClass}'>{$statusIcon} {$result['status']}</span></td>
                    <td>{$this->formatDuration($result['duration'])}</td>
                </tr>
            ";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenancy Performance Test Report</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 2.5em;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .summary-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 0.9em;
            text-transform: uppercase;
        }
        .summary-card .value {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        .results-table {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🚀 Tenancy Performance Test Report</h1>
        <p>artflow-studio/tenancy Package</p>
        <p>Generated: " . now()->format('Y-m-d H:i:s') . "</p>
    </div>

    <div class="summary">
        <div class="summary-card">
            <h3>Total Tests</h3>
            <div class="value">{$this->testResults->count()}</div>
        </div>
        <div class="summary-card">
            <h3>Passed</h3>
            <div class="value" style="color: #28a745;">{$passed}</div>
        </div>
        <div class="summary-card">
            <h3>Failed</h3>
            <div class="value" style="color: #dc3545;">{$failed}</div>
        </div>
        <div class="summary-card">
            <h3>Success Rate</h3>
            <div class="value">{$successRate}%</div>
        </div>
        <div class="summary-card">
            <h3>Total Duration</h3>
            <div class="value" style="font-size: 1.5em;">{$this->formatDuration($totalDuration)}</div>
        </div>
    </div>

    <div class="results-table">
        <h2>Detailed Test Results</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Test Name</th>
                    <th>Status</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
                {$testsHtml}
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>Generated by artflow-studio/tenancy performance testing suite</p>
    </div>
</body>
</html>
HTML;
    }

    private function formatDuration(float $milliseconds): string
    {
        if ($milliseconds < 1000) {
            return round($milliseconds, 2) . 'ms';
        } elseif ($milliseconds < 60000) {
            return round($milliseconds / 1000, 2) . 's';
        } else {
            $minutes = floor($milliseconds / 60000);
            $seconds = round(($milliseconds % 60000) / 1000, 2);
            return "{$minutes}m {$seconds}s";
        }
    }
}
