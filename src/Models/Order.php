<?php

namespace Laguna\Integration\Models;

use Laguna\Integration\Utils\Validator;

/**
 * Order Model
 * 
 * Represents an order from 3DCart and provides methods for data transformation
 * and validation for NetSuite integration.
 */
class Order {
    private $data;
    private $items;
    private $customer;
    
    public function __construct($orderData) {
        $this->data = $orderData;
        $this->items = $this->parseItems();
        $this->customer = $this->parseCustomer();
    }
    
    /**
     * Get order ID
     */
    public function getId() {
        return $this->data['OrderID'] ?? null;
    }
    
    /**
     * Get customer ID
     */
    public function getCustomerId() {
        return $this->data['CustomerID'] ?? null;
    }
    
    /**
     * Get order date
     */
    public function getOrderDate() {
        return $this->data['OrderDate'] ?? null;
    }
    
    /**
     * Get order status
     */
    public function getOrderStatus() {
        return $this->data['OrderStatusID'] ?? null;
    }
    
    /**
     * Get order total
     */
    public function getTotal() {
        return (float)($this->data['OrderTotal'] ?? 0);
    }
    
    /**
     * Get subtotal
     */
    public function getSubtotal() {
        return (float)($this->data['SalesTax'] ?? 0);
    }
    
    /**
     * Get shipping cost
     */
    public function getShippingCost() {
        return (float)($this->data['ShippingCost'] ?? 0);
    }
    
    /**
     * Get tax amount
     */
    public function getTaxAmount() {
        return (float)($this->data['SalesTax'] ?? 0);
    }
    
    /**
     * Get billing address
     */
    public function getBillingAddress() {
        return [
            'firstname' => $this->data['BillingFirstName'] ?? '',
            'lastname' => $this->data['BillingLastName'] ?? '',
            'company' => $this->data['BillingCompany'] ?? '',
            'address1' => $this->data['BillingAddress'] ?? '',
            'address2' => $this->data['BillingAddress2'] ?? '',
            'city' => $this->data['BillingCity'] ?? '',
            'state' => $this->data['BillingState'] ?? '',
            'postal_code' => $this->data['BillingZipCode'] ?? '',
            'country' => $this->data['BillingCountry'] ?? 'US',
            'phone' => $this->data['BillingPhoneNumber'] ?? '',
        ];
    }
    
    /**
     * Get shipping address
     */
    public function getShippingAddress() {
        return [
            'firstname' => $this->data['ShippingFirstName'] ?? $this->data['BillingFirstName'] ?? '',
            'lastname' => $this->data['ShippingLastName'] ?? $this->data['BillingLastName'] ?? '',
            'company' => $this->data['ShippingCompany'] ?? $this->data['BillingCompany'] ?? '',
            'address1' => $this->data['ShippingAddress'] ?? $this->data['BillingAddress'] ?? '',
            'address2' => $this->data['ShippingAddress2'] ?? $this->data['BillingAddress2'] ?? '',
            'city' => $this->data['ShippingCity'] ?? $this->data['BillingCity'] ?? '',
            'state' => $this->data['ShippingState'] ?? $this->data['BillingState'] ?? '',
            'postal_code' => $this->data['ShippingZipCode'] ?? $this->data['BillingZipCode'] ?? '',
            'country' => $this->data['ShippingCountry'] ?? $this->data['BillingCountry'] ?? 'US',
            'phone' => $this->data['ShippingPhoneNumber'] ?? $this->data['BillingPhoneNumber'] ?? '',
        ];
    }
    
    /**
     * Get order items
     */
    public function getItems() {
        return $this->items;
    }
    
    /**
     * Get customer information
     */
    public function getCustomer() {
        return $this->customer;
    }
    
    /**
     * Get raw order data
     */
    public function getRawData() {
        return $this->data;
    }
    
    /**
     * Parse order items from raw data
     */
    private function parseItems() {
        $items = [];
        
        if (isset($this->data['OrderItemList']) && is_array($this->data['OrderItemList'])) {
            foreach ($this->data['OrderItemList'] as $item) {
                $items[] = new OrderItem($item);
            }
        }
        
        return $items;
    }
    
    /**
     * Parse customer information from order data
     */
    private function parseCustomer() {
        return [
            'id' => $this->data['CustomerID'] ?? null,
            'email' => $this->data['BillingEmail'] ?? '',
            'firstname' => $this->data['BillingFirstName'] ?? '',
            'lastname' => $this->data['BillingLastName'] ?? '',
            'company' => $this->data['BillingCompany'] ?? '',
            'phone' => $this->data['BillingPhoneNumber'] ?? '',
            'billing_address' => $this->getBillingAddress(),
            'shipping_address' => $this->getShippingAddress(),
        ];
    }
    
    /**
     * Validate order data
     */
    public function validate() {
        return Validator::validateThreeDCartOrder($this->data);
    }
    
    /**
     * Convert to NetSuite format
     */
    public function toNetSuiteFormat($customerId) {
        $billingAddress = $this->getBillingAddress();
        $shippingAddress = $this->getShippingAddress();
        
        return [
            'entity' => ['id' => $customerId],
            'tranDate' => date('Y-m-d', strtotime($this->getOrderDate())),
            'orderStatus' => 'A', // Pending Approval
            'externalId' => '3DCART_' . $this->getId(),
            'memo' => 'Order imported from 3DCart - Order #' . $this->getId(),
            'billAddress' => [
                'addr1' => $billingAddress['address1'],
                'addr2' => $billingAddress['address2'],
                'city' => $billingAddress['city'],
                'state' => $billingAddress['state'],
                'zip' => $billingAddress['postal_code'],
                'country' => $billingAddress['country'],
            ],
            'shipAddress' => [
                'addr1' => $shippingAddress['address1'],
                'addr2' => $shippingAddress['address2'],
                'city' => $shippingAddress['city'],
                'state' => $shippingAddress['state'],
                'zip' => $shippingAddress['postal_code'],
                'country' => $shippingAddress['country'],
            ],
            'item' => array_map(function($item) {
                return $item->toNetSuiteFormat();
            }, $this->items)
        ];
    }
    
    /**
     * Get order summary for logging/notifications
     */
    public function getSummary() {
        return [
            'order_id' => $this->getId(),
            'customer_id' => $this->getCustomerId(),
            'customer_email' => $this->data['BillingEmail'] ?? '',
            'order_date' => $this->getOrderDate(),
            'total' => $this->getTotal(),
            'item_count' => count($this->items),
            'status' => $this->getOrderStatus(),
        ];
    }
}

/**
 * Order Item Model
 * 
 * Represents an individual item within an order.
 */
class OrderItem {
    private $data;
    
    public function __construct($itemData) {
        $this->data = $itemData;
    }
    
    /**
     * Get item SKU/Catalog ID
     */
    public function getSku() {
        return $this->data['CatalogID'] ?? '';
    }
    
    /**
     * Get item name
     */
    public function getName() {
        return $this->data['ItemName'] ?? '';
    }
    
    /**
     * Get item description
     */
    public function getDescription() {
        return $this->data['ItemDescription'] ?? $this->getName();
    }
    
    /**
     * Get quantity
     */
    public function getQuantity() {
        return (float)($this->data['Quantity'] ?? 0);
    }
    
    /**
     * Get unit price
     */
    public function getUnitPrice() {
        return (float)($this->data['ItemPrice'] ?? 0);
    }
    
    /**
     * Get total price (quantity * unit price)
     */
    public function getTotalPrice() {
        return $this->getQuantity() * $this->getUnitPrice();
    }
    
    /**
     * Get item weight
     */
    public function getWeight() {
        return (float)($this->data['ItemWeight'] ?? 0);
    }
    
    /**
     * Get raw item data
     */
    public function getRawData() {
        return $this->data;
    }
    
    /**
     * Validate item data
     */
    public function validate() {
        return Validator::validateOrderItem($this->data);
    }
    
    /**
     * Convert to NetSuite format
     */
    public function toNetSuiteFormat() {
        return [
            'item' => ['externalId' => $this->getSku()], // Will be resolved to internal ID
            'quantity' => $this->getQuantity(),
            'rate' => $this->getUnitPrice(),
            'description' => $this->getDescription(),
        ];
    }
    
    /**
     * Get item summary
     */
    public function getSummary() {
        return [
            'sku' => $this->getSku(),
            'name' => $this->getName(),
            'quantity' => $this->getQuantity(),
            'unit_price' => $this->getUnitPrice(),
            'total_price' => $this->getTotalPrice(),
        ];
    }
}