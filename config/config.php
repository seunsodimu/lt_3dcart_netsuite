<?php
/**
 * Main Configuration File
 * 
 * This file contains the main configuration settings for the 3DCart to NetSuite integration.
 */

// Load environment variables if .env file exists
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Load credentials
require_once __DIR__ . '/credentials.php';

return [
    // Application Settings
    'app' => [
        'name' => '3DCart NetSuite Integration',
        'version' => '1.0.0',
        'timezone' => 'America/New_York',
        'debug' => $_ENV['APP_DEBUG'] ?? false,
    ],

    // Logging Configuration
    'logging' => [
        'enabled' => true,
        'level' => 'info', // debug, info, warning, error
        'file' => __DIR__ . '/../logs/app.log',
        'max_files' => 30,
    ],

    // Database Configuration (optional - for advanced logging)
    'database' => [
        'enabled' => false,
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => $_ENV['DB_PORT'] ?? 3306,
        'database' => $_ENV['DB_NAME'] ?? 'integration_db',
        'username' => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASS'] ?? '',
        'charset' => 'utf8mb4',
    ],

    // File Upload Settings
    'upload' => [
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'allowed_extensions' => ['csv', 'xlsx', 'xls'],
        'upload_path' => __DIR__ . '/../uploads/',
    ],

    // Email Notification Settings
    'notifications' => [
        'enabled' => true,
        'from_email' => $_ENV['NOTIFICATION_FROM_EMAIL'] ?? 'noreply@yourdomain.com',
        'from_name' => $_ENV['NOTIFICATION_FROM_NAME'] ?? '3DCart Integration',
        'to_emails' => explode(',', $_ENV['NOTIFICATION_TO_EMAILS'] ?? 'admin@yourdomain.com'),
        'subject_prefix' => '[3DCart Integration] ',
    ],

    // Webhook Settings
    'webhook' => [
        'secret_key' => $_ENV['WEBHOOK_SECRET'] ?? 'your-webhook-secret-key',
        'timeout' => 30, // seconds
    ],

    // Order Processing Settings
    'order_processing' => [
        'auto_create_customers' => true,
        'default_customer_type' => 'individual',
        'default_payment_terms' => 'Net 30',
        'retry_attempts' => 3,
        'retry_delay' => 5, // seconds
    ],

    // API Rate Limiting
    'rate_limiting' => [
        'netsuite_requests_per_minute' => 10,
        'threedcart_requests_per_minute' => 60,
    ],
];