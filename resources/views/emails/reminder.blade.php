<!DOCTYPE html>
<html>
<head>
    <title>Reminder: Applicant Form Review Needed</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333333;
        }
        h1 {
            color: #007BFF;
        }
        p {
            line-height: 1.6;
        }
        .footer {
            margin-top: 20px;
            font-size: 0.9em;
            color: #777777;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            font-size: 16px;
            color: #ffffff !important;
            background-color: #007BFF;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <h1>Reminder: Applicant Form Review Needed</h1>

    <p>Dear Admissions Team,</p>

    <p>This is a reminder regarding the application form for the <b>{{ $courseName }}</b> program submitted by<b> {{ $studentName }}</b> through StudyPal. We kindly request that you review the application at your earliest convenience.</p>

    <p>Details of the application:</p>
    <ul>
        <li>Student Name:<b> {{ $studentName }}</b></li>
        <li>Course: <b>{{ $courseName }}</b></li>
        <li>School: <b>{{ $schoolName }}</b></li>
    </ul>

    <p>To review the application, please click the button below:</p>
    <p><a href="{{ $reviewLink }}" class="button" target="_blank">Review Application</a></p>

    <p>Thank you for your attention to this matter.</p>

    <p>Best regards,<br/>
    The StudyPal Team</p>

    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
    </div>
</body>
</html>
