<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Report</title>
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
            text-align: center; /* Center align the content including the button */
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
        /* Button Styles */
        .button {
            display: inline-block;
            padding: 10px 20px;
            margin-top: 20px;
            background-color: white;
            color: #B71A18 !important; /* Ensure the text is red */
            text-align: center;
            text-decoration: none !important; /* Ensure no underline */
            border: 2px solid #B71A18;
            border-radius: 5px;
            font-size: 16px;
            transition: background-color 0.3s, color 0.3s;
        }

        /* Prevent the text color from turning blue */
        .button:link, .button:visited, .button:focus, .button:active {
            color: #B71A18 !important; /* Ensure text is red in all states */
        }

        /* Button Hover Effect */
        .button:hover {
            background-color: #B71A18;
            color: white !important; /* Set text color to white on hover */
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Monthly Student Interested Report</h2>
        </div>
        
        <div class="content">
            <p>Hello Admin,</p>
            
            <p>You have received a new monthly report with the following details:</p>
            
            <table>
                <tr>
                    <th>Course Category</th>
                    <th>Number of Students Interested</th>
                </tr>
                @foreach ($courseCategory as $item)
                    <tr>
                        <td><strong>{{ $item['category_name'] }}</strong></td>
                        <td>{{ $item['number_count'] }}</td>
                    </tr>
                @endforeach
            </table>
            
            <p>This report was received on {{ date('F j, Y \a\t g:i a') }}</p>
            
            <!-- Centered Button -->
            <a href="https://studypal.my/schoolPortalLogin" class="button">View more Detail</a>
        </div>
        
        <div class="footer">
            <p>This is an automated email notification. Please do not reply to this email.</p>
            <p>© {{ date('Y') }} Studypal. All rights reserved.</p>
        </div>
    </div>
</body>
</html>