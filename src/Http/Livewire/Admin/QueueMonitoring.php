<?php

namespace ArtflowStudio\Tenancy\Http\Livewire\Admin;

use Livewire\Component;
use ArtflowStudio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class QueueMonitoring extends Component
{
    protected $layout = 'artflow-tenancy::layout.app';
    
    public array $queueJobs = [];
    public array $failedJobs = [];
    public array $queueStats = [];
    public string $selectedQueue = 'default';
    public bool $autoRefresh = true;
    public int $refreshInterval = 10;
    public array $availableQueues = []; // Available queue configurations

    protected $listeners = ['refreshQueues'];

    public function mount()
    {
        $this->loadAvailableQueues();
        $this->refreshQueues();
    }

    private function loadAvailableQueues(): void
    {
        $queueConfig = config('queue.connections', []);
        $this->availableQueues = [];
        
        foreach ($queueConfig as $name => $config) {
            $this->availableQueues[] = [
                'name' => $name,
                'driver' => $config['driver'] ?? 'unknown',
                'active' => $name === config('queue.default'),
            ];
        }
        
        // Fallback to default queues if none configured
        if (empty($this->availableQueues)) {
            $this->availableQueues = [
                ['name' => 'default', 'driver' => 'sync', 'active' => true],
                ['name' => 'high', 'driver' => 'sync', 'active' => false],
                ['name' => 'low', 'driver' => 'sync', 'active' => false],
            ];
        }
    }

    public function refreshQueues()
    {
        $this->queueJobs = $this->getQueueJobs();
        $this->failedJobs = $this->getFailedJobs();
        $this->queueStats = $this->getQueueStats();
    }

    public function refreshStats()
    {
        $this->refreshQueues();
        session()->flash('message', 'Queue statistics refreshed successfully.');
    }

    public function clearFailedJobs()
    {
        $this->clearAllFailedJobs();
    }

    public function retryFailedJob($jobId)
    {
        try {
            $failedJob = DB::table('failed_jobs')->where('id', $jobId)->first();
            if ($failedJob) {
                // Retry the job
                Queue::pushRaw($failedJob->payload, $failedJob->queue);
                DB::table('failed_jobs')->where('id', $jobId)->delete();
                
                session()->flash('message', 'Job retried successfully');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to retry job: ' . $e->getMessage());
        }
        
        $this->refreshQueues();
    }

    public function retryAllFailedJobs()
    {
        try {
            $failedJobs = DB::table('failed_jobs')->get();
            $retried = 0;
            
            foreach ($failedJobs as $job) {
                try {
                    Queue::pushRaw($job->payload, $job->queue);
                    DB::table('failed_jobs')->where('id', $job->id)->delete();
                    $retried++;
                } catch (\Exception $e) {
                    // Continue with other jobs
                    continue;
                }
            }
            
            session()->flash('message', "Successfully retried {$retried} jobs");
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to retry jobs: ' . $e->getMessage());
        }
        
        $this->refreshQueues();
    }

    public function deleteFailedJob($jobId)
    {
        try {
            DB::table('failed_jobs')->where('id', $jobId)->delete();
            session()->flash('message', 'Failed job deleted successfully');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete job: ' . $e->getMessage());
        }
        
        $this->refreshQueues();
    }

    public function clearAllFailedJobs()
    {
        try {
            $count = DB::table('failed_jobs')->count();
            DB::table('failed_jobs')->truncate();
            session()->flash('message', "Cleared {$count} failed jobs");
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to clear jobs: ' . $e->getMessage());
        }
        
        $this->refreshQueues();
    }

    public function dispatchTestJob()
    {
        try {
            // Dispatch a test job to verify queue is working
            dispatch(new \ArtflowStudio\Tenancy\Jobs\TestQueueJob());
            session()->flash('message', 'Test job dispatched successfully');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to dispatch test job: ' . $e->getMessage());
        }
        
        $this->refreshQueues();
    }

    private function getQueueJobs(): array
    {
        try {
            if (config('queue.default') === 'database') {
                return DB::table('jobs')
                    ->select('id', 'queue', 'payload', 'attempts', 'created_at', 'available_at')
                    ->orderBy('created_at', 'desc')
                    ->limit(50)
                    ->get()
                    ->map(function ($job) {
                        $payload = json_decode($job->payload, true);
                        return [
                            'id' => $job->id,
                            'queue' => $job->queue,
                            'job_class' => $payload['displayName'] ?? 'Unknown',
                            'attempts' => $job->attempts,
                            'created_at' => $job->created_at,
                            'available_at' => $job->available_at,
                            'data' => $payload['data'] ?? [],
                        ];
                    })
                    ->toArray();
            }
            
            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getFailedJobs(): array
    {
        try {
            return DB::table('failed_jobs')
                ->select('id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at')
                ->orderBy('failed_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($job) {
                    $payload = json_decode($job->payload, true);
                    return [
                        'id' => $job->id,
                        'uuid' => $job->uuid,
                        'connection' => $job->connection,
                        'queue' => $job->queue,
                        'job_class' => $payload['displayName'] ?? 'Unknown',
                        'exception' => substr($job->exception, 0, 200) . '...',
                        'failed_at' => $job->failed_at,
                        'full_exception' => $job->exception,
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getQueueStats(): array
    {
        try {
            $stats = [
                'pending_jobs' => 0,
                'failed_jobs' => 0,
                'processed_today' => 0,
                'queue_driver' => config('queue.default'),
                'connections' => [],
            ];

            if (config('queue.default') === 'database') {
                $stats['pending_jobs'] = DB::table('jobs')->count();
                $stats['failed_jobs'] = DB::table('failed_jobs')->count();
            }

            // Get queue connections
            $connections = config('queue.connections', []);
            foreach ($connections as $name => $config) {
                $stats['connections'][] = [
                    'name' => $name,
                    'driver' => $config['driver'] ?? 'unknown',
                    'active' => $name === config('queue.default'),
                ];
            }

            return $stats;
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'pending_jobs' => 0,
                'failed_jobs' => 0,
                'processed_today' => 0,
                'queue_driver' => 'unknown',
                'connections' => [],
            ];
        }
    }

    public function render()
    {
        return view('af-tenancy::livewire.admin.queue-monitoring')->extends($this->layout);
    }
}
