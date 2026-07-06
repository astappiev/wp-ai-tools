/* eslint-disable no-console */
import React, { useCallback, useEffect, useState, useMemo } from 'react';
import { SettingsContext } from '../contexts/SettingsContext';
import { useContext } from '@wordpress/element';
import { debounce, isEqual } from '../utils';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	Notice,
	SelectControl,
	Spinner,
	TextareaControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const GeneralSettings = () => {
	const { settings, updateSettings, isPending, notice, hasError } =
		useContext( SettingsContext );

	// Local state for all settings, acting as a "draft"
	const [ localSettings, setLocalSettings ] = useState( settings );
	const [ isDirty, setIsDirty ] = useState( false );

	const [ isKeyValid, setIsKeyValid ] = useState( true );
	const [ keyValidationMessage, setKeyValidationMessage ] = useState( '' );

	// Sync local state when the main context settings change (e.g., after a save)
	useEffect( () => {
		setLocalSettings( settings );
	}, [ settings ] );

	// Check for unsaved changes to enable/disable the save button
	useEffect( () => {
		setIsDirty( ! isEqual( settings, localSettings ) );
	}, [ settings, localSettings ] );

	// Generic handler to update a setting in the local state
	const handleSettingChange = ( key, value ) => {
		setLocalSettings( ( prev ) => {
			const updated = { ...prev, [ key ]: value };
			// If changing provider, we should also update the model to its default for that provider
			if ( key === 'ai_provider' ) {
				updated.model = settings?.default_models[ value ] || '';
			}
			return updated;
		} );
	};

	const handleSaveAllSettings = async () => {
		try {
			await updateSettings( localSettings );
		} catch ( error ) {
			console.error( 'Failed to save settings:', error );
		}
	};

	const validateAPIKey = useCallback( async ( key, provider ) => {
		if ( ! key || key.length < 10 ) {
			setIsKeyValid( true );
			setKeyValidationMessage( '' );
			return;
		}
		setIsKeyValid( false );
		setKeyValidationMessage( '' );
		try {
			const response = await apiFetch( {
				path: '/wp-ai-tools/v1/validate-key',
				method: 'POST',
				data: { key, provider },
			} );
			setIsKeyValid( response.valid );
			setKeyValidationMessage( response.message );
			if ( response.valid ) {
				setTimeout( () => setKeyValidationMessage( '' ), 3000 );
			}
		} catch ( error ) {
			setIsKeyValid( false );
			setKeyValidationMessage(
				error.message || 'Failed to validate API key'
			);
		}
	}, [] );

	const debouncedValidate = useMemo(
		() => debounce( validateAPIKey, 500 ),
		[ validateAPIKey ]
	);

	const currentProvider = localSettings.ai_provider;
	const currentKey = localSettings.api_key;

	// When provider or key changes, validate the key
	useEffect( () => {
		if ( currentProvider ) {
			if ( currentKey && currentKey.length > 10 ) {
				debouncedValidate( currentKey, currentProvider );
			} else {
				setIsKeyValid( true );
				setKeyValidationMessage( '' );
			}
		}
	}, [ currentProvider, currentKey, debouncedValidate ] );

	const providerLabel =
		settings?.available_providers?.[ localSettings.ai_provider ] ||
		localSettings.ai_provider;

	return (
		<>
			<Card>
				<CardBody style={ { marginBottom: '10px' } }>
					<div style={ { marginBottom: '24px' } }>
						<SelectControl
							label={ __( 'AI Provider', 'wp-ai-tools' ) }
							value={ localSettings.ai_provider }
							options={ Object.entries(
								settings?.available_providers || {}
							).map( ( [ key, label ] ) => ( {
								label,
								value: key,
							} ) ) }
							onChange={ ( value ) =>
								handleSettingChange( 'ai_provider', value )
							}
						/>
					</div>

					<TextControl
						label={ sprintf(
							// translators: %s: Name of AI provider
							__( '%s API Key', 'wp-ai-tools' ),
							providerLabel
						) }
						placeholder={ __(
							'Enter API key here',
							'wp-ai-tools'
						) }
						value={ localSettings.api_key || '' }
						onChange={ ( value ) =>
							handleSettingChange( 'api_key', value )
						}
						type="password"
					/>
					{ keyValidationMessage && (
						<Notice
							status={ isKeyValid ? 'success' : 'error' }
							isDismissible={ false }
						>
							{ keyValidationMessage }
						</Notice>
					) }
					{ settings?.help_urls?.[ localSettings.ai_provider ] && (
						<a
							href={
								settings.help_urls[ localSettings.ai_provider ]
							}
							target="_blank"
							rel="noreferrer noopener"
						>
							{ __(
								'Get help for the API key here',
								'wp-ai-tools'
							) }
						</a>
					) }

					<div style={ { marginTop: '16px' } }>
						<TextControl
							label={ __( 'Model', 'wp-ai-tools' ) }
							help={ __(
								'Enter the exact model name (e.g., gpt-4o-mini, claude-3-haiku-20240307)',
								'wp-ai-tools'
							) }
							value={ localSettings.model || '' }
							onChange={ ( value ) =>
								handleSettingChange( 'model', value )
							}
						/>
					</div>

					<div style={ { marginTop: '16px' } }>
						<TextControl
							label={ __( 'Language', 'wp-ai-tools' ) }
							help={ __(
								'Enter the language to write the alt text in (e.g., English, French, Spanish)',
								'wp-ai-tools'
							) }
							value={ localSettings.language || '' }
							onChange={ ( value ) =>
								handleSettingChange( 'language', value )
							}
						/>
					</div>

					<div style={ { marginTop: '32px' } }>
						<TextareaControl
							label={ __( 'Prompt Template', 'wp-ai-tools' ) }
							help={ __(
								'Customize the prompt used to generate alt text',
								'wp-ai-tools'
							) }
							value={ localSettings.prompt || '' }
							onChange={ ( value ) =>
								handleSettingChange( 'prompt', value )
							}
							rows={ 4 }
						/>
					</div>

					<div style={ { marginTop: '16px' } }>
						<ToggleControl
							label={ __( 'Generate on Upload', 'wp-ai-tools' ) }
							help={ __(
								'Automatically generate alt text when images are uploaded',
								'wp-ai-tools'
							) }
							checked={
								localSettings.on_upload_alt_text || false
							}
							onChange={ ( value ) =>
								handleSettingChange(
									'on_upload_alt_text',
									value
								)
							}
						/>
					</div>

					<div style={ { marginTop: '16px' } }>
						<ToggleControl
							label={ __( 'Set Image Title', 'wp-ai-tools' ) }
							help={ __(
								'Automatically set the image title to the generated alt text',
								'wp-ai-tools'
							) }
							checked={ localSettings.set_title || false }
							onChange={ ( value ) =>
								handleSettingChange( 'set_title', value )
							}
						/>
					</div>

					<div style={ { marginTop: '16px' } }>
						<ToggleControl
							label={ __( 'Set Image Caption', 'wp-ai-tools' ) }
							help={ __(
								'Automatically set the image caption to the generated alt text',
								'wp-ai-tools'
							) }
							checked={ localSettings.set_caption || false }
							onChange={ ( value ) =>
								handleSettingChange( 'set_caption', value )
							}
						/>
					</div>

					<div style={ { marginTop: '16px' } }>
						<ToggleControl
							label={ __(
								'Set Image Description',
								'wp-ai-tools'
							) }
							help={ __(
								'Automatically set the image description to the generated alt text',
								'wp-ai-tools'
							) }
							checked={ localSettings.set_description || false }
							onChange={ ( value ) =>
								handleSettingChange( 'set_description', value )
							}
						/>
					</div>
				</CardBody>
			</Card>

			<div
				style={ {
					position: 'sticky',
					bottom: '20px',
					padding: '10px',
					backgroundColor: '#fff',
					borderTop: '1px solid #ddd',
					zIndex: 100,
					display: 'flex',
					alignItems: 'center',
				} }
			>
				<Button
					isPrimary
					onClick={ handleSaveAllSettings }
					disabled={ ! isDirty || isPending || ! isKeyValid }
				>
					{ __( 'Save Changes', 'wp-ai-tools' ) }
				</Button>
				{ isPending && <Spinner style={ { marginLeft: '10px' } } /> }
				{ notice && (
					<span
						style={ {
							marginLeft: '10px',
							color: hasError ? 'red' : 'green',
						} }
					>
						{ notice }
					</span>
				) }
			</div>
		</>
	);
};

export default GeneralSettings;
