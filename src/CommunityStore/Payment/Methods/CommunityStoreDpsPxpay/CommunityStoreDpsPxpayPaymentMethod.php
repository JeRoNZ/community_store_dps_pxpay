<?php

namespace Concrete\Package\CommunityStoreDpsPxpay\Src\CommunityStore\Payment\Methods\CommunityStoreDpsPxpay;

/*
 * Author: Jeremy Rogers infoatjero.co.nz
 * Adapted from Paypal Standard payment method
 * License: MIT
 */

use Concrete\Core\Http\Request;
use Core;
use Symfony\Component\HttpFoundation\RedirectResponse;
use URL;
use Config;

use \Concrete\Package\CommunityStore\Src\CommunityStore\Payment\Method as StorePaymentMethod;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Order\Order as StoreOrder;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Customer\Customer as StoreCustomer;
use Concrete\Core\Logging\Logger;
use Concrete\Package\CommunityStoreDpsPxpay\Src\Lib\PXPay2;

class CommunityStoreDpsPxpayPaymentMethod extends StorePaymentMethod {
	/* @var $logger \Monolog\Logger */
	private $logger = null;

	public function dashboardForm () {
		$this->set('pxpay2URL', Config::get('community_store_dps_pxpay.pxpay2URL'));
		$this->set('pxpay2Currency', Config::get('community_store_dps_pxpay.pxpay2Currency'));
		$this->set('pxpay2TxType', Config::get('community_store_dps_pxpay.pxpay2TxType'));
		$this->set('pxpay2EnableBillCard', Config::get('community_store_dps_pxpay.pxpay2EnableBillCard'));
		$this->set('pxpay2UserID', Config::get('community_store_dps_pxpay.pxpay2UserID'));
		$this->set('pxpay2AccessKey', Config::get('community_store_dps_pxpay.pxpay2AccessKey'));
		$this->set('pxpay2Debug', Config::get('community_store_dps_pxpay.pxpay2Debug'));
		// These are the only currencies supported by DPS, AFAIK.
		$currencies = array(
			'AUD' => "Australian Dollar",
			'NZD' => "New Zealand Dollar",
			'USD' => "U.S. Dollar"
		);
		$this->set('currencies', $currencies);
		$this->set('form', Core::make("helper/form"));
	}

	public function save (array $data = []) {
		Config::save('community_store_dps_pxpay.pxpay2URL', $data['pxpay2URL']);
		Config::save('community_store_dps_pxpay.pxpay2Currency', $data['pxpay2Currency']);
		Config::save('community_store_dps_pxpay.pxpay2TxType', $data['pxpay2TxType']);
		Config::save('community_store_dps_pxpay.pxpay2EnableBillCard', $data['pxpay2EnableBillCard']);
		Config::save('community_store_dps_pxpay.pxpay2UserID', $data['pxpay2UserID']);
		Config::save('community_store_dps_pxpay.pxpay2AccessKey', $data['pxpay2AccessKey']);
		Config::save('community_store_dps_pxpay.pxpay2Debug', ($data['pxpay2Debug'] ? 1 : 0));
	}

	public function validate ($args, $e) {
		$pm = StorePaymentMethod::getByHandle('community_store_dps_pxpay');
		if ($args['paymentMethodEnabled'][$pm->getID()] == 1) {
			if ($args['pxpay2UserID'] == "") {
				$e->add(t("User ID must be set"));
			}
			if ($args['pxpay2AccessKey'] == "") {
				$e->add(t("Access key must be set"));
			}
			if ($args['pxpay2URL'] == "") {
				$e->add(t("PXPay2 URL must be set"));
			}
		}

		return $e;

	}

	private function log ($message, $force = false) {
		if (!$force) {
			if (!Config::get('community_store_dps_pxpay.pxpay2Debug')) {
				return false;
			}
		}
		if (!$this->logger) {
			$this->logger = new Logger('windcave');
		}
		if ($force) {
			$this->logger->addError($message);
		} else {
			$this->logger->addDebug($message);
		}

		return true;

	}

	public function redirectForm () {
		// Unlike Paypal, DPS needs to generate a request url by communicating with the card gateway
		// and then redirecting the browser to the returned url. This also sets up a session in DPS.
		// Rather than generating a form that has to auto-submit itself,
		// we redirect the browser directly to the gateway.
		$request = $this->makeRequest();

		$session = Core::make('session');
		/* @var $session \Symfony\Component\HttpFoundation\Session\Session */

		$oid = $session->get('orderID');
		$order = StoreOrder::getByID($oid);
		if (!$order) {
			$this->log('Unable to find the order ' . $oid, true);
			throw new \Exception('Unable to find the order');
		}
		/* @var $order StoreOrder */

		$custID = $order->getCustomerID();
		$customer = new StoreCustomer($custID);

		$request->setTxnData1($customer->getValue("billing_first_name") . ' ' . $customer->getValue("billing_last_name"));
		$request->setTxnData2(implode(' ', array($customer->getValue("billing_address")->address1, $customer->getValue("billing_address")->address2)));
		$request->setTxnData3($customer->getValue("billing_address")->city);

		$request->setAmountInput($order->getTotal());

		$request->setTxnType(Config::get('community_store_dps_pxpay.pxpay2TxType'));
		$request->setInputCurrency(Config::get('community_store_dps_pxpay.pxpay2Currency'));
		$request->setMerchantReference($oid);
		$request->setEmailAddress($customer->getEmail());
		// This option allows the merchant to debit the card throught the DPS website, e.g. if they want extra stuff
		// added on to their order after they make payment.
		// It does NOT affect the store transaction in any way.
		$request->setEnableAddBillCard(Config::get('community_store_dps_pxpay.pxpay2EnableBillCard'));

		#$base = \Core::getApplicationURL();
		$request->setUrlFail((string) \URL::to('/checkout/pxpayfail'));
		$request->setUrlSuccess((string) \URL::to('/checkout/pxpaysuccess'));

		$request_string = $request->makeRequest();
		if ($request_string === false) {
			$this->log(__METHOD__ . PHP_EOL . ' $request->makeRequest returned FALSE', true);
			$this->log(__METHOD__ . PHP_EOL . " Error:\n" . $request->error, true);
			throw new \Exception('Error communicating with card gateway');
		}

		header("Location: $request_string");
		die();
	}

	public function getName () {
		return 'DPS PXPay2';
	}

	public function isExternal () {
		return true;
	}

	/**
	 * @return RedirectResponse
	 * @throws \Exception
	 */
	public function DpsSuccess () {
		$response = $this->getResponse();
		$this->log(__METHOD__ . PHP_EOL . var_export($response, true));

		if ((string) $response->Success != '1') {
			$this->log(__METHOD__ . PHP_EOL . ' Redirecting to /checkout/pxpayfail because Success !=1');

			return new RedirectResponse(\URL::to('/checkout/pxpayfail'));
		}

		$oid = (string) $response->MerchantReference;
		$order = StoreOrder::getByID($oid);
		if (!$order) {
			$this->log(t('Fatal: DPS: no such order ' . $oid), true);
			throw new \Exception('Fatal: DPS: no such order');
		}

		// DPS has a fail proof notification service (FPRN) where they ping the success URL several times from their end,
		// so even if the punter doesn't click next to go back to the website, we get a notification.
		// Thus, we may get pinged at least twice (once by the user, once by DPS).
		// Therefore, we only want to set the order status/completion and send emails once.
		// We don't care who pinged us, just set the status/complete the order and jump to the complete page.

		/* @var $order StoreOrder */


		$request = Request::getInstance();
		$userAgent = $request->server->get('HTTP_USER_AGENT');
		$ip = $request->server->get('REMOTE_ADDR');

		$userStuff = PHP_EOL. 'User Agent: '.$userAgent.PHP_EOL.'IP: '.$ip;

		if (!$order->getTransactionReference()) {
			$this->log(t('Completing order because it does not have a transaction reference set'.$userStuff));
			// No transaction reference, so we must not have been pinged yet.
			// Order status does not appear to change between initiation and payment, so cannot be used.
			// Complete the order, pushing in the tx ref.
			$order->completeOrder((string) $response->DpsTxnRef);
		} else {
			$this->log(t('NOT Completing order because it already has a transaction reference set'.$userStuff));
		}
		$this->log(t('Redirecting to /checkout/complete'));

		return new RedirectResponse(\URL::to('/checkout/complete'));
	}

	public function DPSFail () {
		$response = $this->getResponse();
		$this->log(__METHOD__ . PHP_EOL . var_export($response, true));
		$session = Core::make('session');
		/* @var $session \Symfony\Component\HttpFoundation\Session\Session */
		$session->set('paymentErrors', (string) $response->ResponseText);
		
		// failed page gives exceptions in the logs when visited by DPS FPN because there's no session info.
		// Don't bother redirecting if it's the FPN calling
		$ua = $this->request->server->get('HTTP_USER_AGENT');

		if ($ua === 'PXL1') {
			$this->log(t('PXL1 user agent detected, not redirecting'));
			// Meaningless response, but it gives a 200 status which is what we want
			
			return new JsonResponse(['OK' => 1]);
		}

		$this->log(t('Redirecting to /checkout/failed'));

		return new RedirectResponse(\URL::to('/checkout/failed'));

	}

	private function getResponse () {
		$request = $this->makeRequest();

// getResponse method in PxAccess object returns PxPayResponse object
// which encapsulates all the response data
		if (array_key_exists('result', $_GET))
			$result = $_GET['result'];
		else {
			$this->log('Warning: DPS: No result in $_GET, searching QUERY_STRING', true);
// Workaround the suhosin.get.max_value_length problem:
// The $_GET array doesn't seem to contain the result
// value, even though it's clearly visible in the query string.
// Therefore we check QUERY_STRING for a second opinion:
			$parts = explode('&', $_SERVER['QUERY_STRING']);
			foreach ($parts as $part) {
				if (strpos($part, 'result=') === 0) {
					$result = substr($part, 7);
					break;
				}
			}
		}
		if (!$result) {
			$this->log(t('Fatal: DPS: Unable to find a result parameter, jumping to home page'), true);
			throw new \Exception('Fatal: DPS: Unable to find a result parameter');
		}
		$this->log(__METHOD__ . PHP_EOL . var_export($result, true));
		$response = $request->decode($result); // SimpleXML
		$this->log(__METHOD__ . PHP_EOL . var_export($response, true));

		return $response;
	}

	private function makeRequest () {
		return new PXPay2(Config::get('community_store_dps_pxpay.pxpay2UserID'),
			Config::get('community_store_dps_pxpay.pxpay2AccessKey'),
			Config::get('community_store_dps_pxpay.pxpay2URL'));
	}
}
