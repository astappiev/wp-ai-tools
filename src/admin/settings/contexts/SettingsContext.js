/* eslint-disable no-console */
import React, { createContext, useEffect, useReducer } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { isEqual } from '../utils';

export const SettingsContext = createContext( null );

const initialState = {
	fetchedSettings: {
		ai_provider: 'openai',
		api_key: '',
		on_upload_alt_text: false,
		all_alt_text: false,
		set_title: false,
		set_caption: false,
		set_description: false,
		prompt: "Create a SEO optimized alt text for this image. Don't include quotes and keep it informative and concise.",
		language: 'english',
		available_providers: {},
		default_models: {},
	},
	stateSettings: {
		ai_provider: 'openai',
		api_key: '',
		on_upload_alt_text: false,
		all_alt_text: false,
		set_title: false,
		set_caption: false,
		set_description: false,
		prompt: "Create a SEO optimized alt text for this image. Don't include quotes and keep it informative and concise.",
		language: 'english',
		available_providers: {},
		default_models: {},
	},
	isPending: true,
	notice: '',
	hasError: false,
	canSave: false,
};

const settingsReducer = ( state, action ) => {
	const newState = Object.assign( {}, state );
	switch ( action.type ) {
		case 'FETCH_SETTINGS':
			newState.fetchedSettings = action.payload.fetchedSettings;
			newState.stateSettings = action.payload.stateSettings;
			newState.isPending = false;
			newState.canSave = false;
			if (
				action.payload.fetchedSettings
					.ai_alt_text_generator_options_fetch_settings_errors !==
				undefined
			) {
				newState.notice = __( 'An error occurred.', 'wp-ai-tools' );
				newState.hasError = true;
			}
			break;
		case 'UPDATE_SETTINGS_BEFORE':
			newState.isPending = true;
			newState.notice = '';
			newState.hasError = false;
			break;
		case 'UPDATE_SETTINGS':
			return {
				fetchedSettings: action.payload.fetchedSettings,
				stateSettings: action.payload.stateSettings,
				isPending: false,
				canSave: false,
				notice: action.payload.notice,
				hasError: action.payload.hasError,
			};
		case 'UPDATE_STATE':
			Object.keys( action.payload ).forEach( ( key ) => {
				newState[ key ] = action.payload[ key ];
			} );
			break;
	}
	return newState;
};

// Dead import compatibility helper
export const useSettings = () => {
	return null;
};

export const SettingsProvider = ( { children } ) => {
	const [ state, dispatch ] = useReducer( settingsReducer, initialState );

	const fetchSettings = async () => {
		try {
			const data =
				( await apiFetch( {
					path: '/wp-ai-tools/v1/settings',
					method: 'GET',
				} ) ) || {};
			const mergedSettings = { ...initialState.stateSettings, ...data };
			dispatch( {
				type: 'FETCH_SETTINGS',
				payload: {
					fetchedSettings: mergedSettings,
					stateSettings: mergedSettings,
				},
			} );
		} catch ( error ) {
			console.error( 'Error fetching settings:', error );
			dispatch( {
				type: 'FETCH_SETTINGS',
				payload: {
					fetchedSettings: initialState.fetchedSettings,
					stateSettings: initialState.stateSettings,
					hasError: true,
					notice: error.message || 'Failed to fetch settings',
				},
			} );
		}
	};

	useEffect( () => {
		fetchSettings();
	}, [] );

	const updateSettings = async ( settings ) => {
		dispatch( { type: 'UPDATE_SETTINGS_BEFORE' } );
		try {
			const data = await apiFetch( {
				path: '/wp-ai-tools/v1/settings',
				method: 'POST',
				data: settings,
			} );
			dispatch( {
				type: 'UPDATE_SETTINGS',
				payload: {
					fetchedSettings: data,
					stateSettings: data,
					hasError: false,
					notice: __( 'Settings updated.', 'wp-ai-tools' ),
				},
			} );
		} catch ( error ) {
			console.error( 'Error updating settings:', error );
			dispatch( {
				type: 'UPDATE_SETTINGS',
				payload: {
					fetchedSettings: settings,
					stateSettings: settings,
					hasError: true,
					notice: error.message || 'Failed to update settings',
				},
			} );
			throw error;
		}
	};

	const updateState = ( payload ) => {
		dispatch( { type: 'UPDATE_STATE', payload } );
	};

	const updateStateSettings = ( key, value ) => {
		const newStateSettings = { ...state.stateSettings };
		newStateSettings[ key ] = value;
		const canSave = ! isEqual( newStateSettings, state.fetchedSettings );
		dispatch( {
			type: 'UPDATE_STATE',
			payload: {
				stateSettings: newStateSettings,
				canSave,
			},
		} );
		return newStateSettings;
	};

	const contextValue = {
		dispatch,
		updateSettings,
		fetchSettings,
		updateState,
		updateStateSettings,
		settings: state.stateSettings,
		isPending: state.isPending,
		notice: state.notice,
		hasError: state.hasError,
		canSave: state.canSave,
		defaultModels: state.stateSettings.default_models || {},
	};

	return (
		<SettingsContext.Provider value={ contextValue }>
			{ children }
		</SettingsContext.Provider>
	);
};
