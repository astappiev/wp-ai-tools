import React, { useContext, useState } from 'react';
import {
	Button,
	Card,
	CardBody,
	Notice,
	Placeholder,
	Spinner,
	TextareaControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { image as imageIcon } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';
import { SettingsContext } from '../contexts/SettingsContext';

const TestPrompt = () => {
	const { settings } = useContext( SettingsContext );
	const {
		ai_provider: aiProvider,
		prompt,
		available_providers: availableProviders,
	} = settings || {};
	const [ selectedImage, setSelectedImage ] = useState( null );
	const [ generatedAlt, setGeneratedAlt ] = useState( '' );
	const [ isGenerating, setIsGenerating ] = useState( false );
	const [ errorMsg, setErrorMsg ] = useState( '' );

	const getAPIKey = () => settings?.api_key || '';

	const openMediaModal = () => {
		const frame = window.wp.media( {
			title: __( 'Select or Upload Image', 'wp-ai-tools' ),
			library: { type: 'image' },
			multiple: false,
			button: { text: __( 'Use this image', 'wp-ai-tools' ) },
		} );
		frame.on( 'select', () => {
			const attachment = frame
				.state()
				.get( 'selection' )
				.first()
				.toJSON();
			setSelectedImage( {
				id: attachment.id,
				url: attachment.url,
				alt: attachment.alt || '',
			} );
		} );
		frame.open();
	};

	const handleGenerate = async () => {
		if ( ! selectedImage ) {
			setErrorMsg( __( 'Please select an image first', 'wp-ai-tools' ) );
			return;
		}
		if ( ! getAPIKey() ) {
			setErrorMsg(
				`Please set your ${
					availableProviders?.[ aiProvider ] || aiProvider
				} API key in the General tab.`
			);
			return;
		}

		setIsGenerating( true );
		setErrorMsg( '' );
		setGeneratedAlt( '' );

		try {
			const response = await apiFetch( {
				path: '/wp-ai-tools/v1/generate-test',
				method: 'POST',
				data: { image_id: selectedImage.id, prompt },
			} );
			if ( response.success ) {
				setGeneratedAlt( response.alt_text );
			} else {
				setErrorMsg(
					response.message ||
						__( 'Failed to generate alt text', 'wp-ai-tools' )
				);
			}
		} catch ( error ) {
			setErrorMsg(
				error.message ||
					__( 'Error generating alt text', 'wp-ai-tools' )
			);
		} finally {
			setIsGenerating( false );
		}
	};

	return (
		<Card>
			<CardBody>
				<h3>{ __( 'Test Prompt', 'wp-ai-tools' ) }</h3>
				<p>
					{ __(
						'Test your prompt with a sample image.',
						'wp-ai-tools'
					) }
				</p>
				<div style={ { marginTop: '16px' } }>
					{ selectedImage ? (
						<div>
							<img
								src={ selectedImage.url }
								alt="Test"
								style={ {
									maxWidth: '150px',
									height: 'auto',
									marginBottom: '10px',
									borderRadius: '4px',
								} }
							/>
							<div style={ { display: 'flex', gap: '8px' } }>
								<Button isSecondary onClick={ openMediaModal }>
									{ __( 'Change Image', 'wp-ai-tools' ) }
								</Button>
								<Button
									isPrimary
									onClick={ handleGenerate }
									disabled={ isGenerating || ! getAPIKey() }
								>
									{ isGenerating ? (
										<Spinner />
									) : (
										__( 'Generate Alt Text', 'wp-ai-tools' )
									) }
								</Button>
							</div>
						</div>
					) : (
						<Placeholder
							icon={ imageIcon }
							label={ __( 'Test Image', 'wp-ai-tools' ) }
							instructions={ __(
								'Select an image to test your prompt.',
								'wp-ai-tools'
							) }
						>
							<Button isPrimary onClick={ openMediaModal }>
								{ __(
									'Select or Upload Image',
									'wp-ai-tools'
								) }
							</Button>
						</Placeholder>
					) }
				</div>
				{ errorMsg && (
					<Notice status="error" isDismissible={ false }>
						{ errorMsg }
					</Notice>
				) }
				{ generatedAlt && (
					<div style={ { marginTop: '16px' } }>
						<TextareaControl
							label={ __( 'Generated Alt Text:', 'wp-ai-tools' ) }
							value={ generatedAlt }
							readOnly
						/>
					</div>
				) }
			</CardBody>
		</Card>
	);
};

export default TestPrompt;
