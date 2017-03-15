<?php
defined('C5_EXECUTE') or die(_("Access Denied."));
extract($vars);
?>

<div class="form-group">
	<?= $form->label('Currency', t("Currency")); ?>
	<?= $form->select('pxpay2Currency', $currencies, $pxpay2Currency ? $pxpay2Currency : 'NZD'); ?>
</div>

<div class="form-group">
	<label><?= t("PXPay2 URL") ?></label>
	<input type="text" name="pxpay2URL" value="<?= ($pxpay2URL ? $pxpay2URL : 'https://sec.paymentexpress.com/pxaccess/pxpay.aspx') ?>" class="form-control">
</div>

<div class="form-group">
	<label><?= t("Transaction Type") ?></label>
	<?= $form->select('pxpay2TxType', array('Auth' => t('Auth'), 'Purchase' => t('Purchase')), $pxpay2TxType); ?>
</div>

<div class="form-group">
	<label><?= t("Enable card billing") ?></label>
	<?= $form->select('pxpay2EnableBillCard', array('0' => t('No'), '1' => t('Yes')), $pxpay2EnableBillCard); ?>
</div>

<div class="form-group">
	<label><?= t("User ID") ?></label>
	<input type="text" name="pxpay2UserID" value="<?= $pxpay2UserID ?>" class="form-control">
</div>

<div class="form-group">
	<label><?= t("Access Key") ?></label>
	<input type="text" name="pxpay2AccessKey" value="<?= $pxpay2AccessKey ?>" class="form-control">
</div>