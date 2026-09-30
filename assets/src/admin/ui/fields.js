/**
 * Form fields of the Foxe Plugin Admin Kit.
 *
 * Each wraps the WordPress component the design system names, so the look
 * can change in one place. Every field takes `label`, `help`, `value`,
 * `onChange` and an optional `error`, which replaces the help line.
 */

/**
 * WordPress dependencies
 */
import {
	BaseControl,
	Button,
	CheckboxControl,
	ColorPalette,
	ColorPicker,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { Icon } from './icons';

/**
 * Render the help line, or the error in its place.
 *
 * @param {Object}         props       Props.
 * @param {string}         props.id    Element ID, for aria-describedby.
 * @param {string|Element} props.help  Help text.
 * @param {string}         props.error Error text.
 * @return {Element|null} Paragraph.
 */
function Describe( { id, help, error } ) {
	if ( error ) {
		return (
			<p className="convocept-error" id={ id }>
				<Icon name="error" size={ 16 } />
				<span>{ error }</span>
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
 * A whole number with its unit inside the field ("25 comments").
 *
 * A native number input in the kit's affix frame, labelled by WordPress's
 * BaseControl: the stable components have no suffix slot. The value passed
 * to onChange is a number, or '' while the field is empty, so validation
 * can say what to enter.
 *
 * @param {Object}                         props          Props.
 * @param {string}                         props.label    Label.
 * @param {string}                         [props.unit]   Plural noun shown after the number.
 * @param {string}                         [props.help]   What the number does.
 * @param {number}                         props.value    Value.
 * @param {number}                         props.min      Minimum.
 * @param {number}                         props.max      Maximum.
 * @param {string}                         [props.error]  Error text.
 * @param {(value: number|string) => void} props.onChange Receives the new value.
 * @return {Element} Field.
 */
export function NumberField( {
	label,
	unit,
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
			<BaseControl __nextHasNoMarginBottom id={ id } label={ label }>
				<div
					className={ `convocept-affix${
						error ? ' convocept-affix--invalid' : ''
					}` }
				>
					<input
						id={ id }
						type="number"
						inputMode="numeric"
						value={ value }
						min={ min }
						max={ max }
						step={ 1 }
						aria-invalid={ !! error }
						aria-describedby={
							unit
								? `${ id }-unit ${ id }-describe`
								: `${ id }-describe`
						}
						onChange={ ( event ) =>
							onChange(
								'' === event.target.value
									? ''
									: Number( event.target.value )
							)
						}
					/>
					{ unit && (
						<span
							className="convocept-affix__unit"
							id={ `${ id }-unit` }
						>
							{ unit }
						</span>
					) }
				</div>
			</BaseControl>
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
				aria-invalid={ !! error }
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
 * A colour as a row (swatch, name, hex) that opens WordPress's colour
 * picker with the theme's palette and a few suggested colours.
 *
 * The picker opens in a panel under the row, in the page flow, not in a
 * floating popover: it is tall, and a popover near the admin bar either
 * flips over it or scrolls inside itself.
 *
 * @param {Object}                  props              Props.
 * @param {string}                  props.label        Label.
 * @param {string}                  props.name         Short name shown in the row, e.g. "Accent".
 * @param {string}                  [props.help]       Where the colour is used.
 * @param {string}                  [props.warning]    Shown instead of the help, e.g. low contrast.
 * @param {string}                  props.value        Hex colour.
 * @param {string}                  props.defaultValue Default, for Reset.
 * @param {Array}                   [props.palettes]   Swatch groups: [{ name, colors: [{ name, color }] }].
 * @param {string}                  [props.error]      Error text.
 * @param {(value: string) => void} props.onChange     Receives the new value.
 * @return {Element} Field.
 */
export function ColorField( {
	label,
	name,
	help,
	warning,
	value,
	defaultValue,
	palettes = [],
	error,
	onChange,
} ) {
	const id = useInstanceId( ColorField, 'convocept-color' );
	const [ open, setOpen ] = useState( false );
	const toggle = useRef();
	const valid = /^#[0-9a-f]{6}$/i.test( value || '' );
	const shown = valid ? value : defaultValue;
	const groups = palettes.filter( ( group ) => group.colors.length );

	return (
		<div
			className={ fieldClass( 'color', error ) }
			role="group"
			aria-labelledby={ `${ id }-label` }
		>
			<span className="convocept-label" id={ `${ id }-label` }>
				{ label }
			</span>
			<div className="convocept-color-row">
				<button
					ref={ toggle }
					type="button"
					className="convocept-color"
					aria-expanded={ open }
					aria-controls={ `${ id }-panel` }
					aria-describedby={ `${ id }-describe` }
					onClick={ () => setOpen( ! open ) }
				>
					<span
						className="convocept-color__chip"
						style={ { background: shown } }
					/>
					<span className="convocept-color__name">{ name }</span>
					<span className="convocept-color__hex">{ value }</span>
					<Icon name="chevron" />
				</button>
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
			{ open && (
				// Escape closes the panel and returns to the row, as a popover would.
				// eslint-disable-next-line jsx-a11y/no-static-element-interactions -- Only listens for Escape bubbling up from the controls inside.
				<div
					id={ `${ id }-panel` }
					className="convocept-color-panel"
					onKeyDown={ ( event ) => {
						if ( 'Escape' === event.key ) {
							event.stopPropagation();
							setOpen( false );
							toggle.current?.focus();
						}
					} }
				>
					<ColorPicker
						color={ shown }
						enableAlpha={ false }
						onChange={ ( next ) => onChange( next.toLowerCase() ) }
					/>
					{ groups.length > 0 && (
						<ColorPalette
							colors={ groups }
							value={ shown }
							disableCustomColors
							clearable={ false }
							headingLevel={ 3 }
							onChange={ ( next ) =>
								next && onChange( next.toLowerCase() )
							}
						/>
					) }
				</div>
			) }
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
 * @param {Object}               props          Props.
 * @param {string}               props.label    Label.
 * @param {string}               props.prompt   Title of the drop area, e.g. "Drop your export file here".
 * @param {string}               [props.help]   Accepted types and size limit.
 * @param {string}               props.accept   The input's accept attribute.
 * @param {File}                 [props.file]   The chosen file.
 * @param {(file: File) => void} props.onChange Receives the chosen File.
 * @return {Element} Field.
 */
export function FileField( { label, prompt, help, accept, file, onChange } ) {
	const id = useInstanceId( FileField, 'convocept-file' );
	const input = useRef();
	const [ over, setOver ] = useState( false );

	return (
		<div className="convocept-field convocept-field--file">
			<label
				className="convocept-label"
				htmlFor={ id }
				id={ `${ id }-label` }
			>
				{ label }
			</label>
			<div
				role="group"
				aria-labelledby={ `${ id }-label` }
				className={ `convocept-drop${ over ? ' convocept-drop--over' : '' }` }
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
					// The button is the keyboard path; a second, invisible tab stop would hide focus.
					tabIndex={ -1 }
					onChange={ ( event ) =>
						event.target.files[ 0 ] &&
						onChange( event.target.files[ 0 ] )
					}
				/>
				<Icon name="upload" size={ 28 } />
				<div className="convocept-drop__title" aria-live="polite">
					{ file ? file.name : prompt }
				</div>
				{ ! file && (
					<div className="convocept-drop__or">
						{ __( 'or', 'convocept' ) }
					</div>
				) }
				<Button
					variant="secondary"
					size="compact"
					aria-describedby={ `${ id }-describe` }
					onClick={ () => input.current.click() }
				>
					{ file
						? __( 'Choose another file', 'convocept' )
						: __( 'Choose file', 'convocept' ) }
				</Button>
			</div>
			<Describe id={ `${ id }-describe` } help={ help } />
		</div>
	);
}
