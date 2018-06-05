<?php

require_once(Mage::getBaseDir('lib') . '/PagoFacil/PagoFacilHelper.php');


class Pagofacil_Externalpayment_PaymentController extends Mage_Core_Controller_Front_Action
{
    // The redirect action is triggered when someone places an order

    public function redirectAction()
    {


        // Retrieve order
        $_order = new Mage_Sales_Model_Order();
        $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $_order->loadByIncrementId($orderId);

//MY SHIT

        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $token_service = Mage::getStoreConfig('payment/externalpayment/servicetoken');
        $token_secret = Mage::getStoreConfig('payment/externalpayment/secrettoken');
        $environment = Mage::getStoreConfig('payment/externalpayment/environment');
        $showAllPlatformsInPagoFacil = Mage::getStoreConfig('payment/externalpayment/usepagofacilplatform');
        $currency_code = Mage::app()->getStore()->getCurrentCurrencyCode();

        $showAllPlatformsInPagoFacil == 1 ? $showAllPlatformsInPagoFacil = "YES" : $showAllPlatformsInPagoFacil = "NO";


        $token_store = md5(date('m/d/Y h:i:s a', time()) . $orderId . $token_service);


        $signaturePayload = array(
            'pf_amount' => $_order->getBaseGrandTotal(),
            'pf_email' => $customer->getEmail(),
            'pf_order_id' => $orderId,
            'pf_token_service' => $token_service,
            'pf_token_store' => $token_store,
            'pf_url_complete' => Mage::getUrl('checkout/onepage/success'),
            'pf_url_callback' => Mage::getUrl('externalpayment/payment/response')

        );

        $PFHelper = new PagoFacilHelper();

        //generate signature
        $signature = $PFHelper->generateSignature($signaturePayload, $token_secret);

        //add signature to the payload
        $signaturePayload['pf_signature'] = $signature;

        //post parameters
        $postVars = '';
        $ix = 0;
        $len = count($signaturePayload);

        foreach ($signaturePayload as $key => $value) {
            if ($ix !== $len - 1) {
                if ($key == "pf_url_complete" || $key == "pf_url_callback") {
                    $postVars .= $key . "=" . urlencode($value) . "&";

                } else {
                    $postVars .= $key . "=" . $value . "&";
                }

            } else {
                if ($key == "pf_url_complete" || $key == "pf_url_callback") {
                    $postVars .= $key . "=" . urlencode($value);
                } else {
                    $postVars .= $key . "=" . $value;
                }
            }
            $ix++;
        }

        //create transaction in pago facil

        if ($showAllPlatformsInPagoFacil == 'YES') {
            $resultBeforeJSONDecode = $PFHelper->createTransaction($postVars, null, $showAllPlatformsInPagoFacil, $environment);

            $result = json_decode($resultBeforeJSONDecode, true);


            if (!empty($result) && array_key_exists("errorMessage", $result) || !empty($result) && array_key_exists("status", $result) && $result['status'] == 0) {
                $this->loadLayout();
                $block = $this->getLayout()->createBlock('Mage_Core_Block_Template', 'externalpayment', array('template' => 'externalpayment/error.phtml'));

                array_key_exists("statusCode", $result) ? $block->assign('errorCode', $result['statusCode']) : $block->assign('errorCode', $result['errorMessage']);

                $this->getLayout()->getBlock('content')->append($block);
                $this->renderLayout();

            } else {
                Mage::app()->getFrontController()->getResponse()->setRedirect($result['redirect']);
            }
        } else {

            //get services
            $services = $PFHelper->getServices($environment, $currency_code, $token_service);

            $this->loadLayout();
            $block = $this->getLayout()->createBlock('Mage_Core_Block_Template', 'externalpayment', array('template' => 'externalpayment/options.phtml'));
            $block->assign('services', $services);
            $block->assign('postVars', $postVars);
            $this->getLayout()->getBlock('content')->append($block);
            $this->renderLayout();
        }
    }

    // The response action is triggered when your gateway sends back a response after processing the customer's payment
    public function responseAction()
    {
        Mage::log("en response action");


        if ($this->getRequest()->isPost()) {

            Mage::log("en response action post");
            $PFHelper = new PagoFacilHelper();
            $token_service = Mage::getStoreConfig('payment/externalpayment/servicetoken');
            $token_secret = Mage::getStoreConfig('payment/externalpayment/secrettoken');

            $POSTsignaturePayload = json_decode(file_get_contents('php://input'), true);


            $POSTsignature = $POSTsignaturePayload['pf_signature'];
            unset($POSTsignaturePayload['pf_signature']);


            $generatedSignature = $PFHelper->generateSignature($POSTsignaturePayload, $token_secret);

            //check signature

            if ($generatedSignature !== $POSTsignature) {
                print_r("NOT THE SAME SIGNATURE!!");
                $PFHelper->httpResponseCode(400);
            }

            //get the order
            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($POSTsignaturePayload['pf_order_id']);


            //get customer data
            $customerData = Mage::getModel('customer/customer')->load($order->getCustomerId());

            Mage::log("viene el if");

            Mage::log($customerData->customer_email);//not working


            Mage::log("dentro del if");
            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, 'Gateway has authorized the payment.');

            $order->sendNewOrderEmail();
            $order->setEmailSent(true);

            $order->save();

            /*
            /* Your gateway's code to make sure the reponse you
            /* just got is from the gatway and not from some weirdo.
            /* This generally has some checksum or other checks,
            /* and is provided by the gateway.
            /* For now, we assume that the gateway's response is valid
            */

            /*
            $validated = true;
            $orderId = '123'; // Generally sent by gateway

            if ($validated) {
                // Payment was successful, so update the order's state, send order email and move to the success page
                $order = Mage::getModel('sales/order');
                $order->loadByIncrementId($orderId);
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, 'Gateway has authorized the payment.');

                $order->sendNewOrderEmail();
                $order->setEmailSent(true);

                $order->save();

                Mage::getSingleton('checkout/session')->unsQuoteId();

                Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure' => true));
            } else {
                // There is a problem in the response we got
                $this->cancelAction();
                Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/failure', array('_secure' => true));
            }
            */

        } else
            Mage_Core_Controller_Varien_Action::_redirect('');
    }

    // The cancel action is triggered when an order is to be cancelled
    public function cancelAction()
    {
        if (Mage::getSingleton('checkout/session')->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
            if ($order->getId()) {
                // Flag the order as 'cancelled' and save it
                $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Gateway has declined the payment.')->save();
            }
        }
    }

    //will get here when the client chooses any option
    public function selectedAction()
    {
        if ($this->getRequest()->isPost() && $this->getRequest()->getParam('endpoint') && $this->getRequest()->getParam('postVars')) {

            Mage::log("en selected!");


            $PFHelper = new PagoFacilHelper();

            $request['endpoint'] = $this->getRequest()->getParam('endpoint');
            $postVars = $this->getRequest()->getParam('postVars');
            $environment = Mage::getStoreConfig('payment/externalpayment/environment');
            $showAllPlatformsInPagoFacil = Mage::getStoreConfig('payment/externalpayment/usepagofacilplatform');

            $showAllPlatformsInPagoFacil == 1 ? $showAllPlatformsInPagoFacil = "YES" : $showAllPlatformsInPagoFacil = "NO";


            Mage::log("viene request y postvars!");


            Mage::log($request);

            Mage::log($postVars);


            $resultBeforeJSONDecode = $PFHelper->createTransaction($postVars, $request, $showAllPlatformsInPagoFacil, $environment);

            $result = json_decode($resultBeforeJSONDecode, true);

            if (!empty($result) && array_key_exists("errorMessage", $result) || !empty($result) && array_key_exists("status", $result) && $result['status'] == 0) {
                $this->loadLayout();
                $block = $this->getLayout()->createBlock('Mage_Core_Block_Template', 'externalpayment', array('template' => 'externalpayment/error.phtml'));

                array_key_exists("statusCode", $result) ? $block->assign('errorCode', $result['statusCode']) : $block->assign('errorCode', $result['errorMessage']);

                $this->getLayout()->getBlock('content')->append($block);
                $this->renderLayout();

            } else {
                if (empty($result)) {
                    //show webpay
                    echo $resultBeforeJSONDecode;
                } else {
                    //redirect to payment platform
                    Mage::app()->getFrontController()->getResponse()->setRedirect($result['redirect']);
                }
            }

        } else {
            Mage_Core_Controller_Varien_Action::_redirect('');
        }
    }
}