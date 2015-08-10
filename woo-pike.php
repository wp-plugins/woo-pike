<?php

/**
 * @package Woo Pike
 * @version 1.0.0
 */
/**
 * Plugin Name: Woo Pike
 * Plugin URI: http://woothemes.com/products/woocommerce-pike/
 * Description: Tor/I2P/Proxy orders sometimes are connected with fraudulent transactions. We will try to deliver you accurate information regarding this networks via our service pike.hqpeak.com .
 * Version: 1.0.0
 * Author: HQPeAk
 * Author URI: http://hqpeak.com/
 * Developer: Hqpeak
 * Developer URI: http://hqpeak.com/
 * Text Domain: woo-pike
 * Domain Path: /languages
 *
 * Copyright: © 2009-2015 HQPeAk.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	
//this should be moved to the setting tab as confugurable option
define( 'PIKE_SERVICE_KEY', 'demoFREEpike' );

//add column to the orders tables
add_filter( 'manage_edit-shop_order_columns', 'pike_add_col' );
function pike_add_col( $columns ){
    		
    $new_columns = ( is_array( $columns )) ? $columns : array();
    
    unset( $new_columns['order_date'] );
	unset( $new_columns['order_total'] );
	unset( $new_columns['order_actions'] );
	
	$new_columns['pike-ip'] = 'Pike';
    $new_columns['order_date'] = $columns['order_date'];
    $new_columns['order_total'] = $columns['order_total'];
    $new_columns['order_actions'] = $columns['order_actions'];
    
    return $new_columns;	
}

//fill it with values
add_action( 'manage_shop_order_posts_custom_column', 'pike_col_values', 2 );
function pike_col_values( $column ){
    	
    global $post;
    
    $data = get_post_meta( $post->ID );
	if ( $column == 'pike-ip' ) {    
        echo (isset($data['_is_pike']) ? '<strong>'.$data['_is_pike'][0].'</strong>' : '–');
    }
}

//check order ip
add_action( 'woocommerce_checkout_update_order_meta', 'pike_check_ip', 2 );
function pike_check_ip( $order_id ){
		
	$ipa = get_post_meta($order_id, '_customer_ip_address', true);
	$proxy = FALSE;	
	$torf = FALSE;			
	$tmp = check_proxy();
	if ( $tmp !== FALSE ){
		if ( $tmp === TRUE ){
			$proxy = TRUE;
		}else{
			$proxy = TRUE;
			$processed = array();
			foreach ( $tmp as $ipt ){
				if ( ! in_array( $ipt, $processed ) && ! $torf ){
					$processed[] = $ipt;			
					$response = "";
					$c = 0;
					while( ! is_array( $response ) && $c < 3 ){
						$response = wp_remote_post( 'http://pike.hqpeak.com/api/ping.php',
						array(
							'body' => array( 'ip' => $ipa, 'key' => PIKE_SERVICE_KEY )
						));
						if( ! is_wp_error( $response ) && is_array( $response ) && isset( $response['body']) ) {
						$body = $response['body'];
						if ( 0 == $body ){
							break;
						}elseif( 1 == $body ){
							$torf = true;
							update_post_meta( $order_id, '_is_pike', 'Tor via Proxy' );	
							$note = 'Client IP='.$ipa.' seems to be a proxy server that have forwarded traffic for Tor user IP='.$ipt.' <a href="http://pike.hqpeak.com">More info</a>';
							$comment_post_ID        = $order_id;
							$comment_author_url     = '';
							$comment_content        = $note;
							$comment_agent          = 'WooCommerce';
							$comment_type           = 'order_note';
							$comment_parent         = 0;
							$comment_approved       = 1;
							$commentdata            = apply_filters( 'woocommerce_new_order_note_data', compact( 'comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content', 'comment_agent', 'comment_type', 'comment_parent', 'comment_approved' ), array( 'order_id' => $order_id, 'is_customer_note' => 0 ) );
							$comment_id = wp_insert_comment( $commentdata );
							add_comment_meta( $comment_id, 'is_customer_note', 0 );
							break;
						}
					}
					$c++;					
					}
					if ( $torf ) break;	
				}		
			}
		}	
	}
	if ( $ipa != "" && filter_var( $ipa, FILTER_VALIDATE_IP ) !== FALSE && ! $torf ){
		$response = "";
		$c = 0;
		while( ! is_array( $response ) && $c < 3){
			$response = wp_remote_post( 'http://pike.hqpeak.com/api/ping.php',
			array(
				'body' => array( 'ip' => $ipa, 'key' => PIKE_SERVICE_KEY )
			));
			if( ! is_wp_error( $response ) && is_array( $response ) && isset( $response['body'] )) {
				$body = $response['body'];
				if ( 0 == $body ){
					$msg = '';
					if ( $proxy ){
						$msg = 'Proxy';			
					}else{
						$msg = 'No';
					}
					update_post_meta( $order_id, '_is_pike', $msg );
					break;
				}elseif( 1 == $body ){
					$msg = '';
					if ( $proxy ){
						$msg = 'Proxy + Tor';		
					}else{
						$msg = 'Tor';
					}
					update_post_meta( $order_id, '_is_pike', $msg );	
					$note = 'Client IP='.$ipa.' seems to be associated with tor network'.' <a href="http://pike.hqpeak.com">More info</a>';
					$comment_post_ID        = $order_id;
					$comment_author_url     = '';
					$comment_content        = $note;
					$comment_agent          = 'WooCommerce';
					$comment_type           = 'order_note';
					$comment_parent         = 0;
					$comment_approved       = 1;
					$commentdata            = apply_filters( 'woocommerce_new_order_note_data', compact( 'comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content', 'comment_agent', 'comment_type', 'comment_parent', 'comment_approved' ), array( 'order_id' => $order_id, 'is_customer_note' => 0 ) );
					$comment_id = wp_insert_comment( $commentdata );
					add_comment_meta( $comment_id, 'is_customer_note', 0 );
					break;
				}else{
					update_post_meta( $order_id, '_is_pike', 'Re-check' );
				}
			}
			$c++;
		}
	}else{
		update_post_meta( $order_id, '_is_pike', 'No IP logged' );	
	}
}
//orderby column
add_filter( 'manage_edit-shop_order_sortable_columns', 'pike_order_by');
function pike_order_by( $columns ){
	$custom = array(
			'pike-ip'=>'_is_pike'	
		);
		return $custom;
}

//orderby query
add_action( 'pre_get_posts', 'pike_event_column_orderby' );
function pike_event_column_orderby( $query ) {
    if( ! is_admin() )
        return;
    $orderby = $query->get( 'orderby' );
    if( '_is_pike' == $orderby ) {
        $query->set( 'meta_key', '_is_pike' );
        $query->set( 'orderby', 'meta_value' );
    }
}

//check headers for semi anonimous proxy servers
function check_proxy(){
	$out = array();
	$pc = FALSE;	
	$proxy_headers = array(
		'HTTP_VIA',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_FORWARDED_FOR',
		'HTTP_X_FORWARDED',
		'HTTP_FORWARDED',
		'HTTP_CLIENT_IP',
		'HTTP_FORWARDED_FOR_IP',
		'VIA',
		'X_FORWARDED_FOR',
		'FORWARDED_FOR',
		'X_FORWARDED',
		'FORWARDED',
		'CLIENT_IP',
		'FORWARDED_FOR_IP',
		'HTTP_PROXY_CONNECTION'
	    );
	foreach( $proxy_headers as $x ){
		if ( isset($_SERVER[ $x ]) && filter_var( $ipa, FILTER_VALIDATE_IP ) !== FALSE ){
			$out[] = $_SERVER[ $x ];		
			$pc = TRUE;		
		}elseif(isset($_SERVER[ $x ])){
			$pc = TRUE;		
		}
	}
	if ( sizeof( $out ) > 0 ) { return $out; }else{ return $pc; }
}

}//end checking if woocommerce is active 
