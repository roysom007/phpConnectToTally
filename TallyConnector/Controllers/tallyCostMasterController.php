<?php
/*
FileName : tallyCostMasterController
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


class tallyCostMasterController extends BaseController{

	private $objTallyConnector = '';
	private $finalResponse = '';

	public function __construct(){
		$this->objTallyConnector = new tallyConnectorModel();
		$this->objTallyLogger = new tallyLoggerModel();
	}

	// Entry point to create the ledger master
	public function createCostCategoriesMaster(Request $request){
       

		// check for the header authentication
		$auth_token = $request->header('Authorization');
		if( !$this->checkAuthentication($auth_token) ){
			$finalResponse = array(
				'message'	=> 'Invalid authentication! Call aborted',
				'status'	=> 'failed',
				'code'		=> '400'
			);

			return $finalResponse;
		}

		$costCategory = $request->input('costCategory');
		$compnayFromInput = $request->input('companyName');

		// check for empty data
		if($costCategory==''){
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
							<COSTCATEGORY Action = "Create">
								<NAME>'.$costCategory.'</NAME>
								<PARENT>Sundry Debtors</PARENT>
							</COSTCATEGORY>
						</TALLYMESSAGE>
					</DATA>
				</BODY>
			</ENVELOPE>';

		// call CURL function to get tally response
		$tallyFinalResponse = $this->objTallyConnector->executeTallyCURL($requestXML);

		// call the method to add logg in MongoDB
		$inputData = array(

				'costCategory'		=>	$costCategory
			);
		// Get IP Address
		$requestIPAddress = request()->ip();
		$this->objTallyLogger->addTallyTransactionLog($requestIPAddress, $inputData, $tallyFinalResponse,'Create','COST_CATEGORY');

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
    public function editCostCategoriesMaster(Request $request){

		//print_r( $request->header('Authorization') );
        $compnayFromInput = $request->input('companyName');
		$CostCategoryOld = $request->input('CostCategoryOld');
		$CostCategoryNew = $request->input('CostCategoryNew');

		// check for empty data
		if($CostCategoryOld=='' || $CostCategoryNew==''){
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
						  	<COSTCATEGORY NAME="'.$CostCategoryOld.'" Action = "Alter">
								<NAME>'.$CostCategoryNew.'</NAME>
							</COSTCATEGORY>
						</TALLYMESSAGE>
					</DATA>
				</BODY>
			</ENVELOPE>';

		// call CURL function to get tally response
		$tallyFinalResponse = $this->objTallyConnector->executeTallyCURL($requestXML);
	

		// call the method to add logg in MongoDB
		$inputData = array(
				'CostCategoryOld'		=>	$CostCategoryOld ,
				'CostCategoryNew'       =>  $CostCategoryNew
			);
		// Get IP Address
		$requestIPAddress = request()->ip();
		$this->objTallyLogger->addTallyTransactionLog($requestIPAddress, $inputData, $tallyFinalResponse,'Edit','COST_CATEGORY');

		return $tallyFinalResponse;
	}

	// Entry point to create the ledger master
	public function createCostCentresMaster(Request $request){
         
        $compnayFromInput = $request->input('companyName'); 
		$costCategory    = $request->input('costCategory');
		$costCentresName = $request->input('costCentresName');

		// check for empty data
		if($costCentresName=='' || $costCategory==''){
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
							<COSTCENTRE Action = "Create">
								<NAME>'.$costCentresName.'</NAME>
								<CATEGORY>'.$costCategory.'</CATEGORY>
							</COSTCENTRE>
						</TALLYMESSAGE>
					</DATA>
				</BODY>
			</ENVELOPE>';
       

		// call CURL function to get tally response
		$tallyFinalResponse = $this->objTallyConnector->executeTallyCURL($requestXML);
	   // print_r($tallyFinalResponse);exit;

		// call the method to add logg in MongoDB
		$inputData = array(
				'costCentresName'   =>  $costCentresName,
				'costCategory'      =>  $costCategory
		);
		// Get IP Address
		$requestIPAddress = request()->ip();
		$this->objTallyLogger->addTallyTransactionLog($requestIPAddress, $inputData, $tallyFinalResponse,'Create','COST_CENTRE');

		return $tallyFinalResponse;
	}

	//Entry point to Edit Ledger Master 
    public function editCostCentresMaster(Request $request){

        //print_r( $request->header('Authorization') );
        $compnayFromInput = $request->input('companyName');
		$costCentresNameOld = $request->input('costCentresNameOld');
		$costCentresNameNew = $request->input('costCentresNameNew');
		$costCategory       = $request->input('costCategory');
       
		// check for empty data
		if($costCentresNameOld=='' || $costCentresNameNew=='' || $costCategory==''){
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
							<COSTCENTRE NAME="'.$costCentresNameOld.'" Action = "Edit">
								<NAME>'.$costCentresNameNew.'</NAME>
								<CATEGORY>'.$costCategory.'</CATEGORY>
							</COSTCENTRE>
						</TALLYMESSAGE>
					</DATA>
				</BODY>
			</ENVELOPE>';

		// call CURL function to get tally response
		$tallyFinalResponse = $this->objTallyConnector->executeTallyCURL($requestXML);

		// call the method to add logg in MongoDB
		$inputData = array(

			    'costCategory'              =>  $costCategory,
				'costCentresNameOld'		=>	$costCentresNameOld ,
				'costCentresNameNew'        =>  $costCentresNameNew,
				'compnayFromInput'          =>  $compnayFromInput

							);
		// Get IP Address
		$requestIPAddress = request()->ip();
		$this->objTallyLogger->addTallyTransactionLog($requestIPAddress, $inputData, $tallyFinalResponse,'Edit','COST_CENTRE');

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