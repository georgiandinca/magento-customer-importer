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
                'lastname' => $this->getMagentoAttributeId('lastname', 1),
                'dob' => $this->getMagentoAttributeId('dob', 1),
                'prefix' => $this->getMagentoAttributeId('prefix', 1),
                'gender' => $this->getMagentoAttributeId('gender', 1),
                'default_billing' => $this->getMagentoAttributeId('default_billing', 1),
                'default_shipping' => $this->getMagentoAttributeId('default_shipping', 1),
                'password_hash' => $this->getMagentoAttributeId('password_hash', 1)
            ),
            'address' => array(
                'firstname' => $this->getMagentoAttributeId('firstname', 2),
                'lastname' => $this->getMagentoAttributeId('lastname', 2),
                'prefix' => $this->getMagentoAttributeId('prefix', 2),
                'country_id' => $this->getMagentoAttributeId('country_id', 2),
                'postcode' => $this->getMagentoAttributeId('postcode', 2),
                'city' => $this->getMagentoAttributeId('city', 2),
                'telephone' => $this->getMagentoAttributeId('telephone', 2),
                'fax' => $this->getMagentoAttributeId('fax', 2),
                'street' => $this->getMagentoAttributeId('street', 2),
                'region_id' => $this->getMagentoAttributeId('region_id', 2)
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
     *
     * @return int|bool
     */
    public function import($customerData)
    {
        // Execute the query, effectively importing the customer:
        $result = $this->queryCreateCustomer->execute(
            array(
                ':website_id' => $customerData['website_id'],
                ':email' => $customerData['email'],
                ':group_id' => $customerData['group_id'],
                ':store_id' => $customerData['store_id']
            )
        );
        if($result === true)
        {
            // Get customer ID:
            $customerID = $this->pdoMagento->lastInsertId();
            
            // Store attributes:
            $this->storeCustomerAttribute($customerID, 'firstname', $customerData['firstname']);
            $this->storeCustomerAttribute($customerID, 'lastname', $customerData['lastname']);
            if(isset($customerData['dob'])) { 
                $this->storeCustomerAttribute($customerID, 'dob', $customerData['dob']); 
            }
            if(isset($customerData['prefix'])) { 
                $this->storeCustomerAttribute($customerID, 'prefix', $customerData['prefix']); 
            }
            if(isset($customerData['gender'])) { 
                $this->storeCustomerAttribute($customerID, 'gender', $customerData['gender']); 
            }
            
            // Create address:
            $result = $this->queryCreateAddress->execute(
                array(
                    ':parent_id' => $customerID
                )
            );
            if($result === true)
            {
                // Get address ID:
                $addressID = $this->pdoMagento->lastInsertId();
                
                // Store attributes:
                $this->storeAddressAttribute($addressID, 'firstname', $customerData['firstname']);
                $this->storeAddressAttribute($addressID, 'lastname', $customerData['lastname']);
                if(isset($customerData['prefix'])) {
                    $this->storeAddressAttribute($addressID, 'prefix', $customerData['prefix']);
                }
                $this->storeAddressAttribute($addressID, 'country_id', $customerData['country_id']);
                $this->storeAddressAttribute($addressID, 'postcode', $customerData['postcode']);
                $this->storeAddressAttribute($addressID, 'city', $customerData['city']);
                $this->storeAddressAttribute($addressID, 'street', $customerData['street']);
                $this->storeAddressAttribute($addressID, 'telephone', $customerData['telephone']);
                if(isset($customerData['fax'])) {
                    $this->storeAddressAttribute($addressID, 'fax', $customerData['fax']);
                }
                if(isset($customerData['region_id']))
                {
                    $this->storeAddressAttribute($addressID, 'region_id', $customerData['region_id']);
                } else {
                    $this->storeAddressAttribute($addressID, 'region_id', 0);
                }
                
                // Set default billing and default shipping:
                $this->storeCustomerAttribute($customerID, 'default_billing', $addressID);
                $this->storeCustomerAttribute($customerID, 'default_shipping', $addressID);
                
                // Done!
                
            } else {
                return false;
            }
        } else {
            return false;
        }
        
        // Return the customer ID:
        return $customerID;
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
}