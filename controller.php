<?php

namespace Concrete\Package\CommunityStoreDpsPxpay;

use Concrete\Core\Package\Package;
use Route;
use Config;
use Whoops\Exception\ErrorException;
use \Concrete\Package\CommunityStore\Src\CommunityStore\Payment\Method as PaymentMethod;

class Controller extends Package
{
    protected $pkgHandle = 'community_store_dps_pxpay';
    protected $appVersionRequired = '8.5.0';
    protected $pkgVersion = '2.0.2';


	protected $pkgAutoloaderRegistries = [
		'src/CommunityStore' => '\Concrete\Package\CommunityStoreDpsPxpay\Src\CommunityStore',
		'src/Lib' => '\Concrete\Package\CommunityStoreDpsPxpay\Src\Lib',
	];

    public function getPackageDescription()
    {
        return t('Windcave / DPS Payment Express PXPay2 Payment Method for Community Store');
    }

    public function getPackageName()
    {
        return t('Windcave / DPS PXPay2 Payment Method');
    }

    public function install()
    {
        $installed = Package::getInstalledHandles();
        if(!(is_array($installed) && in_array('community_store',$installed)) ) {
            throw new ErrorException(t('This package requires that Community Store be installed'));
        } else {
            $pkg = parent::install();
            $pm = new PaymentMethod();
            $pm->add('community_store_dps_pxpay','DPS',$pkg);
        }

    }

    public function upgrade(){
    	if (Config::get('community_store_dps_pxpay.pxpay2Receipt') === null) {
			Config::save('community_store_dps_pxpay.pxpay2Receipt', '1');
		}
    	parent::upgrade();
	}

    public function uninstall()
    {
        $pm = PaymentMethod::getByHandle('community_store_dps_pxpay');
        if ($pm) {
            $pm->delete();
        }
        $pkg = parent::uninstall();
    }

    public function on_start() {
        Route::register('/checkout/pxpaysuccess','\Concrete\Package\CommunityStoreDpsPxpay\Src\CommunityStore\Payment\Methods\CommunityStoreDpsPxpay\CommunityStoreDpsPxpayPaymentMethod::DpsSuccess');
        Route::register('/checkout/pxpayfail','\Concrete\Package\CommunityStoreDpsPxpay\Src\CommunityStore\Payment\Methods\CommunityStoreDpsPxpay\CommunityStoreDpsPxpayPaymentMethod::DpsFail');
    }
}
?>