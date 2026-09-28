/**
 * The Settings pages: General, Appearance, Composer, Moderation & spam,
 * Subscriptions and Advanced.
 *
 * Every page receives the same props from the app:
 * `{ draft, update, errors, boot }`. `draft` holds the settings as edited,
 * `update( key, value )` changes one, `errors` maps keys to messages.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import {
	Button,
	Card,
	CheckboxList,
	ChoiceTiles,
	ColorField,
	Notice,
	NumberField,
	SelectField,
	SummaryList,
	TextareaListField,
	Toggle,
} from '../ui';
import { contrastWithWhite } from '../app/validate';

/**
 * Build the common props of a number field.
 *
 * @param {Object}                                props        Page props.
 * @param {Object}                                props.draft  Settings as edited.
 * @param {(key: string, value: unknown) => void} props.update Change one setting.
 * @param {Object}                                props.errors Error message per setting key.
 * @param {Object}                                props.boot   Boot data.
 * @param {string}                                key          Setting key.
 * @return {Object} Props for NumberField.
 */
function numberProps( { draft, update, errors, boot }, key ) {
	return {
		value: draft[ key ],
		min: boot.schema[ key ]?.minimum,
		max: boot.schema[ key ]?.maximum,
		error: errors[ key ],
		onChange: ( value ) => update( key, value ),
	};
}

/**
 * General: where Convocept shows, the thread and live updates.
 *
 * @param {Object} props Page props.
 * @return {Element} Page content.
 */
export function GeneralPage( props ) {
	const { draft, update, errors, boot } = props;

	return (
		<>
			{ ! draft.enabled && (
				<Notice
					tone="warning"
					title={ __( 'Convocept is off', 'convocept' ) }
					actions={
						<Button
							variant="primary"
							size="compact"
							onClick={ () => update( 'enabled', true ) }
						>
							{ __( 'Turn Convocept on', 'convocept' ) }
						</Button>
					}
				>
					{ __(
						"Your theme's own comments show on every post. Turn it on to use Convocept's thread and form.",
						'convocept'
					) }
				</Notice>
			) }
			<Card
				title={ __( 'Where it shows', 'convocept' ) }
				description={ __(
					"Convocept replaces the theme's comment list and form on these content types.",
					'convocept'
				) }
			>
				<Toggle
					label={ __( "Replace the theme's comments", 'convocept' ) }
					help={ __(
						'Turn it off to go back to your theme’s comments. Nothing is deleted.',
						'convocept'
					) }
					checked={ draft.enabled }
					onChange={ ( value ) => update( 'enabled', value ) }
				/>
				<CheckboxList
					label={ __( 'Show on', 'convocept' ) }
					help={ __(
						'Other content types keep their theme’s comments.',
						'convocept'
					) }
					value={ draft.post_types }
					options={ boot.postTypes }
					error={ errors.post_types }
					onChange={ ( value ) => update( 'post_types', value ) }
				/>
			</Card>
			<Card
				title={ __( 'Thread', 'convocept' ) }
				description={ __(
					'How comments are ordered and split into pages.',
					'convocept'
				) }
			>
				<SelectField
					label={ __( 'Default order', 'convocept' ) }
					help={ __(
						'Readers can still switch order above the thread.',
						'convocept'
					) }
					value={ draft.default_sort }
					options={ [
						{
							value: 'oldest',
							label: __( 'Oldest first', 'convocept' ),
						},
						{
							value: 'newest',
							label: __( 'Newest first', 'convocept' ),
						},
						{
							value: 'top',
							label: __( 'Most upvoted', 'convocept' ),
						},
					] }
					error={ errors.default_sort }
					onChange={ ( value ) => update( 'default_sort', value ) }
				/>
				<NumberField
					label={ __( 'Comments per page', 'convocept' ) }
					help={ __(
						'Top-level comments; their replies come with them.',
						'convocept'
					) }
					{ ...numberProps( props, 'per_page' ) }
				/>
				<NumberField
					label={ __( 'Reply depth', 'convocept' ) }
					help={ __(
						'Deeper replies show in one flat list at this depth.',
						'convocept'
					) }
					{ ...numberProps( props, 'max_depth' ) }
				/>
				<NumberField
					label={ __( 'Replies shown', 'convocept' ) }
					help={ __(
						'Per comment, before “Show more replies”. 0 shows them all.',
						'convocept'
					) }
					{ ...numberProps( props, 'replies_shown' ) }
				/>
				<Toggle
					label={ __( 'Readers can upvote comments', 'convocept' ) }
					help={ __(
						'One vote per reader. Adds the “Most upvoted” order.',
						'convocept'
					) }
					checked={ draft.votes }
					onChange={ ( value ) => update( 'votes', value ) }
				/>
			</Card>
			<Card
				title={ __( 'Live updates', 'convocept' ) }
				description={ __(
					'New comments appear while a reader has the page open.',
					'convocept'
				) }
			>
				<Toggle
					label={ __( 'Check for new comments', 'convocept' ) }
					help={ __(
						'Only while the tab is visible. When nothing is new, a check costs almost nothing.',
						'convocept'
					) }
					checked={ draft.live_updates }
					onChange={ ( value ) => update( 'live_updates', value ) }
				/>
				{ draft.live_updates && (
					<NumberField
						label={ __( 'Check every', 'convocept' ) }
						help={ __( 'Seconds between checks.', 'convocept' ) }
						{ ...numberProps( props, 'poll_interval' ) }
					/>
				) }
			</Card>
		</>
	);
}

/**
 * Appearance: colour scheme and accent colour.
 *
 * @param {Object}                                props        Page props.
 * @param {Object}                                props.draft  Settings as edited.
 * @param {(key: string, value: unknown) => void} props.update Change one setting.
 * @param {Object}                                props.errors Error message per setting key.
 * @param {Object}                                props.boot   Boot data.
 * @return {Element} Page content.
 */
export function AppearancePage( { draft, update, errors, boot } ) {
	const accent = draft.accent_color;
	const lowContrast =
		/^#[0-9a-f]{6}$/i.test( accent ) && contrastWithWhite( accent ) < 4.5;

	return (
		<Card
			title={ __( 'Colours', 'convocept' ) }
			description={ __(
				'How the thread looks next to your theme.',
				'convocept'
			) }
		>
			<ChoiceTiles
				label={ __( 'Colour scheme', 'convocept' ) }
				value={ draft.color_scheme }
				options={ [
					{
						value: 'auto',
						label: __( 'Match the theme', 'convocept' ),
						description: __(
							'Uses your theme’s text and background colours.',
							'convocept'
						),
					},
					{
						value: 'system',
						label: __( 'Follow the reader’s device', 'convocept' ),
						description: __(
							'Light or dark, from the reader’s system setting.',
							'convocept'
						),
					},
					{
						value: 'light',
						label: __( 'Always light', 'convocept' ),
						description: __(
							'Dark text on a light background.',
							'convocept'
						),
					},
					{
						value: 'dark',
						label: __( 'Always dark', 'convocept' ),
						description: __(
							'Light text on a dark background.',
							'convocept'
						),
					},
				] }
				onChange={ ( value ) => update( 'color_scheme', value ) }
			/>
			<ColorField
				label={ __( 'Accent colour', 'convocept' ) }
				help={ __(
					'Links, buttons and focus rings in the thread.',
					'convocept'
				) }
				warning={
					lowContrast
						? __(
								'This colour is hard to read as text on white. Pick a darker one so links stay readable.',
								'convocept'
							)
						: ''
				}
				value={ accent }
				defaultValue={ boot.defaults.accent_color }
				error={ errors.accent_color }
				onChange={ ( value ) => update( 'accent_color', value ) }
			/>
		</Card>
	);
}

/**
 * Composer: the edit window and the rules WordPress sets.
 *
 * @param {Object} props Page props.
 * @return {Element} Page content.
 */
export function ComposerPage( props ) {
	const { boot } = props;
	const yes = __( 'Yes', 'convocept' );
	const no = __( 'No', 'convocept' );
	const days = boot.discussion.closeAfterDays;

	return (
		<>
			<Card
				title={ __( 'Editing after posting', 'convocept' ) }
				description={ __(
					'Authors can fix a typo for a short while after posting, even as guests.',
					'convocept'
				) }
			>
				<NumberField
					label={ __( 'Edit window', 'convocept' ) }
					help={ __(
						'Minutes. 0 turns editing off. Moderators can always edit.',
						'convocept'
					) }
					{ ...numberProps( props, 'edit_window' ) }
				/>
			</Card>
			<Card
				title={ __( 'Set in WordPress', 'convocept' ) }
				description={ __(
					'Convocept follows your Discussion settings for who can comment.',
					'convocept'
				) }
			>
				<SummaryList
					rows={ [
						{
							label: __( 'Name and email required', 'convocept' ),
							value: boot.discussion.requireNameEmail ? yes : no,
						},
						{
							label: __(
								'Must be logged in to comment',
								'convocept'
							),
							value: boot.discussion.registration ? yes : no,
						},
						{
							label: __(
								'Every comment waits for approval',
								'convocept'
							),
							value: boot.discussion.manualApproval ? yes : no,
						},
						{
							label: __(
								'Close comments on old posts',
								'convocept'
							),
							value: days
								? sprintf(
										/* translators: %d: Number of days. */
										_n(
											'After %d day',
											'After %d days',
											days,
											'convocept'
										),
										days
									)
								: __( 'Never', 'convocept' ),
						},
					] }
				/>
				<Button variant="secondary" href={ boot.urls.discussion }>
					{ __( 'Open Discussion settings', 'convocept' ) }
				</Button>
			</Card>
		</>
	);
}

/**
 * Moderation & spam: the protection in place and the review queue.
 *
 * @param {Object} props      Page props.
 * @param {Object} props.boot Boot data.
 * @return {Element} Page content.
 */
export function ModerationPage( { boot } ) {
	const on = __( 'On', 'convocept' );
	const seconds = boot.spam.minSubmitSeconds;

	return (
		<>
			<Card
				title={ __( 'Spam protection', 'convocept' ) }
				description={ __(
					'These checks run on every comment, without asking readers to solve anything.',
					'convocept'
				) }
			>
				<SummaryList
					rows={ [
						{
							label: __(
								'Hidden trap field for bots',
								'convocept'
							),
							value: on,
						},
						{
							label: __(
								'Minimum time to write a comment',
								'convocept'
							),
							value:
								seconds > 0
									? sprintf(
											/* translators: %s: Number of seconds. */
											_n(
												'%s second',
												'%s seconds',
												seconds,
												'convocept'
											),
											seconds
										)
									: __( 'Off', 'convocept' ),
						},
						{
							label: __(
								'Limits on how fast one visitor can post',
								'convocept'
							),
							value: on,
						},
						{
							label: __( 'Akismet', 'convocept' ),
							value: boot.spam.akismet
								? __( 'Active', 'convocept' )
								: __( 'Not installed', 'convocept' ),
						},
					] }
				/>
				{ ! boot.spam.akismet && (
					<p className="convocept-help">
						{ __(
							'Convocept works with Akismet and other anti-spam plugins that check core comments.',
							'convocept'
						) }
					</p>
				) }
			</Card>
			<Card
				title={ __( 'Waiting for review', 'convocept' ) }
				description={
					boot.pending
						? sprintf(
								/* translators: %s: Number of comments. */
								_n(
									'%s comment is waiting for approval.',
									'%s comments are waiting for approval.',
									boot.pending,
									'convocept'
								),
								boot.pending.toLocaleString()
							)
						: __(
								'No comments are waiting for approval.',
								'convocept'
							)
				}
			>
				<Button variant="secondary" href={ boot.urls.pending }>
					{ __( 'Review comments', 'convocept' ) }
				</Button>
			</Card>
		</>
	);
}

/**
 * Subscriptions: email subscriptions and sending.
 *
 * @param {Object} props Page props.
 * @return {Element} Page content.
 */
export function SubscriptionsPage( props ) {
	const { draft, update } = props;

	return (
		<>
			<Card
				title={ __( 'Email subscriptions', 'convocept' ) }
				description={ __(
					'Readers get an email when someone replies, or when a post gets new comments.',
					'convocept'
				) }
			>
				<Toggle
					label={ __(
						'Readers can subscribe by email',
						'convocept'
					) }
					help={ __(
						'Adds the options to the comment form and a “subscribe without commenting” form.',
						'convocept'
					) }
					checked={ draft.subscriptions }
					onChange={ ( value ) => update( 'subscriptions', value ) }
				/>
				<Toggle
					label={ __(
						'Ask new subscribers to confirm their address',
						'convocept'
					) }
					help={ __(
						'They click a link in an email first. Recommended: it keeps strangers from signing others up.',
						'convocept'
					) }
					checked={ draft.double_opt_in }
					onChange={ ( value ) => update( 'double_opt_in', value ) }
				/>
			</Card>
			<Card
				title={ __( 'Sending', 'convocept' ) }
				description={ __(
					'Notifications go out in the background, a batch at a time.',
					'convocept'
				) }
			>
				<NumberField
					label={ __( 'Emails per batch', 'convocept' ) }
					help={ __(
						'Lower it if your email service limits how fast you can send.',
						'convocept'
					) }
					{ ...numberProps( props, 'batch_size' ) }
				/>
			</Card>
		</>
	);
}

/**
 * Advanced: visitor IPs behind proxies and structured data.
 *
 * @param {Object}                                props        Page props.
 * @param {Object}                                props.draft  Settings as edited.
 * @param {(key: string, value: unknown) => void} props.update Change one setting.
 * @param {Object}                                props.errors Error message per setting key.
 * @return {Element} Page content.
 */
export function AdvancedPage( { draft, update, errors } ) {
	return (
		<>
			<Card
				title={ __( 'Visitor IP addresses', 'convocept' ) }
				description={ __(
					'Rate limits and spam checks use the visitor’s IP address. Behind a proxy or CDN, every visitor looks like the proxy unless you trust it here.',
					'convocept'
				) }
			>
				<TextareaListField
					code
					label={ __( 'Trusted proxies', 'convocept' ) }
					help={ __(
						'One IP address or CIDR range per line. Leave empty unless your site is behind a proxy.',
						'convocept'
					) }
					value={ draft.trusted_proxies }
					error={ errors.trusted_proxies }
					onChange={ ( value ) => update( 'trusted_proxies', value ) }
				/>
				<SelectField
					label={ __( 'Header with the visitor’s IP', 'convocept' ) }
					help={ __(
						'Read only on requests from a trusted proxy.',
						'convocept'
					) }
					value={ draft.proxy_header }
					options={ [
						{ value: 'X-Forwarded-For', label: 'X-Forwarded-For' },
						{
							value: 'CF-Connecting-IP',
							label: 'CF-Connecting-IP (Cloudflare)',
						},
						{ value: 'X-Real-IP', label: 'X-Real-IP' },
						{ value: 'True-Client-IP', label: 'True-Client-IP' },
					] }
					error={ errors.proxy_header }
					onChange={ ( value ) => update( 'proxy_header', value ) }
				/>
			</Card>
			<Card
				title={ __( 'Privacy', 'convocept' ) }
				description={ __(
					'Tools → Export Personal Data and Erase Personal Data include comment subscriptions and the upvotes of registered users. Settings → Privacy has suggested text for your privacy policy.',
					'convocept'
				) }
			>
				<SelectField
					label={ __(
						'IP address stored with new comments',
						'convocept'
					) }
					help={ __(
						'Spam checks still see the full address when a comment arrives. Also applies to imported comments. Comments already stored are not changed.',
						'convocept'
					) }
					value={ draft.commenter_ip }
					options={ [
						{
							value: 'keep',
							label: __(
								'Full address (WordPress default)',
								'convocept'
							),
						},
						{
							value: 'anonymize',
							label: __(
								'Anonymized (last part removed)',
								'convocept'
							),
						},
						{ value: 'none', label: __( 'None', 'convocept' ) },
					] }
					error={ errors.commenter_ip }
					onChange={ ( value ) => update( 'commenter_ip', value ) }
				/>
			</Card>
			<Card
				title={ __( 'Search engines', 'convocept' ) }
				description={ __(
					'Comments are in the page’s HTML either way, so search engines can read them.',
					'convocept'
				) }
			>
				<Toggle
					label={ __(
						'Describe comments with structured data',
						'convocept'
					) }
					help={ __(
						'Adds schema.org data for the comments on each page. With Yoast SEO or Rank Math it joins their data.',
						'convocept'
					) }
					checked={ draft.structured_data }
					onChange={ ( value ) => update( 'structured_data', value ) }
				/>
			</Card>
			<Card
				title={ __( 'Uninstall', 'convocept' ) }
				description={ __(
					'Comments always stay: they belong to WordPress. Deactivating the plugin never deletes anything.',
					'convocept'
				) }
			>
				<Toggle
					label={ __(
						'Delete all Convocept data when the plugin is deleted',
						'convocept'
					) }
					help={ __(
						'Removes settings, subscriptions, upvotes, pins and edit history. Leave off to keep them for a later reinstall.',
						'convocept'
					) }
					checked={ draft.delete_data }
					onChange={ ( value ) => update( 'delete_data', value ) }
				/>
			</Card>
		</>
	);
}
