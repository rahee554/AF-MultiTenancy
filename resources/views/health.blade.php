@extends('artflow-tenancy::layout.app')

@section('title', 'System Health Check')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">System Health Check</h1>
                    <p class="mt-2 text-gray-600">Monitor the status of all system components</p>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 rounded-full {{ $overall_status === 'healthy' ? 'bg-green-500' : 'bg-red-500' }}"></div>
                    <span class="text-sm font-medium {{ $overall_status === 'healthy' ? 'text-green-700' : 'text-red-700' }}">
                        {{ ucfirst($overall_status) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Overall Status Card -->
        <div class="mb-8 p-6 bg-white rounded-lg shadow-md border-l-4 {{ $overall_status === 'healthy' ? 'border-green-500' : 'border-red-500' }}">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    @if($overall_status === 'healthy')
                        <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    @else
                        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    @endif
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-medium {{ $overall_status === 'healthy' ? 'text-green-900' : 'text-red-900' }}">
                        System is {{ $overall_status === 'healthy' ? 'Healthy' : 'Experiencing Issues' }}
                    </h3>
                    <p class="text-sm {{ $overall_status === 'healthy' ? 'text-green-700' : 'text-red-700' }}">
                        Last checked: {{ $timestamp->format('Y-m-d H:i:s') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Health Checks Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($health_checks as $component => $check)
                <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 capitalize">{{ $component }}</h3>
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full 
                                {{ $check['status'] === 'ok' ? 'bg-green-500' : 
                                   ($check['status'] === 'warning' ? 'bg-yellow-500' : 'bg-red-500') }}">
                            </div>
                            <span class="text-sm font-medium 
                                {{ $check['status'] === 'ok' ? 'text-green-700' : 
                                   ($check['status'] === 'warning' ? 'text-yellow-700' : 'text-red-700') }}">
                                {{ ucfirst($check['status']) }}
                            </span>
                        </div>
                    </div>
                    
                    <div class="text-sm text-gray-600">
                        {{ $check['message'] }}
                    </div>

                    @if($check['status'] === 'ok')
                        <div class="mt-4 flex items-center text-green-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="text-xs">Working properly</span>
                        </div>
                    @elseif($check['status'] === 'warning')
                        <div class="mt-4 flex items-center text-yellow-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            <span class="text-xs">Needs attention</span>
                        </div>
                    @else
                        <div class="mt-4 flex items-center text-red-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <span class="text-xs">Not working</span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- Actions -->
        <div class="mt-8 flex justify-between items-center">
            <div class="flex space-x-4">
                <a href="{{ route('tenancy.dashboard') }}" 
                   class="inline-flex items-center px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-md transition duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Dashboard
                </a>
            </div>
            
            <button onclick="window.location.reload()" 
                    class="inline-flex items-center px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-md transition duration-200">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Refresh Health Check
            </button>
        </div>
    </div>
</div>
@endsection
