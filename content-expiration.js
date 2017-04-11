jQuery(document).ready(function($) {
	$('input[name="expiration-status"]').change(function() {
		switch ($('input[name="expiration-status"]:checked').val()) {
			case "by-date":
				$('#expiration-info').show();
				$('#expiration-by-date').show();
				$('#expiration-by-days').hide();
				break;
			case "by-days":
				$('#expiration-info').show();
				$('#expiration-by-date').hide();
				$('#expiration-by-days').show();
				break;
			default:
				$('#expiration-info').hide();
				$('#expiration-by-date').hide();
				$('#expiration-by-days').hide();
		}
	});
})
