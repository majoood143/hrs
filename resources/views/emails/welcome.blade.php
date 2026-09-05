<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome Email</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #4f46e5;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border-radius: 0 0 8px 8px;
            border: 1px solid #e5e7eb;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: #4f46e5;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 14px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Welcome to {{ $appName }}!</h1>
    </div>
    
    <div class="content">
        <h2>Hello {{ $user->name }},</h2>
        
        <p>We're excited to welcome you to {{ $appName }}! Your account has been successfully created.</p>
        
        @if($password)
        <p><strong>Your temporary password:</strong> {{ $password }}</p>
        <p>Please change your password after your first login.</p>
        @endif
        
        <p>You can now access your account and start using our services.</p>
        
        <div style="text-align: center;">
            <a href="{{ url('/login') }}" class="button">Login to Your Account</a>
        </div>
        
        <p>If you have any questions, feel free to contact our support team.</p>
        
        <p>Best regards,<br>The {{ $appName }} Team</p>
    </div>
    
    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
    </div>
</body>
</html>