<?php

namespace Concrete\Package\CommunityStoreDpsPxpay\Src\CommunityStore\Payment\Methods\CommunityStoreDpsPxpay;

/*
 * Author: Jeremy Rogers infoatjero.co.nz
 * Adapted from Paypal Standard payment method
 * License: MIT
 */

use Concrete\Core\Support\Facade\Application;
use Concrete\Package\CommunityStoreDpsPxpay\Src\Lib\WindCaveCS;
use Config;
use Concrete\Package\CommunityStore\Src\CommunityStore\Payment\Method as StorePaymentMethod;
use Concrete\Package\CommunityStoreDpsPxpay\Src\Lib\PXPay2;

class CommunityStoreDpsPxpayPaymentMethod extends WindCaveCS {
	public function dashboardForm () {
		$this->set('pxpay2URL', Config::get('community_store_dps_pxpay.pxpay2URL'));
		$this->set('pxpay2Currency', Config::get('community_store_dps_pxpay.pxpay2Currency'));
		$this->set('pxpay2TxType', Config::get('community_store_dps_pxpay.pxpay2TxType'));
		$this->set('pxpay2EnableBillCard', Config::get('community_store_dps_pxpay.pxpay2EnableBillCard'));
		$this->set('pxpay2UserID', Config::get('community_store_dps_pxpay.pxpay2UserID'));
		$this->set('pxpay2AccessKey', Config::get('community_store_dps_pxpay.pxpay2AccessKey'));
		$this->set('pxpay2Debug', Config::get('community_store_dps_pxpay.pxpay2Debug'));
		$this->set('pxpay2Receipt', Config::get('community_store_dps_pxpay.pxpay2Receipt'));
		// These are the only currencies supported by DPS, AFAIK.
		$currencies = array(
			'AUD' => 'Australian Dollar',
			'NZD' => 'New Zealand Dollar',
			'USD' => 'US Dollar'
		);
		$this->set('currencies', $currencies);
		$app = Application::getFacadeApplication();
		$this->set('form', $app->make('helper/form'));
	}

	public function save (array $data = []) {
		Config::save('community_store_dps_pxpay.pxpay2URL', $data['pxpay2URL']);
		Config::save('community_store_dps_pxpay.pxpay2Currency', $data['pxpay2Currency']);
		Config::save('community_store_dps_pxpay.pxpay2TxType', $data['pxpay2TxType']);
		Config::save('community_store_dps_pxpay.pxpay2EnableBillCard', $data['pxpay2EnableBillCard']);
		Config::save('community_store_dps_pxpay.pxpay2UserID', $data['pxpay2UserID']);
		Config::save('community_store_dps_pxpay.pxpay2AccessKey', $data['pxpay2AccessKey']);
		Config::save('community_store_dps_pxpay.pxpay2Debug', (isset($data['pxpay2Debug']) ? 1 : 0));
		Config::save('community_store_dps_pxpay.pxpay2Receipt', (isset($data['pxpay2Receipt']) ? 1 : 0));
	}

	public function validate ($args, $e) {
		$pm = StorePaymentMethod::getByHandle('community_store_dps_pxpay');
		if ($args['paymentMethodEnabled'][$pm->getID()] == 1) {
			if ($args['pxpay2UserID'] == '') {
				$e->add(t('User ID must be set'));
			}
			if ($args['pxpay2AccessKey'] == '') {
				$e->add(t('Access key must be set'));
			}
			if ($args['pxpay2URL'] == '') {
				$e->add(t('PXPay2 URL must be set'));
			}
		}

		return $e;

	}


	public function getName () {
		return 'WindCave PXPay2';
	}

	protected function makeRequest () {
		return new PXPay2(Config::get('community_store_dps_pxpay.pxpay2UserID'),
			Config::get('community_store_dps_pxpay.pxpay2AccessKey'),
			Config::get('community_store_dps_pxpay.pxpay2URL'));
	}
}
