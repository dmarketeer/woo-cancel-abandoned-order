(function ($) {
	$(function () {

		// Each settings section (table) has its own mode selector.
		function woo_cao_check_mode($select) {
			var $table = $select.closest('table');
			var mode = $select.val();

			$table.find('.woo_cao-field-moded').closest('tr').hide();
			$table.find('.woo_cao-field-' + mode).closest('tr').show();
		}

		$('select.woo_cao-field-mode')
			.on('change', function () {
				woo_cao_check_mode($(this));
			})
			.each(function () {
				woo_cao_check_mode($(this));
			});
	});
})(jQuery);
