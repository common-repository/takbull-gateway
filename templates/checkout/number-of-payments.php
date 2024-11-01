<?php

defined('ABSPATH') || exit; // Exit if accessed directly
?>
<fieldset class="form-row woocommerce-paments-number">
	<label class="screen-reader-text" for="takbull-number-of-payments">
		<?php _e('Number of payments', 'takbull-gateway'); ?>
	</label>
	<select name="wc-<?= $gateway->id; ?>-total-payments" id="takbull-number-of-payments">
		<option value=""><?php _e('Number of payments', 'takbull-gateway'); ?></option>
		<?php foreach ($payments as $payment) :

			if ($fee_enabled) {
				$fee = 0;
				foreach ($fees as $row => $innerArray) {
					if ($innerArray['number_of_payments'] == $payment) {
						$fee = $innerArray['fee'];
					}
				}
				if (!empty($fee)) {
					$payment_display_value = sprintf(__('%1$s - %2$s payments', 'takbull-gateway'), round($total * (1 + ($fee / 100)), 2), $payment);
				} else {
					$payment_display_value = sprintf(__('%1$s - %2$s payments', 'takbull-gateway'), $total, $payment);
				}
			} else {
				$payment_display_value = $payment;
			}
		?>
			<option value="<?= esc_attr($payment); ?>">
				<?= $payment_display_value; ?>
			</option>
		<?php endforeach; ?>
	</select>
</fieldset>