/**
 * Simple deep equality check.
 *
 * @param {*} a First value.
 * @param {*} b Second value.
 * @return {boolean} True if values are deeply equal.
 */
export function isEqual( a, b ) {
	if ( a === b ) {
		return true;
	}

	if (
		typeof a !== 'object' ||
		a === null ||
		typeof b !== 'object' ||
		b === null
	) {
		return false;
	}

	const keysA = Object.keys( a );
	const keysB = Object.keys( b );

	if ( keysA.length !== keysB.length ) {
		return false;
	}

	for ( const key of keysA ) {
		if ( ! keysB.includes( key ) ) {
			return false;
		}
		if ( ! isEqual( a[ key ], b[ key ] ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Creates a debounced function that delays invoking func until after wait milliseconds
 * have elapsed since the last time the debounced function was invoked.
 *
 * @param {Function} func The function to debounce.
 * @param {number}   wait The number of milliseconds to delay.
 * @return {Function} The debounced function.
 */
export function debounce( func, wait ) {
	let timeout;
	return function executedFunction( ...args ) {
		const later = () => {
			clearTimeout( timeout );
			func( ...args );
		};
		clearTimeout( timeout );
		timeout = setTimeout( later, wait );
	};
}
