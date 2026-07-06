import './settings.scss';
import React, { useContext, useEffect, useCallback } from 'react';
import { createRoot } from 'react-dom/client';
import { HashRouter, Navigate, NavLink, Route, Routes } from 'react-router-dom';
import { Notice, Popover, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import {
	SettingsContext,
	SettingsProvider,
} from './settings/contexts/SettingsContext';
import GeneralSettings from './settings/pages/general';
import TestPrompt from './settings/pages/testing';
import BulkGeneration from './settings/pages/bulk-generate';

// Popover Notice Component
const SaveNotice = () => {
	const { notice, hasError, updateState } = useContext( SettingsContext );

	const clearNotice = useCallback( () => {
		updateState( { notice: '', hasError: false } );
	}, [ updateState ] );

	useEffect( () => {
		const timer = setTimeout( clearNotice, 5000 );
		return () => clearTimeout( timer );
	}, [ clearNotice ] );

	return (
		<Popover className="wp-ai-tools-popover">
			<Notice
				className="wp-ai-tools-notice"
				onRemove={ clearNotice }
				status={ hasError ? 'error' : 'success' }
			>
				<p>{ notice }</p>
			</Notice>
		</Popover>
	);
};

// Navigation Tabs
const Navigation = () => {
	const tabs = [
		{ to: 'general', title: __( 'General', 'wp-ai-tools' ) },
		{ to: 'testing', title: __( 'Testing', 'wp-ai-tools' ) },
		{ to: 'bulk-generate', title: __( 'Bulk Generation', 'wp-ai-tools' ) },
	];

	return (
		<nav
			className="wp-ai-tools-navigation"
			style={ { marginBottom: '10px' } }
		>
			<ul
				style={ {
					display: 'flex',
					gap: '25px',
					margin: 0,
					padding: '0 15px',
					listStyle: 'none',
				} }
			>
				{ tabs.map( ( tab, idx ) => (
					<li key={ idx } style={ { margin: 0, padding: 0 } }>
						<NavLink
							to={ tab.to }
							className={ ( { isActive } ) =>
								isActive ? 'wp-ai-tools-nav-active' : ''
							}
						>
							{ tab.title }
						</NavLink>
					</li>
				) ) }
			</ul>
		</nav>
	);
};

// Header
const Header = () => {
	const { isPending, notice } = useContext( SettingsContext );

	return (
		<>
			<header className="wp-ai-tools-header wp-ai-tools-header-sticky">
				<div
					style={ {
						display: 'flex',
						alignItems: 'center',
						justifyContent: 'space-between',
					} }
				>
					<div className="wp-ai-tools-title">
						<h1>{ __( 'Settings', 'wp-ai-tools' ) }</h1>
					</div>
				</div>
			</header>
			{ notice && ! isPending && <SaveNotice /> }
			<Navigation />
		</>
	);
};

// Footer
const Footer = () => (
	<footer className="wp-ai-tools-footer">
		<p></p>
	</footer>
);

// Main Layout component
const SettingsLayout = () => {
	const { settings } = useContext( SettingsContext );

	if ( ! settings || Object.keys( settings ).length === 0 ) {
		return <Spinner className="wp-ai-tools-page-loader" />;
	}

	return (
		<div className="wp-ai-tools">
			<Header />
			<main className="wp-ai-tools-main">
				<Routes>
					<Route path="/general" element={ <GeneralSettings /> } />
					<Route path="/testing" element={ <TestPrompt /> } />
					<Route
						path="/bulk-generate"
						element={ <BulkGeneration /> }
					/>
					<Route
						path="/"
						element={ <Navigate replace to="/general" /> }
					/>
				</Routes>
			</main>
			<Footer />
		</div>
	);
};

// App Root
const App = () => (
	<HashRouter basename="/">
		<SettingsProvider>
			<SettingsLayout />
		</SettingsProvider>
	</HashRouter>
);

// Mount the App when DOM is loaded
const initApp = () => {
	const rootId = 'wp-ai-tools';
	const container = document.getElementById( rootId );
	if ( container ) {
		createRoot( container ).render( <App /> );
	} else {
		// eslint-disable-next-line no-console
		console.error( 'Root element not found:', rootId );
	}
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initApp );
} else {
	initApp();
}
