<?php
/*
FileName : tallyFetchLedgerCURLController
Author   : eButor
Description :
CreatedDate : 17/Nov/2016
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

class tallyFetchLedgerCURLController extends BaseController{

	private $_objGetDBData = '';

	public function __construct(){
		$this->_objGetDBData = new tallyGetDataFromDB();
	}

	public function fetchAndStoreTallyLedger($companyName){

		// Call Tally API to get All ledger Master
		$response = $this->executeTallyAPI($companyName);

		// Store Tally Ledger master into the table
		// Build Data
		$insertData = array();
		if(isset( $response['data'] )){

			foreach($response['data'] as $ledger){
				$insertData[] = array(
					'tlm_name' 		=> $ledger['LedgerName'],
					'tlm_group' 	=> $ledger['ParentName'],
					'sync_date'		=> date('Y-m-d'),
					'sync_source'	=> 'CURL'
				); 
			}

			return $this->_objGetDBData->insertTallyLedger($insertData);

		}else{

			echo 'No Response from Server / Tally! Please Check Server/Tally URL.';
		}
	}

	// Run the tally APIs
	public function executeTallyAPI($companyName){

		$apiURL = env('APP_ENV_URL') . "tallyconnector/fetchtallyledger";
 
		$headers =  array(
		    "auth: E446F5E53AD8835EAA4FA63511E22",
		    "Content-Type: application/json"
		);

		$postFields = array(	
				'companyName'		=>	$companyName
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

		$output = json_decode($output, true);
		return $output;
	}

}