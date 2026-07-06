/* global MutationObserver, wpAITools */
/* eslint-disable no-console */
import jQuery from 'jquery';

jQuery( document ).ready( function ( $ ) {
	// Function to insert the button
	function insertAltTextButton() {
		if ( ! $( '#generate-alt-text-btn' ).length ) {
			$( '.attachment-alt-text, .alt-text' ).append(
				'<br></b><p class="alt-generate-alt-text-wrapper" style="display:inline-block;width:100%;">' +
					'<input type="button" id="generate-alt-text-btn" class="button" value="Generate Alt Text">' +
					'<span class="spinner"></span>' +
					'<span class="error-message" style="color: red; margin-left: 10px;"></span>' +
					'<span class="success-message" style="color: green; margin-left: 10px;"></span>' +
					'</p><br><br>'
			);
		}
	}

	// Mutation observer to detect when media details are opened
	const observer = new MutationObserver( function ( mutations ) {
		mutations.forEach( function ( mutation ) {
			if ( mutation.addedNodes.length ) {
				insertAltTextButton();
			}
		} );
	} );

	// Start observing
	observer.observe( document.body, { childList: true, subtree: true } );

	// Handle button click
	$( document ).on( 'click', '#generate-alt-text-btn', function ( e ) {
		e.preventDefault();

		function showError( $wrapper, message ) {
			$wrapper.find( '.error-message' ).text( message ).show();
			setTimeout( function () {
				$wrapper.find( '.error-message' ).fadeOut();
			}, 5000 );
		}

		const $button = $( this );
		const $wrapper = $button.closest( '.alt-generate-alt-text-wrapper' );
		let attachmentId = null;
		let $selectedBlock = null;

		console.log( 'AITOOLS_ Debug: Starting attachment ID detection...' );

		// 1. Media Library Modal (most reliable)
		attachmentId = $button
			.closest( '.attachment-details' )
			.find( 'input[data-setting="id"]' )
			.val();
		if ( attachmentId ) {
			console.log(
				'AITOOLS Debug: Found ID in Media Modal:',
				attachmentId
			);
		}

		// 2. Media Library Grid/List View
		if ( ! attachmentId ) {
			attachmentId = $button.closest( '.attachment' ).data( 'id' );
			if ( attachmentId ) {
				console.log(
					'AITOOLS Debug: Found ID in Media Grid/List:',
					attachmentId
				);
			}
		}

		// 3. Gutenberg Editor (most complex)
		if ( ! attachmentId ) {
			console.log(
				'AITOOLS Debug: Trying Gutenberg editor detection...'
			);
			$selectedBlock = $( '.wp-block.is-selected' );
			console.log(
				'AITOOLS Debug: Selected block found:',
				$selectedBlock.length > 0
			);

			if ( $selectedBlock.length ) {
				const $img = $selectedBlock.find( 'img' );
				console.log(
					'AITOOLS Debug: Image inside block found:',
					$img.length > 0
				);

				// Find image ID from class name
				const imgClass = $img.attr( 'class' );
				console.log( 'AITOOLS Debug: Image class:', imgClass );
				if ( imgClass ) {
					const match = imgClass.match( /wp-image-(\d+)/ );
					if ( match && match[ 1 ] ) {
						attachmentId = match[ 1 ];
						console.log(
							'AITOOLS Debug: Found ID from class name:',
							attachmentId
						);
					}
				}

				// Fallback: Check for data-id attribute on image
				if ( ! attachmentId && $img.data( 'id' ) ) {
					attachmentId = $img.data( 'id' );
					console.log(
						'AITOOLS Debug: Found ID from data-id attribute:',
						attachmentId
					);
				}
			}
		}

		// 4. Classic Editor
		if ( ! attachmentId ) {
			if ( $( '#post_ID' ).length ) {
				attachmentId = $( '#post_ID' ).val();
				console.log(
					'AITOOLS Debug: Found ID in Classic Editor:',
					attachmentId
				);
			}
		}

		console.log(
			'AITOOLS Debug: Final attachmentId before AJAX:',
			attachmentId
		);

		if ( ! attachmentId ) {
			showError(
				$wrapper,
				'Could not find image ID. Please select an image.'
			);
			return;
		}

		$wrapper.find( 'span.spinner' ).addClass( 'is-active' );
		$wrapper.find( '.error-message' ).text( '' ).hide();
		$button.prop( 'disabled', true );

		$.ajax( {
			url: wpAITools.ajax_url,
			type: 'POST',
			data: {
				action: 'generate_alt_text',
				nonce: wpAITools.nonce,
				post_id: attachmentId,
			},
			success( response ) {
				if ( response.success && response.data ) {
					const altText = response.data;

					// Update alt text field in media library modal
					const $altTextarea = $button
						.closest( '.attachment-details' )
						.find(
							'textarea[name$="[alt]"], textarea[id^="attachment-details-alt-text"]'
						);
					if ( $altTextarea.length ) {
						$altTextarea.val( altText );
					} else {
						// Fallback for different structures
						$(
							'textarea[name="_wp_attachment_image_alt"], .alt-text textarea'
						).val( altText );
					}

					// Update alt text in Gutenberg editor
					if ( $selectedBlock && $selectedBlock.length ) {
						$selectedBlock.find( 'img' ).attr( 'alt', altText );
						const inputEvent = new Event( 'input', {
							bubbles: true,
						} );
						$selectedBlock
							.find( 'img' )[ 0 ]
							.dispatchEvent( inputEvent );
					}

					showSuccess( $wrapper, 'Alt text generated successfully' );
				} else {
					showError(
						$wrapper,
						response.data || 'Failed to generate alt text'
					);
				}
			},
			error( xhr ) {
				let message = 'Server error occurred';
				try {
					const response = JSON.parse( xhr.responseText );
					message = response.data || message;
				} catch {
					// Silent fallback
				}
				showError( $wrapper, message );
			},
			complete() {
				$wrapper.find( 'span.spinner' ).removeClass( 'is-active' );
				$button.prop( 'disabled', false );
			},
		} );
	} );

	function showSuccess( $wrapper, message ) {
		let $successMessage = $wrapper.find( '.success-message' );
		if ( ! $successMessage.length ) {
			$wrapper.append(
				'<span class="success-message" style="color: green; margin-left: 10px; width: auto;"></span>'
			);
			$successMessage = $wrapper.find( '.success-message' );
		}
		$successMessage.text( message ).show();
		setTimeout( function () {
			$successMessage.fadeOut();
		}, 5000 );
	}
} );
