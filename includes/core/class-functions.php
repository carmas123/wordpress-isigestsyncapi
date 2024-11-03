<?php

/**
 * Prepara gli attributi di tipo funzionalità per un prodotto.
 *
 * Questo metodo crea un array di attributi per una singola funzionalità del prodotto.
 * Può essere utilizzato per attributi che non sono varianti, come ad esempio la marca o altre caratteristiche fisse.
 *
 * @param string $value Il valore dell'attributo.
 * @param string $label L'etichetta dell'attributo.
 * @param string|null $key La chiave dell'attributo. Se non specificata, verrà generata dal label.
 *
 * @return array Un array contenente l'attributo preparato.
 */
function isigestsyncapi_prepare_feature_attribute($value, $label, $key, $hidden = false) {
	$attributes = [];

	// Impostiamo la chiave
	$key ??= wc_attribute_taxonomy_name($label);

	$attributes[$key] = [
		'variant' => false,
		'label' => $label,
		'value' => $value,
		'hidden' => (bool) $hidden,
	];

	return $attributes;
}
