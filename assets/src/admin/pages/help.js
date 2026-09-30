/**
 * More → Help: documentation, where to get help, and the versions to quote.
 */

/**
 * WordPress dependencies
 */
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies
 */
import { Button, Card, Icon } from '../ui';

/**
 * The Help page.
 *
 * @param {Object} props      Page props.
 * @param {Object} props.boot Boot data.
 * @return {Element} Page content.
 */
export function HelpPage( { boot } ) {
	const { createSuccessNotice } = useDispatch( noticesStore );
	const versions = [
		{ label: __( 'Convocept', 'convocept' ), value: boot.version },
		{ label: __( 'WordPress', 'convocept' ), value: boot.wpVersion },
		{ label: __( 'PHP', 'convocept' ), value: boot.phpVersion },
	];

	/**
	 * Copy the versions as plain lines, ready to paste into a forum post.
	 *
	 * @return {Promise<void>}
	 */
	const copy = async () => {
		const text = versions
			.map( ( item ) => `${ item.label }: ${ item.value }` )
			.join( '\n' );

		try {
			await window.navigator.clipboard.writeText( text );
			createSuccessNotice( __( 'Versions copied.', 'convocept' ), {
				type: 'snackbar',
			} );
		} catch {
			createSuccessNotice(
				__(
					'Your browser blocked copying. Select the versions and copy them instead.',
					'convocept'
				),
				{ type: 'snackbar' }
			);
		}
	};

	const external = (
		<>
			<Icon name="external" size={ 16 } />
			<span className="screen-reader-text">
				{ __( '(opens in a new tab)', 'convocept' ) }
			</span>
		</>
	);

	return (
		<>
			<Card
				title={ __( 'Documentation', 'convocept' ) }
				description={ __(
					'Setting up, moving from Disqus, theming and the hooks reference.',
					'convocept'
				) }
				action={
					<Button
						variant="primary"
						size="compact"
						href={ boot.urls.docs }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Read the docs', 'convocept' ) }
						{ external }
					</Button>
				}
			/>
			<Card
				title={ __( 'Get help', 'convocept' ) }
				description={ __(
					'Ask in the support forum on WordPress.org. Paste the versions below into your post.',
					'convocept'
				) }
				action={
					<Button
						variant="secondary"
						size="compact"
						href={ boot.urls.support }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Support forum', 'convocept' ) }
						{ external }
					</Button>
				}
			/>
			<Card
				title={ __( 'Versions', 'convocept' ) }
				description={ __(
					'What’s running on this site.',
					'convocept'
				) }
				action={
					<Button
						variant="secondary"
						size="compact"
						icon={ <Icon name="copy" size={ 16 } /> }
						onClick={ copy }
					>
						{ __( 'Copy versions', 'convocept' ) }
					</Button>
				}
			>
				<dl className="convocept-versions">
					{ versions.map( ( item ) => (
						<div key={ item.label }>
							<dt>{ item.label }</dt>
							<dd>{ item.value }</dd>
						</div>
					) ) }
				</dl>
			</Card>
		</>
	);
}
