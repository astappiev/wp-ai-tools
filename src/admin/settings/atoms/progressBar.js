import React from 'react';
import { ProgressBar as WPProgressBar } from '@wordpress/components';

const ProgressBar = ( { progress, label } ) => {
	return (
		<div>
			<WPProgressBar value={ progress } />
			{ label && <p style={ { marginTop: '5px' } }>{ label }</p> }
		</div>
	);
};

export default ProgressBar;
