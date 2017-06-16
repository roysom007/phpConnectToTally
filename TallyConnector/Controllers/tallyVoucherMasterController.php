<?php
/*
FileName : tallyVoucherMasterController
Author   : eButor
Description :
CreatedDate : 19/Oct/2016
*/

//defining namespace
namespace App\Modules\TallyConnector\Controllers;

//loading namespaces
use App\Http\Controllers\BaseController;
use App\Modules\TallyConnector\Models\tallyConnectorModel;

use Illuminate\Http\Request;
use Input;
use Log;
use Session;


class tallyVoucherMasterController extends BaseController{

	private $objTallyConnector = '';
	private $finalResponse = '';

	public function __construct(){
		$this->objTallyConnector = new tallyConnectorModel();
	}

	public function checkAuthentication($auth_token){
    	if( $auth_token=='E446F5E53AD8835EAA4FA63511E22' ){
    		return true;
    	}else{
    		return false;
    	}
    }

	// Create Payment Voucher to Tally
	public function createVouchers(Request $request){

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

		// Getting all the response from the call &apos;
		$compnayFromInput 	= str_replace("&", "&amp;", $request->input('companyName'));
		$vcType 			= $request->input('vcType');
		$vcNumber 			= $request->input('vcNumber');
		$reffNumber			= $request->input('reffNumber');

		// Convert date format as Tally
		$vcDate 			= $request->input('vcDate');
		$vcDate				= Date('Ymd', strtotime($vcDate));

		$naration 			= str_replace("'", "&apos;", (str_replace("&", "&amp;", $request->input('naration'))) );
		$drTransDet 		= $request->input('drTransDet');
		$crTransDet 		= $request->input('crTransDet');

		// Prepare tally DR side
		$drTallyXml = '';

		foreach($drTransDet as $data){
			$minusSymbol = $data['trans_type']=='Dr' ? '-' : '';
			$yesNo = $data['trans_type']=='Dr' ? 'Yes' : 'No';

			$newReff = '';
			if($data['trans_type']=='Cr' && $vcType=='Purchase'){
				$newReff = '<BILLALLOCATIONS.LIST>
								<NAME>'.trim($reffNumber).'</NAME>
								<BILLTYPE>New Ref</BILLTYPE>
								<TDSDEDUCTEEISSPECIALRATE>No</TDSDEDUCTEEISSPECIALRATE>
								<AMOUNT>'.trim($data['amount']).'</AMOUNT>
								<INTERESTCOLLECTION.LIST></INTERESTCOLLECTION.LIST>
								<STBILLCATEGORIES.LIST></STBILLCATEGORIES.LIST>
							</BILLALLOCATIONS.LIST>';
			}
			if($data['trans_type']=='Dr' && $vcType=='Sales'){
				$newReff = '<BILLALLOCATIONS.LIST>
								<NAME>'.trim($reffNumber).'</NAME>
								<BILLTYPE>New Ref</BILLTYPE>
								<TDSDEDUCTEEISSPECIALRATE>No</TDSDEDUCTEEISSPECIALRATE>
								<AMOUNT>-'.trim($data['amount']).'</AMOUNT>
								<INTERESTCOLLECTION.LIST></INTERESTCOLLECTION.LIST>
								<STBILLCATEGORIES.LIST></STBILLCATEGORIES.LIST>
							</BILLALLOCATIONS.LIST>';
			}
			if($data['trans_type']=='Cr' && $vcType=='Credit Note'){
				$newReff = '<BILLALLOCATIONS.LIST>
								<NAME>'.trim($reffNumber).'</NAME>
								<BILLTYPE>Agst Ref</BILLTYPE>
								<TDSDEDUCTEEISSPECIALRATE>No</TDSDEDUCTEEISSPECIALRATE>
								<AMOUNT>'.trim($data['amount']).'</AMOUNT>
								<INTERESTCOLLECTION.LIST></INTERESTCOLLECTION.LIST>
								<STBILLCATEGORIES.LIST></STBILLCATEGORIES.LIST>
							</BILLALLOCATIONS.LIST>';
			}
			if($data['trans_type']=='Dr' && $vcType=='Journal'){
				$newReff = '<BILLALLOCATIONS.LIST>
								<NAME>'.trim($reffNumber).'</NAME>
								<BILLTYPE>Agst Ref</BILLTYPE>
								<TDSDEDUCTEEISSPECIALRATE>No</TDSDEDUCTEEISSPECIALRATE>
								<AMOUNT>-'.trim($data['amount']).'</AMOUNT>
								<INTERESTCOLLECTION.LIST></INTERESTCOLLECTION.LIST>
								<STBILLCATEGORIES.LIST></STBILLCATEGORIES.LIST>
							</BILLALLOCATIONS.LIST>';
			}
			
			if($data['trans_type']=='Cr' && $vcType=='Receipt'){
				$newReff = '<BILLALLOCATIONS.LIST>
								<NAME>'.trim($reffNumber).'</NAME>
								<BILLTYPE>Agst Ref</BILLTYPE>
								<TDSDEDUCTEEISSPECIALRATE>No</TDSDEDUCTEEISSPECIALRATE>
								<AMOUNT>'.trim($data['amount']).'</AMOUNT>
								<INTERESTCOLLECTION.LIST></INTERESTCOLLECTION.LIST>
								<STBILLCATEGORIES.LIST></STBILLCATEGORIES.LIST>
							</BILLALLOCATIONS.LIST>';
			}

			$drTallyXml .= '<ALLLEDGERENTRIES.LIST>
								<LEDGERNAME>'.trim( str_replace("'", "&apos;", (str_replace("&", "&amp;", $data['ledger_account']))) ).'</LEDGERNAME>
								<ISDEEMEDPOSITIVE>'.$yesNo.'</ISDEEMEDPOSITIVE>
								<AMOUNT>'.$minusSymbol.trim($data['amount']).'</AMOUNT>
								'.$newReff.'
								<CATEGORYALLOCATIONS.LIST>
									<CATEGORY>EBUTOR</CATEGORY>
									<COSTCENTREALLOCATIONS.LIST>
										<NAME>'.trim(str_replace("&", "&amp;", $data['cost_centre'])).'</NAME>
										<ISDEEMEDPOSITIVE>'.$yesNo.'</ISDEEMEDPOSITIVE>
										<AMOUNT>'.$minusSymbol.trim($data['amount']).'</AMOUNT>
									</COSTCENTREALLOCATIONS.LIST>
								</CATEGORYALLOCATIONS.LIST>
							</ALLLEDGERENTRIES.LIST>';	
		}

		// Prepare tally CR side
		$crTallyXml = '';
		/*foreach($crTransDet as $data){
			$crTallyXml .= '<ALLLEDGERENTRIES.LIST>
								<LEDGERNAME>'.trim( str_replace("'", "&apos;", (str_replace("&", "&amp;", $data['ledger_account']))) ).'</LEDGERNAME>
								<ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>
								<AMOUNT>'.trim($data['amount']).'</AMOUNT>
								<CATEGORYALLOCATIONS.LIST>
									<CATEGORY>Zone 01</CATEGORY>
									<COSTCENTREALLOCATIONS.LIST>
										<NAME>'.trim(str_replace("&", "&amp;", $data['cost_centre'])).'</NAME>
										<ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>
										<AMOUNT>'.trim($data['amount']).'</AMOUNT>
									</COSTCENTREALLOCATIONS.LIST>
								</CATEGORYALLOCATIONS.LIST>
							</ALLLEDGERENTRIES.LIST>';	
		}*/


		// Prepare XML to create Voucher Master
		$requestXML = '
			<ENVELOPE>
				<HEADER>
					<VERSION>1</VERSION>
					<TALLYREQUEST>Import</TALLYREQUEST>
					<TYPE>Data</TYPE>
					<ID>Vouchers</ID>
				</HEADER>
				<BODY>
					<DESC>
						<STATICVARIABLES>
							<SVCURRENTCOMPANY>'.trim($compnayFromInput).'</SVCURRENTCOMPANY>
						</STATICVARIABLES>
					</DESC>
					<DATA>
						<TALLYMESSAGE>
							<VOUCHER>
								<DATE>'.trim($vcDate).'</DATE>
								<NARRATION>'.trim($naration).'</NARRATION>
								<VOUCHERTYPENAME>'.trim($vcType).'</VOUCHERTYPENAME>
								<VOUCHERNUMBER>'.trim($vcNumber).'</VOUCHERNUMBER>
								<REFERENCEDATE>'.trim($vcDate).'</REFERENCEDATE>
								<REFERENCE>'.trim($vcNumber).'</REFERENCE>
								'.$drTallyXml.
								$crTallyXml.'
							</VOUCHER>
						</TALLYMESSAGE>
					</DATA>
				</BODY>
			</ENVELOPE>';

		//return $requestXML;

		// call CURL function to get tally response
		return $this->objTallyConnector->executeTallyCURL($requestXML);
	}

	public function updateVouchers(){

		$newReff = '<BILLALLOCATIONS.LIST>
								<NAME>TSIV16120005897</NAME>
								<BILLTYPE>New Ref</BILLTYPE>
								<TDSDEDUCTEEISSPECIALRATE>No</TDSDEDUCTEEISSPECIALRATE>
								<AMOUNT>-255.60</AMOUNT>
								<INTERESTCOLLECTION.LIST></INTERESTCOLLECTION.LIST>
								<STBILLCATEGORIES.LIST></STBILLCATEGORIES.LIST>
							</BILLALLOCATIONS.LIST>';


		$requestXML = '
			<ENVELOPE>
				<HEADER>
					<VERSION>1</VERSION>
					<TALLYREQUEST>Import</TALLYREQUEST>
					<TYPE>Data</TYPE>
					<ID>Vouchers</ID>
				</HEADER>
				<BODY>
					<DESC>
						<STATICVARIABLES>
							<SVCURRENTCOMPANY>Ebutor Ledgers TST1</SVCURRENTCOMPANY>
						</STATICVARIABLES>
					</DESC>
					<DATA>
						<TALLYMESSAGE>
							<VOUCHER REMOTEID="9c72e163-01e4-4e29-9583-1af7f970d5bc-000008a9" DATE="20161209" VCHTYPE="Sales" TAGNAME="VOUCHERNUMBER" TAGVALUE="428" ACTION="Alter" >
								<GUID>9c72e163-01e4-4e29-9583-1af7f970d5bc-000008a9</GUID>
								<DATE>20161209</DATE>
								<NARRATION>Vouchers altered</NARRATION>
								<VOUCHERNUMBER>428</VOUCHERNUMBER>
								<ALLLEDGERENTRIES.LIST>
								'.$newReff.'
								</ALLLEDGERENTRIES.LIST>
							</VOUCHER>
						</TALLYMESSAGE>
					</DATA>
				</BODY>
			</ENVELOPE>
		';

		// call CURL function to get tally response
		return $this->objTallyConnector->executeTallyCURL($requestXML);
	} 

}