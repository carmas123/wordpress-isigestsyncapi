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
		var originalButtonText = $submitButton.val();

		// Disabilita il pulsante durante il salvataggio
		$submitButton.val('Salvataggio...').prop('disabled', true);

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
					showNotice('success', response.data.message);
				} else {
					showNotice('error', response.data.message || 'Errore durante il salvataggio');
				}
			},
			error: function () {
				showNotice('error', 'Si è verificato un problema');
			},
			complete: function () {
				// Ripristina il testo originale del pulsante
				$submitButton.val(originalButtonText).prop('disabled', false);
			}
		});
	});

	function showNotice(type, message) {
		var icon = type === 'success' ? '✓' : '⚠';
		var backgroundColor = type === 'success' ? '#4CAF50' : '#f44336';

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
			hideNotification($notification);
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
			hideNotification($notification);
		}, 5000);
	}

	function hideNotification($notification) {
		$notification
			.css({
				opacity: '0',
				transform: 'translateX(50px)'
			})
			.one('transitionend', function () {
				$(this).remove();
			});
	}
});
