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
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">API Keys</h1>
                        <p class="text-muted mb-0">Manage your API keys and access tokens</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createKeyModal">
                        <i class="fas fa-plus me-2"></i>Generate New Key
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- API Keys List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Active API Keys</h5>
                </div>
                <div class="card-body">
                    @if(empty($apiKeys))
                        <div class="text-center py-5">
                            <i class="fas fa-key fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No API keys found</h5>
                            <p class="text-muted">Generate your first API key to get started</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>API Key</th>
                                        <th>Permissions</th>
                                        <th>Created</th>
                                        <th>Last Used</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($apiKeys as $key)
                                        <tr>
                                            <td>
                                                <div class="fw-bold">{{ $key['name'] }}</div>
                                            </td>
                                            <td>
                                                <code class="bg-light px-2 py-1 rounded">{{ $key['key'] }}</code>
                                            </td>
                                            <td>
                                                @foreach($key['permissions'] as $permission)
                                                    <span class="badge bg-secondary me-1">{{ $permission }}</span>
                                                @endforeach
                                            </td>
                                            <td>{{ $key['created_at'] }}</td>
                                            <td>{{ $key['last_used'] }}</td>
                                            <td>
                                                @if($key['status'] === 'active')
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-danger">Revoked</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($key['status'] === 'active')
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            wire:click="revokeKey({{ $key['id'] }})"
                                                            onclick="return confirm('Are you sure you want to revoke this API key?')">
                                                        <i class="fas fa-ban me-1"></i>Revoke
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create API Key Modal -->
<div class="modal fade" id="createKeyModal" tabindex="-1" aria-labelledby="createKeyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createKeyModalLabel">Generate New API Key</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form wire:submit="generateApiKey">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="newKeyName" class="form-label">Key Name</label>
                        <input type="text" class="form-control" id="newKeyName" wire:model="newKeyName" 
                               placeholder="e.g., Production API Key" required>
                        <div class="form-text">Choose a descriptive name for this API key</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Permissions</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="read" 
                                   wire:model="newKeyPermissions" id="permRead">
                            <label class="form-check-label" for="permRead">
                                Read Access
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="write" 
                                   wire:model="newKeyPermissions" id="permWrite">
                            <label class="form-check-label" for="permWrite">
                                Write Access
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="delete" 
                                   wire:model="newKeyPermissions" id="permDelete">
                            <label class="form-check-label" for="permDelete">
                                Delete Access
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key me-2"></i>Generate Key
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
