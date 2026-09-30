/**
 * Feedback and data components of the Foxe Plugin Admin Kit: ProgressBar,
 * Steps, StatusList, StatTiles and Snackbars.
 */

/**
 * WordPress dependencies
 */
import { SnackbarList } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies
 */
import { Icon } from './icons';

/**
 * A determinate bar with the progress in words.
 *
 * @param {Object} props         Props.
 * @param {number} props.percent 0–100.
 * @param {string} props.text    Progress sentence.
 * @param {string} props.label   Accessible name.
 * @return {Element} Progress bar.
 */
export function ProgressBar( { percent, text, label } ) {
	const value = Math.max( 0, Math.min( 100, Math.round( percent ) ) );

	return (
		<div className="convocept-progress">
			<div
				className="convocept-progress__track"
				role="progressbar"
				aria-valuenow={ value }
				aria-valuemin="0"
				aria-valuemax="100"
				aria-label={ label }
			>
				<div
					className="convocept-progress__fill"
					style={ { width: `${ value }%` } }
				/>
			</div>
			<div className="convocept-progress__text">
				<span aria-live="polite">{ text }</span>
				<span>{ value }%</span>
			</div>
		</div>
	);
}

/**
 * Where the person is in a process of a few fixed steps.
 *
 * @param {Object}   props         Props.
 * @param {string}   props.label   Accessible name of the list.
 * @param {string[]} props.steps   Step names.
 * @param {number}   props.current Index of the current step; steps before it are done.
 * @return {Element} Ordered list.
 */
export function Steps( { label, steps, current } ) {
	const items = [];

	steps.forEach( ( step, index ) => {
		const done = index < current;
		const now = index === current;

		if ( index > 0 ) {
			items.push(
				<li
					key={ `line-${ index }` }
					className={ `convocept-step__line${
						index <= current ? ' convocept-step__line--done' : ''
					}` }
					aria-hidden="true"
				/>
			);
		}

		items.push(
			<li
				key={ step }
				className={ `convocept-step${
					done ? ' convocept-step--done' : ''
				}${ now ? ' convocept-step--current' : '' }` }
				aria-current={ now ? 'step' : undefined }
			>
				<span className="convocept-step__num" aria-hidden="true">
					{ done ? <Icon name="check" size={ 14 } /> : index + 1 }
				</span>
				{ step }
				{ done && (
					<span className="screen-reader-text">
						{ __( '(done)', 'convocept' ) }
					</span>
				) }
			</li>
		);
	} );

	return (
		<ol className="convocept-steps" aria-label={ label }>
			{ items }
		</ol>
	);
}

/**
 * Read-only facts, each with a check or a dash beside its label.
 *
 * @param {Object}  props       Props.
 * @param {Array}   props.rows  [{ label, on, value }]; rows with a null `on` and value are left out.
 * @param {boolean} [props.end] Whether values sit at the end of the row (counts).
 * @return {Element} List.
 */
export function StatusList( { rows, end = false } ) {
	return (
		<ul className="convocept-status-list">
			{ rows
				.filter( ( row ) => null !== row.value )
				.map( ( row ) => (
					<li className="convocept-status-item" key={ row.label }>
						<span
							className={ `convocept-status-item__icon convocept-status-item__icon--${
								row.on ? 'yes' : 'no'
							}` }
						>
							<Icon
								name={ row.on ? 'check' : 'dash' }
								size={ 20 }
							/>
						</span>
						<span>{ row.label }</span>
						{ undefined !== row.value && (
							<span
								className={ `convocept-status-item__value${
									end
										? ' convocept-status-item__value--end'
										: ''
								}` }
							>
								{ row.value }
							</span>
						) }
					</li>
				) ) }
		</ul>
	);
}

/**
 * Two to four headline numbers.
 *
 * @param {Object} props       Props.
 * @param {Array}  props.tiles [{ value, label }].
 * @return {Element} Description list.
 */
export function StatTiles( { tiles } ) {
	return (
		<dl className="convocept-stats">
			{ tiles.map( ( tile ) => (
				<div className="convocept-stat" key={ tile.label }>
					<dt className="convocept-stat__label">{ tile.label }</dt>
					<dd className="convocept-stat__value">{ tile.value }</dd>
				</div>
			) ) }
		</dl>
	);
}

/**
 * The snackbars of the screen, from the core notices store.
 *
 * Create one with `createSuccessNotice( text, { type: 'snackbar' } )`.
 *
 * @return {Element} Snackbar list.
 */
export function Snackbars() {
	const notices = useSelect(
		( select ) =>
			select( noticesStore )
				.getNotices()
				.filter( ( notice ) => 'snackbar' === notice.type ),
		[]
	);
	const { removeNotice } = useDispatch( noticesStore );

	return (
		<SnackbarList
			className="convocept-snackbars"
			notices={ notices }
			onRemove={ removeNotice }
		/>
	);
}
