<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
    <div class="page-container">
        <div class="logo">
            <div class="logo-text">DAMYEL</div>
            
        </div>

        <?php 
            $message_rtl = Opigm_Utils::is_hebrew( $message ) ? 'direction:rtl; text-align:center !important;' : 'text-align:center !important;';
        ?>
        <div class="message-body" style="<?php echo $message_rtl; ?>">
            <?php echo $message; ?>
        </div>

        <?php 
            $name_rtl = Opigm_Utils::is_hebrew( $customer_name ) ? 'direction:rtl; text-align:left !important; display:inline-block;' : 'text-align:left !important;';
        ?>
        <div class="signature" style="<?php echo $name_rtl; ?>">
            <?php if ( ! empty( $customer_name ) ) : ?>
                <span class="names"><?php echo $customer_name; ?></span> 
            <?php endif; ?>
            <div class="website-line">WWW.DAMYEL.CO.IL | 00 972 54 22 32 563</div>
        </div>
    </div>
