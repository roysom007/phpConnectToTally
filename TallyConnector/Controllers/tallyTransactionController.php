<?php
/*
FileName : tallyTransactionController
Author   : eButor
Description :
CreatedDate :01/Nov/2016
*/

//defining namespace
namespace App\Modules\TallyConnector\Controllers;

//loading namespaces
use App\Http\Controllers\BaseController;

use App\Modules\TallyConnector\Models\tallyGetDataFromDB;
use Illuminate\Http\Request;
use Input;
use Log;
use Session;
use Mail;


class tallyTransactionController extends BaseController{

	private $_objGetDBData = '';

	public function __construct(){
		$this->_objGetDBData = new tallyGetDataFromDB();
	}

	public function pushTallyLedgerCreditors($companyName){

		// get Data from Supplier
		$supplierData = $this->_objGetDBData->getSupplierData();

		foreach($supplierData as $suppler){
			$ebutorSupplierName = $suppler->business_legal_name . ' - ' . $suppler->le_code;

			$address1 	= $suppler->address1;
			$address2 	= $suppler->address2;
			$city		= $suppler->city;
			$pinCode	= $suppler->pincode;
			$state		= "Telangana";

			// if Supplier code is not entered then we are not making any entry
			if( $suppler->le_code!=''){

				$response = $this->executeTallyAPI('createledgermaster', $ebutorSupplierName, $suppler->le_code, 'Sundry Creditors', $address1, $address2, $city, $pinCode, $state, $companyName);
				$response = json_decode($response, true);
				
				if(isset($response['status'])){
					
					if($response['status']=='Success'){
						// update table by 1
						$ledgerData = $this->_objGetDBData->updateLedger($suppler->legal_entity_id, 1, $response['message']);
					}else{
						//update table by 0
	                    $ledgerData = $this->_objGetDBData->updateLedger($suppler->legal_entity_id, 0, $response['message']);   
					}
				}	 
			}
		}

		return 'Tally Ledger Import is Done, Please reffer MongoDB for Details';
	}

    public function pushTallyLedgerDebtors($companyName){

		// get Data from Customer
		$customerData = $this->_objGetDBData->getCustomerData();

		foreach($customerData as $customer){
			$ebutorCustomerName = $customer->business_legal_name . '-' . $customer->le_code;
			$address1 	= $customer->address1;
			$address2 	= $customer->address2;
			$city		= $customer->city;
			$pinCode	= $customer->pincode;
			$state		= "Telangana";

			if( $customer->le_code!=''){
				$response = $this->executeTallyAPI('createledgermaster', $ebutorCustomerName, $customer->le_code, 'Sundry Debtors', $address1, $address2, $city, $pinCode, $state, $companyName);

				$response = json_decode($response, true);
				
				if(isset($response['status'])){
					
					if($response['status']=='Success'){
						// update table by 1
						$ledgerData = $this->_objGetDBData->updateLedger($customer->legal_entity_id, 1, $response['message']);
					}else{
						//update table by 0
	                    $ledgerData = $this->_objGetDBData->updateLedger($customer->legal_entity_id, 0, $response['message']);   
					}
				}
			}
			
		}

		return 'Tally Ledger Import is Done, Please reffer MongoDB for Details';
	}

	// Run the tally APIs
	public function executeTallyAPI($callerFunction, $ledgerName, $aliasName,$ledgerPrnt, $address1, $address2, $city, $pinCode, $state, $companyName){

		$apiURL = env("APP_ENV_URL") . "tallyconnector/".$callerFunction;
 
		$headers =  array(
		    "auth: E446F5E53AD8835EAA4FA63511E22",
		    "Content-Type: application/json"
		);

		$postFields = array(
				'parentDC'			=>	$ledgerPrnt,
				'ledgerName'		=>	$ledgerName,
				'aliasName'			=>	$aliasName,
				'openingBalance'	=>	'0',
				'address1'			=>	$address1,
				'address2'			=>	$address2,
				'city'				=>	$city,
				"pinCode"			=>	$pinCode,
				"state"				=>	$state,
				'companyName'		=> 	$companyName
			);

		$postFields = json_encode($postFields);

		$ch = curl_init();  

		curl_setopt($ch,CURLOPT_URL,$apiURL);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($ch, CURLOPT_POST, count($postFields));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);   
		 
		$output=curl_exec($ch);
		curl_close($ch);

		return $output;
	}

	public function sendMailForLedger($emailList){

		// Retrive all unImported data from Legal Entry Tanble
		$allPendingData = $this->_objGetDBData->getUnImportedLedgerData(); 

		// Creating the mail Content
		$iDs = '';
		$mailContent = "<table border='1' cellspacing='0' cellpadding='5'>";
		$mailContent .= "<tr bgcolor='#efefef'>";
		$mailContent .= "<th>LE ID</th>";
		$mailContent .= "<th>LE TYPE</th>";
		$mailContent .= "<th>LE NAME</th>";
		$mailContent .= "<th>TALLY RESPONSE</th>";
		$mailContent .= "</tr>";
		foreach ($allPendingData as $pData) {

			$le_type = $pData->legal_entity_type_id=='1002' ? 'Supplier' : 'Customer';

			$mailContent .= "<tr>";
			$mailContent .= "<td>".$pData->legal_entity_id."</td>";
			$mailContent .= "<td>".$le_type."</td>";
			$mailContent .= "<td>".$pData->business_legal_name."</td>";
			$mailContent .= "<td>".$pData->tally_resp."</td>";
			$mailContent .= "</tr>";

			$iDs .= $pData->legal_entity_id . ',';
		}

		if($iDs==''){
			$mailContent .= "<tr>";
			$mailContent .= "<td colspan='4'><b>All Ledgers Imported Successfully<b></td>";
			$mailContent .= "</tr>";
		}

		$mailContent .= "</table>";

		$mailContent .= "<br><br>";
		$mailContent .= "<b>IDs : " . $iDs . "</b>";

		$toEmails = explode(",", $emailList);

		Mail::send('emails.tallyImportStatusMail', ['emailContent' => $mailContent], function ($message) use ($toEmails) {
			$message->to($toEmails);
			$message->subject('Tally Import Status For Ledger as on - ' . date('d-m-Y') );
		});
	}

}