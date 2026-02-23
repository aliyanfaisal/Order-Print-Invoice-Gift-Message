<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Opigm_Admin_UI {

	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		
        add_filter( 'manage_edit-shop_order_columns', [ $this, 'add_order_columns' ] );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'render_order_columns' ], 99, 2 );
        
        add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'add_order_columns' ] );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'render_order_columns' ], 99, 2 );

		add_filter( 'bulk_actions-edit-shop_order', [ $this, 'add_bulk_actions_prepared' ], 5 );
        add_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this, 'add_bulk_actions_prepared' ], 5 );

		add_filter( 'bulk_actions-edit-shop_order', [ $this, 'add_bulk_actions_download' ], 10 );
        add_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this, 'add_bulk_actions_download' ], 10 );
        
        add_filter( 'bulk_actions-edit-shop_order', [ $this, 'add_bulk_actions_print' ], 20 );
        add_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this, 'add_bulk_actions_print' ], 20 );

        add_filter( 'bulk_actions-edit-shop_order', [ $this, 'add_bulk_actions_extra' ], 30 );
        add_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this, 'add_bulk_actions_extra' ], 30 );

        add_filter( 'bulk_actions-edit-shop_order', [ $this, 'rename_bulk_actions' ], 99 );
        add_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this, 'rename_bulk_actions' ], 99 );

		add_filter( 'handle_bulk_actions-edit-shop_order', [ $this, 'handle_bulk_actions' ], 10, 3 );
        add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', [ $this, 'handle_bulk_actions' ], 10, 3 );
        
        add_action( 'woocommerce_process_shop_order_meta', [ $this, 'save_prepared_status' ] );
        
        add_action( 'wp_ajax_opigm_toggle_prepared', [ $this, 'ajax_toggle_prepared' ] );

        // Views (Links at top)
        add_filter( 'views_edit-shop_order', [ $this, 'add_prepared_views' ] );
        add_filter( 'views_woocommerce_page_wc-orders', [ $this, 'add_prepared_views' ] );

        // Filtering Logic
        add_action( 'pre_get_posts', [ $this, 'handle_prepared_pre_get_posts' ], 50 );
        add_filter( 'woocommerce_order_query_args', [ $this, 'handle_prepared_hpos_filter' ], 50, 1 );
        add_filter( 'woocommerce_order_list_query_args', [ $this, 'handle_prepared_hpos_filter' ], 50, 1 );
        add_filter( 'woocommerce_orders_table_query_clauses', [ $this, 'modify_hpos_count_query' ], 10, 3 );
        add_action( 'admin_footer', [ $this, 'fix_pagination_display' ], 1 );

        add_filter( 'wc_order_statuses', [ $this, 'rename_order_statuses' ] );
        add_filter( 'woocommerce_register_shop_order_post_statuses', [ $this, 'rename_post_statuses' ] );
        add_filter( 'woocommerce_admin_reports_order_statuses', [ $this, 'rename_report_labels' ] );
        
        add_action( 'wp_ajax_opigm_print_all_single', [ $this, 'ajax_print_all_single' ] );
        add_action( 'admin_footer', [ $this, 'admin_footer_scripts' ] );
    }

    public function handle_prepared_hpos_filter( $query_args ) {
        if ( isset( $_GET['opigm_prepared_filter'] ) && ! empty( $_GET['opigm_prepared_filter'] ) ) {
            if ( isset( $query_args['opigm_skip_filter'] ) && $query_args['opigm_skip_filter'] ) {
                return $query_args;
            }

            $filter = sanitize_text_field( $_GET['opigm_prepared_filter'] );
            
            $query_args['status'] = [ 'processing', 'completed' ];

            if ( isset( $query_args['meta_query'] ) ) {
                $query_args['meta_query'] = [];
            } else {
                $query_args['meta_query'] = [];
            }

            if ( 'prepared' === $filter ) {
                $query_args['meta_query'][] = [
                    'key'   => '_opigm_is_prepared',
                    'value' => 'yes'
                ];
            } elseif ( 'not_prepared' === $filter ) {
                $query_args['meta_query'][] = [
                    'relation' => 'OR',
                    [
                        'key'     => '_opigm_is_prepared',
                        'value'   => 'yes',
                        'compare' => '!='
                    ],
                    [
                        'key'     => '_opigm_is_prepared',
                        'compare' => 'NOT EXISTS'
                    ]
                ];
            }
        }
        return $query_args;
    }

    public function modify_hpos_count_query( $clauses, $query_vars, $data_store ) {
        if ( ! isset( $_GET['opigm_prepared_filter'] ) || empty( $_GET['opigm_prepared_filter'] ) ) {
            return $clauses;
        }
        
        return $clauses;
    }

    public function fix_pagination_display() {
        if ( ! isset( $_GET['opigm_prepared_filter'] ) || empty( $_GET['opigm_prepared_filter'] ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || 'woocommerce_page_wc-orders' !== $screen->id ) {
            return;
        }

        $filter = sanitize_text_field( $_GET['opigm_prepared_filter'] );
        
        // Get the correct count
        $args = [
            'status' => [ 'processing', 'completed' ],
            'type'   => 'shop_order', // Exclude refunds to match admin list
            'limit' => -1,
            'return' => 'ids',
        ];

        if ( 'prepared' === $filter ) {
            $args['meta_query'] = [
                [
                    'key'   => '_opigm_is_prepared',
                    'value' => 'yes'
                ]
            ];
        } elseif ( 'not_prepared' === $filter ) {
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => '_opigm_is_prepared',
                    'value'   => 'yes',
                    'compare' => '!='
                ],
                [
                    'key'     => '_opigm_is_prepared',
                    'compare' => 'NOT EXISTS'
                ]
            ];
        }

        $orders = wc_get_orders( $args );
        $correct_count = count( $orders );
        $per_page = 20; // Default WooCommerce orders per page
        $total_pages = max( 1, ceil( $correct_count / $per_page ) );

        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Fix the "X items" text
            $('.displaying-num').text('<?php echo $correct_count; ?> items');
            
            // Fix total pages in pagination
            $('.total-pages').text('<?php echo $total_pages; ?>');
            
            // If we're on a page that doesn't exist anymore, hide pagination
            <?php if ( $total_pages <= 1 ): ?>
            $('.tablenav-pages').hide();
            <?php endif; ?>
        });
        </script>
        <?php
    }

    public function save_prepared_status( $post_id ) {
        $order = wc_get_order( $post_id );
        if ( ! $order ) return;

        $is_prepared = isset( $_POST['opigm_is_prepared'] ) ? 'yes' : 'no';
        $order->update_meta_data( '_opigm_is_prepared', $is_prepared );
        $order->save();
    }
    
	public function add_meta_boxes() {
		$screen_id = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop_order' ) : 'shop_order';
		add_meta_box(
			'opigm_order_exports',
			__( 'Order Exports and Prepared', 'afb-offcanvas' ),
			[ $this, 'render_meta_box' ],
			$screen_id,
			'side',
			'high'
		);
	}

	public function render_meta_box( $post ) {
        if ( $post instanceof WC_Order ) {
            $order_id = $post->get_id();
            $order    = $post;
        } else {
            $order_id = $post->ID;
            $order    = wc_get_order( $order_id );
        }
        
        $is_prepared = $order ? ( $order->get_meta( '_opigm_is_prepared', true ) === 'yes' ) : false;

		$url = wp_nonce_url( admin_url( 'admin-ajax.php?action=opigm_print_gift_message&order_id=' . $order_id ), 'opigm_print_gift_message', 'nonce' );
		?>
		<div class="opigm-meta-box-content">
             <p class="form-field form-field-wide" style="margin-bottom: 20px;">
                <label for="opigm_is_prepared" style="display: inline-block; font-weight: bold; margin-right: 10px; color: #46b450; font-size: 14px; vertical-align: middle;">
                    <?php esc_html_e( 'Order is ready?', 'afb-offcanvas' ); ?>
                </label>
                <input type="checkbox" name="opigm_is_prepared" id="opigm_is_prepared" value="yes" <?php checked( $is_prepared ); ?> style="transform: scale(1.5); margin: 0; vertical-align: middle; accent-color: #46b450;">
            </p>
            
            <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">
            
            <?php do_action( 'opigm_meta_box_actions', $order ); ?>

			<a href="#" class="button button-secondary opigm-pdf-action-btn" data-url="<?php echo esc_url( $url ); ?>" data-type="gift" style="width: 100%; text-align: center; margin-bottom: 5px;">
				<?php esc_html_e( 'Generate Gift Message PDF', 'afb-offcanvas' ); ?>
			</a>

            <?php 
            $details_url = wp_nonce_url( admin_url( 'admin-ajax.php?action=opigm_print_order_details&order_id=' . $order_id ), 'opigm_print_order_details', 'nonce' );
            $invoice_url = wp_nonce_url( admin_url( 'admin-ajax.php?action=opigm_print_invoice&order_id=' . $order_id ), 'opigm_print_invoice', 'nonce' );
            ?>
            <a href="#" class="button button-secondary opigm-pdf-action-btn" data-url="<?php echo esc_url( $details_url ); ?>" data-type="order_details" style="width: 100%; text-align: center; margin-bottom: 5px;">
				<?php esc_html_e( 'Export Order Details PDF', 'afb-offcanvas' ); ?>
			</a>
            <?php
            $print_all_url = wp_nonce_url( admin_url( 'admin-ajax.php?action=opigm_print_all_single&order_id=' . $order_id ), 'opigm_print_all_single', 'nonce' );
            ?>
            <a href="#" class="button button-secondary opigm-pdf-action-btn" data-url="<?php echo esc_url( $print_all_url ); ?>" data-type="print_all" style="width: 100%; text-align: center; margin-bottom: 5px;">
				<?php esc_html_e( 'Print All', 'afb-offcanvas' ); ?>
			</a>
		</div>
		<?php
	}

	public function add_order_columns( $columns ) {
		$new_columns = [];
		foreach ( $columns as $key => $column ) {
			$new_columns[ $key ] = $column;
			if ( 'order_status' === $key ) {
				$new_columns['opigm_prepared'] = __( 'Prepared', 'afb-offcanvas' );
			}
            if ( 'order_total' === $key ) {
				$new_columns['opigm_exports'] = __( 'Order Exports', 'afb-offcanvas' );
			}
		}
		if ( ! isset( $new_columns['opigm_exports'] ) ) {
			$new_columns['opigm_exports'] = __( 'Order Exports', 'afb-offcanvas' );
		}
        if ( ! isset( $new_columns['opigm_prepared'] ) ) {
            $new_columns['opigm_prepared'] = __( 'Prepared', 'afb-offcanvas' );
        }
		return $new_columns;
	}

	public function render_order_columns( $column, $post_id ) {
        if ( is_object( $post_id ) && method_exists( $post_id, 'get_id' ) ) {
            $order_id = $post_id->get_id();
            $order    = $post_id;
        } else {
            $order_id = $post_id;
            $order    = wc_get_order( $order_id );
        }

        if ( 'order_number' === $column ) {
            $is_prepared = $order ? ( $order->get_meta( '_opigm_is_prepared', true ) === 'yes' ) : false;
            ?>
            <div class="opigm-mobile-switch-container" style="display: none;">
                <label class="opigm-switch">
                    <input type="checkbox" class="opigm-toggle-prepared-switch" 
                           data-order-id="<?php echo esc_attr( $order_id ); ?>" 
                           <?php checked( $is_prepared ); ?>>
                    <span class="opigm-slider round"></span>
                </label>
                <span class="opigm-switch-label" style="margin-left: 8px; font-weight: 600; vertical-align: middle;"><?php _e('Prepared', 'afb-offcanvas'); ?></span>
            </div>
            <?php
        }

		if ( 'opigm_exports' === $column ) {
            $gift_url    = wp_nonce_url( admin_url( 'admin-ajax.php?action=opigm_print_gift_message&order_id=' . $order_id ), 'opigm_print_gift_message', 'nonce' );
            $invoice_url = wp_nonce_url( admin_url( 'admin-ajax.php?action=opigm_print_invoice&order_id=' . $order_id ), 'opigm_print_invoice', 'nonce' );
            $details_url = wp_nonce_url( admin_url( 'admin-ajax.php?action=opigm_print_order_details&order_id=' . $order_id ), 'opigm_print_order_details', 'nonce' );

			echo '<a href="#" class="button button-small opigm-pdf-action-btn" data-url="' . esc_url( $gift_url ) . '" data-type="gift" title="' . esc_attr__( 'Print Gift Message', 'afb-offcanvas' ) . '">';
			echo '<span class="dashicons dashicons-printer" style="margin-top: 3px;"></span> ' . esc_html__( 'Gift Message', 'afb-offcanvas' );
			echo '</a>';

            echo '<a href="#" class="button button-small opigm-pdf-action-btn" data-url="' . esc_url( $details_url ) . '" data-type="order_details" title="' . esc_attr__( 'Export Order Details', 'afb-offcanvas' ) . '" style="margin-top:2px;">';
			echo '<span class="dashicons dashicons-media-text" style="margin-top: 3px;"></span> ' . esc_html__( 'Order Details', 'afb-offcanvas' );
			echo '</a>';

            $print_all_url = wp_nonce_url( admin_url( 'admin-ajax.php?action=opigm_print_all_single&order_id=' . $order_id ), 'opigm_print_all_single', 'nonce' );
            echo '<a href="#" class="button button-small opigm-pdf-action-btn" data-url="' . esc_url( $print_all_url ) . '" data-type="print_all" title="' . esc_attr__( 'Print All', 'afb-offcanvas' ) . '" style="margin-top:2px;">';
			echo '<span class="dashicons dashicons-media-archive" style="margin-top: 3px;"></span> ' . esc_html__( 'Print All', 'afb-offcanvas' );
			echo '</a>';
		}

        if ( 'opigm_prepared' === $column ) {
            $is_prepared = $order ? ( $order->get_meta( '_opigm_is_prepared', true ) === 'yes' ) : false;
            ?>
            <div class="opigm-switch-wrapper">
                <label class="opigm-switch">
                    <input type="checkbox" class="opigm-toggle-prepared-switch" 
                           data-order-id="<?php echo esc_attr( $order_id ); ?>" 
                           <?php checked( $is_prepared ); ?>>
                    <span class="opigm-slider round"></span>
                </label>
            </div>
            <?php
        }
	}

	public function add_bulk_actions_download( $bulk_actions ) {
		$bulk_actions['opigm_download_gift_messages'] = __( 'Download Gift Message', 'afb-offcanvas' );
        $bulk_actions['opigm_download_order_details'] = __( 'Download Order Details', 'afb-offcanvas' );
		return $bulk_actions;
	}

    public function add_bulk_actions_print( $bulk_actions ) {
		$bulk_actions['opigm_print_gift_messages'] = __( 'Print Gift Message', 'afb-offcanvas' );
        $bulk_actions['opigm_print_order_details'] = __( 'Print Order Details', 'afb-offcanvas' );
		return $bulk_actions;
	}

    public function add_bulk_actions_prepared( $bulk_actions ) {
        $bulk_actions['opigm_mark_prepared'] = __( 'Mark order as Prepared', 'afb-offcanvas' );
        return $bulk_actions;
    }

    public function add_bulk_actions_extra( $bulk_actions ) {
        $bulk_actions['opigm_bulk_print_all'] = __( 'Export All (ZIP)', 'afb-offcanvas' );
        return $bulk_actions;
    }

    public function rename_bulk_actions( $actions ) {
        if ( isset( $actions['mark_processing'] ) ) {
            $actions['mark_processing'] = __( 'Change status to Paid', 'afb-offcanvas' );
        }
        if ( isset( $actions['mark_completed'] ) ) {
            $actions['mark_completed'] = __( 'Change status to Delivered', 'afb-offcanvas' );
        }
        if ( isset( $actions['mark_cancelled'] ) ) {
            $actions['mark_cancelled'] = __( 'Change status to Abandoned Cart', 'afb-offcanvas' );
        }
        return $actions;
    }

	public function handle_bulk_actions( $redirect_to, $action, $post_ids ) {
        if ( 'opigm_mark_prepared' === $action ) {
            foreach ( $post_ids as $post_id ) {
                $order = wc_get_order( $post_id );
                if ( $order ) {
                    $order->update_meta_data( '_opigm_is_prepared', 'yes' );
                    $order->save();
                }
            }
            return add_query_arg( 'opigm_prepared_count', count( $post_ids ), $redirect_to );
        }

        if ( 'opigm_bulk_print_all' === $action ) {
            $ids = implode( ',', $post_ids );
            return add_query_arg( [
                'action'    => 'opigm_bulk_print_all',
                'order_ids' => $ids,
                'nonce'     => wp_create_nonce( 'opigm_bulk_print_all' ),
            ], admin_url( 'admin-ajax.php' ) );
        }


		if ( ! in_array( $action, [ 'opigm_print_gift_messages', 'opigm_download_gift_messages', 'opigm_print_order_details', 'opigm_download_order_details' ] ) ) {
			return $redirect_to;
		}

        $is_details = in_array( $action, [ 'opigm_print_order_details', 'opigm_download_order_details' ] );
        $ajax_action = $is_details ? 'opigm_print_order_details' : 'opigm_print_gift_message';
        $nonce_action = $is_details ? 'opigm_print_order_details' : 'opigm_print_gift_message';

        $ids = implode( ',', $post_ids );
        
        $args = [
            'action'    => $ajax_action,
            'order_ids' => $ids,
            'nonce'     => wp_create_nonce( $nonce_action ),
        ];

        if ( strpos( $action, 'download' ) !== false ) {
            $args['download'] = '1';
        }

        return add_query_arg( $args, admin_url( 'admin-ajax.php' ) );
    }

    public function ajax_toggle_prepared() {
        check_ajax_referer( 'opigm_toggle_prepared', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'afb-offcanvas' ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            wp_send_json_error( __( 'Order not found', 'afb-offcanvas' ) );
        }

        $current_status = $order->get_meta( '_opigm_is_prepared', true );
        $new_status     = ( $current_status === 'yes' ) ? 'no' : 'yes';

        $order->update_meta_data( '_opigm_is_prepared', $new_status );
        $order->save();

        wp_send_json_success( [
            'status' => $new_status,
            'icon'   => ( $new_status === 'yes' ) ? 'dashicons-yes-alt' : 'dashicons-marker',
            'color'  => ( $new_status === 'yes' ) ? '#46b450' : '#ccc',
        ] );
    }

    public function ajax_print_all_single() {
        check_ajax_referer( 'opigm_print_all_single', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Unauthorized', 'afb-offcanvas' ) );
        }

        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wp_die( __( 'Order not found', 'afb-offcanvas' ) );
        }

        // 1. Invoice
        require_once OPIGM_PLUGIN_DIR . 'includes/invoice/class-invoice-generator.php';
        $inv_gen = new Opigm_Invoice_Generator();
        
        // 2. Order Details
        require_once OPIGM_PLUGIN_DIR . 'includes/class-order-details-generator.php';
        $details_gen = new Opigm_Order_Details_Generator();

        // 3. Gift Message
        require_once OPIGM_PLUGIN_DIR . 'includes/class-pdf-generator.php';
        $gift_gen = new Opigm_PDF_Generator();

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <style>
                @page { margin: 0; }
                body {
                    font-family: 'DejaVu Sans', sans-serif;
                    margin: 0;
                    padding: 0;
                }
                .page-break { page-break-after: always; }
                
                /* --- INVOICE STYLES Scoped to #invoice-view --- */
                #invoice-view {
                    font-size: 11px;
                    line-height: 1.4;
                    color: #000;
                }
                #invoice-view table {
                    width: 100%;
                    border-collapse: collapse;
                    border-spacing: 0;
                }
                #invoice-view .header-table td { vertical-align: top; }
                #invoice-view .logo {
                    font-size: 30px;
                    font-weight: bold;
                    text-transform: uppercase;
                    letter-spacing: 2px;
                    line-height: 1;
                }
                #invoice-view .logo-subtext {
                    font-size: 10px;
                    font-weight: normal;
                    letter-spacing: 1px;
                    color: #444;
                    margin-top: 5px;
                    line-height: 1.2;
                }
                #invoice-view .invoice-details {
                    text-align: right;
                    color: #888;
                }
                #invoice-view .invoice-details strong {
                    font-size: 16px;
                    color: #444; 
                    text-transform: uppercase;
                    display: block;
                    margin-bottom: 2px;
                }
                #invoice-view .invoice-details span {
                    display: block;
                    font-size: 12px;
                }
                
                #invoice-view .addresses-table { margin-top: 30px; width: 100%; }
                #invoice-view .addresses-table td { vertical-align: top; width: 48%; }
                
                #invoice-view .address-title {
                    font-weight: bold;
                    margin-bottom: 8px;
                    font-size: 12px;
                    text-decoration: underline;
                }
                
                #invoice-view .order-pickup-location h4 { margin: 0 0 4px; font-size: 12px; }
                #invoice-view .order-pickup-location p { margin: 0; }

                #invoice-view .summary-strip {
                    margin-top: 30px;
                    background-color: #f9f9f9;
                    border: 1px solid #eee;
                }
                #invoice-view .summary-strip td {
                    padding: 8px 10px;
                    text-align: center;
                    font-size: 11px;
                }
                #invoice-view .summary-strip th {
                    padding: 8px 10px;
                    font-weight: bold;
                    text-align: center;
                    font-size: 11px;
                    background-color: #eee;
                    border-bottom: 1px solid #ddd; 
                }
                
                #invoice-view .items-table { margin-top: 25px; border: 1px solid #000; width: 100%; table-layout: fixed; }
                #invoice-view .items-table th {
                    background-color: #f0f0f0;
                    padding: 8px;
                    text-align: left;
                    font-weight: bold;
                    border-bottom: 1px solid #000;
                    font-size: 11px;
                }
                #invoice-view .items-table th.right { text-align: right; }
                #invoice-view .items-table th.center { text-align: center; }
                
                #invoice-view .items-table td {
                    padding: 8px;
                    border-bottom: 1px solid #ddd;
                    vertical-align: middle;
                    word-wrap: break-word;
                }
                #invoice-view .items-table td.right { text-align: right; }
                #invoice-view .items-table td.center { text-align: center; }
                
                #invoice-view .totals-area { margin-top: 20px; width: 100%; border-collapse: separate; border-spacing: 0; }
                #invoice-view .totals-area td { vertical-align: top; }
                
                #invoice-view .tax-table {
                    width: 100%;
                    border: 1px solid #ddd;
                    margin-bottom: 20px;
                }
                #invoice-view .tax-table th {
                    background-color: #f0f0f0;
                    padding: 5px;
                    font-size: 10px;
                    text-align: right;
                    border-bottom: 1px solid #ddd;
                }
                #invoice-view .tax-table th:first-child { text-align: left; }
                #invoice-view .tax-table td {
                    padding: 5px;
                    text-align: right;
                    font-size: 10px;
                    border-bottom: 1px solid #eee;
                }
                #invoice-view .tax-table td:first-child { text-align: left; }
                
                #invoice-view .grand-totals-table {
                    width: 100%;
                    border: 1px solid #000;
                }
                #invoice-view .grand-totals-table td {
                    padding: 6px 10px;
                    text-align: right;
                    border-bottom: 1px solid #000;
                }
                #invoice-view .grand-totals-table .label { 
                    text-align: right; 
                    background-color: #f5f5f5; 
                    font-weight: bold;
                    border-right: 1px solid #ddd;
                    width: 40%;
                }
                #invoice-view .grand-totals-table .amount { font-weight: bold; }
                
                #invoice-view .grand-total-row td {
                    border-top: 1px solid #000;
                    font-weight: bold;
                    font-size: 13px;
                    background-color: #fff;
                }
                
                #invoice-view .footer-table { width: 100%; border: 1px solid #ddd; font-size: 10px; }
                #invoice-view .footer-table td { padding: 5px; border-bottom: 1px solid #eee; }
                #invoice-view .footer-label { font-weight: bold; background-color: #f5f5f5; width: 30%; border-right: 1px solid #ddd; }


                /* --- ORDER DETAILS STYLES Scoped to #details-view --- */
                #details-view {
                    font-size: 12px;
                    line-height: 1.5;
                    color: #333;
                }
                #details-view .header {
                    text-align: center;
                    margin-bottom: 40px;
                }
                #details-view .logo-img {
                    max-height: 50px;
                    margin-bottom: 10px;
                }
                #details-view .logo-text {
                    font-size: 30px;
                    font-weight: bold;
                    text-transform: uppercase;
                    letter-spacing: 2px;
                    line-height: 1;
                }
                #details-view .logo-subtext {
                    font-size: 10px;
                    font-weight: normal;
                    letter-spacing: 1px;
                    color: #444;
                    margin-top: 5px;
                    line-height: 1.2;
                }
                #details-view .order-info {
                    margin-bottom: 30px;
                }
                #details-view .order-info h2 {
                    font-size: 18px;
                    margin: 0 0 10px;
                    border-bottom: 2px solid #eee;
                    padding-bottom: 10px;
                }
                #details-view .addresses {
                    width: 100%;
                    margin-bottom: 30px;
                    border-collapse: collapse;
                }
                #details-view .addresses td {
                    vertical-align: top;
                    width: 50%;
                }
                #details-view .address-box h3 {
                    font-size: 14px;
                    margin: 0 0 5px;
                    color: #777;
                    text-transform: uppercase;
                }
                #details-view .items-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 30px;
                }
                #details-view .items-table th {
                    text-align: left;
                    padding: 10px;
                    background: #f9f9f9;
                    border-bottom: 2px solid #eee;
                }
                #details-view .totals-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                #details-view .totals-table td {
                    padding: 5px 10px;
                    text-align: right;
                }
                #details-view .totals-table .label {
                    color: #777;
                    width: 70%;
                }
                #details-view .totals-table .amount {
                    font-weight: bold;
                    width: 30%;
                }
                #details-view .total-row td {
                    border-top: 2px solid #eee;
                    padding-top: 10px;
                    font-size: 16px; 
                    color: #000;
                }
                #details-view .footer {
                    margin-top: 50px;
                    text-align: center;
                    font-size: 10px;
                    color: #aaa;
                    border-top: 1px solid #eee;
                    padding-top: 10px;
                }


                /* --- GIFT MESSAGE STYLES Scoped to #gift-view --- */
                #gift-view {
                    background: #ffffff; 
                    color: #000;
                }
                #gift-view .page-container {
                    width: 100%;
                    padding-top: 30px;  
                    box-sizing: border-box;
                    max-width: 85%;
                    margin: 0 auto;
                }
                #gift-view .logo {
                    margin-top: 20px;
                    margin-bottom: 40px;
                    text-align: center;
                    width: 100%;
                }
                #gift-view .logo-text {
                    font-size: 30px;
                    font-weight: bold;
                    text-transform: uppercase;
                    letter-spacing: 2px;
                    margin-bottom: 5px;
                    color: #000;
                }
                #gift-view .logo-subtext {
                    font-size: 10px;
                    font-weight: normal;
                    letter-spacing: 1px;
                    color: #444;
                    margin-top: 5px;
                    line-height: 1.2;
                    text-transform: none;
                }
                #gift-view .message-body {
                    font-size: 17px;
                    line-height: 1.6;
                    margin: 30px 0;
                    width: 100%;
                    box-sizing: border-box;
                    text-align: center; 
                    font-style: italic;
                    font-weight: normal; 
                    color: #222;
                    min-height: 150px;
                }
                #gift-view .signature {
                    margin-top: 40px;
                    text-align: left;
                    font-size: 14px;
                    color: #000;
                    line-height: 1.5;
                    font-weight: normal;
                }
                #gift-view .signature strong {
                    font-weight: normal;
                    font-size: 14px;
                    display: block;
                    margin-bottom: 2px;
                }
                #gift-view .quote { 
                    font-style: italic;
                    font-weight: normal; 
                    font-size: 14px;
                }
                #gift-view .website-line {
                    margin-top: 15px;
                    font-size: 12px;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    font-weight: normal;
                }
            </style>
        </head>
        <body>
        <?php
        $html = ob_get_clean();

        // 1. Invoice Content
        if ( Opigm_Utils::is_order_invoiceable( $order ) ) {
            $html .= '<div id="invoice-view" style="padding: 40px;">';
            $html .= $inv_gen->get_body_html( $order );
            $html .= '</div>';
            $html .= '<div class="page-break"></div>';
        }

        // 2. Order Details Content
        $html .= '<div id="details-view" style="padding: 40px;">';
        $html .= $details_gen->get_body_html( $order );
        $html .= '</div>';
        
        // 3. Gift Message Content
        $html .= '<div class="page-break"></div>';
        $html .= '<div id="gift-view">';
        // The get_body_html for gift generator already includes page-container, but we wrap it for CSS scope
        $html .= $gift_gen->get_body_html( $order_id );
        $html .= '</div>';

        $html .= '</body></html>';

        $options = new \Dompdf\Options();
        $options->set( 'defaultFont', 'DejaVu Sans' );
        $options->set( 'isRemoteEnabled', true );
        $options->set( 'isHtml5ParserEnabled', true );

        $dompdf = new \Dompdf\Dompdf( $options );
        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();

        $filename = 'Order-' . $order->get_order_number() . '-All-In-One.pdf';
        $dompdf->stream( $filename, [ 'Attachment' => false ] );
        exit;
    }

    public function add_prepared_views( $views ) {
        $prepared_count = $this->get_prepared_count( 'yes' );
        $not_prepared_count = $this->get_prepared_count( 'no' );
        
        $current = isset( $_GET['opigm_prepared_filter'] ) ? sanitize_text_field( $_GET['opigm_prepared_filter'] ) : '';
        
        $base_url = admin_url( 'edit.php?post_type=shop_order' );
        $screen = get_current_screen();
        if ( $screen && 'woocommerce_page_wc-orders' === $screen->id ) {
            $base_url = admin_url( 'admin.php?page=wc-orders' );
        }

        $views['opigm_prepared'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            esc_url( add_query_arg( 'opigm_prepared_filter', 'prepared', $base_url ) ),
            ( 'prepared' === $current ) ? 'current' : '',
            __( 'Prepared', 'afb-offcanvas' ),
            $prepared_count
        );

        $views['opigm_not_prepared'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            esc_url( add_query_arg( 'opigm_prepared_filter', 'not_prepared', $base_url ) ),
            ( 'not_prepared' === $current ) ? 'current' : '',
            __( 'Not Prepared', 'afb-offcanvas' ),
            $not_prepared_count
        );

        return $views;
    }

    private function get_prepared_count( $status = 'yes' ) {
        $args = [
            'status'            => [ 'processing', 'completed' ],
            'type'              => 'shop_order', // Exclude refunds to match admin list
            'limit'             => -1,
            'return'            => 'ids',
            'opigm_skip_filter' => true,
        ];

        if ( 'yes' === $status ) {
            $args['meta_query'] = [
                [
                    'key'     => '_opigm_is_prepared',
                    'value'   => 'yes',
                    'compare' => '='
                ]
            ];
        } else {
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => '_opigm_is_prepared',
                    'value'   => 'yes',
                    'compare' => '!='
                ],
                [
                    'key'     => '_opigm_is_prepared',
                    'compare' => 'NOT EXISTS'
                ]
            ];
        }

        $orders = wc_get_orders( $args );
        return count( $orders );
    }

    public function handle_prepared_pre_get_posts( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        global $typenow;
        $post_type = $query->get( 'post_type' );
        
        // Fallback to global typenow if query post_type is not set
        if ( empty( $post_type ) ) {
            $post_type = $typenow;
        }

        if ( 'shop_order' !== $post_type ) {
            return;
        }

        if ( isset( $_GET['opigm_prepared_filter'] ) && ! empty( $_GET['opigm_prepared_filter'] ) ) {
            // Check opigm_skip_filter var - though unlikely in main query
            if ( $query->get( 'opigm_skip_filter' ) ) {
                return;
            }

            $filter = sanitize_text_field( $_GET['opigm_prepared_filter'] );
            
            $query->set( 'post_status', [ 'wc-processing', 'wc-completed' ] );

            $meta_query = $query->get( 'meta_query' );
            if ( ! is_array( $meta_query ) ) {
                $meta_query = [];
            }

            if ( 'prepared' === $filter ) {
                $meta_query[] = [
                    'key'   => '_opigm_is_prepared',
                    'value' => 'yes'
                ];
            } elseif ( 'not_prepared' === $filter ) {
                $meta_query[] = [
                    'relation' => 'OR',
                    [
                        'key'     => '_opigm_is_prepared',
                        'value'   => 'yes',
                        'compare' => '!='
                    ],
                    [
                        'key'     => '_opigm_is_prepared',
                        'compare' => 'NOT EXISTS'
                    ]
                ];
            }
            
            $query->set( 'meta_query', $meta_query );
        }
    }



    public function rename_order_statuses( $statuses ) {
        if ( isset( $statuses['wc-processing'] ) ) {
            $statuses['wc-processing'] = _x( 'Paid', 'Order status', 'afb-offcanvas' );
        }
        if ( isset( $statuses['wc-completed'] ) ) {
            $statuses['wc-completed'] = _x( 'Delivered', 'Order status', 'afb-offcanvas' );
        }
        if ( isset( $statuses['wc-cancelled'] ) ) {
            $statuses['wc-cancelled'] = _x( 'Abandoned Cart', 'Order status', 'afb-offcanvas' );
        }
        return $statuses;
    }

    public function rename_post_statuses( $post_statuses ) {
        if ( isset( $post_statuses['wc-processing'] ) ) {
            $post_statuses['wc-processing']['label'] = _x( 'Paid', 'Order status', 'afb-offcanvas' );
        }
        if ( isset( $post_statuses['wc-completed'] ) ) {
            $post_statuses['wc-completed']['label'] = _x( 'Delivered', 'Order status', 'afb-offcanvas' );
        }
        if ( isset( $post_statuses['wc-cancelled'] ) ) {
            $post_statuses['wc-cancelled']['label'] = _x( 'Abandoned Cart', 'Order status', 'afb-offcanvas' );
        }
        return $post_statuses;
    }

    public function rename_report_labels( $statuses ) {
        if ( isset( $statuses['processing'] ) ) {
            $statuses['processing'] = __( 'Paid', 'afb-offcanvas' );
        }
        if ( isset( $statuses['completed'] ) ) {
            $statuses['completed'] = __( 'Delivered', 'afb-offcanvas' );
        }
        if ( isset( $statuses['cancelled'] ) ) {
            $statuses['cancelled'] = __( 'Abandoned Cart', 'afb-offcanvas' );
        }
        return $statuses;
    }

    public function admin_footer_scripts() {
        ?>
        <style>
        /* Switch styling */
        .opigm-switch {
          position: relative;
          display: inline-block;
          width: 40px;
          height: 20px;
        }
        .opigm-switch input {
          opacity: 0;
          width: 0;
          height: 0;
        }
        .opigm-slider {
          position: absolute;
          cursor: pointer;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background-color: #ccc;
          -webkit-transition: .4s;
          transition: .4s;
        }
        .opigm-slider:before {
          position: absolute;
          content: "";
          height: 14px;
          width: 14px;
          left: 3px;
          bottom: 3px;
          background-color: white;
          -webkit-transition: .4s;
          transition: .4s;
        }
        input:checked + .opigm-slider {
          background-color: #46b450;
        }
        input:focus + .opigm-slider {
          box-shadow: 0 0 1px #46b450;
        }
        input:checked + .opigm-slider:before {
          -webkit-transform: translateX(20px);
          -ms-transform: translateX(20px);
          transform: translateX(20px);
        }
        .opigm-slider.round {
          border-radius: 20px;
        }
        .opigm-slider.round:before {
          border-radius: 50%;
        }
        
        /* Green styling for mobile switch label */
        .opigm-switch-label {
            color: #46b450;
        }

        /* Mobile visibility for Prepared column */
        @media screen and (max-width: 782px) {
            .wp-list-table tr:not(.inline-edit-row):not(.no-items) td.column-opigm_prepared {
                display: none !important;
            }
            .opigm-mobile-switch-container {
                display: flex !important;
                align-items: center;
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid #f0f0f1;
            }
        }

        /* Order Status Badge Styling */
        .wp-list-table .column-order_status mark.processing::after,
        .wp-list-table .column-order_status mark.completed::after,
        .wp-list-table .column-order_status mark.cancelled::after,
        .wp-list-table .column-order_status mark.failed::after {
            content: none !important;
        }

        .wp-list-table .column-order_status mark.processing { 
            background-color: #46b450 !important; 
            color: #fff !important;
            padding: 5px 8px;
            border-radius: 4px;
            font-weight: 600;
        }
        .wp-list-table .column-order_status mark.completed { 
            background-color: #0073aa !important; 
            color: #fff !important;
            padding: 5px 8px;
            border-radius: 4px;
            font-weight: 600;
        }
        .wp-list-table .column-order_status mark.cancelled { 
            background-color: #999 !important; 
            color: #fff !important;
            padding: 5px 8px;
            border-radius: 4px;
            font-weight: 600;
        }
        .wp-list-table .column-order_status mark.failed { 
            background-color: #d63638 !important; 
            color: #fff !important;
            padding: 5px 8px;
            border-radius: 4px;
            font-weight: 600;
        }
        </style>
        <script>
        jQuery(document).ready(function($) {
            // Bulk Actions: Open Print in New Tab
            $('#doaction, #doaction2').on('click', function(e) {
                var selectId = $(this).attr('id').replace('doaction', 'bulk-action-selector-top');
                if ( $(this).attr('id') === 'doaction2' ) {
                    selectId = 'bulk-action-selector-bottom';
                }
                
                var action = $('#' + selectId).val();
                var printActions = ['opigm_print_gift_messages', 'opigm_print_order_details'];

                if ( printActions.indexOf(action) !== -1 ) {
                    $(this).closest('form').attr('target', '_blank');
                    
                    // Optional: remove target after a delay to restore normal behavior for other actions
                    setTimeout(function() {
                        $('#posts-filter').removeAttr('target');
                    }, 1000);
                } else {
                    $(this).closest('form').removeAttr('target');
                }
            });

            // Stop propagation on switch click to prevent opening the order
            $(document).on('click', '.opigm-switch', function(e) {
                e.stopPropagation();
            });

            $(document).on('change', '.opigm-toggle-prepared-switch', function(e) {
                var checkbox = $(this);
                var order_id = checkbox.data('order-id');
                var wrapper = checkbox.closest('.opigm-switch');
                
                wrapper.css('opacity', '0.5');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'opigm_toggle_prepared',
                        order_id: order_id,
                        nonce: '<?php echo wp_create_nonce( "opigm_toggle_prepared" ); ?>'
                    },
                    success: function(response) {
                        if (!response.success) {
                            alert('Error updating status');
                            checkbox.prop('checked', !checkbox.prop('checked'));
                        }
                        wrapper.css('opacity', '1');
                    },
                    error: function() {
                        alert('Request failed');
                        checkbox.prop('checked', !checkbox.prop('checked'));
                        wrapper.css('opacity', '1');
                    }
                });
            });
            
            $(document).on('click', '.opigm-pdf-action-btn', function(e) {
                e.preventDefault();
                var btn = $(this);
                var baseUrl = btn.data('url');
                var type = btn.data('type');
                
                // Print All single order doesn't need choice confirmation usually if it's "Print All" 
                // but let's see if we should allow download too.
                if (type === 'print_all') {
                    window.open(baseUrl, '_blank');
                    return;
                }

                var choice = confirm('Choose action:\nOK = Download PDF\nCancel = Print (Open in new tab)');
                
                if (choice) {
                    // Download
                    window.location.href = baseUrl + '&download=1';
                } else {
                    // Print (open in new tab)
                    window.open(baseUrl, '_blank');
                }
            });
            
            $(document).on('click', '.opigm-print-all-single', function(e) {
                e.preventDefault();
                var btn = $(this);
                
                // URLs from data attributes
                var giftMessageUrl = btn.data('gift-url');
                var invoiceUrl = btn.data('invoice-url');
                var order_id = btn.data('order-id');
                var details_url = btn.data('details-url');
                
                if (giftMessageUrl) {
                    window.open(giftMessageUrl, '_blank');
                }
                
                setTimeout(function() {
                    if (invoiceUrl) {
                        window.open(invoiceUrl, '_blank');
                    }
                }, 200);
                
                setTimeout(function() {
                   if (details_url) {
                       window.open(details_url, '_blank');
                   }
                }, 400);
            });
        });
        
        </script>
        <?php
    }
}
