<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reply to Your Enquiry</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-bottom: 3px solid #dee2e6;
        }
        .content {
            padding: 30px 20px;
            background: #ffffff;
        }
        .message {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #6c757d;
            border-top: 1px solid #dee2e6;
        }
        @media only screen and (max-width: 600px) {
            .email-wrapper {
                width: 100% !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="header">
            <h2>Reply to Your Enquiry</h2>
        </div>

        <div class="content">
            <p>Dear Valued Customer,</p>

            <div class="message">
                {!! nl2br(e($messageContent)) !!}
            </div>

            <p>If you have any further questions, please don't hesitate to contact us.</p>

            <p>Best regards,<br>
            {{ config('app.name') }} Team</p>
        </div>

        <div class="footer">
            <p>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <p>This email was sent in response to your enquiry.</p>
        </div>
    </div>
</body>
</html>
