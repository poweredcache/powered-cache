/* global jQuery  */
import '@wpmudev/shared-ui/dist/js/_src/modal-dialog';
import './modules/nav';
import './modules/toggle';
import './modules/modal';

(function ($) {
	let zone_index = 0;

	$('.add_cdn_hostname').click(function (e) {
		e.preventDefault();
		let ref_el = $('.cdn-zone:first').clone();

		if (zone_index === 0) {
			zone_index = $('.cdn-zone').length;
		} else {
			zone_index++;
		}

		ref_el = $(ref_el).attr('id', `cdn-zone-${zone_index}`);
		$(ref_el).find('.cdn_hostname').val('');
		$(ref_el).find('.cdn_zone').prop('selectedIndex', 0);

		$(ref_el).find('button').removeClass('sui-hidden-important');

		$('#cdn-zones').append(ref_el);
	});

	$('#cdn-zones').on('click', '.remove_cdn_hostname', function () {
		const target_node = $(this).parents('.cdn-zone');
		if (target_node.attr('id') === 'cdn-zone-0') {
			alert('Nice try :) This zone cannot be removed!');
			return false;
		}

		target_node.remove();
		return true;
	});

	// toggle sitemap options based on cache preloading
	$('#enable_cache_preload').on('change', function () {
		if ($(this).is(':checked')) {
			$('#enable_sitemap_preload_wrapper').show();
		} else {
			$('#enable_sitemap_preload_wrapper').hide();
		}
	});

	$('#enable_page_cache').on('change', function () {
		if ($(this).is(':checked')) {
			$('#preload_page_cache_warning_message').hide();
		} else {
			$('#preload_page_cache_warning_message').show();
		}
	});

	$('#cloudflare-api-token').on('keyup keypress change', function () {
		if ($(this).val().length > 0) {
			$('#cloudflare-api-details').hide();
		} else {
			$('#cloudflare-api-details').show();
		}
	});

	$('.heartbeat_dashboard_status').on('change', function () {
		if ($(this).val() === 'modify') {
			$('#heartbeat-dashboard-interval').show();
		} else {
			$('#heartbeat-dashboard-interval').hide();
		}
	});

	$('.heartbeat_radio').on('change', function () {
		const targetInput = $(this).attr('aria-controls');

		if (!targetInput) {
			return;
		}

		if ($(this).val() === 'modify') {
			$(`#${targetInput}`).show();
		} else {
			$(`#${targetInput}`).hide();
		}
	});

	$('#powered-cache-import-file-input').on('change', function () {
		const elm = $(this)[0];
		if (elm.files.length) {
			const file = elm.files[0];
			$('#powered-cache-import-file-name').text(file.name);
			$('#powered-cache-import-upload-wrap').addClass('sui-has_file');
			$('#powered-cache-import-btn').removeAttr('disabled');
		} else {
			$('#powered-cache-import-file-name').text('');
			$('#powered-cache-import-upload-wrap').removeClass('sui-has_file');
			$('#powered-cache-import-btn').attr('disabled', 'disabled');
		}
	});

	$('#powered-cache-import-remove-file').on('click', function () {
		$('#powered-cache-import-file-input').val('').trigger('change');
	});
})(jQuery);
