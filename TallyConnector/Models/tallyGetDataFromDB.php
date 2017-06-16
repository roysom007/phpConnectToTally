<?php
/*
FileName : tallyGetDataFromDB.php
Author   : eButor
Description : Pricing dashboard model.
CreatedDate : 01/Nov/2016
*/
//defining namespace
namespace App\Modules\TallyConnector\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Session;

class tallyGetDataFromDB extends Model{


	// get Supplier Data if they have any PO
	public function getSupplierData(){

		$supperDataSQL = "select * FROM
						(
							SELECT *,
							(SELECT COUNT(inward_id) FROM inward AS inw WHERE inw.legal_entity_id=l.legal_entity_id) AS 'TotalGRN'
							FROM legal_entities AS l
							WHERE legal_entity_type_id='1002' AND is_posted=0
						) AS innertbl WHERE TotalGRN>0";

		$supperData = DB::select(DB::raw($supperDataSQL));

		/*$supperData = DB::table('legal_entities_live')
					->where("legal_entity_type_id","=","1002")
					->where('is_posted', '=', '0')
					->get();*/

		return $supperData;

	}

	// get Customer Data if they have any Order
	public function getCustomerData(){

		$customerDataSQL = "select * FROM
						(
							SELECT *,
							(SELECT COUNT(gds_order_id) FROM gds_orders AS gord WHERE gord.cust_le_id=l.legal_entity_id) AS 'TotalOrder'
							FROM legal_entities AS l
							WHERE legal_entity_type_id LIKE '30%' AND is_posted=0
						) AS innertbl WHERE TotalOrder>0";

		$customerData = DB::select(DB::raw($customerDataSQL));

		/*$customerData = DB::table('legal_entities_live')
					->where("legal_entity_type_id","like","30%")
					->where('is_posted', '=', '0')
					->get();*/
					
		return $customerData;

	}

	public function getVoucherData(){

		$voucherData = DB::table('vouchers')
					->where('is_posted', '=', '0')
					//->whereNull('tally_resp')
					//->where('tally_resp', 'like', 'Voucher totals DO NOT MATCH%')
					->where('voucher_type', '<>', 'Journal')
					->groupBy("voucher_code")
					->orderBy("voucher_code")
					->offset(0)
					->limit(2000)
					->get();
		return $voucherData;
	}

	public function getVoucherLineData($voucherCode, $voucherType){
		$voucherLineData = DB::table('vouchers')
					->where('voucher_code', '=', $voucherCode)
					->where('voucher_type', '=', $voucherType)
					->get();
		return $voucherLineData;
	}

	// Check for Duplicate Voucher
	public function checkDuplicateVoucher($reffNumber, $VCType){

		$voucherCount = DB::table('vouchers')
					->where("reference_no", "=", $reffNumber)
					->where("voucher_type", "=", $VCType)
					->where('is_posted', '=', '1')
					->count();

		return $voucherCount;
	}
 
	public function updateVoucher($voucher_code, $updateVal, $tally_resp){
		// update table by 1
		$updateVouchers = DB::table('vouchers')
		             	->where('voucher_code', '=', $voucher_code)
		             	->update(['is_posted'=> $updateVal, 'tally_resp' => $tally_resp, 'sync_date' => date('Y-m-d H:i:s') ]);

		return true;
	}

	public function updateLedger($legal_entity_id, $updateVal, $tally_resp){
		// update table by 0
		$updateLedger = DB::table('legal_entities')
		             	->where('legal_entity_id', '=', $legal_entity_id)
		             	->update(['is_posted'=> $updateVal, 'tally_resp' => $tally_resp]);

		return true;
	}

	public function getUnImportedLedgerData(){
		/*$pendingData = DB::table('legal_entities')
					->where('is_posted', '=', '0')
					->get();*/

		// GET SUPPLIER DATA
		$pendingSupplier = $this->getSupplierData(); 
		// GET PENDING CUSTOMER DATA
		$pendingCustomer = $this->getCustomerData();

		$pendingData = array_merge($pendingSupplier, $pendingCustomer);		

		return $pendingData;
	}

	public function getUnImportedVoucherData(){
		$pendingData = DB::table('vouchers')
					->where('is_posted', '=', '0')
					->groupBy('voucher_code')
					->get();
		return $pendingData;
	}

	// This function inserts Ledger Data from Tally to SQL
	// It takes 50 records at a chunk
	// and does a bluk update
	public function insertTallyLedger($insertData){

		// Assign the value for setup
		$total = count($insertData);
		$start = 0;
		$nextLimit = 50;

		// Assign the variale with default values
		$insideCounter = 0;
		$firstPart = "insert INTO tally_ledger_master (tlm_name, tlm_group, sync_date, sync_source) VALUES ";
		$lastPart = " ON DUPLICATE KEY UPDATE tlm_name=tlm_name";
		$middlePart = "";

		for($start=0; $start<$nextLimit; $start++){

			// for the last chunk
			if($insideCounter>=$total){
				DB::statement(DB::raw( $firstPart . rtrim($middlePart, ',') . $lastPart ));
				break;
			}

			// Prepare the middle part of the query for blunk section
			$middlePart .= "('". str_replace("'", "\'", $insertData[$insideCounter]['tlm_name']) ."', '". str_replace("'", "\'", $insertData[$insideCounter]['tlm_group']) ."', '".date('Y-m-d')."', 'CURL') ";

			// Reset the Chunk Size
			if($start== ($nextLimit-1) ){

				DB::statement(DB::raw( $firstPart . $middlePart . $lastPart ));

				$start=-1;
				$middlePart="";

			}else{
				$middlePart .= ",";
			}

			$insideCounter++;
		}
		return true;
	}

	// Get total Sales Return Value for Report
	public function getTotalSalesReturnValue($fromDate, $toDate){

		$returnData = "select SUM(returnvalue) AS 'TotalReturnAmt' FROM
							( 
								SELECT 
								gds_orders.`order_code` ,
								gds_orders.`order_date`,
								gds_returns.`reference_no` AS returncode,
								gds_returns.`created_at` AS returndate,
								SUM(gds_returns.`total`) AS returnvalue
								FROM gds_orders
								JOIN gds_returns ON gds_orders.`gds_order_id` = gds_returns.`gds_order_id`
								WHERE DATE(gds_orders.`order_date`)  >= '".$fromDate."' AND DATE(gds_orders.`order_date`)  <= '".$toDate."'
								AND gds_returns.return_status_id = '57066'
								GROUP BY gds_orders.`gds_order_id`
							) AS innertbl";

		$returnData = DB::select(DB::raw($returnData));

		return $returnData;

	}

	// This function Returns Sales value from DB
	public function getTotalSalesValue($fromDate, $toDate){

		$salesData = "select SUM(invoicevalue) AS 'TotalSalesAmt' FROM 
						(
							SELECT 
							gds_orders.`order_code` ,
							gds_orders.`order_date`,
							gds_invoice_grid.`invoice_code` AS invoicecode,
							gds_invoice_grid.`grand_total` AS invoicevalue,
							gds_invoice_grid.`created_at` AS invoicedate
							FROM gds_orders
							JOIN gds_invoice_grid ON gds_orders.`gds_order_id` = gds_invoice_grid.`gds_order_id`
							WHERE DATE(gds_orders.`order_date`)  >= '".$fromDate."' AND DATE(gds_orders.`order_date`)  <= '".$toDate."'
						) AS innertbl";

		$salesData = DB::select(DB::raw($salesData));

		return $salesData;
	}


	// This function Returns Total Purchase value from DB
	public function getTotalPurchaseData($fromDate, $toDate){

		$purchaseData = "select SUM(grand_total) AS 'TotalPurchase'
						FROM inward 
						WHERE DATE(created_at) >= '".$fromDate."' AND DATE(created_at) <= '".$toDate."'
						AND approval_status = 1";

		$purchaseData = DB::select(DB::raw($purchaseData));

		return $purchaseData;
	}

}