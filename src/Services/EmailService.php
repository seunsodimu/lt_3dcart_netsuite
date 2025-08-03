<?php

namespace Laguna\Integration\Services;

use SendGrid\Mail\Mail;
use SendGrid\Mail\To;
use Laguna\Integration\Utils\Logger;

/**
 * Email Service using SendGrid
 * 
 * Handles all email notifications for the integration system.
 * Documentation: https://www.twilio.com/docs/sendgrid/api-reference
 */
class EmailService {
    private $sendgrid;
    private $credentials;
    private $config;
    private $logger;
    
    public function __construct() {
        $credentials = require __DIR__ . '/../../config/credentials.php';
        $config = require __DIR__ . '/../../config/config.php';
        
        $this->credentials = $credentials['sendgrid'];
        $this->config = $config['notifications'];
        $this->logger = Logger::getInstance();
        
        $this->sendgrid = new \SendGrid($this->credentials['api_key']);
    }
    
    /**
     * Test SendGrid connection
     */
    public function testConnection() {
        try {
            // Test by attempting to get API key info
            $response = $this->sendgrid->client->api_keys()->get();
            
            $this->logger->info('SendGrid connection test successful', [
                'status_code' => $response->statusCode()
            ]);
            
            return [
                'success' => true,
                'status_code' => $response->statusCode(),
                'service' => 'SendGrid'
            ];
        } catch (\Exception $e) {
            $this->logger->error('SendGrid connection test failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'service' => 'SendGrid'
            ];
        }
    }
    
    /**
     * Send order processing notification
     */
    public function sendOrderNotification($orderId, $status, $details = []) {
        if (!$this->config['enabled']) {
            $this->logger->info('Email notifications disabled, skipping order notification');
            return true;
        }
        
        $subject = $this->config['subject_prefix'] . "Order {$orderId} - {$status}";
        $content = $this->buildOrderNotificationContent($orderId, $status, $details);
        
        return $this->sendEmail($subject, $content, $this->config['to_emails']);
    }
    
    /**
     * Send error notification
     */
    public function sendErrorNotification($error, $context = []) {
        if (!$this->config['enabled']) {
            $this->logger->info('Email notifications disabled, skipping error notification');
            return true;
        }
        
        $subject = $this->config['subject_prefix'] . "Integration Error";
        $content = $this->buildErrorNotificationContent($error, $context);
        
        return $this->sendEmail($subject, $content, $this->config['to_emails']);
    }
    
    /**
     * Send daily summary notification
     */
    public function sendDailySummary($summary) {
        if (!$this->config['enabled']) {
            $this->logger->info('Email notifications disabled, skipping daily summary');
            return true;
        }
        
        $subject = $this->config['subject_prefix'] . "Daily Integration Summary - " . date('Y-m-d');
        $content = $this->buildDailySummaryContent($summary);
        
        return $this->sendEmail($subject, $content, $this->config['to_emails']);
    }
    
    /**
     * Send connection status alert
     */
    public function sendConnectionAlert($service, $status, $details = []) {
        if (!$this->config['enabled']) {
            $this->logger->info('Email notifications disabled, skipping connection alert');
            return true;
        }
        
        $statusText = $status ? 'Connected' : 'Connection Failed';
        $subject = $this->config['subject_prefix'] . "{$service} - {$statusText}";
        $content = $this->buildConnectionAlertContent($service, $status, $details);
        
        return $this->sendEmail($subject, $content, $this->config['to_emails']);
    }
    
    /**
     * Send generic email
     */
    private function sendEmail($subject, $content, $recipients) {
        try {
            $email = new Mail();
            
            // Set from address
            $email->setFrom(
                $this->credentials['from_email'],
                $this->credentials['from_name']
            );
            
            // Set subject
            $email->setSubject($subject);
            
            // Add recipients
            foreach ($recipients as $recipient) {
                $email->addTo(new To(trim($recipient)));
            }
            
            // Set content
            $email->addContent("text/html", $content);
            
            // Send email
            $response = $this->sendgrid->send($email);
            
            $this->logger->info('Email sent successfully', [
                'subject' => $subject,
                'recipients' => $recipients,
                'status_code' => $response->statusCode()
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send email', [
                'subject' => $subject,
                'recipients' => $recipients,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Build order notification email content
     */
    private function buildOrderNotificationContent($orderId, $status, $details) {
        $timestamp = date('Y-m-d H:i:s');
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .content { margin-bottom: 20px; }
                .details { background-color: #f8f9fa; padding: 15px; border-radius: 5px; }
                .status-success { color: #28a745; font-weight: bold; }
                .status-error { color: #dc3545; font-weight: bold; }
                .status-warning { color: #ffc107; font-weight: bold; }
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>3DCart to NetSuite Integration - Order Update</h2>
                <p><strong>Timestamp:</strong> {$timestamp}</p>
            </div>
            
            <div class='content'>
                <h3>Order Information</h3>
                <p><strong>Order ID:</strong> {$orderId}</p>
                <p><strong>Status:</strong> <span class='status-" . $this->getStatusClass($status) . "'>{$status}</span></p>
            </div>";
        
        if (!empty($details)) {
            $html .= "<div class='details'>
                <h3>Details</h3>
                <table>";
            
            foreach ($details as $key => $value) {
                $html .= "<tr><th>" . htmlspecialchars($key) . "</th><td>" . htmlspecialchars($value) . "</td></tr>";
            }
            
            $html .= "</table></div>";
        }
        
        $html .= "
            <div class='content'>
                <p><em>This is an automated notification from the 3DCart to NetSuite integration system.</em></p>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Build error notification email content
     */
    private function buildErrorNotificationContent($error, $context) {
        $timestamp = date('Y-m-d H:i:s');
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { background-color: #f8d7da; padding: 20px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
                .error { color: #721c24; background-color: #f8d7da; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
                .context { background-color: #f8f9fa; padding: 15px; border-radius: 5px; }
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>🚨 Integration Error Alert</h2>
                <p><strong>Timestamp:</strong> {$timestamp}</p>
            </div>
            
            <div class='error'>
                <h3>Error Details</h3>
                <p><strong>Error:</strong> " . htmlspecialchars($error) . "</p>
            </div>";
        
        if (!empty($context)) {
            $html .= "<div class='context'>
                <h3>Context Information</h3>
                <table>";
            
            foreach ($context as $key => $value) {
                $displayValue = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value;
                $html .= "<tr><th>" . htmlspecialchars($key) . "</th><td><pre>" . htmlspecialchars($displayValue) . "</pre></td></tr>";
            }
            
            $html .= "</table></div>";
        }
        
        $html .= "
            <div style='margin-top: 20px; padding: 15px; background-color: #fff3cd; border-radius: 5px;'>
                <p><strong>Action Required:</strong> Please review the error and take appropriate action to resolve the issue.</p>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Build daily summary email content
     */
    private function buildDailySummaryContent($summary) {
        $date = date('Y-m-d');
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { background-color: #d4edda; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .summary-box { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
                .metric { display: inline-block; margin: 10px 20px 10px 0; }
                .metric-value { font-size: 24px; font-weight: bold; color: #007bff; }
                .metric-label { font-size: 14px; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>📊 Daily Integration Summary - {$date}</h2>
            </div>
            
            <div class='summary-box'>
                <h3>Order Processing Statistics</h3>
                <div class='metric'>
                    <div class='metric-value'>" . ($summary['orders_processed'] ?? 0) . "</div>
                    <div class='metric-label'>Orders Processed</div>
                </div>
                <div class='metric'>
                    <div class='metric-value'>" . ($summary['orders_successful'] ?? 0) . "</div>
                    <div class='metric-label'>Successful</div>
                </div>
                <div class='metric'>
                    <div class='metric-value'>" . ($summary['orders_failed'] ?? 0) . "</div>
                    <div class='metric-label'>Failed</div>
                </div>
            </div>
            
            <div class='summary-box'>
                <h3>Customer Management</h3>
                <div class='metric'>
                    <div class='metric-value'>" . ($summary['customers_created'] ?? 0) . "</div>
                    <div class='metric-label'>New Customers Created</div>
                </div>
                <div class='metric'>
                    <div class='metric-value'>" . ($summary['customers_existing'] ?? 0) . "</div>
                    <div class='metric-label'>Existing Customers</div>
                </div>
            </div>";
        
        if (isset($summary['errors']) && !empty($summary['errors'])) {
            $html .= "<div class='summary-box' style='background-color: #f8d7da;'>
                <h3>Errors Encountered</h3>
                <ul>";
            
            foreach ($summary['errors'] as $error) {
                $html .= "<li>" . htmlspecialchars($error) . "</li>";
            }
            
            $html .= "</ul></div>";
        }
        
        $html .= "
            <div style='margin-top: 20px; font-size: 12px; color: #6c757d;'>
                <p>This summary covers the period from 00:00 to 23:59 on {$date}.</p>
                <p>Generated automatically by the 3DCart to NetSuite integration system.</p>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Build connection alert email content
     */
    private function buildConnectionAlertContent($service, $status, $details) {
        $timestamp = date('Y-m-d H:i:s');
        $statusText = $status ? 'Connected' : 'Connection Failed';
        $statusColor = $status ? '#28a745' : '#dc3545';
        $bgColor = $status ? '#d4edda' : '#f8d7da';
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { background-color: {$bgColor}; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .status { color: {$statusColor}; font-weight: bold; font-size: 18px; }
                .details { background-color: #f8f9fa; padding: 15px; border-radius: 5px; }
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>🔗 Connection Status Alert</h2>
                <p><strong>Service:</strong> {$service}</p>
                <p><strong>Status:</strong> <span class='status'>{$statusText}</span></p>
                <p><strong>Timestamp:</strong> {$timestamp}</p>
            </div>";
        
        if (!empty($details)) {
            $html .= "<div class='details'>
                <h3>Connection Details</h3>
                <table>";
            
            foreach ($details as $key => $value) {
                $html .= "<tr><th>" . htmlspecialchars($key) . "</th><td>" . htmlspecialchars($value) . "</td></tr>";
            }
            
            $html .= "</table></div>";
        }
        
        if (!$status) {
            $html .= "
            <div style='margin-top: 20px; padding: 15px; background-color: #fff3cd; border-radius: 5px;'>
                <p><strong>Action Required:</strong> The {$service} connection has failed. Please check your credentials and network connectivity.</p>
            </div>";
        }
        
        $html .= "</body></html>";
        
        return $html;
    }
    
    /**
     * Get CSS class for status
     */
    private function getStatusClass($status) {
        $status = strtolower($status);
        
        if (in_array($status, ['success', 'completed', 'processed'])) {
            return 'success';
        } elseif (in_array($status, ['error', 'failed', 'rejected'])) {
            return 'error';
        } else {
            return 'warning';
        }
    }
}