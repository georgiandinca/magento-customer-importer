<?php
/**
 * Magento Customer Importer
 * v1.0
 *
 * Copyright (c) 2014 Happy Online B.V. (www.happy-online.nl)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Class FastCustomerImporter
 */
class CustomerImporter
{
    /**
     * @var $attributes array with customer attributes
     */
    private $attributes;

    /**
     * @var $pdoMagento PDO
     */
    private $pdoMagento;

    /**
     * @var $queryCreateCustomer PDOStatement
     */
    private $queryCreateCustomer;

    /**
     * @var $queryCreateAddress PDOStatement
     */
    private $queryCreateAddress;

    /**
     * @var $isUpdate bool
     */
    private $isUpdate;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Connect to databases:
        $this->connectToDatabase();

        // Setup:
        $this->setup();
    }

    /**
     * Connect to the databases
     */
    private function connectToDatabase()
    {
        // Connect to Magento:
        $this->pdoMagento = new PDO(sprintf(
                'mysql:host=%s;dbname=%s',
                MAGENTO_HOST,
                MAGENTO_DB
            ),
            MAGENTO_USER,
            MAGENTO_PASS
        );
    }

    /**
     * Setup customer import
     */
    private function setup()
    {
        // Get customer attributes for fast import:
        $this->attributes = array(
            'customer' => array(
                'firstname' => $this->getMagentoAttributeId('firstname', 1),
                'middlename' => $this->getMagentoAttributeId('middlename', 1),
                'lastname' => $this->getMagentoAttributeId('lastname', 1),
                'dob' => $this->getMagentoAttributeId('dob', 1),
                'prefix' => $this->getMagentoAttributeId('prefix', 1),
                'gender' => $this->getMagentoAttributeId('gender', 1),
                'default_billing' => $this->getMagentoAttributeId('default_billing', 1),
                'default_shipping' => $this->getMagentoAttributeId('default_shipping', 1),
                'password_hash' => $this->getMagentoAttributeId('password_hash', 1),
//                'initials' => $this->getMagentoAttributeId('initials', 1),
            //utm_source,utm_campaign,utm_medium,utm_content,referral,utm_retargeting_source,referer_url,registered_from, showroom_style
                'utm_source' => $this->getMagentoAttributeId('utm_source', 1),
                'utm_campaign' => $this->getMagentoAttributeId('utm_campaign', 1),
                'utm_medium' => $this->getMagentoAttributeId('utm_medium', 1),
                'utm_content' => $this->getMagentoAttributeId('utm_content', 1),
                'referral' => $this->getMagentoAttributeId('referral', 1),
                'utm_retargeting_source' => $this->getMagentoAttributeId('utm_retargeting_source', 1),
                'referer_url' => $this->getMagentoAttributeId('referer_url', 1),
                'registered_from' => $this->getMagentoAttributeId('registered_from', 1),
                'showroom_style' => $this->getMagentoAttributeId('showroom_style', 1),
            ),
            'address' => array(
                'firstname' => $this->getMagentoAttributeId('firstname', 2),
                'middlename' => $this->getMagentoAttributeId('middlename', 2),
                'lastname' => $this->getMagentoAttributeId('lastname', 2),
                'prefix' => $this->getMagentoAttributeId('prefix', 2),
                'suffix' => $this->getMagentoAttributeId('suffix', 2),
                'country_id' => $this->getMagentoAttributeId('country_id', 2),
                'postcode' => $this->getMagentoAttributeId('postcode', 2),
                'city' => $this->getMagentoAttributeId('city', 2),
                'telephone' => $this->getMagentoAttributeId('telephone', 2),
                'fax' => $this->getMagentoAttributeId('fax', 2),
                'street' => $this->getMagentoAttributeId('street', 2),
                'region' => $this->getMagentoAttributeId('region', 2),
                'company' => $this->getMagentoAttributeId('company', 2),
            )
        );

        // Prepare queries:
        $this->queryCreateCustomer = $this->pdoMagento->prepare(
            'INSERT INTO `customer_entity` (`entity_type_id`, `attribute_set_id`, `website_id`, `email`,
            `group_id`, `increment_id`, `store_id`, `created_at`, `updated_at`, `is_active`, `disable_auto_group_change`)
            VALUES (1, 0, :website_id, :email, :group_id, NULL, :store_id, NOW(), NOW(), 1, 0)'
        );
        
        $this->queryCreateAddress = $this->pdoMagento->prepare(
            'INSERT INTO `customer_address_entity` (`entity_type_id`, `attribute_set_id`, `increment_id`, `parent_id`,
            `created_at`, `updated_at`, `is_active`) VALUES (2, 0, NULL, :parent_id, NOW(), NOW(), 1)'
        );
    }

    /**
     * Import a single customer
     * 
     * @param $customerData
     * @param bool|int $updateId    If set, update the customer with the specific ID
     *
     * @return int|bool
     */
    public function import($customerData, $updateId)
    {
        // Execute the query, effectively importing the customer:
        if($updateId === false)
        {
            $result = $this->queryCreateCustomer->execute(
                array(
                    ':website_id' => $customerData['website_id'],
                    ':email' => $customerData['email'],
                    ':group_id' => $customerData['group_id'],
                    ':store_id' => $customerData['store_id']
                )
            );
            $this->isUpdate = false;
        } else {
            $result = true;
            $this->isUpdate = true;
        }
        
        // Import attributes and address:
        if($result === true)
        {
            // Get customer ID:
            $customerID = $updateId === false ? $this->pdoMagento->lastInsertId() : $updateId;
            
            // Store attributes:
            $this->storeCustomerAttribute($customerID, 'firstname', $customerData['firstname']);
            $this->storeCustomerAttribute($customerID, 'lastname', $customerData['lastname']);
            if(isset($customerData['dob']))
                $this->storeCustomerAttribute($customerID, 'dob', $customerData['dob']); 
            if(isset($customerData['prefix']))
                $this->storeCustomerAttribute($customerID, 'prefix', $customerData['prefix']); 
            if(isset($customerData['gender']))
                $this->storeCustomerAttribute($customerID, 'gender', $customerData['gender']); 
            if(isset($customerData['middlename']))
                $this->storeCustomerAttribute($customerID, 'middlename', $customerData['middlename']);
            //CUSTOM
            //utm_source,utm_campaign,utm_medium,utm_content,referral,utm_retargeting_source,referer_url,registered_from, showroom_style
            if(isset($customerData['utm_source']))
                $this->storeCustomerAttribute($customerID, 'utm_source', $customerData['utm_source']);
            if(isset($customerData['utm_campaign']))
                $this->storeCustomerAttribute($customerID, 'utm_campaign', $customerData['utm_campaign']);
            if(isset($customerData['utm_medium']))
                $this->storeCustomerAttribute($customerID, 'utm_medium', $customerData['utm_medium']);
            if(isset($customerData['utm_content']))
                $this->storeCustomerAttribute($customerID, 'utm_content', $customerData['utm_content']);
            if(isset($customerData['referral']))
                $this->storeCustomerAttribute($customerID, 'referral', $customerData['referral']);
            if(isset($customerData['utm_retargeting_source']))
                $this->storeCustomerAttribute($customerID, 'utm_retargeting_source', $customerData['utm_retargeting_source']);
            if(isset($customerData['referer_url']))
                $this->storeCustomerAttribute($customerID, 'referer_url', $customerData['referer_url']);
            if(isset($customerData['registered_from']))
                $this->storeCustomerAttribute($customerID, 'registered_from', $customerData['registered_from']);
            if(isset($customerData['showroom_style']))
                $this->storeCustomerAttribute($customerID, 'showroom_style', $customerData['showroom_style']);

            // Create addresses:
            // Verify is Billing address is same with Shipping address
            // billing_prefix,billing_firstname,billing_middlename,billing_lastname,billing_suffix,billing_street_full,billing_city,billing_region,billing_country,
            // billing_postcode, billing_telephone,billing_company,billing_fax
            $address['billing']['prefix'] = $customerData['billing_prefix'];
            $address['billing']['firstname'] = $customerData['billing_firstname'];
            $address['billing']['middlename'] = $customerData['billing_middlename'];
            $address['billing']['lastname'] = $customerData['billing_lastname'];
            $address['billing']['suffix'] = $customerData['billing_suffix'];
            $address['billing']['street'] = $customerData['billing_street_full'];
            $address['billing']['city'] = $customerData['billing_city'];
            $address['billing']['region'] = $customerData['billing_region'];
            $address['billing']['country_id'] = $customerData['billing_country'];
            $address['billing']['postcode'] = $customerData['billing_postcode'];
            $address['billing']['telephone'] = $customerData['billing_telephone'];
            $address['billing']['company'] = $customerData['billing_company'];
            $address['billing']['fax'] = $customerData['billing_fax'];

            // shipping_prefix,shipping_firstname,shipping_middlename,shipping_lastname,shipping_suffix,shipping_street_full,shipping_city,shipping_region,shipping_country,
            // shipping_postcode,shipping_telephone,shipping_company,shipping_fax
            $address['shipping']['prefix'] = $customerData['shipping_prefix'];
            $address['shipping']['firstname'] = $customerData['shipping_firstname'];
            $address['shipping']['middlename'] = $customerData['shipping_middlename'];
            $address['shipping']['lastname'] = $customerData['shipping_lastname'];
            $address['shipping']['suffix'] = $customerData['shipping_suffix'];
            $address['shipping']['street'] = $customerData['shipping_street_full'];
            $address['shipping']['city'] = $customerData['shipping_city'];
            $address['shipping']['region'] = $customerData['shipping_region'];
            $address['shipping']['country_id'] = $customerData['shipping_country'];
            $address['shipping']['postcode'] = $customerData['shipping_postcode'];
            $address['shipping']['telephone'] = $customerData['shipping_telephone'];
            $address['shipping']['company'] = $customerData['shipping_company'];
            $address['shipping']['fax'] = $customerData['shipping_fax'];

            if ($address['billing'] == $address['shipping']) {
                // Same billing and shippment
                $addressID = $this->createCustomerAddress($customerID, $address['billing']);
                if ($addressID !== false) {
                    // Set default billing and default shipping:
                    $this->storeCustomerAttribute($customerID, 'default_billing', $addressID);
                    $this->storeCustomerAttribute($customerID, 'default_shipping', $addressID);
                }
            } else {
                // Different billing and shipment address
                $addressBillingID   = $this->createCustomerAddress($customerID, $address['billing']);
                $addressShippingID  = $this->createCustomerAddress($customerID, $address['shipping']);

                if ($addressBillingID !== false) {
                    // Set default billing:
                    $this->storeCustomerAttribute($customerID, 'default_billing', $addressBillingID);
                }
                if ($addressShippingID !== false) {
                    // Set default shipping:
                    $this->storeCustomerAttribute($customerID, 'default_shipping', $addressShippingID);
                }
            }

        } else {
            return false;
        }
        
        // Return the customer ID:
        return $customerID;
    }

    private function createCustomerAddress($customerID, $address) {
        if(!$this->isUpdate)
        {
            $result = $this->queryCreateAddress->execute(
                array(
                    ':parent_id' => $customerID
                )
            );
        } else {
            $addressID = $this->getPreferredAddressId($customerID);
            $result    = $addressID !== false;
        }

        if($result === true)
        {
            // Get address ID:
            if(!$this->isUpdate)
            {
                $addressID = $this->pdoMagento->lastInsertId();
            }

            // Store attributes:
            $this->storeAddressAttribute($addressID, 'firstname', $address['firstname']);
            $this->storeAddressAttribute($addressID, 'lastname', $address['lastname']);
            if(isset($address['prefix'])) {
                $this->storeAddressAttribute($addressID, 'prefix', $address['prefix']);
            }
            if(isset($address['suffix'])) {
                $this->storeAddressAttribute($addressID, 'suffix', $address['suffix']);
            }
            $this->storeAddressAttribute($addressID, 'country_id', $address['country_id']);
            $this->storeAddressAttribute($addressID, 'postcode', $address['postcode']);
            $this->storeAddressAttribute($addressID, 'city', $address['city']);
            $this->storeAddressAttribute($addressID, 'street', $address['street']);
            $this->storeAddressAttribute($addressID, 'telephone', $address['telephone']);
            if(isset($address['fax'])) {
                $this->storeAddressAttribute($addressID, 'fax', $address['fax']);
            }
            if(isset($address['region']))
            {
                $this->storeAddressAttribute($addressID, 'region', $address['region']);
            } else {
                $this->storeAddressAttribute($addressID, 'region_id', 0);
            }
            if(isset($address['company'])) {
                $this->storeAddressAttribute($addressID, 'fax', $address['company']);
            }

            //DONE!
            return $addressID;

        } else {
            return false;
        }
    }

    /**
     * Store customer attribute
     * 
     * @param $customerId
     * @param $key
     * @param $value
     */
    private function storeCustomerAttribute($customerId, $key, $value)
    {
        if(!$this->checkIfAttributeExists(
            $customerId,
            1,
            $this->attributes['customer'][$key]['id'],
            'customer_entity_' . $this->attributes['customer'][$key]['type'])
        )
        {
            $this->pdoMagento->query(
                sprintf(
                    'INSERT INTO `customer_entity_%1$s` (`entity_type_id`, `attribute_id`, `entity_id`, `value`)
                    VALUES (1, %2$d, %3$d, \'%4$s\')',
                    $this->attributes['customer'][$key]['type'],
                    $this->attributes['customer'][$key]['id'],
                    $customerId,
                    addslashes($value)
                )
            );
        } else {
            $this->pdoMagento->query(
                sprintf(
                    'UPDATE `customer_entity_%1$s` SET `value` = \'%4$s\' WHERE 
                    `entity_id` = %3$d AND `entity_type_id` = 1 AND `attribute_id` = %2$d;',
                    $this->attributes['customer'][$key]['type'],
                    $this->attributes['customer'][$key]['id'],
                    $customerId,
                    addslashes($value)
                )
            );
        }
    }

    /**
     * Store customer address attribute
     *
     * @param $addressId
     * @param $key
     * @param $value
     */
    private function storeAddressAttribute($addressId, $key, $value)
    {
        if(!$this->checkIfAttributeExists(
            $addressId, 
            2, 
            $this->attributes['address'][$key]['id'], 
            'customer_address_entity_' . $this->attributes['address'][$key]['type'])
        )
        {
            $this->pdoMagento->query(
                sprintf(
                    'INSERT INTO `customer_address_entity_%1$s` (`entity_type_id`, `attribute_id`, `entity_id`, `value`)
                    VALUES (2, %2$d, %3$d, \'%4$s\')',
                    $this->attributes['address'][$key]['type'],
                    $this->attributes['address'][$key]['id'],
                    $addressId,
                    addslashes($value)
                )
            );
        } else {
            $this->pdoMagento->query(
                sprintf(
                    'UPDATE `customer_address_entity_%1$s` SET `value` = \'%4$s\' WHERE 
                        `entity_id` = %3$d AND `entity_type_id` = 2 AND `attribute_id` = %2$d;',
                    $this->attributes['address'][$key]['type'],
                    $this->attributes['address'][$key]['id'],
                    $addressId,
                    addslashes($value)
                )
            );
        }
    }

    /**
     * @param $entityId
     * @param $entityTypeId
     * @param $attributeId
     * @param $tableName
     *
     * @return bool
     */
    private function checkIfAttributeExists($entityId, $entityTypeId, $attributeId, $tableName)
    {
        $checkQuery = $this->pdoMagento->prepare(
            'SELECT `value` FROM :table_name WHERE `entity_id` = :entity_id AND 
                `entity_type_id` = :entity_type_id AND `attribute_id` = :attribute_id'
        );
        $checkQuery->execute(
            array(
                ':table_name' => $tableName,
                ':entity_id' => $entityId,
                ':entity_type_id' => $entityTypeId,
                ':attribute_id' => $attributeId
            )
        );
        return $checkQuery->rowCount() > 0;
    }
    
    /**
     * Get Magento attribute ID
     *
     * @param $code
     * @param $entityTypeId
     *
     * @return array
     * @throws Exception
     */
    private function getMagentoAttributeId($code, $entityTypeId)
    {
        $attributeQuery = $this->pdoMagento->prepare('SELECT attribute_id, backend_type FROM eav_attribute 
            WHERE attribute_code = :attribute_code AND entity_type_id = :entity_type_id;');
        $attributeQuery->execute(array(':attribute_code' => $code, ':entity_type_id' => $entityTypeId));
        if($attributeQuery->rowCount() == 1)
        {
            $result = $attributeQuery->fetch(PDO::FETCH_ASSOC);
            return array(
                'id' => (int) $result['attribute_id'],
                'type' => $result['backend_type']
            );
        } else {
            throw new Exception('Cannot find Magento attribute: ' . $code . ' (' . $entityTypeId . ')');
        }
    }

    /**
     * @param $customerID
     * 
     * @return bool|int
     */
    private function getPreferredAddressId($customerID)
    {
        $addressQuery = $this->pdoMagento->prepare('
            SELECT `value` FROM `customer_entity_int` WHERE `entity_id` = :customer_id AND 
            `attribute_id` = :attribute_id;');
        $data = array(
            ':customer_id' => $customerID,
            ':attribute_id' => $this->attributes['customer']['default_billing']['id']
        ); 
        $addressQuery->execute($data);
        if($addressQuery->rowCount() == 1)
        {
            $row = $addressQuery->fetch(PDO::FETCH_ASSOC);
            return (int) $row['value'];
        }
        return false;
    }
}
