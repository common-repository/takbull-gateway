<?php
defined('ABSPATH') || exit; // Exit if accessed directly
?>
<style>
	#takbull-transactions table th {
		text-align: left;
	}
</style>
<table id="takbull-transactions" style="width: 100%;">
	<tbody>
		<th>Transaction Id</th>
		<th>Date</th>
		<th>Status</th>
		<th>Card last 4 dig</th>
		<th>Invoice</th>
		<?php foreach ($transactions as $transaction) : ?>
			<tr>
				<td>
					<?php
					echo $transaction->get_id();
					?>
				</td>
				<td>
					<?php
					echo $transaction->get_transactionDate();
					?>
				</td>
				<td>
					<?php
					echo $transaction->get_status();
					?>
				</td>
				<td>
					<?php
					echo $transaction->get_last4DigitsCardNumber();
					?>
				</td>
				<td>
					<?php
					$invoice = $transaction->get_invoiceLink();
					if (!empty($invoice)) {
						printf(
							'<a href="https://api.takbull.co.il/PublicInvoice/Invoice?InvUniqId=%2$s"  target="_blank">%1$s</a>',
							__('Get Invoice', 'woocommerce-gateway-takbull'),
							$invoice
						);
					}
					?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>