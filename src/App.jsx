/**
 * Application shell: header with comment count, thread, pagination,
 * and composer.
 */

import { useEffect } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import Composer from './components/Composer';
import Pagination from './components/Pagination';
import Thread from './components/Thread';
import { getConfig } from './config';
import { useComments } from './hooks/useComments';
import { useLiveUpdates } from './hooks/useLiveUpdates';

/**
 * The comment app.
 *
 * @param {Object}   props           Component props.
 * @param {Function} props.onFailure Called when the thread cannot be loaded.
 * @return {JSX.Element} App element.
 */
export default function App( { onFailure } ) {
	const config = getConfig();
	const {
		comments,
		status,
		page,
		totalPages,
		setPage,
		reload,
		merge,
		post,
		edit,
	} = useComments();

	useLiveUpdates( merge, status === 'ready' );

	useEffect( () => {
		if ( status === 'error' ) {
			onFailure();
		}
	}, [ status, onFailure ] );

	if ( status === 'error' ) {
		return (
			<p className="convocept-notice convocept-notice--error">
				{ __( 'Comments could not be loaded.', 'convocept' ) }{ ' ' }
				<button type="button" className="convocept-link" onClick={ reload }>
					{ __( 'Retry', 'convocept' ) }
				</button>
			</p>
		);
	}

	const count = comments.length;

	return (
		<div className="convocept-root">
			<header className="convocept-header">
				<h3 className="convocept-header__title">
					{ status === 'loading'
						? __( 'Comments', 'convocept' )
						: sprintf(
								/* translators: %d: number of comments. */
								_n(
									'%d Comment',
									'%d Comments',
									count,
									'convocept'
								),
								count
						  ) }
				</h3>
			</header>

			{ status === 'loading' && (
				<p className="convocept-notice" aria-live="polite">
					{ __( 'Loading comments…', 'convocept' ) }
				</p>
			) }

			<Thread comments={ comments } onReply={ post } onEdit={ edit } />

			{ config.pageComments && (
				<Pagination
					page={ page }
					totalPages={ totalPages }
					onChange={ setPage }
				/>
			) }

			{ config.commentsOpen ? (
				<Composer parent={ 0 } onSubmit={ post } />
			) : (
				<p className="convocept-notice">
					{ __( 'Comments are closed.', 'convocept' ) }
				</p>
			) }
		</div>
	);
}
