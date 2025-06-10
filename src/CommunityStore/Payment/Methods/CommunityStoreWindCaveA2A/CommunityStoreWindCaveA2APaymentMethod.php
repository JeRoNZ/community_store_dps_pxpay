<?php

namespace Concrete\Package\CommunityStoreDpsPxpay\Src\CommunityStore\Payment\Methods\CommunityStoreWindCaveA2A;

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

class CommunityStoreWindCaveA2APaymentMethod extends WindCaveCS {
	public function dashboardForm () {
		$this->set('pxa2aURL', Config::get('community_store_windcave_a2a.pxa2aURL'));
		$app = Application::getFacadeApplication();
		$this->set('form', $app->make('helper/form'));
	}

	public function save (array $data = []) {
		Config::save('community_store_windcave_a2a.pxa2aURL', $data['pxa2aURL']);
	}

	public function validate ($args, $e) {
		$pm = StorePaymentMethod::getByHandle('community_store_dps_pxpay');
		if ($args['paymentMethodEnabled'][$pm->getID()] == 1) {
			if ($args['pxa2aURL'] == '') {
				$e->add(t('URL must be set'));
			}
		}

		return $e;

	}

	public function getName () {
		return 'Windcave Account2Account';
	}

	protected function makeRequest () {
		return new PXPay2(Config::get('community_store_dps_pxpay.pxpay2UserID'),
			Config::get('community_store_dps_pxpay.pxpay2AccessKey'),
			Config::get('community_store_windcave_a2a.pxa2aURL'));
	}
}
