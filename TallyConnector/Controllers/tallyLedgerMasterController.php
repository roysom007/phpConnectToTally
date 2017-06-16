<?php
/*
FileName : tallyLedgerMasterController
Author   : eButor
Description :
CreatedDate :19/Oct/2016
*/

//defining namespace
namespace App\Modules\TallyConnector\Controllers;

//loading namespaces
use App\Http\Controllers\BaseController;
use App\Modules\TallyConnector\Models\tallyConnectorModel;
use App\Modules\TallyConnector\Models\tallyLoggerModel;

use Illuminate\Http\Request;
use Input;
use Log;
use Session;


class tallyLedgerMasterController extends BaseController{

	private $objTallyConnector = '';
	private $finalResponse = '';

	public function __construct(){
		$this->objTallyConnector = new tallyConnectorModel();
		$this->objTallyLogger = new tallyLoggerModel();
	}

	// Entry point to create the ledger master
	public function createLedgerGroupMaster(Request $request){
       
		// check for the header authentication
		$auth_token = $request->header('auth');
		if( !$this->checkAuthentication($auth_token) ){
			$finalResponse = array(
				'message'	=> 'Invalid authentication! Call aborted',
				'status'	=> 'failed',
				'code'		=> '400'
			);

			return $finalResponse;
		}

		$dcInfor = $request->input('ebutorDC');
		$compnayFromInput = $request->input('companyName');


		// check for empty data
		if($dcInfor==''){
			$finalResponse = array(
				'message'	=> 'API Argument does not match, Call aborted!',
				'status'	=> 'failed',
				'code'		=> '401'
			);

			return $finalResponse;
		}

		// Prepare XML to create Ledger Master
		$headerPart = $this->prepareHeaderPart($compnayFromInput);
		$requestXML = $headerPart . '
					<DATA>
						<TALLYMESSAGE>
							<GROUP Action = "Create">
								<NAME>'.$dcInfor.'</NAME>
								<PARENT>Sundry Debtors</PARENT>
							</GROUP>
						</TALLYMESSAGE>
					</DATA>
				</BODY>
			</ENVELOPE>';

		// call CURL function to get tally response
		$tallyFinalResponse = $this->objTallyConnector->executeTallyCURL($requestXML);

		// call the method to add logg in MongoDB
		$inputData = array(
				'ebutorDC'		=>	$dcInfor
			);
		// Get IP Address
		$requestIPAddress = request()->ip();
		$this->objTallyLogger->addTallyTransactionLog($requestIPAddress, $inputData, $tallyFinalResponse,'Create','LEDGER_GROUP');

		return $tallyFinalResponse;
	}
    
    public function checkAuthentication($auth_token)
    {
    	if( $auth_token=='E446F5E53AD8835EAA4FA63511E22' ){
    		return true;
    	}else{
    		return false;
    	}
    }

    //Code to Edit Group Ledger Master
    public function editLedgerGroupMaster(Request $request){

        $compnayFromInput = $request->input('companyName');
		$ebutorDCOld = $request->input('ebutorDCOld');
		$ebutorDCNew = $request->input('ebutorDCNew');


		// check for empty data
		if($ebutorDCOld=='' || $ebutorDCNew==''){
			$finalResponse = array(
				'message'	=> 'API Argument does not match, Call aborted!',
				'status'	=> 'failed',
				'code'		=> '401'
			);

			return $finalResponse;
		}

		// Prepare XML to create Ledger Master
		$headerPart = $this->prepareHeaderPart($compnayFromInput);
		$requestXML = $headerPart . '
					<DATA>
						<TALLYMESSAGE>
						  	<GROUP NAME="'.$ebutorDCOld.'" Action = "Alter">
								<NAME>'.$ebutorDCNew.'</NAME>
							</GROUP>
						</TALLYMESSAGE>
					</DATA>
				</BODY>
			</ENVELOPE>';

		// call CURL function to get tally response
		$tallyFinalResponse = $this->objTallyConnector->executeTallyCURL($requestXML);
	    

		// call the method to add logg in MongoDB
		$inputData = array(
				'ebutorDCOld'		=>	$ebutorDCOld ,
				'ebutorDCNew'       =>  $ebutorDCNew
			);

		// Get IP Address
		$requestIPAddress = request()->ip();
		$this->objTallyLogger->addTallyTransactionLog($requestIPAddress, $inputData, $tallyFinalResponse,'Edit','LEDGER_GROUP');

		return $tallyFinalResponse;
	}

	// Entry point to create the ledger master
	public function createLedgerMaster(Request $request){
         
        $compnayFromInput 	= str_replace("&", "&amp;", $request->input('companyName')); 
		$parentDC 			= str_replace("&", "&amp;", $request->input('parentDC'));
		$ledgerName 		= str_replace("&", "&amp;", $request->input('ledgerName'));
		$openingBalance 	= $request->input('openingBalance');
		$aliasName 			= str_replace("&", "&amp;", $request->input('aliasName'));

		$address1			= str_replace("&", "&amp;", $request->input('address1'));
		$address2			= str_replace("&", "&amp;", $request->input('address2'));
		$city				= str_replace("&", "&amp;", $request->input('city'));
		$pinCode			= $request->input('pinCode');
		$stateName			= str_replace("&", "&amp;", $request->input('state'));

		$aliasName = $aliasName!='' ? '<NAME TYPE="String">'.trim($aliasName).'</NAME>' : '</NAME>';

		$address1  = $address1!='' ? '<ADDRESS>'.trim($address1).'</ADDRESS>' : '';
		$address2  = $address2!='' ? '<ADDRESS>'.trim($address2).'</ADDRESS>' : '';
        $city      = $city!='' ? '<ADDRESS>'.trim($city).'</ADDRESS>' : '';

		// check for empty data
		if($ledgerName=='' || $parentDC==''){
			$finalResponse = array(
				'message'	=> 'API Argument does not match, Call aborted!',
				'status'	=> 'failed',
				'code'		=> '401'
			);

			return $finalResponse;
		}

		// Prepare XML to create Ledger Master
		$headerPart = $this->prepareHeaderPart($compnayFromInput);
		$requestXML = $headerPart . '
					<DATA>
						<TALLYMESSAGE>
							<LEDGER NAME="'.trim($ledgerName).'" Action = "Alter">
								<NAME.LIST TYPE="String">
									<NAME TYPE="String">'.trim($ledgerName).'</NAME>
									'.$aliasName.'
								</NAME.LIST>
								<PARENT>'.trim($parentDC).'</PARENT>
								<OPENINGBALANCE>'.trim($openingBalance).'</OPENINGBALANCE>
								<ISCOSTCENTRESON>Yes</ISCOSTCENTRESON>
								<ADDRESS.LIST>
									'.$address1.
									$address2.
									$city.'
								</ADDRESS.LIST>
								<STATENAME>'.trim($stateName).'</STATENAME>
								<PINCODE>'.trim($pinCode).'</PINCODE>
								<ADDITIONALNAME>'.trim($ledgerName).'</ADDITIONALNAME>
							</LEDGER>
						</TALLYMESSAGE>
					</DATA>
				</BODY>
			</ENVELOPE>';

		// call CURL function to get tally response
		$tallyFinalResponse = $this->objTallyConnector->executeTallyCURL($requestXML);

		// call the method to add logg in MongoDB
		$inputData = array(
				'suplierName'		=>	$ledgerName ,
				'parentDC'          =>  $parentDC,
				'openingBalance'    =>  $openingBalance
			);
		// Get IP Address
		$requestIPAddress = request()->ip();
		$this->objTallyLogger->addTallyTransactionLog($requestIPAddress, $inputData, $tallyFinalResponse,'Create','LEDGER_MASTER');

		return $tallyFinalResponse;
	}

	//Entry point to Edit Ledger Master 
    public function editLedgerMaster(Request $request){

        //print_r( $request->header('Authorization') );
        $compnayFromInput = $request->input('companyName');
		$suplierNameOld = $request->input('suplierNameOld');
		$suplierNameNew = $request->input('suplierNameNew');

		
		// check for empty data
		if($suplierNameOld=='' || $suplierNameNew=='' ){
			$finalResponse = array(
				'message'	=> 'API Argument does not match, Call aborted!',
				'status'	=> 'failed',
				'code'		=> '401'
			);

			return $finalResponse;
		}

		// Prepare XML to Edit Ledger Master
		$headerPart = $this->prepareHeaderPart($compnayFromInput);
		$requestXML = $headerPart . '
						<DATA>
						<TALLYMESSAGE>
						<NAME.LIST TYPE="String">
					            <NAME TYPE="String">'.$suplierNameOld.'</NAME>
								<NAME TYPE="String">'.$suplierNameNew.'</NAME>
								'.$aliasName.'
								</NAME.LIST>
							<LEDGER NAME="'.$suplierNameOld.'" Action = "Edit">
								<NAME>'.$suplierNameNew.'</NAME>
							</LEDGER>
						</TALLYMESSAGE>
					</DATA>
				</BODY>
			</ENVELOPE>';

		// call CURL function to get tally response
		$tallyFinalResponse = $this->objTallyConnector->executeTallyCURL($requestXML);

		// call the method to add logg in MongoDB
		$inputData = array(
				'suplierNameOld'		=>	$suplierNameOld ,
				'suplierNameNew'        =>  $suplierNameNew
							);
		// Get IP Address
		$requestIPAddress = request()->ip();
		$this->objTallyLogger->addTallyTransactionLog($requestIPAddress, $inputData, $tallyFinalResponse,'Edit','LEDGER_MASTER');

		return $tallyFinalResponse;
	}

	// Preparing the common header part of Tally Ledger XML
	private function prepareHeaderPart($compnayFromInput){

		// Get Company Name
		$currentCompanyName = trim($compnayFromInput);
		if($currentCompanyName==""){
			$currentCompanyName = $this->objTallyConnector->getCompanyName();
		}

		$xmlLedgerHeader = '
		<ENVELOPE>
				<HEADER>
					<VERSION>1</VERSION>
					<TALLYREQUEST>Import</TALLYREQUEST>
					<TYPE>Data</TYPE>
					<ID>All Masters</ID>
				</HEADER>
				<BODY>
					<DESC>
						<STATICVARIABLES>
							<SVCURRENTCOMPANY>'.$currentCompanyName.'</SVCURRENTCOMPANY>
						</STATICVARIABLES>
					</DESC>';
		return $xmlLedgerHeader;
	}
}