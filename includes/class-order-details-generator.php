<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Dompdf\Dompdf;
use Dompdf\Options;

class Opigm_Order_Details_Generator {
    
    public function __construct() {
        add_action( 'wp_ajax_opigm_print_order_details', [ $this, 'generate_ajax_pdf' ] );
    }

    public function generate_ajax_pdf() {
        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'opigm_print_order_details' ) ) {
            wp_die( __( 'Invalid nonce', 'afb-offcanvas' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Unauthorized access', 'afb-offcanvas' ) );
        }

        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        $order_ids = isset( $_GET['order_ids'] ) ? array_map( 'absint', explode( ',', $_GET['order_ids'] ) ) : [];

        if ( $order_id ) $order_ids[] = $order_id;
        $order_ids = array_unique( $order_ids );

        if ( empty( $order_ids ) ) {
            wp_die( __( 'No order selected', 'afb-offcanvas' ) );
        }

        $download = isset( $_GET['download'] ) && $_GET['download'] == '1';

        if ( count( $order_ids ) === 1 ) {
            $order = wc_get_order( $order_ids[0] );
            if ( ! $order ) {
                wp_die( __( 'Order not found', 'afb-offcanvas' ) );
            }

            $html = $this->get_html( $order );
            
            $options = new Options();
            $options->set( 'defaultFont', 'DejaVu Sans' );
            $options->set( 'isRemoteEnabled', true );
            $options->set( 'isHtml5ParserEnabled', true );

            $dompdf = new Dompdf( $options );
            $dompdf->loadHtml( $html );
            $dompdf->setPaper( 'A4', 'portrait' );
            $dompdf->render();

            $filename = 'order-details-' . $order->get_order_number() . '.pdf';
            $dompdf->stream( $filename, [ 'Attachment' => $download ] );
            exit;
        } else {
            if ( $download ) {
                $zip_file = tempnam( sys_get_temp_dir(), 'opigm_details_zip' );
                $zip = new ZipArchive();
                if ( $zip->open( $zip_file, ZipArchive::CREATE ) !== TRUE ) {
                    wp_die( 'Cannot create zip' );
                }

                foreach ( $order_ids as $oid ) {
                    $html_str = $this->generate_pdf_string( $oid );
                    if ( $html_str ) {
                        $zip->addFromString( 'order-details-' . $oid . '.pdf', $html_str );
                    }
                }
                $zip->close();
                
                $date = date( 'Y-m-d-H-i-s' );
                $filename = 'Download Order Details - ' . $date . '.zip';
                
                header( 'Content-Type: application/zip' );
                header( 'Content-disposition: attachment; filename=' . $filename );
                header( 'Content-Length: ' . filesize( $zip_file ) );
                readfile( $zip_file );
                unlink( $zip_file );
                exit;
            } else {
                // Merge for Print
                $html = $this->get_header_html();
                $count = count( $order_ids );
                foreach ( $order_ids as $index => $oid ) {
                    $order = wc_get_order( $oid );
                    if ( $order ) {
                        $html .= $this->get_body_html( $order );
                        if ( $index < $count - 1 ) {
                            $html .= '<div class="page-break"></div>';
                        }
                    }
                }
                $html .= $this->get_footer_html();

                $date = date( 'Y-m-d-H-i-s' );
                $filename = 'Print Order Details - ' . $date . '.pdf';

                $options = new Options();
                $options->set( 'defaultFont', 'DejaVu Sans' );
                $options->set( 'isRemoteEnabled', true );
                $options->set( 'isHtml5ParserEnabled', true );

                $dompdf = new Dompdf( $options );
                $dompdf->loadHtml( $html );
                $dompdf->setPaper( 'A4', 'portrait' );
                $dompdf->render();

                $dompdf->stream( $filename, [ 'Attachment' => false ] );
                exit;
            }
        }
    }

    public function generate_pdf_string( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return false;
        }

        $html = $this->get_html( $order );
        
        $options = new Options();
        $options->set( 'defaultFont', 'DejaVu Sans' ); // Consistent font for Hebrew support
        $options->set( 'isRemoteEnabled', true );
        $options->set( 'isHtml5ParserEnabled', true );

        $dompdf = new Dompdf( $options );
        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();

        return $dompdf->output();
    }

    public function get_html( $order ) {
        return $this->get_header_html() . $this->get_body_html( $order ) . $this->get_footer_html();
    }

    public function get_header_html() {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <style>
                @page { margin: 40px; }
                body {
                    font-family: 'DejaVu Sans', sans-serif;
                    font-size: 12px;
                    line-height: 1.5;
                    color: #333;
                }
                .header {
                    text-align: center;
                    margin-bottom: 40px;
                }
                .logo-img {
                    max-height: 50px;
                    margin-bottom: 10px;
                }
                .logo-text {
                    font-size: 30px;
                    font-weight: bold;
                    text-transform: uppercase;
                    letter-spacing: 2px;
                    line-height: 1;
                }
                .logo-subtext {
                    font-size: 10px;
                    font-weight: normal;
                    letter-spacing: 1px;
                    color: #444;
                    margin-top: 5px;
                    line-height: 1.2;
                }
                .order-info {
                    margin-bottom: 30px;
                }
                .order-info h2 {
                    font-size: 18px;
                    margin: 0 0 10px;
                    border-bottom: 2px solid #eee;
                    padding-bottom: 10px;
                }
                .addresses {
                    width: 100%;
                    margin-bottom: 30px;
                }
                .addresses td {
                    vertical-align: top;
                    width: 50%;
                }
                .address-box h3 {
                    font-size: 14px;
                    margin: 0 0 5px;
                    color: #777;
                    text-transform: uppercase;
                }
                .items-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 30px;
                }
                .items-table th {
                    text-align: left;
                    padding: 10px;
                    background: #f9f9f9;
                    border-bottom: 2px solid #eee;
                }
                .totals-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .totals-table td {
                    padding: 5px 10px;
                    text-align: right;
                }
                .totals-table .label {
                    color: #777;
                    width: 70%;
                }
                .totals-table .amount {
                    font-weight: bold;
                    width: 30%;
                }
                .total-row td {
                    border-top: 2px solid #eee;
                    padding-top: 10px;
                    font-size: 16px; 
                    color: #000;
                }
                .footer {
                    margin-top: 50px;
                    text-align: center;
                    font-size: 10px;
                    color: #aaa;
                    border-top: 1px solid #eee;
                    padding-top: 10px;
                }
                .page-break { page-break-after: always; }
            </style>
        </head>
        <body>
        <?php
        return ob_get_clean();
    }

    public function get_footer_html() {
        return '</body></html>';
    }

    public function get_body_html( $order ) {
        $logo_url = '';
        if ( function_exists( 'get_custom_logo' ) ) {
            $custom_logo_id = get_theme_mod( 'custom_logo' );
            $logo = wp_get_attachment_image_src( $custom_logo_id , 'full' );
            if ( has_custom_logo() ) {
                $logo_url = $logo[0];
            }
        }
        
        $site_name = get_bloginfo( 'name' );

        $items_html = '';
        foreach ( $order->get_items() as $item_id => $item ) {
            $product      = $item->get_product();
            $product_name = $item->get_name();
            $qty          = $item->get_quantity();
            $total        = wc_price( $order->get_item_total( $item, false, true ), array( 'currency' => $order->get_currency() ) );
            $price        = wc_price( $order->get_item_subtotal( $item, false, true ), array( 'currency' => $order->get_currency() ) );
            
            $item_rtl = 'text-align: left;';
            
            $items_html .= '<tr>';
            $items_html .= '<td style="border-bottom:1px solid #eee; padding: 10px; ' . $item_rtl . '">';
            $items_html .= Opigm_Utils::render_bidi( wp_kses_post( $product_name ) );
            
            $meta_data = $item->get_formatted_meta_data( '' );
            if ( $meta_data ) {
                $items_html .= '<br/><small>';
                foreach ( $meta_data as $meta_id => $meta ) {
                    if ( in_array( $meta->key, [ 'afb_delivery_option', 'afb_pickup_store' ] ) ) continue; 
                    $meta_val = strip_tags( $meta->display_value );
                    $m_rtl = 'display:block; text-align:left;';
                    $items_html .= '<span style="' . $m_rtl . '">' . wp_kses_post( $meta->display_key ) . ': ' . Opigm_Utils::render_bidi( wp_kses_post( $meta_val ) ) . '</span><br/>';
                }
                $items_html .= '</small>';
            }
            
            $items_html .= '</td>';
            $items_html .= '<td style="border-bottom:1px solid #eee; padding: 10px;">' . wp_kses_post( $price ) . '</td>';
            $items_html .= '<td style="border-bottom:1px solid #eee; padding: 10px;">× ' . esc_html( $qty ) . '</td>';
            $items_html .= '<td style="border-bottom:1px solid #eee; padding: 10px; text-align:right;">' . wp_kses_post( $total ) . '</td>';
            $items_html .= '</tr>';
        }

        ob_start();
        ?>
        <div class="order-details-body">
            
            <div class="header">
                <?php if ( $logo_url ) : ?>
                    <img src="<?php echo esc_url( $logo_url ); ?>" class="logo-img" alt="Logo">
                <?php else : ?>
                    <div class="logo-text">DAMYEL</div>
                    
                <?php endif; ?>
            </div>

            <div class="order-info">
                <h2>Order #<?php echo esc_html( $order->get_order_number() ); ?> Details</h2>
                <p>
                    Payment method: <strong><?php echo Opigm_Utils::render_bidi( esc_html( $order->get_payment_method_title() ) ); ?></strong><br>
                    Customer IP: <strong><?php echo esc_html( $order->get_customer_ip_address() ); ?></strong>
                </p>
            </div>

            <table class="addresses">
                <tr>
                    <td style="padding-right: 20px;">
                        <div class="address-box">
                            <h3>Billing</h3>
                            <?php 
                                $b_text = $order->get_formatted_billing_address();
                                // Strip redundant titles often added by some themes/plugins
                                $b_text = str_ireplace( [ 'BILLING ADDRESS' ], '', $b_text );
                                echo '<div style="text-align:left;">' . wp_kses_post( Opigm_Utils::render_bidi( $b_text ) ) . '</div>';
                                if ( $order->get_billing_email() ) echo '<div>' . esc_html( $order->get_billing_email() ) . '</div>'; 
                                if ( $order->get_billing_phone() ) echo '<div>' . esc_html( $order->get_billing_phone() ) . '</div>'; 
                            ?>
                        </div>
                    </td>
                    <td>
                        <div class="address-box">
                            <h3>Shipping</h3>
                            <?php 
                                $s_text = $order->get_formatted_shipping_address();
                                // Strip redundant titles often added by some themes/plugins
                                $s_text = str_ireplace( [ 'SHIPPING ADDRESS' ], '', $s_text );
                                echo '<div style="text-align:left;">' . wp_kses_post( Opigm_Utils::render_bidi( $s_text ) ) . '</div>';
                            ?>
                        </div>
                    </td>
                </tr>
            </table>

            <?php if ( $order->get_customer_note() ) : 
                $note = $order->get_customer_note();
            ?>
            <div style="margin-bottom: 30px;">
                <h3 style="font-size: 14px; margin: 0 0 5px; color: #777; text-transform: uppercase;">Customer Note</h3>
                <div style="padding: 10px; background: #f9f9f9; border: 1px solid #eee; text-align:left;">
                    <?php echo nl2br( Opigm_Utils::render_bidi( esc_html( $note ) ) ); ?>
                </div>
            </div>
            <?php endif; ?>

            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Cost</th>
                        <th>Qty</th>
                        <th style="text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php echo $items_html; ?>
                </tbody>
            </table>

            <table class="totals-table">
                <tr>
                    <td class="label">Items Subtotal:</td>
                    <td class="amount"><?php echo wc_price( $order->get_subtotal(), array( 'currency' => $order->get_currency() ) ); ?></td>
                </tr>
                <?php if ( (float) $order->get_shipping_total() > 0 ) : ?>
                <tr>
                    <td class="label">Shipping:</td>
                    <td class="amount"><?php echo wc_price( $order->get_shipping_total(), array( 'currency' => $order->get_currency() ) ); ?></td>
                </tr>
                <?php endif; ?>
                <?php 
                $total_inc = (float) $order->get_total();
                $total_ex  = $total_inc / ( 1 + OPIGM_VAT_RATE );
                $vat_amount = $total_inc - $total_ex;
                ?>
                <tr>
                    <td class="label"><?php echo OPIGM_VAT_LABEL; ?>:</td>
                    <td class="amount"><?php echo wc_price( $vat_amount, array( 'currency' => $order->get_currency() ) ); ?></td>
                </tr>
                <?php ?>
                <tr class="total-row">
                    <td class="label">Order Total:</td>
                    <td class="amount"><?php echo $order->get_formatted_order_total(); ?></td>
                </tr>
            </table>

            <div class="footer">
                <?php echo esc_html( $site_name ); ?> &mdash; <?php echo date( 'Y' ); ?>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }
}
