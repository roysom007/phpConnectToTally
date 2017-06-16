<?php
/*
FileName : tallyPushVoucherController
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

class tallyPushVoucherController extends BaseController{

	private $_objGetDBData = '';

	public function __construct(){
		$this->_objGetDBData = new tallyGetDataFromDB();
	}

	public function pushTallyVouchers($companyName){
		
		// get Data from Supplier
		$voucherData = $this->_objGetDBData->getVoucherData();

		foreach ($voucherData as $voucher) {

			$response 		= array();

			$VCType 		= $voucher->voucher_type;
			$VCNumber 		= $voucher->voucher_code;
			$VCDate 		= $voucher->voucher_date;
			$Naration 		= $voucher->naration;
			$reffNumber		= $voucher->reference_no;

			// Check Vouvher Duplication
			$checkVoucherFlag = $this->_objGetDBData->checkDuplicateVoucher($reffNumber, $VCType);

			if($checkVoucherFlag>0){

				$response['status'] = "Success";
				$response['message'] = "Duplicate Voucher Entry";

			}else{

				$voucherLineData = $this->_objGetDBData->getVoucherLineData($voucher->voucher_code, $voucher->voucher_type);

				$drList = array();
				$crList = array();

				// Arrange Dr/Cr Type
				$drcnt = $crcnt = 0;
				foreach ($voucherLineData as $lineData) {

					//if($lineData->tran_type=='Dr'){
						
						$drList[$drcnt]['trans_type']=$lineData->tran_type;

						if( $lineData->voucher_type=='Sales' && $lineData->ledger_group=='Sundry Debtors' ){
							$drList[$drcnt]['ledger_account']= substr($lineData->ledger_account, -15);

						}elseif( $lineData->voucher_type=='Credit Note' && $lineData->ledger_group=='Sundry Debtors' ){
							$drList[$drcnt]['ledger_account']= substr($lineData->ledger_account, -15);
							
						}elseif( $lineData->voucher_type=='Receipt' && $lineData->ledger_group=='Sundry Debtors' ){
							$drList[$drcnt]['ledger_account']= substr($lineData->ledger_account, -15);
							
						}else{
							$drList[$drcnt]['ledger_account']=$lineData->ledger_account;
						}

						$drList[$drcnt]['amount']=$lineData->amount;
						$drList[$drcnt]['cost_centre']=$lineData->cost_centre;

						$drcnt++;
					//}

					// posting the ledger before the voucher posting
					// $this->executeTallyAPIFor_Ledger('createledgermaster', $lineData->ledger_account, $lineData->ledger_group, $companyName);
				}

				$transDetails['DR'] 		= $drList;
				$transDetails['CR'] 		= $crList;

				$response = $this->executeTallyAPI($VCType, $VCNumber, $VCDate, $Naration, $reffNumber, $transDetails['DR'], $transDetails['CR'], $companyName);

			}

			if(isset($response['status'])){

				if($response['status']=='Success'){
					// update table by 1
					$voucherData = $this->_objGetDBData->updateVoucher($voucher->voucher_code, 1, $response['message']);
				}else{
					//update table by 0
                    $voucherData = $this->_objGetDBData->updateVoucher($voucher->voucher_code, 0, $response['message']);   
				}
			}else{
				$voucherData = $this->_objGetDBData->updateVoucher($voucher->voucher_code, 0, "No Response Received");
			}
		}

		return 'Tally Ledger Import is Done, Please reffer MongoDB for Details';

	}

	// Run the tally APIs to Post a Voucher
	public function executeTallyAPI($VCType, $VCNumber, $VCDate, $Naration, $reffNumber, $drTransDet, $crTransDet, $companyName){

		$apiURL = env('APP_ENV_URL') . "tallyconnector/createvouchers";
 
		$headers =  array(
		    "auth: E446F5E53AD8835EAA4FA63511E22",
		    "Content-Type: application/json"
		);

		$postFields = array(
				'vcType'			=>	$VCType,
				'vcNumber'		    =>	$VCNumber,
				'vcDate'			=>	$VCDate,
				'naration'	        =>	$Naration,
				'reffNumber'		=>	$reffNumber,
				'drTransDet'		=> 	$drTransDet,
				'crTransDet'		=>	$crTransDet,	
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

	// Run the tally APIs to Post a Ledger at the time of Voucher created
	public function executeTallyAPIFor_Ledger($callerFunction, $ledgerName, $parentName, $companyName){

		$apiURL = env('APP_ENV_URL') . "tallyconnector/".$callerFunction;
 
		$headers =  array(
		    "auth: E446F5E53AD8835EAA4FA63511E22",
		    "Content-Type: application/json"
		);

		$postFields = array(
				'parentDC'			=>	$parentName,
				'ledgerName'		=>	$ledgerName,
				'aliasName'			=>	$ledgerName.'SM',
				'openingBalance'	=>	'',
				'address1'			=>	'From System',
				'address2'			=>	'',
				'city'				=>	'',
				"pinCode"			=>	'',
				"state"				=>	'',
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

	public function sendMailForVoucher($emailList){

		// Retrive all unImported data from Legal Entry Tanble
		$allPendingData = $this->_objGetDBData->getUnImportedVoucherData(); 

		// Creating the mail Content
		$iDs = '';
		$mailContent = "<table border='1' cellspacing='0' cellpadding='5'>";
		$mailContent .= "<tr bgcolor='#efefef'>";
		$mailContent .= "<th>VC CODE</th>";
		$mailContent .= "<th>VC TYPE</th>";
		$mailContent .= "<th>VC DATE</th>";
		$mailContent .= "<th>TALLY RESPONSE</th>";
		$mailContent .= "</tr>";
		foreach ($allPendingData as $pData) {

			$mailContent .= "<tr>";
			$mailContent .= "<td>".$pData->voucher_code."</td>";
			$mailContent .= "<td>".$pData->voucher_type."</td>";
			$mailContent .= "<td>".date('d-m-Y', strtotime($pData->voucher_date))."</td>";
			$mailContent .= "<td>".$pData->tally_resp."</td>";
			$mailContent .= "</tr>";

			$iDs .= $pData->voucher_code . ',';
		}

		if($iDs==''){
			$mailContent .= "<tr>";
			$mailContent .= "<td colspan='4'><b>All Vouchers Imported Successfully<b></td>";
			$mailContent .= "</tr>";
		}

		$mailContent .= "</table>";

		$mailContent .= "<br><br>";
		$mailContent .= "<b>VCCodes : " . $iDs . "</b>";

		$toEmails = explode(",", $emailList);

		Mail::send('emails.tallyImportStatusMail', ['emailContent' => $mailContent], function ($message) use ($toEmails) {
			$message->to($toEmails);
			$message->subject('Tally Import Status For Voucher as on - ' . date('d-m-Y') );
		});
	}

}