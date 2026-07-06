<?php
require_once dirname( __FILE__ ) . '/functions.php';

class WP_AI_Tools_Restpoint {
	private $batch_size = 10;
	private $rewrite_all = false;

	public function __construct() {
		$this->rewrite_all = aitools_get_options( 'all_alt_text' );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'ai_process_media_batch', array( $this, 'process_media_batch' ), 10, 1 );
	}

	public function register_rest_routes() {
		register_rest_route( 'wp-ai-tools/v1', '/start-processing', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'start_processing' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( 'wp-ai-tools/v1', '/process-next', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'process_next_image' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( 'wp-ai-tools/v1', '/processing-status', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_processing_status' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( 'wp-ai-tools/v1', '/is-processing', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'check_processing_status' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( 'wp-ai-tools/v1', '/stop-processing', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'stop_processing' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( 'wp-ai-tools/v1', '/validate-key', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'validate_api_key' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( 'wp-ai-tools/v1', '/settings', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_settings' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			),
		) );

		register_rest_route( 'wp-ai-tools/v1', '/generate-test', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_test_generation' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );

	}

	public function start_processing( WP_REST_Request $request ) {
		try {
			// Get total number of images to process
			$args = array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => - 1,
				'fields'         => 'ids'
			);

			// If not processing all images, only get those without alt text
			if ( ! $this->rewrite_all ) {
				$ids = $this->get_images_without_alt_text_ids();
				if ( ! empty( $ids ) ) {
					$args['post__in'] = $ids;
				} else {
					return new WP_REST_Response( array(
						'status'  => 'error',
						'message' => 'No images found to process'
					), 200 );
				}
			}

			$images_ids   = get_posts( $args );
			$total_images = count( $images_ids );

			if ( $total_images === 0 ) {
				return new WP_REST_Response( array(
					'status'  => 'error',
					'message' => 'No images found to process'
				), 200 );
			}

			// Save target IDs to transient so processing uses a static list and offset works correctly
			set_transient( 'aitools_bulk_generation_ids', $images_ids, DAY_IN_SECONDS );

			// Store processing state
			update_option( 'aitools_alt_gen_is_processing', true );
			update_option( 'aitools_alt_gen_processing_total', $total_images );
			update_option( 'aitools_alt_gen_processing_current', 0 );

			return new WP_REST_Response( array(
				'status'        => 'success',
				'message'       => sprintf( 'Found %d images to process', $total_images ),
				'total_items'   => $total_images,
				'is_processing' => true
			), 200 );

		} catch ( Exception $e ) {
			// Clean up on error
			update_option( 'aitools_alt_gen_is_processing', false );
			update_option( 'aitools_alt_gen_processing_total', 0 );
			update_option( 'aitools_alt_gen_processing_current', 0 );

			return new WP_REST_Response( array(
				'status'  => 'error',
				'message' => 'Internal server error: ' . $e->getMessage()
			), 500 );
		}
	}

	public function process_next_image() {
		try {
			if ( ! get_option( 'aitools_alt_gen_is_processing', false ) ) {
				return new WP_REST_Response( array(
					'status'  => 'error',
					'message' => 'Processing is not active'
				), 200 );
			}

			$current = get_option( 'aitools_alt_gen_processing_current', 0 );
			$total   = get_option( 'aitools_alt_gen_processing_total', 0 );

			if ( $current >= $total ) {
				update_option( 'aitools_alt_gen_is_processing', false );
				update_option( 'aitools_alt_gen_processing_total', 0 );
				update_option( 'aitools_alt_gen_processing_current', 0 );

				return new WP_REST_Response( array(
					'status'  => 'completed',
					'message' => 'All images processed',
					'current' => $current,
					'total'   => $total
				), 200 );
			}

			$args = array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => 1,
				'offset'         => $current
			);

			// Check if we're processing a specific list of IDs from a bulk action
			$bulk_ids = get_transient( 'aitools_bulk_generation_ids' );

			if ( $bulk_ids && is_array( $bulk_ids ) ) {
				$args['post__in'] = $bulk_ids;
				$args['orderby']  = 'post__in';
			} elseif ( ! $this->rewrite_all ) {
				$ids = $this->get_images_without_alt_text_ids();
				if ( empty( $ids ) ) {
					update_option( 'aitools_alt_gen_is_processing', false );
					update_option( 'aitools_alt_gen_processing_total', 0 );
					update_option( 'aitools_alt_gen_processing_current', 0 );
					// Clear transient if it exists
					delete_transient( 'aitools_bulk_generation_ids' );

					return new WP_REST_Response( array(
						'status'  => 'completed',
						'message' => 'No more images to process',
						'current' => $current,
						'total'   => $total
					), 200 );
				}
				$args['post__in'] = $ids;
			}

			$media_items = get_posts( $args );

			if ( empty( $media_items ) ) {
				update_option( 'aitools_alt_gen_is_processing', false );
				update_option( 'aitools_alt_gen_processing_total', 0 );
				update_option( 'aitools_alt_gen_processing_current', 0 );
				// Clear transient if it exists
				delete_transient( 'aitools_bulk_generation_ids' );

				return new WP_REST_Response( array(
					'status'  => 'completed',
					'message' => 'No more images to process',
					'current' => $current,
					'total'   => $total
				), 200 );
			}

			$item = $media_items[0];

			// Generate alt text using AI provider
			$admin_instance = WP_AI_Tools_Admin::get_instance();
			$alt_text       = $admin_instance->generate_alt_text_with_ai( $item->ID, array( 'source' => 'bulk' ) );

			if ( empty( $alt_text ) ) {
				throw new Exception( 'The AI provider failed to generate alt text.' );
			}

			// Save the generated alt text (with add-on hooks).
			aitools_save_generated_alt_text( $item->ID, $alt_text, array( 'source' => 'bulk' ) );
			$current ++;
			update_option( 'aitools_alt_gen_processing_current', $current );

			// If processing is complete, clear the transient
			if ( $current >= $total ) {
				delete_transient( 'aitools_bulk_generation_ids' );
			}

			return new WP_REST_Response( array(
				'status'        => 'success',
				'message'       => 'Image processed successfully',
				'current'       => $current,
				'total'         => $total,
				'is_processing' => true
			), 200 );

		} catch ( Exception $e ) {
			// Skip this image but continue processing
			$current ++;
			update_option( 'aitools_alt_gen_processing_current', $current );

			return new WP_REST_Response( array(
				'status'        => 'error',
				'message'       => $e->getMessage(),
				'current'       => $current,
				'total'         => $total,
				'is_processing' => true
			), 200 );
		}
	}

	public function validate_api_key( WP_REST_Request $request ) {
		$key      = $request->get_param( 'key' );
		$provider = $request->get_param( 'provider' );

		if ( empty( $key ) ) {
			return new WP_REST_Response( array(
				'valid'   => false,
				'message' => 'API key is required'
			), 400 );
		}

		if ( empty( $provider ) ) {
			return new WP_REST_Response( array(
				'valid'   => false,
				'message' => 'Provider is required'
			), 400 );
		}

		$result = AITOOLS_Provider_Factory::validate_api_key( $provider, $key );

		$status_code = $result['valid'] ? 200 : 400;

		return new WP_REST_Response( $result, $status_code );
	}


	public function process_media_batch( $batch_size ) {
		if ( ! get_option( 'aitools_alt_gen_is_processing', false ) ) {
			update_option( 'aitools_alt_gen_processing_total', 0 );
			update_option( 'aitools_alt_gen_processing_current', 0 );

			return;
		}

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => $batch_size,
			'offset'         => get_option( 'aitools_alt_gen_processing_current', 0 )
		);

		// Check if we're processing a specific list of IDs
		$bulk_ids = get_transient( 'aitools_bulk_generation_ids' );

		if ( $bulk_ids && is_array( $bulk_ids ) ) {
			$args['post__in'] = $bulk_ids;
			$args['orderby']  = 'post__in';
		} elseif ( ! $this->rewrite_all ) {
			$ids = $this->get_images_without_alt_text_ids();
			if ( empty( $ids ) ) {
				update_option( 'aitools_alt_gen_is_processing', false );
				update_option( 'aitools_alt_gen_processing_total', 0 );
				update_option( 'aitools_alt_gen_processing_current', 0 );

				return;
			}
			$args['post__in'] = $ids;
		}

		$media_items = get_posts( $args );

		if ( empty( $media_items ) ) {
			update_option( 'aitools_alt_gen_is_processing', false );
			update_option( 'aitools_alt_gen_processing_total', 0 );
			update_option( 'aitools_alt_gen_processing_current', 0 );

			return;
		}

		$admin_instance = WP_AI_Tools_Admin::get_instance();
		$current        = get_option( 'aitools_alt_gen_processing_current', 0 );
		$total          = get_option( 'aitools_alt_gen_processing_total', 0 );

		foreach ($media_items as $item) {
            if (!get_option('aitools_alt_gen_is_processing', false)) {
                return;
            }

            try {
                $alt_text = $admin_instance->generate_alt_text_with_ai($item->ID, array('source' => 'bulk'));

                if ($alt_text) {
                    aitools_save_generated_alt_text($item->ID, $alt_text, array( 'source' => 'bulk'));
                }
            } catch (Exception $e) {
                // Continue to increment and process next images
            }

            $current++;
            update_option('aitools_alt_gen_processing_current', $current);

            // Check if we've processed all images
            if ($current >= $total) {
                update_option('aitools_alt_gen_is_processing', false);
                update_option('aitools_alt_gen_processing_total', 0);
                update_option('aitools_alt_gen_processing_current', 0);
                delete_transient('aitools_bulk_generation_ids');
                return;
            }
        }

		if ( get_option( 'aitools_alt_gen_is_processing', false ) ) {
			wp_schedule_single_event( time() + 5, 'ai_process_media_batch', array( $batch_size ) );
		}
	}

	private function get_images_without_alt_text_ids() {
		global $wpdb;

		$query = "
			SELECT p.ID
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
			WHERE p.post_type = 'attachment'
			AND p.post_mime_type LIKE 'image%'
			AND (pm.meta_value IS NULL OR pm.meta_value = '')
		";

		$results = $wpdb->get_results( $query );
		$ids     = array_map( function ( $result ) {
			return $result->ID;
		}, $results );

		return $ids;
	}

	public function get_settings() {
		$options = aitools_get_options();

		// Add provider options and their default models for the frontend
		$providers        = AITOOLS_Provider_Factory::get_providers();
		$provider_options = array();
		$default_models   = array();
		$help_urls        = array();

		foreach ( $providers as $name => $provider ) {
			$provider_options[ $name ] = $provider->get_display_name();
			$default_models[ $name ]   = $provider->get_default_model();
			$help_urls[ $name ]        = method_exists( $provider, 'get_api_key_help_url' ) ? $provider->get_api_key_help_url() : '';
		}

		$options['available_providers'] = $provider_options;
		$options['default_models']      = $default_models;
		$options['help_urls']           = $help_urls;

		// Ensure a model is set, if not, use the default for the current provider
		if ( empty( $options['model'] ) ) {
			$current_provider = $options['ai_provider'] ?? 'openai';
			$options['model'] = $default_models[ $current_provider ] ?? '';
		}

		return new WP_REST_Response( $options, 200 );
	}

	public function update_settings( WP_REST_Request $request ) {
		$settings = $request->get_params();

		$defaults = aitools_default_options();

		// Remove read-only / server-managed fields the frontend echoes back.
		unset( $settings['available_providers'], $settings['default_models'], $settings['help_urls'] );

		// Ensure we have all required fields
		$settings = wp_parse_args( $settings, $defaults );

		// Cast boolean settings properly before saving
		foreach ( $settings as $key => $value ) {
			if ( isset( $defaults[ $key ] ) && is_bool( $defaults[ $key ] ) ) {
				$settings[ $key ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			}
		}

		// Delete the option first to ensure it's updated
		delete_option( 'aitools_options' );

		// Save the new settings
		$result = update_option( 'aitools_options', $settings, false );

		// Verify the save
		$saved = get_option( 'aitools_options' );

		if ( ! $result || ! $saved ) {
			return new WP_REST_Response( array(
				'error'    => 'Failed to save settings',
				'settings' => $settings
			), 500 );
		}

		// Re-add provider options for frontend (since it's read-only)
		$saved['available_providers'] = AITOOLS_Provider_Factory::get_provider_options();

		// Ensure returned boolean values are cast correctly for the frontend
		foreach ( $saved as $key => $value ) {
			if ( isset( $defaults[ $key ] ) && is_bool( $defaults[ $key ] ) ) {
				$saved[ $key ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			}
		}

		return new WP_REST_Response( $saved, 200 );
	}

	public function check_processing_status() {
		$is_processing = get_option( 'aitools_alt_gen_is_processing', false );

		return new WP_REST_Response( array( 'is_processing' => $is_processing ), 200 );
	}

	public function stop_processing() {
		update_option( 'aitools_alt_gen_is_processing', false );
		update_option( 'aitools_alt_gen_processing_total', 0 );
		update_option( 'aitools_alt_gen_processing_current', 0 );

		return new WP_REST_Response( array(
			'status'  => 'success',
			'message' => 'Processing stopped'
		), 200 );
	}

	public function get_processing_status() {
		$is_processing = get_option( 'aitools_alt_gen_is_processing', false );
		$total_items   = get_option( 'aitools_alt_gen_processing_total', 0 );
		$current_item  = get_option( 'aitools_alt_gen_processing_current', 0 );

		// Validate the status - if current equals total, processing is done
		if ( $total_items > 0 && $current_item >= $total_items ) {
			update_option( 'aitools_alt_gen_is_processing', false );
			update_option( 'aitools_alt_gen_processing_total', 0 );
			update_option( 'aitools_alt_gen_processing_current', 0 );
			$is_processing = false;
			$total_items   = 0;
			$current_item  = 0;
		}

		// If not processing, ensure counters are reset
		if ( ! $is_processing ) {
			update_option( 'aitools_alt_gen_processing_total', 0 );
			update_option( 'aitools_alt_gen_processing_current', 0 );
			$total_items  = 0;
			$current_item = 0;
		}

		return new WP_REST_Response( array(
			'is_processing' => $is_processing,
			'total_items'   => $total_items,
			'current_item'  => $current_item
		), 200 );
	}

	public function handle_test_generation( WP_REST_Request $request ) {
		try {
			$image_id      = $request->get_param( 'image_id' );
			$custom_prompt = $request->get_param( 'prompt' );

			if ( ! $image_id ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => 'Image ID is required'
				), 400 );
			}

			$admin_instance = WP_AI_Tools_Admin::get_instance();
			$alt_text       = $admin_instance->generate_alt_text_with_ai( $image_id, array( 'prompt' => $custom_prompt, 'source' => 'test' ) );

			if ( empty( $alt_text ) ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => 'Failed to generate alt text'
				), 400 );
			}

			return new WP_REST_Response( array(
				'success'  => true,
				'alt_text' => $alt_text
			), 200 );

		} catch ( Exception $e ) {
			return new WP_REST_Response( array(
				'success' => false,
				'message' => 'Error: ' . $e->getMessage()
			), 500 );
		}
	}
}

// Initialize the class
new WP_AI_Tools_Restpoint();
