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
	function handleCommandButtonClick(command, confirmMessage) {
		return function (e) {
			e.preventDefault();
			const message = confirmMessage || "Confermi l'esecuzione del comando?";
			if (!confirm(message)) {
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
					$button.text(originalButtonText).prop('disabled', false);
				}
			});
		};
	}

	// Associazione degli handler ai pulsanti
	$('#isi_customers_set_all_as_exported_button').on(
		'click',
		handleCommandButtonClick('customers_setallasexported')
	);
	$('#isi_orders_set_all_as_exported_button').on(
		'click',
		handleCommandButtonClick('orders_setallasexported')
	);
	$('#isi_products_clear_exported_history_button').on(
		'click',
		handleCommandButtonClick('products_clearexportedhistory')
	);
	$('#isi_advanced_danger_zone_delete_products_association_button').on(
		'click',
		handleCommandButtonClick('advanced_danger_zone_delete_products_association')
	);
	$('#isi_advanced_danger_zone_draft_all_catalog_button').on(
		'click',
		handleCommandButtonClick(
			'advanced_danger_zone_draft_all_catalog',
			'Questa operazione metterà in bozza tutti i prodotti e le varianti sincronizzati da ISIGest. Verranno riattivati solo quelli inviati dalla prossima sincronizzazione. Continuare?'
		)
	);

	var ISIGestSyncAPI_OrphanImages = (function () {
		var scanData = null;
		var isDeleting = false;

		var $container = $('#isi_orphan_images_cleanup');
		var $scanButton = $('#isi_orphan_images_scan_button');
		var $deleteButton = $('#isi_orphan_images_delete_button');
		var $cancelButton = $('#isi_orphan_images_cancel_button');
		var $report = $('#isi_orphan_images_report');
		var $summary = $('#isi_orphan_images_summary');
		var $previewBody = $('#isi_orphan_images_preview_body');
		var $scanProgress = $('#isi_orphan_images_scan_progress');
		var $progress = $('#isi_orphan_images_progress');
		var $progressFill = $('#isi_orphan_images_progress_fill');
		var $progressLabel = $('#isi_orphan_images_progress_label');

		function formatNumber(value) {
			return Number(value || 0).toLocaleString('it-IT');
		}

		function reasonLabel(reason) {
			if (reason === 'solo_prodotti_cestino') {
				return 'Solo prodotti nel cestino';
			}
			return 'Nessun riferimento attivo';
		}

		function setButtonsDisabled(disabled) {
			$scanButton.prop('disabled', disabled);
			$deleteButton.prop('disabled', disabled || !scanData || !scanData.orphan_count);
		}

		function renderSummaryCard(label, value, highlight) {
			var $card = $('<div class="isi-orphan-images-stat-card">');
			if (highlight) {
				$card.addClass('is-highlight');
			}
			$card.append($('<span class="isi-orphan-images-stat-label">').text(label));
			$card.append($('<strong class="isi-orphan-images-stat-value">').text(value));
			return $card;
		}

		function renderReport(data) {
			scanData = data;
			$summary.empty();
			$summary.append(
				renderSummaryCard('Immagini ISIGest totali', formatNumber(data.total_isigest))
			);
			$summary.append(
				renderSummaryCard('Immagini orfane', formatNumber(data.orphan_count), true)
			);
			$summary.append(
				renderSummaryCard(
					'Solo prodotti nel cestino',
					formatNumber(data.orphan_only_trash || 0)
				)
			);
			$summary.append(
				renderSummaryCard('Senza riferimento', formatNumber(data.orphan_no_reference || 0))
			);

			var sizeLabel = data.orphan_size_is_estimate
				? 'Spazio stimato recuperabile (appross.)'
				: 'Spazio stimato recuperabile';
			$summary.append(renderSummaryCard(sizeLabel, data.orphan_size_human || '0 B'));

			$previewBody.empty();
			if (data.preview && data.preview.length) {
				data.preview.forEach(function (row) {
					$previewBody.append(
						$('<tr>').append(
							$('<td>').text(row.id),
							$('<td>').text(row.filename || ''),
							$('<td>').text(row.isigest_sku || ''),
							$('<td>').text(row.post_parent || 0),
							$('<td>').text(reasonLabel(row.reason))
						)
					);
				});
			} else {
				$previewBody.append(
					$('<tr>').append($('<td colspan="5">').text('Nessuna immagine orfana trovata.'))
				);
			}

			$report.show();
			setButtonsDisabled(false);

			$('html, body').animate(
				{
					scrollTop: Math.max($container.offset().top - 40, 0)
				},
				300
			);
		}

		function updateProgress(processed, total) {
			var percent = total > 0 ? Math.round((processed / total) * 100) : 100;
			$progressFill.css('width', percent + '%');
			$progressLabel.text(processed + ' / ' + total + ' elaborate');
		}

		function runDeleteBatch() {
			return $.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'isigestsyncapi_orphan_images_delete_batch',
					nonce: isigestsyncapi.nonce
				}
			});
		}

		function deleteAllBatches() {
			if (!isDeleting) {
				return;
			}

			runDeleteBatch()
				.done(function (response) {
					if (!response.success) {
						ISIGestSyncAPI_ShowNotice(
							'error',
							(response.data && response.data.message) ||
								"Errore durante l'eliminazione"
						);
						isDeleting = false;
						$cancelButton.hide();
						setButtonsDisabled(false);
						return;
					}

					var data = response.data;
					updateProgress(data.processed, data.total);

					if (data.errors && data.errors.length) {
						ISIGestSyncAPI_ShowNotice(
							'error',
							'Errori parziali: ' + data.errors.join('; ')
						);
					}

					if (data.done) {
						isDeleting = false;
						$cancelButton.hide();
						setButtonsDisabled(false);
						ISIGestSyncAPI_ShowNotice(
							'success',
							'Eliminazione completata: ' + data.processed + ' immagini elaborate'
						);
						scanData = null;
						$deleteButton.prop('disabled', true);
						return;
					}

					deleteAllBatches();
				})
				.fail(function () {
					ISIGestSyncAPI_ShowNotice('error', 'Si è verificato un problema');
					isDeleting = false;
					$cancelButton.hide();
					setButtonsDisabled(false);
				});
		}

		function init() {
			if (!$scanButton.length) {
				return;
			}

			$scanButton.on('click', function (e) {
				e.preventDefault();
				if (isDeleting) {
					return;
				}

				var originalText = $scanButton.text();
				setButtonsDisabled(true);
				$scanButton.text('Analisi in corso...');
				$report.hide();
				$progress.hide();
				$scanProgress.show();

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					timeout: 300000,
					data: {
						action: 'isigestsyncapi_orphan_images_scan',
						nonce: isigestsyncapi.nonce
					},
					success: function (response) {
						if (response.success) {
							renderReport(response.data);
							ISIGestSyncAPI_ShowNotice(
								'success',
								'Analisi completata: ' +
									formatNumber(response.data.orphan_count) +
									' immagini orfane'
							);
						} else {
							ISIGestSyncAPI_ShowNotice(
								'error',
								(response.data && response.data.message) ||
									"Errore durante l'analisi"
							);
							setButtonsDisabled(false);
						}
					},
					error: function (xhr, status) {
						var message =
							status === 'timeout'
								? 'Analisi troppo lunga: riprova o aumenta il timeout PHP del server'
								: 'Si è verificato un problema';
						ISIGestSyncAPI_ShowNotice('error', message);
						setButtonsDisabled(false);
					},
					complete: function () {
						$scanButton.text(originalText);
						$scanProgress.hide();
					}
				});
			});

			$deleteButton.on('click', function (e) {
				e.preventDefault();
				if (!scanData || !scanData.orphan_count || isDeleting) {
					return;
				}

				var confirmMessage =
					'Eliminare ' +
					formatNumber(scanData.orphan_count) +
					' immagini orfane (' +
					(scanData.orphan_size_human || '0 B') +
					")? L'operazione è irreversibile.";
				if (!confirm(confirmMessage)) {
					return;
				}

				isDeleting = true;
				setButtonsDisabled(true);
				$cancelButton.show();
				$progress.show();
				updateProgress(0, scanData.orphan_count);
				deleteAllBatches();
			});

			$cancelButton.on('click', function (e) {
				e.preventDefault();
				if (!isDeleting) {
					return;
				}

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'isigestsyncapi_orphan_images_cancel',
						nonce: isigestsyncapi.nonce
					},
					complete: function () {
						isDeleting = false;
						$cancelButton.hide();
						$progress.hide();
						setButtonsDisabled(false);
						ISIGestSyncAPI_ShowNotice('success', 'Operazione annullata');
					}
				});
			});
		}

		return {
			init: init
		};
	})();

	ISIGestSyncAPI_OrphanImages.init();

	// Gestione click sulla cella di esportazione
	$(document).on('click', '.isigest-export-status', function (e) {
		e.preventDefault();
		e.stopPropagation(); // Ferma la propagazione dell'evento

		const $statusCell = $(this);
		const orderId = $statusCell.data('order-id');
		const $icon = $statusCell.find('.dashicons');

		// Disabilita temporaneamente il click
		$statusCell.css('pointer-events', 'none');

		// Aggiungi classe di caricamento
		$icon
			.removeClass('dashicons-yes-alt dashicons-no-alt')
			.addClass('dashicons-update spinning')
			.css('color', '#72777c'); // Colore grigio standard di WordPress

		$.ajax({
			url: isigestsyncapi.ajaxurl,
			type: 'POST',
			data: {
				action: 'isigestsyncapi_toggle_export_status',
				order_id: orderId,
				nonce: isigestsyncapi.nonce
			},
			success: function (response) {
				if (response.success) {
					// Aggiorna l'icona in base al nuovo stato
					$icon.removeClass('dashicons-update spinning');
					if (response.data.is_exported) {
						$icon
							.addClass('dashicons-yes-alt')
							.css('color', '#2ea2cc')
							.attr('title', 'Ordine esportato');
					} else {
						$icon
							.addClass('dashicons-no-alt')
							.css('color', '#dc3232')
							.attr('title', 'Ordine non esportato');
					}
				} else {
					alert("Errore durante l'aggiornamento dello stato: " + response.data.message);
					$icon
						.removeClass('dashicons-update spinning')
						.addClass(
							$statusCell.hasClass('exported')
								? 'dashicons-yes-alt'
								: 'dashicons-no-alt'
						);
				}
			},
			error: function () {
				alert('Errore di comunicazione con il server');
				$icon
					.removeClass('dashicons-update spinning')
					.addClass(
						$statusCell.hasClass('exported') ? 'dashicons-yes-alt' : 'dashicons-no-alt'
					);
			},
			complete: function () {
				// Riabilita il click
				$statusCell.css('pointer-events', 'auto');
			}
		});
	});
});
