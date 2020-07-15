<?php
/**
 * Folders functionality
 *
 * @package wpmp
 */

namespace WPMP\Modules\Folders;

use WPMP\Module;
use WPMP\Utils;
use \WP_Term_Query;
use \WP_Query;

/**
 * Folders class
 */
class Folders extends Module {
	const TAXONOMY_SLUG = 'wpmp-folder';

	/**
	 * Setup module
	 */
	public function setup() {
		if ( empty( Utils\get_settings( 'enable_folders' ) ) ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'init', [ $this, 'register_taxonomy' ] );
		add_action( 'wp_ajax_wpmp_create_folder', [ $this, 'ajax_create_folder' ] );
		add_action( 'wp_ajax_wpmp_delete_folder', [ $this, 'ajax_delete_folder' ] );
		add_action( 'wp_ajax_wpmp_get_folders', [ $this, 'ajax_get_folders' ] );
		add_action( 'wp_ajax_wpmp_get_folder_path', [ $this, 'ajax_get_folder_path' ] );
		add_action( 'wp_ajax_wpmp_move_image', [ $this, 'ajax_move_image' ] );
		add_action( 'wp_ajax_wpmp_rename_folder', [ $this, 'ajax_rename_folder' ] );
		add_filter( 'ajax_query_attachments_args', [ $this, 'filter_attachment_query' ] );
		add_action( 'attachment_submitbox_misc_actions', [ $this, 'folder_meta_box' ], 11 );
	}

	/**
	 * Get folder path for a post
	 *
	 * @param int $post_id Post ID
	 * @return array
	 */
	public function get_folder_path( $post_id ) {
		$term = wp_get_object_terms( $post_id, self::TAXONOMY_SLUG );

		if ( empty( $term ) ) {
			return null;
		}

		$terms = [];

		$term = $term[0];

		$terms[] = $term;

		while ( ! empty( $term->parent ) ) {
			$term = get_term( $term->parent, self::TAXONOMY_SLUG );

			if ( empty( $term ) ) {
				break;
			}

			array_unshift( $terms, $term );
		}

		return $terms;
	}

	/**
	 * Get pretty folder path
	 *
	 * @param int $post_id Post ID
	 * @return string
	 */
	public function get_pretty_folder_path( $post_id ) {
		$terms = $this->get_folder_path( $post_id );

		if ( empty( $terms ) ) {
			return null;
		}

		$path = '';

		foreach ( $terms as $term ) {
			if ( ! empty( $path ) ) {
				$path .= ' &gt; ';
			}

			$path .= '<a href="' . esc_url( admin_url( 'upload.php?mode=grid#' . $term->slug ) ) . '">' . esc_html( $term->name ) . '</a>';
		}

		return $path;
	}

	/**
	 * Output folder meta box
	 *
	 * @param WP_Post $post Post object
	 * @return void
	 */
	public function folder_meta_box( $post ) {
		$folder_path = $this->get_pretty_folder_path( $post->ID );
		if ( empty( $folder_path ) ) {
			$folder_path = esc_html__( 'None', 'wpmp' );
		}
		?>
		<div class="misc-pub-section misc-pub-dimensions">
			<?php esc_html_e( 'Folder:', 'wpmp' ); ?> <strong><?php echo wp_kses_post( $folder_path ); ?></strong>
		</div>
		<?php
	}
	/**
	 * Filter attachment query to insert folder tax query
	 *
	 * @param array $query_args Attachment query arguments
	 * @return array
	 */
	public function filter_attachment_query( $query_args ) {
		if ( ! empty( $_POST['query'] ) && ! empty( $_POST['query']['wpmp-folder'] ) ) {
			if ( empty( $query_args['tax_query'] ) ) {
				$query_args['tax_query'] = [];
			}

			$query_args['tax_query'][] = [
				'taxonomy'         => self::TAXONOMY_SLUG,
				'terms'            => [ absint( $_POST['query']['wpmp-folder'] ) ],
				'include_children' => false,
			];
		}

		return $query_args;
	}

	/**
	 * Get unique slug for a folder
	 *
	 * @param string $name Folder name
	 * @param int    $parent Parent folder ID if one
	 * @return string
	 */
	public function create_folder_slug( $name, $parent = 0 ) {
		$term_parents = [];

		while ( true ) {
			if ( empty( $parent ) ) {
				break;
			}

			$parent_term = get_term( $parent, self::TAXONOMY_SLUG );

			if ( empty( $parent_term ) ) {
				break;
			}

			array_unshift( $term_parents, $parent_term->name );

			$parent = $parent_term->parent;
		}

		$slug = '';

		if ( ! empty( $term_parents ) ) {
			foreach ( $term_parents as $parent_name ) {
				$slug .= sanitize_key( $parent_name ) . '-';
			}
		}

		$original_slug = strtolower( $slug . sanitize_key( $name ) );
		$slug          = $original_slug;

		$i = 1;

		while ( true ) {
			$term_exists = get_term_by( 'slug', $slug, self::TAXONOMY_SLUG );

			if ( empty( $term_exists ) ) {
				break;
			}

			$i++;

			$slug = $original_slug . $i;
		}

		return $slug;
	}

	/**
	 * Get folders
	 *
	 * @return array
	 */
	public function get_folders() {
		$args = [
			'taxonomy'     => self::TAXONOMY_SLUG,
			'hide_empty'   => false,
			'number'       => 'all',
			'count'        => true,
			'hierarchical' => true,
		];

		$folder_query = new WP_Term_Query( $args );

		if ( empty( $folder_query->terms ) ) {
			return [];
		}

		$all_terms = [];

		foreach ( $folder_query->terms as $key => $term ) {
			$all_terms[] = $this->format_folder( $term );
		}

		return $all_terms;
	}

	/**
	 * Get folders for ajax
	 */
	public function ajax_get_folders() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmp_folders' ) ) {
			wp_send_json_error();
		}

		$parent = null;

		if ( ! empty( $_POST['parent'] ) ) {
			$parent = absint( $_POST['parent'] );
		}

		$folders = $this->get_folders();

		wp_send_json_success( $folders );
	}

	/**
	 * Create a folder
	 */
	public function ajax_create_folder() {
		if ( empty( $_POST['name'] ) ) {
			wp_send_json_error();
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmp_folders' ) ) {
			wp_send_json_error();
		}

		$name   = filter_input( INPUT_POST, 'name', FILTER_SANITIZE_STRING );
		$parent = filter_input( INPUT_POST, 'parent', FILTER_SANITIZE_NUMBER_INT );

		$args = [
			'slug' => $this->create_folder_slug( $name, $parent ),
		];

		if ( ! empty( $parent ) ) {
			$args['parent'] = $parent;
		}

		$new_term = wp_insert_term(
			$name,
			self::TAXONOMY_SLUG,
			$args
		);

		wp_send_json_success( $this->format_folder( $new_term['term_id'] ) );
	}

	/**
	 * Move image to a folder
	 */
	public function ajax_move_image() {
		if ( empty( $_POST['imageId'] ) || empty( $_POST['folderId'] ) ) {
			wp_send_json_error();
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmp_folders' ) ) {
			wp_send_json_error();
		}

		$image_id  = filter_input( INPUT_POST, 'imageId', FILTER_SANITIZE_NUMBER_INT );
		$folder_id = filter_input( INPUT_POST, 'folderId', FILTER_SANITIZE_NUMBER_INT );

		wp_set_object_terms( (int) $image_id, [ (int) $folder_id ], self::TAXONOMY_SLUG, false );

		wp_send_json_success( $this->get_folders() );
	}

	/**
	 * Get folder path
	 */
	public function ajax_get_folder_path() {
		if ( empty( $_POST['postId'] ) ) {
			wp_send_json_error();
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmp_folders' ) ) {
			wp_send_json_error();
		}

		$post_id = filter_input( INPUT_POST, 'postId', FILTER_SANITIZE_NUMBER_INT );

		wp_send_json_success( $this->get_pretty_folder_path( $post_id ) );
	}

	/**
	 * Rename a folder
	 */
	public function ajax_rename_folder() {
		if ( empty( $_POST['name'] ) || empty( $_POST['folderId'] ) ) {
			wp_send_json_error();
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmp_folders' ) ) {
			wp_send_json_error();
		}

		$folder_id = filter_input( INPUT_POST, 'folderId', FILTER_SANITIZE_NUMBER_INT );
		$name      = filter_input( INPUT_POST, 'name', FILTER_SANITIZE_STRING );

		$term = get_term( $_POST['folderId'], self::TAXONOMY_SLUG );

		wp_update_term(
			$folder_id,
			self::TAXONOMY_SLUG,
			[
				'name' => $name,
				'slug' => $this->create_folder_slug( $name, $term->parent ),
			]
		);

		wp_send_json_success( $this->format_folder( $folder_id ) );
	}

	/**
	 * Delete folder via ajax
	 */
	public function ajax_delete_folder() {
		if ( empty( $_POST['id'] ) ) {
			wp_send_json_error();
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wpmp_folders' ) ) {
			wp_send_json_error();
		}

		$id   = filter_input( INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT );
		$term = get_term( $id, self::TAXONOMY_SLUG );

		if ( empty( $term ) ) {
			wp_send_json_error();
		}

		$deleted = [ (int) $id ];

		$children = new WP_Term_Query(
			[
				'taxonomy'     => self::TAXONOMY_SLUG,
				'hide_empty'   => false,
				'number'       => 'all',
				'count'        => false,
				'hierarchical' => true,
				'child_of'     => $_POST['id'],
			]
		);

		if ( ! empty( $children->terms ) ) {
			foreach ( $children->terms as $term ) {
				$deleted[] = (int) $term->term_id;
			}
		}

		$attachment_query = new WP_Query(
			[
				'post_type'              => 'attachment',
				'posts_per_page'         => 3000,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'tax_query'              => [
					[
						'taxonomy' => self::TAXONOMY_SLUG,
						'field'    => 'id',
						'terms'    => $deleted,
					],
				],
			]
		);

		if ( ! empty( $attachment_query->posts ) ) {
			foreach ( $attachment_query->posts as $attachment_id ) {
				if ( ! empty( $_POST['deleteChildMedia'] ) ) {
					wp_delete_post( $attachment_id, true );
				} else {
					$terms    = wp_get_object_terms( $attachment_id, self::TAXONOMY_SLUG );
					$term_ids = wp_list_pluck( $terms, 'term_id' );

					foreach ( $term_ids as $key => $term_id ) {
						if ( in_array( (int) $term_id, $deleted, true ) ) {
							unset( $term_ids[ $key ] );
						}
					}

					wp_set_object_terms( $attachment_id, $term_ids, self::TAXONOMY_SLUG, false );
				}
			}
		}

		foreach ( $deleted as $term_id ) {
			wp_delete_term( $term_id, self::TAXONOMY_SLUG );
		}

		wp_send_json_success( $deleted );
	}

	/**
	 * Return formatted version of term
	 *
	 * @param \WP_Term $term Term to format
	 * @return array
	 */
	public function format_folder( $term ) {
		if ( is_numeric( $term ) ) {
			$term = get_term( $term, self::TAXONOMY_SLUG );
		}

		if ( empty( $term ) ) {
			return null;
		}

		$folder       = (array) $term;
		$folder['id'] = (int) $folder['term_id'];

		return $folder;
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts() {
		global $pagenow;

		wp_enqueue_media();

		wp_enqueue_script( 'wpmp-folders', WPMP_URL . '/dist/js/folders.js', [ 'wp-edit-post', 'media-views', 'jquery', 'media-models', 'react', 'wp-i18n' ], WPMP_VERSION, true );

		wp_localize_script(
			'wpmp-folders',
			'wpmpFolders',
			[
				'nonce'       => wp_create_nonce( 'wpmp_folders' ),
				'pluginUrl'   => WPMP_URL,
				'libraryPage' => 'upload.php' === $pagenow,

			]
		);

		wp_enqueue_style( 'wpmp-folders', WPMP_URL . '/dist/css/folders-styles.css', [], WPMP_VERSION );
	}

	/**
	 * Register folder taxonomy
	 */
	public function register_taxonomy() {
		$args = array(
			'hierarchical'          => true,
			'show_ui'               => false,
			'show_admin_column'     => false,
			'query_var'             => false,
			'rewrite'               => false,
			'public'                => false,
			'update_count_callback' => '_update_generic_term_count',
		);

		register_taxonomy( self::TAXONOMY_SLUG, [ 'attachment' ], $args );
	}
}
