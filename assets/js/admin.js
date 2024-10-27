jQuery(document).ready(function ($) {
	// Rimuoviamo il vecchio handler che potrebbe interferire con la navigazione
	/*$('.nav-tab-wrapper .nav-tab').on('click', function(e) {
        e.preventDefault(); // Questo era il problema - preveniva la navigazione normale
        var target = $(this).data('tab');
        $('.nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
    });*/

	// Gestiamo solo il salvataggio delle impostazioni
	$('#isi-settings-form').on('submit', function (e) {
		e.preventDefault();

		var $form = $(this);
		var $submitButton = $form.find('input[type="submit"]');

		$submitButton.prop('disabled', true);

		$.ajax({
			url: isigestsyncapi.ajaxurl,
			type: 'POST',
			data: {
				action: 'isigestsyncapi_save_settings',
				nonce: isigestsyncapi.nonce,
				formData: $form.serialize()
			},
			success: function (response) {
				if (response.success) {
					showNotice('success', 'Impostazioni salvate con successo');
				} else {
					showNotice('error', response.data.message || 'Errore durante il salvataggio');
				}
			},
			error: function () {
				showNotice('error', 'Errore di connessione');
			},
			complete: function () {
				$submitButton.prop('disabled', false);
			}
		});
	});

	// Test connessione API
	$('#test-api-connection').on('click', function (e) {
		e.preventDefault();
		var $button = $(this);
		$button.prop('disabled', true);

		$.ajax({
			url: isigestsyncapi.ajaxurl,
			type: 'POST',
			data: {
				action: 'isigestsyncapi_test_connection',
				nonce: isigestsyncapi.nonce
			},
			success: function (response) {
				if (response.success) {
					showNotice('success', 'Connessione API verificata con successo');
				} else {
					showNotice('error', response.data.message || 'Errore di connessione API');
				}
			},
			error: function () {
				showNotice('error', 'Errore durante il test della connessione');
			},
			complete: function () {
				$button.prop('disabled', false);
			}
		});
	});

	function showNotice(type, message) {
		var noticeClass = 'notice-' + (type === 'success' ? 'success' : 'error');
		var $notice = $(
			'<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>'
		);

		$('.isi-settings-wrap').prepend($notice);

		setTimeout(function () {
			$notice.fadeOut(function () {
				$(this).remove();
			});
		}, 3000);
	}
});
