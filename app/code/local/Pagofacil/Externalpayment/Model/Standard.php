<?php
class Pagofacil_Externalpayment_Model_Standard extends Mage_Payment_Model_Method_Abstract {
	protected $_code = 'externalpayment';
	
	protected $_isInitializeNeeded      = true;
	protected $_canUseInternal          = true;
	protected $_canUseForMultishipping  = false;
	
	public function getOrderPlaceRedirectUrl() {
		return Mage::getUrl('externalpayment/payment/redirect', array('_secure' => true));
	}
}
?>