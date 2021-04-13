<?php
 
/*
Plugin Name: Xendit for GiveWP 
Plugin URI: https://github.com/RegiAdi/xendit-for-givewp
Description: Xendit payment gateway add-on for GiveWP Plugin.
Version: 1.0
Author: Regi Adi
Author URI: https://github.com/regiadi
License: GPLv2 or later
Text Domain: Xendit
*/

require_once('vendor/autoload.php');

use Xendit\Xendit;

/**
 * Register payment method.
 *
 * @since 1.0.0
 *
 * @param array $gateways List of registered gateways.
 *
 * @return array
 */

add_action( 'init', 'xendit_for_give_listen');

/**
 * Listen for Xendit events.
 *
 * @access public
 * @since  2.5.0
 *
 * @return void
 */
function xendit_for_give_listen() {
    // Must be a stripe listener to proceed.
    if ( ! isset( $give_listener ) || 'xendit' !== $give_listener ) {
        return;
    }

    // Retrieve the request's body and parse it as JSON.
    $body  = @file_get_contents( 'php://input' );
    $event = json_decode( $body );

    var_dump($event);

    $message = 'Success';

    status_header( 200 );
    exit( $message );
}

function xendit_for_give_register_payment_method( $gateways ) {
  // Duplicate this section to add support for multiple payment method from a custom payment gateway.
  $gateways['xendit'] = array(
    'admin_label'    => __( 'Xendit', 'xendit-for-give' ), // This label will be displayed under Give settings in admin.
    'checkout_label' => __( 'Xendit', 'xendit-for-give' ), // This label will be displayed on donation form in frontend.
  );
  
  return $gateways;
}

add_filter( 'give_payment_gateways', 'xendit_for_give_register_payment_method' );

/**
 * Register Section for Payment Gateway Settings.
 *
 * @param array $sections List of payment gateway sections.
 *
 * @since 1.0.0
 *
 * @return array
 */

function xendit_for_give_register_payment_gateway_sections( $sections ) {
	
	// `xendit-settings` is the name/slug of the payment gateway section.
	$sections['xendit-settings'] = __( 'Xendit', 'xendit-for-give' );

	return $sections;
}

add_filter( 'give_get_sections_gateways', 'xendit_for_give_register_payment_gateway_sections' );

/**
 * Register Admin Settings.
 *
 * @param array $settings List of admin settings.
 *
 * @since 1.0.0
 *
 * @return array
 */
function xendit_for_give_register_payment_gateway_setting_fields( $settings ) {
	switch ( give_get_current_setting_section() ) {
		case 'xendit-settings':
			$settings = array(
				array(
					'id'   => 'give_title_xendit',
					'type' => 'title',
				),
			);

            $settings[] = array(
				'name' => __( 'Enable Test Mode', 'give-xendit-enable-test' ),
				'desc' => __( 'Enable Xendit Test Environment.', 'xendit-for-give' ),
				'id'   => 'xendit_for_give_xendit_enable_test',
				'type' => 'checkbox',
		    );

            $settings[] = array(
				'name' => __( 'Test Mode API Key', 'give-xendit-api-key-test' ),
				'desc' => __( 'Enter your Xendit Test API Key.', 'xendit-for-give' ),
				'id'   => 'xendit_for_give_xendit_api_key_test',
				'type' => 'text',
		    );

            $settings[] = array(
				'name' => __( 'Live Mode API Key', 'give-xendit-api-key-live' ),
				'desc' => __( 'Enter your Xendit Live API Key.', 'xendit-for-give' ),
				'id'   => 'xendit_for_give_xendit_api_key_live',
				'type' => 'text',
		    );

			$settings[] = array(
				'id'   => 'give_title_xendit',
				'type' => 'sectionend',
			);

			break;

	} // End switch().

	return $settings;
}

add_filter( 'give_get_settings_gateways', 'xendit_for_give_register_payment_gateway_setting_fields' );

/**
 * Toggle Xendit CC Billing Detail Fieldset.
 *
 * @param int $form_id Form ID.
 *
 * @return bool
 * @since 1.8.5
 */
function xendit_for_give_standard_billing_fields( $form_id ) {

	// if ( give_is_setting_enabled( give_get_option( 'paypal_standard_billing_details' ) ) ) {
	// 	give_default_cc_address_fields( $form_id );

	// 	return true;
	// }

	// if ( FormUtils::isLegacyForm( $form_id ) ) {
	// 	return false;
	// }

	printf(
		'
		<fieldset class="no-fields">
			<div style="display: flex; justify-content: center; margin-top: 20px;">
                <img width="250" height="106" src="%4$s" alt="">
			</div>
			<p style="text-align: center;"><b>%1$s</b></p>
			<p style="text-align: center;">
				<b>%2$s</b> %3$s
			</p>
		</fieldset>
	',
		__( 'Make your donation quickly and securely with Xendit', 'give' ),
		__( 'How it works:', 'give' ),
		__( 'You will be redirected to Xendit to pay using bank transfer, e-wallet, retail outlet, or with a credit or debit card. You will then be brought back to this page to view your receipt.', 'give' ),
        plugin_dir_url(__FILE__) . '/images/xendit_logo.png'
	);

	return true;

}

add_action( 'give_xendit_cc_form', 'xendit_for_give_standard_billing_fields' );

/**
 * Process Xendit checkout submission.
 *
 * @param array $posted_data List of posted data.
 *
 * @since  1.0.0
 * @access public
 *
 * @return void
 */
function xendit_for_give_process_xendit_donation( $posted_data ) {
	// Make sure we don't have any left over errors present.
	give_clear_errors();

	// Any errors?
	$errors = give_get_errors();

	// No errors, proceed.
	if ( ! $errors ) {

		$form_id         = intval( $posted_data['post_data']['give-form-id'] );
		$price_id        = ! empty( $posted_data['post_data']['give-price-id'] ) ? $posted_data['post_data']['give-price-id'] : 0;
		$donation_amount = ! empty( $posted_data['price'] ) ? $posted_data['price'] : 0;

		// Setup the payment details.
		$donation_data = array(
			'price'           => $donation_amount,
			'give_form_title' => $posted_data['post_data']['give-form-title'],
			'give_form_id'    => $form_id,
			'give_price_id'   => $price_id,
			'date'            => $posted_data['date'],
			'user_email'      => $posted_data['user_email'],
			'purchase_key'    => $posted_data['purchase_key'],
			'currency'        => give_get_currency( $form_id ),
			'user_info'       => $posted_data['user_info'],
			'status'          => 'pending',
			'gateway'         => 'xendit',
		);

		// Record the pending donation.
		$donation_id = give_insert_payment( $donation_data );

		if ( ! $donation_id ) {

			// Record Gateway Error as Pending Donation in Give is not created.
			give_record_gateway_error(
				__( 'Xendit Error', 'xendit-for-give' ),
				sprintf(
				/* translators: %s Exception error message. */
					__( 'Unable to create a pending donation with Give.', 'xendit-for-give' )
				)
			);

			// Send user back to checkout.
			give_send_back_to_checkout( '?payment-mode=xendit' );
			return;
		}

		// Do the actual payment processing using the custom payment gateway API. To access the GiveWP settings, use give_get_option() 
                // as a reference, this pulls the API key entered above: give_get_option('insta_for_give_instamojo_api_key')
        $invoice = xendit_for_give_create_invoice($donation_data);

        wp_redirect($invoice['invoice_url']);
	} else {

		// Send user back to checkout.
		give_send_back_to_checkout( '?payment-mode=xendit' );
	} // End if().
}

add_action( 'give_gateway_xendit', 'xendit_for_give_process_xendit_donation' );

/**
 * Create Xendit invoice.
 *
 * @param array $data List of user submitted data.
 *
 * @since  1.0.0
 * @access public
 *
 * @return string
 */
function xendit_for_give_create_invoice($data) {
    Xendit::setApiKey(xendit_for_give_get_api_key());

    $params = [ 
        'external_id' => $data['purchase_key'],
        'payer_email' => $data['user_email'],
        'description' => 'Pembayaran Donasi Lazismu - ' . $data['give_form_title'],
        'amount' => $data['price'],
        'should_send_email' => true,
        'success_redirect_url' => give_get_success_page_uri()
    ];

    try {
        $invoice = \Xendit\Invoice::create($params);
    } catch (\Xendit\Exceptions\ApiException $e) {
        return $this->sendError($e->getMessage());
    }

    return $invoice;
}

/**
 * Get Xendit API Key based on environment.
 *
 * @since  1.0.0
 * @access public
 *
 * @return string
 */
function xendit_for_give_get_api_key() {
    if (xendit_for_give_is_test_mode()) {
        return give_get_option('xendit_for_give_xendit_api_key_test');
    } else {
        return give_get_option('xendit_for_give_xendit_api_key_live');
    }
}

/**
 * Check Xendit test mode state.
 *
 * @since  1.0.0
 * @access public
 *
 * @return bool
 */
function xendit_for_give_is_test_mode() {
    if (give_get_option('xendit_for_give_xendit_enable_test')) {
        return true;
    } else {
        return false;
    }
}