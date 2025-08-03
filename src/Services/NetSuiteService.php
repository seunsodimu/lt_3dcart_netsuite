<?php

namespace Laguna\Integration\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Laguna\Integration\Utils\Logger;

/**
 * NetSuite REST API Service
 * 
 * Handles all interactions with the NetSuite SuiteTalk REST API.
 * Documentation: https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_1529089601.html
 */
class NetSuiteService {
    private $client;
    private $credentials;
    private $logger;
    private $baseUrl;
    
    public function __construct() {
        $credentials = require __DIR__ . '/../../config/credentials.php';
        $this->credentials = $credentials['netsuite'];
        $this->logger = Logger::getInstance();
        
        $this->baseUrl = rtrim($this->credentials['base_url'], '/') . '/services/rest/record/' . $this->credentials['rest_api_version'];
        
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 60,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
    }
    
    /**
     * Generate OAuth 1.0 signature for NetSuite API
     */
    private function generateOAuthHeader($method, $url, $params = []) {
        $oauthParams = [
            'oauth_consumer_key' => $this->credentials['consumer_key'],
            'oauth_token' => $this->credentials['token_id'],
            'oauth_signature_method' => 'HMAC-SHA256',
            'oauth_timestamp' => time(),
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_version' => '1.0'
        ];
        
        // Merge OAuth params with request params
        $allParams = array_merge($oauthParams, $params);
        ksort($allParams);
        
        // Create parameter string
        $paramString = http_build_query($allParams, '', '&', PHP_QUERY_RFC3986);
        
        // Create signature base string
        $baseString = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);
        
        // Create signing key
        $signingKey = rawurlencode($this->credentials['consumer_secret']) . '&' . rawurlencode($this->credentials['token_secret']);
        
        // Generate signature
        $signature = base64_encode(hash_hmac('sha256', $baseString, $signingKey, true));
        $oauthParams['oauth_signature'] = $signature;
        
        // Build authorization header
        $authHeader = 'OAuth ';
        $authParts = [];
        foreach ($oauthParams as $key => $value) {
            $authParts[] = $key . '="' . rawurlencode($value) . '"';
        }
        $authHeader .= implode(', ', $authParts);
        
        return $authHeader;
    }
    
    /**
     * Make authenticated request to NetSuite
     */
    private function makeRequest($method, $endpoint, $data = null, $params = []) {
        $url = $this->baseUrl . $endpoint;
        $authHeader = $this->generateOAuthHeader($method, $url, $params);
        
        $options = [
            'headers' => [
                'Authorization' => $authHeader,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ];
        
        if ($data) {
            $options['json'] = $data;
        }
        
        if ($params) {
            $options['query'] = $params;
        }
        
        return $this->client->request($method, $endpoint, $options);
    }
    
    /**
     * Test connection to NetSuite API
     */
    public function testConnection() {
        try {
            $startTime = microtime(true);
            $response = $this->makeRequest('GET', '/customer', null, ['limit' => 1]);
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('NetSuite', '/customer', 'GET', $response->getStatusCode(), $duration);
            
            return [
                'success' => true,
                'status_code' => $response->getStatusCode(),
                'response_time' => round($duration, 2) . 'ms'
            ];
        } catch (RequestException $e) {
            $this->logger->error('NetSuite connection test failed', [
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
     * Search for customer by email
     */
    public function findCustomerByEmail($email) {
        try {
            $startTime = microtime(true);
            $response = $this->makeRequest('GET', '/customer', null, [
                'q' => "email IS '{$email}'",
                'limit' => 1
            ]);
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('NetSuite', '/customer', 'GET', $response->getStatusCode(), $duration);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['items']) && count($data['items']) > 0) {
                $customer = $data['items'][0];
                $this->logger->info('Found existing customer in NetSuite', [
                    'email' => $email,
                    'customer_id' => $customer['id']
                ]);
                return $customer;
            }
            
            $this->logger->info('Customer not found in NetSuite', ['email' => $email]);
            return null;
        } catch (RequestException $e) {
            $this->logger->error('Failed to search for customer in NetSuite', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Failed to search for customer: " . $e->getMessage());
        }
    }
    
    /**
     * Create customer in NetSuite
     */
    public function createCustomer($customerData) {
        try {
            // Map 3DCart customer data to NetSuite format
            $netsuiteCustomer = [
                'companyName' => $customerData['company'] ?? null,
                'firstName' => $customerData['firstname'],
                'lastName' => $customerData['lastname'],
                'email' => $customerData['email'],
                'phone' => $customerData['phone'] ?? null,
                'isPerson' => true,
                'subsidiary' => ['id' => 1], // Default subsidiary - adjust as needed
            ];
            
            // Add billing address if available
            if (isset($customerData['billing_address'])) {
                $netsuiteCustomer['defaultAddress'] = $this->formatAddress($customerData['billing_address']);
            }
            
            $startTime = microtime(true);
            $response = $this->makeRequest('POST', '/customer', $netsuiteCustomer);
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('NetSuite', '/customer', 'POST', $response->getStatusCode(), $duration);
            
            $createdCustomer = json_decode($response->getBody()->getContents(), true);
            
            $this->logger->info('Created customer in NetSuite', [
                'email' => $customerData['email'],
                'customer_id' => $createdCustomer['id'],
                'name' => $customerData['firstname'] . ' ' . $customerData['lastname']
            ]);
            
            return $createdCustomer;
        } catch (RequestException $e) {
            $this->logger->error('Failed to create customer in NetSuite', [
                'customer_data' => $customerData,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Failed to create customer: " . $e->getMessage());
        }
    }
    
    /**
     * Create sales order in NetSuite
     */
    public function createSalesOrder($orderData, $customerId) {
        try {
            // Map 3DCart order data to NetSuite sales order format
            $salesOrder = [
                'entity' => ['id' => $customerId],
                'tranDate' => date('Y-m-d', strtotime($orderData['OrderDate'])),
                'orderStatus' => 'A', // Pending Approval
                'subsidiary' => ['id' => 1], // Default subsidiary - adjust as needed
                'location' => ['id' => 1], // Default location - adjust as needed
                'memo' => 'Order imported from 3DCart - Order #' . $orderData['OrderID'],
                'externalId' => '3DCART_' . $orderData['OrderID'],
            ];
            
            // Add shipping address if available
            if (isset($orderData['ShippingAddress'])) {
                $salesOrder['shipAddress'] = $this->formatAddress($orderData['ShippingAddress']);
            }
            
            // Add billing address if available
            if (isset($orderData['BillingAddress'])) {
                $salesOrder['billAddress'] = $this->formatAddress($orderData['BillingAddress']);
            }
            
            // Add order items
            $items = [];
            if (isset($orderData['OrderItemList']) && is_array($orderData['OrderItemList'])) {
                foreach ($orderData['OrderItemList'] as $item) {
                    $items[] = [
                        'item' => ['id' => $this->findOrCreateItem($item)],
                        'quantity' => (float)$item['Quantity'],
                        'rate' => (float)$item['ItemPrice'],
                        'description' => $item['ItemName'],
                    ];
                }
            }
            $salesOrder['item'] = $items;
            
            $startTime = microtime(true);
            $response = $this->makeRequest('POST', '/salesorder', $salesOrder);
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('NetSuite', '/salesorder', 'POST', $response->getStatusCode(), $duration);
            
            $createdOrder = json_decode($response->getBody()->getContents(), true);
            
            $this->logger->info('Created sales order in NetSuite', [
                'threedcart_order_id' => $orderData['OrderID'],
                'netsuite_order_id' => $createdOrder['id'],
                'customer_id' => $customerId,
                'total_items' => count($items)
            ]);
            
            return $createdOrder;
        } catch (RequestException $e) {
            $this->logger->error('Failed to create sales order in NetSuite', [
                'threedcart_order_id' => $orderData['OrderID'],
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Failed to create sales order: " . $e->getMessage());
        }
    }
    
    /**
     * Find or create item in NetSuite
     */
    private function findOrCreateItem($itemData) {
        try {
            // First, try to find existing item by SKU or name
            $response = $this->makeRequest('GET', '/item', null, [
                'q' => "itemId IS '{$itemData['CatalogID']}'",
                'limit' => 1
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['items']) && count($data['items']) > 0) {
                return $data['items'][0]['id'];
            }
            
            // If item doesn't exist, create it as a non-inventory item
            $newItem = [
                'itemId' => $itemData['CatalogID'],
                'displayName' => $itemData['ItemName'],
                'description' => $itemData['ItemName'],
                'basePrice' => (float)$itemData['ItemPrice'],
                'includeChildren' => false,
                'isInactive' => false,
                'subsidiary' => [['id' => 1]], // Default subsidiary
            ];
            
            $response = $this->makeRequest('POST', '/noninventoryitem', $newItem);
            $createdItem = json_decode($response->getBody()->getContents(), true);
            
            $this->logger->info('Created new item in NetSuite', [
                'item_id' => $itemData['CatalogID'],
                'name' => $itemData['ItemName'],
                'netsuite_id' => $createdItem['id']
            ]);
            
            return $createdItem['id'];
        } catch (RequestException $e) {
            $this->logger->warning('Failed to find/create item, using default', [
                'item_id' => $itemData['CatalogID'],
                'error' => $e->getMessage()
            ]);
            
            // Return a default item ID if creation fails
            // You should configure this with an actual default item in your NetSuite
            return 1; // Replace with your default item ID
        }
    }
    
    /**
     * Format address for NetSuite
     */
    private function formatAddress($addressData) {
        return [
            'addr1' => $addressData['Address1'] ?? '',
            'addr2' => $addressData['Address2'] ?? '',
            'city' => $addressData['City'] ?? '',
            'state' => $addressData['State'] ?? '',
            'zip' => $addressData['PostalCode'] ?? '',
            'country' => $addressData['Country'] ?? 'US',
        ];
    }
    
    /**
     * Get sales order by external ID
     */
    public function getSalesOrderByExternalId($externalId) {
        try {
            $response = $this->makeRequest('GET', '/salesorder', null, [
                'q' => "externalId IS '{$externalId}'",
                'limit' => 1
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['items']) && count($data['items']) > 0) {
                return $data['items'][0];
            }
            
            return null;
        } catch (RequestException $e) {
            $this->logger->error('Failed to find sales order by external ID', [
                'external_id' => $externalId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}