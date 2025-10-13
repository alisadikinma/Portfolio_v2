<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Contact Message</title>
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
            background-color: #3B82F6;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 30px;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 5px 5px;
        }
        .field {
            margin-bottom: 20px;
        }
        .field-label {
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
        }
        .field-value {
            background-color: white;
            padding: 10px;
            border-left: 3px solid #3B82F6;
            margin-top: 5px;
        }
        .message-box {
            background-color: white;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-top: 5px;
            white-space: pre-wrap;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #777;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>New Contact Message</h1>
    </div>

    <div class="content">
        <p>You have received a new contact message from your portfolio website.</p>

        <div class="field">
            <div class="field-label">From:</div>
            <div class="field-value">{{ $contact->name }}</div>
        </div>

        <div class="field">
            <div class="field-label">Email:</div>
            <div class="field-value">
                <a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>
            </div>
        </div>

        <div class="field">
            <div class="field-label">Subject:</div>
            <div class="field-value">{{ $contact->subject }}</div>
        </div>

        <div class="field">
            <div class="field-label">Message:</div>
            <div class="message-box">{{ $contact->message }}</div>
        </div>

        <div class="field">
            <div class="field-label">Received at:</div>
            <div class="field-value">{{ $contact->created_at->format('F j, Y g:i A') }}</div>
        </div>
    </div>

    <div class="footer">
        <p>This is an automated notification from your Portfolio V2 contact form.</p>
        <p>To reply, simply respond to this email or click the email address above.</p>
    </div>
</body>
</html>
