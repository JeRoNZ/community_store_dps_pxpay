<?php
namespace Concrete\Package\CommunityStoreDpsPxpay\Src\Lib;

defined('C5_EXECUTE') or die('ACCESS DENIED');


class PXPay2 {
	private $user;
	private $key;
	private $curlDebug = 0;
	private $TxnType = 'Purchase';
	private $AmountInput = 0;
	private $CurrencyInput = 'NZD';
	private $MerchantReference = '';
	private $TxnData1 = '';
	private $TxnData2 = '';
	private $TxnData3 = '';
	private $EmailAddress = '';
	private $TxnId = '';
	private $BillingId = '';
	private $EnableAddBillCard = 0;
	private $UrlSuccess = '';
	private $UrlFail = '';

	public $error;

	const TYPE = 'Purchase'; // or Auth

	function __construct ($user, $key, $url) {
		$this->user = $user;
		$this->key = $key;
		$this->url = $url;
	}

	public function setAmountInput ($amount) {
		//$this->AmountInput = round($amount, 2);
		// Any amount ending .10, .20 but NOT .00 ends up as a 1 decimal place value triggering a "IUAmount Format Fail" error.
		$this->AmountInput = trim(sprintf('%7.2f',round($amount, 2)));
	}

	public function setCurrencyInput ($cur) {
		$this->CurrencyInput = $cur;
	}

	private function entities ($string) {
		setlocale(LC_ALL, 'en_GB.UTF-8');
		$x = iconv('UTF-8', 'ASCII//TRANSLIT', $string);

		return htmlentities($x, ENT_COMPAT, APP_CHARSET);
	}

	public function setMerchantReference ($ref) {
		$this->MerchantReference = $this->entities($ref);
	}

	public function TxnData1 ($ref) {
		$this->TxnData1 = $this->entities($ref);
	}

	public function TxnData2 ($ref) {
		$this->TxnData2 = $this->entities($ref);
	}

	public function TxnData3 ($ref) {
		$this->TxnData3 = $this->entities($ref);
	}

	public function setEmailAddress ($email) {
		$this->EmailAddress = $email;
	}

	public function setTxnData1 ($text) {
		$this->TxnData1 = $this->entities($text);
	}

	public function setTxnData2 ($text) {
		$this->TxnData2 = $this->entities($text);
	}

	public function setTxnData3 ($text) {
		$this->TxnData2 = $this->entities($text);
	}

	public function setTxnId ($TxnId) {
		$this->TxnId = $this->entities($TxnId);
	}

	function setTxnType($TxnType){
		$this->TxnType = $TxnType;
	}
	function setInputCurrency($InputCurrency){
		$this->CurrencyInput = $InputCurrency;
	}
	public function setBillingId ($BillingId) {
		$this->BillingId = $this->entities($BillingId);
	}

	public function setEnableAddBillCard () {
		$this->EnableAddBillCard = 1;
	}

	public function setUrlSuccess ($UrlSuccess) {
		$this->UrlSuccess = $UrlSuccess;
	}

	public function setUrlFail ($UrlFail) {
		$this->UrlFail = $UrlFail;
	}

	public function setDebug () {
		$this->curlDebug = 1;
	}

	private function submitXml ($xml) {
		$this->error = false;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->url);
		curl_setopt($ch, CURLOPT_VERBOSE, $this->curlDebug); //set to 1 for verbose output
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);

		$return = curl_exec($ch);
		$this->error = curl_errno($ch);

		if (!$this->error) {
			try {
				$sx = new \SimpleXMLElement($return);
			} catch (Exception $e) {
				$this->error = $e->getMessage();

				return false;
			}

			return $sx;
		}

		var_dump($return);

		return false;
	}

	public function makeRequest () {
		$xml = new \SimpleXMLElement('<GenerateRequest></GenerateRequest>');
		$xml->addChild('PxPayUserId', $this->user);
		$xml->addChild('PxPayKey', $this->key);
		$xml->addChild('TxnType', ($this->TxnType) ? $this->TxnType : self::TYPE);
		$xml->addChild('AmountInput', $this->AmountInput);
		$xml->addChild('CurrencyInput', $this->CurrencyInput);
		$xml->addChild('MerchantReference', $this->MerchantReference);
		$xml->addChild('TxnData1', $this->TxnData1);
		$xml->addChild('TxnData2', $this->TxnData2);
		$xml->addChild('TxnData3', $this->TxnData3);
		$xml->addChild('EmailAddress', $this->EmailAddress);
		$xml->addChild('TxnId', $this->TxnId);
		$xml->addChild('BillingId', $this->BillingId);
		$xml->addChild('EnableAddBillCard', $this->EnableAddBillCard);
		$xml->addChild('UrlSuccess', $this->UrlSuccess);
		$xml->addChild('UrlFail', $this->UrlFail);

		$sx = $this->submitXml($xml->asXML());

		if ($this->error)
			return false;

# good: <Request valid="1"><URI>https://sec.paymentexpress.com/pxmi3/EF4054F622D6C4C1BA2941CF6E676FC0416DA900571307BBC823DD391FA4D1E456386A2FD1B4B046F</URI></Request>
# bad:  <Request valid="1"><Reco>IP</Reco><ResponseText>Check User Access Error:</ResponseText></Request>
# bad: <Request valid="1"><Reco>W4</Reco><ResponseText>TxnId/TxnRef duplicate</ResponseText></Request>
# bad: <html><body><p>Error - Not acceptable input XML.</p></body></html>

		$valid = (int) $sx->attributes()->valid[0];
		if (! $valid) { // The XML supplied was broken
			$this->error= $sx->asXML();
			return false;
		}
		if ($sx->Reco) { // Valid XML, but some other error, maybe bad credentials etc.
			$this->error = (string) $sx->Reco . (string) $sx->ResponseText;
			return false;
		}

		// all good, extract the URI to redirect the browser to.
		return (string) $sx->URI;

	}

	public function decode ($response) {
		$xml = new \SimpleXMLElement('<ProcessResponse></ProcessResponse>');
		$xml->addChild('PxPayUserId', $this->user);
		$xml->addChild('PxPayKey', $this->key);
		$xml->addChild('Response', $response);

		return $this->submitXml($xml->asXML());
	}
}