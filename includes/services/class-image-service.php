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

use ISIGestSyncAPI\Core\ConfigHelper;
use ISIGestSyncAPI\Core\ISIGestSyncApiNotFoundException;
use ISIGestSyncAPI\Core\Utilities;
use ISIGestSyncAPI\Core\ISIGestSyncApiException;
use ISIGestSyncAPI\Core\ISIGestSyncApiBadRequestException;

/**
 * Classe ImageService per la gestione delle immagini.
 *
 * @since 1.0.0
 */
class ImageService extends BaseService {
	/**
	 * Handler per lo status dei prodotti.
	 *
	 * @var ProductStatusHandler
	 */
	private $status_handler;

	/**
	 * @var bool $use_woo_variation_gallery Indica se è installato il plugin Woo Variation Gallery.
	 */
	private $use_woo_variation_gallery = false;

	/**
	 * Costruttore.
	 */
	public function __construct() {
		parent::__construct();

		$this->status_handler = new ProductStatusHandler();

		// Verifichiamo se è installato il plugin Woo Variation Gallery.
		$this->use_woo_variation_gallery = \in_array(
			'woo-variation-gallery/woo-variation-gallery.php',
			apply_filters('active_plugins', get_option('active_plugins')),
		);
	}

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
	 * @param string $sku Lo SKU del prodotto/variante.
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
		$sku,
		$product_id,
		$variation_id,
		$filename,
		$attachment,
		$is_main
	) {
		try {
			// Immagine non principale di una variante??
			$is_not_main_variant = $variation_id && !$is_main;

			// Quando è un'immagine della variante e non è main allora la aggiungiamo solo alla galleria del prodotto
			if ($is_not_main_variant && !$this->use_woo_variation_gallery) {
				return ['id' => 0];
			}

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

			// Ottieni l'ID del prodotto/variante
			$post_id = $variation_id ?: $product_id;

			// Carica il file nella libreria media
			$attachment_id = $this->uploadToMediaLibrary($sku, $file_array, $post_id);

			// Gestisci l'associazione dell'immagine
			if ($variation_id && !$is_not_main_variant) {
				$this->setVariationImage($variation_id, $attachment_id);
				// Aggiorniamo l'attributo di default in modo da aggiornare anche l'immagine di copertina del prodotto
				ProductService::updateDefaultVariant($product_id);
			} else {
				if ($variation_id) {
					// Aggiungiamo l'immagine alla galleria della variante
					$current_gallery_images = get_post_meta(
						$variation_id,
						'woo_variation_gallery_images',
						true,
					);
					if (!empty($current_gallery_images)) {
						$gallery_image_ids = array_merge($current_gallery_images, [$attachment_id]);
					} else {
						$gallery_image_ids = [$attachment_id];
					}

					// Verifichiamo che le immagini esistano ancora
					$gallery_image_ids = array_filter($gallery_image_ids, function ($id) {
						return wp_attachment_is_image($id);
					});
					$gallery_image_ids = array_values($gallery_image_ids);

					update_post_meta(
						$variation_id,
						'woo_variation_gallery_images',
						$gallery_image_ids,
					);
				} else {
					// Aggiungiamo l'immagine al prodotto
					$this->setProductImage($product_id, $attachment_id, $is_main);
				}
			}

			// Pulisci il file temporaneo
			if (file_exists($temp_file)) {
				@unlink($temp_file);
			}

			// Verifichiamo l'attivazione/disattivazione del prodotto se necessario
			if ($this->config->get('products_disable_without_image')) {
				if ($variation_id) {
					$this->status_handler->checkAndUpdateProductStatus($variation_id, true);
				} else {
					$this->status_handler->checkAndUpdateProductStatus($product_id, false);
				}
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
	 * @param string $sku Lo SKU del prodotto/variante.
	 * @param array   $file_array L'array con i dati del file.
	 * @param integer $post_id L'ID del prodotto/variante.
	 * @return integer L'ID dell'attachment creato.
	 * @throws ISIGestSyncApiException Se si verifica un errore durante l'upload.
	 */
	private function uploadToMediaLibrary($sku, $file_array, $post_id) {
		// Carica le dipendenze solo quando servono
		$this->loadMediaDependencies();

		// Verifichiamo il tipo di file
		$filetype = wp_check_filetype(basename($file_array['name']), null);
		if (!$filetype['type'] || !in_array($filetype['type'], $this->getAllowedMimeTypes())) {
			throw new ISIGestSyncApiException('Tipo di file non supportato');
		}

		// Carichiamo l'immagine nella libreria media con i dati ISIGest
		$attachment_id = media_handle_sideload($file_array, $post_id);

		if (is_wp_error($attachment_id)) {
			throw new ISIGestSyncApiException(
				'Errore nel caricamento dell\'immagine: ' . $attachment_id->get_error_message(),
			);
		} elseif ($sku) {
			// Aggiungiamo i dati aggiuntivi all'attachment
			update_post_meta($attachment_id, 'isigest_image', true);
			update_post_meta($attachment_id, 'isigest_sku', $sku);
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
			// Imposta l'immagine della variante
			$variation->set_image_id($attachment_id);

			// Assicuriamoci che l'immagine sia associata correttamente alla variante
			update_post_meta($variation_id, '_thumbnail_id', $attachment_id);

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
			$gallery_ids = $product->get_gallery_image_ids('edit');

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
		// Costruisci la meta_query base
		$meta_query = [
			'relation' => 'OR',
			// Cerca nelle immagini in evidenza
			[
				'key' => '_thumbnail_id',
				'value' => $image_id,
				'compare' => '=',
			],
			// Cerca nelle gallerie prodotto
			[
				'key' => '_product_image_gallery',
				'value' => $image_id,
				'compare' => 'LIKE',
			],
		];

		// Se è attivo Woo Variation Gallery, cerca anche nelle gallerie delle varianti
		if ($this->use_woo_variation_gallery) {
			$meta_query[] = [
				'key' => 'woo_variation_gallery_images',
				'value' => $image_id,
				'compare' => 'LIKE',
			];
		}

		// Trova tutti i post/prodotti che usano questa immagine
		$posts_using_image = get_posts([
			'post_type' => ['product', 'product_variation'],
			'meta_query' => $meta_query,
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

				// Rimuovi dalla galleria prodotto
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

				// Rimuovi dalla galleria Woo Variation Gallery (varianti)
				if ($this->use_woo_variation_gallery) {
					$variation_gallery = get_post_meta(
						$post_id,
						'woo_variation_gallery_images',
						true,
					);
					if (!empty($variation_gallery) && is_array($variation_gallery)) {
						if (in_array($image_id, $variation_gallery)) {
							$variation_gallery = array_diff($variation_gallery, [$image_id]);
							$variation_gallery = array_values($variation_gallery);
							update_post_meta(
								$post_id,
								'woo_variation_gallery_images',
								$variation_gallery,
							);
							$removed_count++;
						}
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
	 * @param integer|null $variation_id ID della variante (opzionale).
	 * @return array Array di oggetti immagine.
	 * @throws ISIGestSyncApiNotFoundException Se il prodotto non viene trovato.
	 */
	public function getProductImages($product_id, $variation_id = null) {
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

			if ($this->use_woo_variation_gallery) {
				// Immagini della galleria della variante
				$variation_gallery_images = get_post_meta(
					$variation_id,
					'woo_variation_gallery_images',
					true,
				);
				$gallery = [];
				if (\is_array($variation_gallery_images)) {
					foreach ($variation_gallery_images as $variation_gallery_image) {
						$gallery[] = [
							'id' => (int) $variation_gallery_image,
						];
					}
				}
				$variant_single_image = [];
				$variation_image_id = $variation->get_image_id('edit');
				if ($variation_image_id) {
					$variant_single_image[] = [
						'id' => (int) $variation_image_id,
					];
				}
				$images = array_merge($gallery, $variant_single_image);
			} else {
				// Immagine in evidenza della variante (SINGOLA IMMAGINE)
				$variation_image_id = $variation->get_image_id('edit');
				if ($variation_image_id) {
					$images[] = [
						'id' => (int) $variation_image_id,
					];
				}
			}
		} else {
			// Immagine in evidenza del prodotto
			$product_image_id = $product->get_image_id('edit');
			if ($product_image_id) {
				$images[] = [
					'id' => (int) $product_image_id,
				];
			}

			// Immagini della galleria
			$gallery_image_ids = $product->get_gallery_image_ids('edit');
			foreach ($gallery_image_ids as $gallery_image_id) {
				$images[] = [
					'id' => (int) $gallery_image_id,
				];
			}
		}

		return $images;
	}
}
