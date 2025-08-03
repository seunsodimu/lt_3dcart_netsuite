<?php

namespace Laguna\Integration\Controllers;

use Laguna\Integration\Services\ThreeDCartService;
use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Services\EmailService;
use Laguna\Integration\Models\Order;
use Laguna\Integration\Models\Customer;
use Laguna\Integration\Utils\Logger;

/**
 * Webhook Controller
 * 
 * Handles incoming webhooks from 3DCart and processes orders.
 */
class WebhookController {
    private $threeDCartService;
    private $netSuiteService;
    private $emailService;
    private $logger;
    private $config;
    
    public function __construct() {
        $this->threeDCartService = new ThreeDCartService();
        $this->netSuiteService = new NetSuiteService();
        $this->emailService = new EmailService();
        $this->logger = Logger::getInstance();
        $this->config = require __DIR__ . '/../../config/config.php';
    }
    
    /**
     * Handle incoming webhook from 3DCart
     */
    public function handleWebhook() {
        try {
            // Get raw POST data
            $rawPayload = file_get_contents('php://input');
            
            if (empty($rawPayload)) {
                $this->respondWithError('Empty payload', 400);
                return;
            }
            
            // Verify webhook signature if configured
            $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
            if (!empty($this->config['webhook']['secret_key']) && !empty($signature)) {
                if (!$this->threeDCartService->verifyWebhookSignature($rawPayload, $signature)) {
                    $this->logger->warning('Invalid webhook signature', [
                        'signature' => $signature,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                    $this->respondWithError('Invalid signature', 401);
                    return;
                }
            }
            
            // Process webhook payload
            $webhookData = $this->threeDCartService->processWebhookPayload($rawPayload);
            
            // Extract order ID
            $orderId = $webhookData['OrderID'] ?? null;
            if (!$orderId) {
                $this->respondWithError('Missing OrderID in webhook', 400);
                return;
            }
            
            $this->logger->info('Processing webhook for order', ['order_id' => $orderId]);
            
            // Process the order
            $result = $this->processOrder($orderId);
            
            if ($result['success']) {
                $this->respondWithSuccess('Order processed successfully', $result);
            } else {
                $this->respondWithError($result['error'], 500);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->emailService->sendErrorNotification(
                'Webhook processing failed: ' . $e->getMessage(),
                ['order_id' => $orderId ?? 'unknown']
            );
            
            $this->respondWithError('Internal server error', 500);
        }
    }
    
    /**
     * Process a single order
     */
    public function processOrder($orderId, $retryCount = 0) {
        $maxRetries = $this->config['order_processing']['retry_attempts'];
        
        try {
            $this->logger->logOrderEvent($orderId, 'processing_started', [
                'retry_count' => $retryCount
            ]);
            
            // Get order data from 3DCart
            $orderData = $this->threeDCartService->getOrder($orderId);
            $order = new Order($orderData);
            
            // Validate order data
            $validationErrors = $order->validate();
            if (!empty($validationErrors)) {
                throw new \Exception('Order validation failed: ' . implode(', ', $validationErrors));
            }
            
            // Get or create customer in NetSuite
            $customer = Customer::fromOrderData($orderData);
            $netSuiteCustomerId = $this->getOrCreateCustomer($customer);
            
            // Check if order already exists in NetSuite
            $existingOrder = $this->netSuiteService->getSalesOrderByExternalId('3DCART_' . $orderId);
            if ($existingOrder) {
                $this->logger->info('Order already exists in NetSuite', [
                    'order_id' => $orderId,
                    'netsuite_order_id' => $existingOrder['id']
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Order already exists',
                    'netsuite_order_id' => $existingOrder['id']
                ];
            }
            
            // Create sales order in NetSuite
            $netSuiteOrder = $this->netSuiteService->createSalesOrder($orderData, $netSuiteCustomerId);
            
            $this->logger->logOrderEvent($orderId, 'processing_completed', [
                'netsuite_order_id' => $netSuiteOrder['id'],
                'customer_id' => $netSuiteCustomerId
            ]);
            
            // Send success notification
            $this->emailService->sendOrderNotification($orderId, 'Successfully Processed', [
                'NetSuite Order ID' => $netSuiteOrder['id'],
                'Customer ID' => $netSuiteCustomerId,
                'Order Total' => '$' . number_format($order->getTotal(), 2),
                'Items Count' => count($order->getItems())
            ]);
            
            return [
                'success' => true,
                'message' => 'Order processed successfully',
                'netsuite_order_id' => $netSuiteOrder['id'],
                'customer_id' => $netSuiteCustomerId
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Order processing failed', [
                'order_id' => $orderId,
                'retry_count' => $retryCount,
                'error' => $e->getMessage()
            ]);
            
            // Retry logic
            if ($retryCount < $maxRetries) {
                $this->logger->info('Retrying order processing', [
                    'order_id' => $orderId,
                    'retry_count' => $retryCount + 1
                ]);
                
                sleep($this->config['order_processing']['retry_delay']);
                return $this->processOrder($orderId, $retryCount + 1);
            }
            
            // Send error notification
            $this->emailService->sendOrderNotification($orderId, 'Processing Failed', [
                'Error' => $e->getMessage(),
                'Retry Count' => $retryCount,
                'Max Retries' => $maxRetries
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retry_count' => $retryCount
            ];
        }
    }
    
    /**
     * Get existing customer or create new one in NetSuite
     */
    private function getOrCreateCustomer(Customer $customer) {
        $email = $customer->getEmail();
        
        if (empty($email)) {
            throw new \Exception('Customer email is required');
        }
        
        // Try to find existing customer
        $existingCustomer = $this->netSuiteService->findCustomerByEmail($email);
        
        if ($existingCustomer) {
            $this->logger->info('Using existing customer', [
                'email' => $email,
                'customer_id' => $existingCustomer['id']
            ]);
            return $existingCustomer['id'];
        }
        
        // Create new customer if auto-creation is enabled
        if ($this->config['order_processing']['auto_create_customers']) {
            $validationErrors = $customer->validate();
            if (!empty($validationErrors)) {
                throw new \Exception('Customer validation failed: ' . implode(', ', $validationErrors));
            }
            
            $newCustomer = $this->netSuiteService->createCustomer($customer->toNetSuiteFormat());
            
            $this->logger->info('Created new customer', [
                'email' => $email,
                'customer_id' => $newCustomer['id']
            ]);
            
            return $newCustomer['id'];
        } else {
            throw new \Exception("Customer not found and auto-creation is disabled: {$email}");
        }
    }
    
    /**
     * Respond with success
     */
    private function respondWithSuccess($message, $data = []) {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ]);
    }
    
    /**
     * Respond with error
     */
    private function respondWithError($message, $statusCode = 400) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ]);
    }
    
    /**
     * Process multiple orders (for batch processing)
     */
    public function processBatchOrders($orderIds) {
        $results = [];
        $successCount = 0;
        $failureCount = 0;
        
        foreach ($orderIds as $orderId) {
            $result = $this->processOrder($orderId);
            $results[$orderId] = $result;
            
            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }
        
        $this->logger->info('Batch processing completed', [
            'total_orders' => count($orderIds),
            'successful' => $successCount,
            'failed' => $failureCount
        ]);
        
        // Send batch summary notification
        $this->emailService->sendOrderNotification('Batch', 'Batch Processing Completed', [
            'Total Orders' => count($orderIds),
            'Successful' => $successCount,
            'Failed' => $failureCount,
            'Success Rate' => round(($successCount / count($orderIds)) * 100, 2) . '%'
        ]);
        
        return [
            'success' => $failureCount === 0,
            'results' => $results,
            'summary' => [
                'total' => count($orderIds),
                'successful' => $successCount,
                'failed' => $failureCount
            ]
        ];
    }
}