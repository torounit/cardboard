<?php
/**
 * Plugin Name: Cardboard
 * Version: 0.1-alpha
 * Description: PLUGIN DESCRIPTION HERE
 * Author: YOUR NAME HERE
 * Author URI: YOUR SITE HERE
 * Plugin URI: PLUGIN SITE HERE
 * Text Domain: cardboard
 * Domain Path: /languages
 * @package Cardboard
 */

// register_activation_hook( __FILE__, 'cardboard_init' );
// add_action( 'init', 'cardboard_init' );
//
// function cardboard_init() {
// 	add_rewrite_endpoint( 'cardboard', EP_ROOT );
// }

$cardboard = new CardBoard();

class Cardboard
{
	const NS = 'http://ns.google.com/photos/1.0/panorama/';

	public function __construct()
	{
		add_action( "plugins_loaded", array( $this, "plugins_loaded" ) );
	}

	public function plugins_loaded()
	{
		add_action( "add_attachment", array( $this, "add_attachment" ) );
		add_filter( "image_send_to_editor", array( $this, "image_send_to_editor" ), 10, 8 );
		add_action( "wp_head", array( $this, "wp_head" ) );
		add_action( "wp_enqueue_scripts", array( $this, "wp_enqueue_scripts" ) );

		add_shortcode( 'cardboard', function( $p, $content ) {
			if ( intval( $p['id'] ) ) {
				$src = wp_get_attachment_image_src( $p['id'], 'full' );
				if ( $src ) {
					return sprintf(
						'<div class="cardboard" data-image="%s"><a class="full-screen"><span class="dashicons dashicons-editor-expand"></span></a></div>',
						esc_url( $src[0] )
					);
				}
			}
		} );
	}

	public function add_attachment( $post_id )
	{
		$src = get_attached_file( $post_id );
		if ( self::is_panorama_photo( $src ) ) {
			update_post_meta( $post_id, 'is_panorama_photo', true );
		}
	}

	public function image_send_to_editor( $html, $post_id, $caption, $title, $align, $url, $size, $alt )
	{
		if ( get_post_meta( $post_id, 'is_panorama_photo' ) && ( ! is_array( $size ) && 'full' === $size ) ) {
			return '[cardboard id="' . esc_attr( $post_id ) . '"]';
		} else {
			return $html;
		}
	}

	public function wp_head()
	{
		?>
		<style>
		.cardboard
		{
			position: relative;
		}
		.cardboard .full-screen
		{
			display: block;
			position: absolute;
			bottom: 8px;
			right: 8px;
			z-index: 999;
			color: #ffffff;
			text-decoration: none;
			border: none;
		}
		</style>
		<?php
	}

	public function wp_enqueue_scripts()
	{
		wp_enqueue_script(
			"three-js",
			plugins_url( 'three/three.min.js', __FILE__ ),
			array(),
			time(),
			true
		);
		wp_enqueue_script(
			"three-plugins-js",
			plugins_url( 'three/three-plugins.min.js', __FILE__ ),
			array( 'three-js' ),
			time(),
			true
		);
		wp_enqueue_script(
			"cardboard-js",
			plugins_url( 'js/cardboard.js', __FILE__ ),
			array( 'jquery','three-plugins-js' ),
			time(),
			true
		);
	}

	/**
	 * Check exif and xmp meta data for detecting is it a paorama or not.
	 * @param  string  $image A path to image.
	 * @return boolean        Is image panorama photo or not.
	 */
	public static function is_panorama_photo( $image )
	{
		$content = file_get_contents( $image );
		$xmp_data_start = strpos( $content, '<x:xmpmeta' );
		$xmp_data_end   = strpos( $content, '</x:xmpmeta>' );
		$xmp_length     = $xmp_data_end - $xmp_data_start;
		if ( $xmp_length ) {
			$xmp_data = substr( $content, $xmp_data_start, $xmp_length + 12 );
			$xmp = simplexml_load_string( $xmp_data );
			$xmp = $xmp->children( "http://www.w3.org/1999/02/22-rdf-syntax-ns#" );
			$xmp = $xmp->RDF->Description;
			if ( "TRUE" === strtoupper( (string) $xmp->attributes( self::NS )->UsePanoramaViewer ) ) {
				return true;
			} elseif ( "TRUE" === strtoupper( (string) $xmp->children( self::NS )->UsePanoramaViewer ) ) {
				return true;
			}
		}

		$models = array(
			'RICOH THETA',
		);
		$models = apply_filters( 'cardboard_exif_models', $models );

		$exif = exif_read_data( $image );
		if ( $exif && ! empty( $exif['Model'] ) ) {
			foreach ( $models as $model ) {
				if ( false !== strpos( strtoupper( $exif['Model'] ), strtoupper( $model ) ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
