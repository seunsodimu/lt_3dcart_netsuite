<?php

namespace Laguna\Integration\Utils;

/**
 * Validator Utility Class
 * 
 * Provides validation methods for order data, customer information, and API responses.
 */
class Validator {
    
    /**
     * Validate 3DCart order data
     */
    public static function validateThreeDCartOrder($orderData) {
        $errors = [];
        
        // Required fields
        $requiredFields = ['OrderID', 'CustomerID', 'OrderDate', 'OrderStatusID'];
        foreach ($requiredFields as $field) {
            if (!isset($orderData[$field]) || empty($orderData[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }
        
        // Validate order ID format
        if (isset($orderData['OrderID']) && !is_numeric($orderData['OrderID'])) {
            $errors[] = "OrderID must be numeric";
        }
        
        // Validate email format if present
        if (isset($orderData['BillingEmail']) && !empty($orderData['BillingEmail'])) {
            if (!filter_var($orderData['BillingEmail'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email format: {$orderData['BillingEmail']}";
            }
        }
        
        // Validate order items
        if (isset($orderData['OrderItemList']) && is_array($orderData['OrderItemList'])) {
            foreach ($orderData['OrderItemList'] as $index => $item) {
                $itemErrors = self::validateOrderItem($item, $index);
                $errors = array_merge($errors, $itemErrors);
            }
        } else {
            $errors[] = "Order must contain at least one item";
        }
        
        return $errors;
    }
    
    /**
     * Validate order item data
     */
    public static function validateOrderItem($item, $index = null) {
        $errors = [];
        $prefix = $index !== null ? "Item {$index}: " : "Item: ";
        
        $requiredFields = ['CatalogID', 'ItemName', 'Quantity', 'ItemPrice'];
        foreach ($requiredFields as $field) {
            if (!isset($item[$field]) || $item[$field] === '') {
                $errors[] = $prefix . "Missing required field: {$field}";
            }
        }
        
        // Validate numeric fields
        if (isset($item['Quantity']) && (!is_numeric($item['Quantity']) || $item['Quantity'] <= 0)) {
            $errors[] = $prefix . "Quantity must be a positive number";
        }
        
        if (isset($item['ItemPrice']) && !is_numeric($item['ItemPrice'])) {
            $errors[] = $prefix . "ItemPrice must be numeric";
        }
        
        return $errors;
    }
    
    /**
     * Validate customer data
     */
    public static function validateCustomerData($customerData) {
        $errors = [];
        
        // Required fields for customer creation
        $requiredFields = ['firstname', 'lastname', 'email'];
        foreach ($requiredFields as $field) {
            if (!isset($customerData[$field]) || empty($customerData[$field])) {
                $errors[] = "Missing required customer field: {$field}";
            }
        }
        
        // Validate email
        if (isset($customerData['email']) && !filter_var($customerData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid customer email format: {$customerData['email']}";
        }
        
        // Validate phone if present
        if (isset($customerData['phone']) && !empty($customerData['phone'])) {
            if (!preg_match('/^[\d\s\-\+\(\)\.]+$/', $customerData['phone'])) {
                $errors[] = "Invalid phone number format";
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate NetSuite response
     */
    public static function validateNetSuiteResponse($response, $expectedType = null) {
        $errors = [];
        
        if (!is_array($response) && !is_object($response)) {
            $errors[] = "Invalid response format";
            return $errors;
        }
        
        // Convert object to array for easier handling
        if (is_object($response)) {
            $response = json_decode(json_encode($response), true);
        }
        
        // Check for NetSuite error responses
        if (isset($response['error'])) {
            $errors[] = "NetSuite API Error: " . $response['error']['message'] ?? 'Unknown error';
        }
        
        if (isset($response['errors']) && is_array($response['errors'])) {
            foreach ($response['errors'] as $error) {
                $errors[] = "NetSuite Error: " . ($error['message'] ?? json_encode($error));
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file) {
        $errors = [];
        $config = require __DIR__ . '/../../config/config.php';
        $uploadConfig = $config['upload'];
        
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $errors[] = "No file uploaded";
            return $errors;
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = "File is too large";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = "File upload was interrupted";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errors[] = "No file was uploaded";
                    break;
                default:
                    $errors[] = "File upload failed";
            }
            return $errors;
        }
        
        // Check file size
        if ($file['size'] > $uploadConfig['max_file_size']) {
            $maxSizeMB = $uploadConfig['max_file_size'] / (1024 * 1024);
            $errors[] = "File size exceeds maximum allowed size of {$maxSizeMB}MB";
        }
        
        // Check file extension
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $uploadConfig['allowed_extensions'])) {
            $allowedExts = implode(', ', $uploadConfig['allowed_extensions']);
            $errors[] = "Invalid file type. Allowed types: {$allowedExts}";
        }
        
        // Check if file is actually uploaded
        if (!is_uploaded_file($file['tmp_name'])) {
            $errors[] = "Invalid file upload";
        }
        
        return $errors;
    }
    
    /**
     * Sanitize string input
     */
    public static function sanitizeString($input, $maxLength = null) {
        $sanitized = trim(strip_tags($input));
        if ($maxLength && strlen($sanitized) > $maxLength) {
            $sanitized = substr($sanitized, 0, $maxLength);
        }
        return $sanitized;
    }
    
    /**
     * Validate and sanitize email
     */
    public static function sanitizeEmail($email) {
        $email = trim(strtolower($email));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
    }
}