<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartPOS - Your POS Terminal Credentials</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 20px;
            color: #333333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: #ffffff;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 24px;
            font-weight: 700;
        }
        .header p {
            margin: 0;
            font-size: 15px;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .credential-box {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .credential-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .credential-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        .label {
            font-weight: 600;
            color: #64748b;
            font-size: 14px;
        }
        .value {
            font-family: 'Courier New', Courier, monospace;
            font-weight: 700;
            color: #0f172a;
            font-size: 16px;
        }
        .steps {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 15px 20px;
            border-radius: 0 8px 8px 0;
            margin: 20px 0;
        }
        .steps h3 {
            margin-top: 0;
            color: #1e3a8a;
            font-size: 16px;
        }
        .steps ol {
            margin: 0;
            padding-left: 20px;
        }
        .steps li {
            margin-bottom: 8px;
            font-size: 14px;
            line-height: 1.5;
        }
        .footer {
            background: #f8fafc;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚡ SmartPOS Terminal Setup</h1>
            <p>Your POS hardware machine is ready to connect</p>
        </div>

        <div class="content">
            <p>Hello <strong>{{ $business->name }}</strong>,</p>
            <p>Your business account has been successfully created. We have automatically provisioned your primary store branch, cash register, and physical POS terminal.</p>

            <div class="credential-box">
                <div class="credential-row">
                    <span class="label">Store Outlet:</span>
                    <span class="value">{{ $outlet->name }} ({{ $outlet->code }})</span>
                </div>
                <div class="credential-row">
                    <span class="label">Cash Register:</span>
                    <span class="value">{{ $register->name }} ({{ $register->code }})</span>
                </div>
                <div class="credential-row">
                    <span class="label">Device Code:</span>
                    <span class="value" style="color: #2563eb;">{{ $device->device_code }}</span>
                </div>
                <div class="credential-row">
                    <span class="label">Device Machine Password:</span>
                    <span class="value" style="color: #dc2626; background: #fee2e2; padding: 2px 8px; border-radius: 4px;">{{ $plainPassword }}</span>
                </div>
            </div>

            <div class="steps">
                <h3>🚀 How to Connect Your Flutter POS Tablet:</h3>
                <ol>
                    <li>Open the <strong>SmartPOS Flutter App</strong> on your POS terminal / tablet.</li>
                    <li>On the Device Setup screen, enter the <strong>Device Code</strong>: <code>{{ $device->device_code }}</code>.</li>
                    <li>Enter the <strong>Device Password</strong>: <code>{{ $plainPassword }}</code>.</li>
                    <li>Click <strong>Connect POS</strong> to authenticate your terminal.</li>
                    <li>After device authentication, your cashiers can log in using their 4-digit PIN!</li>
                </ol>
            </div>

            <p style="font-size: 13px; color: #64748b;">
                <strong>Security Notice:</strong> Please keep this machine password secure. If you ever need to replace or rotate this password, you can do so in the SmartPOS Owner Backoffice.
            </p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} SmartPOS Ecosystem. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
