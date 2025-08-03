<?php
/**
 * Main Entry Point
 * 
 * This is the main entry point for the 3DCart to NetSuite integration system.
 * It provides a simple dashboard with links to all available functionality.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Set timezone
date_default_timezone_set('America/New_York');

$config = require __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $config['app']['name']; ?> - Dashboard</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 1200px;
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
        .header h1 {
            margin: 0;
            font-size: 2.5em;
            font-weight: 300;
        }
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 1.1em;
        }
        .content {
            padding: 40px;
        }
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        .feature-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .feature-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .feature-icon {
            font-size: 3em;
            margin-bottom: 15px;
        }
        .feature-card h3 {
            margin: 0 0 15px 0;
            color: #333;
        }
        .feature-card p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.2s;
            font-weight: 500;
        }
        .btn:hover {
            background: #5a6fd8;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .status-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin-top: 30px;
        }
        .status-section h3 {
            margin-top: 0;
            color: #333;
        }
        .quick-links {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }
        .info-box h4 {
            margin: 0 0 10px 0;
            color: #1976d2;
        }
        .footer {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo $config['app']['name']; ?></h1>
            <p>Automated order processing and customer management</p>
        </div>
        
        <div class="content">
            <div class="features">
                <div class="feature-card">
                    <div class="feature-icon">🔗</div>
                    <h3>Webhook Integration</h3>
                    <p>Automatically receives and processes orders from 3DCart in real-time. Orders are validated, customers are created or matched, and sales orders are generated in NetSuite.</p>
                    <a href="webhook.php" class="btn">View Webhook Endpoint</a>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Connection Status</h3>
                    <p>Monitor the health and connectivity of all integrated services including 3DCart, NetSuite, and SendGrid. Get real-time status updates and performance metrics.</p>
                    <a href="status.php" class="btn">Check Status</a>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📤</div>
                    <h3>Manual Upload</h3>
                    <p>Upload orders manually using CSV or Excel files. Perfect for bulk imports, historical data migration, or processing orders from other sources.</p>
                    <a href="upload.php" class="btn">Upload Orders</a>
                </div>
            </div>
            
            <div class="info-box">
                <h4>🚀 Getting Started</h4>
                <p>
                    <strong>New to the system?</strong> Start by checking the connection status to ensure all services are properly configured. 
                    Then review the documentation for webhook setup and API credentials configuration.
                </p>
            </div>
            
            <div class="status-section">
                <h3>Quick Actions</h3>
                <div class="quick-links">
                    <a href="status.php?format=json" class="btn btn-secondary">API Status (JSON)</a>
                    <a href="../docs/SETUP.md" class="btn btn-secondary">Setup Guide</a>
                    <a href="../docs/API_CREDENTIALS.md" class="btn btn-secondary">API Credentials</a>
                    <a href="../logs/" class="btn btn-secondary">View Logs</a>
                </div>
            </div>
            
            <div class="info-box">
                <h4>📋 System Information</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li><strong>Version:</strong> <?php echo $config['app']['version']; ?></li>
                    <li><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></li>
                    <li><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s T'); ?></li>
                    <li><strong>Timezone:</strong> <?php echo date_default_timezone_get(); ?></li>
                    <li><strong>Debug Mode:</strong> <?php echo $config['app']['debug'] ? 'Enabled' : 'Disabled'; ?></li>
                </ul>
            </div>
            
            <div class="info-box">
                <h4>🔧 Configuration Status</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li><strong>Credentials File:</strong> <?php echo file_exists(__DIR__ . '/../config/credentials.php') ? '✅ Configured' : '❌ Missing'; ?></li>
                    <li><strong>Logs Directory:</strong> <?php echo is_writable(__DIR__ . '/../logs') ? '✅ Writable' : '❌ Not writable'; ?></li>
                    <li><strong>Uploads Directory:</strong> <?php echo is_writable(__DIR__ . '/../uploads') ? '✅ Writable' : '❌ Not writable'; ?></li>
                    <li><strong>Email Notifications:</strong> <?php echo $config['notifications']['enabled'] ? '✅ Enabled' : '❌ Disabled'; ?></li>
                </ul>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> 3DCart to NetSuite Integration System. Built with PHP.</p>
        </div>
    </div>
</body>
</html>