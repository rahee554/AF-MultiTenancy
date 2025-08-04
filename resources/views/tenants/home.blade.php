<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tenant->name }} - Welcome</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }
        h1 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        .domain {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }
        .buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: transform 0.2s;
            cursor: pointer;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-secondary {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #dee2e6;
        }
        .features {
            margin-top: 2rem;
            text-align: left;
        }
        .feature {
            display: flex;
            align-items: center;
            margin-bottom: 0.8rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .feature-icon {
            width: 20px;
            height: 20px;
            background: #28a745;
            border-radius: 50%;
            margin-right: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            {{ strtoupper(substr($tenant->name, 0, 1)) }}
        </div>
        
        <h1>Welcome to {{ $tenant->name }}</h1>
        <div class="domain">{{ $domain }}</div>
        
        <div class="buttons">
            <a href="/login" class="btn btn-primary">Sign In</a>
            <a href="/register" class="btn btn-secondary">Sign Up</a>
        </div>
        
        <div class="features">
            <div class="feature">
                <div class="feature-icon">✓</div>
                <div>Secure multi-tenant platform</div>
            </div>
            <div class="feature">
                <div class="feature-icon">✓</div>
                <div>Powered by AF-MultiTenancy</div>
            </div>
            <div class="feature">
                <div class="feature-icon">✓</div>
                <div>Enterprise-grade isolation</div>
            </div>
        </div>
    </div>
</body>
</html>
