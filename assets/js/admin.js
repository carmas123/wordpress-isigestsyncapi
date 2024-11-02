function ISIGestSyncAPI_ShowNotice(type, message) {
	var icon = type === 'success' ? '✓' : '⚠';
	var backgroundColor = type === 'success' ? '#4CAF50' : '#f44336';

	const $ = jQuery;

	var $notification = $('<div class="isi-notification">').css({
		'background-color': backgroundColor,
		color: 'white',
		padding: '15px 20px',
		'margin-bottom': '10px',
		'border-radius': '4px',
		'box-shadow': '0 2px 5px rgba(0,0,0,0.2)',
		display: 'flex',
		'align-items': 'center',
		'justify-content': 'space-between',
		opacity: '0',
		transform: 'translateX(50px)',
		transition: 'all 0.3s ease'
	});

	var $content = $('<div class="notification-content">')
		.css({
			display: 'flex',
			'align-items': 'center',
			gap: '10px'
		})
		.appendTo($notification);

	$('<span class="notification-icon">')
		.text(icon)
		.css({
			'font-size': '20px',
			'font-weight': 'bold'
		})
		.appendTo($content);

	$('<span class="notification-message">').text(message).appendTo($content);

	var $closeButton = $('<button type="button" class="notification-close">')
		.html('&times;')
		.css({
			background: 'none',
			border: 'none',
			color: 'white',
			'font-size': '20px',
			cursor: 'pointer',
			padding: '0 5px',
			'margin-left': '10px'
		})
		.appendTo($notification);

	$closeButton.on('click', function () {
		ISIGestSyncAPI_HideNotification($notification);
	});

	$('#isi-notifications').prepend($notification);

	// Anima l'entrata
	setTimeout(function () {
		$notification.css({
			opacity: '1',
			transform: 'translateX(0)'
		});
	}, 10);

	// Rimuovi automaticamente dopo 5 secondi
	setTimeout(function () {
		ISIGestSyncAPI_HideNotification($notification);
	}, 5000);
}

function ISIGestSyncAPI_HideNotification($notification) {
	const $ = jQuery;

	$notification
		.css({
			opacity: '0',
			transform: 'translateX(50px)'
		})
		.one('transitionend', function () {
			$(this).remove();
		});
}

jQuery(document).ready(function ($) {
	// Creiamo il container per le notifiche se non esiste
	if (!$('#isi-notifications').length) {
		$('<div id="isi-notifications"></div>')
			.css({
				position: 'fixed',
				top: '32px', // Considera la admin bar di WordPress
				right: '20px',
				'z-index': '9999',
				width: '300px'
			})
			.appendTo('body');
	}

	// Gestiamo il salvataggio delle impostazioni
	$('form[action="options.php"]').on('submit', function (e) {
		e.preventDefault();

		var $form = $(this);
		var $submitButton = $('#submit');
		var originalButtonText = $submitButton.text();

		// Disabilita il pulsante durante il salvataggio
		$submitButton.text('Salvataggio...').prop('disabled', true);

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
					ISIGestSyncAPI_ShowNotice('success', response.data.message);
				} else {
					ISIGestSyncAPI_ShowNotice(
						'error',
						response.data.message || 'Errore durante il salvataggio'
					);
				}
			},
			error: function () {
				ISIGestSyncAPI_ShowNotice('error', 'Si è verificato un problema');
			},
			complete: function () {
				// Ripristina il testo originale del pulsante
				$submitButton.text(originalButtonText).prop('disabled', false);
			}
		});
	});

	// Handler per il pulsante "Azzera log"
	$('#isi_debug_log_clear').on('click', function (e) {
		e.preventDefault();
		if (!confirm('Sei sicuro di voler azzerare il log?')) {
			return;
		}

		const $button = $(this);
		const originalButtonText = $button.text();

		$button.text('Attendere...').prop('disabled', true);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'isigestsyncapi_clear_log',
				nonce: isigestsyncapi.nonce
			},
			success: function (response) {
				if (response.success) {
					$('#debug_log').val(response.data.content);
					ISIGestSyncAPI_ShowNotice('success', 'Log azzerato con successo');
				} else {
					ISIGestSyncAPI_ShowNotice(
						'error',
						response.data.message || "Errore durante l'azzeramento del log"
					);
				}
			},
			error: function () {
				ISIGestSyncAPI_ShowNotice('error', 'Si è verificato un problema');
			},
			complete: function () {
				// Ripristina il testo originale del pulsante
				$button.text(originalButtonText).prop('disabled', false);
			}
		});
	});

	// Handler per il pulsante "Aggiorna Log"
	$('#isi_debug_log_refresh').on('click', function (e) {
		e.preventDefault();

		const $button = $(this);
		const originalButtonText = $button.text();

		$button.text('Attendere...').prop('disabled', true);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'isigestsyncapi_refresh_log',
				nonce: isigestsyncapi.nonce
			},
			success: function (response) {
				if (response.success) {
					$('#debug_log').val(response.data.content);
				} else {
					ISIGestSyncAPI_ShowNotice(
						'error',
						response.data.message || "Errore durante l'aggiornamento del log"
					);
				}
			},
			error: function () {
				ISIGestSyncAPI_ShowNotice('error', 'Si è verificato un problema');
			},
			complete: function () {
				// Ripristina il testo originale del pulsante
				$button.text(originalButtonText).prop('disabled', false);
			}
		});
	});

	// Handler per il pulsante "Imposta tutti i clienti come esportati"
	// Funzione generica per gestire i click sui pulsanti per i comandi
	function handleCommandButtonClick(command) {
		return function (e) {
			e.preventDefault();
			if (!confirm("Confermi l'esecuzione del comando?")) {
				return;
			}

			const $button = $(this);
			const originalButtonText = $button.text();

			$button.text('Attendere...').prop('disabled', true);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'isigestsyncapi_commands',
					nonce: isigestsyncapi.nonce,
					command: command
				},
				success: function (response) {
					if (response.success) {
						$('#debug_log').val(response.data.content);
					} else {
						ISIGestSyncAPI_ShowNotice(
							'error',
							response.data.message || "Errore durante l'esecuzione del comando"
						);
					}
				},
				error: function () {
					ISIGestSyncAPI_ShowNotice('error', 'Si è verificato un problema');
				},
				complete: function () {
					$button.text(originalButtonText).prop('disabled', false);
				}
			});
		};
	}

	// Associazione degli handler ai pulsanti
	$('#isi_customers_set_all_as_exported_button').on(
		'click',
		handleCommandButtonClick('customers_set_all_as_exported')
	);
	$('#isi_orders_set_all_as_exported_button').on(
		'click',
		handleCommandButtonClick('orders_set_all_as_exported')
	);
});
