<?php
function erp_login(){
	$authenticationDataArray = array();
	$authenticationDataArray['DDIRequest']['schema'] = 'Login';
	$authenticationDataArray['DDIRequest']['username'] = 'YOURUSERNAME';
	$authenticationDataArray['DDIRequest']['password'] = 'YOURPASSWORD';
	$json_authentication_data = json_encode($authenticationDataArray);
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, "APIURL_ENDPOINT");
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	curl_setopt($curl, CURLOPT_POSTFIELDS, $json_authentication_data);
	$authenticationDataResponse = curl_exec($curl);
	curl_close($curl);
	$authenticationDataResponseArray = json_decode($authenticationDataResponse,true);	
	return $authenticationDataResponseArray;
}
?>
