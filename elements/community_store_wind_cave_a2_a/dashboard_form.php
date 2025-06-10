<?php
defined('C5_EXECUTE') or die(_("Access Denied."));
extract($vars);
?>

<div class="form-group">
	<label><?= t('A2A URL') ?></label>
	<input type="text" name="pxa2aURL" value="<?= ($pxa2aURLL ? $pxa2aURL : 'https://sec.paymentexpress.com/pxaccess/pxa2a.aspx') ?>" class="form-control">
</div>