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
    protected $pkgVersion = '3.0.0';


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
            $pm->add('community_store_dps_pxpay','WindCave',$pkg);
	        $pm = new PaymentMethod();
	        $pm->add('community_store_dps_windcave_a2a','WindCave A2A',$pkg);
        }

    }

    public function upgrade(){
		$pkg = Package::getByHandle($this->pkgHandle);
    	if (Config::get('community_store_dps_pxpay.pxpay2Receipt') === null) {
			Config::save('community_store_dps_pxpay.pxpay2Receipt', '1');
		}
	    $pm = PaymentMethod::getByHandle('community_store_wind_cave_a2_a');
	    if (!$pm) {
		    $pm = new PaymentMethod();
		    $pm->add('community_store_wind_cave_a2_a','WindCave A2A',$pkg);
	    }
    	parent::upgrade();
	}

    public function uninstall()
    {
        $pm = PaymentMethod::getByHandle('community_store_dps_pxpay');
        if ($pm) {
            $pm->delete();
        }
	    $pm = PaymentMethod::getByHandle('community_store_wind_cave_a2_a');
	    if ($pm) {
		    $pm->delete();
	    }
        parent::uninstall();
    }

    public function on_start() {
        Route::register('/checkout/pxpaysuccess','\Concrete\Package\CommunityStoreDpsPxpay\Src\CommunityStore\Payment\Methods\CommunityStoreDpsPxpay\CommunityStoreDpsPxpayPaymentMethod::DpsSuccess');
        Route::register('/checkout/pxpayfail','\Concrete\Package\CommunityStoreDpsPxpay\Src\CommunityStore\Payment\Methods\CommunityStoreDpsPxpay\CommunityStoreDpsPxpayPaymentMethod::DpsFail');
    }
}