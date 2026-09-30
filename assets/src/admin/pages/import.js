/**
 * Manage → Import: upload an export, review the dry run, import with
 * progress, then read the report, with the four steps shown above.
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
import {
	createInterpolateElement,
	useCallback,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
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
	StatTiles,
	StatusList,
	Steps,
} from '../ui';
import './import.css';

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
			<Notice
				tone="error"
				title={ __( 'The import page could not load', 'convocept' ) }
			>
				{ error }
			</Notice>
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

	const errorNotice = error && (
		<Notice
			tone="error"
			title={
				job
					? __( 'The import stopped', 'convocept' )
					: __( 'This file can’t be imported', 'convocept' )
			}
		>
			{ error }
		</Notice>
	);

	/**
	 * The step row above the cards.
	 *
	 * @param {number} current Index of the current step.
	 * @return {Element} Steps.
	 */
	const steps = ( current ) => (
		<Steps
			label={ __( 'Import steps', 'convocept' ) }
			steps={ [
				__( 'Upload', 'convocept' ),
				__( 'Review', 'convocept' ),
				__( 'Import', 'convocept' ),
				__( 'Done', 'convocept' ),
			] }
			current={ current }
		/>
	);

	/**
	 * A count as a skipped-row value, or null to leave the row out.
	 *
	 * @param {string} label Row label.
	 * @param {number} value Count.
	 * @return {Object} StatusList row.
	 */
	const skipped = ( label, value ) => ( {
		label,
		on: false,
		value: nonZero( value ),
	} );

	if ( ! job ) {
		return (
			<>
				{ steps( 0 ) }
				{ errorNotice }
				<Card
					title={ __( 'Import from Disqus', 'convocept' ) }
					description={ __(
						'Nothing is imported until you have reviewed a dry run. Running the same export again never duplicates comments.',
						'convocept'
					) }
					footer={
						<Button
							variant="primary"
							__next40pxDefaultSize
							// Stays focusable while there is no file yet, so it can be found.
							onClick={ file && ! busy ? upload : undefined }
							aria-disabled={ ! file || busy }
							isBusy={ busy }
						>
							{ __( 'Upload and review', 'convocept' ) }
						</Button>
					}
				>
					<ol className="convocept-how">
						<li>
							{ __(
								'In Disqus, open Admin → Moderation → Export.',
								'convocept'
							) }
						</li>
						<li>
							{ __(
								'Download the file Disqus emails you.',
								'convocept'
							) }
						</li>
						<li>{ __( 'Drop it below.', 'convocept' ) }</li>
					</ol>
					<FileField
						label={ __( 'Export file', 'convocept' ) }
						prompt={ __(
							'Drop your export file here',
							'convocept'
						) }
						accept=".xml,.gz"
						file={ file }
						onChange={ setFile }
						help={ sprintf(
							/* translators: %s: Maximum upload size. */
							__( '.xml or .xml.gz, up to %s.', 'convocept' ),
							`${ Math.floor( state.max_upload / 1048576 ) } MB`
						) }
					/>
					<Notice
						tone="info"
						title={ __( 'Bigger export?', 'convocept' ) }
					>
						{ createInterpolateElement(
							sprintf(
								/* translators: %s: WP-CLI command. */
								__(
									'Run <code>%s</code> on the server instead.',
									'convocept'
								),
								'wp convocept import disqus export.xml'
							),
							{ code: <code className="convocept-code" /> }
						) }
					</Notice>
				</Card>
			</>
		);
	}

	if ( 'prepared' === job.status ) {
		return (
			<>
				{ steps( 1 ) }
				{ errorNotice }
				<Card
					title={ __( 'Dry run', 'convocept' ) }
					description={ __(
						'Nothing has been imported yet. This is what the export holds.',
						'convocept'
					) }
					footer={
						<>
							<Button
								variant="tertiary"
								__next40pxDefaultSize
								onClick={ () =>
									cancel(
										__( 'Import cancelled.', 'convocept' )
									)
								}
								disabled={ busy }
							>
								{ __( 'Cancel', 'convocept' ) }
							</Button>
							<Button
								variant="primary"
								__next40pxDefaultSize
								onClick={ run }
								disabled={ busy }
							>
								{ __( 'Start import', 'convocept' ) }
							</Button>
						</>
					}
				>
					<StatTiles
						tiles={ [
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
								value: count( counter( job, 'unmatched' ) ),
							},
						] }
					/>
					<StatusList
						end
						rows={ [
							skipped(
								__( 'Spam (skipped)', 'convocept' ),
								counter( job, 'spam' )
							),
							skipped(
								__( 'Deleted (skipped)', 'convocept' ),
								counter( job, 'deleted' )
							),
						] }
					/>
					<p className="convocept-help">
						{ __(
							'Imported comments don’t email your subscribers. Comments already on this site, from an earlier import or the Disqus plugin, are skipped.',
							'convocept'
						) }
					</p>
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
										<td className="convocept-table__date">
											{ row.date }
										</td>
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
				{ steps( 2 ) }
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
					footer={
						<>
							<Button
								variant="tertiary"
								__next40pxDefaultSize
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
							{ ! running && (
								<Button
									variant="primary"
									__next40pxDefaultSize
									onClick={ run }
									disabled={ busy }
								>
									{ __( 'Continue', 'convocept' ) }
								</Button>
							) }
						</>
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
				</Card>
			</>
		);
	}

	return (
		<>
			{ steps( 3 ) }
			{ errorNotice }
			<Card
				title={ __( 'Import complete', 'convocept' ) }
				footer={
					<>
						<Button
							variant="tertiary"
							__next40pxDefaultSize
							onClick={ () => cancel( '' ) }
							disabled={ busy }
						>
							{ __( 'Start another import', 'convocept' ) }
						</Button>
						<Button
							variant="primary"
							__next40pxDefaultSize
							href={ boot.urls.comments }
						>
							{ __( 'View comments', 'convocept' ) }
						</Button>
					</>
				}
			>
				<StatTiles
					tiles={ [
						{
							label: __( 'Imported', 'convocept' ),
							value: count( counter( job, 'imported' ) ),
						},
					] }
				/>
				<StatusList
					end
					rows={ [
						skipped(
							__( 'Already on this site (skipped)', 'convocept' ),
							counter( job, 'existing' )
						),
						skipped(
							__( 'Post not found (skipped)', 'convocept' ),
							counter( job, 'no_post' )
						),
						skipped(
							__( 'Spam (skipped)', 'convocept' ),
							counter( job, 'spam' )
						),
						skipped(
							__( 'Deleted (skipped)', 'convocept' ),
							counter( job, 'deleted' )
						),
						skipped(
							__( 'Empty (skipped)', 'convocept' ),
							counter( job, 'empty' ) +
								counter( job, 'invalid' ) +
								counter( job, 'filtered' )
						),
						skipped(
							__( 'Failed', 'convocept' ),
							counter( job, 'failed' )
						),
					] }
				/>
			</Card>
		</>
	);
}
