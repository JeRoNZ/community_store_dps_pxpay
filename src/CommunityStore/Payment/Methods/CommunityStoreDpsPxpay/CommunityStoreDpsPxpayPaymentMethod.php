<?php
namespace Concrete\Package\CommunityStoreDpsPxpay\Src\CommunityStore\Payment\Methods\CommunityStoreDpsPxpay;

/*
 * Author: Jeremy Rogers infoatjero.co.nz
 * Adapted from Paypal Standard payment method
 * License: MIT
 */

use Core;
use Symfony\Component\HttpFoundation\RedirectResponse;
use URL;
use Config;
use Session;
use Log;

use \Concrete\Package\CommunityStore\Src\CommunityStore\Payment\Method as StorePaymentMethod;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Cart\Cart as StoreCart;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Order\Order as StoreOrder;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Customer\Customer as StoreCustomer;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Order\OrderStatus\OrderStatus as StoreOrderStatus;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Utilities\Calculator as StoreCalculator;
use Concrete\Package\CommunityStoreDpsPxpay\Src\Lib\PXPay2;

class CommunityStoreDpsPxpayPaymentMethod extends StorePaymentMethod
{
	public function dashboardForm ()
	{
		$this->set('pxpay2URL', Config::get('community_store_dps_pxpay.pxpay2URL'));
		$this->set('pxpay2Currency', Config::get('community_store_dps_pxpay.pxpay2Currency'));
		$this->set('pxpay2TxType', Config::get('community_store_dps_pxpay.pxpay2TxType'));
		$this->set('pxpay2EnableBillCard', Config::get('community_store_dps_pxpay.pxpay2EnableBillCard'));
		$this->set('pxpay2UserID', Config::get('community_store_dps_pxpay.pxpay2UserID'));
		$this->set('pxpay2AccessKey', Config::get('community_store_dps_pxpay.pxpay2AccessKey'));
		// These are the only currencies supported by DPS, AFAIK.
		$currencies = array(
			'AUD' => "Australian Dollar",
			'NZD' => "New Zealand Dollar",
			'USD' => "U.S. Dollar"
		);
		$this->set('currencies', $currencies);
		$this->set('form', Core::make("helper/form"));
	}

	public function save (array $data = [])
	{
		Config::save('community_store_dps_pxpay.pxpay2URL', $data['pxpay2URL']);
		Config::save('community_store_dps_pxpay.pxpay2Currency', $data['pxpay2Currency']);
		Config::save('community_store_dps_pxpay.pxpay2TxType', $data['pxpay2TxType']);
		Config::save('community_store_dps_pxpay.pxpay2EnableBillCard', $data['pxpay2EnableBillCard']);
		Config::save('community_store_dps_pxpay.pxpay2UserID', $data['pxpay2UserID']);
		Config::save('community_store_dps_pxpay.pxpay2AccessKey', $data['pxpay2AccessKey']);
	}

	public function validate ($args, $e)
	{
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

	public function redirectForm ()
	{
		// Unlike Paypal, DPS needs to generate a request url by communicating with the card gateway
		// and then redirecting the browser to the returned url. This also sets up a session in DPS.
		// Rather than generating a form that has to auto-submit itself,
		// we redirect the browser directly to the gateway.
		$request = $this->makeRequest();

		$oid = Session::get('orderID');
		$order = StoreOrder::getByID($oid);
		if (!$order) {
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
			\Log::addEntry(__METHOD__ . ' $request->makeRequest returned FALSE', 'DPS');
			\Log::addEntry(__METHOD__ . " Error:\n" . $request->error, 'DPS');
			throw new \Exception('Error communicating with card gateway');
		}

		header("Location: $request_string");
		die();
	}

	public function getName ()
	{
		return 'DPS PXPay2';
	}

	public function isExternal ()
	{
		return true;
	}

	public function DpsSuccess ()
	{
		$response = $this->getResponse();
		if ((string) $response->Success != '1') {
			return new RedirectResponse(\URL::to('/checkout/pxpayfail'));
		}

		$oid = (string) $response->MerchantReference;
		$order = StoreOrder::getByID($oid);
		if (!$order) {
			\Log::addEntry(t('Fatal: DPS: no such order ' . $response->MerchantReference));
			throw new \Exception('Fatal: DPS: no such order');
		}

		// DPS has a fail proof notification service (FPRN) where they ping the success URL several times from their end,
		// so even if the punter doesn't click next to go back to the website, we get a notification.
		// Thus, we may get pinged at least twice (once by the user, once by DPS).
		// Therefore, we only want to set the order status/completion and send emails once.
		// We don't care who pinged us, just set the status/complete the order and jump to the complete page.

		/* @var $order StoreOrder */

		if (!$order->getTransactionReference()) {
			// No transaction reference, so we must not have been pinged yet.
			// Order status does not appear to change between initiation and payment, so cannot be used.
			// Complete the order, pushing in the tx ref.
			$order->completeOrder((string) $response->DpsTxnRef);
		}
		return new RedirectResponse(\URL::to('/checkout/complete'));
	}

	public function DPSFail ()
	{
		$response = $this->getResponse();
		Session::set('paymentErrors', (string) $response->ResponseText);
		return new RedirectResponse(\URL::to('/checkout/failed'));

	}

	private function getResponse ()
	{
		$request = $this->makeRequest();

// getResponse method in PxAccess object returns PxPayResponse object
// which encapsulates all the response data
		if (array_key_exists('result', $_GET))
			$result = $_GET['result'];
		else {
			\Log::addEntry(t('Warning: DPS: No result in $_GET, searching QUERY_STRING'));
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
			\Log::addEntry(t('Fatal: DPS: Unable to find a result parameter, jumping to home page'));
			throw new \Exception('Fatal: DPS: Unable to find a result parameter');
		}
		$response = $request->decode($result); // SimpleXML

		return $response;
	}

	private function makeRequest ()
	{
		return new PXPay2(Config::get('community_store_dps_pxpay.pxpay2UserID'),
			Config::get('community_store_dps_pxpay.pxpay2AccessKey'),
			Config::get('community_store_dps_pxpay.pxpay2URL'));
	}
}