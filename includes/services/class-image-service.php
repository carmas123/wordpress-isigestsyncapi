<?php
/**
 * Gestione delle immagini dei prodotti
 *
 * @package    ISIGestSyncAPI
 * @subpackage Services
 * @author     Massimo Caroccia & Claude
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Services;

use ISIGestSyncAPI\Core\Utilities;
use ISIGestSyncAPI\Core\ISIGestSyncApiException;
use ISIGestSyncAPI\Core\ISIGestSyncApiBadRequestException;

/**
 * Classe ImageService per la gestione delle immagini.
 *
 * @since 1.0.0
 */
class ImageService {
	/**
	 * Gestisce l'upload di un'immagine per un prodotto.
	 *
	 * @param array $data I dati dell'immagine da gestire.
	 * @return array
	 * @throws ISIGestSyncApiException Se si verifica un errore durante l'upload.
	 */
	public function handleImageUpload($data) {
		global $wpdb;

		try {
			// Troviamo il prodotto dallo SKU
			$product_data = $this->findProductBySku($data['sku']);
			if (!$product_data) {
				throw new ISIGestSyncApiBadRequestException('Prodotto non trovato');
			}

			$product_id = $product_data['id_product'];
			$variation_id = $product_data['id_product_attribute'];

			// Verifichiamo l'URL dell'immagine
			if (!Utilities::isValidUrl($data['image_url'])) {
				throw new ISIGestSyncApiBadRequestException('URL immagine non valido');
			}

			// Scarichiamo l'immagine
			$temp_file = $this->downloadImage($data['image_url']);

			// Prepariamo l'array del file
			$file_array = [
				'name' => Utilities::sanitizeFilename(basename($data['image_url'])),
				'tmp_name' => $temp_file,
			];

			// Carichiamo il file nella libreria media
			$attachment_id = $this->uploadToMediaLibrary($file_array, $product_id);

			// Se è una variante, impostiamo l'immagine sulla variante
			if ($variation_id) {
				$this->setVariationImage($variation_id, $attachment_id);
			} else {
				// Altrimenti impostiamo l'immagine sul prodotto principale
				$this->setProductImage($product_id, $attachment_id, isset($data['is_main']));
			}

			return [
				'success' => true,
				'message' => 'Immagine aggiornata con successo',
				'attachment_id' => $attachment_id,
				'url' => wp_get_attachment_url($attachment_id),
			];
		} catch (\Exception $e) {
			// Pulizia in caso di errore
			if (isset($temp_file) && file_exists($temp_file)) {
				@unlink($temp_file);
			}
			throw new ISIGestSyncApiException(
				'Errore durante la gestione dell\'immagine: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Trova un prodotto tramite SKU.
	 *
	 * @param string $sku Lo SKU del prodotto.
	 * @return array|null
	 */
	private function findProductBySku($sku) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id_product, id_product_attribute 
				FROM {$wpdb->prefix}isi_api_product 
				WHERE codice = %s",
				$sku,
			),
			ARRAY_A,
		);
	}

	/**
	 * Scarica un'immagine da un URL.
	 *
	 * @param string $url L'URL dell'immagine.
	 * @return string Il percorso del file temporaneo.
	 * @throws ISIGestSyncApiException Se si verifica un errore durante il download.
	 */
	private function downloadImage($url) {
		$temp_file = download_url($url);

		if (is_wp_error($temp_file)) {
			throw new ISIGestSyncApiException(
				'Errore nel download dell\'immagine: ' . $temp_file->get_error_message(),
			);
		}

		return $temp_file;
	}

	/**
	 * Carica un file nella libreria media di WordPress.
	 *
	 * @param array   $file_array L'array con i dati del file.
	 * @param integer $product_id L'ID del prodotto.
	 * @return integer L'ID dell'attachment creato.
	 * @throws ISIGestSyncApiException Se si verifica un errore durante l'upload.
	 */
	private function uploadToMediaLibrary($file_array, $product_id) {
		// Verifichiamo il tipo di file
		$filetype = wp_check_filetype(basename($file_array['name']), null);
		if (!$filetype['type'] || !in_array($filetype['type'], $this->getAllowedMimeTypes())) {
			throw new ISIGestSyncApiException('Tipo di file non supportato');
		}

		$attachment_id = media_handle_sideload($file_array, $product_id);

		if (is_wp_error($attachment_id)) {
			throw new ISIGestSyncApiException(
				'Errore nel caricamento dell\'immagine: ' . $attachment_id->get_error_message(),
			);
		}

		return $attachment_id;
	}

	/**
	 * Imposta l'immagine per una variante.
	 *
	 * @param integer $variation_id  L'ID della variante.
	 * @param integer $attachment_id L'ID dell'immagine.
	 * @return void
	 */
	private function setVariationImage($variation_id, $attachment_id) {
		$variation = wc_get_product($variation_id);
		if ($variation) {
			$variation->set_image_id($attachment_id);
			$variation->save();
		}
	}

	/**
	 * Imposta l'immagine per un prodotto.
	 *
	 * @param integer $product_id    L'ID del prodotto.
	 * @param integer $attachment_id L'ID dell'immagine.
	 * @param boolean $is_main       Se è l'immagine principale.
	 * @return void
	 */
	private function setProductImage($product_id, $attachment_id, $is_main = false) {
		$product = wc_get_product($product_id);
		if (!$product) {
			return;
		}

		if ($is_main) {
			$product->set_image_id($attachment_id);
		} else {
			$gallery_ids = $product->get_gallery_image_ids();

			// Rimuove l'immagine dalla galleria se era già presente
			$gallery_ids = array_diff($gallery_ids, [$attachment_id]);

			// Aggiunge la nuova immagine alla galleria
			$gallery_ids[] = $attachment_id;

			$product->set_gallery_image_ids($gallery_ids);
		}

		$product->save();
	}

	/**
	 * Ritorna i tipi MIME consentiti per le immagini.
	 *
	 * @return array
	 */
	private function getAllowedMimeTypes() {
		return ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
	}

	/**
	 * Ottimizza un'immagine dopo il caricamento.
	 *
	 * @param integer $attachment_id L'ID dell'immagine.
	 * @return void
	 */
	private function optimizeImage($attachment_id) {
		// Genera le dimensioni delle miniature
		$metadata = wp_generate_attachment_metadata(
			$attachment_id,
			get_attached_file($attachment_id),
		);
		wp_update_attachment_metadata($attachment_id, $metadata);

		// Se disponibile un servizio di ottimizzazione immagini, lo utilizziamo qui
		do_action('isigestsyncapi_optimize_image', $attachment_id);
	}

	/**
	 * Rimuove un'immagine dal prodotto e dalla libreria media.
	 *
	 * @param integer $product_id    L'ID del prodotto.
	 * @param integer $attachment_id L'ID dell'immagine.
	 * @return boolean
	 */
	public function removeImage($product_id, $attachment_id) {
		$product = wc_get_product($product_id);
		if (!$product) {
			return false;
		}

		// Se è l'immagine principale, la rimuoviamo
		if ($product->get_image_id() === $attachment_id) {
			$product->set_image_id('');
		}

		// Rimuoviamo dalla galleria
		$gallery_ids = $product->get_gallery_image_ids();
		$gallery_ids = array_diff($gallery_ids, [$attachment_id]);
		$product->set_gallery_image_ids($gallery_ids);

		$product->save();

		// Eliminiamo l'attachment
		return wp_delete_attachment($attachment_id, true);
	}
}
