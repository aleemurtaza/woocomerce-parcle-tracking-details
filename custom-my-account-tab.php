<?php
/**
 * Plugin Name: Add Parcel Tracking Details Tab
 * Description: Adds a form to the WooCommerce Order Details page and My Account page for parcel tracking details.
 * Version: 1.9
 * Author: Alee Murtaza
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Add CSS for the form and button
function aptd_enqueue_styles() {
    echo '<style>
        .aptd-parcel-tracking-form {
            max-width: 600px;
            margin-bottom: 20px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            display: none; /* Initially hide the form */
        }
        .aptd-parcel-tracking-form.active {
            display: block;
        }
        .aptd-parcel-tracking-form p {
            margin-bottom: 15px;
        }
        .aptd-parcel-tracking-form label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .aptd-parcel-tracking-form input[type="text"],
        .aptd-parcel-tracking-form input[type="file"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .aptd-parcel-tracking-form input[type="submit"] {
            background-color: #007cba;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
        .aptd-parcel-tracking-form input[type="submit"]:hover {
            background-color: #005a87;
        }
        .aptd-add-more-tracking-button {
            background-color: #007cba;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-block;
            margin-bottom: 20px;
        }
        .aptd-add-more-tracking-button:hover {
            background-color: #005a87;
        }
    </style>';
}
add_action( 'wp_head', 'aptd_enqueue_styles' );

// Add endpoint for the new tab
function aptd_add_my_account_endpoint() {
    add_rewrite_endpoint( 'parcel-tracking', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'aptd_add_my_account_endpoint' );

// Add query variable for the new endpoint
function aptd_add_query_vars( $vars ) {
    $vars[] = 'parcel-tracking';
    return $vars;
}
add_filter( 'query_vars', 'aptd_add_query_vars' );

// Add the new tab to My Account menu
function aptd_add_my_account_menu_item( $items ) {
    $items['parcel-tracking'] = 'Parcel Tracking';
    return $items;
}
add_filter( 'woocommerce_account_menu_items', 'aptd_add_my_account_menu_item' );

// Display content for the new tab
function aptd_my_account_endpoint_content() {
    echo '<h5>Parcel Tracking</h5>';
    
    if ( isset( $_GET['success'] ) && $_GET['success'] == 'true' ) {
        echo '<p style="color: green;">Your details have been successfully submitted.</p>';
    }
    
    // Add the "Add Tracking" button
    echo '<button class="aptd-add-more-tracking-button" id="aptd-add-more-tracking-button">Add Tracking</button>';

    // Display the form
    ?>
    <form method="post" enctype="multipart/form-data" class="aptd-parcel-tracking-form" id="aptd-parcel-tracking-form">
        <p>
            <label for="order_number">Your Order Number</label>
            <input type="text" name="order_number" id="order_number" required>
        </p>
        <p>
            <label for="courier_company_name">Courier Company Name</label>
            <input type="text" name="courier_company_name" id="courier_company_name" required>
        </p>
        <p>
            <label for="tracking_id">Tracking ID</label>
            <input type="text" name="tracking_id" id="tracking_id" required>
        </p>
        <p>
            <label for="tracking_image">Upload Image</label>
            <input type="file" name="tracking_image" id="tracking_image" required>
        </p>
        <p>
            <input type="submit" name="submit_parcel_tracking_form" value="Submit">
        </p>
    </form>
    
    <?php
    // Display previous records for the logged-in user
    global $wpdb;
    $user_id = get_current_user_id();
    $order_number = isset($_GET['order_number']) ? sanitize_text_field($_GET['order_number']) : '';

    if ($order_number) {
        $table_name = $wpdb->prefix . 'parcel_tracking_data';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_id = %d AND order_number = %s",
                $user_id, $order_number
            )
        );

        if ( $results ) {
            echo '<h5>Previous Tracking Records</h5>';
            echo '<table border="1" class="widefat">
                    <thead>
                        <tr>
                            <th>Order Number</th>
                            <th>Courier Company Name</th>
                            <th>Tracking ID</th>
                            <th>Image URL</th>
                        </tr>
                    </thead>
                    <tbody>';
            foreach ( $results as $row ) {
                echo '<tr>
                        <td>' . esc_html( $row->order_number ) . '</td>
                        <td>' . esc_html( $row->courier_company_name ) . '</td>
                        <td>' . esc_html( $row->tracking_id ) . '</td>
                        <td><a href="' . esc_url( $row->image_url ) . '" target="_blank">View Image</a></td>
                    </tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No records found for this order.</p>';
        }
    } else {
        echo '<p>Please enter an order number to view records.</p>';
    }
}
add_action( 'woocommerce_account_parcel-tracking_endpoint', 'aptd_my_account_endpoint_content' );

// Handle form submission and save data
function aptd_handle_form_submission() {
    if ( isset( $_POST['submit_parcel_tracking_form'] ) && isset( $_FILES['tracking_image'] ) ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $order_number = sanitize_text_field( $_POST['order_number'] );
        $courier_company_name = sanitize_text_field( $_POST['courier_company_name'] );
        $tracking_id = sanitize_text_field( $_POST['tracking_id'] );
        $image = $_FILES['tracking_image'];

        // Handle file upload
        if ( $image['error'] === UPLOAD_ERR_OK ) {
            $upload = wp_upload_bits( $image['name'], null, file_get_contents( $image['tmp_name'] ) );
            if ( ! $upload['error'] ) {
                $image_url = $upload['url'];
                // Save data to the database
                $table_name = $wpdb->prefix . 'parcel_tracking_data';
                $wpdb->insert(
                    $table_name,
                    array(
                        'user_id'              => $user_id,
                        'order_number'         => $order_number,
                        'courier_company_name' => $courier_company_name,
                        'tracking_id'          => $tracking_id,
                        'image_url'            => $image_url,
                    ),
                    array(
                        '%d',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                    )
                );
                // Redirect to avoid resubmission and show success message
                wp_redirect( add_query_arg( array( 'success' => 'true', 'order_number' => $order_number ), wc_get_account_endpoint_url( 'parcel-tracking' ) ) );
                exit;
            }
        }
    }
}
add_action( 'template_redirect', 'aptd_handle_form_submission' );

// Create custom database table on plugin activation
function aptd_create_database_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'parcel_tracking_data';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        order_number varchar(255) NOT NULL,
        courier_company_name varchar(255) NOT NULL,
        tracking_id varchar(255) NOT NULL,
        image_url varchar(255) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'aptd_create_database_table' );

// Add JavaScript to handle form display toggle
function aptd_enqueue_scripts() {
    ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var form = document.getElementById('aptd-parcel-tracking-form');
            var button = document.getElementById('aptd-add-more-tracking-button');
            if (form && button) {
                button.addEventListener('click', function() {
                    form.classList.toggle('active');
                });
            }
        });
    </script>
    <?php
}
add_action('wp_footer', 'aptd_enqueue_scripts');

// Add CSS for the admin table
function aptd_enqueue_admin_styles() {
    echo '<style>
        .aptd-admin-table {
            width: 448px;
            border-collapse: collapse;
            margin-top: 120px;
        }
        .aptd-admin-table th,
        .aptd-admin-table td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        .aptd-admin-table th {
            background-color: #f4f4f4;
            text-align: left;
        }
        .aptd-admin-table td {
            vertical-align: top;
        }
        .aptd-admin-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .aptd-admin-table tr:hover {
            background-color: #f1f1f1;
        }
        .aptd-admin-table a {
            color: #007cba;
            text-decoration: none;
        }
        .aptd-admin-table a:hover {
            text-decoration: underline;
        }
    </style>';
}
add_action( 'admin_head', 'aptd_enqueue_admin_styles' );

// Display parcel tracking details on the WooCommerce order details page in admin
function aptd_display_parcel_tracking_details_in_admin( $order ) {
    global $wpdb;
    $order_id = $order->get_id();
    $table_name = $wpdb->prefix . 'parcel_tracking_data';

    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE order_number = %s",
            $order_id
        )
    );

    if ( $results ) {
        echo '<h3>Parcel Tracking Details</h3>';
        echo '<table class="aptd-admin-table">
                <thead>
                    <tr>
                        <th>Order Number</th>
                        <th>Courier Company Name</th>
                        <th>Tracking ID</th>
                        <th>Image URL</th>
                    </tr>
                </thead>
                <tbody>';
        foreach ( $results as $row ) {
            echo '<tr>
                    <td>' . esc_html( $row->order_number ) . '</td>
                    <td>' . esc_html( $row->courier_company_name ) . '</td>
                    <td>' . esc_html( $row->tracking_id ) . '</td>
                    <td><a href="' . esc_url( $row->image_url ) . '" target="_blank">View Image</a></td>
                </tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No parcel tracking details found for this order.</p>';
    }
}
add_action( 'woocommerce_admin_order_data_after_order_details', 'aptd_display_parcel_tracking_details_in_admin' );
