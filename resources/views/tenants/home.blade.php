<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $tenant->name ?? 'Tenant' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px 60px;
            text-align: center;
            max-width: 600px;
            margin: 20px;
        }
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2.5rem;
            font-weight: 300;
        }
        .subtitle {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .stats {
            display: flex;
            justify-content: space-around;
            margin: 30px 0;
            flex-wrap: wrap;
            gap: 20px;
        }
        .stat {
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            display: block;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .actions {
            margin-top: 40px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 0 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 500;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        .btn-outline {
            background: transparent;
            border: 2px solid #667eea;
            color: #667eea;
        }
        .btn-outline:hover {
            background: #667eea;
            color: white;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #999;
            font-size: 0.9rem;
        }
        @media (max-width: 768px) {
            .container {
                padding: 30px 20px;
                margin: 10px;
            }
            h1 {
                font-size: 2rem;
            }
            .stats {
                flex-direction: column;
                align-items: center;
            }
            .btn {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            {{ strtoupper(substr($tenant->name ?? 'T', 0, 1)) }}
        </div>
        
        <h1>Welcome to {{ $tenant->name ?? 'Your Tenant' }}</h1>
        <p class="subtitle">
            You're now accessing <strong>{{ $domain }}</strong><br>
            Your personalized tenant environment is ready to use.
        </p>
        
        <div class="stats">
            <div class="stat">
                <span class="stat-number">{{ $tenant->id ? '✓' : '×' }}</span>
                <span class="stat-label">Tenant Active</span>
            </div>
            <div class="stat">
                <span class="stat-number">{{ $tenant->status ?? 'Unknown' }}</span>
                <span class="stat-label">Status</span>
            </div>
            <div class="stat">
                <span class="stat-number">{{ $tenant->hasHomepage() ? '✓' : '×' }}</span>
                <span class="stat-label">Homepage</span>
            </div>
        </div>
        
        <div class="actions">
            <a href="/tenant/dashboard" class="btn">Go to Dashboard</a>
            <a href="/tenant/info" class="btn btn-outline">Tenant Info</a>
        </div>
        
        <div class="footer">
            <p>Powered by <strong>AF-MultiTenancy</strong> Package</p>
            <p>Tenant ID: <code>{{ Str::limit($tenant->id ?? 'N/A', 8) }}...</code></p>
        </div>
    </div>
</body>
</html>
