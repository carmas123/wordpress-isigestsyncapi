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
	const codemirror = editor.codemirror;

	codemirror.on('change', function () {
		codemirror.save();
		$editor.trigger('change');
	});

	// Gestisci il salvataggio
	$('form[action="options.php"]').on('submit', function (e) {
		// Sincronizza il contenuto di CodeMirror con il textarea
		codemirror.save();
		$editor.val(codemirror.getValue());
	});
});
