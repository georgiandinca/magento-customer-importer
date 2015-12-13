<?php
/**
 * Created by PhpStorm.
 * User: georgiandinca
 * Date: 13/12/15
 * Time: 19:48
 */

/**
 * Name of file that will be inmported
 */
$csvImportFile = "migration_customers_lastid_288392.csv";

/*************************************
 * Error handling
 */
ini_set('display_errors',1);
error_reporting(E_ALL);

/**
 * Global settings for CSV manipulation
 */
define( 'DELIMITER', ","); //coma
define( 'ENCLOSER', '"'); //coma
define( 'ESCAPE', "\\"); //coma

/**
 * Globals DB connections
 */
define( 'MAGENTO_HOST', 'localhost' );
define( 'MAGENTO_USER', 'root' );
define( 'MAGENTO_PASS', 'geo' );
define( 'MAGENTO_DB', 'dev_im_new' );


/**
 * get the customer importer class
 */
require_once('customer_importer_im.php');
require_once('variables.php');

$importer = new CustomerImporter();

/**
 * Prepare variables
 */
$customer = array();
$row = 1;
$head = array();

//get file handler
if (($handle = fopen($csvImportFile, "r")) !== FALSE) {
    //loop in file line by line and import customers
    while (($data = fgetcsv($handle, 0, DELIMITER, ENCLOSER, ESCAPE)) !== FALSE) {
        if ($row == 1) //get first line as head
            $head = $data;

        else {
            $num = count($data);
            $customer['store_id'] = $storeIds['default'];
            for ($c=0; $c < $num; $c++) {
                if($head[$c] == 'website')
                    $customer['website_id'] = $website_Ids[$data[$c]];
                elseif($head[$c] == 'group_id')
                    $customer['group_id'] = $group_Ids[$data[$c]];
                else
                    $customer[$head[$c]] = $data[$c];
            }
            echo $row." - Importing - ".$customer['email']. " - ";
            $result = $importer->import($customer, false);
            if($result != false)
            {
                echo "Import successful (result is $result)\n";
            } else {
                echo "Import failed (result is $result)\n";
            }
        }
        $row++;
        $customer = array();
    }
    fclose($handle);
}

