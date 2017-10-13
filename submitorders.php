<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require("functions.php");
$CurrentHour = date('H');

$dateFrom = date('Y-m-d '.$CurrentHour.':00:00');
$dateTo = date('Y-m-d '.$CurrentHour.':59:59');

$params = array(
	'filter' => array(
		array(
			'key' => 'status',
			'value' => 'complete'
		)
	),
	'complex_filter' => array(
		array(
			'key' => 'updated_at',
			'value' => array(
				'key' => 'from',
				'value' => $dateFrom
			),
		),
		array(
			'key' => 'updated_at',
			'value' => array(
				'key' => 'to',
				'value' => $dateTo
			),
		),
	)
);


$magento_orders = array();

$client = new SoapClient('MAGENTO_SOAP_API_URL');

try {
	$session = $client->login('USERNAME', 'PASSWORD');
	$magento_orders = $client->salesOrderList($session, $params);
} catch (\SoapFault $e) {
  echo $e->getMessage();
}



if(!empty($magento_orders)){

	/* Start ERP Authenticatoin Call */
	$authenticationDataResponseArray = erp_login();
	/* End ERP Authenticatoin Call */

	foreach($magento_orders as $mago){
		$ordersDataArray = array();
		$ordersDataArray['DDIRequest']['schema'] = 'SubmitOrder';
		$ordersDataArray['DDIRequest']['token'] = $authenticationDataResponseArray['DDIResponse']['token'];
		$ordersDataArray['DDIRequest']['branch'] = $authenticationDataResponseArray['DDIResponse']['branch'];
		$ordersDataArray['DDIRequest']['accountNumber'] = $authenticationDataResponseArray['account']['accountNumber'];
		$ordersDataArray['DDIRequest']['orderToken'] = $mago->increment_id;

		if(@$mago->customer_id != ""){
			$ordersDataArray['DDIRequest']['user']['userId'] = $mago->customer_id;
		}
		$ordersDataArray['DDIRequest']['user']['userName'] = $mago->customer_email;
		$ordersDataArray['DDIRequest']['user']['firstName'] = $mago->customer_firstname;
		$ordersDataArray['DDIRequest']['user']['lastName'] = $mago->customer_lastname;
		$ordersDataArray['DDIRequest']['user']['email'] = $mago->customer_email;
		

		$ordersDataArray['DDIRequest']['jobName'] = 'API Test Order - '.$mago->increment_id;
		$ordersDataArray['DDIRequest']['specialInstructions'] = 'This is test transaction to test API script. Order Id = '.$mago->order_id.' and Order Increment Id = '.$mago->increment_id;		
		$ordersDataArray['DDIRequest']['specialPayInstructions'] = 'This is test order. No need to bill this customer.';				


		$magento_order_detail = $client->salesOrderInfo($session, $mago->increment_id);
		$purchaseOrder = @$magento_order_detail->payment->po_number;	
		if($purchaseOrder == ""){
			$purchaseOrder = $mago->order_id;
		}	
		$ordersDataArray['DDIRequest']['purchaseOrder'] = $purchaseOrder;				
		$ordersDataArray['DDIRequest']['shipAddress']['shipId'] = $mago->shipping_address_id;

		$itemLoopNumber = 0;
		foreach($magento_order_detail->items as $mod){
			$ordersDataArray['DDIRequest']['lineItems']['itemData'][$itemLoopNumber]['stockNum'] = $mod->sku;
			$ordersDataArray['DDIRequest']['lineItems']['itemData'][$itemLoopNumber]['qty'] = $mod->qty_ordered;
			$ordersDataArray['DDIRequest']['lineItems']['itemData'][$itemLoopNumber]['price'] = $mod->price;
			//$ordersDataArray['DDIRequest']['lineItems']['itemData'][$itemLoopNumber]['description'] = $mod->name;			
			$itemLoopNumber++;	
		}
		
		/* Start ERP Submit Order Call */
		$json_orders_data = json_encode($ordersDataArray);
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, "APIURL_ENDPOINT");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($curl, CURLOPT_POSTFIELDS, $json_orders_data);
		$orderDataResponse = curl_exec($curl);
		curl_close($curl);
		$orderDataResponseArray = json_decode($orderDataResponse,true);
		/* End ERP Submit Order Call */
		//print_r($orderDataResponseArray);
		if($orderDataResponseArray['DDIResponse']['isValid'] == 'yes'){
			$orderNumber = $orderDataResponseArray['DDIResponse']['orderNumber'];	
			echo '<div style="margin-bottom:5px;background-color: #dff0d8;color:#3c763d;padding:5px 10px 5px 2px;5px;border-radius: 3px;width: 42%;"><strong>Success! Order Number '.$orderNumber.' is submitted in ERP.</strong></div>';	
		}
		else{
			$errorMessage = $orderDataResponseArray['DDIResponse']['errorMessage'];	
			echo '<div style="margin-bottom:5px;background-color: #f2dede;color:#a94442;padding:5px 10px 5px 2px;5px;border-radius: 3px;width: 42%;"><strong>Failed! DDI ERP Error ['.$errorMessage.']</strong></div>';					
		}
		echo "===============================================================<br/>";
	}
}
else{
	echo '<div style="margin-bottom:5px;background-color: #fcf8e3;color:#8a6d3b;padding:5px 10px 5px 2px;5px;border-radius: 3px;width: 42%;"><strong>No New Orders In Your Magento Store</strong></div>';		
}

?>
