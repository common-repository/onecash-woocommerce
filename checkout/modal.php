<img src="<?php echo plugins_url('../images/onecash_logo.png', __FILE__ ) ?>" class="v-middle" id="onecash-logo" />
<span id="onecash-callout"><?php echo 'Pay in four monthly interest-free payments.'; ?></span>

<a href="#onecash-what-is-modal" id="what-is-onecash-trigger">
    <?php echo 'What is OneCash?'; ?>
</a>

<div id="onecash-what-is-modal" style="display:none;">
  <a href="https://www.onecash.com/terms/" target="_blank" style="border: none">
    <img class="onecash-modal-image" src="<?php echo plugins_url('../images/checkout/banner-large.jpg',__FILE__)  ?>" alt="OneCash" />
    <img class="onecash-modal-image-mobile" src="<?php echo pluginS_url('../images/checkout/banner-mobile.jpg', __FILE__ ) ?>" alt="OneCash" />
  </a>
</div>

<script type="text/javascript">
    // included inline as this template is loaded through an ajax request
    jQuery('#what-is-onecash-trigger').fancybox();
</script>
