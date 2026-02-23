<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Opigm_Invoice_Integration {

    public function __construct() {
        add_action( 'wp_ajax_opigm_print_invoice', [ $this, 'generate_invoice_pdf' ] );
        add_action( 'wp_ajax_nopriv_opigm_print_invoice', [ $this, 'generate_invoice_pdf' ] );

        add_action( 'opigm_meta_box_actions', [ $this, 'render_meta_box_content' ] );
        
        add_filter( 'manage_edit-shop_order_columns', [ $this, 'add_column_header' ], 20 );
        add_action( 'manage_shop_order_posts_custom_column', [ $this, 'render_column_content' ], 20, 2 );
        add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'add_column_header' ], 20 );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'render_column_content' ], 20, 2 );

        add_filter( 'bulk_actions-edit-shop_order', [ $this, 'add_bulk_actions_download' ], 11 );
        add_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this, 'add_bulk_actions_download' ], 11 );
        
        add_filter( 'bulk_actions-edit-shop_order', [ $this, 'add_bulk_actions_print' ], 21 );
        add_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this, 'add_bulk_actions_print' ], 21 );
        add_filter( 'handle_bulk_actions-edit-shop_order', [ $this, 'handle_bulk_action' ], 10, 3 );
        add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', [ $this, 'handle_bulk_action' ], 10, 3 );

        add_filter( 'woocommerce_my_account_my_orders_actions', [ $this, 'add_my_account_action' ], 10, 2 );
        add_action( 'woocommerce_view_order', [ $this, 'add_view_order_button' ], 10 );

        add_action( 'woocommerce_thankyou', [ $this, 'add_thankyou_button' ], 10 );
    }

    public function generate_invoice_pdf() {
        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'opigm_print_invoice' ) ) {
        }
        
        
        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        $order_ids = isset( $_GET['order_ids'] ) ? array_map( 'absint', explode( ',', $_GET['order_ids'] ) ) : [];

        if ( $order_id ) $order_ids[] = $order_id;
        $order_ids = array_unique( $order_ids );

        if ( empty( $order_ids ) ) wp_die( 'No order specified' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            if ( count( $order_ids ) > 1 ) wp_die( 'Unauthorized bulk download' );
            
            $order = wc_get_order( $order_ids[0] );
            if ( ! $order || ! current_user_can( 'view_order', $order_ids[0] ) ) {
                $order_key = isset( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : '';
                if ( ! $order || ! $order->key_is_valid( $order_key ) ) {
                     wp_die( 'Unauthorized access' );
                }
            }
        }

        // Filter invoiceable orders for bulk operations
        if ( count( $order_ids ) > 1 ) {
            $filtered_order_ids = [];
            foreach ( $order_ids as $oid ) {
                $order = wc_get_order( $oid );
                if ( Opigm_Utils::is_order_invoiceable( $order ) ) {
                    $filtered_order_ids[] = $oid;
                }
            }
            
            if ( empty( $filtered_order_ids ) ) {
                wp_die( 'No orders are eligible for invoice generation. Only Processing and Completed orders can have invoices.' );
            }
            
            $order_ids = $filtered_order_ids;
        }

        require_once dirname( __FILE__ ) . '/class-invoice-generator.php';
        $generator = new Opigm_Invoice_Generator();

        $download = isset( $_GET['download'] ) && $_GET['download'] == '1';

        if ( count( $order_ids ) === 1 ) {
            $generator->stream_pdf( $order_ids[0] );
        } else {
            if ( $download ) {
                $zip_file = tempnam( sys_get_temp_dir(), 'opigm_inv_zip' );
                $zip = new ZipArchive();
                if ( $zip->open( $zip_file, ZipArchive::CREATE ) !== TRUE ) {
                    wp_die( 'Cannot create zip' );
                }

                foreach ( $order_ids as $oid ) {
                    $html = $generator->generate_pdf( $oid );
                    if ( $html ) {
                        $zip->addFromString( 'invoice-' . $oid . '.pdf', $html );
                    }
                }
                $zip->close();
                
                $date = date( 'Y-m-d-H-i-s' );
                $filename = 'Download Order Invoice - ' . $date . '.zip';
                
                header( 'Content-Type: application/zip' );
                header( 'Content-disposition: attachment; filename=' . $filename );
                header( 'Content-Length: ' . filesize( $zip_file ) );
                readfile( $zip_file );
                unlink( $zip_file );
                exit;
            } else {
                // Merge into single PDF for Print
                $html = $generator->get_header_html();
                $count = count( $order_ids );
                foreach ( $order_ids as $index => $oid ) {
                    $order = wc_get_order( $oid );
                    if ( $order ) {
                        $html .= $generator->get_body_html( $order );
                        if ( $index < $count - 1 ) {
                            $html .= '<div class="page-break"></div>';
                        }
                    }
                }
                $html .= $generator->get_footer_html();

                $date = date( 'Y-m-d-H-i-s' );
                $filename = 'Print Order Invoices - ' . $date . '.pdf';

                $options = new \Dompdf\Options();
                $options->set( 'defaultFont', 'DejaVu Sans' );
                $options->set( 'isRemoteEnabled', true );
                $options->set( 'isHtml5ParserEnabled', true );

                $dompdf = new \Dompdf\Dompdf( $options );
                $dompdf->loadHtml( $html );
                $dompdf->setPaper( 'A4', 'portrait' );
                $dompdf->render();

                $dompdf->stream( $filename, [ 'Attachment' => false ] );
                exit;
            }
        }
    }

    public function add_column_header( $columns ) {
        return $columns;
    }

    public function render_column_content( $column, $post_id ) {
        if ( 'opigm_exports' === $column ) {
             if ( is_object( $post_id ) ) $post_id = $post_id->get_id();
             
             $order = wc_get_order( $post_id );
             
             // Only show invoice button for invoiceable orders
             if ( $order && Opigm_Utils::is_order_invoiceable( $order ) ) {
                 $url = wp_nonce_url( admin_url( 'admin-ajax.php?action=opigm_print_invoice&order_id=' . $post_id ), 'opigm_print_invoice', 'nonce' );
                 echo '<a href="#" class="button button-small opigm-pdf-action-btn" data-url="' . esc_url( $url ) . '" data-type="invoice" title="' . esc_attr__( 'Print Invoice', 'afb-offcanvas' ) . '" style="margin-top:5px;">';
                 echo '<span class="dashicons dashicons-media-document" style="margin-top: 3px;"></span> ' . esc_html__( 'Order Invoice', 'afb-offcanvas' );
                 echo '</a>';
             }
        }
    }

    public function add_bulk_actions_download( $actions ) {
        $actions['opigm_download_invoices'] = __( 'Download Invoice', 'afb-offcanvas' );
        return $actions;
    }

    public function add_bulk_actions_print( $actions ) {
        $actions['opigm_print_invoices'] = __( 'Print Invoice', 'afb-offcanvas' );
        return $actions;
    }

    public function handle_bulk_action( $redirect_to, $action, $post_ids ) {
        if ( ! in_array( $action, [ 'opigm_print_invoices', 'opigm_download_invoices' ] ) ) return $redirect_to;

        $ids = implode( ',', $post_ids );
        $args = [
            'action' => 'opigm_print_invoice',
            'order_ids' => $ids,
            'nonce' => wp_create_nonce( 'opigm_print_invoice' )
        ];

        if ( 'opigm_download_invoices' === $action ) {
            $args['download'] = '1';
        }

        return add_query_arg( $args, admin_url( 'admin-ajax.php' ) );
    }

    public function render_meta_box_content( $order ) {
        if ( ! Opigm_Utils::is_order_invoiceable( $order ) ) {
            return;
        }

        $order_id = $order->get_id();
        $url = wp_nonce_url( admin_url( 'admin-ajax.php?action=opigm_print_invoice&order_id=' . $order_id ), 'opigm_print_invoice', 'nonce' );
        ?>
        <a href="#" class="button button-secondary opigm-pdf-action-btn" data-url="<?php echo esc_url( $url ); ?>" data-type="invoice" style="width: 100%; text-align: center; margin-bottom: 5px; margin-top:5px;">
            <?php esc_html_e( 'Download Invoice PDF', 'afb-offcanvas' ); ?>
        </a>
        <?php
    }
    
    public function add_my_account_action( $actions, $order ) {
        // Only show invoice action for invoiceable orders
        if ( Opigm_Utils::is_order_invoiceable( $order ) ) {
            $actions['print_invoice'] = [
                'url'  => wp_nonce_url( admin_url( 'admin-ajax.php?action=opigm_print_invoice&order_id=' . $order->get_id() . '&key=' . $order->get_order_key() ), 'opigm_print_invoice', 'nonce' ),
                'name' => __( 'Download Invoice', 'afb-offcanvas' )
            ];
        }
        return $actions;
    }
    
    public function add_view_order_button( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        
        // Only show invoice button for invoiceable orders
        if ( ! Opigm_Utils::is_order_invoiceable( $order ) ) {
            return;
        }
        
        $url = wp_nonce_url( admin_url( 'admin-ajax.php?action=opigm_print_invoice&order_id=' . $order_id . '&key=' . $order->get_order_key() ), 'opigm_print_invoice', 'nonce' );
        
        echo '<p class="order-invoice-btn-para">';
        echo '<a href="' . esc_url( $url ) . '" class="button button-primary opigm-print-invoice" target="_blank">';
        echo esc_html__( 'Download Invoice PDF', 'afb-offcanvas' );
        echo '</a>';
        echo '</p>';
    }

    public function add_thankyou_button( $order_id ) {
        if ( ! $order_id ) return;
        $order = wc_get_order( $order_id );
        
        // Only show invoice button for invoiceable orders
        if ( ! Opigm_Utils::is_order_invoiceable( $order ) ) {
            return;
        }
        
        $url = wp_nonce_url( admin_url( 'admin-ajax.php?action=opigm_print_invoice&order_id=' . $order_id . '&key=' . $order->get_order_key() ), 'opigm_print_invoice', 'nonce' );
        
        echo '<div class="opigm-thankyou-invoice" style="margin: 20px 0;">';
        echo '<a href="' . esc_url( $url ) . '" class="button button-primary button-large opigm-print-invoice" target="_blank" style="padding: 10px 20px; font-size: 16px;">';
        echo esc_html__( 'Download Invoice PDF', 'afb-offcanvas' );
        echo '</a>';
        echo '</div>';
    }

}
