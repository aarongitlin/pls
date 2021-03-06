<?php

function espresso_display_paypal($payment_data) {
	global $wpdb;
	extract($payment_data);
	include_once ('Paypal.php');
	$myPaypal = new EE_Paypal();
	echo '<!-- Event Espresso PayPal Gateway Version ' . $myPaypal->gateway_version . '-->';
	global $org_options;
	$paypal_settings = get_option('event_espresso_paypal_settings');
	$paypal_id = empty($paypal_settings['paypal_id']) ? '' : $paypal_settings['paypal_id'];
	$paypal_cur = empty($paypal_settings['currency_format']) ? '' : $paypal_settings['currency_format'];
	$no_shipping = isset($paypal_settings['no_shipping']) ? $paypal_settings['no_shipping'] : '0';
	$use_sandbox = $paypal_settings['use_sandbox'];
	if ($use_sandbox) {
		$myPaypal->enableTestMode();
	}

	do_action('action_hook_espresso_use_add_on_functions');

	// get attendee_session
	$SQL = "SELECT attendee_session FROM " . EVENTS_ATTENDEE_TABLE . " WHERE id=%d";
	$session_id = $wpdb->get_var( $wpdb->prepare( $SQL, $attendee_id ));
	// now get all registrations for that session
	$SQL = "SELECT a.final_price, a.orig_price, a.quantity, ed.event_name, a.price_option, a.fname, a.lname ";
	$SQL .= " FROM " . EVENTS_ATTENDEE_TABLE . " a ";
	$SQL .= " JOIN " . EVENTS_DETAIL_TABLE . " ed ON a.event_id=ed.id ";
	$SQL .= " WHERE attendee_session=%s ORDER BY a.id ASC";
	
	$items = $wpdb->get_results( $wpdb->prepare( $SQL, $session_id ));
	//printr( $items, '$items  <br /><span style="font-size:10px;font-weight:normal;">' . __FILE__ . '<br />line no: ' . __LINE__ . '</span>', 'auto' );
					
	foreach ( $items as $key => $item ) {	
	
		$item_num=$key+1;
		$myPaypal->addField('item_name_' . $item_num, $item->price_option . ' for ' . $item->event_name . '. Attendee: '. $item->fname . ' ' . $item->lname);
		$myPaypal->addField('quantity_' . $item_num, absint($item->quantity));

		if ( $item->final_price < $item->orig_price ) {
		
			$adjustment = abs( $item->orig_price - $item->final_price );
			$myPaypal->addField('amount_' . $item_num, $item->orig_price);
			$myPaypal->addField('discount_amount_' . $item_num, $adjustment);
			$myPaypal->addField('discount_amount2_' . $item_num, $adjustment);
			
		} else {

			$myPaypal->addField('amount_' . $item_num, $item->final_price);
		}		
	
	}
	

//	$total_discount = (float)$total_orig_price - (float)$total_final_price;	
//	if ( $total_discount > 0 ) {
//		$myPaypal->addField('discount_amount_cart', $total_discount);
//	}
	
	$myPaypal->addField('business', $paypal_id);
	if ($paypal_settings['force_ssl_return']) {
		$home = str_replace("http://", "https://", home_url());
	} else {
		$home = home_url();
	}
	$myPaypal->addField('charset', "utf-8");
	$myPaypal->addField('return', $home . '/?page_id=' . $org_options['return_url'] . '&r_id=' . $registration_id. '&type=paypal');
	$myPaypal->addField('cancel_return', $home . '/?page_id=' . $org_options['cancel_return']);
	$myPaypal->addField('notify_url', $home . '/?page_id=' . $org_options['notify_url'] . '&id=' . $attendee_id . '&r_id=' . $registration_id . '&event_id=' . $event_id . '&attendee_action=post_payment&form_action=payment&type=paypal');
	$event_name = $wpdb->get_var('SELECT event_name FROM ' . EVENTS_DETAIL_TABLE . " WHERE id='" . $event_id . "'");
	$myPaypal->addField('cmd', '_cart');
	$myPaypal->addField('upload', '1');

	$myPaypal->addField('currency_code', $paypal_cur);
	$myPaypal->addField('image_url', empty($paypal_settings['image_url']) ? '' : $paypal_settings['image_url']);
	$myPaypal->addField('no_shipping ', $no_shipping);
	$myPaypal->addField('first_name', $fname);
	$myPaypal->addField('last_name', $lname);
	$myPaypal->addField('email', $attendee_email);
	$myPaypal->addField('address1', $address);
	$myPaypal->addField('city', $city);
	$myPaypal->addField('state', $state);
	$myPaypal->addField('zip', $zip);
	
	if (!empty($paypal_settings['bypass_payment_page']) && $paypal_settings['bypass_payment_page'] == 'Y') {
		$myPaypal->submitPayment();
	} else {
		if (empty($paypal_settings['button_url'])) {
			if (file_exists(EVENT_ESPRESSO_GATEWAY_DIR . "/paypal/btn_stdCheckout2.gif")) {
				$button_url = EVENT_ESPRESSO_GATEWAY_DIR . "/paypal/btn_stdCheckout2.gif";
			} else {
				$button_url = EVENT_ESPRESSO_PLUGINFULLURL . "gateways/paypal/btn_stdCheckout2.gif";
			}
		} elseif (isset($paypal_settings['button_url'])) {
			$button_url = $paypal_settings['button_url'];
		} else {
			$button_url = EVENT_ESPRESSO_PLUGINFULLURL . "gateways/paypal/btn_stdCheckout2.gif";
		}
		$myPaypal->submitButton($button_url, 'paypal');
	}

	if ($use_sandbox) {

		echo '<h3 style="color:#ff0000;" title="Payments will not be processed">' . __('PayPal Debug Mode Is Turned On', 'event_espresso') . '</h3>';
		$myPaypal->dump_fields();
	}
}

add_action('action_hook_espresso_display_offsite_payment_gateway', 'espresso_display_paypal');

//function espresso_itemize_paypal_items($myPaypal, $attendee_id) {
//
//	global $wpdb;
//	
//	// get attendee_session
//	$SQL = "SELECT attendee_session FROM " . EVENTS_ATTENDEE_TABLE . " WHERE id=%d";
//	$session_id = $wpdb->get_var( $wpdb->prepare( $SQL, $attendee_id ));
//	// now get all registrations for that session
//	$SQL = "SELECT a.final_price, a.orig_price, a.quantity, ed.event_name, a.price_option, a.fname, a.lname, dc.coupon_code_price, dc.use_percentage ";
//	$SQL .= " FROM " . EVENTS_ATTENDEE_TABLE . " a ";
//	$SQL .= " JOIN " . EVENTS_DETAIL_TABLE . " ed ON a.event_id=ed.id ";
//	$SQL .= " LEFT JOIN " . EVENTS_DISCOUNT_CODES_TABLE . " dc ON a.coupon_code=dc.coupon_code ";
//	$SQL .= " WHERE attendee_session=%s ORDER BY a.id ASC";
//	
//	$items = $wpdb->get_results( $wpdb->prepare( $SQL, $session_id ));
//
//	$total_orig_price = 0;
//	$total_final_price = 0;
//	
//	foreach ($items as $key=>$item) {
//		$total_orig_price += $item->orig_price * $item->quantity;
//		$total_final_price += $item->final_price * $item->quantity;
//		$item_num=$key+1;
//		$myPaypal->addField('item_name_' . $item_num, $item->price_option . ' for ' . $item->event_name . '. Attendee: '. $item->fname . ' ' . $item->lname);
//		$myPaypal->addField('amount_' . $item_num, $item->orig_price);
//		$myPaypal->addField('quantity_' . $item_num, $item->quantity);
//	}
//	
//	$total_discount = (float)$total_orig_price - (float)$total_final_price;
//	
//	if ( $total_discount > 0 ) {
//		$myPaypal->addField('discount_amount_cart', $total_discount);
//	}
//	
//}
