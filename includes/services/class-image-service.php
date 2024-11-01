<?php
/**
 * Gestione delle immagini dei prodotti
 *
 * @package    ISIGestSyncAPI
 * @subpackage Services
 * @author     ISIGest S.r.l.
 * @copyright  2024 ISIGest
 */

namespace ISIGestSyncAPI\Services;

use ISIGestSyncAPI\Core\ISIGestSyncApiNotFoundException;
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
	 * Carica le dipendenze necessarie per la gestione dei media
	 */
	private function loadMediaDependencies() {
		if (!function_exists('media_handle_sideload')) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
	}

	/**
	 * Gestisce l'upload di un'immagine per un prodotto o una sua variante.
	 *
	 * @param integer $product_id ID del prodotto.
	 * @param integer $variation_id ID della variante (opzionale).
	 * @param string $filename Nome del file.
	 * @param string $attachment Contenuto dell'immagine in base64.
	 * @param bool $is_main Immagine principale (Copertina)
	 * @return array Dati dell'immagine caricata.
	 * @throws ISIGestSyncApiException Se si verifica un errore nell'upload.
	 * @throws ISIGestSyncApiBadRequestException Contanuto dei dati non valido.
	 */
	public function handleImageUpload(
		$product_id,
		$variation_id,
		$filename,
		$attachment,
		$is_main
	) {
		try {
			// Decodifica il contenuto base64
			$image_data = base64_decode($attachment);
			if (!$image_data) {
				throw new ISIGestSyncApiBadRequestException('Contenuto base64 non valido');
			}

			// Crea file temporaneo
			$upload_dir = wp_upload_dir();
			$temp_file = tempnam($upload_dir['basedir'], 'isigestsyncapi');
			if (!$temp_file) {
				throw new ISIGestSyncApiException('Impossibile creare il file temporaneo');
			}

			// Salva il contenuto nel file temporaneo
			if (file_put_contents($temp_file, $image_data) === false) {
				throw new ISIGestSyncApiException('Impossibile salvare il file temporaneo');
			}

			// Prepara l'array del file
			$file_array = [
				'name' => Utilities::sanitizeFilename($filename),
				'tmp_name' => $temp_file,
				'type' => mime_content_type($temp_file),
				'error' => 0,
				'size' => filesize($temp_file),
			];

			// Carica il file nella libreria media
			$attachment_id = $this->uploadToMediaLibrary($file_array, $product_id);

			// Gestisci l'associazione dell'immagine
			if ($variation_id) {
				$this->setVariationImage($variation_id, $attachment_id);
			} else {
				$this->setProductImage($product_id, $attachment_id, $is_main);
			}

			// Pulisci il file temporaneo
			if (file_exists($temp_file)) {
				@unlink($temp_file);
			}

			return ['id' => $attachment_id];
		} catch (\Exception $e) {
			// Pulisci il file temporaneo in caso di errore
			if (isset($temp_file) && file_exists($temp_file)) {
				@unlink($temp_file);
			}

			throw new ISIGestSyncApiException(
				'Errore durante la gestione dell\'immagine: ' . $e->getMessage(),
			);
		}
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
		// Carica le dipendenze solo quando servono
		$this->loadMediaDependencies();

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
			throw new ISIGestSyncApiNotFoundException('Prodotto non trovato');
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
		// Carica le dipendenze solo quando servono
		$this->loadMediaDependencies();

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
	 * Rimuove un'immagine dalla libreria media.
	 *
	 * @param integer $image_id    L'ID dell'immagine.
	 * @return boolean
	 */
	public function removeImage($image_id) {
		// Trova tutti i post/prodotti che usano questa immagine
		$posts_using_image = get_posts([
			'post_type' => ['product', 'product_variation'],
			'meta_query' => [
				'relation' => 'OR',
				// Cerca nelle immagini in evidenza
				[
					'key' => '_thumbnail_id',
					'value' => $image_id,
					'compare' => '=',
				],
				// Cerca nelle gallerie
				[
					'key' => '_product_image_gallery',
					'value' => $image_id,
					'compare' => 'LIKE',
				],
			],
			'posts_per_page' => -1,
			'fields' => 'ids', // Restituisce solo gli ID per ottimizzare
		]);

		$removed_count = 0;

		if (!empty($posts_using_image)) {
			foreach ($posts_using_image as $post_id) {
				// Rimuovi da immagine in evidenza
				$thumbnail_id = get_post_thumbnail_id($post_id);
				if ($thumbnail_id == $image_id) {
					delete_post_thumbnail($post_id);
					$removed_count++;
				}

				// Rimuovi dalla galleria
				$gallery = get_post_meta($post_id, '_product_image_gallery', true);
				if (!empty($gallery)) {
					$gallery_array = explode(',', $gallery);
					if (in_array($image_id, $gallery_array)) {
						$gallery_array = array_diff($gallery_array, [$image_id]);
						update_post_meta(
							$post_id,
							'_product_image_gallery',
							implode(',', $gallery_array),
						);
						$removed_count++;
					}
				}
			}
		}

		// Opzionale: elimina l'immagine dal media library
		wp_delete_attachment($image_id, true);

		return $removed_count;
	}

	/**
	 * Ottiene le immagini di un prodotto o di una sua variante.
	 *
	 * @param integer $product_id ID del prodotto.
	 * @param integer $variation_id ID della variante (opzionale).
	 * @return array Array di oggetti immagine.
	 * @throws ISIGestSyncApiNotFoundException Se il prodotto non viene trovato.
	 */
	public function getProductImages($product_id, $variation_id = 0) {
		$product = wc_get_product($product_id);
		if (!$product) {
			throw new ISIGestSyncApiNotFoundException('Prodotto non trovato');
		}

		$images = [];

		if ($variation_id) {
			// Immagini della variante
			$variation = wc_get_product($variation_id);
			if (!$variation) {
				throw new ISIGestSyncApiNotFoundException('Variante non trovata');
			}

			// Immagine in evidenza della variante
			$variation_image_id = $variation->get_image_id();
			if ($variation_image_id) {
				$images[] = [
					'id' => (int) $variation_image_id,
				];
			}
		} else {
			// Immagine in evidenza del prodotto
			$product_image_id = $product->get_image_id();
			if ($product_image_id) {
				$images[] = [
					'id' => (int) $product_image_id,
				];
			}

			// Immagini della galleria
			$gallery_image_ids = $product->get_gallery_image_ids();
			foreach ($gallery_image_ids as $gallery_image_id) {
				$images[] = [
					'id' => (int) $gallery_image_id,
				];
			}
		}

		return $images;
	}
}
