/**
 * Typing indicator — three animated dots shown inside the streaming bubble.
 *
 * @package
 * @since 1.0.0
 */

import { css } from '@emotion/css';
import { colors, radii, spacing, bounce } from './styles';

const wrap = css`
	display: flex;
	align-items: center;
	gap: 4px;
	padding: 4px ${ spacing.sm };
`;

const dotBase = ( delay ) => css`
	display: inline-block;
	width: 6px;
	height: 6px;
	border-radius: ${ radii.full };
	background: ${ colors.textMuted };
	animation: ${ bounce } 1.4s ease-in-out ${ delay }s infinite;
`;

// Pre-compute all three variants at module scope.
const dot0 = dotBase( 0 );
const dot1 = dotBase( 0.16 );
const dot2 = dotBase( 0.32 );

const TypingIndicator = () => (
	<div className={ wrap }>
		<span className={ dot0 } />
		<span className={ dot1 } />
		<span className={ dot2 } />
	</div>
);

export default TypingIndicator;
