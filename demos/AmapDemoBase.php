<?php
/**
 * Created by PhpStorm.
 * User: Guillaume
 * Date: 20/05/2019
 * Time: 07:40
 */

class AmapDemoBase {
	protected $users = [ '0' => 0 ];
	protected $posts = [ '0' => 0 ];
	protected $taxonomies = [];
	protected $medias = [];

	protected function createPost( $postarr ) {
		return wp_insert_post( $postarr );
	}

	protected function createUser( $userdata ) {
		return wp_insert_user( $userdata );
	}

	protected static function cleanAttachments() {
		$cnt = 0;
		foreach (
			get_posts( [
				'post_type'      => 'attachment',
				'posts_per_page' => - 1,
				'post_status'    => null
			] ) as $post
		) {
			if ( strpos( $post->post_title, 'amp_attach' ) !== false ) {
				wp_delete_attachment( $post->ID, true );
				$cnt += 1;
			}
		}
		echo "<p>Deleted $cnt media</p>";
	}

	public static function dumpTerms( $taxonomy ) {
		$terms = get_terms( $taxonomy,
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			) );
		$ret   = [];
		/** @var WP_Term $term */
		foreach ( $terms as $term ) {
			$ret[] = [
				'id'          => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'parent'      => $term->parent,
			];
		}

		return $ret;
	}

	protected function createTerms( $terms, $taxonomy ) {
		echo "<p>Cleaning terms for $taxonomy</p>";
		$prev_terms = get_terms( $taxonomy,
			array(
				'taxonomy'   => $taxonomy,
				'fields'     => 'ids',
				'hide_empty' => false
			) );
		foreach ( $prev_terms as $value ) {
			wp_delete_term( $value, $taxonomy );
		}

		echo "<p>Inserting terms for $taxonomy</p>";
		$this->taxonomies[ $taxonomy ] = [];
		foreach ( $terms as $term ) {
			$id   = strval( $term['id'] );
			$name = $term['name'];
			unset( $term['id'] );
			unset( $term['name'] );
			$res = wp_insert_term( $name, $taxonomy, $term );
			if ( is_array( $res ) ) {
				$this->taxonomies[ $taxonomy ][ $id ] = $res['term_id'];
			}
		}
	}

	public static function startTransaction() {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
	}

	public static function commitTransaction() {
		global $wpdb;
		$wpdb->query( 'COMMIT' );
	}

	public static function abortTransaction() {
		global $wpdb;
		$wpdb->query( 'ROLLBACK' );
	}

	public static function deleteAutoGeneratedUsers() {
		$query = array(
			'meta_query' => array(
				array(
					'key'     => 'amapress_user_autogen',
					'compare' => 'EXISTS'
				)
			),
		);
		$cnt   = 0;
		foreach ( get_users( $query ) as $user ) {
			wp_delete_user( $user->ID );
			$cnt += 1;
		}
		echo "<p>Deleted $cnt users</p>";

		return $cnt;
	}

	protected static function deletePostsByType( $post_types ) {
		$cnt = 0;
		foreach (
			get_posts( [
				'post_type'      => $post_types,
				'post_status'    => 'all',
				'posts_per_page' => - 1,
			] ) as $post
		) {
			wp_delete_post( $post->ID, true );
			$cnt += 1;
		}
		echo "<p>Deleted $cnt posts</p>";

		return $cnt;
	}

	public static function deleteAutoGeneratedPosts() {
		self::startTransaction();
		$cnt = self::deletePostsByType( [
			AmapressVisite::INTERNAL_POST_TYPE,
			AmapressProduit::INTERNAL_POST_TYPE,
			AmapressAmap_event::INTERNAL_POST_TYPE,
			AmapressAssemblee_generale::INTERNAL_POST_TYPE,
			AmapressRecette::INTERNAL_POST_TYPE,
			AmapressPanier::INTERNAL_POST_TYPE,
			AmapressDistribution::INTERNAL_POST_TYPE,
			AmapressAdhesionPeriod::INTERNAL_POST_TYPE,
			AmapressAdhesion::INTERNAL_POST_TYPE,
			AmapressIntermittence_panier::INTERNAL_POST_TYPE,
			AmapressAmapien_paiement::INTERNAL_POST_TYPE,
			AmapressAdhesion_paiement::INTERNAL_POST_TYPE,
			AmapressLieu_distribution::INTERNAL_POST_TYPE,
			AmapressContrat_quantite::INTERNAL_POST_TYPE,
			AmapressProducteur::INTERNAL_POST_TYPE,
			AmapressContrat::INTERNAL_POST_TYPE,
			AmapressContrat_instance::INTERNAL_POST_TYPE
		] );
		self::commitTransaction();

		return $cnt;
	}

	public static function deletePartialAutoGeneratedPosts() {
		self::startTransaction();
		$cnt = self::deletePostsByType( [
			AmapressPanier::INTERNAL_POST_TYPE,
			AmapressDistribution::INTERNAL_POST_TYPE,
			AmapressAdhesion::INTERNAL_POST_TYPE,
			AmapressAdhesion_paiement::INTERNAL_POST_TYPE,
			AmapressAmapien_paiement::INTERNAL_POST_TYPE,
			AmapressLieu_distribution::INTERNAL_POST_TYPE,
			AmapressContrat_quantite::INTERNAL_POST_TYPE,
			AmapressIntermittence_panier::INTERNAL_POST_TYPE,
			AmapressContrat::INTERNAL_POST_TYPE,
			AmapressContrat_instance::INTERNAL_POST_TYPE
		] );
		self::commitTransaction();

		return $cnt;
	}

	public static function resolvePostalCodeCityName( $postal_code, $country = 'fr' ) {
		//
		$key       = "amps_cp_$postal_code-$country";
		$city_name = wp_cache_get( $key );
		if ( false === $city_name ) {
			$url     = "https://nominatim.openstreetmap.org/search?postalcode=$postal_code&country=$country&format=json&addressdetails=1";
			$request = wp_remote_get( $url );
			if ( ! is_wp_error( $request ) ) {
				$body = wp_remote_retrieve_body( $request );
				$json = json_decode( $body, true );
				foreach ( $json as $item ) {
					if ( isset( $item['address']['city'] ) ) {
						$city_name = $item['address']['city'];
						break;
					}
				}
				wp_cache_set( $key, $city_name );
			}
		}

		return $city_name;
	}

	public static function generateRandomAddress( $lat, $lng, $radius_meter ) {
		$key       = "amps_addr_gen_$lat-$lng-$radius_meter";
		$addresses = wp_cache_get( $key );
		if ( false === $addresses ) {
			$addresses = [];
			$url       = "http://overpass-api.de/api/interpreter?data=[out:json];(node[%22addr:housenumber%22][%22name%22](around:$radius_meter,$lat,$lng););out;%3E;out%20skel%20qt;";
			$request   = wp_remote_get( $url );
			if ( ! is_wp_error( $request ) ) {
				$body = wp_remote_retrieve_body( $request );
				$json = json_decode( $body, true );
				foreach ( $json['elements'] as $item ) {
					//addr:housenumber	"8"
					//addr:postcode	"75014"
					//addr:street	"Rue des Plantes"
					if ( isset( $item['tags']['addr:housenumber'] ) && isset( $item['tags']['addr:postcode'] ) && isset( $item['tags']['addr:street'] ) ) {
						$housenumber = $item['tags']['addr:housenumber'];
						$street      = $item['tags']['addr:street'];
						$postcode    = $item['tags']['addr:postcode'];
						$city        = self::resolvePostalCodeCityName( $postcode );
						$addresses[] = [
							'full'        => "$housenumber $street, $postcode $city",
							'address'     => "$housenumber $street",
							'housenumber' => $housenumber,
							'street'      => $street,
							'postcode'    => $postcode,
							'city'        => $city,
							'lat'         => $item['lat'],
							'lon'         => $item['lon'],
						];
					}
				}
				wp_cache_set( $key, $addresses );
			}
		}
		if ( empty( $addresses ) ) {
			return [];
		}

		return $addresses[ rand( 0, count( $addresses ) - 1 ) ];
	}

	protected function onCreateAmap( $now ) {

	}

	public function createAMAP( $shift_weeks = 0 ) {
		echo "<p>Starting import</p>";
		self::startTransaction();

		try {
			self::deleteAutoGeneratedUsers();

			self::deleteAutoGeneratedPosts();

			self::cleanAttachments();

			$this->onCreateAmap( Amapress::start_of_day( Amapress::add_a_week( amapress_time(), $shift_weeks ) ) );

			echo "<p>Updating all post titles and slug</p>";
			amapress_update_all_posts();

			echo "<p>Committing import</p>";
			self::commitTransaction();

		} catch ( Exception $exception ) {
			self::abortTransaction();
		}
	}

	function insertPostFromBitsBase64( $bits_name, $bits_base64, $parent_post_id = null ) {
		return $this->insertPostFromBits( $bits_name, base64_decode( $bits_base64 ), $parent_post_id );
	}

	function insertPostFromBits( $bits_name, $bits, $parent_post_id = null ) {
		if ( empty( $bits ) ) {
			return false;
		}

		$upload = wp_upload_bits( $bits_name, null, $bits );
		if ( ! empty( $upload['error'] ) ) {
			amapress_dump( $upload );

			return false;
		}
		$file_path        = $upload['file'];
		$file_name        = basename( $file_path );
		$file_type        = wp_check_filetype( $file_name, null );
		$attachment_title = sanitize_file_name( pathinfo( $file_name, PATHINFO_FILENAME ) );
		$wp_upload_dir    = wp_upload_dir();
		$post_info        = array(
			'guid'           => $wp_upload_dir['url'] . '/' . $file_name,
			'post_mime_type' => $file_type['type'],
			'post_title'     => $attachment_title,
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		// Create the attachment
		$attach_id = wp_insert_attachment( $post_info, $file_path, $parent_post_id );
		// Include image.php
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		// Define attachment metadata
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
		// Assign metadata to attachment
		wp_update_attachment_metadata( $attach_id, $attach_data );

		echo "<p>Inserted $bits_name media in library ($attach_id)</p>";

		return $attach_id;
	}
}