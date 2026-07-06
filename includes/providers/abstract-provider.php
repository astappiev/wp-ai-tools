<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for AI providers
 */
abstract class AITOOLS_Abstract_AI_Provider {

	/**
	 * Provider name/identifier
	 */
	abstract public function get_name();

	/**
	 * Provider display name
	 */
	abstract public function get_display_name();

	/**
	 * Validate API key for this provider
	 *
	 * @param string $api_key
	 *
	 * @return array Array with 'valid' boolean and 'message' string
	 */
	abstract public function validate_api_key( $api_key );

	/**
	 * Generate alt text for image
	 *
	 * @param string $image_base64 Base64 encoded image
	 * @param string $prompt Custom prompt
	 * @param string $language Target language
	 * @param string $api_key API key
	 *
	 * @return array Array with 'success' boolean, 'alt_text' string, and 'message' string
	 */
	abstract public function generate_alt_text( $image_base64, $prompt, $language, $api_key );

	/**
	 * Get default model for this provider
	 *
	 * @return string
	 */
	abstract public function get_default_model();

	/**
	 * Get help URL for getting API key
	 *
	 * @return string
	 */
	abstract public function get_api_key_help_url();

	/**
	 * Common method to make HTTP requests
	 *
	 * @param string $url
	 * @param array $headers
	 * @param string $body
	 * @param string $method
	 * @param int $timeout
	 *
	 * @return array|WP_Error
	 */
	protected function make_request( $url, $headers = array(), $body = '', $method = 'POST', $timeout = 30 ) {
		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => $timeout,
		);

		if ( ! empty( $body ) ) {
			$args['body'] = $body;
		}

		if ( $method === 'POST' ) {
			return wp_remote_post( $url, $args );
		} else {
			return wp_remote_get( $url, $args );
		}
	}

	/**
	 * Common method to handle API response
	 *
	 * @param array|WP_Error $response
	 * @param string $provider_name
	 *
	 * @return array
	 */
	protected function handle_response( $response, $provider_name = '' ) {
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $provider_name . ' API error: ' . $response->get_error_message()
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code !== 200 ) {
			return array(
				'success'       => false,
				'message'       => $provider_name . ' API error: ' . $response_body,
				'response_code' => $response_code
			);
		}

		return array(
			'success'  => true,
			'data'     => json_decode( $response_body, true ),
			'raw_body' => $response_body
		);
	}
}
