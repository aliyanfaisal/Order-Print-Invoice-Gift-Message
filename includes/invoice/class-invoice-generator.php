<?php
if (!defined('ABSPATH')) {
    exit;
}

use Dompdf\Dompdf;
use Dompdf\Options;

class Opigm_Invoice_Generator
{

    public function generate_pdf($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        $html = $this->get_html($order);
        return $this->render_pdf_string($html);
    }

    public function stream_pdf($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die('Order not found');
        }

        $html = $this->get_html($order);
        $filename = 'invoice-' . $order->get_order_number() . '.pdf';

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        if (ob_get_length())
            ob_end_clean();

        $download = isset($_GET['download']) && $_GET['download'] == '1';
        $dompdf->stream($filename, ['Attachment' => $download]);
        exit;
    }

    private function render_pdf_string($html)
    {
        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        //  $options->set( 'defaultFont', 'DejaVu Sans' );
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function get_html($order)
    {
        return $this->get_header_html() . $this->get_body_html($order) . $this->get_footer_html();
    }

    public function get_header_html()
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <style>
                @page {
                    margin: 40px;
                }

                body {
                    font-family: 'DejaVu Sans', sans-serif;
                    font-size: 11px;
                    line-height: 1.4;
                    color: #000;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                    border-spacing: 0;
                }

                .header-table td {
                    vertical-align: top;
                }

                .logo {
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

                .invoice-details {
                    text-align: right;
                    color: #888;
                }

                .invoice-details strong {
                    font-size: 16px;
                    color: #444;
                    text-transform: uppercase;
                    display: block;
                    margin-bottom: 2px;
                }

                .invoice-details span {
                    display: block;
                    font-size: 12px;
                }

                .addresses-table {
                    margin-top: 30px;
                    width: 100%;
                }

                .addresses-table td {
                    vertical-align: top;
                    width: 48%;
                }

                .address-title {
                    font-weight: bold;
                    margin-bottom: 8px;
                    font-size: 12px;
                    text-decoration: underline;
                }

                .order-pickup-location h4 {
                    margin: 0 0 4px;
                    font-size: 12px;
                }

                .order-pickup-location p {
                    margin: 0;
                }

                .summary-strip {
                    margin-top: 30px;
                    background-color: #f9f9f9;
                    border: 1px solid #eee;
                }

                .summary-strip td {
                    padding: 8px 10px;
                    text-align: center;
                    font-size: 11px;
                }

                .summary-strip th {
                    padding: 8px 10px;
                    font-weight: bold;
                    text-align: center;
                    font-size: 11px;
                    background-color: #eee;
                    border-bottom: 1px solid #ddd;
                }

                .items-table {
                    margin-top: 25px;
                    border: 1px solid #000;
                    width: 100%;
                    table-layout: fixed;
                }

                .items-table th {
                    background-color: #f0f0f0;
                    padding: 8px;
                    text-align: left;
                    font-weight: bold;
                    border-bottom: 1px solid #000;
                    font-size: 11px;
                }

                .items-table th.right {
                    text-align: right;
                }

                .items-table th.center {
                    text-align: center;
                }

                .items-table td {
                    padding: 8px;
                    border-bottom: 1px solid #ddd;
                    vertical-align: middle;
                    word-wrap: break-word;
                }

                .items-table td.right {
                    text-align: right;
                }

                .items-table td.center {
                    text-align: center;
                }

                .totals-area {
                    margin-top: 20px;
                    width: 100%;
                    border-collapse: separate;
                    border-spacing: 0;
                }

                .totals-area td {
                    vertical-align: top;
                }

                .tax-table {
                    width: 100%;
                    border: 1px solid #ddd;
                    margin-bottom: 20px;
                }

                .tax-table th {
                    background-color: #f0f0f0;
                    padding: 5px;
                    font-size: 10px;
                    text-align: right;
                    border-bottom: 1px solid #ddd;
                }

                .tax-table th:first-child {
                    text-align: left;
                }

                .tax-table td {
                    padding: 5px;
                    text-align: right;
                    font-size: 10px;
                    border-bottom: 1px solid #eee;
                }

                .tax-table td:first-child {
                    text-align: left;
                }

                .grand-totals-table {
                    width: 100%;
                    border: 1px solid #000;
                }

                .grand-totals-table td {
                    padding: 6px 10px;
                    text-align: right;
                    border-bottom: 1px solid #000;
                }

                .grand-totals-table .label {
                    text-align: right;
                    background-color: #f5f5f5;
                    font-weight: bold;
                    border-right: 1px solid #ddd;
                    width: 40%;
                }

                .grand-totals-table .amount {
                    font-weight: bold;
                }

                .grand-total-row td {
                    border-top: 1px solid #000;
                    font-weight: bold;
                    font-size: 13px;
                    background-color: #fff;
                }

                .footer-table {
                    width: 100%;
                    border: 1px solid #ddd;
                    font-size: 10px;
                }

                .footer-table td {
                    padding: 5px;
                    border-bottom: 1px solid #eee;
                }

                .footer-label {
                    font-weight: bold;
                    background-color: #f5f5f5;
                    width: 30%;
                    border-right: 1px solid #ddd;
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

    public function get_footer_html()
    {
        return '</body></html>';
    }

    public function get_body_html($order)
    {
        $order_id = $order->get_id();
        $date_format = get_option('date_format');

        $billing_address = $order->get_formatted_billing_address();

        $delivery_option = (string) $order->get_meta('afb_delivery_option');
        $is_pickup = ($delivery_option === 'pickup');

        ob_start();
        ?>
            <div class="invoice-body">

                <table class="header-table">
                    <tr>
                        <td style="width: 55%;">
                            <div class="logo">DAMYEL</div>
                            <div class="logo-subtext">
                                Z.H.D SELECTION LTD<br />
                                Company No.: 516236007
                            </div>
                        </td>
                        <td class="invoice-details" style="width: 45%;">
                            <strong>INVOICE/RECEIPT</strong>
                            <span><?php echo Opigm_Utils::render_bidi(esc_html(date_i18n($date_format, current_time('timestamp')))); ?></span>
                            <span>#<?php echo esc_html($order->get_order_number()); ?></span>
                        </td>
                    </tr>
                </table>

                <table class="addresses-table">
                    <tr>
                        <td>
                            <div class="address-title">
                                <?php echo $is_pickup ? esc_html__('In-Store Pickup Info', 'afb-offcanvas') : esc_html__('Shipping Address', 'afb-offcanvas'); ?>
                            </div>
                            <?php
                            if ($delivery_option === 'multiship') {
                                echo '<div class="multiship-addresses" style="font-size:10px;">';
                                $items = $order->get_items();
                                $count_items = count($items);
                                $idx = 0;
                                foreach ($items as $item) {
                                    $idx++;
                                    $address_meta = $item->get_meta('selected_address', true);
                                    if (!empty($address_meta) && is_array($address_meta)) {
                                        $item_name = $item->get_name();
                                        $item_rtl = Opigm_Utils::is_hebrew($item_name) ? 'direction:rtl; text-align:right;' : '';
                                        echo '<p style="margin:0 0 2px; ' . $item_rtl . '"><strong>' . wp_kses_post($item_name) . '</strong></p>';

                                        $f_name = isset($address_meta['shipping_first_name']) ? $address_meta['shipping_first_name'] : '';
                                        $l_name = isset($address_meta['shipping_last_name']) ? $address_meta['shipping_last_name'] : '';
                                        if ($f_name || $l_name) {
                                            $full_name = trim($f_name . ' ' . $l_name);
                                            $name_rtl = Opigm_Utils::is_hebrew($full_name) ? 'direction:rtl; text-align:right;' : '';
                                            echo '<p style="margin:0; ' . $name_rtl . '">' . esc_html($full_name) . '</p>';
                                        }

                                        $addr_parts = [];
                                        if (!empty($address_meta['shipping_address_1']))
                                            $addr_parts[] = $address_meta['shipping_address_1'];
                                        if (!empty($address_meta['shipping_city']))
                                            $addr_parts[] = $address_meta['shipping_city'];
                                        if (!empty($address_meta['shipping_postcode']))
                                            $addr_parts[] = $address_meta['shipping_postcode'];
                                        if (!empty($address_meta['shipping_country']))
                                            $addr_parts[] = $address_meta['shipping_country'];

                                        if (!empty($addr_parts)) {
                                            $addr_str = implode(', ', $addr_parts);
                                            $addr_rtl = Opigm_Utils::is_hebrew($addr_str) ? 'direction:rtl; text-align:right;' : '';
                                            echo '<p style="margin:0; ' . $addr_rtl . '">' . esc_html($addr_str) . '</p>';
                                        }

                                        if ($idx < $count_items) {
                                            echo '<hr style="border:0; border-bottom:1px solid #ddd; margin: 8px 0;">';
                                        } else {
                                            echo '<div style="margin-bottom:8px;"></div>';
                                        }
                                    }
                                }
                                echo '</div>';

                            } elseif ($is_pickup) {
                                $pickup_location = $order->get_meta('afb_pickup_store') ?: $order->get_meta('pickup_location');
                                $delivery_option = get_post_meta($order->get_id(), 'afb_delivery_option', true);
                                $pickup_location = get_post_meta($order->get_id(), '_pickup_location', true);

                                echo '<div class="order-pickup-location">';

                                $info = function_exists('afb_get_store_info') ? afb_get_store_info($pickup_location) : null;
                                if ($info && ($info['address'] || $info['city'] || $info['phone'])) {
                                    $name_style = Opigm_Utils::is_hebrew($info['name']) ? 'direction:rtl; text-align:right;' : 'margin:0;';
                                    echo '<p style="' . $name_style . '">' . esc_html($info['name']) . '</p>';

                                    if ($info['address']) {
                                        $addr_style = Opigm_Utils::is_hebrew($info['address']) ? 'direction:rtl; text-align:right;' : 'margin:0;';
                                        echo '<p style="' . $addr_style . '">' . esc_html($info['address']) . '</p>';
                                    }
                                    if ($info['city']) {
                                        $city_style = Opigm_Utils::is_hebrew($info['city']) ? 'direction:rtl; text-align:right;' : 'margin:0;';
                                        echo '<p style="' . $city_style . '">' . esc_html($info['city']) . '</p>';
                                    }
                                    if ($info['phone']) {
                                        echo '<p style="margin:0;">' . esc_html($info['phone']) . '</p>';
                                    }
                                } else {
                                    $fmt_loc = function_exists('afb_format_pickup_location') ? afb_format_pickup_location($pickup_location) : $pickup_location;
                                    $fmt_style = Opigm_Utils::is_hebrew($fmt_loc) ? 'direction:rtl; text-align:right;' : 'margin:0;';
                                    echo '<p style="' . $fmt_style . '">' . esc_html($fmt_loc) . '</p>';
                                }
                                echo '</div>';

                            } else {
                                $ship_addr = $order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address();
                                // Consistently align to start (left), avoid direction: rtl conflict with render_bidi
                                echo '<div style="text-align:left;">' . Opigm_Utils::render_bidi($ship_addr) . '</div>';
                            }
                            ?>
                        </td>

                        <td style="width: 4%;"></td>

                        <td>
                            <div class="address-title">Billing Address</div>
                            <?php
                            $bill_addr = $order->get_formatted_billing_address();
                            // Consistently align to start (left), avoid direction: rtl conflict with render_bidi
                            echo '<div style="text-align:left;">' . Opigm_Utils::render_bidi($bill_addr) . '</div>';
                            ?>
                            <div style="margin-top:5px;">
                                <?php if ($order->get_billing_email())
                                    echo '<div>' . esc_html($order->get_billing_email()) . '</div>'; ?>
                                <?php if ($order->get_billing_phone())
                                    echo '<div>' . esc_html($order->get_billing_phone()) . '</div>'; ?>
                            </div>
                        </td>
                    </tr>
                </table>

                <table class="summary-strip" style="width:100%; border:1px solid #777; margin-top:20px;">
                    <tr style="background-color:#eee;">
                        <th style="padding:5px;">Invoice Number</th>
                        <th style="padding:5px;">Invoice Date</th>
                        <th style="padding:5px;">Order Ref</th>
                        <th style="padding:5px;">Order Date</th>
                    </tr>
                    <tr>
                        <td>#<?php echo esc_html($order->get_order_number()); ?></td>
                        <td><?php echo Opigm_Utils::render_bidi(esc_html(date_i18n($date_format, current_time('timestamp')))); ?></td>
                        <td><?php echo esc_html($order->get_id()); ?></td>
                        <td><?php echo Opigm_Utils::render_bidi(esc_html(date_i18n($date_format, strtotime($order->get_date_created())))); ?></td>
                    </tr>
                </table>

                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width:70%;">Product</th>
                            <th class="center" style="width:10%;">Qty</th>
                            <th class="right" style="width:20%;">Total (Inc. Tax)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($order->get_items() as $item_id => $item) {
                            $product = $item->get_product();

                            // Manual Tax Calculation
                            $total_inc_tax = (float) $item->get_total();
                            $qty = (float) $item->get_quantity();

                            $product_name = $item->get_name();
                            $is_hebrew = Opigm_Utils::is_hebrew($product_name);
                            $align_style = 'text-align:left;';

                            echo '<tr>';
                            echo '<td style="' . $align_style . '">' . Opigm_Utils::render_bidi(wp_kses_post($product_name));

                            $meta_data = $item->get_formatted_meta_data('');
                            if ($meta_data) {
                                echo '<br/><small>';
                                foreach ($meta_data as $meta_id => $meta) {
                                    if (in_array($meta->key, ['afb_delivery_option', 'afb_pickup_store']))
                                        continue;
                                    $meta_val = strip_tags($meta->display_value);
                                    $m_align = 'display:block; text-align:left;';
                                    echo '<span style="' . $m_align . '">' . wp_kses_post($meta->display_key) . ': ' . Opigm_Utils::render_bidi(wp_kses_post($meta_val)) . '</span>';
                                }
                                echo '</small>';
                            }
                            echo '</td>';
                            echo '<td class="center">' . esc_html($qty) . '</td>';
                            echo '<td class="right">' . wc_price($total_inc_tax, array('currency' => $order->get_currency())) . '</td>';
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>


                <?php
                // Manual Tax Calculation logic
                $total_inc_tax = (float) $order->get_total();

                // Products totals (Subtotal in WC includes discounts but usually matches item totals sum)
                $products_inc_tax = (float) $order->get_subtotal();
                $products_ex_tax = $products_inc_tax / (1 + OPIGM_VAT_RATE);
                $products_vat = $products_inc_tax - $products_ex_tax;

                // Shipping totals
                $shipping_inc_tax = (float) $order->get_shipping_total();
                $shipping_ex_tax = $shipping_inc_tax / (1 + OPIGM_VAT_RATE);
                $shipping_vat = $shipping_inc_tax - $shipping_ex_tax;

                // Grand Summary
                $summary_inc_vat = $total_inc_tax;
                $summary_ex_tax = $summary_inc_vat / (1 + OPIGM_VAT_RATE);
                $summary_vat = $summary_inc_vat - $summary_ex_tax;
                ?>

                <table style="width:100%; margin-top:20px;">
                    <tr>
                        <td style="width: 50%; vertical-align:top; padding-right: 15px;">
                            <div class="address-title" style="margin-top:0; text-decoration:none;">Tax Details</div>
                            <table class="tax-table" style="margin-bottom:15px;">
                                <thead>
                                    <tr>
                                        <th>Total</th>
                                        <th>Tax Rate</th>
                                        <th>Base Price</th>
                                        <th>Tax Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Product(s)</td>
                                        <td><?php echo OPIGM_VAT_LABEL; ?></td>
                                        <td><?php echo wc_price($products_ex_tax, array('currency' => $order->get_currency(), 'decimals' => 2)); ?>
                                        </td>
                                        <td><?php echo wc_price($products_vat, array('currency' => $order->get_currency(), 'decimals' => 2)); ?>
                                        </td>
                                    </tr>

                                    <?php if ($shipping_inc_tax > 0): ?>
                                        <tr>
                                            <td>Shipping</td>
                                            <td><?php echo OPIGM_VAT_LABEL; ?></td>
                                            <td><?php echo wc_price($shipping_ex_tax, array('currency' => $order->get_currency(), 'decimals' => 2)); ?>
                                            </td>
                                            <td><?php echo wc_price($shipping_vat, array('currency' => $order->get_currency(), 'decimals' => 2)); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <table class="footer-table">
                                <tr>
                                    <td class="footer-label">Payment Method</td>
                                    <td><?php echo Opigm_Utils::render_bidi(esc_html($order->get_payment_method_title())); ?>
                                    </td>
                                </tr>
                                <?php if ($order->get_shipping_method()): ?>
                                    <tr>
                                        <td class="footer-label">Carrier</td>
                                        <td><?php echo Opigm_Utils::render_bidi(esc_html($order->get_shipping_method())); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </table>

                        </td>

                        <td style="width: 50%; vertical-align:top;">
                            <table class="grand-totals-table">

                                <tr>
                                    <td class="label">Sub Total Products (Incl. Tax)</td>
                                    <td class="amount">
                                        <?php echo wc_price($products_inc_tax, array('currency' => $order->get_currency())); ?>
                                    </td>
                                </tr>
                                <?php if ($shipping_inc_tax > 0): ?>
                                    <tr>
                                        <td class="label">Shipping Cost (Incl. Tax)</td>
                                        <td class="amount">
                                            <?php echo wc_price($shipping_inc_tax, array('currency' => $order->get_currency())); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="label">Total (Excl. VAT)</td>
                                    <td class="amount">
                                        <?php echo wc_price($summary_ex_tax, array('currency' => $order->get_currency(), 'decimals' => 2)); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label"><?php echo OPIGM_VAT_LABEL; ?></td>
                                    <td class="amount">
                                        <?php echo wc_price($summary_vat, array('currency' => $order->get_currency(), 'decimals' => 2)); ?>
                                    </td>
                                </tr>

                                <tr class="grand-total-row">
                                    <td class="label">Grand Total (Inc. VAT)</td>
                                    <td class="amount">
                                        <?php echo wc_price($summary_inc_vat, array('currency' => $order->get_currency())); ?>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </div>
            <?php
            return ob_get_clean();
    }
}
