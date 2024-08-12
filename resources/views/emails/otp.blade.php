<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your OTP Code</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            padding: 10px 0;
            border-bottom: 1px solid #eeeeee;
        }
        .header h1 {
            margin: 0;
            color: #333333;
        }
        .content {
            padding: 20px 0;
            color: #555555;
            line-height: 1.6;
        }
        .otp-code {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin: 20px 0;
            background-color: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
        }
        .footer {
            text-align: center;
            padding: 20px 0;
            color: #777777;
            font-size: 12px;
            border-top: 1px solid #eeeeee;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Your OTP Code</h1>
        </div>
        <div class="content">
            <p>Hello,</p>
            <p>We received a request to reset your password. Please use the OTP code below to proceed with the resetting password process:</p>
            <div class="otp-code">
                {{ $otp }}
            </div>
            <p>This code will expire in 5 minutes. If you did not request this OTP, please ignore this email or contact our support team immediately.</p>
            <p>For your security, never share your OTP with anyone.</p>
        </div>
        <div class="footer">
            <p>Thank you,</p>
            <p><strong>{{ config('app.name') }}</strong></p>
            <p>This is an automated message, please do not reply.</p>
        </div>
    </div>
</body>
</html>
