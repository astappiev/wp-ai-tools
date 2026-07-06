<?php
/**
 * WP-CLI commands for the AI Tools plugin.
 *
 * Provides a `wp ai-alt-text` command suite to configure providers,
 * bulk-generate alt text, and inspect coverage from the command line.
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Only define the command when WP-CLI is available.
if ( ! class_exists( 'WP_CLI_Command' ) ) {
	return;
}

/**
 * Manage AI-generated alt text for image attachments.
 *
 * ## EXAMPLES
 *
 *     # Configure the OpenAI provider with an API key.
 *     $ wp ai-alt-text activate --provider=openai --key=sk-xxxxxxxx
 *
 *     # Generate alt text for all images missing it.
 *     $ wp ai-alt-text generate
 *
 *     # Show current configuration and coverage.
 *     $ wp ai-alt-text status
 */
class WP_AI_Tools_CLI extends WP_CLI_Command {

	/**
	 * Default prompt used when none is configured.
	 *
	 * @var string
	 */
	const DEFAULT_PROMPT = 'Create a SEO optimized alt text for this image. Don\'t include quotes and keep it informative and concise.';

	/**
	 * Default language used when none is configured.
	 *
	 * @var string
	 */
	const DEFAULT_LANGUAGE = 'english';

	/**
	 * Batch size threshold above which a confirmation prompt is shown.
	 *
	 * @var int
	 */
	const CONFIRM_THRESHOLD = 50;

	/**
	 * Configure the AI provider and API key.
	 *
	 * Stores the provider selection and its API key in the plugin options.
	 * Other existing settings are preserved. The full key is never echoed back;
	 * only the last four characters are shown.
	 *
	 * Note: "activate" here means "activate/configure a provider"; it is not
	 * related to WordPress plugin activation.
	 *
	 * ## OPTIONS
	 *
	 * [--provider=<provider>]
	 * : The AI provider to configure.
	 * ---
	 * default: openai
	 * options:
	 *   - openai
	 *   - anthropic
	 * ---
	 *
	 * --key=<api-key>
	 * : The API key for the selected provider. Stored in '<provider>_key'.
	 *
	 * [--skip-validation]
	 * : Skip the live API-key validation request before saving.
	 *
	 * ## EXAMPLES
	 *
	 *     # Configure OpenAI and validate the key against the live API.
	 *     $ wp ai-alt-text activate --provider=openai --key=sk-xxxxxxxx
	 *
	 *     # Configure Anthropic, skipping validation.
	 *     $ wp ai-alt-text activate --provider=anthropic --key=sk-ant-xxxx --skip-validation
	 *
	 * @when after_wp_load
	 *
	 * @param array $args Positional arguments (unused).
	 * @param array $assoc_args Associative arguments.
	 */
	public function activate( $args, $assoc_args ) {
		$provider = WP_CLI\Utils\get_flag_value( $assoc_args, 'provider', 'openai' );
		$key      = WP_CLI\Utils\get_flag_value( $assoc_args, 'key', '' );
		$skip     = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-validation', false );

		if ( ! AITOOLS_Provider_Factory::provider_exists( $provider ) ) {
			WP_CLI::error(
				sprintf(
				/* translators: %s: provider name */
					__( 'Unknown provider: %s. Valid options are: openai, anthropic.', 'wp-ai-tools' ),
					$provider
				)
			);
		}

		$key = is_string( $key ) ? trim( $key ) : '';
		if ( '' === $key ) {
			WP_CLI::error( __( 'An API key is required. Pass it with --key=<api-key>.', 'wp-ai-tools' ) );
		}

		if ( ! $skip ) {
			WP_CLI::log( sprintf( __( 'Validating %s API key...', 'wp-ai-tools' ), $provider ) );
			$validation = AITOOLS_Provider_Factory::validate_api_key( $provider, $key );

			if ( empty( $validation['valid'] ) ) {
				$message = isset( $validation['message'] ) ? $validation['message'] : __( 'API key validation failed.', 'wp-ai-tools' );
				WP_CLI::error( sprintf( __( 'API key validation failed: %s', 'wp-ai-tools' ), $message ) );
			}

			WP_CLI::log( __( 'API key validated successfully.', 'wp-ai-tools' ) );
		} else {
			WP_CLI::warning( __( 'Skipping API key validation (--skip-validation).', 'wp-ai-tools' ) );
		}

		// Merge into existing options without clobbering unrelated keys.
		$defaults = aitools_default_options();
		$options  = get_option( 'aitools_options', array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$options = wp_parse_args( $options, $defaults );

		$options['ai_provider']        = $provider;
		$options['api_key'] = $key;

		update_option( 'aitools_options', $options );

		WP_CLI::success(
			sprintf(
			/* translators: 1: provider name, 2: masked API key */
				__( 'Configured provider "%1$s" with key %2$s.', 'wp-ai-tools' ),
				$provider,
				$this->mask_key( $key )
			)
		);
	}

	/**
	 * Bulk-generate alt text for image attachments.
	 *
	 * By default only processes images that are missing alt text. Per-image
	 * failures are reported as warnings and do not abort the run.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Maximum number of images to process this run. 0 means no limit.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--provider=<provider>]
	 * : Override the provider for this run. Defaults to the saved provider.
	 * ---
	 * options:
	 *   - openai
	 *   - anthropic
	 * ---
	 *
	 * [--force]
	 * : Regenerate alt text even for images that already have it.
	 *
	 * [--ids=<ids>]
	 * : Comma-separated attachment IDs to restrict processing to. When given,
	 * these exact attachments are processed regardless of existing alt text.
	 *
	 * [--dry-run]
	 * : Report what would be processed without calling the API or writing meta.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt for large batches.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate alt text for all images missing it.
	 *     $ wp ai-alt-text generate
	 *
	 *     # Regenerate alt text for specific attachments.
	 *     $ wp ai-alt-text generate --ids=12,34,56 --force
	 *
	 *     # Preview what the first 20 images would do, no API calls.
	 *     $ wp ai-alt-text generate --limit=20 --dry-run
	 *
	 * @when after_wp_load
	 *
	 * @param array $args Positional arguments (unused).
	 * @param array $assoc_args Associative arguments.
	 */
	public function generate( $args, $assoc_args ) {
		$limit        = (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 0 );
		$provider_arg = WP_CLI\Utils\get_flag_value( $assoc_args, 'provider', '' );
		$force        = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );
		$ids_arg      = WP_CLI\Utils\get_flag_value( $assoc_args, 'ids', '' );
		$dry_run      = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$yes          = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false );

		$defaults = aitools_default_options();
		$options  = get_option( 'aitools_options', array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$options = wp_parse_args( $options, $defaults );

		// Resolve provider.
		$provider = ! empty( $provider_arg ) ? $provider_arg : ( ! empty( $options['ai_provider'] ) ? $options['ai_provider'] : 'openai' );

		if ( ! AITOOLS_Provider_Factory::provider_exists( $provider ) ) {
			WP_CLI::error(
				sprintf(
				/* translators: %s: provider name */
					__( 'Unknown provider: %s. Valid options are: openai, anthropic.', 'wp-ai-tools' ),
					$provider
				)
			);
		}

		$api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';

		// In a dry run we still want to surface a missing key, but we do not abort.
		if ( empty( $api_key ) && ! $dry_run ) {
			WP_CLI::error(
				sprintf(
				/* translators: %1$s: provider name */
					__( 'No API key configured. Run: wp ai-alt-text activate --provider=%1$s --key=<api-key>', 'wp-ai-tools' ),
					$provider
				)
			);
		}

		// Parse explicit IDs if provided.
		$id_filter = array();
		if ( ! empty( $ids_arg ) ) {
			$id_filter = array_filter( array_map( 'absint', array_map( 'trim', explode( ',', $ids_arg ) ) ) );
		}

		$image_ids = $this->get_target_image_ids( $force, $id_filter, $limit );

		if ( empty( $image_ids ) ) {
			WP_CLI::warning( __( 'No images found to process.', 'wp-ai-tools' ) );

			return;
		}

		$total = count( $image_ids );

		WP_CLI::log(
			sprintf(
			/* translators: 1: count, 2: provider name */
				__( 'Found %1$d image(s) to process with provider "%2$s".', 'wp-ai-tools' ),
				$total,
				$provider
			)
		);

		if ( $dry_run ) {
			WP_CLI::log( __( 'Dry run: the following attachment IDs would be processed (no API calls, no writes):', 'wp-ai-tools' ) );
			WP_CLI::log( implode( ', ', $image_ids ) );
			WP_CLI::success(
				sprintf(
				/* translators: %d: count */
					__( 'Dry run complete. %d image(s) would be processed.', 'wp-ai-tools' ),
					$total
				)
			);

			return;
		}

		// Confirm large batches unless --yes.
		if ( ! $yes && $total > self::CONFIRM_THRESHOLD ) {
			WP_CLI::confirm(
				sprintf(
				/* translators: %d: count */
					__( 'About to generate alt text for %d images. This will make live API calls and may incur costs. Continue?', 'wp-ai-tools' ),
					$total
				),
				$assoc_args
			);
		}

		$prompt   = ! empty( $options['prompt'] ) ? $options['prompt'] : self::DEFAULT_PROMPT;
		$language = ! empty( $options['language'] ) ? $options['language'] : self::DEFAULT_LANGUAGE;

		$succeeded = 0;
		$skipped   = 0;
		$failed    = 0;

		$progress = WP_CLI\Utils\make_progress_bar( __( 'Generating alt text', 'wp-ai-tools' ), $total );

		$upload_dir = wp_upload_dir();
		$basedir    = isset( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : '';

		foreach ( $image_ids as $id ) {
			$meta = wp_get_attachment_metadata( $id );

			if ( ! $meta || ! isset( $meta['file'] ) ) {
				WP_CLI::warning( sprintf( __( 'Attachment %d: missing metadata, skipping.', 'wp-ai-tools' ), $id ) );
				$skipped ++;
				$progress->tick();
				continue;
			}

			$path = $basedir . '/' . $meta['file'];

			if ( ! file_exists( $path ) ) {
				WP_CLI::warning( sprintf( __( 'Attachment %d: file not found at %s, skipping.', 'wp-ai-tools' ), $id, $path ) );
				$skipped ++;
				$progress->tick();
				continue;
			}

			// Guard against reading and base64-encoding very large originals,
			// which can exhaust PHP's memory_limit during a bulk run. The cap is
			// filterable via 'aitools_cli_max_image_bytes'.
			$max_bytes = (int) apply_filters( 'aitools_cli_max_image_bytes', 20 * MB_IN_BYTES );
			$size      = filesize( $path );
			if ( false === $size || ( $max_bytes > 0 && $size > $max_bytes ) ) {
				WP_CLI::warning(
					sprintf(
					/* translators: 1: attachment ID, 2: file size in bytes, 3: max size in bytes */
						__( 'Attachment %1$d: file too large (%2$d bytes > %3$d limit), skipping.', 'wp-ai-tools' ),
						$id,
						(int) $size,
						$max_bytes
					)
				);
				$skipped ++;
				$progress->tick();
				continue;
			}

			$contents = file_get_contents( $path );
			if ( false === $contents ) {
				WP_CLI::warning( sprintf( __( 'Attachment %d: unable to read file, skipping.', 'wp-ai-tools' ), $id ) );
				$skipped ++;
				$progress->tick();
				continue;
			}

			$base64 = base64_encode( $contents );
			if ( empty( $base64 ) ) {
				WP_CLI::warning( sprintf( __( 'Attachment %d: failed to encode image, skipping.', 'wp-ai-tools' ), $id ) );
				$skipped ++;
				$progress->tick();
				continue;
			}

			$result = AITOOLS_Provider_Factory::generate_alt_text( $provider, $base64, $prompt, $language, $api_key, array( 'attachment_id' => $id, 'source' => 'cli' ) );

			// Free the (potentially large) image buffers before the next iteration.
			unset( $contents, $base64 );

			if ( empty( $result['success'] ) ) {
				$message = isset( $result['message'] ) ? $result['message'] : __( 'unknown error', 'wp-ai-tools' );
				WP_CLI::warning( sprintf( __( 'Attachment %1$d: generation failed (%2$s).', 'wp-ai-tools' ), $id, $message ) );
				$failed ++;
				$progress->tick();
				continue;
			}

			aitools_save_generated_alt_text( $id, $result['alt_text'], array( 'source' => 'cli' ) );
			$succeeded ++;
			$progress->tick();
		}

		$progress->finish();

		WP_CLI::success(
			sprintf(
			/* translators: 1: succeeded count, 2: skipped count, 3: failed count */
				__( 'Done. Generated: %1$d, Skipped: %2$d, Failed: %3$d.', 'wp-ai-tools' ),
				$succeeded,
				$skipped,
				$failed
			)
		);
	}

	/**
	 * Show current configuration and alt text coverage.
	 *
	 * Prints the active provider, which provider keys are configured, the
	 * prompt and language, and counts of image attachments with/without alt
	 * text. No secrets are printed.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format for the coverage/config tables.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Show configuration and coverage.
	 *     $ wp ai-alt-text status
	 *
	 *     # Output as JSON.
	 *     $ wp ai-alt-text status --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args Positional arguments (unused).
	 * @param array $assoc_args Associative arguments.
	 */
	public function status( $args, $assoc_args ) {
		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$defaults = aitools_default_options();
		$options  = get_option( 'aitools_options', array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$options = wp_parse_args( $options, $defaults );

		$active_provider = ! empty( $options['ai_provider'] ) ? $options['ai_provider'] : 'openai';
		$prompt          = ! empty( $options['prompt'] ) ? $options['prompt'] : self::DEFAULT_PROMPT;
		$language        = ! empty( $options['language'] ) ? $options['language'] : self::DEFAULT_LANGUAGE;

		// Use the model from settings when available, otherwise fall back to the provider's default model.
		$model = ! empty( $options['model'] ) ? $options['model'] : AITOOLS_Provider_Factory::get_default_model( $active_provider );

		$api_key_configured = ! empty( $options['api_key'] ) ? __( 'yes', 'wp-ai-tools' ) : __( 'no', 'wp-ai-tools' );

		$total   = $this->count_total_images();
		$without = $this->count_images_without_alt_text();
		$with    = max( 0, $total - $without );

		$config_rows = array(
			array(
				'setting' => __( 'Active provider', 'wp-ai-tools' ),
				'value'   => $active_provider,
			),
			array(
				'setting' => __( 'Model', 'wp-ai-tools' ),
				'value'   => $model ?: '—',
			),
			array(
				'setting' => __( 'API key configured', 'wp-ai-tools' ),
				'value'   => $api_key_configured,
			),
			array(
				'setting' => __( 'Language', 'wp-ai-tools' ),
				'value'   => $language,
			),
			array(
				'setting' => __( 'Prompt', 'wp-ai-tools' ),
				'value'   => $prompt,
			),
		);

		$coverage_rows = array(
			array(
				'metric' => __( 'Total image attachments', 'wp-ai-tools' ),
				'count'  => $total,
			),
			array(
				'metric' => __( 'With alt text', 'wp-ai-tools' ),
				'count'  => $with,
			),
			array(
				'metric' => __( 'Without alt text', 'wp-ai-tools' ),
				'count'  => $without,
			),
		);

		WP_CLI::log( __( 'Configuration:', 'wp-ai-tools' ) );
		WP_CLI\Utils\format_items( $format, $config_rows, array( 'setting', 'value' ) );

		WP_CLI::log( '' );
		WP_CLI::log( __( 'Coverage:', 'wp-ai-tools' ) );
		WP_CLI\Utils\format_items( $format, $coverage_rows, array( 'metric', 'count' ) );
	}

	/**
	 * Mask an API key, revealing only the last four characters.
	 *
	 * @param string $key The API key.
	 *
	 * @return string Masked key.
	 */
	private function mask_key( $key ) {
		$key = (string) $key;
		$len = strlen( $key );

		if ( $len <= 4 ) {
			return str_repeat( '*', $len );
		}

		return str_repeat( '*', $len - 4 ) . substr( $key, - 4 );
	}

	/**
	 * Resolve the list of attachment IDs to process.
	 *
	 * @param bool $force Whether to include images that already have alt text.
	 *                         Ignored when $id_filter is non-empty (explicit IDs
	 *                         are always treated as authoritative).
	 * @param array $id_filter Optional explicit list of attachment IDs.
	 * @param int $limit Maximum number of IDs to return. 0 = no limit.
	 *
	 * @return array Attachment IDs.
	 */
	private function get_target_image_ids( $force, $id_filter, $limit ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => $limit > 0 ? $limit : - 1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		if ( ! empty( $id_filter ) ) {
			$args['post__in'] = $id_filter;
			$args['orderby']  = 'post__in';
		}

		// When the user names explicit IDs, treat them as authoritative and
		// process exactly those attachments regardless of existing alt text.
		// Otherwise, when not forcing, restrict to images missing alt text.
		if ( ! $force && empty( $id_filter ) ) {
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key'     => '_wp_attachment_image_alt',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_wp_attachment_image_alt',
					'value'   => '',
					'compare' => '=',
				),
			);
		}

		$query = new WP_Query( $args );

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Count total image attachments.
	 *
	 * Uses a direct COUNT query so large media libraries do not load every
	 * attachment ID into memory just to count them.
	 *
	 * @return int
	 */
	private function count_total_images() {
		global $wpdb;

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = 'attachment'
			   AND post_status = 'inherit'
			   AND post_mime_type LIKE 'image/%'"
		);
	}

	/**
	 * Count image attachments without alt text.
	 *
	 * Uses a direct COUNT query with a LEFT JOIN so large media libraries do
	 * not load every attachment ID into memory just to count them.
	 *
	 * @return int
	 */
	private function count_images_without_alt_text() {
		global $wpdb;

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} m
			   ON ( p.ID = m.post_id AND m.meta_key = '_wp_attachment_image_alt' )
			 WHERE p.post_type = 'attachment'
			   AND p.post_status = 'inherit'
			   AND p.post_mime_type LIKE 'image/%'
			   AND ( m.meta_id IS NULL OR m.meta_value = '' )"
		);
	}
}
