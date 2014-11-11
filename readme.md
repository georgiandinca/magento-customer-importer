# Magento Customer Importer

## A blazing fast customer importer class

---

## Usage

### Define constants

The first thing you need to do is define some constants somewhere in your code, so that the customer importer
can connect to the Magento database:

    define( 'MAGENTO_HOST', 'localhost' );
    define( 'MAGENTO_USER', 'root' );
    define( 'MAGENTO_PASS', 'super_secret_password' );
    define( 'MAGENTO_DB', 'magento_db' );

### Define class

The class only needs to be included once:

    require_once('customer_importer.php');
    $importer = new CustomerImporter();

### Import customers

To import a single customer, you can use a data array, like so:

    // Set data:
    $data = array(
        'website_id'    => $websiteId,
        'store_id'      => $storeId,
        'group_id'      => $groupId,
        'prefix'        => 'Mr.',
        'firstname'     => 'John',
        'lastname'      => 'Doe',
        'email'         => 'john.doe@example.com',
        'country_id'    => 'NL',
        'postcode'      => '5611GD',
        'city'          => 'Eindhoven',
        'street'        => 'Keizersgracht 2a/b',
        'telephone'     => '+31 (0)40 - 30 401 70',
        'fax'           => '+31 (0)40 - 30 401 71', // optional
        'dob'           => '25-05-1984',            // optional
        'gender'        => 1                        // 1=male, 2=female
    );
    
    // Actual import:
    $result = $this->customerImporter->import($data);
    if($result === false)
    {
        echo "Import successful\n";   
    } else {
        echo "Import failed\n";
    }

The address will be automatically set to default billing and shipping address. This code can be easily called in a `foreach()`-loop to provide mass importing of customers.

### Speed comparison

Compared to the [Magento-way of creating customers programmatically](http://inchoo.net/magento/programming-magento/programmaticaly-adding-new-customers-to-the-magento-store/) 
the speed of importing is at least 5000 times (or even more) faster. This is because instead of doing it with Magento, the
customers are imported directly into the database.

### Disadvantages

Some disadvantages are to consider when using this import:

- Since the import goes outside Magento's core code, Magento specific events are not triggered. Also rewrite rules by third party modules are not executed. This causes code that depends on these events not being executed.
- Validation is not done in this class. So you have to manually check for required fields prior before importing.
- Custom customer attributes (in Magento Enterprise or provided by other modules) are not imported. Only the base essentials as provided in this code are imported.
