<?php
spl_autoload_register(function ($class) {
	// Verifica se la classe appartiene al nostro namespace
	$prefix = 'ISIGestSyncAPI\\';
	$base_dir = plugin_dir_path(__FILE__);

	$len = strlen($prefix);
	if (strncmp($prefix, $class, $len) !== 0) {
		return;
	}

	// Ottieni il nome relativo della classe
	$relative_class = substr($class, $len);

	// Sostituisci i separatori di namespace con i separatori di directory
	// e aggiungi .php
	$file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

	// Se il file esiste, richiedilo
	if (file_exists($file)) {
		require $file;
	}
});
