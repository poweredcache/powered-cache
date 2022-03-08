/* global jQuery  */
(function ($) {
	$('#powered_cache_specific_critical_css').on('change', function () {
		if ($(this).is(':checked')) {
			$('#powered_cache_disable_critical_css').attr('disabled', 'disbled');
		} else {
			$('#powered_cache_disable_critical_css').removeAttr('disabled');
		}
	});

	$('#powered_cache_disable_critical_css').on('change', function () {
		if ($(this).is(':checked')) {
			$('#powered_cache_specific_critical_css').attr('disabled', 'disbled');
		} else {
			$('#powered_cache_specific_critical_css').removeAttr('disabled');
		}
	});
})(jQuery);
