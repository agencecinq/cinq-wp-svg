<?php
/**
 * Plugin Name: CINQ SVG
 * Plugin URI: https://github.com/agencecinq/cinq-wp-svg
 * Description: Lets users who can post unfiltered HTML upload SVG files, and gives them dimensions. Files are not sanitized. No settings screen.
 * Version: 1.0.0
 * Author: CINQ
 * Author URI: https://agencecinq.com/
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: cinq-wp-svg
 *
 * @package CinqWpSvg
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'upload_mimes', 'cinq_wp_svg_upload_mimes', 10, 2 );

/**
 * Allow SVG uploads for users who can post unfiltered HTML.
 *
 * An SVG can hold scripts, and the plugin does not sanitize it: it is left to
 * the users WordPress already trusts with raw HTML.
 *
 * @param array<string, string> $mimes Allowed mime types, keyed by extension.
 * @param int|WP_User|null      $user  User the types are checked for. Current user when null.
 * @return array<string, string>
 */
function cinq_wp_svg_upload_mimes( array $mimes, $user = null ): array {
	$user = $user ?? wp_get_current_user();

	if ( user_can( $user, 'unfiltered_html' ) ) {
		$mimes['svg'] = 'image/svg+xml';
	}

	return $mimes;
}

add_filter( 'wp_generate_attachment_metadata', 'cinq_wp_svg_attachment_metadata', 10, 2 );

/**
 * Store the width and height of an uploaded SVG.
 *
 * WordPress reads dimensions from raster images only. The viewBox wins over
 * the width and height attributes, as in Timber.
 *
 * @param array<string, mixed> $metadata      Attachment metadata.
 * @param int                  $attachment_id Attachment ID.
 * @return array<string, mixed>
 */
function cinq_wp_svg_attachment_metadata( array $metadata, int $attachment_id ): array {
	if ( 'image/svg+xml' !== get_post_mime_type( $attachment_id ) ) {
		return $metadata;
	}

	$file = get_attached_file( $attachment_id );
	$svg  = simplexml_load_file( $file, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING );

	if ( false === $svg ) {
		return $metadata;
	}

	$attributes = $svg->attributes();
	$view_box   = preg_split( '/[\s,]+/', trim( (string) $attributes['viewBox'] ) );

	if ( 4 === count( $view_box ) ) {
		[ , , $width, $height ] = $view_box;
	} else {
		$width  = (string) $attributes['width'];
		$height = (string) $attributes['height'];
	}

	$metadata['width']  = (int) round( (float) $width );
	$metadata['height'] = (int) round( (float) $height );

	return $metadata;
}

add_filter( 'image_downsize', 'cinq_wp_svg_image_downsize', 10, 2 );

/**
 * Give an SVG the same source at every image size.
 *
 * WordPress does not count SVG as an image, so `image_downsize()` returns
 * false for it, and `wp_get_attachment_image_src()` with it.
 *
 * @param array|false $downsize      Short-circuit value.
 * @param int         $attachment_id Attachment ID.
 * @return array|false Image URL, width, height, and whether it is a resized copy.
 */
function cinq_wp_svg_image_downsize( $downsize, int $attachment_id ) {
	if ( 'image/svg+xml' !== get_post_mime_type( $attachment_id ) ) {
		return $downsize;
	}

	$url      = wp_get_attachment_url( $attachment_id );
	$metadata = wp_get_attachment_metadata( $attachment_id );
	$width    = $metadata['width'] ?? 0;
	$height   = $metadata['height'] ?? 0;

	return array( $url, $width, $height, false );
}

add_action( 'post-upload-ui', 'cinq_wp_svg_upload_notice' );

/**
 * Warn under the upload area that SVG files are not sanitized.
 *
 * Printed when the current user may upload SVG, on the Add Media File screen
 * and in the media modal.
 *
 * @return void
 */
function cinq_wp_svg_upload_notice(): void {
	$mimes = get_allowed_mime_types();

	if ( ! in_array( 'image/svg+xml', $mimes, true ) ) {
		return;
	}
	?>
	<p class="cinq-wp-svg-notice">
		<?php esc_html_e( 'SVG files are uploaded as they are, without sanitization: a script inside one runs when the file is opened in a browser. Only upload SVG files from a source you trust.', 'cinq-wp-svg' ); ?>
	</p>
	<?php
}
