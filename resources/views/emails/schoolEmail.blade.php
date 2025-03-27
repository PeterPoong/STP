<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Applicant Received From StudyPal</title>
    <style>
        .button {
    display: inline-block;
    padding: 10px 20px;
    font-size: 16px;
    background-color: #4CAF50; /* Green background */
    color: white !important; /* White text */
    border: 1px solid #4CAF50; /* Match border color with background */
    text-decoration: none;
    border-radius: 5px;
}
        </style>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <table style="width: 100%; max-width: 600px; margin: 0 auto; border-collapse: collapse;">
        <tr>
            <td style="padding: 20px; text-align: center; background-color: #f8f8f8;">
                <h1 style="margin: 0; color: #333;">New Applicant Received From StudyPal</h1>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px; background-color: #ffffff;">
                <p>Dear {{ $institute_name }},</p>

                <p>We are pleased to inform you that a new student has applied for the <strong>{{ $course_name }}</strong> at your esteemed institution through StudyPal. Below are the details of the applicant:</p>

                <table style="width: 100%; border: 1px solid #dddddd; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 10px; border: 1px solid #dddddd;"><strong>Applicant Name:</strong></td>
                        <td style="padding: 10px; border: 1px solid #dddddd;">{{ $student_name }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #dddddd;"><strong>Email Address:</strong></td>
                        <td style="padding: 10px; border: 1px solid #dddddd;">{{ str_repeat('*', strlen($student_email) - strpos($student_email, '@')) . substr($student_email, strpos($student_email, '@')) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #dddddd;"><strong>Phone Number:</strong></td>
                        <td style="padding: 10px; border: 1px solid #dddddd;"> {{ '+' . str_repeat('*', strlen($student_phone) - 5) . substr($student_phone, -5) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #dddddd;"><strong>Course Applied For:</strong></td>
                        <td style="padding: 10px; border: 1px solid #dddddd;">{{ $course_name }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #dddddd;"><strong>Application Date:</strong></td>
                        <td style="padding: 10px; border: 1px solid #dddddd;">{{ $application_date }}</td>
                    </tr>
                </table>

                <p>Please review the application at your earliest convenience. You can access the full application and any supporting documents through your StudyPal dashboard.</p>
                <p>
                    {{-- <a href="{{ $actionUrl }}" target="_blank" style="color: #4CAF50; text-decoration: none;">
                        View Application Details
                    </a> --}}
                    <p><a href="{{ $actionUrl }}" class="button" target="_blank">View Application Details</a></p>

                </p>

                <p>If you have any questions or require further information, feel free to reach out to us.</p>

                <p>Thank you for your continued partnership.</p>

                <p>Best regards,<br/>
                    StudyPal</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px; text-align: center; background-color: #f8f8f8;">
                <p style="margin: 0;">&copy; {{ date('Y') }} StudyPal. All rights reserved.</p>
            </td>
        </tr>
    </table>
</body>
</html>
