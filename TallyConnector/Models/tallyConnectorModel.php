<?php
/*
FileName : tallyConnectorModel.php
Author   : eButor
Description : Making the connection with Tally.
CreatedDate : 10/Oct/2016
*/
//defining namespace
namespace App\Modules\TallyConnector\Models;

use DB;
use Session;

class tallyConnectorModel {

	public function getTallyResponse(){
		return 'working';
	}

	// Function which will take XML data as Param and communicate with Tally
	public function executeTallyCURL($requestXML, $returnRaw=0){

		$server = env('APP_TALLY_URL');
 
		$headers = array( "Content-type: text/xml", "Content-length:".strlen($requestXML) , "Connection: close");

		$ch = curl_init(); 
		curl_setopt($ch, CURLOPT_URL, $server);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 100);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $requestXML);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$data = curl_exec($ch);

		if($returnRaw==1){
			$data = str_replace("&#4;", "", $data);
		}

		$xml = simplexml_load_string($data) or die("Error: Cannot create object");
		$xml = json_decode( json_encode($xml), true );

		
		// get the final response 
		if( $returnRaw== 0){
			$finalResponse = $this->generateFinalResponse($xml);
		}else{
			$finalResponse = $xml;
		}

		return $finalResponse;
	}

	// Returns the Company Name
	public function getCompanyName(){

		$companyDetails = DB::table("legal_entities")
						->where("legal_entity_type_id", "=", "1001")
						->first();

		return $companyDetails->business_legal_name;
	}

	// Generate Tally Final response on the top of RAW Response
	private function generateFinalResponse($tallyRespRAW){

		if( isset( $tallyRespRAW[0] ) ){
			$finalResponse = array(
				'message'	=> $tallyRespRAW[0],
				'status'	=> 'failed',
				'code'		=> '400'
			);
		}elseif ( $tallyRespRAW == "Error: Cannot create object" ) {
			$finalResponse = array(
				'message'	=> $tallyRespRAW,
				'status'	=> 'failed',
				'code'		=> '400'
			);
		}elseif ( isset($tallyRespRAW['BODY']['DATA']['ERRORS']) && $tallyRespRAW['BODY']['DATA']['ERRORS']>=1 ){
			$finalResponse = array(
				'message'	=> $tallyRespRAW['BODY']['DATA']['LINEERROR'],
				'status'	=> 'failed',
				'code'		=> '401'
			);
		}elseif ( isset($tallyRespRAW['BODY']['DATA']['LINEERROR']) ) {
			$finalResponse = array(
				'message'	=> $tallyRespRAW['BODY']['DATA']['LINEERROR'],
				'status'	=> 'failed',
				'code'		=> '401'
			);
		} elseif ( isset($tallyRespRAW['BODY']['DATA']['IMPORTRESULT']) ){
			$finalResponse = array(
				'message'	=> 'Data Imported Successfully',
				'status'	=> 'Success',
				'code'		=> '200'
			);
		}

		return $finalResponse;
	}
}