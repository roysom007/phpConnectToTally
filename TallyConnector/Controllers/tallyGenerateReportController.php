<?php
/*
FileName : tallyGenerateReportController
Author   : eButor
Description :
CreatedDate :19/Apr/2017
*/

//defining namespace
namespace App\Modules\TallyConnector\Controllers;

//loading namespaces
use App\Http\Controllers\BaseController;
use App\Modules\TallyConnector\Models\tallyGetDataFromDB;

use Illuminate\Http\Request;
use Input;
use Log;
use Mail;

class tallyGenerateReportController extends BaseController{

	private $_objGetDBData = '';

	public function __construct(){
		$this->_objGetDBData = new tallyGetDataFromDB();
	}

	public function checkAuthentication($auth_token){
    	if( $auth_token=='E446F5E53AD8835EAA4FA63511E22' ){
    		return true;
    	}else{
    		return false;
    	}
    }

    // Function for API
	public function generateTallyVSEPReportAPI(Request $request){

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
		$emailList 	= $request->input('emailList');

		$reportArray = $this->generateTallyVSEPReport($compnayFromInput, $emailList);

		return $reportArray;
	}

	// Fetch all the Ledger with their balance as per the date range
	public function generateTallyVSEPReport( $companyName, $emailList ){

		// Arrange Date for Normal Case
		$startDateForTally = date('d-M-Y', strtotime('-7 days'));
		$toDateForTally = date('d-M-Y');

		// Arrange Date For EP
		$startDate = date('Y-m-d', strtotime('-7 days'));
		$toDate = date('Y-m-d');
		
		//==============================================================================
		// Need to Block the Code once the Report got generated
		// For Jan Data
		/*$startDateForTally = date('d-M-Y', strtotime('01-Jan-2017'));
		$toDateForTally = date('d-M-Y', strtotime('01-01-2017 +30 days'));
		$startDate = date('Y-m-d', strtotime('01-Jan-2017'));
		$toDate = date('Y-m-d', strtotime('01-01-2017 +30 days'));*/

		// For Feb Data
		/*$startDateForTally = date('d-M-Y', strtotime('01-Feb-2017'));
		$toDateForTally = date('d-M-Y', strtotime('01-02-2017 +27 days'));
		$startDate = date('Y-m-d', strtotime('01-Feb-2017'));
		$toDate = date('Y-m-d', strtotime('01-02-2017 +27 days'));*/

		// For March Data
		$startDateForTally = date('d-M-Y', strtotime('01-Mar-2017'));
		$toDateForTally = date('d-M-Y', strtotime('01-03-2017 +30 days'));
		$startDate = date('Y-m-d', strtotime('01-Mar-2017'));
		$toDate = date('Y-m-d', strtotime('01-03-2017 +30 days'));

		// For APR Data
		/*$startDateForTally = date('d-M-Y', strtotime('01-Apr-2017'));
		$toDateForTally = date('d-M-Y', strtotime('01-04-2017 +29 days'));
		$startDate = date('Y-m-d', strtotime('01-Apr-2017'));
		$toDate = date('Y-m-d', strtotime('01-04-2017 +29 days'));*/
		//==============================================================================


		$reportArray = array();
		$reportArray['fromDate'] 	= 	$startDateForTally;
		$reportArray['toDate'] 		= 	$toDateForTally;

		// Getting data from Tally
		$tallyData = $this->executeTallyAPIForReport($companyName, $startDateForTally, $toDateForTally, $emailList);

		// Arrange Tally Data
		$reportArray['salesData']['tally']				=	$tallyData['salesTotal'];
		$reportArray['salesReturnData']['tally']		=	abs($tallyData['salesReturnTotal']);

		// Sales return VAT
		$reportArray['salesVATTotal']['tally']			=	$tallyData['salesVATTotal'];
		$reportArray['salesVATTotal']['EP']				=	"0.00";

		// Purchase value from Tally
		$totaPurchaseAmt = ( abs($tallyData['purchaseTotal']) + abs($tallyData['purchaseVATTotal']) );
		//$reportArray['purchaseData']['tally']			=	abs($tallyData['purchaseTotal']) . " + " . abs($tallyData['purchaseVATTotal']) . "=" . $totaPurchaseAmt;
		$reportArray['purchaseData']['tally']			=	abs($tallyData['purchaseTotal']);

		// Purchase VAT value from Tally
		$reportArray['purchaseVATData']['tally']		=	abs($tallyData['purchaseVATTotal']);
		$reportArray['purchaseVATData']['EP']			=	"0.00";
		
		// Get Sales Value from EP
		$salesData = $this->_objGetDBData->getTotalSalesValue($startDate, $toDate);
		$reportArray['salesData']['EP']					=	$salesData[0]->TotalSalesAmt;

		// Get Sales Return Value from EP
		$salesReturnData = $this->_objGetDBData->getTotalSalesReturnValue($startDate, $toDate);
		$reportArray['salesReturnData']['EP']			=	$salesReturnData[0]->TotalReturnAmt;

		// Get EP Purchase Value from EP
		$purchaseData = $this->_objGetDBData->getTotalPurchaseData($startDate, $toDate);
		$reportArray['purchaseData']['EP'] = $purchaseData[0]->TotalPurchase;

		// Calculate amount difference
		$reportArray['salesData']['diff']				=	$reportArray['salesData']['EP'] - $reportArray['salesData']['tally'];
		$reportArray['salesReturnData']['diff']			=	$reportArray['salesReturnData']['EP'] - $reportArray['salesReturnData']['tally'];
		$reportArray['purchaseData']['diff']			=	$reportArray['purchaseData']['EP'] - $totaPurchaseAmt;

		// Sending Mails
		$this->sendMailForReconsileReport($reportArray, $emailList);

		return $reportArray;
	}

	// Run the tally APIs
	private function executeTallyAPIForReport($companyName, $startDate, $toDate){

		$apiURL = env('APP_ENV_URL') . "tallyconnector/fetchLedgerWithBalance";
 
		$headers =  array(
		    "auth: E446F5E53AD8835EAA4FA63511E22",
		    "Content-Type: application/json"
		);

		$postFields = array(	
				'companyName'		=>	$companyName,
				'startDate'			=>	$startDate,
				'toDate'			=>	$toDate
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

	// Sending mail the listed person
	private function sendMailForReconsileReport($reportArray, $emailList){

		// Convert email list as an Array
		$toEmails = explode(",", $emailList);

		// Retrive all unImported data from Legal Entry Tanble
		$allPendingData = $this->_objGetDBData->getUnImportedLedgerData(); 

		// Creating the mail Content
		$iDs = '';
		$mailContent = "<h3>Tally VS EP Reconciliation Report as on : " . $reportArray['fromDate'] . " - " . $reportArray['toDate'] . "</h3>";
		$mailContent .= "<br><br>";
		$mailContent .= "<table border='1' cellspacing='0' cellpadding='5'>";
		$mailContent .= "<tr bgcolor='#efefef'>";
		$mailContent .= "<th>Account Name</th>";
		$mailContent .= "<th>EP Data</th>";
		$mailContent .= "<th>Tally Data</th>";
		$mailContent .= "<th>Difference</th>";
		$mailContent .= "</tr>";
		
		// For Sales Data
		$mailContent .= "<tr>";
		$mailContent .= "<td>Sales Account</td>";
		$mailContent .= "<td>".$reportArray['salesData']['EP']."</td>";
		$mailContent .= "<td>".$reportArray['salesData']['tally']."</td>";
		$mailContent .= "<td>".$reportArray['salesData']['diff']."</td>";
		$mailContent .= "</tr>";

		// For Sales Return Data
		$mailContent .= "<tr>";
		$mailContent .= "<td>Sales Return</td>";
		$mailContent .= "<td>".$reportArray['salesReturnData']['EP']."</td>";
		$mailContent .= "<td>".$reportArray['salesReturnData']['tally']."</td>";
		$mailContent .= "<td>".$reportArray['salesReturnData']['diff']."</td>";
		$mailContent .= "</tr>";

		// For Sales Return Data
		$mailContent .= "<tr>";
		$mailContent .= "<td>Sales Total VAT</td>";
		$mailContent .= "<td>0.00</td>";
		$mailContent .= "<td>".$reportArray['salesVATTotal']['tally']."</td>";
		$mailContent .= "<td>".$reportArray['salesVATTotal']['tally']."</td>";
		$mailContent .= "</tr>";

		// For Purchase Data
		$mailContent .= "<tr>";
		$mailContent .= "<td>Purchase Account</td>";
		$mailContent .= "<td>".$reportArray['purchaseData']['EP']."</td>";
		$mailContent .= "<td>".$reportArray['purchaseData']['tally']."</td>";
		$mailContent .= "<td>0.00</td>";
		$mailContent .= "</tr>";

		// For Purchase VAT Data
		$mailContent .= "<tr>";
		$mailContent .= "<td>Input VAT</td>";
		$mailContent .= "<td>".$reportArray['purchaseVATData']['EP']."</td>";
		$mailContent .= "<td>".$reportArray['purchaseVATData']['tally']."</td>";
		$mailContent .= "<td>".$reportArray['purchaseData']['diff']."</td>";
		$mailContent .= "</tr>";

		$mailContent .= "</table>";

		Mail::send('emails.tallyImportStatusMail', ['emailContent' => $mailContent], function ($message) use ($toEmails) {
			$message->to($toEmails);
			$message->subject('Tally Reconciliation Report as on - ' . date('d-m-Y') );
		});

		return 1;
	}

}