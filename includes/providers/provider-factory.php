<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'openai-provider.php';
require_once 'anthropic-provider.php';
require_once 'google-provider.php';

/**
 * Factory class for managing AI providers
 */
class AITOOLS_Provider_Factory {

	/**
	 * Available providers
	 *
	 * @var array
	 */
	private static $providers = array();

	/**
	 * Initialize all providers
	 */
	public static function init() {
		if ( empty( self::$providers ) ) {
			$providers = array(
				'openai'    => new AITOOLS_OpenAI_Provider(),
				'anthropic' => new AITOOLS_Anthropic_Provider(),
				'google'    => new AITOOLS_Google_Provider(),
			);

			/**
			 * Filter the registered AI providers.
			 *
			 * Add-ons can register additional providers (e.g. premium models or a
			 * managed-credit service) by adding an instance of a class that extends
			 * AITOOLS_Abstract_AI_Provider, keyed by its provider name.
			 *
			 * @param array $providers Associative array of provider_name => AITOOLS_Abstract_AI_Provider instance.
			 */
			self::$providers = apply_filters( 'aitools_providers', $providers );
		}
	}

	/**
	 * Get all available providers
	 *
	 * @return array
	 */
	public static function get_providers() {
		self::init();

		return self::$providers;
	}

	/**
	 * Get provider by name
	 *
	 * @param string $provider_name
	 *
	 * @return AITOOLS_Abstract_AI_Provider|null
	 */
	public static function get_provider( $provider_name ) {
		self::init();

		return self::$providers[ $provider_name ] ?? null;
	}

	/**
	 * Get provider options for select field
	 *
	 * @return array
	 */
	public static function get_provider_options() {
		self::init();
		$options = array();

		foreach ( self::$providers as $provider ) {
			$options[ $provider->get_name() ] = $provider->get_display_name();
		}

		return $options;
	}

	/**
	 * Check if provider exists
	 *
	 * @param string $provider_name
	 *
	 * @return bool
	 */
	public static function provider_exists( $provider_name ) {
		self::init();

		return isset( self::$providers[ $provider_name ] );
	}

	/**
	 * Validate API key for specific provider
	 *
	 * @param string $provider_name
	 * @param string $api_key
	 *
	 * @return array
	 */
	public static function validate_api_key( $provider_name, $api_key ) {
		$provider = self::get_provider( $provider_name );

		if ( ! $provider ) {
			return array(
				'valid'   => false,
				'message' => 'Provider not found: ' . $provider_name
			);
		}

		return $provider->validate_api_key( $api_key );
	}

	/**
	 * Generate alt text using specified provider
	 *
	 * @param string $provider_name
	 * @param string $image_base64
	 * @param string $prompt
	 * @param string $language
	 * @param string $api_key
	 *
	 * @return array
	 */
	public static function generate_alt_text( $provider_name, $image_base64, $prompt, $language, $api_key, $context = array() ) {
		/**
		 * Filter the provider, prompt and language just before generation.
		 *
		 * The $context array carries request metadata (e.g. 'attachment_id', 'source')
		 * so add-ons can tailor generation per image — for example injecting WooCommerce
		 * product context into the prompt, or routing premium requests to another provider.
		 *
		 * @param string $value The provider name / prompt / language being filtered.
		 * @param array $context Request context (attachment_id, source, etc.).
		 */
		$provider_name = apply_filters( 'aitools_generate_provider', $provider_name, $context );
		$prompt        = apply_filters( 'aitools_generate_prompt', $prompt, $context );
		$language      = apply_filters( 'aitools_generate_language', $language, $context );

		/**
		 * Short-circuit alt text generation.
		 *
		 * Return a non-null result array (with 'success', 'alt_text' and 'message' keys)
		 * to bypass the built-in providers entirely. This is the integration point for a
		 * managed-credit service or any external generation backend.
		 *
		 * @param array|null $pre Short-circuit result, or null to use built-in providers.
		 * @param string $image_base64 Base64-encoded image.
		 * @param string $prompt Generation prompt.
		 * @param string $language Target language.
		 * @param array $context Request context.
		 */
		$pre = apply_filters( 'aitools_pre_generate_alt_text', null, $image_base64, $prompt, $language, $context );
		if ( null !== $pre ) {
			return $pre;
		}

		$provider = self::get_provider( $provider_name );

		if ( ! $provider ) {
			return array(
				'success'  => false,
				'alt_text' => '',
				'message'  => 'Provider not found: ' . $provider_name
			);
		}

		$result = $provider->generate_alt_text( $image_base64, $prompt, $language, $api_key );

		/**
		 * Filter the generation result before it is returned to the caller.
		 *
		 * Add-ons can post-process the generated alt text here (e.g. SEO keyword
		 * injection, length trimming, profanity filtering).
		 *
		 * @param array $result Result array with 'success', 'alt_text', 'message'.
		 * @param array $context Request context.
		 */
		return apply_filters( 'aitools_generate_result', $result, $context );
	}

	/**
	 * Get default model for provider
	 *
	 * @param string $provider_name
	 *
	 * @return string
	 */
	public static function get_default_model( $provider_name ) {
		$provider = self::get_provider( $provider_name );

		if ( ! $provider ) {
			return '';
		}

		return $provider->get_default_model();
	}
}
