jQuery(function ($) {
	// Aggiungi nuova caratteristica
	$('.isi-add-feature').on('click', function () {
		var template = $('.isi-feature-row-template').html();
		var $newRow = $('<tr>' + template + '</tr>');

		// Animazione per la nuova riga
		$newRow.hide();
		$('.isi-features-table tbody').append($newRow);
		$newRow.fadeIn(300);

		// Focus sul primo input della nuova riga
		$newRow.find('input:first').focus();
	});

	// Rimuovi caratteristica
	$('.isi-features-table').on('click', '.isi-remove-feature', function () {
		var $row = $(this).closest('tr');
		$row.fadeOut(300, function () {
			$(this).remove();
		});
	});
});
