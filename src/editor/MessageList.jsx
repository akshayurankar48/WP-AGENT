/**
 * Scrollable message list with auto-scroll and loading states.
 *
 * @package
 * @since 1.0.0
 */

import { useRef, useEffect } from '@wordpress/element';
import { css } from '@emotion/css';
import MessageBubble from './MessageBubble';
import { ThinkingIndicator, ActionIndicator, StepperIndicator, SkeletonMessages } from './LoadingStates';
import { spacing, scrollbar } from './styles';

/* -- Styles --------------------------------------------------------- */

const list = css`
	${ scrollbar };
	flex: 1;
	overflow-y: auto;
	padding: ${ spacing.md } ${ spacing.md } ${ spacing.xs };
`;

/* -- Helpers -------------------------------------------------------- */

const ACTION_LABELS = {
	create_post: 'Creating post',
	edit_post: 'Editing post',
	delete_post: 'Deleting post',
	clone_post: 'Cloning post',
	read_blocks: 'Reading blocks',
	insert_blocks: 'Inserting blocks',
	search_posts: 'Searching posts',
	bulk_edit: 'Bulk editing',
	search_media: 'Searching media',
	import_media: 'Importing media',
	generate_image: 'Generating image',
	set_featured_image: 'Setting featured image',
	generate_content: 'Generating content',
	web_search: 'Searching the web',
	read_url: 'Reading URL',
	manage_seo: 'Managing SEO',
	list_patterns: 'Loading patterns',
	get_pattern: 'Getting pattern',
	create_pattern: 'Creating pattern',
	edit_global_styles: 'Editing styles',
	add_custom_css: 'Adding CSS',
	screenshot_page: 'Taking screenshot',
	manage_menus: 'Managing menus',
	manage_taxonomies: 'Managing taxonomies',
	install_plugin: 'Installing plugin',
	activate_plugin: 'Activating plugin',
	manage_theme: 'Managing theme',
	undo_action: 'Undoing action',
};

const getActionLabel = ( progress ) => {
	if ( ! progress || ! progress.action ) {
		return 'Working on it...';
	}
	const label = ACTION_LABELS[ progress.action ] || progress.action.replace( /_/g, ' ' );
	if ( progress.total > 1 ) {
		return `${ label } (${ progress.index }/${ progress.total })`;
	}
	return `${ label }...`;
};

/* -- Component ------------------------------------------------------ */

const MessageList = ( { messages, isStreaming, streamingContent, isLoading, actionProgress, completedSteps = [] } ) => {
	const bottomRef = useRef( null );

	// Auto-scroll to bottom when messages change or streaming content updates.
	useEffect( () => {
		bottomRef.current?.scrollIntoView( { behavior: 'smooth' } );
	}, [ messages, streamingContent, isStreaming, actionProgress ] );

	// Loading skeleton while fetching conversation history.
	if ( isLoading ) {
		return (
			<div className={ list }>
				<SkeletonMessages />
			</div>
		);
	}

	const showThinking = isStreaming && ! streamingContent && ! actionProgress;
	const showAction = isStreaming && actionProgress && actionProgress.stage === 'action_start';
	const showStepper = isStreaming && actionProgress && completedSteps.length > 0;

	// Build step labels for the stepper.
	const stepLabels = completedSteps.map( ( step ) => ( {
		...step,
		label: ACTION_LABELS[ step.action ] || step.action.replace( /_/g, ' ' ),
	} ) );

	return (
		<div className={ list }>
			{ messages.map( ( msg ) => (
				<MessageBubble
					key={ msg.id }
					role={ msg.role }
					content={ msg.content }
					timestamp={ msg.timestamp }
				/>
			) ) }

			{ /* Streaming bubble (text appearing in real time) */ }
			{ isStreaming && streamingContent && (
				<MessageBubble
					key="streaming"
					role="assistant"
					content={ streamingContent }
				/>
			) }

			{ /* Thinking indicator: AI processing, no text yet */ }
			{ showThinking && <ThinkingIndicator /> }

			{ /* Stepper indicator: multi-step progress with completed steps */ }
			{ showStepper && (
				<StepperIndicator
					completedSteps={ stepLabels }
					currentLabel={ getActionLabel( actionProgress ) }
				/>
			) }

			{ /* Simple action indicator: single action, no history yet */ }
			{ showAction && ! showStepper && <ActionIndicator label={ getActionLabel( actionProgress ) } /> }

			<div ref={ bottomRef } />
		</div>
	);
};

export default MessageList;
