<?php
/*
FileName : tallyFetchLedgerMasterController
Author   : eButor
Description :
CreatedDate :17/Nov/2016
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

class tallyFetchLedgerMasterController extends BaseController{

	private $objTallyConnector = '';
	private $finalResponse = '';

	public function __construct(){
		$this->objTallyConnector = new tallyConnectorModel();
		$this->objTallyLogger = new tallyLoggerModel();
	}

	public function checkAuthentication($auth_token){
    	if( $auth_token=='E446F5E53AD8835EAA4FA63511E22' ){
    		return true;
    	}else{
    		return false;
    	}
    }

    // Entry point to create the ledger master
	public function fetchLedgerMaster(Request $request){

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
         
        $compnayFromInput 	= str_replace("&", "&amp;", $request->input('companyName'));
		// Get Company Name
		$currentCompanyName = trim($compnayFromInput);
		if($currentCompanyName==""){
			$currentCompanyName = $this->objTallyConnector->getCompanyName();
		}

		// Prepare XML to Fetch Ledger Master
		$requestXML = '
				<ENVELOPE>
					<HEADER>
						<VERSION>1</VERSION>
						<TALLYREQUEST>EXPORT</TALLYREQUEST>
						<TYPE>COLLECTION</TYPE>
						<ID>Remote Ledger Coll</ID>
					</HEADER>
					<BODY>
						<DESC>
							<STATICVARIABLES>
								<SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>
								<SVCURRENTCOMPANY>'.$currentCompanyName.'</SVCURRENTCOMPANY>
							</STATICVARIABLES>
							<TDL>
								<TDLMESSAGE>
									<COLLECTION NAME="Remote Ledger Coll" ISINITIALIZE="Yes">
										<TYPE>Ledger</TYPE>
										<NATIVEMETHOD>Name</NATIVEMETHOD>
										<NATIVEMETHOD>OpeningBalance</NATIVEMETHOD>
										<NATIVEMETHOD>Parent</NATIVEMETHOD>
									</COLLECTION>
								</TDLMESSAGE>
							</TDL>
						</DESC>
					</BODY>
				</ENVELOPE>';

		// call CURL function to get tally response
		$tallyFinalResponse = $this->objTallyConnector->executeTallyCURL($requestXML, 1);

		// Convert Data into Proper Array Format
		$tallyAllLedger = array();
		if( isset($tallyFinalResponse['BODY']['DATA']['COLLECTION']['LEDGER']) ){
			$tallyFinalResponse = $tallyFinalResponse['BODY']['DATA']['COLLECTION']['LEDGER'];
			$loop_couneter=0;
			foreach($tallyFinalResponse as $data){
				$tallyAllLedger[$loop_couneter]['LedgerName'] = $data['@attributes']['NAME'];
				$tallyAllLedger[$loop_couneter]['ParentName'] = $data['PARENT'];
				$loop_couneter++;
			}			
		}

		// call the method to add logg in MongoDB
		$inputData = array();

		// Get IP Address
		$requestIPAddress = request()->ip();
		//$this->objTallyLogger->addTallyTransactionLog($requestIPAddress, $inputData, "",'Fetch','LEDGER_MASTER');

		// Making the final response
		$finalResponse = array(
			'message'	=> 'Ledger Imported Successfully!',
			'status'	=> 'Success',
			'code'		=> '200',
			'data'		=> $tallyAllLedger
		);

		return $finalResponse;
	}

	// Fetch Ledger from Tally with Balance Details
	public function fetchLedgerWithBalance(Request $request){

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

		$compnayFromInput 	= str_replace("&", "&amp;", $request->input('companyName'));
		
		// Get Company Name
		$currentCompanyName = trim($compnayFromInput);
		if($currentCompanyName==""){
			$currentCompanyName = $this->objTallyConnector->getCompanyName();
		}

		$startDate = $request->input('startDate');
		$toDate = $request->input('toDate');

		// Prepare XML to Fetch Ledger Master
		$requestXML = '
				<ENVELOPE>
			        <HEADER>
						<VERSION>1</VERSION>
						<TALLYREQUEST>EXPORT</TALLYREQUEST>
						<TYPE>COLLECTION</TYPE>
						<ID>Remote Ledger Coll</ID>
			        </HEADER>
			        <BODY>
						<DESC>
							<STATICVARIABLES>
								<SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>
								<SVCURRENTCOMPANY>'.$compnayFromInput.'</SVCURRENTCOMPANY>
								<SVFROMDATE TYPE="Date">'.$startDate.'</SVFROMDATE>
								<SVTODATE TYPE="Date">'.$toDate.'</SVTODATE>
								</STATICVARIABLES>
							<TDL>
								<TDLMESSAGE>
									<COLLECTION NAME="Remote Ledger Coll" ISINITIALIZE="Yes">
										<TYPE>Ledger</TYPE>
										<NATIVEMETHOD>Name</NATIVEMETHOD>
										<NATIVEMETHOD>OpeningBalance</NATIVEMETHOD>
										<NATIVEMETHOD>ClosingBalance</NATIVEMETHOD>
										<NATIVEMETHOD>Parent</NATIVEMETHOD>
									</COLLECTION>
								</TDLMESSAGE>
							</TDL>
						</DESC>
					</BODY>
			</ENVELOPE>';

		/*echo $requestXML;exit;*/

		// call CURL function to get tally response
		$tallyFinalResponse = $this->objTallyConnector->executeTallyCURL($requestXML, 1);

		// Convert Data into Proper Array Format
		$tallyAllVouchers = array();
		$loop_couneter=0;
		$tallyFinalResponse = $tallyFinalResponse['BODY']['DATA']['COLLECTION']['LEDGER'];

		/*echo '<pre>';
		print_r($tallyFinalResponse);
		exit;*/

		// Loop through the data
		$salesTotal = 0;
		$salesReturnTotal = 0;
		$purchaseTotal = 0;
		$purchaseVatTotal = 0;
		$salesVatTotal = 0;
		$disCountOnPurchase = 0;

		$newArr = array();

		foreach($tallyFinalResponse as $data){

			$ClosingBalance = isset($data['CLOSINGBALANCE']) ? $data['CLOSINGBALANCE'] : "0";
			$OpeningBalance = isset($data['OPENINGBALANCE']) ? $data['OPENINGBALANCE'] : "0";

			if( !is_array($ClosingBalance) ){
				$ClosingBalance = isset($data['CLOSINGBALANCE']) ? (float)$data['CLOSINGBALANCE'] : "0";
			}else{
				$ClosingBalance = 0;
			}

			if( !is_array($OpeningBalance) ){
				$OpeningBalance = isset($data['OPENINGBALANCE']) ? (float)$data['OPENINGBALANCE'] : "0";
			}else{
				$OpeningBalance = 0;
			}

			// Actual Balance
			$ClosingBalance = $ClosingBalance - $OpeningBalance;

			// For Sales Account
			if( $data['PARENT'] == "Sales Accounts" && $ClosingBalance>0 ){
				$tallyAllVouchers[$loop_couneter]['Type']				= 	isset($data['@attributes']['NAME']) ? $data['@attributes']['NAME'] : 'NotFound';
				$tallyAllVouchers[$loop_couneter]['PARENT']				= 	isset($data['PARENT']) ? $data['PARENT'] : "";
				$tallyAllVouchers[$loop_couneter]['OPENINGBALANCE']		=	isset($data['OPENINGBALANCE']) ? $data['OPENINGBALANCE'] : "";
				$tallyAllVouchers[$loop_couneter]['CLOSINGBALANCE']		=	isset($data['CLOSINGBALANCE']) ? $data['CLOSINGBALANCE'] : "";
				$loop_couneter++;
				$salesTotal = $salesTotal+$ClosingBalance;
			}

			// For Sales Return Account
			if( $data['PARENT'] == "Sales Accounts" && $ClosingBalance<0 ){
				$tallyAllVouchers[$loop_couneter]['Type']				= 	isset($data['@attributes']['NAME']) ? $data['@attributes']['NAME'] : 'NotFound';
				$tallyAllVouchers[$loop_couneter]['PARENT']				= 	isset($data['PARENT']) ? $data['PARENT'] : "";
				$tallyAllVouchers[$loop_couneter]['OPENINGBALANCE']		=	isset($data['OPENINGBALANCE']) ? $data['OPENINGBALANCE'] : "";
				$tallyAllVouchers[$loop_couneter]['CLOSINGBALANCE']		=	isset($data['CLOSINGBALANCE']) ? $data['CLOSINGBALANCE'] : "";
				$loop_couneter++;
				$salesReturnTotal = $salesReturnTotal+$ClosingBalance;
			}

			// For Purchase Account
			if( $data['PARENT'] == "Purchase Accounts" && $ClosingBalance<0 ){
				$tallyAllVouchers[$loop_couneter]['Type']				= 	isset($data['@attributes']['NAME']) ? $data['@attributes']['NAME'] : 'NotFound';
				$tallyAllVouchers[$loop_couneter]['PARENT']				= 	isset($data['PARENT']) ? $data['PARENT'] : "";
				$tallyAllVouchers[$loop_couneter]['OPENINGBALANCE']		=	isset($data['OPENINGBALANCE']) ? $data['OPENINGBALANCE'] : "";
				$tallyAllVouchers[$loop_couneter]['CLOSINGBALANCE']		=	isset($data['CLOSINGBALANCE']) ? $data['CLOSINGBALANCE'] : "";
				$loop_couneter++;
				$purchaseTotal = $purchaseTotal+$ClosingBalance;
			}

			// For Sales VAT Account
			if( ($data['@attributes']['NAME'] == "Input TS VAT @14.5%") || ($data['@attributes']['NAME'] == "Input TS VAT @5%") ){

				$tallyAllVouchers[$loop_couneter]['Type']				= 	isset($data['@attributes']['NAME']) ? $data['@attributes']['NAME'] : 'NotFound';
				$tallyAllVouchers[$loop_couneter]['PARENT']				= 	isset($data['PARENT']) ? $data['PARENT'] : "";
				$tallyAllVouchers[$loop_couneter]['OPENINGBALANCE']		=	isset($data['OPENINGBALANCE']) ? $data['OPENINGBALANCE'] : "";
				$tallyAllVouchers[$loop_couneter]['CLOSINGBALANCE']		=	isset($data['CLOSINGBALANCE']) ? $data['CLOSINGBALANCE'] : "";
				$loop_couneter++;
				$purchaseVatTotal = $purchaseVatTotal+$ClosingBalance;
			}

			// For Purchase VAT Account
			if( ($data['@attributes']['NAME'] == "Output TS VAT @14.5%") || ($data['@attributes']['NAME'] == "Output TS VAT @5%") ){

				$tallyAllVouchers[$loop_couneter]['Type']				= 	isset($data['@attributes']['NAME']) ? $data['@attributes']['NAME'] : 'NotFound';
				$tallyAllVouchers[$loop_couneter]['PARENT']				= 	isset($data['PARENT']) ? $data['PARENT'] : "";
				$tallyAllVouchers[$loop_couneter]['OPENINGBALANCE']		=	isset($data['OPENINGBALANCE']) ? $data['OPENINGBALANCE'] : "";
				$tallyAllVouchers[$loop_couneter]['CLOSINGBALANCE']		=	isset($data['CLOSINGBALANCE']) ? $data['CLOSINGBALANCE'] : "";
				$loop_couneter++;
				$salesVatTotal = $salesVatTotal+$ClosingBalance;
			}

			// For Discount on Purchase
			if( ($data['PARENT'] == "Indirect Incomes") || ($data['@attributes']['NAME'] == "Discount Receivables") ){

				$tallyAllVouchers[$loop_couneter]['Type']				= 	isset($data['@attributes']['NAME']) ? $data['@attributes']['NAME'] : 'NotFound';
				$tallyAllVouchers[$loop_couneter]['PARENT']				= 	isset($data['PARENT']) ? $data['PARENT'] : "";
				$tallyAllVouchers[$loop_couneter]['OPENINGBALANCE']		=	isset($data['OPENINGBALANCE']) ? $data['OPENINGBALANCE'] : "";
				$tallyAllVouchers[$loop_couneter]['CLOSINGBALANCE']		=	isset($data['CLOSINGBALANCE']) ? $data['CLOSINGBALANCE'] : "";
				$loop_couneter++;
				$disCountOnPurchase = $disCountOnPurchase+$ClosingBalance;
			}

			//$newArr[]= $data['PARENT'] . '======' . $data['@attributes']['NAME'];
		}

		// Making the final response
		$finalResponse = array(
			'message'					=> 'Ledger Imported Successfully!',
			'status'					=> 'Success',
			'code'						=> '200',
			'data'						=> $tallyAllVouchers,
			'salesTotal'				=> $salesTotal,
			'salesReturnTotal'			=> $salesReturnTotal,
			'purchaseTotal'				=> $purchaseTotal,
			'purchaseVATTotal'			=> $purchaseVatTotal,
			'salesVATTotal'				=> $salesVatTotal,
			'disCountOnPurchase'		=> $disCountOnPurchase
		);

		/*print_r($finalResponse);
		exit;*/

		// call the method to add logg in MongoDB
		//$requestIPAddress = request()->ip();
		//$this->objTallyLogger->addTallyTransactionLog($requestIPAddress, $finalResponse, "",'Fetch','LEDGER_MASTER_WITH_BALANCE');

		return $finalResponse;
	}

	// Fetch all the Voucher from Tally ( Not in use now )
	public function fetchVoucherDetails(Request $request){

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
         
        $compnayFromInput 	= str_replace("&", "&amp;", $request->input('companyName'));
		// Get Company Name
		$currentCompanyName = trim($compnayFromInput);
		if($currentCompanyName==""){
			$currentCompanyName = $this->objTallyConnector->getCompanyName();
		}

		$startDate 	= $request->input('startDate');
		$endDate 	= $request->input('endDate');

		// Prepare XML to Fetch Ledger Master
		$requestXML = '
		<ENVELOPE>
		    <HEADER>
		        <VERSION>1</VERSION>
		        <TALLYREQUEST>Export</TALLYREQUEST>
		        <TYPE>Collection</TYPE>
		        <ID>Sales</ID>
		    </HEADER>
			<BODY>
			    <DESC>
			        <STATICVARIABLES>
			        	<SVCURRENTCOMPANY>'.$currentCompanyName.'</SVCURRENTCOMPANY>
			            <SVFROMDATE TYPE="Date">'.$startDate.'</SVFROMDATE>
			            <SVTODATE TYPE="Date">'.$endDate.'</SVTODATE>
			            <EXPLODEFLAG>Yes</EXPLODEFLAG>
			            <SVVOUCHERTYPENAME>Receipt</SVVOUCHERTYPENAME>
			            <SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>                
			        </STATICVARIABLES>
			        <TDL>
			            <TDLMESSAGE>
			                <COLLECTION NAME="Sales" ISMODIFY="No">
			                    <TYPE>Voucher</TYPE>      
			                    <FETCH>Vouchernumber</FETCH>
			                    <FETCH>VoucherTypeName</FETCH>
			                    <FETCH>LedgerName</FETCH>
			                    <FETCH>Amount</FETCH>
			                    <FETCH>REFERENCE</FETCH>
			                </COLLECTION>
			            </TDLMESSAGE>
			        </TDL>
			    </DESC>                                
			</BODY>
		</ENVELOPE>     
		';

		// call CURL function to get tally response
		$tallyFinalResponse = $this->objTallyConnector->executeTallyCURL($requestXML, 1);

		// return $tallyFinalResponse;
		// exit;

		// Convert Data into Proper Array Format
		$tallyAllVouchers = array();
		if( isset($tallyFinalResponse['BODY']['DATA']['COLLECTION']['VOUCHER']) ){
			$tallyFinalResponse = $tallyFinalResponse['BODY']['DATA']['COLLECTION']['VOUCHER'];
			$loop_couneter=0;


			if(isset($tallyFinalResponse[0])){

				foreach($tallyFinalResponse as $data){

					$tallyAllVouchers[$loop_couneter]['Type']				= 	isset($data['VOUCHERTYPENAME']) ? $data['VOUCHERTYPENAME'] : 'NotFound';
					$tallyAllVouchers[$loop_couneter]['DATE']				= 	isset($data['DATE']) ? date('Y-m-d', strtotime($data['DATE'])) : date('Y-m-d');
					$tallyAllVouchers[$loop_couneter]['LEDGERNAME-ONE']		=	isset($data['LEDGERNAME']) ? $data['LEDGERNAME'] : "";
					$tallyAllVouchers[$loop_couneter]['LEDGERNAME-TWO']		=	isset($data['ALLLEDGERENTRIES.LIST'][1]['LEDGERNAME']) ? $data['ALLLEDGERENTRIES.LIST'][1]['LEDGERNAME'] : "";
					$tallyAllVouchers[$loop_couneter]['VOUCHERNUMBER']		=	isset($data['VOUCHERNUMBER']) ? $data['VOUCHERNUMBER'] : "";
					$tallyAllVouchers[$loop_couneter]['AMOUNT']				=	isset($data['AMOUNT']) ? abs($data['AMOUNT']) : 0;
					$tallyAllVouchers[$loop_couneter]['REFERENCE']			=	isset($data['REFERENCE']) && !is_array($data['REFERENCE']) ? $data['REFERENCE'] : "";

					$loop_couneter++;
				}

			}else{

				$tallyAllVouchers[$loop_couneter]['Type']			= 	isset($tallyFinalResponse['VOUCHERTYPENAME']) ? $tallyFinalResponse['VOUCHERTYPENAME'] : 'NotFound';
				$tallyAllVouchers[$loop_couneter]['DATE']			= 	isset($tallyFinalResponse['DATE']) ? date('Y-m-d', strtotime($tallyFinalResponse['DATE'])) : date('Y-m-d');
				$tallyAllVouchers[$loop_couneter]['LEDGERNAME-ONE']		=	isset($tallyFinalResponse['LEDGERNAME']) ? $tallyFinalResponse['LEDGERNAME'] : "";
				$tallyAllVouchers[$loop_couneter]['LEDGERNAME-TWO']		=	isset($data['ALLLEDGERENTRIES.LIST'][1]['LEDGERNAME']) ? $data['ALLLEDGERENTRIES.LIST'][1]['LEDGERNAME'] : "";
				$tallyAllVouchers[$loop_couneter]['VOUCHERNUMBER']	=	isset($tallyFinalResponse['VOUCHERNUMBER']) ? $tallyFinalResponse['VOUCHERNUMBER'] : "";
				$tallyAllVouchers[$loop_couneter]['AMOUNT']			=	isset($tallyFinalResponse['AMOUNT']) ? abs($tallyFinalResponse['AMOUNT']) : 0;
				$tallyAllVouchers[$loop_couneter]['REFERENCE']		=	isset($tallyFinalResponse['REFERENCE']) && !is_array($tallyFinalResponse['REFERENCE']) ? $tallyFinalResponse['REFERENCE'] : "";
				$loop_couneter++;

			}
						
		}

		/*print_r($tallyAllVouchers);
		exit;*/

		$counter=1;
		$receiptTotal = 0;
		$receiptArray = array();
		foreach($tallyAllVouchers as $value){
			if($value['Type']=='Sales'){
				$receiptArray[$counter] = $value;
				$receiptTotal = $receiptTotal + $value['AMOUNT'];
				$counter++;
			}
		}
		

		// Making the final response
		$finalResponse = array(
			'message'	=> 'Ledger Imported Successfully!',
			'status'	=> 'Success',
			'code'		=> '200',
			'data'		=> $receiptArray,
			'Total'		=> $receiptTotal
		);

		return $finalResponse;
	}
}