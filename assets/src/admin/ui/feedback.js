/**
 * Feedback components of the Foxe Plugin Admin UI: ProgressBar,
 * SummaryList and Snackbars.
 */

/**
 * WordPress dependencies
 */
import { SnackbarList } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

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
 * Label and value rows for a read-only report.
 *
 * @param {Object} props      Props.
 * @param {Array}  props.rows [{ label, value }]; rows with a null value are left out.
 * @return {Element} Description list.
 */
export function SummaryList( { rows } ) {
	return (
		<dl className="convocept-summary">
			{ rows
				.filter(
					( row ) => null !== row.value && undefined !== row.value
				)
				.map( ( row ) => (
					<div className="convocept-summary__row" key={ row.label }>
						<dt>{ row.label }</dt>
						<dd>{ row.value }</dd>
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
