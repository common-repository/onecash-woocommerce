<?php /** @var $this OneCash_OneCash_Block_Form_Payovertime */ ?>
<ul class="form-list" style="">
    <li class="form-alt">
        <div class="instalments">
            <p class="header-text">
                <?php
                  global $woocommerce;
                  $order_total = $woocommerce->cart->total;
                  $instalment_amount = $order_total / 4;
                ?>
                <?php echo '4 interest-free payments of '; ?>
                <?php
                  echo "<strong>$" . number_format( $instalment_amount, 2 ) . "</strong>";
                ?>
                <?php echo ' totalling '; ?>
                <?php
                  echo "<strong>$" . number_format( $order_total, 2 ) . "</strong>";
                ?>
            </p>
            <ul class="cost">
                <li><?php echo "$" . number_format( $instalment_amount, 2 ); ?></li>
                <li><?php echo "$" . number_format( $instalment_amount, 2 ); ?></li>
                <li><?php echo "$" . number_format( $instalment_amount, 2 ); ?></li>
                <li><?php echo "$" . number_format( $instalment_amount, 2 ); ?></li>
            </ul>
            <ul class="icon">
                <li>
                    <img src="<?php echo plugins_url('../images/checkout/circle_1@2x.png', __FILE__ ); ?>" alt="" height="24" width="24"/>
                </li>
                <li>
                    <img src="<?php echo plugins_url('../images/checkout/circle_2@2x.png', __FILE__ ) ?>" alt="" height="24" width="24"/>
                </li>
                <li>
                    <img src="<?php echo plugins_url('../images/checkout/circle_3@2x.png', __FILE__ ) ?>" alt="" height="24" width="24"/>
                </li>
                <li>
                    <img src="<?php echo plugins_url('../images/checkout/circle_4@2x.png', __FILE__ ) ?>" alt="" height="24" width="24"/>
                </li>
            </ul>
            <ul class="instalment">
                <li>First Payment<br />Today</li>
                <li>1 month later<br /><?php echo date('d M', strtotime('+1 months')) ?></li>
                <li>2 months later<br /><?php echo date('d M', strtotime('+2 months')) ?></li>
                <li>3 months later<br /><?php echo date('d M', strtotime('+3 months')) ?></li>
            </ul>
        </div>
        <div class="instalment-footer">
            <p><?php echo "You'll be redirected to the OneCash website when you proceed to checkout." ?></p>
            <a href="http://www.onecash.com/terms/" target="_blank"><?php echo 'Terms & Conditions'; ?></a>
        </div>
    </li>
</ul>
