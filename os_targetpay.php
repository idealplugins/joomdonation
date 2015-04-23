<?php
/**
 * @version		3.1
 * @package		Joomla
 * @subpackage	Joom Donation
 * @author		Yellow Melon
 * @copyright	Copyright (C) 2013 Yellow Melon
 * @license		GNU/GPL, see LICENSE.php
 */
defined( '_JEXEC' ) or die ;

require_once(dirname(__FILE__) .'/targetpay.class.php');

class os_targetpay extends os_payment {
	/**
	 * Targetpay URL
	 *
	 * @var string
	 */
	var $_url = null;	
	/**
	 * Containing all parameters will be submitted to targetpay server
	 *
	 * @var array
	 */		
	var $_params = array();
	/**
	 * Constructor function
	 *
	 * @return os_targetpay
	 */
	function os_targetpay($params) {
		parent::setName('os_targetpay');
		parent::os_payment() ;
		parent::setCreditCard(false);
		parent::setCardType(false);
		parent::setCardCvv(false);
		parent::setCardHolderName(false);
		$this->setParam('rtlo', $params->get('tp_rtlo'));
		$this->setParam('appId', '7e8d37655efaedd55dae720d10f605d9');
		$this->setParam('currency', $params->get('tp_currency', 'EUR'));
		$this->setParam('language', 'EN');
		$this->installSQL();
	}
	/**
	 * Set the parameter 
	 *
	 * @param string $name
	 * @param string $value
	 */		
	function setParam($name, $val) {
		$this->_params[$name] = $val;	
	}
	
	function getParam($name) {
		return (!empty($this->_params[$name]) ? $this->_params[$name] : false);
	}
	/**
	 * Setup an array of parameter
	 *
	 * @param array $params
	 */
	function setParams($params) {
		foreach ($params as $key=>$val) {
			$this->_params[$key] = $val;
		}
	} 	
	/**
	 * Process Payment 
	 *
	 */
	function processPayment($row, $data) {
		$Itemid = JRequest::getInt('Itemid');
		$siteUrl = JURI::base() ;
		$this->setParam('transaction_id', $row->transaction_id);
		$this->setParam('amount', $data['gateway_amount']);
		$this->setParam('item_id', $Itemid);
		$this->setParam('id', $row->id);
		$this->setParam('return_url', $siteUrl.'index.php?option=com_jdonation&view=complete&id='.$row->id.'&Itemid='.$Itemid);
		$this->setParam('cancel_url', $siteUrl.'index.php?option=com_jdonation&task=cancel&id='.$row->id.'&Itemid='.$Itemid);
		$this->setParam('status_url', $siteUrl.'index.php?option=com_jdonation&task=payment_confirm&payment_method=os_targetpay&id='.$row->id);
		$this->setParam('firstname', $data['first_name']);
		$this->setParam('lastname', $data['last_name']);
		$this->setParam('address', $data['address']);
		$this->setParam('address2', $data['address2']); 
		$this->setParam('phone_number', $row->phone);
		$this->setParam('postal_code', $row->zip);
		$this->setParam('city', $row->city);
		$this->setParam('state', $row->state);
		$this->setParam('country', $row->country);
		$this->submitPost();
	}
	/**
	 * Submit post to targetpay server
	 *
	 */
	function submitPost() {
		$requestTransaction = false;
		$redirect = false;
		$error = '';
		$TargetPayCore = new TargetPayCore('AUTO',$this->getParam('rtlo'),$this->getParam('appId'));
		$bankArr = $TargetPayCore->getBankList();
		
		
		
		if(isset($_POST)) {
			if(isset($_POST["bankID"]) && (empty($_POST["bankID"]) || !array_key_exists($_POST["bankID"],$bankArr))) {
				$error = "Selecteer een valide bank";
			} else if(isset($_POST["bankID"])){
				$requestTransaction = true;
			}
		}
		
		if($requestTransaction) {
			$rd_amount = JRequest::getVar ('rd_amount', 0);
			$amount = JRequest::getVar ('amount', 0);
			$amount = (!empty($amount)) ? $amount : $rd_amount;
			$TargetPayCore->setBankId($_POST["bankID"]);
			$TargetPayCore->setAmount($amount*100);
			$description = 'Donatie id: '.$this->getParam('transaction_id');
			$TargetPayCore->setDescription($description);
			$TargetPayCore->setReturnUrl($this->getParam('return_url'));
			$TargetPayCore->setReportUrl($this->getParam('status_url'));
			$TargetPayCore->setCancelUrl($this->getParam('cancel_url'));
			$result = @$TargetPayCore->startPayment();
			
			if ($result !== false) {
				$data["cart_id"]			= $this->getParam('id');
				$data["rtlo"]				= $this->getParam('rtlo');
				$data["paymethod"]			= $TargetPayCore->getPayMethod();
				$data["transaction_id"]		= $TargetPayCore->getTransactionId();
				$data["bank_id"]			= $TargetPayCore->getBankId();
				$data["description"]		= $TargetPayCore->getDescription();
				$data["amount"]				= $TargetPayCore->getAmount();
				$data["bankaccount"]		= 'NULL';
				$data["name"]				= 'NULL';
				$data["city"]				= 'NULL';
				$data["status"]				= '0';
				$data["via"]				= 'NULL';
				$this->installSQL();
				$this->__storeTargetpayRequestData($data);
				$redirect = true;
			} else {
				$error = $TargetPayCore->getErrorMessage();
			}
			
			
		}
		if(!$redirect) {
			$html = '<p>Selecteer hieronder hoe u wilt betalen.</p>';
			$html .= '<form name="jd_form" id="jd_form" method="post" action="">';
			$html .= $this->makeHiddenFields($_POST);
			$html .= '<p class="warning">{error}</p>';
			$html .= '<p>'.$this->makeBankSelectBox($bankArr).'</p>';
			$html .= '<input type="submit" name="Submit" class="button" value="Betaal" />';
			$html .= '</form>';
			$html = str_replace('{error}',((strlen($error) > 0) ?'Er is een probleem ontstaan: '.$error : ''),$html);
			echo $html;
			
		} else {
			echo $TargetPayCore->getBankUrl();
			header('Location: '.$TargetPayCore->getBankUrl());
			die();
		}
		
	}
	
	function makeHiddenFields($arr) {
		$hidden = '';
		foreach($arr AS $key => $value) {
			if($key !== 'bankID' && $key !== 'Submit') {
				$hidden .= '<input type="hidden" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars($value).'" />';
			}
		}
		return $hidden;
	}
	
	function makeBankSelectBox($bankArr) {
		
		$box = '<select name="bankID">';
		$box .= '<option value="">Selecteer je betaal methode</option>';
		foreach($bankArr AS $bankCode => $description) {
			$box .= '<option value="'.$bankCode.'">'.$description.'</option>';
		}
		$box .= '</select>';
		return $box;
	}
	
	
	/**
	 * Validate the data submited from targetpay server to our server
	 *
	 * @param array $data
	 * @param object $config
	 */
	function _validate($data, $params) {		
		$val =  $data['merchant_id'].
				$data['transaction_id'].
				strtoupper(md5($params->get('mb_secret_word'))).
				$data['mb_amount'].
				$data['mb_currency'].
				$data['status']		
				;
		$val = strtoupper(md5($val));
		if ($val != $data['md5sig'])
			return false;
		else 	
			return true;					
	}	
	/**
	 * Confirm payment process 
	 * @return boolean : true if success, otherwise return false
	 */
	function verifyPayment() {
		$config = JoomDonationHelper::getConfig() ;
		$db = JFactory::getDBO() ;
		$data =  $_REQUEST;
		
		$targetInfo = $this->__retrieveTargetpayInformation($data['trxid']);
		$TargetPayCore = new TargetPayCore($targetInfo->paymethod,$targetInfo->rtlo,$this->getParam('appId'));
		$TargetPayCore->checkPayment($targetInfo->transaction_id);
		if(!$TargetPayCore->getPaidStatus()) {
			echo "Not paid " . $TargetPayCore->getErrorMessage(). "... (JoomDonation, 23-04-2015)";
			die();
		}
		
		$sql = 'SELECT params FROM #__jd_payment_plugins WHERE name="os_targetpay"';
		$db->setQuery($sql) ;
		$params =  $db->loadResult() ;
		if (version_compare(JVERSION, '1.6.0', 'ge')) {
		    $params = new JRegistry($params) ;
		} else {
		    $params = new JParameter($params) ;
		}

		$ret = true ;
		$id = $data['id'];
		if ($ret) {
   			$row = JTable::getInstance('jdonation', 'Table');
   			$row->load($id);
   			$row->transaction_id = $data['mb_transaction_id']  ;
   			$row->payment_date =  date('Y-m-d H:i:s');
   			$row->published = true;
   			$row->store();
   			
			//Only send emails on success
			if($TargetPayCore->getPaidStatus()) {
				JoomDonationHelper::sendEmails($row, $config);
				JPluginHelper::importPlugin( 'jdonation' );
				$dispatcher = JDispatcher::getInstance();
				$dispatcher->trigger( 'onAfterPaymentSuccess', array($row));
				return true;
			}
		}
		return false;
	}
	
	function __storeTargetpayRequestData($data) {

		// Get a db connection.
		$db = JFactory::getDbo();
		 
		// Create a new query object.
		$query = $db->getQuery(true);
		
		foreach($data AS $key => $value) {
			$columns[] = $key;
			$values[] = $db->quote($value);
		}
		
		// Prepare the insert query.
		$query
			->insert($db->quoteName('#__joomDonation_targetpay'))
			->columns($db->quoteName($columns))
			->values(implode(',', $values));
		 
		// Reset the query using our newly populated query object.
		$db->setQuery($query);
		$db->execute();
		return $db->insertid();
	}
	
	function __retrieveTargetpayInformation($trxid){
		// Get a db connection.
		$db = JFactory::getDbo();
		 
		// Create a new query object.
		$query = $db->getQuery(true);
		 
		// Select all records from the user profile table where key begins with "custom.".
		// Order it by the ordering field.
		$query->select(array('id','cart_id','rtlo','paymethod','transaction_id','bank_id','description','amount','bankaccount','name','city','status','via'));
		
		$query->from('#__joomDonation_targetpay');
		$query->where("transaction_id = '".$trxid."'");
		 
		// Reset the query using our newly populated query object.
		$db->setQuery($query);
		$db->execute();
		// Load the results as a list of stdClass objects.
		return $db->loadObjectList();
	}
	
	/*
	 * ToDo:
	 * This is a temporarily fix. JoomDonation is at the moment (the 4th of july 2013)
	 * not able to process any sql-files while installing the payment option.
	 * So, we had to do it in the code
	 */
	function installSQL() {
		// Get a db connection.
		$db = JFactory::getDbo();
		
		$query = "CREATE TABLE IF NOT EXISTS `#__joomDonation_targetpay` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `cart_id` varchar(11) NOT NULL DEFAULT '0',
				  `rtlo` int(11) NOT NULL,
				  `paymethod` varchar(8) NOT NULL DEFAULT 'IDE',
				  `transaction_id` varchar(255) NOT NULL,
				  `bank_id` varchar(8) NOT NULL,
				  `description` int(64) NOT NULL,
				  `amount` decimal(11,2) NOT NULL,
				  `bankaccount` varchar(25) DEFAULT NULL,
				  `name` varchar(35) DEFAULT NULL,
				  `city` varchar(25) DEFAULT NULL,
				  `status` int(5) NOT NULL,
				  `via` varchar(10) DEFAULT NULL,
				  PRIMARY KEY (`id`),
				  KEY `cart_id` (`cart_id`),
				  KEY `transaction_id` (`transaction_id`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=0 ;";
		$db->setQuery($query);
		$db->execute();
	}
	
}
