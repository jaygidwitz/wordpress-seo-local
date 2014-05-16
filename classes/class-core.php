<?php

/**
 * WPSEO_Local_Core class.
 *
 * @package WordPress SEO Local
 * @since   1.0
 */
if ( !class_exists( 'WPSEO_Local_Core' ) ) {
	class WPSEO_Local_Core {

		var $options = array();
		var $days = array();

		/**
		* @var Yoast_Plugin_License_Manager Holds an instance of the license manager class
		*/
		private $license_manager = null;

		/**
		 * Constructor for the WPSEO_Local_Core class.
		 *
		 * @since 1.0
		 */
		function __construct() {

			$this->options = get_option( "wpseo_local" );
			$this->days    = array(
				'monday'    => __( 'Monday' ),
				'tuesday'   => __( 'Tuesday' ),
				'wednesday' => __( 'Wednesday' ),
				'thursday'  => __( 'Thursday' ),
				'friday'    => __( 'Friday' ),
				'saturday'  => __( 'Saturday' ),
				'sunday'    => __( 'Sunday' ),
			);

			if ( wpseo_has_multiple_locations() ) {
				add_action( 'init', array( &$this, 'create_custom_post_type' ), 10, 1 );
				add_action( 'init', array( &$this, 'create_taxonomies' ), 10, 1 );
			}

			if ( is_admin() ) {

				$this->license_manager = $this->get_license_manager();

				$this->license_manager->setup_hooks();

				add_action( 'wpseo_licenses_forms', array( $this->license_manager, 'show_license_form' ) );		

			} else {
				// XML Sitemap Index addition
				add_action( 'template_redirect', array( $this, 'redirect_old_sitemap' ) );
				add_action( 'setup_theme', array( $this, 'init' ) );
				add_filter( 'wpseo_sitemap_index', array( $this, 'add_to_index' ) );
			}

			// Run update if needed
			add_action( 'plugins_loaded', array( &$this, 'do_upgrade' ), 14 );
		}

		function do_upgrade() {
			$options = get_option( 'wpseo_local' );

			if ( ! isset( $options['version'] ) ) {
				$options['version'] = '0';
			}

			if ( version_compare( $options['version'], WPSEO_LOCAL_VERSION, '<' ) ) {

				// upgrade to new licensing class
				$license_manager = $this->get_license_manager();

				if( $license_manager->license_is_valid() === false ) {

					if( isset( $options['license'] ) ) {
						$license_manager->set_license_key( $options['license'] );
					}

					if( isset( $options['license-status'] ) ) {
						$license_manager->set_license_status( $options['license-status'] );
					}

				}

				// other upgrades
				wpseo_local_do_upgrade( $options['version'] );
			}
		}

		/**
		* Returns an instance of the Yoast_Plugin_License_Manager class
		* Takes care of remotely (de)activating licenses and plugin updates.
		*/
		private function get_license_manager() {

			// We need WP SEO 1.5+ or higher but WP SEO Local doesn't have a version check.
			if( ! $this->license_manager ) {

				require_once dirname( __FILE__ ) . '/class-product.php';

				$this->license_manager = new Yoast_Plugin_License_Manager( new Yoast_Product_WPSEO_Local() );
				$this->license_manager->set_license_constant_name( 'WPSEO_LOCAL_LICENSE' );
			}

			return $this->license_manager;
		}

		/**
		 * Adds the rewrite for the Geo sitemap and KML file
		 *
		 * @since 1.0
		 */
		public function init() {

			if ( isset( $GLOBALS['wpseo_sitemaps'] ) ) {
				add_action( 'wpseo_do_sitemap_geo', array( $this, 'build_local_sitemap' ) );
				add_action( 'wpseo_do_sitemap_locations', array( $this, 'build_kml' ) );

				add_rewrite_rule( 'geo-sitemap\.xml$', 'index.php?sitemap=geo_', 'top' );
				add_rewrite_rule( 'locations\.kml$', 'index.php?sitemap=locations', 'top' );


				if ( preg_match( '/(geo-sitemap.xml|locations.kml)(.*?)$/', $_SERVER['REQUEST_URI'], $match ) ) {
					if ( in_array( $match[1], array( 'geo-sitemap.xml', 'locations.kml' ) ) ) {
						$sitemap = 'geo';
						if( $match[1] == 'locations.kml' ) {
							$sitemap = 'locations';
						}
						
						$GLOBALS['wpseo_sitemaps']->build_sitemap( $sitemap );
					} else {
						return;
					}

					// 404 for invalid or emtpy sitemaps
					if ( $GLOBALS['wpseo_sitemaps']->bad_sitemap ) {
						$GLOBALS['wp_query']->is_404 = true;
						return;
					}

					$GLOBALS['wpseo_sitemaps']->output();
					$GLOBALS['wpseo_sitemaps']->sitemap_close();
				}
			}
		}



		/**
		 * Redirects old geo_sitemap.xml to geo-sitemap.xml to be more in line with other XML sitemaps of WordPress SEO plugin.
		 *
		 * @since 1.2.2.1
		 *
		 */
		public function redirect_old_sitemap() {
			if ( preg_match( '/(geo_sitemap.xml)(.*?)$/', $_SERVER['REQUEST_URI'], $match ) ) { 
				
				if( $match[1] == 'geo_sitemap.xml' ) {
					wp_redirect( trailingslashit( get_home_url() ) . 'geo-sitemap.xml', 301 );
					exit;
				}
			}
		}

		/**
		 * Adds the Geo Sitemap to the Index Sitemap.
		 *
		 * @since 1.0
		 *
		 * @param $str string String with the filtered additions to the index sitemap in it.
		 * @return string $str string String with the local XML sitemap additions to the index sitemap in it.
		 */
		public function add_to_index( $str ) {
			$base = $GLOBALS['wp_rewrite']->using_index_permalinks() ? 'index.php/' : '';

			$date = get_option( 'wpseo_local_xml_update' );
			if ( !$date || $date == '' ) {
				$date = date( 'c' );
			}

			$str .= '<sitemap>' . "\n";
			$str .= '<loc>' . home_url( $base . 'geo-sitemap.xml' ) . '</loc>' . "\n";
			$str .= '<lastmod>' . $date . '</lastmod>' . "\n";
			$str .= '</sitemap>' . "\n";
			return $str;
		}

		/**
		 * Pings Google with the (presumeably updated) Geo Sitemap.
		 *
		 * @since 1.0
		 */
		private function ping() {
			$base = $GLOBALS['wp_rewrite']->using_index_permalinks() ? 'index.php/' : '';

			// Ping Google. Just do it. 
			wp_remote_get( 'http://www.google.com/webmasters/tools/ping?sitemap=' . home_url( $base . 'geo-sitemap.xml' ) );
		}

		/**
		 * Updates the last update time transient for the local sitemap and pings Google with the sitemap.
		 *
		 * @since 1.0
		 */
		public function update_sitemap() {
			update_option( 'wpseo_local_xml_update', date( 'c' ) );
			$this->ping();
		}


		/**
		 * This function generates the Geo sitemap's contents.
		 *
		 * @since 1.0
		 */
		public function build_local_sitemap() {
			$base = $GLOBALS['wp_rewrite']->using_index_permalinks() ? 'index.php/' : '';

			// Build entry for Geo Sitemap
			$output = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:geo="http://www.google.com/geo/schemas/sitemap/1.0">
				<url>
					<loc>' . home_url( $base . 'locations.kml' ) . '</loc>
					<lastmod>' . date( 'c' ) . '</lastmod>
					<priority>1</priority>
				</url>
			</urlset>';

			if ( isset( $GLOBALS['wpseo_sitemaps'] ) ) {
				$GLOBALS['wpseo_sitemaps']->set_sitemap( $output );
				$GLOBALS['wpseo_sitemaps']->set_stylesheet( '<?xml-stylesheet type="text/xsl" href="' . dirname( plugin_dir_url( __FILE__ ) ) . '/styles/geo-sitemap.xsl"?>' );
			}
		}

		/**
		 * This function generates the KML file contents.
		 *
		 * @since 1.0
		 */
		public function build_kml() {
			$location_data = $this->get_location_data();
			$errors        = array();

			if ( isset( $location_data["businesses"] ) && is_array( $location_data["businesses"] ) && count( $location_data["businesses"] ) > 0 ) {
				$kml_output = "<kml xmlns=\"http://www.opengis.net/kml/2.2\" xmlns:atom=\"http://www.w3.org/2005/Atom\">\n";
				$kml_output .= "\t<Document>\n";
				$kml_output .= "\t\t<name>" . ( !empty( $location_data['kml_name'] ) ? $location_data['kml_name'] : " Locations for " . $location_data['business_name'] ) . "</name>\n";

				if ( !empty( $location_data->author ) ) {
					$kml_output .= "\t\t<atom:author>\n";
					$kml_output .= "\t\t\t<atom:name>" . $location_data['author'] . "</atom:name>\n";
					$kml_output .= "\t\t</atom:author>\n";
				}
				if ( !empty( $location_data_fields["business_website"] ) ) {
					$kml_output .= "\t\t<atom:link href=\"" . $location_data['website'] . "\" />\n";
				}

				$kml_output .= "\t\t<open>1</open>\n";
				$kml_output .= "\t\t<Folder>\n";

				foreach ( $location_data['businesses'] as $key => $business ) {
					if ( !empty( $business ) ) {
						$business_name        = esc_attr( $business['business_name'] );
						$business_description = !empty( $business['business_description'] ) ? esc_attr( strip_shortcodes( $business['business_description'] ) ) : "";
						$business_description = htmlentities( $business_description );
						$business_url         = esc_url( $business['business_url'] );
						if ( wpseo_has_multiple_locations() && !empty( $business['post_id'] ) )
							$business_url = get_permalink( $business['post_id'] );
						if ( ! isset ( $business['full_address'] ) || empty ( $business['full_address'] ) ) {
							$business['full_address'] = $business['business_address'] . ', ' . wpseo_local_get_address_format( $business['business_zipcode'], $business['business_city'], $business['business_state'], true, false, false );
							if( ! empty( $business['business_country'] ) )
								$business['full_address'] .= ', ' . WPSEO_Local_Frontend::get_country( $business['business_country'] );
						}
						$business_fulladdress = $business['full_address'];

						$kml_output .= "\t\t\t<Placemark>\n";
						$kml_output .= "\t\t\t\t<name><![CDATA[" . $business_name . "]]></name>\n";
						$kml_output .= "\t\t\t\t<address><![CDATA[" . $business_fulladdress . "]]></address>\n";
						$kml_output .= "\t\t\t\t<description><![CDATA[" . $business_description . "]]></description>\n";
						$kml_output .= "\t\t\t\t<atom:link href=\"" . $business_url . "\"/>\n";
						$kml_output .= "\t\t\t\t<LookAt>\n";
						$kml_output .= "\t\t\t\t\t<latitude>" . $business["coords"]["lat"] . "</latitude>\n";
						$kml_output .= "\t\t\t\t\t<longitude>" . $business["coords"]["long"] . "</longitude>\n";
						$kml_output .= "\t\t\t\t\t<altitude>1500</altitude>\n";
						$kml_output .= "\t\t\t\t\t<range></range>\n";
						$kml_output .= "\t\t\t\t\t<tilt>0</tilt>\n";
						$kml_output .= "\t\t\t\t\t<heading></heading>\n";
						$kml_output .= "\t\t\t\t\t<altitudeMode>relativeToGround</altitudeMode>\n";
						$kml_output .= "\t\t\t\t</LookAt>\n";
						$kml_output .= "\t\t\t\t<Point>\n";
						$kml_output .= "\t\t\t\t\t<coordinates>" . $business["coords"]["long"] . "," . $business["coords"]["lat"] . ",0</coordinates>\n";
						$kml_output .= "\t\t\t\t</Point>\n";
						$kml_output .= "\t\t\t</Placemark>\n";
					}
				}

				$kml_output .= "\t\t</Folder>\n";
				$kml_output .= "\t</Document>\n";
				$kml_output .= "</kml>\n";

				if ( isset( $GLOBALS['wpseo_sitemaps'] ) ) {
					$GLOBALS['wpseo_sitemaps']->set_sitemap( $kml_output );
					$GLOBALS['wpseo_sitemaps']->set_stylesheet( '<?xml-stylesheet type="text/xsl" href="' . dirname( plugin_dir_url( __FILE__ ) ) . '/styles/kml-file.xsl"?>' );
				}
			}

			return $location_data;
		}

		/**
		 * Builds an array based upon the data from the wpseo_locations post type. This data is needed as input for the Geo sitemap & KML API.
		 *
		 * @since 1.0
		 */
		function get_location_data() {
			$locations               = array();
			$locations["businesses"] = array();

			if ( wpseo_has_multiple_locations() ) {
				$posts = get_posts( array(
					'post_type'      => 'wpseo_locations',
					'posts_per_page' => -1,
					'fields'		 => 'ids'
				) );

				foreach ( $posts as $post_id ) {
					$business = array(
						"business_name"        => get_the_title( $post_id ),
						"business_address"     => get_post_meta( $post_id, '_wpseo_business_address', true ),
						"business_city"        => get_post_meta( $post_id, '_wpseo_business_city', true ),
						"business_state"       => get_post_meta( $post_id, '_wpseo_business_state', true ),
						"business_zipcode"     => get_post_meta( $post_id, '_wpseo_business_zipcode', true ),
						"business_country"     => get_post_meta( $post_id, '_wpseo_business_country', true ),
						"business_phone"       => get_post_meta( $post_id, '_wpseo_business_phone', true ),
						"business_phone_2nd"   => get_post_meta( $post_id, '_wpseo_business_phone_2nd', true ),
						"business_fax"         => get_post_meta( $post_id, '_wpseo_business_fax', true ),
						"business_email"       => get_post_meta( $post_id, '_wpseo_business_email', true ),
						"business_url"	       => get_post_meta( $post_id, '_wpseo_business_url', true ),
						"business_description" => wpseo_local_get_excerpt( $post_id ),
						"coords"               => array(
							'lat'  => get_post_meta( $post_id, '_wpseo_coordinates_lat', true ),
							'long' => get_post_meta( $post_id, '_wpseo_coordinates_long', true )
						),
						"post_id"              => $post_id
					);


					if( empty( $business['business_url'] ) )
						$business['business_url'] = get_permalink( $post_id );

					array_push( $locations["businesses"], $business );
				}
			} else {
				$options = get_option( 'wpseo_local' );

				$business = array(
					"business_name"        => $options['location_name'],
					"business_address"     => $options['location_address'],
					"business_city"        => $options['location_city'],
					"business_state"       => $options['location_state'],
					"business_zipcode"     => $options['location_zipcode'],
					"business_country"     => $options['location_country'],
					"business_phone"       => $options['location_phone'],
					"business_phone_2nd"   => $options['location_phone_2nd'],
					"business_fax"         => $options['location_fax'],
					"business_email"       => $options['location_email'],
					"business_description" => get_option( "blogname" ) . ' - ' . get_option( "blogdescription" ),
					"business_url"         => get_home_url(),
					"coords"               => array(
						'lat'  => $options['location_coords_lat'],
						'long' => $options['location_coords_long'],
					)
				);
				array_push( $locations["businesses"], $business );
			}

			$base = $GLOBALS['wp_rewrite']->using_index_permalinks() ? 'index.php/' : '';

			$locations["business_name"] = get_option( "blogname" );
			$locations["kml_name"]      = "Locations for " . $locations["business_name"] . ".";
			$locations["kml_url"]       = home_url( $base . '/locations.kml' );
			$locations["kml_website"]   = get_home_url();
			$locations["author"]        = get_option( "blogname" );

			return $locations;
		}

		/**
		 * Retrieves the lat/long coordinates from the Google Maps API
		 *
		 * @param Array $location_info Array with location info. Array structure: array( _wpseo_business_address, _wpseo_business_city, _wpseo_business_state, _wpseo_business_zipcode, _wpseo_business_country )
		 * @param bool  $force_update  Whether to force the update or not
		 * @param int $post_id
		 *
		 * @return bool|array Returns coordinates in array ( Format: array( 'lat', 'long' ) ). False when call the Maps API did not succeed
		 */
		public function get_geo_data( $location_info, $force_update = false, $post_id = 0 ) {
			$full_address = $location_info['_wpseo_business_address'] . ', ' . wpseo_local_get_address_format( $location_info['_wpseo_business_zipcode'], $location_info['_wpseo_business_city'], $location_info['_wpseo_business_state'], true, false, false ) . ', ' . WPSEO_Local_Frontend::get_country( $location_info['_wpseo_business_country'] );

			$coordinates = array();

			if ( ( $post_id === 0 || empty( $post_id ) ) && isset( $location_info['_wpseo_post_id'] ) )
				$post_id = $location_info['_wpseo_post_id'];

			if ( $force_update || empty( $location_info['_wpseo_coords']['lat'] ) || empty( $location_info['_wpseo_coords']['long'] ) ) {

				$results = wpseo_geocode_address( $full_address );

				if ( is_wp_error( $results ) )
					return false;

				if ( isset( $results->results[0] ) && !empty( $results->results[0] ) ) {
					$coordinates['lat']  = $results->results[0]->geometry->location->lat;
					$coordinates['long'] = $results->results[0]->geometry->location->lng;

					if ( wpseo_has_multiple_locations() && $post_id !== 0 ) {

						update_post_meta( $post_id, '_wpseo_coordinates_lat', $coordinates['lat'] );
						update_post_meta( $post_id, '_wpseo_coordinates_long', $coordinates['long'] );
					} else {
						$options                         = get_option( 'wpseo_local' );
						$options['location_coords_lat']  = $coordinates['lat'];
						$options['location_coords_long'] = $coordinates['long'];

						update_option( 'wpseo_local', $options );
					}
				}
			} else {
				$coordinates['lat']  = $location_info['_wpseo_coords']['lat'];
				$coordinates['long'] = $location_info['_wpseo_coords']['long'];
			}

			$return_array['coords']       = $coordinates;
			$return_array["full_address"] = $full_address;

			return $return_array;
		}

		/**
		 * Creates the wpseo_locations Custom Post Type
		 */
		function create_custom_post_type() {
			/* Locations as Custom Post Type */
			$labels = array(
				'name'               => __( 'Locations', 'yoast-local-seo' ),
				'singular_name'      => __( 'Location', 'yoast-local-seo' ),
				'add_new'            => __( 'New Location', 'yoast-local-seo' ),
				'new_item'           => __( 'New Location', 'yoast-local-seo' ),
				'add_new_item'       => __( 'Add New Location', 'yoast-local-seo' ),
				'edit_item'          => __( 'Edit Location', 'yoast-local-seo' ),
				'view_item'          => __( 'View Location', 'yoast-local-seo' ),
				'search_items'       => __( 'Search Locations', 'yoast-local-seo' ),
				'not_found'          => __( 'No locations found', 'yoast-local-seo' ),
				'not_found_in_trash' => __( 'No locations found in trash', 'yoast-local-seo' ),
			);

			$slug = !empty( $this->options['locations_slug'] ) ? $this->options['locations_slug'] : 'locations';

			$args_cpt = array(
				'labels'               => $labels,
				'public'               => true,
				'show_ui'              => true,
				'capability_type'      => 'post',
				'hierarchical'         => false,
				'rewrite'              => array( 'slug' => $slug ),
				'has_archive'          => $slug,
				'query_var'            => true,
				'supports'             => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'revisions', 'custom-fields', 'page-attributes' )
			);
			$args_cpt = apply_filters( 'wpseo_local_cpt_args', $args_cpt );

			register_post_type( 'wpseo_locations', $args_cpt );
		}

		/**
		 * Create custom taxonomy for wpseo_locations Custom Post Type
		 */
		function create_taxonomies() {

			$labels = array(
				'name'              => __( 'Location categories', 'yoast-local-seo' ),
				'singular_name'     => __( 'Location category', 'yoast-local-seo' ),
				'search_items'      => __( 'Search Location categories', 'yoast-local-seo' ),
				'all_items'         => __( 'All Location categories', 'yoast-local-seo' ),
				'parent_item'       => __( 'Parent Location category', 'yoast-local-seo' ),
				'parent_item_colon' => __( 'Parent Location category:', 'yoast-local-seo' ),
				'edit_item'         => __( 'Edit Location category', 'yoast-local-seo' ),
				'update_item'       => __( 'Update Location category', 'yoast-local-seo' ),
				'add_new_item'      => __( 'Add New Location category', 'yoast-local-seo' ),
				'new_item_name'     => __( 'New Location category Name', 'yoast-local-seo' ),
				'menu_name'         => __( 'Location categories', 'yoast-local-seo' ),
			);

			$slug = !empty( $this->options['locations_taxo_slug'] ) ? $this->options['locations_taxo_slug'] : 'locations-category';

			$args = array(
				'hierarchical'          => true,
				'labels'                => $labels,
				'show_ui'               => true,
				'show_admin_column'     => true,
				'update_count_callback' => '_update_post_term_count',
				'query_var'             => true,
				'rewrite' 				=> array( 'slug' => $slug )
			);
			$args = apply_filters( 'wpseo_local_custom_taxonomy_args', $args );

			register_taxonomy(
				'wpseo_locations_category',
				'wpseo_locations',
				$args
			);
		}

		/**
		 * Adds metabox for editing screen of the wpseo_locations Custom Post Type
		 */
		function add_location_metaboxes() {
			add_meta_box( 'wpseo_locations', __( 'Business address details' ), array( &$this, 'metabox_locations' ), 'wpseo_locations', 'normal', 'high' );
		}

		/**
		 * Builds the metabox for editing screen of the wpseo_locations Custom Post Type
		 */
		function metabox_locations() {
			$post_id = get_the_ID();

			$options = $this->options;

			echo '<div style="overflow: hidden;" id="wpseo-local-metabox">';

			// Noncename needed to verify where the data originated
			echo '<input type="hidden" name="locationsmeta_noncename" id="locationsmeta_noncename" value="' . wp_create_nonce( plugin_basename( __FILE__ ) ) . '" />';


			// Copy from other locations field
			$locations = get_posts( array(
				'post_type' => 'wpseo_locations',
				'posts_per_page' => -1,
				'orderby' => 'title',
				'order' => 'ASC',
				'fields' => 'ids'
			) );

			if( count( $locations ) > 0 ) :
				echo '<p>';
				echo '<label class="textinput">' . __('Copy data from another location', 'yoast-local-seo') . ':</label>';
				echo '<select class="chzn-select" name="_wpseo_copy_from_location" id="wpseo_copy_from_location" style="width: 400px;" data-placeholder="' . __( 'Choose your location', 'yoast-local-seo' ) . '">';
				echo '<option value=""></option>';
				foreach( $locations as $location_id ) :
					echo '<option value="' . $location_id . '">' . get_the_title( $location_id ) . '</option>';
				endforeach;
				echo '</select>';
				echo '</p>';
				echo '<p style="clear:both; margin-left: 150px;"><em><strong>' . __('Note', 'yoast-local-seo') . ':</strong> ' . __('selecting a location will overwrite all data below. If you accidently selected a location, just refresh the page and make sure you don\'t save it.', 'yoast-local-seo') . '</em></p><br>';
				

				wp_reset_postdata();
			endif;

			// Get the location data if its already been entered
			$business_type          = get_post_meta( $post_id, '_wpseo_business_type', true );
			$business_address       = get_post_meta( $post_id, '_wpseo_business_address', true );
			$business_city          = get_post_meta( $post_id, '_wpseo_business_city', true );
			$business_state         = get_post_meta( $post_id, '_wpseo_business_state', true );
			$business_zipcode       = get_post_meta( $post_id, '_wpseo_business_zipcode', true );
			$business_country       = get_post_meta( $post_id, '_wpseo_business_country', true );
			$business_phone         = get_post_meta( $post_id, '_wpseo_business_phone', true );
			$business_phone_2nd     = get_post_meta( $post_id, '_wpseo_business_phone_2nd', true );
			$business_fax           = get_post_meta( $post_id, '_wpseo_business_fax', true );
			$business_email         = get_post_meta( $post_id, '_wpseo_business_email', true );
			$business_url 	        = get_post_meta( $post_id, '_wpseo_business_url', true );
			$coordinates_lat        = get_post_meta( $post_id, '_wpseo_coordinates_lat', true );
			$coordinates_long       = get_post_meta( $post_id, '_wpseo_coordinates_long', true );
			$is_postal_address      = get_post_meta( $post_id, '_wpseo_is_postal_address', true );
			$multiple_opening_hours = get_post_meta( $post_id, '_wpseo_multiple_opening_hours', true );
			$multiple_opening_hours = $multiple_opening_hours == 'on';

			if( empty( $business_url ) ) {
				$business_url = get_permalink();
			}

			// Echo out the field
			echo '<p><label class="textinput" for="wpseo_business_type">Business type:</label>';
			echo '<select class="chzn-select" name="_wpseo_business_type" id="wpseo_business_type" style="width: 200px;" data-placeholder="' . __( 'Choose your business type', 'yoast-local-seo' ) . '">';
			echo '<option></option>';
			foreach ( $this->get_local_business_types() as $bt_label => $bt_option ) {
				$sel = '';
				if ( $business_type == $bt_option )
					$sel = 'selected="selected"';
				echo '<option ' . $sel . ' value="' . $bt_option . '">' . $bt_label . '</option>';
			}
			echo '</select></p>';
			echo '<p class="desc label">' . sprintf( __( 'If your business type is not listed, please read %sthe FAQ entry%s.', 'yoast-local-seo' ), '<a href="https://yoast.com/wordpress/local-seo/faq/#my-business-is-not-listed-can-you-add-it" target="_blank">', '</a>' ) . '</p><br class="clear">';
			echo '<p><label class="textinput" for="wpseo_business_address">' . __( 'Business address:', 'yoast-local-seo' ) . '</label>';
			echo '<input type="text" name="_wpseo_business_address" id="wpseo_business_address" value="' . $business_address . '" /></p>';
			echo '<p><label class="textinput" for="wpseo_business_city">' . __( 'Business city', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="text" name="_wpseo_business_city" id="wpseo_business_city" value="' . $business_city . '" /></p>';
			echo '<p><label class="textinput" for="wpseo_business_state">' . __( 'Business state', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="text" name="_wpseo_business_state" id="wpseo_business_state" value="' . $business_state . '" /></p>';
			echo '<p><label class="textinput" for="wpseo_business_zipcode">' . __( 'Business zipcode', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="text" name="_wpseo_business_zipcode" id="wpseo_business_zipcode" value="' . $business_zipcode . '" /></p>';
			echo '<p><label class="textinput" for="wpseo_business_country">' . __( 'Business country', 'yoast-local-seo' ) . ':</label>';
			echo '<select class="chzn-select" name="_wpseo_business_country" id="wpseo_business_country" style="width: 200px; margin-top: 8px;" data-placeholder="' . __( 'Choose your country', 'yoast-local-seo' ) . '">';
			echo '<option></option>';
			$countries = WPSEO_Local_Frontend::get_country_array();
			foreach ( $countries as $key => $val ) {
				echo '<option value="' . $key . '"' . ( $business_country == $key ? ' selected="selected"' : '' ) . '>' . $countries[$key] . '</option>';
			}
			echo '</select></p>';
			echo '<p><label class="textinput" for="wpseo_business_phone">' . __( 'Main phone number', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="text" name="_wpseo_business_phone" id="wpseo_business_phone" value="' . $business_phone . '" /></p>';
			echo '<p><label class="textinput" for="wpseo_business_phone_2nd">' . __( 'Second phone number', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="text" name="_wpseo_business_phone_2nd" id="wpseo_business_phone_2nd" value="' . $business_phone_2nd . '" /></p>';
			echo '<p><label class="textinput" for="wpseo_business_fax">' . __( 'Fax number', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="text" name="_wpseo_business_fax" id="wpseo_business_fax" value="' . $business_fax . '" /></p>';
			echo '<p><label class="textinput" for="wpseo_business_email">' . __( 'Email address', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="text" name="_wpseo_business_email" id="wpseo_business_email" value="' . $business_email . '" /></p>';
			echo '<p><label class="textinput" for="wpseo_business_url">' . __( 'URL', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="text" name="_wpseo_business_url" id="wpseo_business_url" value="' . $business_url . '" /></p>';

			echo '<p>' . __( 'You can enter the lat/long coordinates yourself. If you leave them empty they will be calculated automatically. If you want to re-calculate these fields, please make them blank before saving this location.', 'yoast-local-seo' ) . '</p>';
			echo '<p><label class="textinput" for="wpseo_coordinates_lat">' . __( 'Latitude', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="text" name="_wpseo_coordinates_lat" id="wpseo_coordinates_lat" value="' . $coordinates_lat . '" /></p>';
			echo '<p><label class="textinput" for="wpseo_coordinates_long">' . __( 'Longitude', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="text" name="_wpseo_coordinates_long" id="wpseo_coordinates_long" value="' . $coordinates_long . '" /></p>';

			echo '<p>';
			echo '<label class="textinput" for="wpseo_is_postal_address">' . __( 'This address is a postal address (not a physical location)', 'yoast-local-seo' ) . ':</label>';
			echo '<input type="checkbox" class="checkbox" name="_wpseo_is_postal_address" id="wpseo_is_postal_address" value="1" ' . checked( $is_postal_address, 1, false ) . ' />';
			echo '</p>';

			// Opening hours
			echo '<br class="clear">';
			echo '<h4>' . __( 'Opening hours', 'yoast-local-seo' ) . '</h4>';

			echo '<div id="opening-hours-multiple">';
			echo '<label for="wpseo_multiple_opening_hours" class="textinput">' . __( 'I have two sets of opening hours per day', 'yoast-local-seo' ) . ':</label>';
			echo '<input class="checkbox" id="wpseo_multiple_opening_hours" type="checkbox" name="_wpseo_multiple_opening_hours" value="1" ' . checked( '1', $multiple_opening_hours, false ) . '> ';
			echo '</div>';
			echo '<br class="clear">';

			foreach ( $this->days as $key => $day ) {
				$field_name = '_wpseo_opening_hours_' . $key;
				$value_from = get_post_meta( $post_id, $field_name . '_from', true );
				if ( !$value_from )
					$value_from = '09:00';
				$value_to = get_post_meta( $post_id, $field_name . '_to', true );
				if ( !$value_to )
					$value_to = '17:00';
				$value_second_from = get_post_meta( $post_id, $field_name . '_second_from', true );
				if ( !$value_second_from )
					$value_second_from = '09:00';
				$value_second_to = get_post_meta( $post_id, $field_name . '_second_to', true );
				if ( !$value_second_to )
					$value_second_to = '17:00';

				echo '<div class="clear opening-hours">';

				if ( !isset( $options['opening_hours_24h'] ) )
					$options['opening_hours_24h'] = false;

				echo '<label class="textinput">' . $day . ':</label>';
				echo '<select class="openinghours_from" style="width: 100px;" id="' . $field_name . '_from" name="' . $field_name . '_from">';
				echo wpseo_show_hour_options( $options['opening_hours_24h'], $value_from );
				echo '</select><span id="' . $field_name . '_to_wrapper"> - ';
				echo '<select class="openinghours_to" style="width: 100px;" id="' . $field_name . '_to" name="' . $field_name . '_to">';
				echo wpseo_show_hour_options( $options['opening_hours_24h'], $value_to );
				echo '</select></span>';

				echo '<div class="clear opening-hour-second ' . ( !$multiple_opening_hours ? 'hidden' : '' ) . '">';
				echo '<div id="' . $field_name . '_second">';
				echo '<label class="textinput">&nbsp;</label>';
				echo '<select class="openinghours_from_second" style="width: 100px;" id="' . $field_name . '_second_from" name="' . $field_name . '_second_from">';
				echo wpseo_show_hour_options( $options['opening_hours_24h'], $value_second_from );
				echo '</select><span id="' . $field_name . '_second_to_wrapper"> - ';
				echo '<select class="openinghours_to_second" style="width: 100px;" id="' . $field_name . '_second_to" name="' . $field_name . '_second_to">';
				echo wpseo_show_hour_options( $options['opening_hours_24h'], $value_second_to );
				echo '</select>';
				echo '</div>';
				echo '</div>';

				echo '</div>';
			}

			echo '<br class="clear" />';
			echo '</div>';
		}

		/**
		 * Inserts attachment in WordPress. Used by import panel
		 *
		 * @param int    $post_id  The post ID where the attachment belongs to
		 * @param string $filepath Filepath of the file which has to be uploaded
		 * @param bool   $setthumb If there's an image in the import file, then set is as a Featured Image
		 * @return int|WP_Error attachment ID. Returns WP_Error when upload goes wrong
		 */
		function insert_attachment( $post_id, $filepath, $setthumb = false ) {
			$wp_filetype = wp_check_filetype( basename( $filepath ), null );

			$file_arr["name"]     = basename( $filepath );
			$file_arr["type"]     = $wp_filetype;
			$file_arr["tmp_name"] = $filepath;
			$file_title           = preg_replace( '/\.[^.]+$/', '', basename( $filepath ) );

			$attach_id = $this->media_handle_sideload( $file_arr, $post_id, $file_title );

			if ( $setthumb ) {
				update_post_meta( $post_id, '_thumbnail_id', $attach_id );
			}

			return $attach_id;
		}

		/**
		 * Handles the file upload and puts it in WordPress. Copied from media.php, because there's a fat bug in the last lines: it returns $url instead of $id;
		 *
		 * @since 2.6.0
		 * @param array  $file_array Array similar to a {@link $_FILES} upload array
		 * @param int    $post_id    The post ID the media is associated with
		 * @param string $desc       Description of the sideloaded file
		 * @param array  $post_data  allows you to overwrite some of the attachment
		 * @return int|object The ID of the attachment or a WP_Error on failure
		 */
		function media_handle_sideload( $file_array, $post_id, $desc = null, $post_data = array() ) {
			$overrides = array( 'test_form' => false );

			$file = wp_handle_sideload( $file_array, $overrides );
			if ( isset( $file['error'] ) )
				return new WP_Error( 'upload_error', $file['error'] );

			$url     = $file['url'];
			$type    = $file['type'];
			$file    = $file['file'];
			$title   = preg_replace( '/\.[^.]+$/', '', basename( $file ) );
			$content = '';

			// use image exif/iptc data for title and caption defaults if possible
			if ( $image_meta = @wp_read_image_metadata( $file ) ) {
				if ( trim( $image_meta['title'] ) && !is_numeric( sanitize_title( $image_meta['title'] ) ) )
					$title = $image_meta['title'];
				if ( trim( $image_meta['caption'] ) )
					$content = $image_meta['caption'];
			}

			$title = @$desc;

			// Construct the attachment array
			$attachment = array_merge( array(
				'post_mime_type' => $type,
				'guid'           => $url,
				'post_parent'    => $post_id,
				'post_title'     => $title,
				'post_content'   => $content,
			), $post_data );

			// Save the attachment metadata
			$id = wp_insert_attachment( $attachment, $file, $post_id );
			if ( !is_wp_error( $id ) ) {
				wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
			}
			return $id;
		}

		/**
		 * Returns the valid local business types currently shown on Schema.org
		 *
		 * @link http://schema.org/docs/full.html In the bottom of this page is a list of Local Business types.
		 * @return array
		 */
		function get_local_business_types() {
			return array(
				"Organization" => "Organization",
				"Corporation" => "Corporation",
				"GovernmentOrganization" => "Government Organization",
				"NGO" => "NGO",
				"EducationalOrganization" => "Educational Organization",
				"CollegeOrUniversity" => "&mdash; College or University",
				"ElementarySchool" => "&mdash; Elementary School",
				"HighSchool" => "&mdash; High School",
				"MiddleSchool" => "&mdash; Middle School",
				"Preschool" => "&mdash; Preschool",
				"School" => "&mdash; School",
				"PerformingGroup" => "Performing Group",
				"DanceGroup" => "&mdash; Dance Group",
				"MusicGroup" => "&mdash; Music Group",
				"TheaterGroup" => "&mdash; Theater Group",
				"SportsTeam" => "Sports Team",
				"LocalBusiness" => "Local Business",
				"AnimalShelter" => "Animal Shelter",
				"AutomotiveBusiness" => "Automotive Business",
				"AutoBodyShop" => "&mdash; Auto Body Shop",
				"AutoDealer" => "&mdash; Auto Dealer",
				"AutoPartsStore" => "&mdash; Auto Parts Store",
				"AutoRental" => "&mdash; Auto Rental",
				"AutoRepair" => "&mdash; Auto Repair",
				"AutoWash" => "&mdash; Auto Wash",
				"GasStation" => "&mdash; Gas Station",
				"MotorcycleDealer" => "&mdash; Motorcycle Dealer",
				"MotorcycleRepair" => "&mdash; Motorcycle Repair",
				"ChildCare" => "Child Care",
				"DryCleaningOrLaundry" => "Dry Cleaning or Laundry",
				"EmergencyService" => "Emergency Service",
				"FireStation" => "&mdash; Fire Station",
				"Hospital" => "&mdash; Hospital",
				"PoliceStation" => "&mdash; Police Station",
				"EmploymentAgency" => "Employment Agency",
				"EntertainmentBusiness" => "Entertainment Business",
				"AdultEntertainment" => "&mdash; Adult Entertainment",
				"AmusementPark" => "&mdash; Amusement Park",
				"ArtGallery" => "&mdash; Art Gallery",
				"Casino" => "&mdash; Casino",
				"ComedyClub" => "&mdash; Comedy Club",
				"MovieTheater" => "&mdash; Movie Theater",
				"NightClub" => "&mdash; Night Club",
				"FinancialService" => "Financial Service",
				"AccountingService" => "&mdash; Accounting Service",
				"AutomatedTeller" => "&mdash; Automated Teller",
				"BankOrCreditUnion" => "&mdash; Bank or Credit Union",
				"InsuranceAgency" => "&mdash; Insurance Agency",
				"FoodEstablishment" => "Food Establishment",
				"Bakery" => "&mdash; Bakery",
				"BarOrPub" => "&mdash; Bar or Pub",
				"Brewery" => "&mdash; Brewery",
				"CafeOrCoffeeShop" => "&mdash; Cafe or Coffee Shop",
				"FastFoodRestaurant" => "&mdash; Fast Food Restaurant",
				"IceCreamShop" => "&mdash; Ice Cream Shop",
				"Restaurant" => "&mdash; Restaurant",
				"Winery" => "&mdash; Winery",
				"GovernmentOffice" => "Government Office",
				"PostOffice" => "&mdash; Post Office",
				"HealthAndBeautyBusiness" => "Health And Beauty Business",
				"BeautySalon" => "&mdash; Beauty Salon",
				"DaySpa" => "&mdash; Day Spa",
				"HairSalon" => "&mdash; Hair Salon",
				"HealthClub" => "&mdash; Health Club",
				"NailSalon" => "&mdash; Nail Salon",
				"TattooParlor" => "&mdash; Tattoo Parlor",
				"HomeAndConstructionBusiness" => "Home And Construction Business",
				"Electrician" => "&mdash; Electrician",
				"GeneralContractor" => "&mdash; General Contractor",
				"HVACBusiness" => "&mdash; HVAC Business",
				"HousePainter" => "&mdash; House Painter",
				"Locksmith" => "&mdash; Locksmith",
				"MovingCompany" => "&mdash; Moving Company",
				"Plumber" => "&mdash; Plumber",
				"RoofingContractor" => "&mdash; Roofing Contractor",
				"InternetCafe" => "Internet Cafe",
				"Library" => " Library",
				"LodgingBusiness" => "Lodging Business",
				"BedAndBreakfast" => "&mdash; Bed And Breakfast",
				"Hostel" => "&mdash; Hostel",
				"Hotel" => "&mdash; Hotel",
				"Motel" => "&mdash; Motel",
				"MedicalOrganization" => "Medical Organization",
				"Dentist" => "&mdash; Dentist",
				"DiagnosticLab" => "&mdash; Diagnostic Lab",
				"Hospital" => "&mdash; Hospital",
				"MedicalClinic" => "&mdash; Medical Clinic",
				"Optician" => "&mdash; Optician",
				"Pharmacy" => "&mdash; Pharmacy",
				"Physician" => "&mdash; Physician",
				"VeterinaryCare" => "&mdash; Veterinary Care",
				"ProfessionalService" => "Professional Service",
				"AccountingService" => "&mdash; Accounting Service",
				"Attorney" => "&mdash; Attorney",
				"Dentist" => "&mdash; Dentist",
				"Electrician" => "&mdash; Electrician",
				"GeneralContractor" => "&mdash; General Contractor",
				"HousePainter" => "&mdash; House Painter",
				"Locksmith" => "&mdash; Locksmith",
				"Notary" => "&mdash; Notary",
				"Plumber" => "&mdash; Plumber",
				"RoofingContractor" => "&mdash; Roofing Contractor",
				"RadioStation" => "Radio Station",
				"RealEstateAgent" => "Real Estate Agent",
				"RecyclingCenter" => "Recycling Center",
				"SelfStorage" => "Self Storage",
				"ShoppingCenter" => "Shopping Center",
				"SportsActivityLocation" => "Sports Activity Location",
				"BowlingAlley" => "&mdash; Bowling Alley",
				"ExerciseGym" => "&mdash; Exercise Gym",
				"GolfCourse" => "&mdash; Golf Course",
				"HealthClub" => "&mdash; Health Club",
				"PublicSwimmingPool" => "&mdash; Public Swimming Pool",
				"SkiResort" => "&mdash; Ski Resort",
				"SportsClub" => "&mdash; Sports Club",
				"StadiumOrArena" => "&mdash; Stadium or Arena",
				"TennisComplex" => "&mdash; Tennis Complex",
				"Store" => " Store",
				"AutoPartsStore" => "&mdash; Auto Parts Store",
				"BikeStore" => "&mdash; Bike Store",
				"BookStore" => "&mdash; Book Store",
				"ClothingStore" => "&mdash; Clothing Store",
				"ComputerStore" => "&mdash; Computer Store",
				"ConvenienceStore" => "&mdash; Convenience Store",
				"DepartmentStore" => "&mdash; Department Store",
				"ElectronicsStore" => "&mdash; Electronics Store",
				"Florist" => "&mdash; Florist",
				"FurnitureStore" => "&mdash; Furniture Store",
				"GardenStore" => "&mdash; Garden Store",
				"GroceryStore" => "&mdash; Grocery Store",
				"HardwareStore" => "&mdash; Hardware Store",
				"HobbyShop" => "&mdash; Hobby Shop",
				"HomeGoodsStore" => "&mdash; HomeGoods Store",
				"JewelryStore" => "&mdash; Jewelry Store",
				"LiquorStore" => "&mdash; Liquor Store",
				"MensClothingStore" => "&mdash; Mens Clothing Store",
				"MobilePhoneStore" => "&mdash; Mobile Phone Store",
				"MovieRentalStore" => "&mdash; Movie Rental Store",
				"MusicStore" => "&mdash; Music Store",
				"OfficeEquipmentStore" => "&mdash; Office Equipment Store",
				"OutletStore" => "&mdash; Outlet Store",
				"PawnShop" => "&mdash; Pawn Shop",
				"PetStore" => "&mdash; Pet Store",
				"ShoeStore" => "&mdash; Shoe Store",
				"SportingGoodsStore" => "&mdash; Sporting Goods Store",
				"TireShop" => "&mdash; Tire Shop",
				"ToyStore" => "&mdash; Toy Store",
				"WholesaleStore" => "&mdash; Wholesale Store",
				"TelevisionStation" => "Television Station",
				"TouristInformationCenter" => "Tourist Information Center",
				"TravelAgency" => "Travel Agency"
			);
		}

	}
}
