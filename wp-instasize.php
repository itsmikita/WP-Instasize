<?php

/**
 * Plugin Name: WP Instasize
 * Plugin URI: https://github.com/itsmikita/WP-Instasize
 * Description: This plugin checks images and regenerates thumbnails of missing image sizes on-demand.
 * Version: 0.1
 * Author: Mikita Stankiewicz
 * Author URI: http://designed.bymikita.com
 * License: GPL2
 *
 * Copyright 2012  Mikita Stankiewicz  (email : designovermatter@gmail.com)
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as 
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * Reset image size check when switching to another theme
 */
function reset_image_size_check() {
	$images = get_posts( array(
		'numberposts' => -1,
		'post_type' => 'attachment',
		'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif' )
	) );
	
	foreach( $images as $image )
		update_post_meta( $image->ID, '_image_size_check', 0 );
}
add_action( 'switch_theme', 'reset_image_size_check', 99 );

/**
 * Check and regenerate missing image sizes.
 *
 * @param bool $false - False
 * @param int $attachment_id - Attachment ID
 * @param mixed $size - Size name or array with width and height
 */
function check_image_sizes( $false, $attachment_id, $size ) {
	global $_wp_additional_image_sizes, $current_screen;
	
	if( ! wp_attachment_is_image( $attachment_id ) )
		return false;
	
	if( 1 == get_post_meta( $attachment_id, '_image_size_check', true ) && isset( $current_screen ) && 'upload.php' != $current_screen->parent_file )
		return false;
	
	$image = get_attached_file( $attachment_id );
	
	if( false === $image || ! file_exists( $image ) )
		return false;
	
	$image_sizes = array(
		'thumbnail' => array(
			'crop' => get_option( 'thumbnail_crop' ),
			'width' => get_option( 'thumbnail_size_w' ),
			'height' => get_option( 'thumbnail_size_h' )
		),
		'medium' => array(
			'crop' => 1,
			'width' => get_option( 'medium_size_w' ),
			'height' => get_option( 'medium_size_h' )
		)
	);
	
	$image_sizes = array_merge( $image_sizes, ( array ) $_wp_additional_image_sizes );
	$metadata = wp_get_attachment_metadata( $attachment_id );
	$upload_path = wp_upload_dir();
	$path = str_replace( wp_basename( $metadata['file'] ), '', $metadata['file'] );
	
	foreach( $image_sizes as $size_name => $size_meta ) {
		$image_size = $metadata['sizes'][ $size_name ];
		
		if( $image_size['width'] == $size_meta['width'] 
			&& $image_size['height'] == $size_meta['height'] 
			&& 1 == $size_meta['crop'] )
			continue;
		
		// delete previous image sizes
		$imageinfo = pathinfo( $image );
		foreach ( glob( str_replace( ".{$imageinfo['extension']}", "-*x*.{$imageinfo['extension']}", $image ) ) as $filename )
			@unlink( $filename );
		
		require_once( ABSPATH . '/wp-admin/includes/image.php' );
		
		set_time_limit( 900 ); // 5 minutes
		$metadata = wp_generate_attachment_metadata( $attachment_id, $image );
		
		if( !$metadata || is_wp_error( $metadata ) )
			return false;
		
		wp_update_attachment_metadata( $attachment_id, $metadata );
		
		break;
	}
	
	update_post_meta( $attachment_id, '_image_size_check', 1 );
	
	if( !is_string( $size ) )
		return false;
	
	if( 'full' == $size ) {
		$image = array( 'file' => $metadata['file'], 'width' => $metadata['width'], 'height' => $metadata['height'] );
		$path = '';
	}
	else
		$image = $metadata['sizes'][ $size ];
	
	$image['file'] = $upload_path['baseurl'] . '/' . $path . $image['file'];
	return array_values( $image );
}
add_filter( 'image_downsize', 'check_image_sizes', 10, 3 );
