<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the balances action.
 *
 * @author tom
 */
class Billrun_ActionManagers_Balances_Query extends Billrun_ActionManagers_Balances_Action{
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $balancesQuery = array();

	/**
	 * If true then the query is a ranged query in a specific date.
	 * @var boolean 
	 */
	protected $queryInRange = false;
	
	/**
	 * Query for projecting the balance.
	 * @var type 
	 */
	protected $balancesProjection = array();
		
	/**
	 */
	public function __construct() {
		parent::__construct();
	}
	
	/**
	 * Query the balances collection to receive data in a range.
	 */
	protected function queryRangeBalances() {
		try {
			$cursor = $this->collection->query($this->balancesQuery)->cursor();
			if(!$this->queryInRange) {
				$cursor->limit(1);
			}
			$returnData = array();
			
			// Going through the lines
			foreach ($cursor as $line) {
				$returnData[] = json_encode($line->getRawData());
			}
		} catch (\Exception $e) {
			Billrun_Factory::log('failed quering DB got error : ' . $e->getCode() . ' : ' . $e->getMessage(), Zend_Log::ALERT);
			return null;
		}	
		
		return $returnData;
	}
	
	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$returnData = 
			$this->queryRangeBalances();

		$success=true;
		
		// Check if the return data is invalid.
		if(!$returnData) {
			$returnData = array();
			$success=false;
		}
		
		$outputResult = 
			array('status'  => ($success) ? (1) : (0),
				  'desc'    => ($success) ? ('success') : ('Failed') . ' querying balances',
				  'details' => $returnData);
		return $outputResult;
	}

	/**
	 * Parse the to and from parameters if exists. If not execute handling logic.
	 * @param type $input - The received input.
	 */
	protected function parseDateParameters($input) {
		// Check if there is a to field.
		$to = $input->get('to');
		$from = $input->get('from');
		if($to && $from) {
			$this->setDateParameters($to, $from, $this->balancesQuery);
			$this->queryInRange = true;
		}
	}
	
	/**
	 * Set date parameters to a query.
	 * are not null.
	 * @param type $to - To pramter.
	 * @param type $from - From parameter.
	 * @param type $query - Query to set the date in.
	 */
	protected function setDateParameters($to, $from, $query) {
		$query['to'] =
			array('$lte' => new MongoTimestamp($to));
		$query['from'] = 
			array('$gte' => new MongoTimestamp($from));
	}
	
	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		$sid = $input->get('sid');
		if(empty($sid)) {
			Billrun_Factory::log("Balances Query receieved no sid!", Zend_Log::NOTICE);
			return false;
		}
		
		$this->balancesQuery = 
			array('sid'	=> $sid);
		
		$this->parseDateParameters($input);
				
		// Set the prepaid filter data.
		if(!$this->createFieldFilterQuery($input)) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * Create the query to filter only the required fields from the record.
	 * @param type $input
	 */
	protected function createFieldFilterQuery($input) {
		$prepaidQuery = $this->getPrepaidQuery($input);
		
		// Check if received both external_id and name.
		if(count($prepaidQuery) > 1) {
			Billrun_Factory::log("Received both external id and name in balances query, specify one or none.", Zend_Log::ERR);
			return false;
		}
		// If empty it means that there is no filtering to be done.
		else if(empty($prepaidQuery)) {
			return true;
		}
		
		// Set to and from if exists.
		if(isset($this->balancesQuery['to']) && isset($this->balancesQuery['from'])) {
			$this->setDateParameters($this->balancesQuery['to'], $this->balancesQuery['from'], $prepaidQuery);
		}
		
		if(!$this->setPrepaidDataToQuery($prepaidQuery)) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * Get the mongo query to run on the prepaid collection.
	 * @param type $input
	 * @return type
	 */
	protected function getPrepaidQuery($input) {
		$prepaidQuery = array();
		
		$accountName = $input->get('pp_includes_name');
		if(!empty($accountName)) {
			$prepaidQuery['name'] = $accountName;
		}
		$accountExtrenalId = $input->get('pp_includes_external_id');
		if(!empty($accountExtrenalId)) {
			$prepaidQuery['external_id '] = $accountExtrenalId;
		}
		
		return $prepaidQuery;
	}
	
	protected function setPrepaidDataToQuery($prepaidQuery) {
		// Get the prepaid record.
		$prepaidCollection = Billrun_Factory::db()->prepaidIncludesCollection();
		
		// TODO: Use the prepaid DB/API proxy.
		$prepaidRecord = $prepaidCollection->query($prepaidQuery)->cursor()->current();
		if(!$prepaidRecord || $prepaidRecord->isEmpty()) {
			Billrun_Factory::log("Failed to get prepaid record.", Zend_Log::NOTICE);
			return false;
		}
		
		// TODO: Check if they are set? Better to have a prepaid record object with this functionallity.
		$chargingBy = $prepaidRecord['charging_by'];
		$chargingByUsegt = $prepaidRecord['charging_by_usaget'];

		$this->balancesQuery['charging_by'] = $chargingBy;
		$this->balancesQuery['charging_by_usaget'] = $chargingByUsegt;
		
		return true;
	}
}