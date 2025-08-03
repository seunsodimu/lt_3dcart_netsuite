<?php

namespace Laguna\Integration\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Laguna\Integration\Utils\Logger;

/**
 * 3DCart API Service
 * 
 * Handles all interactions with the 3DCart REST API.
 * Documentation: https://apirest.3dcart.com/v2/getting-started/index.html
 */
class ThreeDCartService {
    private $client;
    private $credentials;
    private $logger;
    private $baseUrl;
    
    public function __construct() {
        $credentials = require __DIR__ . '/../../config/credentials.php';
        $this->credentials = $credentials['3dcart'];
        $this->logger = Logger::getInstance();
        
        $this->baseUrl = rtrim($this->credentials['store_url'], '/') . '/3dCartWebAPI/v2';
        
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'PrivateKey' => $this->credentials['private_key'],
                'Token' => $this->credentials['token'],
            ]
        ]);
    }
    
    /**
     * Test connection to 3DCart API
     */
    public function testConnection() {
        try {
            $startTime = microtime(true);
            $response = $this->client->get('/Orders', [
                'query' => ['limit' => 1]
            ]);
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('3DCart', '/Orders', 'GET', $response->getStatusCode(), $duration);
            
            return [
                'success' => true,
                'status_code' => $response->getStatusCode(),
                'response_time' => round($duration, 2) . 'ms'
            ];
        } catch (RequestException $e) {
            $this->logger->error('3DCart connection test failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : null
            ];
        }
    }
    
    /**
     * Get order by ID
     */
    public function getOrder($orderId) {
        try {
            $startTime = microtime(true);
            $response = $this->client->get("/Orders/{$orderId}");
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('3DCart', "/Orders/{$orderId}", 'GET', $response->getStatusCode(), $duration);
            
            $orderData = json_decode($response->getBody()->getContents(), true);
            
            $this->logger->info('Retrieved order from 3DCart', [
                'order_id' => $orderId,
                'customer_id' => $orderData['CustomerID'] ?? null
            ]);
            
            return $orderData;
        } catch (RequestException $e) {
            $this->logger->error('Failed to retrieve order from 3DCart', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Failed to retrieve order {$orderId}: " . $e->getMessage());
        }
    }
    
    /**
     * Get customer by ID
     */
    public function getCustomer($customerId) {
        try {
            $startTime = microtime(true);
            $response = $this->client->get("/Customers/{$customerId}");
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('3DCart', "/Customers/{$customerId}", 'GET', $response->getStatusCode(), $duration);
            
            $customerData = json_decode($response->getBody()->getContents(), true);
            
            $this->logger->info('Retrieved customer from 3DCart', [
                'customer_id' => $customerId,
                'email' => $customerData['Email'] ?? null
            ]);
            
            return $customerData;
        } catch (RequestException $e) {
            $this->logger->error('Failed to retrieve customer from 3DCart', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Failed to retrieve customer {$customerId}: " . $e->getMessage());
        }
    }
    
    /**
     * Get orders with filters
     */
    public function getOrders($filters = []) {
        try {
            $queryParams = array_merge([
                'limit' => 50,
                'offset' => 0
            ], $filters);
            
            $startTime = microtime(true);
            $response = $this->client->get('/Orders', [
                'query' => $queryParams
            ]);
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('3DCart', '/Orders', 'GET', $response->getStatusCode(), $duration);
            
            $ordersData = json_decode($response->getBody()->getContents(), true);
            
            $this->logger->info('Retrieved orders from 3DCart', [
                'count' => count($ordersData),
                'filters' => $filters
            ]);
            
            return $ordersData;
        } catch (RequestException $e) {
            $this->logger->error('Failed to retrieve orders from 3DCart', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Failed to retrieve orders: " . $e->getMessage());
        }
    }
    
    /**
     * Update order status
     */
    public function updateOrderStatus($orderId, $statusId, $comments = '') {
        try {
            $updateData = [
                'OrderStatusID' => $statusId
            ];
            
            if (!empty($comments)) {
                $updateData['InternalComments'] = $comments;
            }
            
            $startTime = microtime(true);
            $response = $this->client->put("/Orders/{$orderId}", [
                'json' => $updateData
            ]);
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('3DCart', "/Orders/{$orderId}", 'PUT', $response->getStatusCode(), $duration);
            
            $this->logger->info('Updated order status in 3DCart', [
                'order_id' => $orderId,
                'status_id' => $statusId,
                'comments' => $comments
            ]);
            
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->logger->error('Failed to update order status in 3DCart', [
                'order_id' => $orderId,
                'status_id' => $statusId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Failed to update order status: " . $e->getMessage());
        }
    }
    
    /**
     * Verify webhook signature (if applicable)
     */
    public function verifyWebhookSignature($payload, $signature, $secret = null) {
        if (!$secret) {
            $config = require __DIR__ . '/../../config/config.php';
            $secret = $config['webhook']['secret_key'];
        }
        
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Process webhook payload
     */
    public function processWebhookPayload($payload) {
        $this->logger->logWebhook('3DCart', 'order_webhook', [
            'payload_size' => strlen($payload)
        ]);
        
        try {
            $data = json_decode($payload, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON payload: ' . json_last_error_msg());
            }
            
            // Validate required webhook fields
            if (!isset($data['OrderID'])) {
                throw new \Exception('Missing OrderID in webhook payload');
            }
            
            $this->logger->info('Processed 3DCart webhook', [
                'order_id' => $data['OrderID'],
                'event_type' => $data['EventType'] ?? 'unknown'
            ]);
            
            return $data;
        } catch (\Exception $e) {
            $this->logger->error('Failed to process 3DCart webhook payload', [
                'error' => $e->getMessage(),
                'payload' => substr($payload, 0, 500) // Log first 500 chars for debugging
            ]);
            throw $e;
        }
    }
    
    /**
     * Get order statuses
     */
    public function getOrderStatuses() {
        try {
            $response = $this->client->get('/OrderStatuses');
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->logger->error('Failed to retrieve order statuses', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}