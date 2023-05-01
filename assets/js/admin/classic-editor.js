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

	$('#powered_cache_specific_ucss').on('change', function () {
		if ($(this).is(':checked')) {
			$('#powered_cache_disable_ucss').attr('disabled', 'disbled');
		} else {
			$('#powered_cache_disable_ucss').removeAttr('disabled');
		}
	});

	$('#powered_cache_disable_ucss').on('change', function () {
		if ($(this).is(':checked')) {
			$('#powered_cache_specific_ucss').attr('disabled', 'disbled');
		} else {
			$('#powered_cache_specific_ucss').removeAttr('disabled');
		}
	});
})(jQuery);
