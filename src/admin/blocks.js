/* eslint-disable no-console */
import apiFetch from '@wordpress/api-fetch';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment, useState } from '@wordpress/element';
import { Button, PanelBody } from '@wordpress/components';
import { InspectorControls } from '@wordpress/block-editor';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

const generateAltText = async ( imageId ) => {
	try {
		console.log( 'Generating alt text for image ID:', imageId );
		const response = await apiFetch( {
			path: '/wp-ai-tools/v1/generate-test',
			method: 'POST',
			data: { image_id: imageId },
		} );
		if ( ! response.success ) {
			throw new Error(
				response.message || 'Failed to generate alt text'
			);
		}
		return response.alt_text;
	} catch ( error ) {
		console.error( 'Error generating alt text:', error );
		throw error;
	}
};

const withGenerateAltTextButton = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const { name, attributes, setAttributes } = props;
		const [ statusMessage, setStatusMessage ] = useState( '' );
		const [ isSuccess, setIsSuccess ] = useState( false );
		const [ isGenerating, setIsGenerating ] = useState( false );

		if ( name !== 'core/image' ) {
			return <BlockEdit { ...props } />;
		}

		const handleGenerate = async () => {
			const { id: imageId } = attributes;
			if ( ! imageId ) {
				setStatusMessage(
					__(
						'Please select an image from the Media Library first.',
						'wp-ai-tools'
					)
				);
				setIsSuccess( false );
				return;
			}

			setIsGenerating( true );
			setStatusMessage( __( 'Generating alt text…', 'wp-ai-tools' ) );
			setIsSuccess( false );

			try {
				const altText = await generateAltText( imageId );
				setAttributes( { alt: altText } );
				setStatusMessage(
					__( 'Alt text generated successfully!', 'wp-ai-tools' )
				);
				setIsSuccess( true );
			} catch ( error ) {
				setStatusMessage(
					error.message ||
						__( 'Error generating alt text.', 'wp-ai-tools' )
				);
				setIsSuccess( false );
			} finally {
				setIsGenerating( false );
			}
		};

		return (
			<Fragment>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ __( 'Generate Alt Text', 'wp-ai-tools' ) }
						initialOpen={ true }
					>
						<Button
							isSecondary
							onClick={ handleGenerate }
							disabled={ isGenerating }
						>
							{ __( 'Generate Alt Text', 'wp-ai-tools' ) }
						</Button>
						{ statusMessage && (
							<Fragment>
								<br />
								<br />
								<p
									style={ {
										color: isSuccess
											? '#00a32a'
											: '#cc1818',
									} }
								>
									{ statusMessage }
								</p>
							</Fragment>
						) }
					</PanelBody>
				</InspectorControls>
			</Fragment>
		);
	};
}, 'withGenerateAltTextButton' );

addFilter(
	'editor.BlockEdit',
	'wp-ai-tools/with-generate-alt-text-button',
	withGenerateAltTextButton
);
