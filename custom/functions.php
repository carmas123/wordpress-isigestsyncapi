<?php
function isigestsyncapi_isigest_box_price( $price, $box ) {
	$price = (float)$price;
	$box = (float)$box;
	
	if (!empty($box)) {
		return $price * $box;
	} else {
		return $price;
	}
}


function isigestsyncapi_isigest_available_label( $data ) {
	$stock = (int)$data["stock_quantity"];
	$available = (int)$data["available_quantity"];
	
	$result = "Non disponibile";
	if (!empty((int)$stock)) {
		$result = "Ordinabile";
	} else {
		if (!empty((int)$available)) {
     		$result = "In arrivo";
		}
	}
	return $result;
}

function isigestsyncapi_func_product_customfields($product, $data) {
	return [
		"_woopq_quantity" => "overwrite",
		"_woopq_type" => "default",
		"_woopq_min" => (float)$data["package_qty"],
		"_woopq_step" => (float)$data["package_qty"],
		"_woopq_value" => (float)$data["package_qty"],
	];
}

function isigestsyncapi_func_product_attributes($product, $data, $attributes) {
	$package_enabled = (bool)$data["package_enabled"];
	$package_qty = (float)$data["package_qty"];
	
	if ($package_enabled) {
		// Confezione
		$attributes[] = isigestsyncapi_prepare_feature_attribute($package_qty, "Confezione", "pa_confezione");

		// Prezzo per Confezione
		$attributes[] = isigestsyncapi_prepare_feature_attribute(isigestsyncapi_isigest_box_price($data["price"], $package_qty), "Prezzo confezione", "pa_prezzo-confezione");
	}
	
	// Codice a barre
	if (!empty($data["ean13"])) {
		$attributes[] = isigestsyncapi_prepare_feature_attribute($data["ean13"], "Codice a barre", "pa_codice-a-barre");
	}
	
	// Data di Arrivo (presa dai Tags)
	if (!empty($data["tags"])) {
		$attributes[] = isigestsyncapi_prepare_feature_attribute($data["tags"], "Data di arrivo", "pa_data_di_arrivo");
	}
	
	// Descrizione Disponibilità
	$attributes[] = isigestsyncapi_prepare_feature_attribute(isigestsyncapi_isigest_available_label($data), "Disponibilità", "pa_disponibilita", false, false);
	
	return $attributes;
}