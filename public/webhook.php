<?php
/**
 * Webhook Endpoint
 * 
 * This endpoint receives webhooks from 3DCart and processes orders.
 * Configure this URL in your 3DCart admin panel as the webhook endpoint.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Controllers\WebhookController;
use Laguna\Integration\Utils\Logger;

// Set timezone
date_default_timezone_set('America/New_York');

// Initialize logger
$logger = Logger::getInstance();

// Handle different request methods
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    // Handle webhook POST request
    try {
        $controller = new WebhookController();
        $controller->handleWebhook();
    } catch (\Exception $e) {
        $logger->error('Webhook endpoint error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Internal server error',
            'timestamp' => date('c')
        ]);
    }
} else {
    // Handle GET request - show webhook information
    $config = require __DIR__ . '/../config/config.php';
    $webhookUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                  '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Webhook Endpoint - 3DCart Integration</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                margin: 0;
                padding: 20px;
                background-color: #f5f5f5;
                color: #333;
            }
            .container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .content {
                padding: 30px;
            }
            .webhook-url {
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 5px;
                padding: 15px;
                font-family: 'Courier New', monospace;
                word-break: break-all;
                margin: 20px 0;
            }
            .info-box {
                background: #e3f2fd;
                border-left: 4px solid #2196f3;
                padding: 15px;
                margin: 20px 0;
                border-radius: 0 4px 4px 0;
            }
            .warning-box {
                background: #fff3e0;
                border-left: 4px solid #ff9800;
                padding: 15px;
                margin: 20px 0;
                border-radius: 0 4px 4px 0;
            }
            .success-box {
                background: #e8f5e8;
                border-left: 4px solid #4caf50;
                padding: 15px;
                margin: 20px 0;
                border-radius: 0 4px 4px 0;
            }
            .btn {
                display: inline-block;
                padding: 10px 20px;
                background: #667eea;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin: 5px;
            }
            .btn:hover {
                background: #5a6fd8;
            }
            code {
                background: #f8f9fa;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: 'Courier New', monospace;
            }
            pre {
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 5px;
                padding: 15px;
                overflow-x: auto;
            }
            .status-indicator {
                display: inline-block;
                width: 12px;
                height: 12px;
                border-radius: 50%;
                margin-right: 8px;
            }
            .status-active { background-color: #4caf50; }
            .status-inactive { background-color: #f44336; }
            .status-unknown { background-color: #ff9800; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔗 Webhook Endpoint</h1>
                <p>3DCart Order Processing Webhook</p>
            </div>
            
            <div class="content">
                <div class="info-box">
                    <h3>📍 Webhook URL</h3>
                    <p>Configure this URL in your 3DCart admin panel to receive order notifications:</p>
                    <div class="webhook-url"><?php echo htmlspecialchars($webhookUrl); ?></div>
                </div>
                
                <div class="success-box">
                    <h3>✅ Endpoint Status</h3>
                    <p>
                        <span class="status-indicator status-active"></span>
                        <strong>Active</strong> - This webhook endpoint is ready to receive POST requests from 3DCart.
                    </p>
                </div>
                
                <h3>🔧 Configuration Instructions</h3>
                <ol>
                    <li><strong>Login to 3DCart Admin:</strong> Access your 3DCart store admin panel</li>
                    <li><strong>Navigate to Settings:</strong> Go to Settings → General → Store Settings</li>
                    <li><strong>Find Webhooks Section:</strong> Look for "Webhooks" or "API Webhooks"</li>
                    <li><strong>Add New Webhook:</strong> Create a new webhook with the following settings:
                        <ul>
                            <li><strong>URL:</strong> <code><?php echo htmlspecialchars($webhookUrl); ?></code></li>
                            <li><strong>Event:</strong> Order Created/Updated</li>
                            <li><strong>Method:</strong> POST</li>
                            <li><strong>Format:</strong> JSON</li>
                        </ul>
                    </li>
                    <?php if (!empty($config['webhook']['secret_key'])): ?>
                    <li><strong>Secret Key:</strong> Configure the secret key for webhook verification (optional but recommended)</li>
                    <?php endif; ?>
                </ol>
                
                <?php if (empty($config['webhook']['secret_key'])): ?>
                <div class="warning-box">
                    <h4>⚠️ Security Notice</h4>
                    <p>No webhook secret key is configured. For enhanced security, consider setting a secret key in your configuration to verify webhook authenticity.</p>
                </div>
                <?php endif; ?>
                
                <h3>📋 Webhook Payload Example</h3>
                <p>3DCart will send a POST request with JSON payload similar to this:</p>
                <pre><code>{
  "OrderID": "12345",
  "CustomerID": "67890",
  "OrderDate": "2024-01-15 10:30:00",
  "OrderStatusID": 1,
  "OrderTotal": 99.99,
  "BillingFirstName": "John",
  "BillingLastName": "Doe",
  "BillingEmail": "john.doe@example.com",
  "BillingAddress": "123 Main St",
  "BillingCity": "Anytown",
  "BillingState": "CA",
  "BillingZipCode": "12345",
  "BillingCountry": "US",
  "OrderItemList": [
    {
      "CatalogID": "PROD001",
      "ItemName": "Sample Product",
      "Quantity": 2,
      "ItemPrice": 49.99
    }
  ]
}</code></pre>
                
                <h3>🔄 Processing Flow</h3>
                <ol>
                    <li><strong>Receive Webhook:</strong> Order data is received from 3DCart</li>
                    <li><strong>Validate Data:</strong> Order and customer data is validated</li>
                    <li><strong>Customer Lookup:</strong> Check if customer exists in NetSuite</li>
                    <li><strong>Create Customer:</strong> Create new customer if not found (if enabled)</li>
                    <li><strong>Create Sales Order:</strong> Generate sales order in NetSuite</li>
                    <li><strong>Send Notification:</strong> Email notification sent with status</li>
                </ol>
                
                <h3>📊 Testing & Monitoring</h3>
                <div style="margin: 20px 0;">
                    <a href="status.php" class="btn">Check System Status</a>
                    <a href="../logs/" class="btn">View Logs</a>
                    <a href="index.php" class="btn">Back to Dashboard</a>
                </div>
                
                <div class="info-box">
                    <h4>🧪 Testing the Webhook</h4>
                    <p>To test this webhook endpoint, you can:</p>
                    <ul>
                        <li>Create a test order in your 3DCart store</li>
                        <li>Use a tool like Postman to send a POST request with sample order data</li>
                        <li>Check the logs for processing details</li>
                        <li>Monitor the status dashboard for real-time updates</li>
                    </ul>
                </div>
                
                <div class="warning-box">
                    <h4>🔒 Security Considerations</h4>
                    <ul>
                        <li>Ensure this endpoint is accessible over HTTPS in production</li>
                        <li>Configure webhook secret verification for additional security</li>
                        <li>Monitor logs for suspicious activity</li>
                        <li>Implement rate limiting if necessary</li>
                    </ul>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>