// assets/js/editor.js
jQuery(document).ready(function ($) {
	const $editor = $('#isi_custom_functions_editor');
	if ($editor.length === 0) {
		return; // Esci se l'elemento non esiste
	}

	// Inizializza CodeMirror
	var editor = wp.codeEditor.initialize($('#isi_custom_functions_editor'), {
		codemirror: {
			mode: 'php',
			lineNumbers: true,
			lineWrapping: true,
			styleActiveLine: true,
			matchBrackets: true,
			autoCloseBrackets: true,
			autoCloseTags: true,
			foldGutter: true,
			gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
			extraKeys: {
				F11: function (cm) {
					cm.setOption('fullScreen', !cm.getOption('fullScreen'));
				},
				Esc: function (cm) {
					if (cm.getOption('fullScreen')) cm.setOption('fullScreen', false);
				},
				'Ctrl-S': function (cm) {
					$('#isi_custom_functions_save').click();
					return false;
				},
				'Cmd-S': function (cm) {
					$('#isi_custom_functions_save').click();
					return false;
				}
			},
			indentUnit: 4,
			indentWithTabs: true,
			scrollbarStyle: 'overlay',
			theme: 'default',
			autocomplete: true
		}
	});

	// Ottieni l'istanza di CodeMirror
	var codemirror = editor.codemirror;

	// Gestisci il salvataggio
	$('#isi_custom_functions_save').on('click', function (e) {
		e.preventDefault();

		const $button = $(this);
		const originalButtonText = $button.text();
		var code = codemirror.getValue();

		$button.text('Salvataggio...').prop('disabled', true);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'isigestsyncapi_save_custom_functions',
				nonce: isigestsyncapi.nonce,
				code: code
			},
			success: function (response) {
				if (response.success) {
					ISIGestSyncAPI_ShowNotice('success', response.data.message);
				} else {
					ISIGestSyncAPI_ShowNotice(
						'error',
						response.data.message ||
							'Errore durante il salvataggio delle funzioni personalizzate'
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
});
