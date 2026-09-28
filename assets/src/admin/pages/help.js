/**
 * More → Help: versions and where to get help.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { Button, Card, SummaryList } from '../ui';

/**
 * The Help page.
 *
 * @param {Object} props      Page props.
 * @param {Object} props.boot Boot data.
 * @return {Element} Page content.
 */
export function HelpPage( { boot } ) {
	return (
		<>
			<Card
				title={ __( 'Get help', 'convocept' ) }
				description={ __(
					'Ask in the support forum on WordPress.org. Include the versions below.',
					'convocept'
				) }
			>
				<div className="convocept-card__actions">
					<Button
						variant="secondary"
						href={ boot.urls.support }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Open the support forum', 'convocept' ) }
					</Button>
					<Button
						variant="tertiary"
						href={ boot.urls.website }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Visit convocept.com', 'convocept' ) }
					</Button>
				</div>
			</Card>
			<Card title={ __( 'Versions', 'convocept' ) }>
				<SummaryList
					rows={ [
						{
							label: __( 'Convocept', 'convocept' ),
							value: boot.version,
						},
						{
							label: __( 'WordPress', 'convocept' ),
							value: boot.wpVersion,
						},
						{
							label: __( 'PHP', 'convocept' ),
							value: boot.phpVersion,
						},
					] }
				/>
			</Card>
		</>
	);
}
