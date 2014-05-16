<?php

if ( ! defined( 'WPSEO_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

global $wpseo_admin_pages, $wpseo_local_core;

$content = '';
$options = get_option( 'wpseo_local' );

$wpseo_admin_pages->admin_header( true, 'yoast_wpseo_local_options', 'wpseo_local' );

$content .= '<h2>' . __( 'General settings', 'yoast-local-seo' ) . '</h2>';

$content .= '<div id="select-multiple-locations" style="">' . __( 'If you have more than one location, you can enable this feature. WordPress SEO will create a new Custom Post Type for you where you can manage your locations. If it\'s not enabled you can enter your address details below. These fields will be ignored when you enable this option.', 'yoast-local-seo' ) . '<br>';
$content .= $wpseo_admin_pages->checkbox( 'use_multiple_locations', '', __( 'Use multiple locations', 'yoast-local-seo' ) );
$content .= '</div>';

$content .= '<div id="show-single-location" style="clear: both; ' . ( wpseo_has_multiple_locations() ? 'display: none;' : '' ) . '">';
$content .= $wpseo_admin_pages->textinput( 'location_name', __( 'Business name', 'yoast-local-seo' ) );

$content .= $wpseo_admin_pages->select( 'business_type', __( 'Business type:', 'yoast-local-seo' ), $wpseo_local_core->get_local_business_types() );
$content .= '<p class="desc label" style="border:none; margin-bottom: 0;">' . sprintf( __( 'If your business type is not listed, please read %sthe FAQ entry%s.', 'yoast-local-seo' ), '<a href="https://yoast.com/wordpress/local-seo/faq/#my-business-is-not-listed-can-you-add-it" target="_blank">', '</a>' ) . '</p>';

$content .= $wpseo_admin_pages->textinput( 'location_address', __( 'Business address', 'yoast-local-seo' ) );
$content .= $wpseo_admin_pages->textinput( 'location_city', __( 'Business city', 'yoast-local-seo' ) );
$content .= $wpseo_admin_pages->textinput( 'location_state', __( 'Business state', 'yoast-local-seo' ) );
$content .= $wpseo_admin_pages->textinput( 'location_zipcode', __( 'Business zipcode', 'yoast-local-seo' ) );
$content .= $wpseo_admin_pages->select( 'location_country', __( 'Business country', 'yoast-local-seo' ) . ':', WPSEO_Local_Frontend::get_country_array() );
$content .= $wpseo_admin_pages->textinput( 'location_phone', __( 'Business phone', 'yoast-local-seo' ) );
$content .= $wpseo_admin_pages->textinput( 'location_phone_2nd', __( '2nd Business phone', 'yoast-local-seo' ) );
$content .= $wpseo_admin_pages->textinput( 'location_fax', __( 'Business fax', 'yoast-local-seo' ) );
$content .= $wpseo_admin_pages->textinput( 'location_email', __( 'Business email', 'yoast-local-seo' ) );


// Calculate lat/long coordinates when address is entered.
if ( $options['location_coords_lat'] == '' || $options['location_coords_long'] == '' ) {
	$location_coordinates = $wpseo_local_core->get_geo_data( array(
		'_wpseo_business_address' => $options['location_address'],
		'_wpseo_business_city'    => $options['location_city'],
		'_wpseo_business_state'   => $options['location_state'],
		'_wpseo_business_zipcode' => $options['location_zipcode'],
		'_wpseo_business_country' => $options['location_country']
	), true );
	if ( !empty( $location_coordinates['coords'] ) ) {
		$options['location_coords_lat']  = $location_coordinates['coords']['lat'];
		$options['location_coords_long'] = $location_coordinates['coords']['long'];
		update_option( 'wpseo_local', $options );
	}
}

$content .= '<p>' . __( 'You can enter the lat/long coordinates yourself. If you leave them empty they will be calculated automatically. If you want to re-calculate these fields, please make them blank before saving this location.', 'yoast-local-seo') . '</p>';
$content .= $wpseo_admin_pages->textinput( 'location_coords_lat', __( 'Latitude', 'yoast-local-seo' ) );
$content .= $wpseo_admin_pages->textinput( 'location_coords_long', __( 'Longitude', 'yoast-local-seo' ) );
$content .= '</div><!-- #show-single-location -->';

$content .= '<div id="show-multiple-locations" style="clear: both; ' . ( wpseo_has_multiple_locations() ? '' : 'display: none;' ) . '">';
$content .= $wpseo_admin_pages->textinput( 'locations_slug', __( 'Locations slug', 'yoast-local-seo' ) );
$content .= '<p class="desc label" style="border: 0; margin-bottom: 0; padding-bottom: 0;">' . __( 'The slug for your location pages. Default slug is <code>locations</code>.', 'yoast-local-seo' ) . '<br>';
$content .= '<a href="' . get_post_type_archive_link( 'wpseo_locations' ) . '" target="_blank">' . __( 'View them all', 'yoast-local-seo' ) . '</a> ' . __( 'or', 'yoast-local-seo' ) . ' <a href="' . admin_url( 'edit.php?post_type=wpseo_locations' ) . '">' . __( 'edit them', 'yoast-local-seo' ) . '</a>';
$content .= '</p>';
$content .= $wpseo_admin_pages->textinput( 'locations_taxo_slug', __( 'Locations category slug', 'yoast-local-seo' ) );
$content .= '<p class="desc label" style="border: 0; margin-bottom: 0; padding-bottom: 0;">' . __( 'The slug for your location categories. Default slug is <code>locations-category</code>.', 'yoast-local-seo' ) . '<br>';
$content .= '<a href="' . admin_url( 'edit-tags.php?taxonomy=wpseo_locations_category&post_type=wpseo_locations' ) . '">' . __( 'Edit the categories', 'yoast-local-seo' ) . '</a>';
$content .= '</p>';
$content .= '</div><!-- #show-multiple-locations -->';

$content .= '<h2>' . __( 'Opening hours', 'yoast-local-seo' ) . '</h2>';

$content .= '<div>';
$content .= $wpseo_admin_pages->checkbox( 'opening_hours_24h', '', __( 'Use 24h format', 'yoast-local-seo' ) );
$content .= '</div>';
$content .= '<br class="clear">';

$content .= '<div id="show-opening-hours" ' . ( wpseo_has_multiple_locations() ? ' class="hidden"' : '' ) . '>';

$content .= '<div id="opening-hours-multiple">';
$content .= $wpseo_admin_pages->checkbox( 'multiple_opening_hours', '', __( 'I have two sets of opening hours per day', 'yoast-local-seo' ) );
$content .= '</div>';
$content .= '<br class="clear">';

if ( !isset( $options['opening_hours_24h'] ) )
	$options['opening_hours_24h'] = false;

foreach ( $wpseo_local_core->days as $key => $day ) {
	$field_name        = 'opening_hours_' . $key;
	$value_from        = isset( $options[$field_name . '_from'] ) ? esc_attr( $options[$field_name . '_from'] ) : '09:00';
	$value_to          = isset( $options[$field_name . '_to'] ) ? esc_attr( $options[$field_name . '_to'] ) : '17:00';
	$value_second_from = isset( $options[$field_name . '_second_from'] ) ? esc_attr( $options[$field_name . '_second_from'] ) : '09:00';
	$value_second_to   = isset( $options[$field_name . '_second_to'] ) ? esc_attr( $options[$field_name . '_second_to'] ) : '17:00';

	$content .= '<div class="clear opening-hours">';

	$content .= '<label class="textinput">' . $day . ':</label>';
	$content .= '<select class="openinghours_from" style="width: 100px;" id="' . $field_name . '_from" name="wpseo_local[' . $field_name . '_from]">';
	$content .= wpseo_show_hour_options( $options['opening_hours_24h'], $value_from );
	$content .= '</select><span id="' . $field_name . '_to_wrapper"> - ';
	$content .= '<select class="openinghours_to" style="width: 100px;" id="' . $field_name . '_to" name="wpseo_local[' . $field_name . '_to]">';
	$content .= wpseo_show_hour_options( $options['opening_hours_24h'], $value_to );
	$content .= '</select>';

	$content .= '<div class="clear opening-hour-second ' . ( empty( $options['multiple_opening_hours'] ) || $options['multiple_opening_hours'] != 'on' ? 'hidden' : '' ) . '">';
	$content .= '<label class="textinput">&nbsp;</label>';
	$content .= '<select class="openinghours_from_second" style="width: 100px;" id="' . $field_name . '_second_from" name="wpseo_local[' . $field_name . '_second_from]">';
	$content .= wpseo_show_hour_options( $options['opening_hours_24h'], $value_second_from );
	$content .= '</select><span id="' . $field_name . '_second_to_wrapper"> - ';
	$content .= '<select class="openinghours_to_second" style="width: 100px;" id="' . $field_name . '_second_to" name="wpseo_local[' . $field_name . '_second_to]">';
	$content .= wpseo_show_hour_options( $options['opening_hours_24h'], $value_second_to );
	$content .= '</select>';
	$content .= '</div>';

	$content .= '</div>';
}

$content .= '</div><!-- #show-opening-hours -->';

$content .= '<h2>' . __( 'Store locator settings', 'yoast-local-seo' ) . '</h2>';
$content .= $wpseo_admin_pages->textinput( 'sl_num_results', __( 'Number of results', 'yoast-local-seo' ) );

$content .= '<h2>' . __( 'Advanced settings', 'yoast-local-seo' ) . '</h2>';

$content .= $wpseo_admin_pages->select( 'unit_system', __( 'Unit System', 'yoast-local-seo' ), array(
	'METRIC' => __( 'Metric', 'yoast-local-seo' ),
	'IMPERIAL' => __( 'Imperial', 'yoast-local-seo' )
) );
$content .= $wpseo_admin_pages->select( 'map_view_style', __( 'Default map style', 'yoast-local-seo' ), array(
	'HYBRID' => __('Hybrid', 'yoast-local-seo'),
	'SATELLITE' => __('Satellite', 'yoast-local-seo'),
	'ROADMAP' => __('Roadmap', 'yoast-local-seo'),
	'TERRAIN' => __('Terrain', 'yoast-local-seo')
) );
$content .= $wpseo_admin_pages->select( 'address_format', __( 'Address format', 'yoast-local-seo' ), array(
	'address-state-postal' => '{city}, {state} {zipcode} &nbsp;&nbsp;&nbsp;&nbsp; (New York, NY 12345 )',
	'address-state-postal-comma' => '{city}, {state}, {zipcode} &nbsp;&nbsp;&nbsp;&nbsp; (New York, NY, 12345 )',
	'address-postal-city-state' => '{zipcode} {city}, {state} &nbsp;&nbsp;&nbsp;&nbsp; (12345 New York, NY )',
	'address-postal' => '{city} {zipcode} &nbsp;&nbsp;&nbsp;&nbsp; (New York 12345 )',
	'address-postal-comma' => '{city}, {zipcode} &nbsp;&nbsp;&nbsp;&nbsp; (New York, 12345 )',
	'postal-address' => '{zipcode} {city} &nbsp;&nbsp;&nbsp;&nbsp; (1234AB Amsterdam)'
) );

$content .= '<p class="desc label" style="border:none; margin-bottom: 0;">' . sprintf( __( 'A lot of countries have their own address format. Please choose one that matches yours. If you have something completely different, please let us know via %s.', 'yoast-local-seo' ), '<a href="mailto:pluginsupport@yoast.com">pluginsupport@yoast.com</a>' ) . '</p>';

$content .= $wpseo_admin_pages->select( 'default_country', __( 'Default country', 'yoast-local-seo' ), WPSEO_Local_Frontend::get_country_array() );

$content .= '<p class="desc label" style="border:none; margin-bottom: 0;">' . __( 'If you\'re having multiple locations and they\'re all in one country, you can select your default country here. This country will be used in the storelocator search to improve the search results.', 'yoast-local-seo' ) . '</p>';

$content .= $wpseo_admin_pages->textinput( 'show_route_label', __( '"Show route" label', 'yoast-local-seo' ) );


$wpseo_admin_pages->postbox( 'local', __( 'Local SEO settings', 'yoast-local-seo' ), $content );

do_action( 'wpseo_local_config' );

$wpseo_admin_pages->admin_footer();
?>