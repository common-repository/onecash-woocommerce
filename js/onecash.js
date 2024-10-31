jQuery(document).ready(function($) {

	checkout_payment_type();

	function checkout_payment_type() {
		$('input[type="radio"][name="onecash_payment_type"]').on('change',function() {
			if ($('input[type="radio"][name="onecash_payment_type"]:checked').val() == "PAD") {
				$('.onecash_pad_description').slideDown(300);
				$('.onecash_pbi_description').slideUp(300);
			} else {
				$('.onecash_pad_description').slideUp(300);
				$('.onecash_pbi_description').slideDown(300);
			}
		});

		$('input[name="onecash_payment_type"]').trigger('change');
	}
});
