/* eslint-disable no-console */
import React, { useContext, useEffect, useState } from 'react';
import {
	Button,
	Card,
	CardBody,
	Notice,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { SettingsContext } from '../contexts/SettingsContext';
import ProgressBar from '../atoms/progressBar';

const processNextImage = async () => {
	try {
		return await apiFetch( {
			path: '/wp-ai-tools/v1/process-next',
			method: 'POST',
		} );
	} catch ( e ) {
		console.error( 'Error processing next image:', e );
		throw e;
	}
};

const BulkGeneration = () => {
	const { settings, updateStateSettings } = useContext( SettingsContext );
	const { all_alt_text: allAltText = false } = settings || {};

	const [ isProcessing, setIsProcessing ] = useState( false );
	const [ progress, setProgress ] = useState( { total: 0, current: 0 } );
	const [ errorMsg, setErrorMsg ] = useState( '' );
	const [ successMsg, setSuccessMsg ] = useState( '' );

	const checkStatus = async () => {
		try {
			const response = await apiFetch( {
				path: '/wp-ai-tools/v1/processing-status',
				method: 'GET',
			} );
			if ( response.is_processing ) {
				setIsProcessing( true );
				setProgress( {
					total: response.total_items,
					current: response.current_item,
				} );
				if ( response.current_item >= response.total_items ) {
					setIsProcessing( false );
					setSuccessMsg(
						__( 'Bulk generation completed!', 'wp-ai-tools' )
					);
					setProgress( { total: 0, current: 0 } );
				}
			} else {
				setIsProcessing( false );
			}
		} catch ( e ) {
			console.error( 'Error checking processing status:', e );
		}
	};

	useEffect( () => {
		checkStatus();
	}, [] );

	useEffect( () => {
		if ( ! isProcessing ) {
			return;
		}
		const interval = setInterval( checkStatus, 2000 );
		return () => clearInterval( interval );
	}, [ isProcessing ] );

	const getAPIKey = () => settings?.api_key || '';

	const handleStartProcessing = async () => {
		if ( ! getAPIKey() ) {
			setErrorMsg(
				__( 'Please set your API key first.', 'wp-ai-tools' )
			);
			return;
		}

		setErrorMsg( '' );
		setSuccessMsg( '' );

		try {
			const startResponse = await apiFetch( {
				path: '/wp-ai-tools/v1/start-processing',
				method: 'POST',
			} );

			if ( startResponse.status === 'success' ) {
				setIsProcessing( true );
				setProgress( { total: startResponse.total_items, current: 0 } );
				setSuccessMsg(
					__( 'Bulk generation started…', 'wp-ai-tools' )
				);

				// Start processing loop
				( async () => {
					try {
						let active = true;
						while ( active ) {
							const result = await processNextImage();
							if ( result.status === 'completed' ) {
								setIsProcessing( false );
								setSuccessMsg(
									__(
										'All images processed successfully!',
										'wp-ai-tools'
									)
								);
								setProgress( { total: 0, current: 0 } );
								active = false;
								break;
							}
							if ( result.status === 'error' ) {
								if ( result.current >= result.total ) {
									active = false;
									setIsProcessing( false );
									break;
								}
							}

							setProgress( {
								total: result.total,
								current: result.current,
							} );
							if ( result.current >= result.total ) {
								setIsProcessing( false );
								setSuccessMsg(
									__(
										'All images processed successfully!',
										'wp-ai-tools'
									)
								);
								setProgress( {
									total: result.total,
									current: result.total,
								} );
								active = false;
								break;
							}

							await new Promise( ( resolve ) =>
								setTimeout( resolve, 1000 )
							);

							// Check if stopped externally
							const statusCheck = await apiFetch( {
								path: '/wp-ai-tools/v1/processing-status',
								method: 'GET',
							} );
							if ( ! statusCheck.is_processing ) {
								active = false;
								setIsProcessing( false );
								break;
							}
						}
					} catch ( e ) {
						console.error( 'Error during processing:', e );
						setErrorMsg(
							e.message ||
								__( 'Error during processing', 'wp-ai-tools' )
						);
						setIsProcessing( false );
					}
				} )();
			} else {
				setErrorMsg(
					startResponse.message ||
						__( 'Failed to start processing', 'wp-ai-tools' )
				);
			}
		} catch ( e ) {
			setErrorMsg(
				e.message ||
					__( 'Error starting bulk processing', 'wp-ai-tools' )
			);
		}
	};

	const handleStopProcessing = async () => {
		try {
			await apiFetch( {
				path: '/wp-ai-tools/v1/stop-processing',
				method: 'POST',
			} );
			setIsProcessing( false );
			setSuccessMsg( __( 'Processing stopped', 'wp-ai-tools' ) );
			setProgress( { total: 0, current: 0 } );
		} catch ( e ) {
			setErrorMsg(
				e.message || __( 'Error stopping processing', 'wp-ai-tools' )
			);
		}
	};

	return (
		<Card>
			<CardBody>
				<h3>{ __( 'Bulk Generation', 'wp-ai-tools' ) }</h3>
				<p>
					{ __(
						'Generate alt text for multiple images at once.',
						'wp-ai-tools'
					) }
				</p>
				<div style={ { marginTop: '16px' } }>
					<ToggleControl
						label={ __( 'Process All Images', 'wp-ai-tools' ) }
						help={ __(
							'Generate alt text for all images, even if they already have it.',
							'wp-ai-tools'
						) }
						checked={ allAltText }
						onChange={ ( val ) =>
							updateStateSettings( 'all_alt_text', val )
						}
						disabled={ isProcessing }
					/>
				</div>

				{ errorMsg && (
					<Notice status="error" isDismissible={ false }>
						{ errorMsg }
					</Notice>
				) }
				{ successMsg && (
					<Notice status="success" isDismissible={ false }>
						{ successMsg }
					</Notice>
				) }

				{ isProcessing && progress.total > 0 && (
					<div style={ { marginTop: '16px', marginBottom: '16px' } }>
						<ProgressBar
							progress={
								( progress.current / progress.total ) * 100
							}
							label={ `${ progress.current } / ${ progress.total } images processed` }
						/>
					</div>
				) }

				<div style={ { marginTop: '16px' } }>
					{ isProcessing ? (
						<Button isSecondary onClick={ handleStopProcessing }>
							{ __( 'Stop Processing', 'wp-ai-tools' ) }
						</Button>
					) : (
						<Button
							isPrimary
							onClick={ handleStartProcessing }
							disabled={ ! getAPIKey() }
						>
							{ __( 'Start Bulk Generation', 'wp-ai-tools' ) }
						</Button>
					) }
				</div>
			</CardBody>
		</Card>
	);
};

export default BulkGeneration;
