<?php
/**
 * Edit functionality
 *
 * @package wpmp
 */

namespace WPMP\Modules\Edit;

use WPMP\Module;
use \stdClass;
use \WP_Error;

/**
 * Edit module
 */
class Edit extends Module {
	/**
	 * Setup module
	 */
	public function setup() {
		add_action( 'wp_ajax_image-editor', [ $this, 'ajax_image_editor' ], 0 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_wpmp_edit_preview', [ $this, 'edit_preview' ] );
		add_filter( 'wp_image_resize_identical_dimensions', '__return_true' );
	}

	/**
	 * Output preview image and incorporate changes
	 *
	 * @param int    $post_id Post ID
	 * @param string $size Image size
	 * @return bool|string
	 */
	public function stream_preview_image( $post_id, $size ) {
		$post = get_post( $post_id );

		wp_raise_memory_limit( 'admin' );

		$img = wp_get_image_editor( _load_image_to_edit_path( $post_id, $size ) );

		if ( is_wp_error( $img ) ) {
			return false;
		}

		$changes = ! empty( $_REQUEST['history'] ) ? json_decode( wp_unslash( $_REQUEST['history'] ) ) : null;
		if ( $changes ) {
			$img = image_edit_apply_changes( $img, $changes );
		}

		// Scale the image.
		$size = $img->get_size();
		$w    = $size['width'];
		$h    = $size['height'];

		$ratio = _image_get_preview_ratio( $w, $h );
		$w2    = max( 1, $w * $ratio );
		$h2    = max( 1, $h * $ratio );

		if ( is_wp_error( $img->resize( $w2, $h2 ) ) ) {
			return false;
		}

		return wp_stream_image( $img, $post->post_mime_type, $post_id );
	}

	/**
	 * Ajax handler for image editor previews. Copied from core
	 */
	public function edit_preview() {
		$post_id = intval( $_GET['postid'] );
		if ( empty( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( -1 );
		}

		check_ajax_referer( "image_editor-$post_id" );

		include_once ABSPATH . 'wp-admin/includes/image-edit.php';

		$size = ( ! empty( $_GET['size'] ) ) ? $_GET['size'] : 'full';

		if ( ! $this->stream_preview_image( $post_id, $size ) ) {
			wp_die( -1 );
		}

		wp_die();
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'wpmp-edit', WPMP_URL . '/dist/js/edit.js', [], WPMP_VERSION, true );

		$args = [
			'nonce' => wp_create_nonce( 'wpmp_edit' ),
		];

		wp_localize_script(
			'wpmp-edit',
			'wpmpEdit',
			$args
		);

		wp_enqueue_style( 'wpmp-edit', WPMP_URL . '/dist/css/edit-styles.css', [], WPMP_VERSION );
	}

	/**
	 * Core overwritten ajax action for displaying edit code
	 *
	 * @return void
	 */
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
				$msg = $this->save_image( $attachment_id );
				if ( ! empty( $msg->error ) ) {
					wp_send_json_error( $msg );
				}

				wp_send_json_success( $msg );
				break;
			case 'scale':
				$msg = $this->save_image( $attachment_id );

				if ( ! empty( $_REQUEST['target'] ) ) {
					$_POST['size'] = $_REQUEST['target'];
				}
				break;
			case 'restore':
				$msg = $this->restore_image( $attachment_id, ( ! empty( $_REQUEST['target'] ) && 'all' !== $_REQUEST['target'] ) ? $_REQUEST['target'] : null );
				break;
		}

		$size = ( ! empty( $_POST['size'] ) && 'all' !== $_POST['size'] ) ? $_POST['size'] : null;

		ob_start();
		$this->image_editor( $attachment_id, $msg, $size );
		$html = ob_get_clean();

		if ( ! empty( $msg->error ) ) {
			wp_send_json_error(
				array(
					'message' => $msg,
					'html'    => $html,
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => $msg,
				'html'    => $html,
			)
		);
	}

	/**
	 * Restores the metadata for a given attachment.
	 *
	 * @since 2.9.0
	 *
	 * @param int    $post_id Attachment post ID.
	 * @param string $size Image size optional
	 * @return stdClass Image restoration message object.
	 */
	public function restore_image( $post_id, $size = null ) {
		$meta             = wp_get_attachment_metadata( $post_id );
		$file             = get_attached_file( $post_id );
		$backup_sizes     = get_post_meta( $post_id, '_wp_attachment_backup_sizes', true );
		$old_backup_sizes = $backup_sizes;
		$restored         = false;
		$msg              = new stdClass();

		if ( ! is_array( $backup_sizes ) ) {
			$msg->error = esc_html__( 'Cannot load image metadata.', 'wpmp' );
			return $msg;
		}

		$parts         = pathinfo( $file );
		$suffix        = time() . rand( 100, 999 );

		if ( empty( $size ) || 'full' === $size ) {
			if ( isset( $backup_sizes['full-orig'] ) && is_array( $backup_sizes['full-orig'] ) ) {
				$data = $backup_sizes['full-orig'];

				if ( $parts['basename'] !== $data['file'] ) {
					$backup_sizes[ "full-$suffix" ] = array(
						'width'  => $meta['width'],
						'height' => $meta['height'],
						'file'   => $parts['basename'],
					);
				}

				$restored_file = path_join( $parts['dirname'], $data['file'] );
				$restored      = update_attached_file( $post_id, $restored_file );

				$meta['file']   = _wp_relative_upload_path( $restored_file );
				$meta['width']  = $data['width'];
				$meta['height'] = $data['height'];
			}
		} else {
			$restored = true;
		}

		$process_sizes = [];

		if ( empty( $sizes ) ) {
			$process_sizes = get_intermediate_image_sizes();
		} elseif ( 'full' !== $size ) {
			$process_sizes[] = $size;
		}

		if ( ! empty( $process_sizes ) ) {
			foreach ( $process_sizes as $process_size ) {
				if ( isset( $backup_sizes[ "$process_size-orig" ] ) ) {
					$data = $backup_sizes[ "$process_size-orig" ];
					if ( isset( $meta['sizes'][ $process_size ] ) && $meta['sizes'][ $process_size ]['file'] !== $data['file'] ) {
						$backup_sizes[ "$process_size-{$suffix}" ] = $meta['sizes'][ $process_size ];
					}

					$meta['sizes'][ $process_size ] = $data;
				} else {
					unset( $meta['sizes'][ $process_size ] );
				}
			}
		}

		if ( ! wp_update_attachment_metadata( $post_id, $meta ) ||
			( $old_backup_sizes !== $backup_sizes && ! update_post_meta( $post_id, '_wp_attachment_backup_sizes', $backup_sizes ) ) ) {

			$msg->error = esc_html__( 'Cannot save image metadata.', 'wpmp' );
			return $msg;
		}

		if ( ! $restored ) {
			$msg->error = esc_html__( 'Image metadata is inconsistent.', 'wpmp' );
		} else {
			$msg->msg = esc_html__( 'Image restored successfully.', 'wpmp' );
		}

		return $msg;
	}


	/**
	 * Saves image to post, along with enqueued changes
	 * in `$_REQUEST['history']`. Copied from core `wp_save_image`
	 *
	 * @param int $post_id Attachment post ID.
	 * @return stdClass
	 */
	public function save_image( $post_id ) {
		$_wp_additional_image_sizes = wp_get_additional_image_sizes();

		$return  = new stdClass();
		$success = false;
		$delete  = false;
		$scaled  = false;
		$nocrop  = false;
		$post    = get_post( $post_id );
		$meta    = wp_get_attachment_metadata( $post_id );
		$path    = get_attached_file( $post_id );
		$dirname = pathinfo( $path, PATHINFO_DIRNAME );
		$target  = ! empty( $_REQUEST['target'] ) ? preg_replace( '/[^a-z0-9_-]+/i', '', $_REQUEST['target'] ) : '';

		if ( 'all' !== $target ) {
			if ( empty( $meta['sizes'] ) || empty( $meta['sizes'][ $target ] ) || empty( $meta['sizes'][ $target ]['file'] ) ) {
				return new WP_Error( 'bad-image', 'Image size does not exist' );
			}
		}

		$editor_path = ( 'all' === $target ) ? _load_image_to_edit_path( $post_id, 'full' ) : $dirname . '/' . $meta['sizes'][ $target ]['file'];

		$img = wp_get_image_editor( $editor_path );
		if ( is_wp_error( $img ) ) {
			$return->error = esc_js( esc_html__( 'Unable to create new image.', 'wpmp' ) );
			return $return;
		}

		$fwidth  = ! empty( $_REQUEST['fwidth'] ) ? intval( $_REQUEST['fwidth'] ) : 0;
		$fheight = ! empty( $_REQUEST['fheight'] ) ? intval( $_REQUEST['fheight'] ) : 0;
		$scale   = ! empty( $_REQUEST['do'] ) && 'scale' == $_REQUEST['do'];

		if ( $scale && $fwidth > 0 && $fheight > 0 ) {
			$size = $img->get_size();
			$s_x   = $size['width'];
			$s_y   = $size['height'];

			// Check if it has roughly the same w / h ratio.
			$diff = round( $s_x / $s_y, 2 ) - round( $fwidth / $fheight, 2 );
			if ( -0.1 < $diff && $diff < 0.1 ) {
				// Scale the full size image.
				if ( $img->resize( $fwidth, $fheight ) ) {
					$scaled = true;
				}
			}

			if ( ! $scaled ) {
				$return->error = esc_js( esc_html__( 'Error while saving the scaled image. Please reload the page and try again.', 'wpmp' ) );
				return $return;
			}
		} elseif ( ! empty( $_REQUEST['history'] ) ) {
			$changes = json_decode( wp_unslash( $_REQUEST['history'] ) );
			if ( $changes ) {
				$img = image_edit_apply_changes( $img, $changes );
			}
		} else {
			$return->error = esc_js( esc_html__( 'Nothing to save, the image has not changed.', 'wpmp' ) );
			return $return;
		}

		$backup_sizes = get_post_meta( $post->ID, '_wp_attachment_backup_sizes', true );

		if ( ! is_array( $meta ) ) {
			$return->error = esc_js( esc_html__( 'Image data does not exist. Please re-upload the image.', 'wpmp' ) );
			return $return;
		}

		if ( ! is_array( $backup_sizes ) ) {
			$backup_sizes = array();
		}

		// Generate new filename.

		$basename = pathinfo( $path, PATHINFO_BASENAME );
		$ext      = pathinfo( $path, PATHINFO_EXTENSION );
		$filename = pathinfo( $path, PATHINFO_FILENAME );
		$suffix   = time() . rand( 100, 999 );

		while ( true ) {
			$filename     = preg_replace( '/-e([0-9]+)$/', '', $filename );
			$filename    .= "-e{$suffix}";
			$new_filename = "{$filename}.{$ext}";
			$new_path     = "{$dirname}/$new_filename";
			if ( file_exists( $new_path ) ) {
				$suffix++;
			} else {
				break;
			}
		}

		// Save the full-size file, also needed to create sub-sizes.
		if ( ! wp_save_image_file( $new_path, $img, $post->post_mime_type, $post_id ) ) {
			$return->error = esc_js( esc_html__( 'Unable to save the image.', 'wpmp' ) );
			return $return;
		}

		if ( 'all' === $target ) {
			$tag = false;
			if ( isset( $backup_sizes['full-orig'] ) ) {
				if ( $backup_sizes['full-orig']['file'] !== $basename ) {
					$tag = "full-$suffix";
				}
			} else {
				$tag = 'full-orig';
			}

			if ( $tag ) {
				$backup_sizes[ $tag ] = array(
					'width'  => $meta['width'],
					'height' => $meta['height'],
					'file'   => $basename,
				);
			}

			$success = ( $path === $new_path ) || update_attached_file( $post_id, $new_path );

			$meta['file'] = _wp_relative_upload_path( $new_path );

			$size           = $img->get_size();
			$meta['width']  = $size['width'];
			$meta['height'] = $size['height'];

			if ( $success ) {
				$sizes = get_intermediate_image_sizes();
			}

			$return->fw = $meta['width'];
			$return->fh = $meta['height'];

			if ( isset( $sizes ) ) {
				$_sizes = array();

				foreach ( $sizes as $size ) {
					$tag = false;

					if ( isset( $meta['sizes'][ $size ] ) ) {
						if ( isset( $backup_sizes[ "$size-orig" ] ) ) {
							if ( $backup_sizes[ "$size-orig" ]['file'] != $meta['sizes'][ $size ]['file'] ) {
								$tag = "$size-$suffix";
							}
						} else {
							$tag = "$size-orig";
						}

						if ( $tag ) {
							$backup_sizes[ $tag ] = $meta['sizes'][ $size ];
						}
					}

					if ( isset( $_wp_additional_image_sizes[ $size ] ) ) {
						$width  = intval( $_wp_additional_image_sizes[ $size ]['width'] );
						$height = intval( $_wp_additional_image_sizes[ $size ]['height'] );
						$crop   = ( $nocrop ) ? false : $_wp_additional_image_sizes[ $size ]['crop'];
					} else {
						$height = get_option( "{$size}_size_h" );
						$width  = get_option( "{$size}_size_w" );
						$crop   = ( $nocrop ) ? false : get_option( "{$size}_crop" );
					}

					$_sizes[ $size ] = array(
						'width'  => $width,
						'height' => $height,
						'crop'   => $crop,
					);
				}

				$meta['sizes'] = array_merge( $meta['sizes'], $img->multi_resize( $_sizes ) );
			}
		} else {

			if ( isset( $meta['sizes'][ $target ] ) ) {
				$tag = false;

				if ( isset( $backup_sizes[ "$target-orig" ] ) ) {
					if ( $backup_sizes[ "$target-orig" ]['file'] !== $meta['sizes'][ $target ]['file'] ) {
						$tag = "$target-$suffix";
					}
				} else {
					$tag = "$target-orig";
				}

				if ( $tag ) {
					$backup_sizes[ $tag ] = $meta['sizes'][ $target ];
				}
			}

			$meta['sizes'][ $target ] = [
				'width'     => $img->get_size()['width'],
				'height'    => $img->get_size()['height'],
				'file'      => $filename . '-' . $img->get_size()['width'] . 'x' . $img->get_size()['height'] . '.' . $ext,
				'mime-type' => $post->post_mime_type,
			];

			rename( $new_path, $dirname . '/' . $filename . '-' . $img->get_size()['width'] . 'x' . $img->get_size()['height'] . '.' . $ext );

			$success = true;
		}

		unset( $img );

		if ( $success ) {
			wp_update_attachment_metadata( $post_id, $meta );
			update_post_meta( $post_id, '_wp_attachment_backup_sizes', $backup_sizes );

			if ( 'thumbnail' === $target || 'all' === $target || 'full' === $target ) {
				// Check if it's an image edit from attachment edit screen.
				if ( ! empty( $_REQUEST['context'] ) && 'edit-attachment' == $_REQUEST['context'] ) {
					$thumb_url         = wp_get_attachment_image_src( $post_id, array( 900, 600 ), true );
					$return->thumbnail = $thumb_url[0];
				} else {
					$file_url = wp_get_attachment_url( $post_id );
					if ( ! empty( $meta['sizes']['thumbnail'] ) ) {
						$thumb             = $meta['sizes']['thumbnail'];
						$return->thumbnail = path_join( dirname( $file_url ), $thumb['file'] );
					} else {
						$return->thumbnail = "$file_url?w=128&h=128";
					}
				}
			}
		} else {
			$delete = true;
		}

		if ( $delete ) {
			wp_delete_file( $new_path );
		}

		$return->msg = esc_js( esc_html__( 'Image saved', 'wpmp' ) );
		return $return;
	}


	/**
	 * Loads the WP image-editing interface. Copied from core function
	 *
	 * @param int         $post_id Attachment post ID.
	 * @param bool|object $msg     Optional. Message to display for image editor updates or errors.
	 *                             Default false.
	 * @param string      $size Image size being edited
	 */
	public function image_editor( $post_id, $msg = false, $size = null ) {
		$nonce     = wp_create_nonce( "image_editor-$post_id" );
		$meta      = wp_get_attachment_metadata( $post_id );
		$thumb     = image_get_intermediate_size( $post_id, 'thumbnail' );
		$sub_sizes = isset( $meta['sizes'] ) && is_array( $meta['sizes'] );

		$sizes = get_intermediate_image_sizes();
		if ( isset( $meta['width'], $meta['height'] ) ) {
			$big = max( $meta['width'], $meta['height'] );
		} else {
			die( esc_html__( 'Image data does not exist. Please re-upload the image.' ) );
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

		$size_width  = $meta['width'];
		$size_height = $meta['height'];

		if ( ! empty( $size ) && ! empty( $meta['sizes'] ) && ! empty( $meta['sizes'][ $size ] ) ) {
			$size_width  = $meta['sizes'][ $size ]['width'];
			$size_height = $meta['sizes'][ $size ]['height'];
		}

		$target = 'all';

		if ( ! empty( $size ) ) {
			$target = $size;
		}

		?>
		<span class="wpmp-image-target" id="imgedit-save-target-<?php echo (int) $post_id; ?>"><input name="imgedit-target-<?php echo (int) $post_id; ?>" checked="checked" type="radio" value="<?php echo esc_attr( $target ); ?>" /></span>
		<div class="imgedit-wrap wp-clearfix">
			<div id="imgedit-panel-<?php echo (int) $post_id; ?>">
				<div class="imgedit-settings">
					<div class="imgedit-group">
						<div class="imgedit-group-top edit-mode">
							<h2><?php esc_html_e( 'Edit Mode', 'wpmp' ); ?></h2>
							<label>
								<input onchange="imageEdit.onEditModeChange(this.value)" type="radio" <?php checked( empty( $size ) ); ?> value="all" name="edit_type">
								<?php esc_html_e( 'Edit all image sizes', 'wpmp' ); ?>
							</label>
							<label>
								<input onchange="imageEdit.onEditModeChange(this.value)" type="radio" <?php checked( ! empty( $size ) ); ?> value="individual" name="edit_type">
								<?php esc_html_e( 'Edit individual image size', 'wpmp' ); ?>
							</label>
							<select onchange="imageEdit.onSizeChange(this.value)" class="wpmp-image-size <?php if ( $size ) : ?>show<?php endif; ?>">
								<option value="full"><?php esc_html_e( 'Full Size', 'wpmp' ); ?></option>
								<?php foreach ( $meta['sizes'] as $size_option => $size_info ) : ?>
									<option <?php selected( $size === $size_option ); ?>><?php echo esc_html( $size_option ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<div class="imgedit-group">
						<div class="imgedit-group-top">
							<h2><?php esc_html_e( 'Scale Image', 'wpmp' ); ?></h2>
							<button type="button" class="dashicons dashicons-editor-help imgedit-help-toggle" onclick="imageEdit.toggleHelp(this);return false;" aria-expanded="false"><span class="screen-reader-text"><?php esc_html_e( 'Scale Image Help', 'wpmp' ); ?></span></button>
							<div class="imgedit-help">
								<p><?php esc_html_e( 'You can proportionally scale the image. For best results, scaling should be done before you crop, flip, or rotate. Images can only be scaled down, not up.', 'wpmp' ); ?></p>
							</div>
							<?php if ( isset( $size_width, $size_height ) ) : ?>
							<p>
								<?php
									printf(
										/* translators: %s: Image width and height in pixels. */
										__( 'Size dimensions %s', 'wpmp' ),
										'<span class="imgedit-original-dimensions">' . (int) $size_width . ' &times; ' . (int) $size_height . '</span>'
									);
									?>
							</p>
							<?php endif ?>
							<div class="imgedit-submit">
								<fieldset class="imgedit-scale">
									<input type="hidden" name="wpmp-current-width-<?php echo (int) $post_id; ?>" value="<?php echo (int) $size_width; ?>">
									<input type="hidden" name="wpmp-current-height-<?php echo (int) $post_id; ?>" value="<?php echo (int) $size_height; ?>">

									<legend><?php _e( 'New dimensions:' ); ?></legend>
									<div class="nowrap">
										<label for="imgedit-scale-width-<?php echo (int) $post_id; ?>" class="screen-reader-text"><?php _e( 'scale width' ); ?></label>
										<input type="text" id="imgedit-scale-width-<?php echo (int) $post_id; ?>" onkeyup="imageEdit.scaleChanged(<?php echo (int) $post_id; ?>, 1, this)" onblur="imageEdit.scaleChanged(<?php echo (int) $post_id; ?>, 1, this)" value="<?php echo (int) $size_width; ?>" />
										<span class="imgedit-separator" aria-hidden="true">&times;</span>
										<label for="imgedit-scale-height-<?php echo (int) $post_id; ?>" class="screen-reader-text"><?php _e( 'scale height' ); ?></label>
										<input type="text" id="imgedit-scale-height-<?php echo (int) $post_id; ?>" onkeyup="imageEdit.scaleChanged(<?php echo (int) $post_id; ?>, 0, this)" onblur="imageEdit.scaleChanged(<?php echo (int) $post_id; ?>, 0, this)" value="<?php echo (int) $size_height; ?>" />
										<span class="imgedit-scale-warn" id="imgedit-scale-warn-<?php echo $post_id; ?>">!</span>
										<div class="imgedit-scale-button-wrapper"><input id="imgedit-scale-button" type="button" onclick="imageEdit.action(<?php echo "$post_id, '$nonce'"; ?>, 'scale', '<?php echo esc_js( $target ); ?>')" class="button button-primary" value="<?php esc_attr_e( 'Scale' ); ?>" /></div>
									</div>
								</fieldset>
							</div>
						</div>
					</div>
					<div class="imgedit-group">
						<div class="imgedit-group-top">
							<h2><?php esc_html_e( 'Image Crop', 'wpmp' ); ?></h2>
							<button type="button" class="dashicons dashicons-editor-help imgedit-help-toggle" onclick="imageEdit.toggleHelp(this);return false;" aria-expanded="false"><span class="screen-reader-text"><?php esc_html_e( 'Image Crop Help', 'wpmp' ); ?></span></button>
							<div class="imgedit-help">
								<p><?php esc_html_e( 'To crop the image, click on it and drag to make your selection.', 'wpmp' ); ?></p>
								<p><strong><?php _e( 'Crop Aspect Ratio', 'wpmp' ); ?></strong><br />
									<?php esc_html_e( 'The aspect ratio is the relationship between the width and height. You can preserve the aspect ratio by holding down the shift key while resizing your selection. Use the input box to specify the aspect ratio, e.g. 1:1 (square), 4:3, 16:9, etc.', 'wpmp' ); ?>
								</p>
								<p><strong><?php esc_html_e( 'Crop Selection', 'wpmp' ); ?></strong><br />
									<?php esc_html_e( 'Once you have made your selection, you can adjust it by entering the size in pixels. The minimum selection size is the thumbnail size as set in the Media settings.', 'wpmp' ); ?>
								</p>
							</div>
						</div>
						<fieldset class="imgedit-crop-ratio">
							<legend><?php esc_html_e( 'Aspect ratio:', 'wpmp' ); ?></legend>
							<div class="nowrap">
								<label for="imgedit-crop-width-<?php echo (int) $post_id; ?>" class="screen-reader-text"><?php esc_html_e( 'crop ratio width', 'wpmp' ); ?></label>
								<input type="text" id="imgedit-crop-width-<?php echo (int) $post_id; ?>" onkeyup="imageEdit.setRatioSelection(<?php echo (int) $post_id; ?>, 0, this)" onblur="imageEdit.setRatioSelection(<?php echo (int) $post_id; ?>, 0, this)" />
								<span class="imgedit-separator" aria-hidden="true">:</span>
								<label for="imgedit-crop-height-<?php echo (int) $post_id; ?>" class="screen-reader-text"><?php esc_html_e( 'crop ratio height', 'wpmp' ); ?></label>
								<input type="text" id="imgedit-crop-height-<?php echo (int) $post_id; ?>" onkeyup="imageEdit.setRatioSelection(<?php echo (int) $post_id; ?>, 1, this)" onblur="imageEdit.setRatioSelection(<?php echo (int) $post_id; ?>, 1, this)" />
							</div>
						</fieldset>
						<fieldset id="imgedit-crop-sel-<?php echo (int) $post_id; ?>" class="imgedit-crop-sel">
							<legend><?php esc_html_e( 'Selection:', 'wpmp' ); ?></legend>
							<div class="nowrap">
								<label for="imgedit-sel-width-<?php echo (int) $post_id; ?>" class="screen-reader-text"><?php esc_html_e( 'selection width', 'wpmp' ); ?></label>
								<input type="text" id="imgedit-sel-width-<?php echo (int) $post_id; ?>" onkeyup="imageEdit.setNumSelection(<?php echo (int) $post_id; ?>, this)" onblur="imageEdit.setNumSelection(<?php echo (int) $post_id; ?>, this)" />
								<span class="imgedit-separator" aria-hidden="true">&times;</span>
								<label for="imgedit-sel-height-<?php echo (int) $post_id; ?>" class="screen-reader-text"><?php esc_html_e( 'selection height', 'wpmp' ); ?></label>
								<input type="text" id="imgedit-sel-height-<?php echo (int) $post_id; ?>" onkeyup="imageEdit.setNumSelection(<?php echo (int) $post_id; ?>, this)" onblur="imageEdit.setNumSelection(<?php echo (int) $post_id; ?>, this)" />
							</div>
						</fieldset>
					</div>
					<?php
					$show_restore      = false;
					$show_size_restore = false;

					if ( ! empty( $backup_sizes ) ) {
						$show_restore = true;
					}

					if ( 'all' !== $target ) {
						if ( ! empty( $meta['sizes'] ) && ! empty( $meta['sizes'][ $target ] ) ) {
							$tag = '-orig';
							if ( preg_match( '#\-e[0-9]+#', $meta['sizes'][ $target ]['file'] ) ) {
								$show_size_restore = true;
							}
						}
					}
					?>

					<?php if ( $show_restore ) : ?>
						<div class="imgedit-group">

							<div class="imgedit-group-top">
								<h2><?php esc_html_e( 'Restore' ); ?></h2>
								<div class="imgedit-submit">
									<input type="button" onclick="imageEdit.action(<?php echo (int) $post_id . ", '" . esc_attr( $nonce ) . "'"; ?>, 'restore')" class="button button-primary imgedit-restore-btn" value="<?php esc_attr_e( 'Restore all image sizes', 'wpmp' ); ?>" />
								</div>
								<?php if ( $show_size_restore ) : ?>
									<div class="imgedit-submit">
										<input type="button" onclick="imageEdit.action(<?php echo (int) $post_id . ", '" . esc_attr( $nonce ) . "'"; ?>, 'restore', '<?php echo esc_attr( $target ); ?>')" class="button button-primary imgedit-restore-btn" value="<?php echo esc_attr( sprintf( esc_attr__( 'Restore %s image size', 'wpmp' ), $target ) ); ?>" />
									</div>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
				<div class="imgedit-panel-content wp-clearfix">
					<?php echo $note; ?>
					<div class="imgedit-menu wp-clearfix">
						<button type="button" onclick="imageEdit.handleCropToolClick( <?php echo (int) $post_id . ", '" . esc_attr( $nonce ) . "'"; ?>, this )" class="imgedit-crop button disabled" disabled><?php esc_html_e( 'Crop', 'wpmp' ); ?></button>
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
						<button type="button" class="imgedit-rleft button" onclick="imageEdit.rotate( 90, <?php echo (int) $post_id . ", '" . esc_attr( $nonce ) . "'"; ?>, this)"><?php esc_html_e( 'Rotate left', 'wpmp' ); ?></button>
						<button type="button" class="imgedit-rright button" onclick="imageEdit.rotate(-90, <?php echo (int) $post_id . ", '" . esc_attr( $nonce ) . "'"; ?>, this)"><?php esc_html_e( 'Rotate right', 'wpmp' ); ?></button>
						<?php
							} else {
								$note_no_rotate = '<p class="note-no-rotate"><em>' . esc_html__( 'Image rotation is not supported by your web host.', 'wpmp' ) . '</em></p>';
								?>
						<button type="button" class="imgedit-rleft button disabled" disabled></button>
						<button type="button" class="imgedit-rright button disabled" disabled></button>
						<?php } ?>
						<button type="button" onclick="imageEdit.flip(1, <?php echo (int) $post_id . ", '" . esc_attr( $nonce ) . "'"; ?>, this)" class="imgedit-flipv button"><?php esc_html_e( 'Flip vertical', 'wpmp' ); ?></button>
						<button type="button" onclick="imageEdit.flip(2, <?php echo (int) $post_id . ", '" . esc_attr( $nonce ) . "'"; ?>, this)" class="imgedit-fliph button"><?php esc_html_e( 'Flip horizontal', 'wpmp' ); ?></button>
						<br class="imgedit-undo-redo-separator" />
						<button type="button" id="image-undo-<?php echo (int) $post_id; ?>" onclick="imageEdit.undo(<?php echo (int) $post_id . ", '" . esc_attr( $nonce ) . "'"; ?>, this)" class="imgedit-undo button disabled" disabled><?php esc_html_e( 'Undo', 'wpmp' ); ?></button>
						<button type="button" id="image-redo-<?php echo (int) $post_id; ?>" onclick="imageEdit.redo(<?php echo (int) $post_id . ", '" . esc_attr( $nonce ) . "'"; ?>, this)" class="imgedit-redo button disabled" disabled><?php esc_html_e( 'Redo', 'wpmp' ); ?></button>
						<?php echo wp_kses_post( $note_no_rotate ); ?>
					</div>
					<input type="hidden" id="imgedit-sizer-<?php echo (int) $post_id; ?>" value="<?php echo $sizer; ?>" />
					<input type="hidden" id="imgedit-history-<?php echo (int) $post_id; ?>" value="" />
					<input type="hidden" id="imgedit-undone-<?php echo (int) $post_id; ?>" value="0" />
					<input type="hidden" id="imgedit-selection-<?php echo (int) $post_id; ?>" value="" />
					<input type="hidden" id="imgedit-x-<?php echo (int) $post_id; ?>" value="<?php echo isset( $meta['width'] ) ? (int) $meta['width'] : 0; ?>" />
					<input type="hidden" id="imgedit-y-<?php echo (int) $post_id; ?>" value="<?php echo isset( $meta['height'] ) ? (int) $meta['height'] : 0; ?>" />
					<div id="imgedit-crop-<?php echo (int) $post_id; ?>" class="imgedit-crop-wrap">
						<?php
						$preview_size = ( empty( $size ) ) ? 'full' : $size;
						?>
						<img id="image-preview-<?php echo (int) $post_id; ?>" onload="imageEdit.imgLoaded('<?php echo (int) $post_id; ?>')" src="<?php echo esc_url( admin_url( 'admin-ajax.php', 'relative' ) ); ?>?action=wpmp_edit_preview&amp;_ajax_nonce=<?php echo esc_attr( $nonce ); ?>&amp;postid=<?php echo (int) $post_id; ?>&amp;rand=<?php echo (int) rand( 1, 99999 ); ?>&amp;size=<?php echo esc_attr( $preview_size ); ?>" alt="" />
					</div>
					<div class="imgedit-submit">
						<input type="button" onclick="imageEdit.close(<?php echo (int) $post_id; ?>, 1)" class="button imgedit-cancel-btn" value="<?php esc_attr_e( 'Cancel', 'wpmp' ); ?>" />
						<input type="button" onclick="imageEdit.save(<?php echo (int) $post_id . ", '" . esc_attr( $nonce ) . "'"; ?>)" disabled="disabled" class="button button-primary imgedit-submit-btn" value="<?php esc_attr_e( 'Save', 'wpmp' ); ?>" />
					</div>
				</div>
			</div>
			<div class="imgedit-wait" id="imgedit-wait-<?php echo (int) $post_id; ?>"></div>
			<div class="hidden" id="imgedit-leaving-<?php echo (int) $post_id; ?>"><?php esc_html_e( "There are unsaved changes that will be lost. 'OK' to continue, 'Cancel' to return to the Image Editor.", 'wpmp' ); ?></div>
		</div>
		<?php
	}

}
