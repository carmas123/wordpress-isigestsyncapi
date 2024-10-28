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

	// Converte il nome della classe nel formato corretto
	$parts = explode('\\', $relative_class);
	$last_part = array_pop($parts); // Prendi l'ultima parte (nome della classe)

	// Converti le parti del namespace in minuscolo
	$parts = array_map('strtolower', $parts);

	// Converti il nome della classe in kebab-case
	$file_name = 'class-' . strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $last_part));

	// Ricostruisci il percorso evitando il doppio includes
	$file = rtrim($base_dir, '/') . '/' . implode('/', $parts) . '/' . $file_name . '.php';

	// Se il file esiste, richiedilo
	if (file_exists($file)) {
		require_once $file;
	}
});
