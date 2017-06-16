<?php
/*
FileName : tallyLoggerModel.php
Author   : eButor
Description : Making the connection with Tally.
CreatedDate : 10/Oct/2016
*/
//defining namespace
namespace App\Modules\TallyConnector\Models;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;
use \Log;
use \Session;
use Carbon\Carbon;
use DateTime;




class tallyLoggerModel extends Eloquent {

    protected $connection = 'mongo';
    protected $table = 'tally_transaction_logs';
    protected $primaryKey = '_id';

    public function addTallyTransactionLog($requestIPAddress, $inputData, $tallyFinalResponse,$action,$TallyModel){

    	try {
            $username = Session::get("fullname");
            $userId = Session::get('userId');
            $userDetails  = array("userId" => $userId, "username" => $username);
            
            $now = new DateTime('Asia/Kolkata');
            $timestamp = $now->format('Y-m-d H:i:s');
            
            $this->TallyModel = "$TallyModel";
            //$this->TallyModelType = "LEDGER_GROUP";
            $this->action = $action;
            $this->InputParam = $inputData;
            $this->OutputResult = $tallyFinalResponse;
            $this->RequestedIPAddress = $requestIPAddress;
			$this->UserDetails = $userDetails;
			$this->created_at = $timestamp;
            $this->updated_at = $timestamp;
			$this->save();	
    	} catch (\ErrorException $ex) {
            Log::info($ex->getMessage());
            Log::info($ex->getTraceAsString());
        }
    }

}