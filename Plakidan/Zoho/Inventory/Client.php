<?php

/**
 * Author: Stierlitz
 * Email: plakida@gmail.com
 * License: GNU General Public License v3.0
 */

namespace Plakidan\Zoho\Inventory;

class Client
{
    public $organizationId;
    public $accessToken;
    public $refreshToken;
    public $clientId;
    public $clientSecret;
    public $redirectUri;
    private $regenerated = false;

    public $returnAsJson = true; // planned to determine function return format (Json/Boolean)

    const ERROR_TYPE_CURL = 'cUrl';
    const ERROR_TYPE_ZOHO = 'zoho';

    const SCOPE_PURCHASEORDERS = 'purchaseorders';
    const SCOPE_SALESORDERS = 'salesorders';
    const SCOPE_ITEMGROUP = 'itemgroup';
    const SCOPE_INVOICES = 'invoices';
    const SCOPE_CONTACTS = 'contacts';
    const SCOPE_ITEMS = 'items';
    const SCOPE_BILLS = 'bills';

    const STATUS_ALL = 'Status.All';
    const STATUS_ACTIVE_CUSTOMERS = 'Status.ActiveCustomers';
    const STATUS_ACTIVE_VENDORS = 'Status.ActiveVendors';
    const STATUS_INACTIVE_CUSTOMERS = 'Status.InactiveCustomers';
    const STATUS_INACTIVE_VENDORS = 'Status.InactiveVendors';
    const STATUS_CRM = 'Status.Crm';
    const STATUS_INACTIVE = 'Status.Inactive';
    const STATUS_ACTIVE = 'Status.Active';

    private $_baseUrl = 'inventory.zoho.com/api/v1';
    private $_curlObject;

    /**
     * ZohoClient constructor.
     * @param array $config accessToken is must, organizationId is optional in case only one organization
     * @throws \Exception
     */
    public function __construct(array $config = [])
    {
        $req = [
            'accessToken',
            'refreshToken',
            'clientId',
            'clientSecret',
            'redirectUri',
        ];
        foreach ($req as $item){
            if (!isset($config[$item])) {
                throw new \Exception('You have to set \''.$item.'\' to use zoho client');
            }
        }
        foreach ($config as $property => $value) {
            $this->{$property} = $value;
        }
    }

    /**
     * @return \stdClass list of organization's properties
     * @see https://www.zoho.com/inventory/api/v1/#organization-id
     * @throws \Exception
     */
    public function retrieveOrganizationsInfo()
    {
        return $this->curlRequest('/organizations');
    }

    /**
     * an extra function to update system settings<br>
     * Example : update the counter of purchase orders
     * <pre>
     *  $zoho->updateSettings(
     *      ZohoClient::SCOPE_PURCHASEORDERS,
     *      ["next_number" => 54]
     *  );
     * </pre>
     * @param string $scope select one of the scopes from constant (example, ZohoClient::SCOPE_PURCHASEORDERS)
     * @param array $params
     * @return \stdClass
     * @throws \Exception
     */
    public function updateSettings($scope, $params)
    {
        return $this->curlRequest("/settings/$scope/", 'PUT', ['JSONString' => json_encode($params)]);
    }

    //************************************** Item *********************************************

    /**
     * @param array $params array of properties for the new item
     * @return \stdClass
     * @throws \Exception
     * @see https://www.zoho.com/inventory/api/v1/#create-a-item
     */
    public function createItem($params)
    {
        return $this->curlRequest('/items', 'POST', ['JSONString' => json_encode($params)]);
    }

    /**
     * @param string $item_id item to update
     * @param array $params array of new properties
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#update-an-item
     * @throws \Exception
     */
    public function updateItem($item_id, $params)
    {
        return $this->curlRequest("/items/{$item_id}", 'PUT', ['JSONString' => json_encode($params)]);
    }

    /**
     * @param string $item_id to retrieve, leave empty to list all items
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#retrieve-a-item
     * @throws \Exception
     */
    public function retrieveItem($item_id = null)
    {
        return $this->curlRequest("/items/{$item_id}");
    }

    /**
     * List all items
     * @param array $filters an extra option used in zoho web application allows you to filter items by specific fields, leave empty to list all items<br>
     * some of available filters :<br>
     * search_text : to search about a part of text inside the item page<br>
     * filter_by   : ItemType.Sales , Status.Unmapped , Status.Active, Status.Lowstock, ItemType.Purchases, ItemType.Inventory, ItemType.NonInventory, ItemType.Service<br>
     * Pagenation arguments : page, per_page,sort_column(column name), sort_order(A/D)<br>
     * ( exmple : to filter by upc field just add to the list of parameters with the value you want to filter, you may use your custom fields also )
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#list-all-item
     * @throws \Exception
     */
    public function listItems(array $filters = [])
    {
        return $this->curlRequest("/items/", 'GET', $filters);
    }

    /**
     * an extra function allows you to search items by text portion
     * @param string $search_text
     * @return \stdClass
     * @throws \Exception
     */
    public function searchItem($search_text)
    {
        return $this->listItems(['search_text' => $search_text]);
    }

    /**
     * Deletes an item
     * @param string $item_id item to delete
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#delete-an-item
     * @throws \Exception
     */
    public function deleteItem($item_id)
    {
        return $this->curlRequest("/items/{$item_id}", 'DELETE');
    }

    /**
     * Add a new item image
     * @param string $item_id item to add the image to
     * @param string $image_path full local file path of image to add
     * @param string|null $type MIME type of image file to add
     * @return \stdClass
     * @see https://help.zoho.com/portal/community/topic/how-can-i-import-product-images-into-inventory
     * @see https://help.zoho.com/portal/community/topic/how-to-add-image-to-item-with-api
     * @throws \Exception
     */
    public function addItemImage($item_id, $image_path, $type = 'image/jpeg')
    {
        $params = [
            'image' => new \CURLFile($image_path, $type),
        ];
        return $this->curlRequest("/items/{$item_id}/image", 'POST', $params);
    }

    /**
     * Delete an existing item image
     * @param string $item_id item to delete its image
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#delete-item-image
     * @throws \Exception
     */
    public function deleteItemImage($item_id)
    {
        return $this->curlRequest("/items/{$item_id}/image", 'DELETE');
    }

    /**
     * Change status of the item to <strong>active</strong>
     * @param string $item_id
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#mark-as-active31
     * @throws \Exception
     */
    public function activeItem($item_id)
    {
        return $this->curlRequest("/items/{$item_id}/active", 'POST');
    }

    /**
     * Change status of the item to <strong>inactive</strong>
     * @see https://www.zoho.com/inventory/api/v1/#mark-as-inactive32
     * @param string $item_id
     * @return \stdClass
     * @throws \Exception
     */
    public function inactiveItem($item_id)
    {
        return $this->curlRequest("/items/{$item_id}/inactive", 'POST');
    }

    //************************************** Item Group *********************************************

    /**
     * Create a item group
     * @param array $params properties for the new item group
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#create-a-item-group
     * @throws \Exception
     */
    public function createItemGroup(array $params)
    {
        return $this->curlRequest("/itemgroups", 'POST', ['JSONString' => json_encode($params)]);
    }

    /**
     * Update an Item Group
     * @param string $itemgroup_id to update
     * @param array $params new properties for the selected item group
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#update-an-item-group
     * @throws \Exception
     */
    public function updateItemGroup($itemgroup_id, array $params)
    {
        return $this->curlRequest("/itemgroups/{$itemgroup_id}", 'PUT', ['JSONString' => json_encode($params)]);
    }

    /**
     * Retrieve a Item Group
     * @param string $group_id to retrieve, leave empty to retrieve all item groups
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#retrieve-a-item-group
     * @throws \Exception
     */
    public function retrieveItemGroup($group_id = null)
    {
        return $this->curlRequest("/itemgroups/{$group_id}");
    }

    /**
     * List all Item Group
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#list-all-item-group
     * @throws \Exception
     */
    public function listItemGroups()
    {
        return $this->retrieveItemGroup();
    }

    /**
     * Delete an Item Group
     * @param string $group_id to be deleted
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#delete-an-item-group
     * @throws \Exception
     */
    public function deleteItemGroup($group_id)
    {
        return $this->curlRequest("/itemgroups/{$group_id}", 'DELETE');
    }

    /**
     * Active an Item Group
     * @param string $group_id to be activated
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#mark-as-active22
     * @throws \Exception
     */
    public function activeItemGroup($group_id)
    {
        return $this->curlRequest("/itemgroups/{$group_id}/active", 'POST');
    }

    /**
     * Inactive an Item Group
     * @param string $group_id to be disabled
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#mark-as-inactive23
     * @throws \Exception
     */
    public function inactiveItemGroup($group_id)
    {
        return $this->curlRequest("/itemgroups/{$group_id}/inactive", 'POST');
    }

    //************************************** Purchase Order *********************************************

    /**
     * Create a purchase order
     * @param array $params
     * @param bool $ignore ignore auto number generation, if true you must specify the new id, default is false
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#create-a-purchase-order
     * @throws \Exception
     */
    public function createPurchaseOrder(array $params, $ignore = false)
    {
        return $this->curlRequest('/purchaseorders', 'POST',
            ['JSONString' => json_encode($params)],
            ['ignore_auto_number_generation' => $ignore ? 'true' : 'false']
        );
    }

    /**
     * Update details of an existing purchase order
     * @param string $purchaseorder_id to update
     * @param array $params
     * @param bool $ignore ignore auto number generation, if true you must specify the new id, default is false
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#update-an-purchase-order
     * @throws \Exception
     */
    public function updatePurchaseOrder($purchaseorder_id, array $params, $ignore = false)
    {
        return $this->curlRequest("/purchaseorders/{$purchaseorder_id}", 'PUT',
            ['JSONString' => json_encode($params)],
            ['ignore_auto_number_generation' => $ignore ? 'true' : 'false']
        );
    }

    /**
     * Retrieve a purchase order
     * @param string $purchaseorder_id to retrieve, leave empty to list all purchase orders
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#retrieve-a-purchase-order
     * @throws \Exception
     */
    public function retrievePurchaseOrder($purchaseorder_id = null)
    {
        return $this->curlRequest("/purchaseorders/{$purchaseorder_id}");
    }

    /**
     * List all purchase order
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#retrieve-a-purchase-order
     * @throws \Exception
     */
    public function listPurchaseOrders()
    {
        return $this->retrievePurchaseOrder();
    }

    /**
     * Delete an purchase order
     * @param string $purchaseorder_id to be deleted
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#delete-an-purchase-order
     * @throws \Exception
     */
    public function deletePurchaseOrder($purchaseorder_id)
    {
        return $this->curlRequest("/purchaseorders/{$purchaseorder_id}", 'DELETE');
    }


    /**
     * Mark as issued
     * @param string $purchaseorder_id
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#mark-as-issued
     * @throws \Exception
     */
    public function issuePurchaseOrder($purchaseorder_id)
    {
        return $this->curlRequest("/purchaseorders/{$purchaseorder_id}/status/issued", 'POST');
    }

    /**
     * Mark as cancelled
     * @param string $purchaseorder_id
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#mark-as-cancelled
     * @throws \Exception
     */
    public function cancelPurchaseOrder($purchaseorder_id)
    {
        return $this->curlRequest("/purchaseorders/{$purchaseorder_id}/status/cancelled", 'POST');
    }

    //************************************** Sales Order *********************************************

    /**
     * Create a sales order
     * @param array $params
     * @param bool $ignore ignore auto number generation, if true you must specify the new id, default is false
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#create-a-sales-order
     * @throws \Exception
     */
    public function createSalesOrder(array $params = [], $ignore = false)
    {
        return $this->curlRequest('/salesorders', 'POST',
            ['JSONString' => json_encode($params)],
            ['ignore_auto_number_generation' => $ignore ? 'true' : 'false']
        );
    }

    /**
     * Update details of an existing sales order
     * @param string $salesorder_id to update
     * @param array $params
     * @param bool $ignore ignore auto number generation, if true you must specify the new id, default is false
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#update-an-sales-order
     * @throws \Exception
     */
    public function updateSalesOrder($salesorder_id, array $params, $ignore = false)
    {
        return $this->curlRequest("/salesorders/{$salesorder_id}", 'PUT',
            ['JSONString' => json_encode($params)],
            ['ignore_auto_number_generation' => $ignore ? 'true' : 'false']
        );
    }

    /**
     * Retrieve a sales order
     * @param string $salesorder_id to retrieve, leave empty to list all purchase orders
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#retrieve-a-sales-order
     * @throws \Exception
     */
    public function retrieveSalesOrder($salesorder_id = null)
    {
        return $this->curlRequest("/salesorders/{$salesorder_id}");
    }

    /**
     * List all sales order
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#list-all-sales-order
     * @throws \Exception
     */
    public function listSalesOrders()
    {
        return $this->retrieveSalesOrder();
    }

    /**
     * Delete an sales order
     * @param string $salesorder_id to be deleted
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#delete-an-sales-order
     * @throws \Exception
     */
    public function deleteSalesOrder($salesorder_id)
    {
        return $this->curlRequest("/salesorders/{$salesorder_id}", 'DELETE');
    }

    /**
     * Mark as void
     * @param string $salesorder_id
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#mark-as-void68
     * @throws \Exception
     */
    public function voidSalesOrder($salesorder_id)
    {
        return $this->curlRequest("/salesorders/{$salesorder_id}/status/void", 'POST');
    }

    //************************************** Invoices *********************************************

    /**
     * create invoice
     * @param array $params
     * @param bool $ignore
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function createInvoice(array $params = [], $ignore = false)
    {
        return $this->curlRequest('/invoices', 'POST',
            ['JSONString' => json_encode($params)],
            ['ignore_auto_number_generation' => $ignore ? 'true' : 'false']
        );
    }

    /**
     * update Invoice
     * @param $invoice_id
     * @param array $params
     * @param bool $ignore
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function updateInvoice($invoice_id, array $params, $ignore = false)
    {
        return $this->curlRequest("/invoices/{$invoice_id}", 'PUT',
            ['JSONString' => json_encode($params)],
            ['ignore_auto_number_generation' => $ignore ? 'true' : 'false']
        );
    }

    /**
     * retrive a single invoice
     * @param null $invoice_id
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function retrieveInvoice($invoice_id = null)
    {
        return $this->curlRequest("/invoices/{$invoice_id}");
    }

    /**
     * list all Invoice
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function listInvoices()
    {
        return $this->retrieveInvoice();
    }

    // TODO: Implement other methods for Invoice

    //************************************** Tax *********************************************

    public function createTax($params)
    {
        return $this->curlRequest('/settings/taxes', 'POST', ['JSONString' => json_encode($params)]);
    }

    public function updateTax($tax_id, $params)
    {
        return $this->curlRequest("/settings/taxes/{$tax_id}", 'PUT', ['JSONString' => json_encode($params)]);
    }

    public function retrieveTax($tax_id = null)
    {
        return $this->curlRequest("/settings/taxes/{$tax_id}");
    }

    /**
     * List all items
     * @param array $filters an extra option used in zoho web application allows you to filter items by specific fields, leave empty to list all items<br>
     * some of available filters :<br>
     * search_text : to search about a part of text inside the item page<br>
     * filter_by   : ItemType.Sales , Status.Unmapped , Status.Active, Status.Lowstock, ItemType.Purchases, ItemType.Inventory, ItemType.NonInventory, ItemType.Service<br>
     * Pagenation arguments : page, per_page,sort_column(column name), sort_order(A/D)<br>
     * ( exmple : to filter by upc field just add to the list of parameters with the value you want to filter, you may use your custom fields also )
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#list-all-item
     * @throws \Exception
     */
    public function listTaxes(array $filters = [])
    {
        return $this->curlRequest("/settings/taxes/", 'GET', $filters);
    }


    public function searchTaxes($search_text)
    {
        return $this->listTaxes(['search_text' => $search_text]);
    }

    // TODO: implement other methods for Taxes


    //************************************** Contacts *********************************************

    /**
     * @param array $params
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#create-a-contact
     * @throws \Exception
     */
    public function createContact(array $params = [])
    {
        return $this->curlRequest("/contacts", 'POST', ['JSONString' => json_encode($params)]);
    }

    /**
     * @param string $contact_id contact id to update
     * @param array $params
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#update-an-contact
     * @throws \Exception
     */
    public function updateContact($contact_id, array $params = [])
    {
        return $this->curlRequest("/contacts/{$contact_id}", 'PUT', ['JSONString' => json_encode($params)]);
    }

    /**
     * @param string $contact_id leave empty to list all contacts
     * @param array $filters filters array, use constants STATUS_.. to filter by contact type
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#retrieve-a-contact
     * @throws \Exception
     */
    public function retrieveContact($contact_id = null, array $filters = [])
    {
        return $this->curlRequest("/contacts/{$contact_id}", 'GET', $filters);
    }

    /**
     * @param array $filters filters array, use constants STATUS_.. to filter by contact type
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#list-all-contact
     * @throws \Exception
     */
    public function listContacts(array $filters = [])
    {
        return $this->curlRequest("/contacts", 'GET', $filters);
    }

    /**
     * @param string $contact_id contact id to delete
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#delete-an-contact
     * @throws \Exception
     */
    public function deleteContact($contact_id)
    {
        return $this->curlRequest("/contacts/{$contact_id}", 'DELETE');
    }

    /**
     * @param string $contact_id contact id to active
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#mark-as-active
     * @throws \Exception
     */
    public function activeContact($contact_id)
    {
        return $this->curlRequest("/contacts/{$contact_id}/active", 'POST');
    }

    /**
     * @param string $contact_id contact id to inactive
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#mark-as-inactive
     * @throws \Exception
     */
    public function inactiveContact($contact_id)
    {
        return $this->curlRequest("/contacts/{$contact_id}/inactive", 'POST');
    }

    /**
     * List all the item adjustments
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#Item_Adjustments_Create_an_item_adjustment
     * @throws \Exception
     */
    public function listInventoryAdjustments()
    {
        return $this->curlRequest("/inventoryadjustments", 'GET');
    }

    /**
     * Creates a new item adjustment in Zoho Inventory.
     * @param $params
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#Item_Adjustments_List_all_the_item_adjustments
     * @throws \Exception
     */
    public function createInventoryAdjustments($params)
    {
        return $this->curlRequest("/inventoryadjustments", 'POST', ['JSONString' => json_encode($params)]);
    }


    /**
     * Fetches the details for an existing item adjustment.
     * @param $inventory_adjustment_id
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#Item_Adjustments_Create_an_item_adjustment
     * @throws \Exception
     */
    public function retrieveInventoryAdjustments($inventory_adjustment_id)
    {
        return $this->curlRequest("/inventoryadjustments/{$inventory_adjustment_id}", 'GET');
    }


    /**
     * delete item adjustments
     * @param $inventory_adjustment_id
     * @return \stdClass
     * @see https://www.zoho.com/inventory/api/v1/#Item_Adjustments_Create_an_item_adjustment
     * @throws \Exception
     */
    public function deleteInventoryAdjustments($inventory_adjustment_id)
    {
        return $this->curlRequest("/inventoryadjustments/{$inventory_adjustment_id}", 'DELETE');
    }

    //************************************** Others *********************************************

    /**
     * Retrieves the array of authentication configs
     * @return array
     */
    public function getAuthParams()
    {
        return ['organization_id' => $this->organizationId];
    }

    //------------------------------ Execution Functions --------------------------

    private function getUrlPath($alias, $params = [])
    {
        return 'https://' . preg_replace('/\/+/', '/', "{$this->_baseUrl}/{$alias}") . '?'
            . http_build_query(array_merge($this->getAuthParams(), $params ?: []));
    }

    /**
     * @param $alias
     * @param string $method
     * @param array $params
     * @param array $urlParams
     * @return bool|mixed|string
     * @throws \Exception
     */
    private function curlRequest($alias, $method = 'GET', array $params = [], array $urlParams = [])
    {
        $this->_curlObject = $this->initializeCurlObject();
        if ($method == 'POST') {
            curl_setopt($this->_curlObject, CURLOPT_POST, true);
        } else {
            curl_setopt($this->_curlObject, CURLOPT_CUSTOMREQUEST, $method);
        }
        if ($method == 'GET') {
            curl_setopt($this->_curlObject, CURLOPT_URL, $this->getUrlPath($alias, $params));
        } else {
            curl_setopt($this->_curlObject, CURLOPT_URL, $this->getUrlPath($alias, $urlParams));
            curl_setopt($this->_curlObject, CURLOPT_POSTFIELDS, $params);
        }
        curl_setopt($this->_curlObject,CURLOPT_HTTPHEADER,['Authorization: Bearer '.$this->accessToken]);
        return $this->execute();
    }

    private function initializeCurlObject()
    {
        $this->_curlObject = curl_init('');
        curl_setopt($this->_curlObject, CURLOPT_RETURNTRANSFER, 1);
        return $this->_curlObject;
    }

    /**
     * @return bool|mixed|string
     * @throws \Exception
     */
    private function execute()
    {
        $return = curl_exec($this->_curlObject);
        if (curl_errno($this->_curlObject)) {
            $errorNo = curl_errno($this->_curlObject);
            $errorText = curl_error($this->_curlObject);
            throw new \Exception("cUrl Error ({$errorNo}) : {$errorText}.");
        }
        curl_close($this->_curlObject);
        $return = json_decode($return);
        if ($return->code == 0) {
            $this->regenerated = false;
            return $return;
        } else {
            if($return->code == 14 && $return->message == 'Invalid value passed for authtoken.' && !$this->regenerated){
                $this->regenerateAccessToken();
                $this->execute();
            }
            throw new \Exception("Zoho Error ({$return->code}) : {$return->message}.");
        }
    }

    private function regenerateAccessToken(){
        $this->regenerated = true;
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://accounts.zoho.com/oauth/v2/token?'.http_build_query([
                    'refresh_token' => $this->refreshToken,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'redirect_uri' => $this->redirectUri,
                    'grant_type' => 'refresh_token'
                ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $response = json_decode($response,true);
        if($response && isset($response['access_token'])){
            $this->accessToken = $response['access_token'];
        }
    }
}
