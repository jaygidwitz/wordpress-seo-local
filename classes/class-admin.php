<?php
/**
 * @package Admin
 */

if ( ! defined( 'WPSEO_LOCAL_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

if ( ! class_exists( 'WPSEO_Local_Admin' ) ) {
	/**
	 * Class that holds most of the admin functionality for WP SEO Local.
	 */
	class WPSEO_Local_Admin {

		var $group_name = 'yoast_wpseo_local_options';
		var $option_name = 'wpseo_local';

		/**
		 * Class constructor
		 */
		public function __construct() {
			
			add_action( 'admin_init', array( &$this, 'options_init' ) );

			// Adds page to WP SEO menu
			add_action( 'admin_menu', array( &$this, 'register_settings_page' ), 20 );

			// Register local into admin_pages
			add_action( 'init', array( &$this, 'register_wpseo' ) );
			
			// Add import options for Local SEO to general import panel of WP SEO
			add_action( 'wpseo_import', array( &$this, 'import_panel' ), 10, 1 );

			// Add styles and scripts
			add_action( 'admin_enqueue_scripts', array( $this, 'config_page_scripts' ) );
			add_action( 'admin_print_styles', array( &$this, 'config_page_styles' ) );
			add_action( 'admin_footer', array( $this, 'config_page_footer' ) );
		}

		

		/**
		 * Registers the wpseo_local setting for Settings API
		 *
		 * @since 1.0
		 */
		function options_init() {
			register_setting( 'yoast_wpseo_local_options', 'wpseo_local' );
		}

		/**
		 * Adds local page to admin_page variable of wpseo
		 */
		function register_wpseo() {
			add_filter('wpseo_admin_pages', array( &$this, 'register_local_page') );
		}

		/**
		 * Registers local page 
		 */
		function register_local_page( $pages ) {
			$pages[] = 'wpseo_local';

			return $pages;
		}

		/**
		 * Registers the settings page in the WP SEO menu
		 *
		 * @since 1.0
		 */
		function register_settings_page() {
			// TODO: hardcoded manage_options here, which should be properly inherited from WPSEO_Admin class later on
			add_submenu_page( 'wpseo_dashboard', 'Yoast WordPress SEO: Local SEO', 'Local SEO', 'manage_options', 'wpseo_local', array( &$this, 'load_page' ) );
		}

		/**
		 * Load the form for a WPSEO admin page
		 */
		function load_page() {
			if ( isset( $_GET['page'] ) ) {
				require_once( WPSEO_LOCAL_PATH . 'admin/pages/local.php' );
			}
		}

		/**
		 * Loads some CSS
		 *
		 * @since 1.0
		 */
		function config_page_styles() {
			global $pagenow, $post;

			if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
				$css_ext = '.css';
			} else {
				$css_ext = '.min.css';
			}

			if ( $pagenow == 'admin.php' && isset( $_GET['page'] ) && $_GET['page'] == 'wpseo_local' ) {
				wp_enqueue_style( 'yoast-local-admin-css', plugins_url( 'styles/yst_plugin_tools' . $css_ext, dirname( __FILE__ ) ), WPSEO_LOCAL_VERSION );
			} else if ( ( $pagenow == 'admin.php' && isset( $_GET['page'] ) && $_GET['page'] == 'wpseo_local' ) || ( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) && $post->post_type == 'wpseo_locations' ) ) {
				wp_enqueue_style( 'jquery-chosen-css', plugins_url( 'styles/chosen' . $css_ext, dirname( __FILE__ ) ), WPSEO_LOCAL_VERSION );
				wp_enqueue_style( 'wpseo-local-admin-css', plugins_url( 'styles/admin' . $css_ext, dirname( __FILE__ ) ), WPSEO_LOCAL_VERSION );
			} else {
				if ( $pagenow == 'post-new.php' || $pagenow == 'post.php' ) {
					wp_enqueue_style( 'wpseo-local-admin-css', plugins_url( 'styles/admin' . $css_ext, dirname( __FILE__ ) ), WPSEO_LOCAL_VERSION );
				}
			}
		}

		/**
		 * Enqueues the (tiny) global JS needed for the plugin.
		 */
		function config_page_scripts() {
			if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
				$css_ext = '.css';
				$js_ext = '.js';
			} else {
				$css_ext = '.min.css';
				$js_ext = '.min.js';
			}

			wp_enqueue_script( 'wpseo-local-global-script', plugins_url( 'js/wp-seo-local-global'.$js_ext, dirname( __FILE__ ) ), array( 'jquery' ), WPSEO_LOCAL_VERSION, true );
			global $pagenow, $post;
			if ( ( $pagenow == 'admin.php' && isset( $_GET['page'] ) && $_GET['page'] == 'wpseo_local' ) || ( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) && $post->post_type == 'wpseo_locations' ) ) {
				wp_enqueue_script( 'jquery-chosen', plugins_url( 'js/chosen.jquery.min.js', dirname( __FILE__ ) ), array( 'jquery' ), WPSEO_LOCAL_VERSION, true );
				wp_enqueue_style( 'jquery-chosen-css', plugins_url( 'styles/chosen'.$css_ext, dirname( __FILE__ ) ), WPSEO_LOCAL_VERSION );
			}
		}

		/**
		 * Print the required JavaScript in the footer
		 */
		function config_page_footer() {
			global $pagenow, $post;
			if ( ( $pagenow == 'admin.php' && isset( $_GET['page'] ) && $_GET['page'] == 'wpseo_local' ) || ( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) && $post->post_type == 'wpseo_locations' ) ) {
				?>
				<script>
					jQuery(document).ready(function ($) {
						$(".chzn-select, #business_type, #location_country").chosen();
					});
				</script>
			<?php
			}
		}

		/**
		 * Generates the import panel for importing locations via CSV
		 */
		function import_panel() {
			global $wpseo_admin_pages;

			$upload_dir       = wp_upload_dir();
			$wpseo_upload_dir = $upload_dir["basedir"] . '/wpseo/import/';

			$content = '<p>' . sprintf( __('View the %sdocumentation%s to check what format of the CSV file should be.', 'yoast-local-seo'), '<a href="https://yoast.com/question/csv-import-file-local-seo-look-like/" target="_blank">', '</a>' ) . '</p>';

			$content .= '<form action="" method="post" enctype="multipart/form-data">';
			$content .= $wpseo_admin_pages->file_upload( 'csvuploadlocations', __( 'Upload CSV', 'yoast-local-seo' ) );
			$content .= '<label for="csv_separator" class="checkbox">' . __( 'Column separator', 'yoast-local-seo' ) . ':</label>';
			$content .= '<select class="textinput" id="csv_separator" name="csv_separator">';
			$content .= '<option value="comma">' . __( 'Comma', 'yoast-local-seo' ) . '</option>';
			$content .= '<option value="semicolon">' . __( 'Semicolon', 'yoast-local-seo' ) . '</option>';
			$content .= '</select>';
			$content .= '<br class="clear">';
			$content .= '<p>';
			$content .= '<input class="checkbox double" id="is-simplemap-import" type="checkbox" name="is-simplemap-import" value="1"> ';
			$content .= '<label for="is-simplemap-import">' . __( 'This CSV is exported by the SimpleMap plugin', 'yoast-local-seo' ) . '</label>';
			$content .= '</p>';
			$content .= '<br class="clear">';
			$content .= '<br/>';

			$content .= '<p><em>' . __('Note', 'yoast-local-seo') . ': ' . __('The Geocoding API is limited to 2,500 queries a day, so when you have large CSV files, with no coordinates, cut them in pieces of 2,500 rows and import them one a day. Indeed, it\'s not funny. It\'s reality.', 'yoast-local-seo') . '</em></p>';

			if( ! is_writable( $wpseo_upload_dir ) ) {
				$content .= '<p>' . sprintf( __( 'Make sure the %s directory is writeable.', 'yoast-local-seo' ), '<code>"' . $wpseo_upload_dir . '"</code>' ) . '</p>';
			}

			$content .= '<input type="submit" class="button-primary" name="csv-import" value="Import" ' . ( ! is_writable( $wpseo_upload_dir ) ? ' disabled="disabled"' : '' ) . ' />';
			$content .= '</form>';

			if ( !empty( $_POST["csv-import"] ) ) {
				$csv_path = $wpseo_upload_dir . basename( $_FILES['wpseo']['name']['csvuploadlocations'] );
				if ( !empty( $_FILES['wpseo'] ) && !move_uploaded_file( $_FILES['wpseo']['tmp_name']['csvuploadlocations'], $csv_path ) ) {
					$content .= '<p class="error">' . __( 'Sorry, there was an error while uploading the CSV file.<br>Please make sure the ' . $wpseo_upload_dir . ' directory is writable (chmod 777).', 'yoast-local-seo' ) . '</p>';
				} else {
					$is_simplemap_import = !empty( $_POST['is-simplemap-import'] ) && $_POST['is-simplemap-import'] == '1';

					$separator = ",";
					if ( ( !empty( $_POST['csv_separator'] ) && $_POST['csv_separator'] == "semicolon" ) && false == $is_simplemap_import ) {
						$separator = ";";
					}

					// Get location data from CSV
					$column_names = array( "name", "address", "city", "zipcode", "state", "country", "phone", "phone2nd", "description", "image", "category" );
					if( $is_simplemap_import )
						$column_names = array( "name", "address", "address2", "city", "state", "zipcode", "country", "phone", "email", "fax", "url", "description", "special", "lat", "long", "pubdate", "category", "tag" );

					$handle       = fopen( $csv_path, "r" );
					$locations    = array();
					$row          = 0;
					while ( ( $csvdata = fgetcsv( $handle, 1000, $separator ) ) !== FALSE ) {
						if ( $row > 0 ) {
							$tmp_location = array();
							for ( $i = 0; $i < count( $column_names ); $i++ ) {

								// Skip columns for simplemap import
								if( $is_simplemap_import && in_array( $column_names[$i], array( 'address2', 'email', 'url', 'special', 'pubdate', 'tag' ) ) ) {
									continue;
								}

								if ( isset( $csvdata[$i] ) ) {
									$tmp_location[$column_names[$i]] = addslashes( $csvdata[$i] );
								}
							}
							array_push( $locations, $tmp_location );
						}
						$row++;
					}
					fclose( $handle );

					$debug = false;

					
					// Create WordPress posts in custom post type
					foreach ( $locations as $location ) {
						// Create standard post data
						$current_post['ID']           = '';
						$current_post['post_title']   = isset( $location["name"] ) ? $location["name"] : '';
						$current_post['post_content'] = isset( $location["description"] ) ? $location["description"] : '';
						$current_post['post_status']  = "publish";
						$current_post['post_date']    = date( "Y-m-d H:i:s", time() );
						$current_post['post_type']    = 'wpseo_locations';

						if ( !$debug ) {
							$errors = array();
							$post_id = wp_insert_post( $current_post );

							// Insert custom fields for location details
							if ( !empty( $post_id ) ) {
								add_post_meta( $post_id, "_wpseo_business_name", isset( $location["name"] ) ? $location["name"] : '', true );
								add_post_meta( $post_id, '_wpseo_business_address', isset( $location["address"] ) ? $location["address"] : '', true );
								add_post_meta( $post_id, '_wpseo_business_city', isset( $location["city"] ) ? $location["city"] : '', true );
								add_post_meta( $post_id, '_wpseo_business_state', isset( $location["state"] ) ? $location["state"] : '', true );
								add_post_meta( $post_id, '_wpseo_business_zipcode', isset( $location["zipcode"] ) ? $location["zipcode"] : '', true );
								add_post_meta( $post_id, '_wpseo_business_country', isset( $location["country"] ) ? $location["country"] : '', true );
								add_post_meta( $post_id, '_wpseo_business_phone', isset( $location["phone"] ) ? $location["phone"] : '', true );
								add_post_meta( $post_id, '_wpseo_business_fax', isset( $location["fax"] ) ? $location["fax"] : '', true );

								if( isset( $location["phone_2nd"] ) )
									add_post_meta( $post_id, '_wpseo_business_phone_2nd', $location["phone_2nd"], true );
								if( isset( $location["email"] ) )
									add_post_meta( $post_id, '_wpseo_business_email', $location["email"], true );

								if( isset( $location['category'] ) )
									wp_set_object_terms( $post_id, $location['category'], 'wpseo_locations_category' );


								if( empty( $location['lat'] ) && empty( $location['long'] ) ) {
									$full_address = $location['address'] . ', ' . wpseo_local_get_address_format( $location['zipcode'], $location['city'], $location['state'], true, false, false );
									if( ! empty( $location['country'] ) )
										$full_address .= ', ' . WPSEO_Local_Frontend::get_country( $location['country'] );

									$geo_data = wpseo_geocode_address( $full_address );

									if ( ! is_wp_error( $geo_data ) && !empty( $geo_data->results[0] ) ) {
										$location['lat']  = $geo_data->results[0]->geometry->location->lat;
										$location['long'] = $geo_data->results[0]->geometry->location->lng;
									}
									else {
										$location['lat'] = '';
										$location['long'] = '';

										if( $geo_data->get_error_code() == 'wpseo-query-limit' ) {
											$errors[] = sprintf( __('The usage of the Google Maps API has exceeds their limits. Please consider entering an API key in the %soptions%s', 'yoast-local-seo' ), '<a href="' . admin_url( 'admin.php?page=wpseo_local' ) . '">', '</a>' );
										}
										else {
											$errors[] = sprintf( __('Location <em>' . esc_attr( $location["name"] ) . '</em> could not be geo-coded. %sEdit this location%s.', 'yoast-local-seo' ), '<a href="' . admin_url( 'post.php?post=' . esc_attr( $post_id ) . '&action=edit' ) . '">', '</a>' );
										}
									}
								}

								add_post_meta( $post_id, '_wpseo_coordinates_lat', $location["lat"], true );
								add_post_meta( $post_id, '_wpseo_coordinates_long', $location["long"], true );
							}

							// Add image as post thumbnail
							if ( !empty( $location["image"] ) ) {
								$upload_dir = wp_upload_dir();
								$filepath   = $upload_dir["basedir"] . '/wpseo/import/images/' . $location["image"];

								$wpseo_admin_pages->insert_attachment( $post_id, $filepath, true );
							}
						}
					}

					$msg = '';
					if ( count( $locations ) > 0 ) {
						$msg .= count( $locations ) . ' locations found and succesfully imported.<br/>';
					}

					if( ! empty( $errors ) ) {

						$msg .= '<p>';						
						$msg .= '<strong>' . __('Some errors has occured', 'yoast-local-seo') . '</strong><br>';						
						foreach( $errors as $error ) {
							$msg .= $error . '<br>';
						}
						$msg .= '</p>';
					}
					if ( $msg != '' ) {
						echo '<div id="message" class="message updated" style="width:94%;"><p>' . $msg . '</p></div>';
					}
				}
			}

			$wpseo_admin_pages->postbox( 'xmlgeositemaps', __( 'CSV import of locations for Local Search', 'yoast-local-seo' ), $content );
		}


	} /* End of class */

} /* End of class-exists wrapper */
