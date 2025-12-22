<?php

/**
 * Prepara gli attributi per un prodotto.
 *
 * Questa funzione crea un array di attributi per una singola funzionalità del prodotto.
 *
 * @param string $value Il valore dell'attributo.
 * @param string $label L'etichetta dell'attributo.
 * @param string|null $key La chiave dell'attributo. Se non specificata, verrà generata dal label.
 * @param bool $hidden Determina se l'attributo deve essere nascosto nell'interfaccia utente. Default: false.
 * @param bool $archive Determina se l'attributo deve essere usato negli archivi. Default: true.
 * @param bool $variant Determina se l'attributo è una variante. Default: false.
 *
 * @return array Un array contenente l'attributo preparato.
 */
function isigestsyncapi_prepare_attribute(
	$value,
	$label,
	$key,
	$hidden = false,
	$archive = true,
	$variant = false
) {
	$attributes = [];

	// Impostiamo la chiave
	$key ??= wc_attribute_taxonomy_name($label);

	$attributes[$key] = [
		'variant' => (bool) $variant,
		'label' => $label,
		'value' => $value,
		'hidden' => (bool) $hidden,
		'archive' => (bool) $archive,
	];

	return $attributes;
}
