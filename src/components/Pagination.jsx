/**
 * Thread pagination controls.
 */

import { __, sprintf } from '@wordpress/i18n';

/**
 * Render previous/next controls.
 *
 * @param {Object}   props            Component props.
 * @param {number}   props.page       Current page.
 * @param {number}   props.totalPages Total pages.
 * @param {Function} props.onChange   Page change handler.
 * @return {JSX.Element|null} Pagination element.
 */
export default function Pagination( { page, totalPages, onChange } ) {
	if ( totalPages <= 1 ) {
		return null;
	}

	return (
		<nav
			className="convocept-pagination"
			aria-label={ __( 'Comment pagination', 'convocept' ) }
		>
			<button
				type="button"
				className="convocept-button convocept-button--ghost"
				disabled={ page <= 1 }
				onClick={ () => onChange( page - 1 ) }
			>
				{ __( 'Previous', 'convocept' ) }
			</button>

			<span className="convocept-pagination__status">
				{ sprintf(
					/* translators: 1: current page number, 2: total number of pages. */
					__( 'Page %1$d of %2$d', 'convocept' ),
					page,
					totalPages
				) }
			</span>

			<button
				type="button"
				className="convocept-button convocept-button--ghost"
				disabled={ page >= totalPages }
				onClick={ () => onChange( page + 1 ) }
			>
				{ __( 'Next', 'convocept' ) }
			</button>
		</nav>
	);
}
