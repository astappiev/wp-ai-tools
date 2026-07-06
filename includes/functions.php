<?php
/**
 * Get the Plugin Default Options.
 */
if ( ! function_exists( 'aitools_default_options' ) ) :
	function aitools_default_options() {
		$default_theme_options = array(
			'ai_provider'        => 'openai',
			'api_key'            => '',
			'on_upload_alt_text' => false,
			'all_alt_text'       => false,
			'set_title'          => false,
			'set_caption'        => false,
			'set_description'    => false,
			'prompt'             => 'Write concise, descriptive alt text for this image that conveys its purpose and key content for screen-reader users and SEO. Use a single sentence under 125 characters. Do not begin with "image of", "photo of", or "picture of", and do not use quotation marks.',
			'language'           => 'English',
		);

		return apply_filters( 'aitools_default_options', $default_theme_options );
	}
endif;

/**
 * Get the Plugin Saved Options.
 *
 * @param string $key optional option key
 * @return mixed All Options Array Or Options Value
 */
if ( ! function_exists( 'aitools_get_options' ) ) :
	function aitools_get_options( $key = '' ) {
		$options         = get_option( 'aitools_options' );
		$default_options = aitools_default_options();

		if ( ! empty( $key ) ) {
			if ( isset( $options[ $key ] ) ) {
				$val = $options[ $key ];
				if ( isset( $default_options[ $key ] ) && is_bool( $default_options[ $key ] ) ) {
					return filter_var( $val, FILTER_VALIDATE_BOOLEAN );
				}

				return $val;
			}

			return isset( $default_options[ $key ] ) ? $default_options[ $key ] : false;
		} else {
			if ( ! is_array( $options ) ) {
				$options = array();
			}
			$merged = array_merge( $default_options, $options );
			foreach ( $merged as $k => $v ) {
				if ( isset( $default_options[ $k ] ) && is_bool( $default_options[ $k ] ) ) {
					$merged[ $k ] = filter_var( $v, FILTER_VALIDATE_BOOLEAN );
				}
			}

			return $merged;
		}
	}
endif;

/**
 * Persist a generated alt text value for an attachment, applying add-on hooks.
 *
 * Centralises the "filter then save then notify" sequence so every generation
 * path (single image, bulk, on-upload, REST, CLI) exposes the same extension
 * points to add-ons:
 *
 *  - filter `aitools_alt_text`      : adjust the alt text per attachment before saving.
 *  - action `aitools_after_generate`: react after the alt text is saved (SEO sync, logging…).
 *
 * @param int $attachment_id Attachment ID.
 * @param string $alt_text Generated alt text.
 * @param array $context Request context (e.g. 'source').
 *
 * @return string The alt text that was saved (possibly filtered); empty string if nothing saved.
 */
if ( ! function_exists( 'aitools_save_generated_alt_text' ) ) :
	function aitools_save_generated_alt_text( $attachment_id, $alt_text, $context = array() ) {
		$context = array_merge( array( 'attachment_id' => $attachment_id ), $context );

		/**
		 * Filter the alt text for a specific attachment just before it is saved.
		 *
		 * @param string $alt_text The generated alt text.
		 * @param int $attachment_id Attachment ID.
		 * @param array $context Request context.
		 */
		$alt_text = apply_filters( 'aitools_alt_text', $alt_text, $attachment_id, $context );

		if ( '' === (string) $alt_text ) {
			return $alt_text;
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

		// Optionally mirror the alt text into the attachment's Title / Caption /
		// Description when those settings are enabled.
		$opts        = aitools_get_options();
		$post_update = array();
		if ( ! empty( $opts['set_title'] ) ) {
			$post_update['post_title'] = $alt_text;
		}
		if ( ! empty( $opts['set_caption'] ) ) {
			$post_update['post_excerpt'] = $alt_text;
		}
		if ( ! empty( $opts['set_description'] ) ) {
			$post_update['post_content'] = $alt_text;
		}
		if ( ! empty( $post_update ) ) {
			$post_update['ID'] = $attachment_id;
			wp_update_post( $post_update );
		}

		/**
		 * Fires after alt text has been generated and saved for an attachment.
		 *
		 * Integration point for SEO-plugin sync, coverage tracking, logging, etc.
		 *
		 * @param int $attachment_id Attachment ID.
		 * @param string $alt_text The alt text that was saved.
		 * @param array $context Request context.
		 */
		do_action( 'aitools_after_generate', $attachment_id, $alt_text, $context );

		return $alt_text;
	}
endif;

/**
 * Get the SEO focus keyphrase for a post from common SEO plugins (Yoast,
 * Rank Math, SEOPress). Filterable so other SEO plugins (e.g. AIOSEO) can be added.
 *
 * @param int $post_id
 *
 * @return string
 */
if ( ! function_exists( 'aitools_get_focus_keyphrase' ) ) :
	function aitools_get_focus_keyphrase( $post_id ) {
		$keyphrase = '';
		$meta_keys = array(
			'_yoast_wpseo_focuskw',         // Yoast SEO
			'rank_math_focus_keyword',      // Rank Math (may be a comma list)
			'_seopress_analysis_target_kw', // SEOPress (may be a comma list)
		);
		foreach ( $meta_keys as $key ) {
			$val = get_post_meta( $post_id, $key, true );
			if ( ! empty( $val ) ) {
				$parts     = explode( ',', (string) $val );
				$keyphrase = trim( wp_strip_all_tags( $parts[0] ) );
				if ( '' !== $keyphrase ) {
					break;
				}
			}
		}

		/**
		 * Filter the detected focus keyphrase (e.g. to add AIOSEO support).
		 *
		 * @param string $keyphrase
		 * @param int $post_id
		 */
		return apply_filters( 'aitools_focus_keyphrase', $keyphrase, $post_id );
	}
endif;

/**
 * Enrich the generation prompt with page context + the SEO focus keyphrase of
 * the post the image belongs to. Hooked onto the plugin's own `aitools_generate_prompt` filter,
 * so it composes with other prompt filters. Disable via `aitools_enable_context_enrichment`.
 *
 * @param string $prompt
 * @param array $context Expects 'attachment_id'.
 *
 * @return string
 */
if ( ! function_exists( 'aitools_enrich_prompt_with_context' ) ) :
	function aitools_enrich_prompt_with_context( $prompt, $context ) {
		if ( ! apply_filters( 'aitools_enable_context_enrichment', true ) ) {
			return $prompt;
		}
		$attachment_id = isset( $context['attachment_id'] ) ? (int) $context['attachment_id'] : 0;
		if ( ! $attachment_id ) {
			return $prompt;
		}
		$post_id = (int) wp_get_post_parent_id( $attachment_id );
		if ( ! $post_id ) {
			return $prompt;
		}

		$extra = array();
		$title = wp_strip_all_tags( get_the_title( $post_id ) );
		if ( '' !== $title ) {
			$extra[] = 'For context, this image appears on a page titled "' . $title . '".';
		}
		$keyphrase = aitools_get_focus_keyphrase( $post_id );
		if ( '' !== $keyphrase ) {
			$extra[] = 'If it fits naturally, incorporate the keyphrase "' . $keyphrase . '" (do not keyword-stuff).';
		}

		if ( empty( $extra ) ) {
			return $prompt;
		}

		return $prompt . ' ' . implode( ' ', $extra );
	}
endif;
add_filter( 'aitools_generate_prompt', 'aitools_enrich_prompt_with_context', 10, 2 );

/**
 * Return base64-encoded image data for an attachment at a sensible size.
 *
 * Prefers a downscaled size (default "large", ~1024px) over the full-size
 * original — better cost/latency for vision models and higher quality than the
 * tiny thumbnail some paths used previously. Falls back to the original file.
 *
 * @param int $attachment_id
 * @param string $size
 *
 * @return string Base64 string, or '' on failure.
 */
if ( ! function_exists( 'aitools_get_image_base64_for_attachment' ) ) :
	function aitools_get_image_base64_for_attachment( $attachment_id, $size = 'large' ) {
		$size = apply_filters( 'aitools_image_size', $size, $attachment_id );
		$path = '';

		$src = wp_get_attachment_image_src( $attachment_id, $size );
		if ( $src && ! empty( $src[0] ) ) {
			$upload    = wp_upload_dir();
			$candidate = str_replace( $upload['baseurl'], $upload['basedir'], $src[0] );
			if ( file_exists( $candidate ) ) {
				$path = $candidate;
			}
		}
		if ( '' === $path ) {
			$full = get_attached_file( $attachment_id );
			if ( $full && file_exists( $full ) ) {
				$path = $full;
			}
		}
		if ( '' === $path ) {
			return '';
		}

		$data = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $data ) {
			return '';
		}

		return base64_encode( $data ); // phpcs:ignore
	}
endif;
