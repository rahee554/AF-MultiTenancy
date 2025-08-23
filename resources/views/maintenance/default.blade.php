<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode - {{ config('app.name', 'Tenant Application') }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1.6;
        }

        .maintenance-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            max-width: 600px;
            width: 90%;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .maintenance-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .maintenance-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .maintenance-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1rem;
        }

        .maintenance-message {
            font-size: 1.2rem;
            color: #4a5568;
            margin-bottom: 2rem;
            line-height: 1.8;
        }

        .maintenance-details {
            background: #f7fafc;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid #667eea;
        }

        .maintenance-detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .maintenance-detail-item:last-child {
            margin-bottom: 0;
        }

        .detail-label {
            font-weight: 600;
            color: #2d3748;
        }

        .detail-value {
            color: #4a5568;
            font-family: monospace;
            background: #e2e8f0;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .contact-info {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 2rem;
        }

        .contact-title {
            font-weight: 600;
            color: #c53030;
            margin-bottom: 0.5rem;
        }

        .contact-value {
            color: #2d3748;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            margin: 2rem 0 1rem;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 3px;
            animation: progress 3s ease-in-out infinite;
        }

        @keyframes progress {
            0% { width: 10%; }
            50% { width: 80%; }
            100% { width: 10%; }
        }

        .estimated-time {
            font-size: 0.9rem;
            color: #718096;
            margin-top: 1rem;
        }

        .bypass-hint {
            margin-top: 2rem;
            padding: 1rem;
            background: #f0f9ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #1e40af;
        }

        @media (max-width: 640px) {
            .maintenance-container {
                padding: 2rem;
                margin: 1rem;
            }
            
            .maintenance-title {
                font-size: 2rem;
            }
            
            .maintenance-message {
                font-size: 1.1rem;
            }
            
            .maintenance-detail-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-icon">🔧</div>
        
        <h1 class="maintenance-title">Maintenance Mode</h1>
        
        <p class="maintenance-message">
            {{ $message ?? 'This tenant is temporarily unavailable for maintenance. We\'re working to improve your experience.' }}
        </p>

        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>

        @if(isset($enabled_at) || isset($retry_after))
        <div class="maintenance-details">
            @if(isset($enabled_at))
            <div class="maintenance-detail-item">
                <span class="detail-label">Maintenance Started:</span>
                <span class="detail-value">{{ \Carbon\Carbon::parse($enabled_at)->format('M j, Y g:i A') }}</span>
            </div>
            @endif
            
            @if(isset($retry_after) && $retry_after > 0)
            <div class="maintenance-detail-item">
                <span class="detail-label">Estimated Return:</span>
                <span class="detail-value">{{ \Carbon\Carbon::now()->addSeconds($retry_after)->format('g:i A') }}</span>
            </div>
            @endif
        </div>
        @endif

        <p class="estimated-time">
            We expect to be back online shortly. Thank you for your patience.
        </p>

        @if(isset($admin_contact) && $admin_contact)
        <div class="contact-info">
            <div class="contact-title">Need Assistance?</div>
            <div class="contact-value">Contact: {{ $admin_contact }}</div>
        </div>
        @endif

        @if(isset($bypass_key) && $bypass_key && config('app.debug'))
        <div class="bypass-hint">
            <strong>Development Mode:</strong> Add <code>?bypass_key={{ $bypass_key }}</code> to bypass maintenance mode.
        </div>
        @endif
    </div>

    <script>
        // Auto-refresh every 30 seconds to check if maintenance is over
        setTimeout(function() {
            window.location.reload();
        }, 30000);

        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.maintenance-container');
            
            // Add a subtle entrance animation
            container.style.opacity = '0';
            container.style.transform = 'translateY(20px)';
            
            setTimeout(function() {
                container.style.transition = 'all 0.6s ease-out';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>
