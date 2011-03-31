<?php

/**
 * @package carrington-personal
 *
 * This file is part of the Carrington Personal Theme for WordPress
 * http://crowdfavorite.com/wordpress/themes/carrington-personal/
 *
 * Copyright (c) 2008-2010 Crowd Favorite, Ltd. All rights reserved.
 * http://crowdfavorite.com
 *
 * **********************************************************************
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
 * **********************************************************************
 */

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) { die(); }

load_theme_textdomain('carrington-personal');

/**
 * Set this to "true" to turn on debugging mode.
 * Debug helps with development by showing you the paths of the files loaded by Carrington.
 */
define('CFCT_DEBUG', false);

define('CFCT_PATH', trailingslashit(TEMPLATEPATH));

/**
 * Theme version.
 */
define('CFCT_THEME_VERSION', '1.0');

/**
 * Theme URL version.
 * Added to query var at the end of assets to force browser cache to reload after upgrade.
 */
define('CFCT_URL_VERSION', '0.2');

/**
 * Includes
 */
include_once(CFCT_PATH.'carrington-core/carrington.php');
include_once(CFCT_PATH.'functions/sidebars.php');
include_once(CFCT_PATH.'plugins/cf-compat/cf-compat.php');

/**
 * Removing theme settings from carrington-core, will replace with carrington-personal settings
 */
//remove_action('admin_menu', 'cfct_admin_menu');

/**
 * Theme Setup
 */
if ( ! function_exists( 'carrington_personal_setup' ) ) {
	function carrington_personal_setup() {
		// Add default posts and comments RSS feed links to head
		add_theme_support( 'automatic-feed-links' );
		
		// This theme uses post thumbnails
		add_theme_support( 'post-thumbnails' );

		// New image sizes that are not overwrote in the admin
		add_image_size('thumb-img', 160, 120, true); // gallery excerpt
		add_image_size('small-img', 310, 180, true); // masthead featured img, bio box
		add_image_size('medium-img', 510, 510, false); // excerpt image-format
		add_image_size('large-img', 710, 710, false); // single view for image and gallery
		add_image_size('banner-img', 510, 180, true); // excerpt featured img
		
		// Add post formats
		add_theme_support( 'post-formats', array('gallery','image','link','quote','status'));
		
		register_nav_menus(array(
			'main' => __( 'Main Navigation', 'carrington-personal' ),
			'footer' => __( 'Footer Navigation', 'carrington-personal' )
		));
		
		// Let's load some scripts
		if( !is_admin()){
			wp_enqueue_script('global', get_bloginfo('template_directory').'/js/global.js', array('jquery'), CFCT_URL_VERSION);
		}
		
	}
}
add_action( 'after_setup_theme', 'carrington_personal_setup' );

/**
 * Kuler Color Integration
 * http://kuler.adobe.com
 */

// Convert color to RGB so we can use background opacity
function hex2rgb( $color ) {
	if ( $color[0] == '#' ) {
			$color = substr( $color, 1 );
	}
	if ( strlen( $color ) == 6 ) {
			list( $r, $g, $b ) = array( $color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5] );
	} elseif ( strlen( $color ) == 3 ) {
			list( $r, $g, $b ) = array( $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] );
	} else {
			return false;
	}
	$r = hexdec( $r );
	$g = hexdec( $g );
	$b = hexdec( $b );
	return array( $r, $g, $b );
}
function echo_hex($color2hex) {
	$color_array = hex2rgb($color2hex);
	for ($i=0; $i < 3; $i++) { 
		echo $color_array[$i].',';
	}
}

// Replaces "[...]" with something more pretty
function cfcp_excerpt_more( $more ) {
	return '&hellip;';
}
add_filter( 'excerpt_more', 'cfcp_excerpt_more' );

// Common date formatting. Uses plugins/cf-compat/
function cfcp_date() {
	global $post;
	return cf_relative_time_ago($post->post_date_gmt, '', 'ago', '4', 'm.d.y', '', true);
}
function cfcp_comment_date() {
	global $comment;
	return cf_relative_time_ago($comment->comment_date_gmt, '', 'ago', '4', 'm.d.y', '', true);
}

// Prettier captions
function cfcp_img_captions($attr, $content = null) {
	$output = apply_filters('img_caption_shortcode', '', $attr, $content);
	if ($output != '') {
		return $output;
	}
	extract(shortcode_atts(array(
		'id'	=> '',
		'align'	=> 'alignnone',
		'width'	=> '',
		'caption' => ''
	), $attr));
	if ( 1 > (int) $width || empty($caption) ) {
		return $content;
	}
	return '
		<dl id="'.$id.'" class="wp-caption '.$align.'" style="width:'.$width.'px">
			<dt>'.do_shortcode($content).'</dt>
			<dd>'.$caption.'</dd>
		</dl>';
}
add_shortcode('wp_caption', 'cfcp_img_captions');
add_shortcode('caption', 'cfcp_img_captions');


// Display gallery images without our own markup for excerpts 
function gallery_excerpt($size = thumbnail, $quantity = 8) {
	//Get number of images we want
	$images = new WP_Query(array(
		'post_parent'		=> get_the_ID(),
		'post_type'			=> 'attachment',
		'post_status'		=> 'inherit',
		'posts_per_page'	=> $quantity, // -1 to show all
		'post_mime_type'	=> 'image%',
		'orderby'			=> 'menu_order',
		'order'				=> 'ASC',
	));
	echo '<ul class="gallery-img-excerpt">';	
		foreach($images->posts as $image) {
			$attimg  = wp_get_attachment_link($image->ID,$size,'false');
			echo '<li>'.$attimg.'</li>';
		}
		if ($images->found_posts > $quantity) {
			echo '<li class="gallery-view-all h5"><a href="'.get_permalink(get_the_ID()).'">View all '.$images->found_posts.'</a></li>';
		}
	echo '</ul>';
}