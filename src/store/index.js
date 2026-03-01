/**
 * WP Agent chat store registration.
 *
 * @package
 * @since 1.0.0
 */

import { createReduxStore, register } from '@wordpress/data';
import { STORE_NAME } from './constants';
import reducer from './reducer';
import * as actions from './actions';
import * as selectors from './selectors';

const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
} );

register( store );

export default store;
