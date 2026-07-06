<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'abstract-provider.php';

class AITOOLS_Google_Provider extends AITOOLS_Abstract_AI_Provider {

	/**
	 * Provider name/identifier
	 */
	public function get_name() {
		return 'google';
	}

	/**
	 * Provider display name
	 */
	public function get_display_name() {
		return 'Google';
	}

	/**
	 * Validate API key for Google
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
			'https://generativelanguage.googleapis.com/v1beta/openai/models',
			array( 'Authorization' => 'Bearer ' . $api_key ),
			'',
			'GET'
		);

		$result = $this->handle_response( $response, 'Google' );

		if ( $result['success'] ) {
			return array(
				'valid'   => true,
				'message' => 'API key is valid'
			);
		} else {
			$data    = isset( $result['data'] ) ? $result['data'] : array();
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Invalid API key';

			return array(
				'valid'   => false,
				'message' => $message
			);
		}
	}

	/**
	 * Generate alt text using OpenAI-compatible API
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

			$options = get_option( 'aitools_options', array() );
			$model   = ! empty( $options['model'] ) ? $options['model'] : $this->get_default_model();

			$body = wp_json_encode( [
				'model'       => $model,
				'temperature' => 0.8,
				'max_tokens'  => 2056,
				'stream'      => false,
				'messages'    => [
					[
						'role'    => 'user',
						'content' => [
							[
								'type' => 'text',
								'text' => $prompt_with_lang,
							],
							[
								'type'      => 'image_url',
								'image_url' => [
									'url' => 'data:image/jpeg;base64,' . $image_base64
								],
							],
						],
					],
				],
			] );

			$response = $this->make_request(
				'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
				array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				$body
			);

			$result = $this->handle_response( $response, 'Google' );

			if ( ! $result['success'] ) {
				return array(
					'success'  => false,
					'alt_text' => '',
					'message'  => $result['message']
				);
			}

			$data = $result['data'];
			if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
				error_log( var_export( $data, true ) );

				return array(
					'success'  => false,
					'alt_text' => '',
					'message'  => 'Invalid response from Google API'
				);
			}

			$alt_text = trim( $data['choices'][0]['message']['content'] );

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
		/**
		 * Filter the default model for a provider. Lets sites (or a future plugin
		 * update) swap the model without code changes if one is deprecated.
		 *
		 * @param string $model
		 * @param string $provider
		 */
		return apply_filters( 'aitools_default_model', 'gemini-3.1-flash-lite', 'google' );
	}

	/**
	 * Get API key help URL
	 *
	 * @return string
	 */
	public function get_api_key_help_url() {
		return 'https://aistudio.google.com/app/api-keys';
	}
}
