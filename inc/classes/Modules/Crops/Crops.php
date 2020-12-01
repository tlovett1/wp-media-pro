<?php
/**
 * Credits functionality
 *
 * @package wpmp
 */

namespace WPMP\Modules\Crops;

use WPMP\Module;

/**
 * Crops module
 */
class Crops extends Module {
	/**
	 * Setup module
	 */
	public function setup() {
		add_action( 'wp_ajax_image-editor', [ $this, 'ajax_image_editor' ], 0 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'wpmp-crops', WPMP_URL . '/dist/js/crops.js', [], WPMP_VERSION, true );

		$args = [
			'nonce' => wp_create_nonce( 'wpmp_crops' ),
		];

		wp_localize_script(
			'wpmp-crops',
			'wpmpCrops',
			$args
		);

		//wp_enqueue_style( 'wpmp-taxonomies', WPMP_URL . '/dist/css/taxonomies-styles.css', [], WPMP_VERSION );
	}

	public function ajax_image_editor() {
		$attachment_id = intval( $_POST['postid'] );

		if ( empty( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			wp_die( -1 );
		}

		check_ajax_referer( "image_editor-$attachment_id" );
		include_once ABSPATH . 'wp-admin/includes/image-edit.php';

		$msg = false;
		switch ( $_POST['do'] ) {
			case 'save':
				$msg = wp_save_image( $attachment_id );
				$msg = wp_json_encode( $msg );
				wp_die( $msg );
				break;
			case 'scale':
				$msg = wp_save_image( $attachment_id );
				break;
			case 'restore':
				$msg = wp_restore_image( $attachment_id );
				break;
		}

		image_editor( $attachment_id, $msg );
		wp_die();
	}

}

/**
 * Loads the WP image-editing interface.
 *
 * @since 2.9.0
 *
 * @param int         $post_id Attachment post ID.
 * @param bool|object $msg     Optional. Message to display for image editor updates or errors.
 *                             Default false.
 */
function image_editor( $post_id, $msg = false ) {
	$nonce     = wp_create_nonce( "image_editor-$post_id" );
	$meta      = wp_get_attachment_metadata( $post_id );
	$thumb     = image_get_intermediate_size( $post_id, 'thumbnail' );
	$sub_sizes = isset( $meta['sizes'] ) && is_array( $meta['sizes'] );
	$note      = '';

	$sizes = get_intermediate_image_sizes();
	if ( isset( $meta['width'], $meta['height'] ) ) {
		$big = max( $meta['width'], $meta['height'] );
	} else {
		die( __( 'Image data does not exist. Please re-upload the image.' ) );
	}

	$sizer = $big > 400 ? 400 / $big : 1;

	$backup_sizes = get_post_meta( $post_id, '_wp_attachment_backup_sizes', true );
	$can_restore  = false;
	if ( ! empty( $backup_sizes ) && isset( $backup_sizes['full-orig'], $meta['file'] ) ) {
		$can_restore = wp_basename( $meta['file'] ) !== $backup_sizes['full-orig']['file'];
	}

	if ( $msg ) {
		if ( isset( $msg->error ) ) {
			$note = "<div class='error'><p>$msg->error</p></div>";
		} elseif ( isset( $msg->msg ) ) {
			$note = "<div class='updated'><p>$msg->msg</p></div>";
		}
	}

	?>
	<div class="imgedit-wrap wp-clearfix">
	<div id="imgedit-panel-<?php echo $post_id; ?>">

	<div class="imgedit-settings">
	<div class="imgedit-group">
		<div class="imgedit-group-top">
			<h2><?php _e( 'Image Size' ); ?></h2>
			<select class="wpmp-image-size">
				<option>Full Size</option>
				<?php foreach ( $sizes as $size ) : ?>
					<option><?php echo $size; ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>
	<div class="imgedit-group">
	<div class="imgedit-group-top">
		<h2><?php _e( 'Scale Image' ); ?></h2>
		<button type="button" class="dashicons dashicons-editor-help imgedit-help-toggle" onclick="imageEdit.toggleHelp(this);return false;" aria-expanded="false"><span class="screen-reader-text"><?php esc_html_e( 'Scale Image Help' ); ?></span></button>
		<div class="imgedit-help">
		<p><?php _e( 'You can proportionally scale the original image. For best results, scaling should be done before you crop, flip, or rotate. Images can only be scaled down, not up.' ); ?></p>
		</div>
		<?php if ( isset( $meta['width'], $meta['height'] ) ) : ?>
		<p>
			<?php
			printf(
				/* translators: %s: Image width and height in pixels. */
				__( 'Original dimensions %s' ),
				'<span class="imgedit-original-dimensions">' . $meta['width'] . ' &times; ' . $meta['height'] . '</span>'
			);
			?>
		</p>
		<?php endif ?>
		<div class="imgedit-submit">

		<fieldset class="imgedit-scale">
		<legend><?php _e( 'New dimensions:' ); ?></legend>
		<div class="nowrap">
		<label for="imgedit-scale-width-<?php echo $post_id; ?>" class="screen-reader-text"><?php _e( 'scale width' ); ?></label>
		<input type="text" id="imgedit-scale-width-<?php echo $post_id; ?>" onkeyup="imageEdit.scaleChanged(<?php echo $post_id; ?>, 1, this)" onblur="imageEdit.scaleChanged(<?php echo $post_id; ?>, 1, this)" value="<?php echo isset( $meta['width'] ) ? $meta['width'] : 0; ?>" />
		<span class="imgedit-separator" aria-hidden="true">&times;</span>
		<label for="imgedit-scale-height-<?php echo $post_id; ?>" class="screen-reader-text"><?php _e( 'scale height' ); ?></label>
		<input type="text" id="imgedit-scale-height-<?php echo $post_id; ?>" onkeyup="imageEdit.scaleChanged(<?php echo $post_id; ?>, 0, this)" onblur="imageEdit.scaleChanged(<?php echo $post_id; ?>, 0, this)" value="<?php echo isset( $meta['height'] ) ? $meta['height'] : 0; ?>" />
		<span class="imgedit-scale-warn" id="imgedit-scale-warn-<?php echo $post_id; ?>">!</span>
		<div class="imgedit-scale-button-wrapper"><input id="imgedit-scale-button" type="button" onclick="imageEdit.action(<?php echo "$post_id, '$nonce'"; ?>, 'scale')" class="button button-primary" value="<?php esc_attr_e( 'Scale' ); ?>" /></div>
		</div>
		</fieldset>

		</div>
	</div>
	</div>

	<?php if ( $can_restore ) { ?>

	<div class="imgedit-group">
	<div class="imgedit-group-top">
		<h2><button type="button" onclick="imageEdit.toggleHelp(this);" class="button-link"><?php _e( 'Restore Original Image' ); ?> <span class="dashicons dashicons-arrow-down imgedit-help-toggle"></span></button></h2>
		<div class="imgedit-help imgedit-restore">
		<p>
			<?php
			_e( 'Discard any changes and restore the original image.' );

			if ( ! defined( 'IMAGE_EDIT_OVERWRITE' ) || ! IMAGE_EDIT_OVERWRITE ) {
				echo ' ' . __( 'Previously edited copies of the image will not be deleted.' );
			}
			?>
		</p>
		<div class="imgedit-submit">
		<input type="button" onclick="imageEdit.action(<?php echo "$post_id, '$nonce'"; ?>, 'restore')" class="button button-primary" value="<?php esc_attr_e( 'Restore image' ); ?>" <?php echo $can_restore; ?> />
		</div>
		</div>
	</div>
	</div>

	<?php } ?>

	<div class="imgedit-group">
	<div class="imgedit-group-top">
		<h2><?php _e( 'Image Crop' ); ?></h2>
		<button type="button" class="dashicons dashicons-editor-help imgedit-help-toggle" onclick="imageEdit.toggleHelp(this);return false;" aria-expanded="false"><span class="screen-reader-text"><?php esc_html_e( 'Image Crop Help' ); ?></span></button>

		<div class="imgedit-help">
		<p><?php _e( 'To crop the image, click on it and drag to make your selection.' ); ?></p>

		<p><strong><?php _e( 'Crop Aspect Ratio' ); ?></strong><br />
		<?php _e( 'The aspect ratio is the relationship between the width and height. You can preserve the aspect ratio by holding down the shift key while resizing your selection. Use the input box to specify the aspect ratio, e.g. 1:1 (square), 4:3, 16:9, etc.' ); ?></p>

		<p><strong><?php _e( 'Crop Selection' ); ?></strong><br />
		<?php _e( 'Once you have made your selection, you can adjust it by entering the size in pixels. The minimum selection size is the thumbnail size as set in the Media settings.' ); ?></p>
		</div>
	</div>

	<fieldset class="imgedit-crop-ratio">
		<legend><?php _e( 'Aspect ratio:' ); ?></legend>
		<div class="nowrap">
		<label for="imgedit-crop-width-<?php echo $post_id; ?>" class="screen-reader-text"><?php _e( 'crop ratio width' ); ?></label>
		<input type="text" id="imgedit-crop-width-<?php echo $post_id; ?>" onkeyup="imageEdit.setRatioSelection(<?php echo $post_id; ?>, 0, this)" onblur="imageEdit.setRatioSelection(<?php echo $post_id; ?>, 0, this)" />
		<span class="imgedit-separator" aria-hidden="true">:</span>
		<label for="imgedit-crop-height-<?php echo $post_id; ?>" class="screen-reader-text"><?php _e( 'crop ratio height' ); ?></label>
		<input type="text" id="imgedit-crop-height-<?php echo $post_id; ?>" onkeyup="imageEdit.setRatioSelection(<?php echo $post_id; ?>, 1, this)" onblur="imageEdit.setRatioSelection(<?php echo $post_id; ?>, 1, this)" />
		</div>
	</fieldset>

	<fieldset id="imgedit-crop-sel-<?php echo $post_id; ?>" class="imgedit-crop-sel">
		<legend><?php _e( 'Selection:' ); ?></legend>
		<div class="nowrap">
		<label for="imgedit-sel-width-<?php echo $post_id; ?>" class="screen-reader-text"><?php _e( 'selection width' ); ?></label>
		<input type="text" id="imgedit-sel-width-<?php echo $post_id; ?>" onkeyup="imageEdit.setNumSelection(<?php echo $post_id; ?>, this)" onblur="imageEdit.setNumSelection(<?php echo $post_id; ?>, this)" />
		<span class="imgedit-separator" aria-hidden="true">&times;</span>
		<label for="imgedit-sel-height-<?php echo $post_id; ?>" class="screen-reader-text"><?php _e( 'selection height' ); ?></label>
		<input type="text" id="imgedit-sel-height-<?php echo $post_id; ?>" onkeyup="imageEdit.setNumSelection(<?php echo $post_id; ?>, this)" onblur="imageEdit.setNumSelection(<?php echo $post_id; ?>, this)" />
		</div>
	</fieldset>

	</div>

	<?php
	if ( $thumb && $sub_sizes ) {
		$thumb_img = wp_constrain_dimensions( $thumb['width'], $thumb['height'], 160, 120 );
		?>

	<div class="imgedit-group imgedit-applyto">
	<div class="imgedit-group-top">
		<h2><?php _e( 'Thumbnail Settings' ); ?></h2>
		<button type="button" class="dashicons dashicons-editor-help imgedit-help-toggle" onclick="imageEdit.toggleHelp(this);return false;" aria-expanded="false"><span class="screen-reader-text"><?php esc_html_e( 'Thumbnail Settings Help' ); ?></span></button>
		<div class="imgedit-help">
		<p><?php _e( 'You can edit the image while preserving the thumbnail. For example, you may wish to have a square thumbnail that displays just a section of the image.' ); ?></p>
		</div>
	</div>

	<figure class="imgedit-thumbnail-preview">
		<img src="<?php echo $thumb['url']; ?>" width="<?php echo $thumb_img[0]; ?>" height="<?php echo $thumb_img[1]; ?>" class="imgedit-size-preview" alt="" draggable="false" />
		<figcaption class="imgedit-thumbnail-preview-caption"><?php _e( 'Current thumbnail' ); ?></figcaption>
	</figure>

	<div id="imgedit-save-target-<?php echo $post_id; ?>" class="imgedit-save-target">
	<fieldset>
		<legend><?php _e( 'Apply changes to:' ); ?></legend>

		<span class="imgedit-label">
			<input type="radio" id="imgedit-target-all" name="imgedit-target-<?php echo $post_id; ?>" value="all" checked="checked" />
			<label for="imgedit-target-all"><?php _e( 'All image sizes' ); ?></label>
		</span>

		<span class="imgedit-label">
			<input type="radio" id="imgedit-target-thumbnail" name="imgedit-target-<?php echo $post_id; ?>" value="thumbnail" />
			<label for="imgedit-target-thumbnail"><?php _e( 'Thumbnail' ); ?></label>
		</span>

		<span class="imgedit-label">
			<input type="radio" id="imgedit-target-nothumb" name="imgedit-target-<?php echo $post_id; ?>" value="nothumb" />
			<label for="imgedit-target-nothumb"><?php _e( 'All sizes except thumbnail' ); ?></label>
		</span>
	</fieldset>
	</div>
	</div>

	<?php } ?>

	</div>

	<div class="imgedit-panel-content wp-clearfix">
		<?php echo $note; ?>
		<div class="imgedit-menu wp-clearfix">
			<button type="button" onclick="imageEdit.handleCropToolClick( <?php echo "$post_id, '$nonce'"; ?>, this )" class="imgedit-crop button disabled" disabled><?php esc_html_e( 'Crop' ); ?></button>
			<?php

			// On some setups GD library does not provide imagerotate() - Ticket #11536.
			if ( wp_image_editor_supports(
				array(
					'mime_type' => get_post_mime_type( $post_id ),
					'methods'   => array( 'rotate' ),
				)
			) ) {
				$note_no_rotate = '';
				?>
				<button type="button" class="imgedit-rleft button" onclick="imageEdit.rotate( 90, <?php echo "$post_id, '$nonce'"; ?>, this)"><?php esc_html_e( 'Rotate left' ); ?></button>
				<button type="button" class="imgedit-rright button" onclick="imageEdit.rotate(-90, <?php echo "$post_id, '$nonce'"; ?>, this)"><?php esc_html_e( 'Rotate right' ); ?></button>
				<?php
			} else {
				$note_no_rotate = '<p class="note-no-rotate"><em>' . __( 'Image rotation is not supported by your web host.' ) . '</em></p>';
				?>
				<button type="button" class="imgedit-rleft button disabled" disabled></button>
				<button type="button" class="imgedit-rright button disabled" disabled></button>
			<?php } ?>

			<button type="button" onclick="imageEdit.flip(1, <?php echo "$post_id, '$nonce'"; ?>, this)" class="imgedit-flipv button"><?php esc_html_e( 'Flip vertical' ); ?></button>
			<button type="button" onclick="imageEdit.flip(2, <?php echo "$post_id, '$nonce'"; ?>, this)" class="imgedit-fliph button"><?php esc_html_e( 'Flip horizontal' ); ?></button>

			<br class="imgedit-undo-redo-separator" />
			<button type="button" id="image-undo-<?php echo $post_id; ?>" onclick="imageEdit.undo(<?php echo "$post_id, '$nonce'"; ?>, this)" class="imgedit-undo button disabled" disabled><?php esc_html_e( 'Undo' ); ?></button>
			<button type="button" id="image-redo-<?php echo $post_id; ?>" onclick="imageEdit.redo(<?php echo "$post_id, '$nonce'"; ?>, this)" class="imgedit-redo button disabled" disabled><?php esc_html_e( 'Redo' ); ?></button>
			<?php echo $note_no_rotate; ?>
		</div>

		<input type="hidden" id="imgedit-sizer-<?php echo $post_id; ?>" value="<?php echo $sizer; ?>" />
		<input type="hidden" id="imgedit-history-<?php echo $post_id; ?>" value="" />
		<input type="hidden" id="imgedit-undone-<?php echo $post_id; ?>" value="0" />
		<input type="hidden" id="imgedit-selection-<?php echo $post_id; ?>" value="" />
		<input type="hidden" id="imgedit-x-<?php echo $post_id; ?>" value="<?php echo isset( $meta['width'] ) ? $meta['width'] : 0; ?>" />
		<input type="hidden" id="imgedit-y-<?php echo $post_id; ?>" value="<?php echo isset( $meta['height'] ) ? $meta['height'] : 0; ?>" />

		<div id="imgedit-crop-<?php echo $post_id; ?>" class="imgedit-crop-wrap">
		<img id="image-preview-<?php echo $post_id; ?>" onload="imageEdit.imgLoaded('<?php echo $post_id; ?>')" src="<?php echo admin_url( 'admin-ajax.php', 'relative' ); ?>?action=imgedit-preview&amp;_ajax_nonce=<?php echo $nonce; ?>&amp;postid=<?php echo $post_id; ?>&amp;rand=<?php echo rand( 1, 99999 ); ?>" alt="" />
		</div>

		<div class="imgedit-submit">
			<input type="button" onclick="imageEdit.close(<?php echo $post_id; ?>, 1)" class="button imgedit-cancel-btn" value="<?php esc_attr_e( 'Cancel' ); ?>" />
			<input type="button" onclick="imageEdit.save(<?php echo "$post_id, '$nonce'"; ?>)" disabled="disabled" class="button button-primary imgedit-submit-btn" value="<?php esc_attr_e( 'Save' ); ?>" />
		</div>
	</div>

	</div>
	<div class="imgedit-wait" id="imgedit-wait-<?php echo $post_id; ?>"></div>
	<div class="hidden" id="imgedit-leaving-<?php echo $post_id; ?>"><?php _e( "There are unsaved changes that will be lost. 'OK' to continue, 'Cancel' to return to the Image Editor." ); ?></div>
	</div>
	<?php
}
