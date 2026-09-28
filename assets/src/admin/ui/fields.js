/**
 * Form fields of the Foxe Plugin Admin UI.
 *
 * Each wraps the WordPress component the design system names, so the look
 * can change in one place. Every field takes `label`, `help`, `value`,
 * `onChange` and an optional `error`, which replaces the help line.
 */

/**
 * WordPress dependencies
 */
import {
	Button,
	CheckboxControl,
	ColorPicker,
	Dropdown,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Render the help line, or the error in its place.
 *
 * @param {Object} props       Props.
 * @param {string} props.id    Element ID, for aria-describedby.
 * @param {string} props.help  Help text.
 * @param {string} props.error Error text.
 * @return {Element|null} Paragraph.
 */
function Describe( { id, help, error } ) {
	if ( error ) {
		return (
			<p className="convocept-error" id={ id }>
				{ error }
			</p>
		);
	}

	return help ? (
		<p className="convocept-help" id={ id }>
			{ help }
		</p>
	) : null;
}

/**
 * Build the class names of a field wrapper.
 *
 * @param {string} kind  Field kind.
 * @param {string} error Error text.
 * @param {string} extra Extra classes.
 * @return {string} Class names.
 */
function fieldClass( kind, error, extra = '' ) {
	return [
		'convocept-field',
		`convocept-field--${ kind }`,
		error && 'convocept-field--invalid',
		extra,
	]
		.filter( Boolean )
		.join( ' ' );
}

/**
 * Turn one behaviour on or off.
 *
 * @param {Object}                     props          Props.
 * @param {string}                     props.label    What the ON state means.
 * @param {string}                     [props.help]   What changes for readers.
 * @param {boolean}                    props.checked  State.
 * @param {(checked: boolean) => void} props.onChange Receives the new state.
 * @return {Element} Toggle.
 */
export function Toggle( { label, help, checked, onChange } ) {
	return (
		<div className="convocept-toggle">
			<ToggleControl
				__nextHasNoMarginBottom
				label={ label }
				help={ help }
				checked={ !! checked }
				onChange={ onChange }
			/>
		</div>
	);
}

/**
 * A whole number.
 *
 * WordPress's TextControl as a number input, so it looks and sizes like
 * every other field; the stepper control in @wordpress/components is
 * still experimental. The value passed to onChange is a number, or ''
 * while the field is empty, so validation can say what to enter.
 *
 * @param {Object}                         props          Props.
 * @param {string}                         props.label    Label.
 * @param {string}                         [props.help]   Help with the unit.
 * @param {number}                         props.value    Value.
 * @param {number}                         props.min      Minimum.
 * @param {number}                         props.max      Maximum.
 * @param {string}                         [props.error]  Error text.
 * @param {(value: number|string) => void} props.onChange Receives the new value.
 * @return {Element} Field.
 */
export function NumberField( {
	label,
	help,
	value,
	min,
	max,
	error,
	onChange,
} ) {
	const id = useInstanceId( NumberField, 'convocept-number' );

	return (
		<div className={ fieldClass( 'number', error ) }>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				id={ id }
				type="number"
				inputMode="numeric"
				label={ label }
				value={ value }
				min={ min }
				max={ max }
				step={ 1 }
				aria-invalid={ !! error }
				aria-describedby={ `${ id }-describe` }
				onChange={ ( next ) =>
					onChange( '' === next ? '' : Number( next ) )
				}
			/>
			<Describe id={ `${ id }-describe` } help={ help } error={ error } />
		</div>
	);
}

/**
 * A single-line text input.
 *
 * @param {Object}                  props          Props.
 * @param {string}                  props.label    Label.
 * @param {string}                  [props.help]   Help.
 * @param {string}                  props.value    Value.
 * @param {string}                  [props.error]  Error text.
 * @param {(value: string) => void} props.onChange Receives the new value.
 * @return {Element} Field.
 */
export function TextField( { label, help, value, error, onChange } ) {
	const id = useInstanceId( TextField, 'convocept-text' );

	return (
		<div className={ fieldClass( 'text', error ) }>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				id={ id }
				label={ label }
				value={ value }
				aria-invalid={ !! error }
				aria-describedby={ `${ id }-describe` }
				onChange={ onChange }
			/>
			<Describe id={ `${ id }-describe` } help={ help } error={ error } />
		</div>
	);
}

/**
 * Pick one value from a plain list.
 *
 * @param {Object}                  props          Props.
 * @param {string}                  props.label    Label.
 * @param {string}                  [props.help]   Help.
 * @param {string}                  props.value    Value.
 * @param {Array}                   props.options  [{ value, label }].
 * @param {string}                  [props.error]  Error text.
 * @param {(value: string) => void} props.onChange Receives the new value.
 * @return {Element} Field.
 */
export function SelectField( {
	label,
	help,
	value,
	options,
	error,
	onChange,
} ) {
	const id = useInstanceId( SelectField, 'convocept-select' );

	return (
		<div className={ fieldClass( 'select', error ) }>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				id={ id }
				label={ label }
				value={ value }
				options={ options }
				aria-describedby={ `${ id }-describe` }
				onChange={ onChange }
			/>
			<Describe id={ `${ id }-describe` } help={ help } error={ error } />
		</div>
	);
}

/**
 * A multi-line input holding a list, one item per line.
 *
 * Keeps the text as typed (blank lines, a trailing newline) while the
 * field is being edited and hands onChange the cleaned list.
 *
 * @param {Object}                    props          Props.
 * @param {string}                    props.label    Label.
 * @param {string}                    [props.help]   Help that says the format.
 * @param {string[]}                  props.value    Items.
 * @param {string}                    [props.error]  Error text.
 * @param {boolean}                   [props.code]   Whether to use the monospace font.
 * @param {(items: string[]) => void} props.onChange Receives the new list.
 * @return {Element} Field.
 */
export function TextareaListField( {
	label,
	help,
	value,
	error,
	code = false,
	onChange,
} ) {
	const id = useInstanceId( TextareaListField, 'convocept-textarea' );
	const joined = ( value || [] ).join( '\n' );
	const [ text, setText ] = useState( joined );

	// Follow outside changes (Discard, Save) without fighting the person typing.
	useEffect( () => {
		setText( ( current ) =>
			toList( current ).join( '\n' ) === joined ? current : joined
		);
	}, [ joined ] );

	return (
		<div
			className={ fieldClass(
				'textarea',
				error,
				code ? 'convocept-field--code' : ''
			) }
		>
			{ /* No id: TextareaControl labels its own generated id, and an id passed in would break that. */ }
			<TextareaControl
				__nextHasNoMarginBottom
				label={ label }
				value={ text }
				rows={ 4 }
				aria-invalid={ !! error }
				aria-describedby={ `${ id }-describe` }
				onChange={ ( next ) => {
					setText( next );
					onChange( toList( next ) );
				} }
			/>
			<Describe id={ `${ id }-describe` } help={ help } error={ error } />
		</div>
	);
}

/**
 * Split text into trimmed, non-empty lines.
 *
 * @param {string} text Text.
 * @return {string[]} Lines.
 */
function toList( text ) {
	return text
		.split( /\r?\n/ )
		.map( ( line ) => line.trim() )
		.filter( Boolean );
}

/**
 * Pick several values from a list.
 *
 * @param {Object}                     props          Props.
 * @param {string}                     props.label    Group label.
 * @param {string}                     [props.help]   Help.
 * @param {string[]}                   props.value    Checked values.
 * @param {Array}                      props.options  [{ value, label }].
 * @param {string}                     [props.error]  Error text.
 * @param {(values: string[]) => void} props.onChange Receives the new list, in option order.
 * @return {Element} Field.
 */
export function CheckboxList( {
	label,
	help,
	value,
	options,
	error,
	onChange,
} ) {
	const id = useInstanceId( CheckboxList, 'convocept-checks' );
	const checked = new Set( value || [] );

	return (
		<fieldset
			className={ fieldClass( 'checks', error, 'convocept-checks' ) }
			aria-describedby={ `${ id }-describe` }
		>
			<legend className="convocept-label">{ label }</legend>
			<div className="convocept-checks__items">
				{ options.map( ( option ) => (
					<CheckboxControl
						__nextHasNoMarginBottom
						key={ option.value }
						label={ option.label }
						checked={ checked.has( option.value ) }
						onChange={ ( on ) => {
							const next = new Set( checked );

							if ( on ) {
								next.add( option.value );
							} else {
								next.delete( option.value );
							}

							onChange(
								options
									.map( ( item ) => item.value )
									.filter( ( item ) => next.has( item ) )
							);
						} }
					/>
				) ) }
			</div>
			<Describe id={ `${ id }-describe` } help={ help } error={ error } />
		</fieldset>
	);
}

/**
 * Pick one mode where each option needs a sentence: a radio group of tiles.
 *
 * Native radios, so arrow keys move between tiles.
 *
 * @param {Object}                  props          Props.
 * @param {string}                  props.label    Group label.
 * @param {string}                  props.value    Selected value.
 * @param {Array}                   props.options  [{ value, label, description }].
 * @param {(value: string) => void} props.onChange Receives the new value.
 * @return {Element} Field.
 */
export function ChoiceTiles( { label, value, options, onChange } ) {
	const name = useInstanceId( ChoiceTiles, 'convocept-tiles' );

	return (
		<fieldset
			className={ `convocept-tiles${
				4 === options.length ? ' convocept-tiles--pairs' : ''
			}` }
		>
			<legend className="convocept-label">{ label }</legend>
			{ options.map( ( option ) => {
				const on = option.value === value;

				return (
					<label
						key={ option.value }
						htmlFor={ `${ name }-${ option.value }` }
						className={ `convocept-tile${ on ? ' convocept-tile--on' : '' }` }
					>
						<span className="convocept-tile__head">
							<input
								id={ `${ name }-${ option.value }` }
								type="radio"
								name={ name }
								value={ option.value }
								checked={ on }
								onChange={ () => onChange( option.value ) }
							/>
							{ option.label }
						</span>
						<span className="convocept-tile__desc">
							{ option.description }
						</span>
					</label>
				);
			} ) }
		</fieldset>
	);
}

/**
 * A colour swatch with a picker and the hex value as text.
 *
 * @param {Object}                  props              Props.
 * @param {string}                  props.label        Label.
 * @param {string}                  [props.help]       Where the colour is used.
 * @param {string}                  [props.warning]    Shown instead of the help, e.g. low contrast.
 * @param {string}                  props.value        Hex colour.
 * @param {string}                  props.defaultValue Default, for Reset.
 * @param {string}                  [props.error]      Error text.
 * @param {(value: string) => void} props.onChange     Receives the new value.
 * @return {Element} Field.
 */
export function ColorField( {
	label,
	help,
	warning,
	value,
	defaultValue,
	error,
	onChange,
} ) {
	const id = useInstanceId( ColorField, 'convocept-color' );
	const valid = /^#[0-9a-f]{6}$/i.test( value || '' );

	return (
		<div
			className={ fieldClass( 'color', error ) }
			role="group"
			aria-labelledby={ `${ id }-label` }
		>
			<span className="convocept-label" id={ `${ id }-label` }>
				{ label }
			</span>
			<div className="convocept-color">
				<Dropdown
					popoverProps={ { placement: 'bottom-start' } }
					renderToggle={ ( { isOpen, onToggle } ) => (
						<button
							type="button"
							className="convocept-color__swatch"
							style={ {
								background: valid ? value : defaultValue,
							} }
							aria-expanded={ isOpen }
							aria-label={ __( 'Pick a colour', 'convocept' ) }
							onClick={ onToggle }
						/>
					) }
					renderContent={ () => (
						<ColorPicker
							color={ valid ? value : defaultValue }
							enableAlpha={ false }
							onChange={ onChange }
						/>
					) }
				/>
				<div className="convocept-color__hex">
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						hideLabelFromVision
						label={ __( 'Hex value', 'convocept' ) }
						value={ value }
						aria-invalid={ !! error }
						aria-describedby={ `${ id }-describe` }
						onChange={ ( next ) =>
							onChange( next.trim().toLowerCase() )
						}
					/>
				</div>
				{ value !== defaultValue && (
					<Button
						variant="tertiary"
						size="compact"
						onClick={ () => onChange( defaultValue ) }
					>
						{ __( 'Reset', 'convocept' ) }
					</Button>
				) }
			</div>
			{ ! error && warning ? (
				<p
					className="convocept-help convocept-help--warning"
					id={ `${ id }-describe` }
				>
					{ warning }
				</p>
			) : (
				<Describe
					id={ `${ id }-describe` }
					help={ help }
					error={ error }
				/>
			) }
		</div>
	);
}

/**
 * Pick one file to upload, by button or by dropping it on the area.
 *
 * @param {Object}               props            Props.
 * @param {string}               props.label      Label.
 * @param {string}               [props.help]     Accepted types and size limit.
 * @param {string}               props.accept     The input's accept attribute.
 * @param {File}                 [props.file]     The chosen file.
 * @param {(file: File) => void} props.onChange   Receives the chosen File.
 * @param {Element}              [props.children] The next step's button.
 * @return {Element} Field.
 */
export function FileField( { label, help, accept, file, onChange, children } ) {
	const id = useInstanceId( FileField, 'convocept-file' );
	const input = useRef();
	const [ over, setOver ] = useState( false );

	return (
		<div
			className="convocept-field convocept-field--file"
			style={ { width: '100%' } }
		>
			<label className="convocept-label" htmlFor={ id }>
				{ label }
			</label>
			<div
				className={ `convocept-file${ over ? ' convocept-file--over' : '' }` }
				onDragOver={ ( event ) => {
					event.preventDefault();
					setOver( true );
				} }
				onDragLeave={ () => setOver( false ) }
				onDrop={ ( event ) => {
					event.preventDefault();
					setOver( false );

					if ( event.dataTransfer.files[ 0 ] ) {
						onChange( event.dataTransfer.files[ 0 ] );
					}
				} }
			>
				<input
					ref={ input }
					id={ id }
					type="file"
					accept={ accept }
					className="screen-reader-text"
					aria-describedby={ `${ id }-describe` }
					onChange={ ( event ) =>
						event.target.files[ 0 ] &&
						onChange( event.target.files[ 0 ] )
					}
				/>
				<Button
					variant="secondary"
					onClick={ () => input.current.click() }
				>
					{ __( 'Choose file', 'convocept' ) }
				</Button>
				<span className="convocept-file__name">
					{ file ? file.name : __( 'or drop it here', 'convocept' ) }
				</span>
				{ children }
			</div>
			<Describe id={ `${ id }-describe` } help={ help } />
		</div>
	);
}
