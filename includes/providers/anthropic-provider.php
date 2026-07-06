<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'abstract-provider.php';

/**
 * Anthropic (Claude) provider implementation
 */
class AITOOLS_Anthropic_Provider extends AITOOLS_Abstract_AI_Provider {

	/**
	 * Provider name/identifier
	 */
	public function get_name() {
		return 'anthropic';
	}

	/**
	 * Provider display name
	 */
	public function get_display_name() {
		return 'Anthropic';
	}

	/**
	 * Validate API key for Anthropic
	 *
	 * @param string $api_key
	 *
	 * @return array
	 */
	public function validate_api_key( $api_key ) {
		if ( empty( $api_key ) ) {
			return array(
				'valid'   => false,
				'message' => 'API key is required'
			);
		}

		$response = $this->make_request(
			'https://api.anthropic.com/v1/models',
			array(
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
			),
			'',
			'GET'
		);

		$result = $this->handle_response( $response, 'Anthropic' );

		if ( $result['success'] ) {
			return array(
				'valid'   => true,
				'message' => 'API key is valid'
			);
		} else {
			$response_body = ! is_wp_error( $response ) ? wp_remote_retrieve_body( $response ) : '';
			$data          = json_decode( $response_body, true );
			$message       = isset( $data['error']['message'] ) ? $data['error']['message'] : ( ! empty( $result['message'] ) ? $result['message'] : 'Invalid API key' );

			return array(
				'valid'   => false,
				'message' => $message
			);
		}
	}

	/**
	 * Generate alt text using Anthropic Claude
	 *
	 * @param string $image_base64
	 * @param string $prompt
	 * @param string $language
	 * @param string $api_key
	 *
	 * @return array
	 */
	public function generate_alt_text( $image_base64, $prompt, $language, $api_key ) {
		try {
			$prompt_with_lang = $prompt . ' Write it in this language: ' . $language;

			// Determine image media type (default to jpeg)
			$media_type = 'image/jpeg';
			if ( strpos( $image_base64, 'data:image/png' ) === 0 ) {
				$media_type = 'image/png';
			} elseif ( strpos( $image_base64, 'data:image/gif' ) === 0 ) {
				$media_type = 'image/gif';
			} elseif ( strpos( $image_base64, 'data:image/webp' ) === 0 ) {
				$media_type = 'image/webp';
			}

			// Clean base64 data (remove data:image/xxx;base64, prefix if present)
			$clean_base64 = preg_replace( '/^data:image\/[^;]+;base64,/', '', $image_base64 );

			$options = get_option( 'aitools_options', array() );
			$model   = ! empty( $options['model'] ) ? $options['model'] : $this->get_default_model();

			$body = wp_json_encode( [
				'model'      => $model,
				'max_tokens' => 100,
				'messages'   => [
					[
						'role'    => 'user',
						'content' => [
							[
								'type'   => 'image',
								'source' => [
									'type'       => 'base64',
									'media_type' => $media_type,
									'data'       => $clean_base64
								]
							],
							[
								'type' => 'text',
								'text' => $prompt_with_lang
							]
						]
					]
				]
			] );

			$response = $this->make_request(
				'https://api.anthropic.com/v1/messages',
				array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
					'content-type'      => 'application/json'
				),
				$body
			);

			$result = $this->handle_response( $response, 'Anthropic' );

			if ( ! $result['success'] ) {
				return array(
					'success'  => false,
					'alt_text' => '',
					'message'  => $result['message']
				);
			}

			$data = $result['data'];
			if ( ! isset( $data['content'][0]['text'] ) ) {
				return array(
					'success'  => false,
					'alt_text' => '',
					'message'  => 'Invalid response from Anthropic API'
				);
			}

			$alt_text = trim( $data['content'][0]['text'] );

			return array(
				'success'  => true,
				'alt_text' => $alt_text,
				'message'  => 'Alt text generated successfully'
			);

		} catch ( Exception $e ) {
			return array(
				'success'  => false,
				'alt_text' => '',
				'message'  => 'Error: ' . $e->getMessage()
			);
		}
	}

	/**
	 * Get default model
	 *
	 * @return string
	 */
	public function get_default_model() {
		return apply_filters( 'aitools_default_model', 'claude-haiku-4-5', 'anthropic' );
	}

	/**
	 * Get API key help URL
	 *
	 * @return string
	 */
	public function get_api_key_help_url() {
		return 'https://docs.anthropic.com/en/api/getting-started';
	}
}
