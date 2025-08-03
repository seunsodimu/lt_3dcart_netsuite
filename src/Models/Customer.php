<?php

namespace Laguna\Integration\Models;

use Laguna\Integration\Utils\Validator;

/**
 * Customer Model
 * 
 * Represents a customer from 3DCart and provides methods for data transformation
 * and validation for NetSuite integration.
 */
class Customer {
    private $data;
    
    public function __construct($customerData) {
        $this->data = $customerData;
    }
    
    /**
     * Get customer ID
     */
    public function getId() {
        return $this->data['id'] ?? $this->data['CustomerID'] ?? null;
    }
    
    /**
     * Get customer email
     */
    public function getEmail() {
        return $this->data['email'] ?? $this->data['Email'] ?? '';
    }
    
    /**
     * Get first name
     */
    public function getFirstName() {
        return $this->data['firstname'] ?? $this->data['FirstName'] ?? '';
    }
    
    /**
     * Get last name
     */
    public function getLastName() {
        return $this->data['lastname'] ?? $this->data['LastName'] ?? '';
    }
    
    /**
     * Get full name
     */
    public function getFullName() {
        return trim($this->getFirstName() . ' ' . $this->getLastName());
    }
    
    /**
     * Get company name
     */
    public function getCompany() {
        return $this->data['company'] ?? $this->data['Company'] ?? '';
    }
    
    /**
     * Get phone number
     */
    public function getPhone() {
        return $this->data['phone'] ?? $this->data['Phone'] ?? '';
    }
    
    /**
     * Get billing address
     */
    public function getBillingAddress() {
        return $this->data['billing_address'] ?? [];
    }
    
    /**
     * Get shipping address
     */
    public function getShippingAddress() {
        return $this->data['shipping_address'] ?? $this->getBillingAddress();
    }
    
    /**
     * Get customer type (individual or company)
     */
    public function getCustomerType() {
        return !empty($this->getCompany()) ? 'company' : 'individual';
    }
    
    /**
     * Get raw customer data
     */
    public function getRawData() {
        return $this->data;
    }
    
    /**
     * Validate customer data
     */
    public function validate() {
        return Validator::validateCustomerData($this->data);
    }
    
    /**
     * Convert to NetSuite customer format
     */
    public function toNetSuiteFormat() {
        $netsuiteCustomer = [
            'firstName' => $this->getFirstName(),
            'lastName' => $this->getLastName(),
            'email' => $this->getEmail(),
            'isPerson' => $this->getCustomerType() === 'individual',
            'subsidiary' => ['id' => 1], // Default subsidiary - adjust as needed
        ];
        
        // Add company name if it's a company
        if ($this->getCustomerType() === 'company') {
            $netsuiteCustomer['companyName'] = $this->getCompany();
        }
        
        // Add phone if available
        if (!empty($this->getPhone())) {
            $netsuiteCustomer['phone'] = $this->getPhone();
        }
        
        // Add billing address if available
        $billingAddress = $this->getBillingAddress();
        if (!empty($billingAddress)) {
            $netsuiteCustomer['defaultAddress'] = $this->formatAddressForNetSuite($billingAddress);
        }
        
        return $netsuiteCustomer;
    }
    
    /**
     * Format address for NetSuite
     */
    private function formatAddressForNetSuite($address) {
        return [
            'addr1' => $address['address1'] ?? '',
            'addr2' => $address['address2'] ?? '',
            'city' => $address['city'] ?? '',
            'state' => $address['state'] ?? '',
            'zip' => $address['postal_code'] ?? '',
            'country' => $address['country'] ?? 'US',
        ];
    }
    
    /**
     * Create customer from 3DCart order data
     */
    public static function fromOrderData($orderData) {
        $customerData = [
            'id' => $orderData['CustomerID'] ?? null,
            'email' => $orderData['BillingEmail'] ?? '',
            'firstname' => $orderData['BillingFirstName'] ?? '',
            'lastname' => $orderData['BillingLastName'] ?? '',
            'company' => $orderData['BillingCompany'] ?? '',
            'phone' => $orderData['BillingPhoneNumber'] ?? '',
            'billing_address' => [
                'address1' => $orderData['BillingAddress'] ?? '',
                'address2' => $orderData['BillingAddress2'] ?? '',
                'city' => $orderData['BillingCity'] ?? '',
                'state' => $orderData['BillingState'] ?? '',
                'postal_code' => $orderData['BillingZipCode'] ?? '',
                'country' => $orderData['BillingCountry'] ?? 'US',
            ],
            'shipping_address' => [
                'address1' => $orderData['ShippingAddress'] ?? $orderData['BillingAddress'] ?? '',
                'address2' => $orderData['ShippingAddress2'] ?? $orderData['BillingAddress2'] ?? '',
                'city' => $orderData['ShippingCity'] ?? $orderData['BillingCity'] ?? '',
                'state' => $orderData['ShippingState'] ?? $orderData['BillingState'] ?? '',
                'postal_code' => $orderData['ShippingZipCode'] ?? $orderData['BillingZipCode'] ?? '',
                'country' => $orderData['ShippingCountry'] ?? $orderData['BillingCountry'] ?? 'US',
            ],
        ];
        
        return new self($customerData);
    }
    
    /**
     * Create customer from CSV row data
     */
    public static function fromCsvData($csvRow, $mapping) {
        $customerData = [];
        
        foreach ($mapping as $csvField => $customerField) {
            if (isset($csvRow[$csvField])) {
                $customerData[$customerField] = $csvRow[$csvField];
            }
        }
        
        return new self($customerData);
    }
    
    /**
     * Get customer summary for logging/notifications
     */
    public function getSummary() {
        return [
            'customer_id' => $this->getId(),
            'email' => $this->getEmail(),
            'name' => $this->getFullName(),
            'company' => $this->getCompany(),
            'type' => $this->getCustomerType(),
            'phone' => $this->getPhone(),
        ];
    }
    
    /**
     * Check if customer has required fields for NetSuite
     */
    public function hasRequiredFields() {
        $errors = $this->validate();
        return empty($errors);
    }
    
    /**
     * Get missing required fields
     */
    public function getMissingFields() {
        $errors = $this->validate();
        $missingFields = [];
        
        foreach ($errors as $error) {
            if (strpos($error, 'Missing required') !== false) {
                preg_match('/Missing required.*field: (.+)/', $error, $matches);
                if (isset($matches[1])) {
                    $missingFields[] = $matches[1];
                }
            }
        }
        
        return $missingFields;
    }
    
    /**
     * Sanitize customer data
     */
    public function sanitize() {
        $this->data['firstname'] = Validator::sanitizeString($this->data['firstname'] ?? '', 50);
        $this->data['lastname'] = Validator::sanitizeString($this->data['lastname'] ?? '', 50);
        $this->data['company'] = Validator::sanitizeString($this->data['company'] ?? '', 100);
        $this->data['email'] = Validator::sanitizeEmail($this->data['email'] ?? '');
        
        // Sanitize phone number
        if (isset($this->data['phone'])) {
            $this->data['phone'] = preg_replace('/[^0-9\-\+\(\)\.\s]/', '', $this->data['phone']);
        }
        
        return $this;
    }
    
    /**
     * Check if this customer matches another customer (for duplicate detection)
     */
    public function matches(Customer $otherCustomer) {
        // Primary match: email
        if (!empty($this->getEmail()) && !empty($otherCustomer->getEmail())) {
            return strtolower($this->getEmail()) === strtolower($otherCustomer->getEmail());
        }
        
        // Secondary match: name and phone
        if (!empty($this->getPhone()) && !empty($otherCustomer->getPhone())) {
            $nameMatch = strtolower($this->getFullName()) === strtolower($otherCustomer->getFullName());
            $phoneMatch = preg_replace('/[^0-9]/', '', $this->getPhone()) === 
                         preg_replace('/[^0-9]/', '', $otherCustomer->getPhone());
            
            return $nameMatch && $phoneMatch;
        }
        
        return false;
    }
}