<?php
Route::group(['middleware' => ['mobile']], function () {

	Route::group(['prefix' => 'tallyconnector', 'namespace' => 'App\Modules\TallyConnector\Controllers'], function () {

		// LEDGER MASTER ROUTE
		Route::post('/createledgergroup', 'tallyLedgerMasterController@createLedgerGroupMaster');
		Route::post('/createledgermaster', 'tallyLedgerMasterController@createLedgerMaster');
		Route::post('/editledgergroupmaster', 'tallyLedgerMasterController@editLedgerGroupMaster');
        Route::post('/editledgermaster', 'tallyLedgerMasterController@editLedgerMaster');

		// VOUCHER MASTER ROUTE
		Route::post('/createvouchers', 'tallyVoucherMasterController@createVouchers');


		//COST CATEGORIES ROUTE
		Route::post('/createcostcategoriesmaster', 'tallyCostMasterController@createCostCategoriesMaster');
		Route::post('/editcostcategoriesmaster', 'tallyCostMasterController@editCostCategoriesMaster');


		//COST CENTRE ROUTE
	    Route::post('/createcostcentresmaster', 'tallyCostMasterController@createCostCentresMaster');
		Route::post('/editcostcentresmaster', 'tallyCostMasterController@editCostCentresMaster');

		// FETCH LEDGER MASTER ROUTE
		Route::post('/fetchtallyledger', 'tallyFetchLedgerMasterController@fetchLedgerMaster');

		// ROUTE FOR REPORTING
		Route::post('/fetchLedgerWithBalance', 'tallyFetchLedgerMasterController@fetchLedgerWithBalance');
		Route::post('/generateTallyVSEPReport', 'tallyGenerateReportController@generateTallyVSEPReportAPI');
		Route::post('/fetchvoucherdetails', 'tallyFetchLedgerMasterController@fetchVoucherDetails');

	});
}); 