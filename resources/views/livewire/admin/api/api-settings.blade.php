@extends('artflow-tenancy::layout.app')

@push('page-title')
    {{ $title }}
@endpush

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h1 class="h3 mb-0">API Settings</h1>
                    <p class="text-muted mb-0">Configure your API settings and preferences</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Form -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">General Settings</h5>
                </div>
                <div class="card-body">
                    <form wire:submit="saveSettings">
                        <div class="row">
                            <div class="col-md-6">
                                <!-- API Enabled -->
                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" type="checkbox" id="apiEnabled" wire:model="apiEnabled">
                                    <label class="form-check-label" for="apiEnabled">
                                        Enable API Access
                                    </label>
                                    <div class="form-text">Allow external applications to access the API</div>
                                </div>

                                <!-- Rate Limiting -->
                                <div class="mb-4">
                                    <label for="rateLimit" class="form-label">Rate Limit (requests per minute)</label>
                                    <input type="number" class="form-control" id="rateLimit" wire:model="rateLimit" min="1" max="1000">
                                    <div class="form-text">Maximum number of API requests per minute per key</div>
                                </div>

                                <!-- Timeout -->
                                <div class="mb-4">
                                    <label for="timeout" class="form-label">Request Timeout (seconds)</label>
                                    <input type="number" class="form-control" id="timeout" wire:model="timeout" min="5" max="120">
                                    <div class="form-text">Maximum time to wait for API responses</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <!-- Logging -->
                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" type="checkbox" id="enableLogging" wire:model="enableLogging">
                                    <label class="form-check-label" for="enableLogging">
                                        Enable API Logging
                                    </label>
                                    <div class="form-text">Log all API requests and responses</div>
                                </div>

                                <!-- Log Level -->
                                <div class="mb-4">
                                    <label for="logLevel" class="form-label">Log Level</label>
                                    <select class="form-select" id="logLevel" wire:model="logLevel">
                                        <option value="debug">Debug</option>
                                        <option value="info">Info</option>
                                        <option value="warning">Warning</option>
                                        <option value="error">Error</option>
                                    </select>
                                    <div class="form-text">Minimum level of logs to record</div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- API Status -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">API Status</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <div class="badge bg-{{ $apiEnabled ? 'success' : 'danger' }} badge-circle me-3">
                                    <i class="fas fa-{{ $apiEnabled ? 'check' : 'times' }}"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">API Status</div>
                                    <div class="text-muted small">{{ $apiEnabled ? 'Enabled' : 'Disabled' }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <div class="badge bg-info badge-circle me-3">
                                    <i class="fas fa-tachometer-alt"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">Rate Limit</div>
                                    <div class="text-muted small">{{ $rateLimit }} req/min</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <div class="badge bg-warning badge-circle me-3">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">Timeout</div>
                                    <div class="text-muted small">{{ $timeout }} seconds</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <div class="badge bg-{{ $enableLogging ? 'success' : 'secondary' }} badge-circle me-3">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">Logging</div>
                                    <div class="text-muted small">{{ $enableLogging ? 'Enabled' : 'Disabled' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.badge-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
@endsection
