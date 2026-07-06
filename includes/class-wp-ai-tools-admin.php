<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    AITOOLS_Text_Generator
 * @subpackage AITOOLS_Text_Generator/admin
 * @author     codersantosh <codersantosh@gmail.com>
 */
class WP_AI_Tools_Admin {

	private static $instance = null;

	/**
	 * The ID of this plugin.
	 * Used on slug of plugin menu.
	 * Used on Root Div ID for React too.
	 *
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version The version of this plugin.
	 */
	public function __construct( $plugin_name = null, $version = null ) {
		$this->plugin_name = $plugin_name ?? 'wp-ai-tools';
		$this->version     = $version ?? AITOOLS_VERSION;

		add_action( 'init', array( $this, 'register_ajax_handlers' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_resources' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_admin_scripts' ) );
		add_action( 'wp_ajax_generate_alt_text', array( $this, 'generate_alt_text_ajax' ) );
		add_action( 'wp_ajax_nopriv_generate_alt_text', array( $this, 'generate_alt_text_ajax' ) );
		add_action( 'generate_alt_text_for_image', array( $this, 'generate_alt_text_for_image_function' ) );

		if ( aitools_get_options( 'on_upload_alt_text' ) ) {
			add_action( 'add_attachment', array( $this, 'generate_alt_text_on_upload' ) );
		}

		/*Register Settings*/
		add_action( 'rest_api_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_filter( 'bulk_actions-upload', array( $this, 'add_bulk_action_option' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_action' ), 10, 3 );

		add_action( 'admin_notices', array( $this, 'show_bulk_processing_notice' ) );
	}

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Add Admin Page Menu page.
	 */
	public function add_admin_menu() {
		add_options_page(
			esc_html__( 'AI Tools', 'wp-ai-tools' ),
			esc_html__( 'AI Tools', 'wp-ai-tools' ),
			'manage_options',
			$this->plugin_name,
			array( $this, 'add_setting_root_div' )
		);
	}

	/**
	 * Add Root Div For React.
	 */
	public function add_setting_root_div() {
		echo '<div id="' . esc_attr( $this->plugin_name ) . '"></div>';
	}

	/**
	 * Register the CSS/JavaScript Resources for the admin area.
	 *
	 * Use Condition to Load it Only When it is Necessary.
	 */
	public function enqueue_resources() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$admin_scripts_bases = array(
			'settings_page_' . $this->plugin_name,
			'toplevel_page_' . $this->plugin_name,
		);

		if ( ! (
			( isset( $screen->base ) && in_array( $screen->base, $admin_scripts_bases ) ) ||
			( isset( $screen->id ) && in_array( $screen->id, $admin_scripts_bases ) )
		) ) {
			return;
		}

		// Enqueue WordPress media scripts
		wp_enqueue_media();


		/*Scripts dependency files*/
		$deps_file  = AITOOLS_PATH . 'build/admin/settings.asset.php';
		$dependency = [];
		$version    = AITOOLS_VERSION;

		if ( file_exists( $deps_file ) ) {
			$deps_file  = require( $deps_file );
			$dependency = $deps_file['dependencies'];
			$version    = $deps_file['version'];
		}

		wp_enqueue_script( $this->plugin_name, esc_url( AITOOLS_URL . 'build/admin/settings.js' ), $dependency, $version, true );
		wp_enqueue_style( $this->plugin_name, esc_url( AITOOLS_URL . 'build/admin/settings.css' ), array(), $version );
	}

	public function add_bulk_action_option( $bulk_actions ) {
		$bulk_actions['generate_alt_text'] = esc_html__( 'Generate Alt Text', 'wp-ai-tools' );

		return $bulk_actions;
	}

	public function enqueue_media_admin_scripts() {
		// only in media and post edit pages
		if ( ! ( 'post.php' === $GLOBALS['pagenow'] || 'post-new.php' === $GLOBALS['pagenow'] || 'upload.php' === $GLOBALS['pagenow'] ) ) {
			return;
		}
		$media_button_asset_path = AITOOLS_PATH . 'build/admin/media-button.asset.php';
		$media_button_version    = AITOOLS_VERSION;
		$media_button_deps       = array();

		if ( file_exists( $media_button_asset_path ) ) {
			$media_button_asset   = require_once $media_button_asset_path;
			$media_button_version = $media_button_asset['version'];
			$media_button_deps    = $media_button_asset['dependencies'];
		}

		wp_enqueue_script( 'wp-ai-tools-media', esc_url( AITOOLS_URL . 'build/admin/media-button.js' ), $media_button_deps, $media_button_version, true );

		$blocks_asset_path = AITOOLS_PATH . 'build/admin/blocks.asset.php';
		$blocks_version    = AITOOLS_VERSION;
		$blocks_deps       = array();

		if ( file_exists( $blocks_asset_path ) ) {
			$blocks_asset   = require_once $blocks_asset_path;
			$blocks_version = $blocks_asset['version'];
			$blocks_deps    = $blocks_asset['dependencies'];
		}

		wp_enqueue_script(
			'alt-gen-gutenberg-blocks',
			esc_url( AITOOLS_URL . 'build/admin/blocks.js' ),
			$blocks_deps,
			$blocks_version
		);

		$nonce = wp_create_nonce( 'alt_gen_ajax_nonce' );
		wp_localize_script( 'wp-ai-tools-media', 'wpAITools', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => $nonce ) );
	}

	public function show_bulk_processing_notice() {
		if ( get_option( 'aitools_alt_gen_is_processing' ) ) {
			$total    = get_option( 'aitools_alt_gen_processing_total', 0 );
			$current  = get_option( 'aitools_alt_gen_processing_current', 0 );
			$progress = $total > 0 ? round( ( $current / $total ) * 100 ) : 0;

			?>
			<div class="notice notice-info is-dismissible aatg-progress-notice">
				<p>
					<strong><?php _e( 'AI Alt Text Generation in Progress', 'wp-ai-tools' ); ?></strong>
				</p>
				<div style="width: 100%; background-color: #e0e0e0; border-radius: 4px; overflow: hidden; margin-bottom: 10px;">
					<div style="width: <?php echo $progress; ?>%; background-color: #0073aa; color: white; text-align: center; line-height: 20px;">
						<?php echo $progress; ?>%
					</div>
				</div>
				<p>
					<?php printf( __( 'Processed %d of %d images.', 'wp-ai-tools' ), $current, $total ); ?>
					<button id="aatg-manual-trigger" class="button" style="margin-left: 10px;"><?php _e( 'Process Next Batch', 'wp-ai-tools' ); ?></button>
					<span class="spinner" style="float: none; margin-left: 5px;"></span>
				</p>
			</div>
			<script>
				jQuery(document).ready(function ($) {
					var notice = $('.aatg-progress-notice');
					var triggerButton = $('#aatg-manual-trigger');
					var spinner = notice.find('.spinner');

					function updateProgress(response) {
						if (response.is_processing) {
							var progress = response.total_items > 0 ? Math.round((response.current_item / response.total_items) * 100) : 0;
							notice.find('.notice-info div > div').css('width', progress + '%').text(progress + '%');
							notice.find('p:first-of-type + p').html('Processed ' + response.current_item + ' of ' + response.total_items + ' images. <button id="aatg-manual-trigger" class="button" style="margin-left: 10px;">Process Next Batch</button>');
						} else {
							notice.removeClass('notice-info').addClass('notice-success').html('<p>Bulk generation complete!</p>');
							clearInterval(interval);
							setTimeout(function () {
								notice.fadeOut();
							}, 5000);
						}
					}

					var interval = setInterval(function () {
						$.get(ajaxurl, {action: 'aitools_processing_status'}, updateProgress);
					}, 10000);

					$(document).on('click', '#aatg-manual-trigger', function () {
						spinner.addClass('is-active');
						triggerButton.prop('disabled', true);

						$.post(ajaxurl, {action: 'aitools_process_next_batch'}, function (response) {
							updateProgress(response);
							spinner.removeClass('is-active');
							triggerButton.prop('disabled', false);
						});
					});
				});
			</script>
			<?php
		}
	}

	// AJAX Handler Function
	public function generate_alt_text_ajax() {
		try {
			// Check nonce
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'alt_gen_ajax_nonce' ) ) {
				wp_send_json_error( 'Security check failed.' );

				return;
			}

			$attachment_id = null;

			if ( ! empty( $_POST['post_id'] ) ) {
				$attachment_id = absint( $_POST['post_id'] );
			}

			if ( ! $attachment_id ) {
				wp_send_json_error( 'Could not find a valid image ID in the request.' );

				return;
			}

			// Generate alt text using AI provider
			$alt_text = $this->generate_alt_text_with_ai( $attachment_id, array( 'source' => 'single' ) );
			if ( empty( $alt_text ) ) {
				wp_send_json_error( 'The AI provider failed to generate alt text.' );

				return;
			}

			// Save the generated alt text (with add-on hooks).
			$alt_text = aitools_save_generated_alt_text( $attachment_id, $alt_text, array( 'source' => 'single' ) );

			wp_send_json_success( $alt_text );

		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}


	public function handle_bulk_action( $redirect_to, $doaction, $post_ids ) {
		if ( $doaction === 'generate_alt_text' ) {

			if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
				return add_query_arg( 'aitools_error', 'no_images_selected', $redirect_to );
			}

			// Immediately process the first image to provide instant feedback
			if ( ! empty( $post_ids ) ) {
				$first_image_id = array_shift( $post_ids );
				$this->generate_alt_text_for_image_function( $first_image_id );
				// Update the count to reflect one image processed
				update_option( 'aitools_alt_gen_processing_current', 1 );
			}

			// If there are more images, store them in the transient for the background processor
			if ( ! empty( $post_ids ) ) {
				set_transient( 'aitools_bulk_generation_ids', $post_ids, HOUR_IN_SECONDS );
				wp_schedule_single_event( time() + 5, 'ai_process_media_batch', array( 10 ) );
			} else {
				// If only one image was processed, we're done
				update_option( 'aitools_alt_gen_is_processing', false );
			}

			// Add a query arg to notify the user
			$redirect_to = add_query_arg( 'aitools_message', 'bulk_started', $redirect_to );
		}

		return $redirect_to;
	}

	public function generate_alt_text_for_image_function( $post_id ) {
		// Generate alt text using the AI provider
		$alt_text = $this->generate_alt_text_with_ai( $post_id, array( 'source' => 'bulk' ) );

		// Save the generated alt text (with add-on hooks).
		aitools_save_generated_alt_text( $post_id, $alt_text, array( 'source' => 'bulk' ) );
	}

	public function generate_alt_text_on_upload( $attachment_id ) {
		// Check if the attachment is an image
		if ( wp_attachment_is_image( $attachment_id ) ) {
			// Generate alt text using AI provider
			$alt_text = $this->generate_alt_text_with_ai( $attachment_id, array( 'source' => 'upload' ) );

			// Save the generated alt text (with add-on hooks).
			aitools_save_generated_alt_text( $attachment_id, $alt_text, array( 'source' => 'upload' ) );
		}
	}

	public function generate_alt_text_with_ai( $attachment_id, $context = array() ) {
		try {
			$context = array_merge( array( 'attachment_id' => $attachment_id ), $context );
			// Get settings
			$options       = get_option( 'aitools_options' );
			$provider      = $options['ai_provider'] ?? 'openai';

			if ( empty( $options['api_key'] ) ) {
				return '';
			}

			$image_base64 = '';
			if ( function_exists( 'aitools_get_image_base64_for_attachment' ) ) {
				$image_base64 = aitools_get_image_base64_for_attachment( $attachment_id );
			}

			if ( empty( $image_base64 ) ) {
				return '';
			}

			// Get prompt and language from settings
			$prompt   = $context['prompt'] ?? $options['prompt'] ?? 'Create a SEO optimized alt text for this image. Don\'t include quotes and keep it informative and concise.';
			$language = $options['language'] ?? 'English';

			// Use the provider factory to generate alt text
			$result = AITOOLS_Provider_Factory::generate_alt_text(
				$provider,
				$image_base64,
				$prompt,
				$language,
				$options['api_key'],
				$context
			);

			return $result['success'] ? $result['alt_text'] : '';

		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Register settings.
	 * Common callback function of rest_api_init and admin_init
	 * Schema: http://json-schema.org/draft-04/schema#
	 *
	 * Add your own settings fields here
	 */
	public function register_settings() {
		$defaults = aitools_default_options();
		register_setting(
			'aitools_settings_group',
			'aitools_options',
			array(
				'type'         => 'object',
				'default'      => $defaults,
				'show_in_rest' => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							/*===Settings===*/
							/*Settings -> General*/
							'ai_provider'        => array(
								'type'              => 'string',
								'default'           => $defaults['ai_provider'],
								'sanitize_callback' => 'sanitize_text_field',
							),
							'api_key'            => array(
								'type'              => 'string',
								'default'           => $defaults['api_key'],
								'sanitize_callback' => 'sanitize_text_field', // Sanitize the API key
							),
							'on_upload_alt_text' => array(
								'type'    => 'boolean',
								'default' => $defaults['on_upload_alt_text']
							),
							'all_alt_text'       => array(
								'type'    => 'boolean',
								'default' => $defaults['all_alt_text']
							),
							'set_title'          => array(
								'type'    => 'boolean',
								'default' => $defaults['set_title']
							),
							'set_caption'        => array(
								'type'    => 'boolean',
								'default' => $defaults['set_caption']
							),
							'set_description'    => array(
								'type'    => 'boolean',
								'default' => $defaults['set_description']
							),
							'prompt'             => array(
								'type'    => 'string',
								'default' => $defaults['prompt']
							),
							'language'           => array(
								'type'    => 'string',
								'default' => $defaults['language']
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Register the AJAX handlers
	 */
	public function register_ajax_handlers() {
		add_action( 'wp_ajax_aitools_processing_status', array( $this, 'get_processing_status_ajax' ) );
		add_action( 'wp_ajax_aitools_process_next_batch', array( $this, 'process_next_batch_ajax' ) );
	}

	public function get_processing_status_ajax() {
		$status = [
			'is_processing' => get_option( 'aitools_alt_gen_is_processing', false ),
			'total_items'   => get_option( 'aitools_alt_gen_processing_total', 0 ),
			'current_item'  => get_option( 'aitools_alt_gen_processing_current', 0 ),
		];
		wp_send_json( $status );
	}

	public function process_next_batch_ajax() {
		// Manually run the next batch from the REST point logic
		$rest_point = new WP_AI_Tools_Restpoint();
		$rest_point->process_media_batch( 10 ); // Process a batch of 10

		// Return the latest status
		$this->get_processing_status_ajax();
	}
}
