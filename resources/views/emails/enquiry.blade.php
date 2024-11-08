<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Enquiry Notification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .content {
            padding: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>New Enquiry Received</h2>
        </div>
        
        <div class="content">
            <p>Hello Admin,</p>
            
            <p>You have received a new enquiry with the following details:</p>
            
            <table>
                <tr>
                    <th>Field</th>
                    <th>Information</th>
                </tr>
                <tr>
                    <td><strong>Name</strong></td>
                    <td>{{ $fullName ?? 'Not provided' }}</td>
                </tr>
                <tr>
                    <td><strong>Email</strong></td>
                    <td>{{ $email ?? 'Not provided' }}</td>
                </tr>
                <tr>
                    <td><strong>Phone</strong></td>
                    <td>{{ $contact ?? 'Not provided' }}</td>
                </tr>
                <tr>
                    <td><strong>Enquiry Subject</strong></td>
                    <td>{{ $emailSubject ?? 'Not provided' }}</td>
                </tr>
                <tr>
                    <td><strong>Message</strong></td>
                    <td>{{ $messageContent ?? 'No message content' }}</td>
                </tr>
            </table>
            
            <p>This enquiry was received on {{ date('F j, Y \a\t g:i a') }}</p>
        </div>
        
        <div class="footer">
            <p>This is an automated email notification. Please do not reply to this email.</p>
            <p>© {{ date('Y') }} Your Company Name. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
