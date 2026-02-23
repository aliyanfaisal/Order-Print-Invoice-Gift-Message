<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Dompdf\Dompdf;
use Dompdf\Options;

class Opigm_PDF_Generator {

    public function __construct() {
        add_action( 'wp_ajax_opigm_print_gift_message', [ $this, 'generate_pdf' ] );
        add_action( 'wp_ajax_opigm_print_all', [ $this, 'generate_print_all_zip' ] );
        add_action( 'wp_ajax_opigm_bulk_print_all', [ $this, 'generate_bulk_print_all' ] );
    }

    public function generate_print_all_zip() {
        if ( ! check_ajax_referer( 'opigm_print_all', 'nonce', false ) && ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Unauthorized access', 'afb-offcanvas' ) );
        }

        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        if ( ! $order_id ) {
            wp_die( __( 'No order selected', 'afb-offcanvas' ) );
        }

        $zip_file = tempnam( sys_get_temp_dir(), 'opigm_print_all_' );
        $zip      = new ZipArchive();

        if ( $zip->open( $zip_file, ZipArchive::CREATE ) !== TRUE ) {
            wp_die( __( 'Could not create ZIP file', 'afb-offcanvas' ) );
        }

        // 1. Gift Message
        $gift_html = $this->get_single_pdf_html( $order_id );
        $gift_pdf  = $this->render_pdf_string( $gift_html );
        $zip->addFromString( 'gift-message-' . $order_id . '.pdf', $gift_pdf );

        // 2. Invoice (only if order is invoiceable)
        $order = wc_get_order( $order_id );
        if ( class_exists( 'Opigm_Invoice_Generator' ) && Opigm_Utils::is_order_invoiceable( $order ) ) {
            $invoice_gen = new Opigm_Invoice_Generator();
            $invoice_pdf = $invoice_gen->generate_pdf( $order_id );
            if ( $invoice_pdf ) {
                 $zip->addFromString( 'invoice-' . $order_id . '.pdf', $invoice_pdf );
            }
        }

        // 3. Order Details
        if ( class_exists( 'Opigm_Order_Details_Generator' ) ) {
            $details_gen = new Opigm_Order_Details_Generator();
            $details_pdf = $details_gen->generate_pdf_string( $order_id );
            if ( $details_pdf ) {
                $zip->addFromString( 'order-details-' . $order_id . '.pdf', $details_pdf );
            }
        }

        $zip->close();

        $order = wc_get_order( $order_id );
        $order_number = $order ? $order->get_order_number() : $order_id;
        $date = date( 'Y-m-d-H-i-s' );
        $filename = 'Print All - Order #' . $order_number . ' - ' . $date . '.zip';

        header( 'Content-Type: application/zip' );
        header( 'Content-disposition: attachment; filename=' . $filename );
        header( 'Content-Length: ' . filesize( $zip_file ) );
        readfile( $zip_file );
        unlink( $zip_file );
        exit;
    }

    public function generate_bulk_print_all() {
        if ( ! check_ajax_referer( 'opigm_bulk_print_all', 'nonce', false ) && ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Unauthorized access', 'afb-offcanvas' ) );
        }

        $order_ids = isset( $_GET['order_ids'] ) ? array_map( 'absint', explode( ',', $_GET['order_ids'] ) ) : [];
        if ( empty( $order_ids ) ) {
            wp_die( __( 'No orders selected', 'afb-offcanvas' ) );
        }

        $master_zip_file = tempnam( sys_get_temp_dir(), 'opigm_bulk_all_' );
        $master_zip = new ZipArchive();

        if ( $master_zip->open( $master_zip_file, ZipArchive::CREATE ) !== TRUE ) {
            wp_die( __( 'Could not create master ZIP file', 'afb-offcanvas' ) );
        }

        foreach ( $order_ids as $order_id ) {
            $order_zip_file = tempnam( sys_get_temp_dir(), 'opigm_order_' );
            $order_zip = new ZipArchive();

            if ( $order_zip->open( $order_zip_file, ZipArchive::CREATE ) !== TRUE ) {
                continue;
            }

            // Generate all 3 PDFs for this order
            $gift_html = $this->get_single_pdf_html( $order_id );
            $gift_pdf = $this->render_pdf_string( $gift_html );
            $order_zip->addFromString( 'gift-message-' . $order_id . '.pdf', $gift_pdf );

            // Only add invoice if order is invoiceable (Processing or Completed)
            $order = wc_get_order( $order_id );
            if ( class_exists( 'Opigm_Invoice_Generator' ) && Opigm_Utils::is_order_invoiceable( $order ) ) {
                $invoice_gen = new Opigm_Invoice_Generator();
                $invoice_pdf = $invoice_gen->generate_pdf( $order_id );
                if ( $invoice_pdf ) {
                    $order_zip->addFromString( 'invoice-' . $order_id . '.pdf', $invoice_pdf );
                }
            }

            if ( class_exists( 'Opigm_Order_Details_Generator' ) ) {
                $details_gen = new Opigm_Order_Details_Generator();
                $details_pdf = $details_gen->generate_pdf_string( $order_id );
                if ( $details_pdf ) {
                    $order_zip->addFromString( 'order-details-' . $order_id . '.pdf', $details_pdf );
                }
            }

            $order_zip->close();

            // Add this order's ZIP to master ZIP
            $order = wc_get_order( $order_id );
            $order_number = $order ? $order->get_order_number() : $order_id;
            $date = date( 'Y-m-d-H-i-s' );
            $zip_name = 'Export Print All - Order #' . $order_number . ' - ' . $date . '.zip';
            $master_zip->addFile( $order_zip_file, $zip_name );
        }

        $master_zip->close();

        // Clean up individual order ZIPs
        foreach ( $order_ids as $order_id ) {
            $temp_file = sys_get_temp_dir() . '/opigm_order_*';
            array_map( 'unlink', glob( $temp_file ) );
        }

        $date = date( 'Y-m-d-H-i-s' );
        $filename = 'Print All - ' . $date . '.zip';

        header( 'Content-Type: application/zip' );
        header( 'Content-disposition: attachment; filename=' . $filename );
        header( 'Content-Length: ' . filesize( $master_zip_file ) );
        readfile( $master_zip_file );
        unlink( $master_zip_file );
        exit;
    }

    public function generate_pdf() {
        if ( ! check_ajax_referer( 'opigm_print_gift_message', 'nonce', false ) && ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Unauthorized access', 'afb-offcanvas' ) );
        }

        $order_ids = [];
        if ( isset( $_GET['order_id'] ) ) {
            $order_ids[] = absint( $_GET['order_id'] );
        } elseif ( isset( $_GET['order_ids'] ) ) {
            $order_ids = array_map( 'absint', explode( ',', $_GET['order_ids'] ) );
        }

        if ( empty( $order_ids ) ) {
            wp_die( __( 'No order selected', 'afb-offcanvas' ) );
        }

        $download = isset( $_GET['download'] ) && $_GET['download'] == '1';

        if ( count( $order_ids ) === 1 ) {
            $html = $this->get_single_pdf_html( $order_ids[0] );
            $this->render_stream_pdf( $html, 'gift-message-' . $order_ids[0] . '.pdf' );
            exit;
        }

        if ( $download ) {
            $zip_file = tempnam( sys_get_temp_dir(), 'opigm_zip' );
            $zip      = new ZipArchive();
            
            if ( $zip->open( $zip_file, ZipArchive::CREATE ) !== TRUE ) {
                wp_die( __( 'Could not create ZIP file', 'afb-offcanvas' ) );
            }

            foreach ( $order_ids as $order_id ) {
                $html = $this->get_single_pdf_html( $order_id );
                $pdf_content = $this->render_pdf_string( $html );
                $zip->addFromString( 'gift-message-' . $order_id . '.pdf', $pdf_content );
            }
            
            $zip->close();

            $date = date( 'Y-m-d-H-i-s' );
            $filename = 'Download Gift Message - ' . $date . '.zip';

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
            foreach ( $order_ids as $index => $order_id ) {
                $html .= $this->get_body_html( $order_id );
                if ( $index < $count - 1 ) {
                    $html .= '<div class="page-break"></div>';
                }
            }
            $html .= $this->get_footer_html();

            $date = date( 'Y-m-d-H-i-s' );
            $filename = 'Print Gift Messages - ' . $date . '.pdf';

            $this->render_stream_pdf( $html, $filename );
            exit;
        }
    }

    private function render_stream_pdf( $html, $filename ) {
        $options = new Options();
        $options->set( 'defaultFont', 'DejaVu Sans' );
        $options->set( 'isRemoteEnabled', true );
        $options->set( 'isHtml5ParserEnabled', true );

        $dompdf = new Dompdf( $options );
        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();

        $download = isset( $_GET['download'] ) && $_GET['download'] == '1';
        $dompdf->stream( $filename, [ 'Attachment' => $download ] );
    }

    private function render_pdf_string( $html ) {
        $options = new Options();
        $options->set( 'defaultFont', 'DejaVu Sans' );
        $options->set( 'isRemoteEnabled', true );
        $options->set( 'isHtml5ParserEnabled', true );

        $dompdf = new Dompdf( $options );
        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();

        return $dompdf->output();
    }

    public function get_single_pdf_html( $order_id ) {
        return $this->get_header_html() . $this->get_body_html( $order_id ) . $this->get_footer_html();
    }

    public function get_header_html() {
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
                    background: #ffffff; 
                    margin: 0;
                    padding: 0;
                    color: #000;
                }
                .page-container {
                    width: 100%;
                    padding-top: 30px;  
                    box-sizing: border-box;
                    max-width: 85%;
                    margin: 0 auto;
                }
                .logo {
                    margin-top: 20px;
                    margin-bottom: 40px;
                    text-align: center;
                    width: 100%;
                }
                .logo-text {
                    font-size: 30px;
                    font-weight: bold;
                    text-transform: uppercase;
                    letter-spacing: 2px;
                    margin-bottom: 5px;
                    color: #000;
                }
                .logo-subtext {
                    font-size: 10px;
                    font-weight: normal;
                    letter-spacing: 1px;
                    color: #444;
                    margin-top: 5px;
                    line-height: 1.2;
                    text-transform: none;
                }
                .message-body {
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
                .signature {
                    margin-top: 40px;
                    text-align: left;
                    font-size: 14px;
                    color: #000;
                    line-height: 1.5;
                    font-weight: normal;
                }
                .signature strong {
                    font-weight: normal;
                    font-size: 14px;
                    display: block;
                    margin-bottom: 2px;
                }
                .quote { 
                    font-style: italic;
                    font-weight: normal; 
                    font-size: 14px;
                }
                .website-line {
                    margin-top: 15px;
                    font-size: 12px;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    font-weight: normal;
                }
                .page-break {
                    page-break-after: always;
                }
            </style>
        </head>
        <body>
        <?php
        return ob_get_clean();
    }

    public function get_footer_html() {
        return '</body></html>';
    }

    public function get_body_html( $order_id ) {
        $messages = [];
        $order = wc_get_order( $order_id );
        
        if ( $order ) {
             foreach ( $order->get_items() as $item ) {
                $msg = $item->get_meta( 'product_message', true );
                if ( ! empty( $msg ) ) {
                    $messages[] = Opigm_Utils::render_bidi( $msg );
                }
            }
            
            if ( empty( $messages ) ) {
                 $msg = $order->get_meta( 'product_message', true ); 
                 if ( $msg ) {
                     $messages[] = Opigm_Utils::render_bidi( $msg );
                 } else {
                     $note = $order->get_customer_note();
                     if ( ! empty( $note ) ) {
                         $messages[] = Opigm_Utils::render_bidi( $note );
                     }
                 }
            }
            
            if ( empty( $messages ) ) {
                 $messages[] = __( 'No Gift Message found for this order.', 'afb-offcanvas' );
            }
        }
        
        $all_messages = $messages;
        
        // Get customer name
        $customer_name = '';
        if ( $order ) {
            $first_name = $order->get_billing_first_name();
            $last_name = $order->get_billing_last_name();
            $customer_name = trim( $first_name . ' ' . $last_name );
            $customer_name = Opigm_Utils::render_bidi( $customer_name );
        }

        ob_start();
        ?>
        <div class="gift-messages-container">
        <?php
        $count = count( $all_messages );
        foreach ( $all_messages as $index => $message ) {
             include OPIGM_PLUGIN_DIR . 'templates/gift-message-pdf.php';
             if ( $index < $count - 1 ) {
                 echo '<div class="page-break"></div>';
             }
        }
        ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
