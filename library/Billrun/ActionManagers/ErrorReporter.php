<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Trait to implement error reportimg.
 *
 * @author tom
 */
trait Billrun_ActionManagers_ErrorReporter {
	/**
	 * This members holds the error message to be reported.
	 */
	protected $error = "Successful";
	protected $errorCode = 0;
	protected $errors = array();
	
	
	/**
	 * Get the current error of the action.
	 * @return string the description for the current error.
	 */
	public function getError() {
		return $this->error;
	}
	
	public function getErrorCode() {
		return $this->errorCode;
	}

	
	/**
	 * Report an error.
	 * @param int $errorCode - Error index to report.
	 * @param Zend_Log_Filter_Priority $errorLevel
	 */
	protected function reportError($errorCode, $errorLevel=Zend_Log::INFO, array $args = array()) {
		if (!is_numeric($errorCode)) {
			$this->error = $errorCode;
			$this->errorCode = -1;
		} else {
			if (isset($this->errors[$errorCode])) {
				$this->error = $this->errors[$errorCode];
				
				if(!empty($args)) {
					$this->error=vsprintf($this->error, $args);
				}
				
				$this->errorCode = $errorCode;
			} else {
				$this->error = 'Unknown issue';
				$this->errorCode = 999999;
			}
		}
		Billrun_Factory::log($this->errorCode . ": " . $this->error, $errorLevel);
	}
}