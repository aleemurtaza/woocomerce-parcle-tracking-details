<?php
/**
 * Plugin Name: Parcel Tracking Order Validation Addon
 * Description: Adds order number validation functionality to the Parcel Tracking plugin.
 * Version: 1.0
 * Author: Alee Murtaza
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Function to validate the order number
function pto_validate_order_number() {
    if ( isset( $_POST['submit_parcel_tracking_form'] ) ) {
        $order_number = sanitize_text_field( $_POST['order_number'] );

        if ( empty( $order_number ) || ! wc_get_order( $order_number ) ) {
            // Add a custom error message if order number is not valid
            wc_add_notice( __( 'Invalid order number. Please enter a valid order number associated with your account.', 'parcel-tracking-addon' ), 'error' );
            wp_redirect( wc_get_account_endpoint_url( 'parcel-tracking' ) );
            exit;
        }
    }
}

// Hook into the template_redirect action to validate before submission is handled
add_action( 'template_redirect', 'pto_validate_order_number', 9 );
