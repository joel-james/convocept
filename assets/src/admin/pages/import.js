/**
 * Manage → Import: upload an export, review the dry run, import with
 * progress, then read the report.
 *
 * Talks to the `/convocept/v1/import` routes. The job lives on the server,
 * so closing the page mid-import loses nothing: opening it again shows the
 * job and Continue resumes it.
 */

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies
 */
import {
	Button,
	Card,
	FileField,
	Notice,
	ProgressBar,
	SummaryList,
} from '../ui';

const PATH = '/convocept/v1/import';

/**
 * Format a count for display.
 *
 * @param {number} value Count.
 * @return {string} Localised number.
 */
function count( value ) {
	return ( value || 0 ).toLocaleString();
}

/**
 * Get a counter of a job.
 *
 * @param {Object} job  Job.
 * @param {string} name Counter name.
 * @return {number} Value.
 */
function counter( job, name ) {
	return job.counts?.[ name ] || 0;
}

/**
 * Leave out zero rows except where the zero is the news.
 *
 * @param {number} value Count.
 * @return {string|null} Formatted count, or null to hide the row.
 */
function nonZero( value ) {
	return value ? count( value ) : null;
}

/**
 * The Import page.
 *
 * @param {Object} props      Page props.
 * @param {Object} props.boot Boot data.
 * @return {Element} Page content.
 */
export function ImportPage( { boot } ) {
	const [ state, setState ] = useState( null );
	const [ file, setFile ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ running, setRunning ] = useState( false );
	const stop = useRef( false );
	const { createSuccessNotice } = useDispatch( noticesStore );

	const setJob = useCallback(
		( job ) => setState( ( current ) => ( { ...current, job } ) ),
		[]
	);

	useEffect( () => {
		apiFetch( { path: PATH } )
			.then( setState )
			.catch( ( failure ) => setError( failure.message ) );

		return () => {
			stop.current = true;
		};
	}, [] );

	/**
	 * Run batches until the job is done, stopped or fails.
	 *
	 * @return {Promise<void>}
	 */
	const run = useCallback( async () => {
		setRunning( true );
		setError( '' );
		stop.current = false;

		while ( ! stop.current ) {
			let job;

			try {
				( { job } = await apiFetch( {
					path: `${ PATH }/batch`,
					method: 'POST',
				} ) );
			} catch ( failure ) {
				// Another tab holds the lock: wait and try again.
				if ( 'convocept_import_locked' === failure.code ) {
					await new Promise( ( resolve ) =>
						setTimeout( resolve, 3000 )
					);
					continue;
				}

				setError(
					failure.message || __( 'The import stopped.', 'convocept' )
				);
				break;
			}

			setJob( job );

			if ( 'done' === job.status ) {
				break;
			}
		}

		setRunning( false );
	}, [ setJob ] );

	if ( ! state ) {
		return error ? (
			<Notice tone="error" title={ error } />
		) : (
			<Card title={ __( 'Loading…', 'convocept' ) } />
		);
	}

	const importer = state.importers[ 0 ];
	const job = state.job;

	/**
	 * Upload the chosen file and run the dry run.
	 *
	 * @return {Promise<void>}
	 */
	const upload = async () => {
		const body = new window.FormData();

		body.append( 'importer', importer.id );
		body.append( 'file', file );
		setBusy( true );
		setError( '' );

		try {
			const response = await apiFetch( {
				path: PATH,
				method: 'POST',
				body,
			} );

			setJob( response.job );
			setFile( null );
		} catch ( failure ) {
			setError( failure.message );
		}

		setBusy( false );
	};

	/**
	 * Cancel the import, or clear a finished one.
	 *
	 * @param {string} message Snackbar text, or '' for none.
	 * @return {Promise<void>}
	 */
	const cancel = async ( message ) => {
		stop.current = true;
		setBusy( true );

		try {
			await apiFetch( { path: PATH, method: 'DELETE' } );
			setJob( null );
			setError( '' );

			if ( message ) {
				createSuccessNotice( message, { type: 'snackbar' } );
			}
		} catch ( failure ) {
			setError( failure.message );
		}

		setBusy( false );
	};

	const errorNotice = error && <Notice tone="error" title={ error } />;

	if ( ! job ) {
		return (
			<>
				{ errorNotice }
				<Card
					title={ __( 'Import from Disqus', 'convocept' ) }
					description={ __(
						'In Disqus, open Admin → Moderation → Export and download the file it emails you. Nothing is imported until you have reviewed a dry run, and running the same export again never duplicates comments.',
						'convocept'
					) }
				>
					<FileField
						label={ __( 'Export file', 'convocept' ) }
						accept=".xml,.gz"
						file={ file }
						onChange={ setFile }
						help={ sprintf(
							/* translators: 1: Maximum upload size, 2: WP-CLI command. */
							__(
								'.xml or .xml.gz, up to %1$s. Bigger exports: %2$s',
								'convocept'
							),
							`${ Math.floor( state.max_upload / 1048576 ) } MB`,
							'wp convocept import disqus export.xml'
						) }
					>
						<Button
							variant="primary"
							onClick={ upload }
							disabled={ ! file || busy }
							isBusy={ busy }
							accessibleWhenDisabled
						>
							{ __( 'Upload and analyse', 'convocept' ) }
						</Button>
					</FileField>
				</Card>
			</>
		);
	}

	if ( 'prepared' === job.status ) {
		return (
			<>
				{ errorNotice }
				<Card
					title={ __( 'Dry run', 'convocept' ) }
					description={ __(
						'Nothing has been imported yet. This is what the export holds.',
						'convocept'
					) }
				>
					<SummaryList
						rows={ [
							{
								label: __( 'Comments to import', 'convocept' ),
								value: count( job.total ),
							},
							{
								label: __(
									'On posts found on this site',
									'convocept'
								),
								value: count( counter( job, 'matched' ) ),
							},
							{
								label: sprintf(
									/* translators: %s: Number of Disqus threads. */
									__(
										'On %s pages not found here (skipped)',
										'convocept'
									),
									count( counter( job, 'threads_unmatched' ) )
								),
								value: nonZero( counter( job, 'unmatched' ) ),
							},
							{
								label: __( 'Spam (skipped)', 'convocept' ),
								value: nonZero( counter( job, 'spam' ) ),
							},
							{
								label: __( 'Deleted (skipped)', 'convocept' ),
								value: nonZero( counter( job, 'deleted' ) ),
							},
						] }
					/>
					<p className="convocept-help">
						{ __(
							'Imported comments don’t email your subscribers. Comments already on this site, from an earlier import or the Disqus plugin, are skipped.',
							'convocept'
						) }
					</p>
					<div className="convocept-card__actions">
						<Button
							variant="primary"
							onClick={ run }
							disabled={ busy }
						>
							{ __( 'Start import', 'convocept' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () =>
								cancel( __( 'Import cancelled.', 'convocept' ) )
							}
							disabled={ busy }
						>
							{ __( 'Cancel', 'convocept' ) }
						</Button>
					</div>
				</Card>
				{ job.sample.length > 0 && (
					<Card
						title={ __( 'Sample', 'convocept' ) }
						description={ __(
							'The first comments in the export, as they will be imported.',
							'convocept'
						) }
					>
						<table className="convocept-table">
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Author', 'convocept' ) }
									</th>
									<th scope="col">
										{ __( 'Date', 'convocept' ) }
									</th>
									<th scope="col">
										{ __( 'Comment', 'convocept' ) }
									</th>
									<th scope="col">
										{ __( 'Post', 'convocept' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ job.sample.map( ( row, index ) => (
									<tr key={ index }>
										<td>{ row.author }</td>
										<td>{ row.date }</td>
										<td>{ row.excerpt }</td>
										<td>{ row.post }</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</Card>
				) }
			</>
		);
	}

	if ( 'running' === job.status ) {
		return (
			<>
				{ errorNotice }
				<Card
					title={ __( 'Importing', 'convocept' ) }
					description={
						running
							? __(
									'Keep this page open. If it closes, come back and click Continue: the import picks up where it stopped.',
									'convocept'
								)
							: __(
									'The import is paused. Continue picks up where it stopped.',
									'convocept'
								)
					}
				>
					<ProgressBar
						label={ __( 'Import progress', 'convocept' ) }
						percent={ job.percent }
						text={ sprintf(
							/* translators: 1: Comments processed, 2: Total comments, 3: Comments imported. */
							__(
								'Processed %1$s of %2$s comments; %3$s imported.',
								'convocept'
							),
							count( job.processed ),
							count( job.total ),
							count( counter( job, 'imported' ) )
						) }
					/>
					<div className="convocept-card__actions">
						{ ! running && (
							<Button
								variant="primary"
								onClick={ run }
								disabled={ busy }
							>
								{ __( 'Continue', 'convocept' ) }
							</Button>
						) }
						<Button
							variant="tertiary"
							onClick={ () =>
								cancel(
									__(
										'Import cancelled. Comments already imported stay.',
										'convocept'
									)
								)
							}
							disabled={ busy }
						>
							{ __( 'Cancel import', 'convocept' ) }
						</Button>
					</div>
				</Card>
			</>
		);
	}

	return (
		<>
			{ errorNotice }
			<Card title={ __( 'Import complete', 'convocept' ) }>
				<SummaryList
					rows={ [
						{
							label: __( 'Imported', 'convocept' ),
							value: count( counter( job, 'imported' ) ),
						},
						{
							label: __(
								'Already on this site (skipped)',
								'convocept'
							),
							value: nonZero( counter( job, 'existing' ) ),
						},
						{
							label: __(
								'Post not found (skipped)',
								'convocept'
							),
							value: nonZero( counter( job, 'no_post' ) ),
						},
						{
							label: __( 'Spam (skipped)', 'convocept' ),
							value: nonZero( counter( job, 'spam' ) ),
						},
						{
							label: __( 'Deleted (skipped)', 'convocept' ),
							value: nonZero( counter( job, 'deleted' ) ),
						},
						{
							label: __( 'Empty (skipped)', 'convocept' ),
							value: nonZero(
								counter( job, 'empty' ) +
									counter( job, 'invalid' ) +
									counter( job, 'filtered' )
							),
						},
						{
							label: __( 'Failed', 'convocept' ),
							value: nonZero( counter( job, 'failed' ) ),
						},
					] }
				/>
				<div className="convocept-card__actions">
					<Button variant="secondary" href={ boot.urls.comments }>
						{ __( 'View comments', 'convocept' ) }
					</Button>
					<Button
						variant="tertiary"
						onClick={ () => cancel( '' ) }
						disabled={ busy }
					>
						{ __( 'Start another import', 'convocept' ) }
					</Button>
				</div>
			</Card>
		</>
	);
}
