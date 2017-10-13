<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require("functions.php");

$params = array('complex_filter' => array(array('key' => 'entity_id','value' => array('key' => 'gt','value' => '9481'))));

$client = new SoapClient('MAGENTO_SOAP_API_URL');
$session = $client->login('USERNAME', 'PASSWORD');

/* Start Get Magento Stock Data */
$productsList = $client->catalogProductList($session,$params);
$productsIds = array();
$loop = 0;
foreach($productsList as $plist){
	$productsIds[$loop] = $plist->product_id;
	$loop++;		
}
$catalogInventoryStockItemList = $client->catalogInventoryStockItemList($session, $productsIds);
/* End Get Magento Stock Data */

/* Start ERP Authenticatoin Call */
$authenticationDataResponseArray = erp_login();
/* End ERP Authenticatoin Call */


$stockDataBatch = array();
$stockDataBatch['DDIRequest']['schema'] = 'PriceStock';
$stockDataBatch['DDIRequest']['token'] = $authenticationDataResponseArray['DDIResponse']['token'];
$stockDataBatch['DDIRequest']['branch'] = $authenticationDataResponseArray['DDIResponse']['branch'];
$stockDataBatch['DDIRequest']['accountNumber'] = $authenticationDataResponseArray['account']['accountNumber'];
$stockDataBatch['DDIRequest']['allWarehouse'] = 'N';

$looperz = 0;
foreach($catalogInventoryStockItemList as $cat){
	$stockNum = @$cat->sku;
	if($stockNum != ""){
		$stockDataBatch['DDIRequest']['itemList'][$looperz]['quantity'] = '50';
		$stockDataBatch['DDIRequest']['itemList'][$looperz]['stockNum'] = $stockNum;		
		$looperz++;	
	}	
}


/* Start ERP Price and Stock Call */
$json_stock_data = json_encode($stockDataBatch);
$stockDataResponse = "";
$stockDataResponseArray = array();
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, "APIURL_ENDPOINT");
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($curl, CURLOPT_POSTFIELDS, $json_stock_data);
$stockDataResponse = curl_exec($curl);
curl_close($curl);
$stockDataResponseArray = json_decode($stockDataResponse,true);
/* End ERP Price and Stock Call */

if($stockDataResponseArray['DDIResponse']['isValid'] == 'yes'){
	
	$stockItemData = $stockDataResponseArray['DDIResponse']['itemData'];
	
	/* Start Update Magento Stock Qty */

	foreach($stockItemData	as $sdra){
		$requestNum = @$sdra['lineItem']['requestNum'];
		$errorMessage = @$sdra['lineItem']['errorMessage'];			
		if($errorMessage == ""){
			$stockNumR = @$sdra['lineItem']['stockNum'];			
			if($stockNumR != ""){
				$available = @$sdra['lineItem']['onHand']['available'];
				echo '<div style = "margin-bottom:5px;"><span>Requested SKU: <strong>'.$requestNum.'</strong></span></div>';
				if($available != 'NA'){
					$stockItemData = array(
						'qty' => $available,
						'manage_stock' => 1,
						'use_config_manage_stock' => 0,
						'enable_qty_increments' => 1,
						'use_config_enable_qty_increments' => 0,
						'is_in_stock' => 1
					);					
					try {
						$resultUpQty = $client->catalogInventoryStockItemUpdate($session,$requestNum,$stockItemData);
						echo '<div style="margin-bottom:5px;background-color: #dff0d8;color:#3c763d;padding:5px 10px 5px 2px;5px;border-radius: 3px;width: 42%;"><strong>Success! Quantity Updated In Magento.</strong></div>';				
					} catch (\SoapFault $e) {
						echo '<div style="margin-bottom:5px;background-color: #f2dede;color:#a94442;padding:5px 10px 5px 2px;5px;border-radius: 3px;width: 42%;"><strong>Failed! Magento Error While Updating Quantity ['.$e->getMessage().']</strong></div>';	
					}							
				}
				else{
					echo '<div style="margin-bottom:5px;background-color: #f2dede;color:#a94442;padding:5px 10px 5px 2px;5px;border-radius: 3px;width: 42%;"><strong>Failed! Stock Not Available in DDI ERP. Cannot Update Quantity in Magento.</strong></div>';				
				}
				$listPrice = @$sdra['lineItem']['listPrice'];	
				if($listPrice != '' && $listPrice > 0){
					try {
						$resultUpPrice = $client->catalogProductUpdate($session, $requestNum, array('price' => $listPrice));
						echo '<div style="margin-bottom:5px;background-color: #dff0d8;color:#3c763d;padding:5px 10px 5px 2px;5px;border-radius: 3px;width: 42%;"><strong>Success! Price Updated In Magento.</strong></div>';	
					} catch (\SoapFault $e) {
						echo '<div style="margin-bottom:5px;background-color: #f2dede;color:#a94442;padding:5px 10px 5px 2px;5px;border-radius: 3px;width: 42%;"><strong>Failed! Magento Error While Updating Price ['.$e->getMessage().']</strong></div>';			  
					}			
				}
				else{
					echo '<div style="margin-bottom:5px;background-color: #f2dede;color:#a94442;padding:5px 10px 5px 2px;5px;border-radius: 3px;width: 42%;"><strong>Failed! List price is either empty or 0 returned by DDI ERP.</strong></div>';					
				}
			}
			else{
				echo '<div style = "margin-bottom:5px;"><span>Requested SKU: <strong>'.$requestNum.'</strong></span></div>';
				echo '<div style="margin-bottom:5px;background-color: #f2dede;color:#a94442;padding:5px 10px 5px 2px;5px;border-radius: 3px;width: 42%;"><strong>Failed! DDI ERP returned no stock number</strong></div>';
			}
		
		}
		else{
			echo '<div style = "margin-bottom:5px;"><span>Requested SKU: <strong>'.$requestNum.'</strong></span></div>';
			echo '<div style="margin-bottom:5px;background-color: #f2dede;color:#a94442;padding:5px 10px 5px 2px;5px;border-radius: 3px;width: 42%;"><strong>Failed! DDI ERP Error ['.$errorMessage.']</strong></div>';			
		}
		echo "===============================================================<br/>";
	}
	/* End Update Magento Stock Qty */	
	
}
else{
	echo '<strong style="background-color: #f2dede;color:#a94442;padding:3px 8px;5px;border-radius: 3px;">Failed! Invalid Request</strong>';
	echo '<br/>';
}

?>
