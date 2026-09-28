/**
 * The settings app: sidebar, page header, the current page and the save bar.
 *
 * Settings are edited as a draft shared by every page, so changes on two
 * pages save together. The save bar shows only while the draft differs
 * from what is saved. Pages are chosen by the `tab` query argument (`&tab=appearance`), so
 * a reload or a shared link opens the same page.
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
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies
 */
import {
	FloatingBar,
	Notice,
	ExternalLink,
	PageFooter,
	PageHeader,
	Sidebar,
	Snackbars,
	StatusChip,
} from '../ui';
import { validateValue } from './validate';

/**
 * Whether two setting values are the same.
 *
 * @param {unknown} a First value.
 * @param {unknown} b Second value.
 * @return {boolean} Whether they are equal.
 */
function same( a, b ) {
	return JSON.stringify( a ) === JSON.stringify( b );
}

/**
 * Read the page ID from the `tab` query argument.
 *
 * @return {string} Page ID, or ''.
 */
function tabFromUrl() {
	return new URLSearchParams( window.location.search ).get( 'tab' ) || '';
}

/**
 * Address of a page: the current screen with `tab` set.
 *
 * @param {string} id Page ID.
 * @return {string} URL.
 */
function tabUrl( id ) {
	const url = new URL( window.location.href );

	url.searchParams.set( 'tab', id );
	url.hash = '';

	return url.toString();
}

/**
 * The settings app.
 *
 * @param {Object} props        Props.
 * @param {Object} props.boot   Data from PHP (settings, schema, defaults, …).
 * @param {Array}  props.pages  Pages: [{ id, group, title, icon, keys, render, wide }].
 * @param {Array}  props.groups Groups: [{ id, label }].
 * @return {Element} App.
 */
export default function App( { boot, pages, groups } ) {
	const [ saved, setSaved ] = useState( boot.settings );
	const [ draft, setDraft ] = useState( boot.settings );
	const [ serverErrors, setServerErrors ] = useState( {} );
	const [ saveError, setSaveError ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ route, setRoute ] = useState( tabFromUrl );
	const navigated = useRef( false );
	const { createSuccessNotice } = useDispatch( noticesStore );

	const page = pages.find( ( item ) => item.id === route ) || pages[ 0 ];

	const dirty = useMemo(
		() =>
			Object.keys( draft ).filter(
				( key ) => ! same( draft[ key ], saved[ key ] )
			),
		[ draft, saved ]
	);

	const errors = useMemo( () => {
		const found = {};

		dirty.forEach( ( key ) => {
			const message =
				serverErrors[ key ] ||
				validateValue(
					key,
					draft[ key ],
					boot.schema[ key ],
					boot.postTypes
				);

			if ( message ) {
				found[ key ] = message;
			}
		} );

		return found;
	}, [ dirty, draft, serverErrors, boot ] );

	const errorCount = Object.keys( errors ).length;

	// Back and forward move between pages without a reload.
	useEffect( () => {
		const onPop = () => {
			navigated.current = true;
			setRoute( tabFromUrl() );
		};

		window.addEventListener( 'popstate', onPop );

		return () => window.removeEventListener( 'popstate', onPop );
	}, [] );

	/**
	 * Show a page and record it in the address and browser history.
	 *
	 * @param {string} id Page ID.
	 * @return {void}
	 */
	const navigate = ( id ) => {
		if ( id === page.id ) {
			return;
		}

		window.history.pushState( null, '', tabUrl( id ) );
		navigated.current = true;
		setRoute( id );
	};

	// Move focus to the new page's title, so keyboard and screen reader users land on it.
	useEffect( () => {
		if ( navigated.current ) {
			document.querySelector( '.convocept-header__title' )?.focus();
		}
	}, [ page.id ] );

	useEffect( () => {
		if ( ! dirty.length ) {
			return undefined;
		}

		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};

		window.addEventListener( 'beforeunload', warn );

		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty.length ] );

	const update = useCallback( ( key, value ) => {
		setDraft( ( current ) => ( { ...current, [ key ]: value } ) );
		setServerErrors( ( current ) => {
			const next = { ...current };
			delete next[ key ];

			return next;
		} );
	}, [] );

	const discard = () => {
		setDraft( saved );
		setServerErrors( {} );
		setSaveError( '' );
	};

	const save = async () => {
		const changes = {};

		dirty.forEach( ( key ) => {
			changes[ key ] = draft[ key ];
		} );

		setSaving( true );
		setSaveError( '' );

		try {
			const response = await apiFetch( {
				path: '/convocept/v1/settings',
				method: 'POST',
				data: { settings: changes },
			} );

			setSaved( response.settings );
			setDraft( response.settings );
			setServerErrors( {} );
			createSuccessNotice( __( 'Settings saved.', 'convocept' ), {
				type: 'snackbar',
			} );
		} catch ( failure ) {
			if ( failure.data?.fields ) {
				setServerErrors( failure.data.fields );
			} else {
				setSaveError(
					failure.message ||
						__(
							'The settings could not be saved. Try again.',
							'convocept'
						)
				);
			}
		}

		setSaving( false );
	};

	const navGroups = groups
		.map( ( group ) => ( {
			...group,
			pages: pages
				.filter( ( item ) => item.group === group.id )
				.map( ( item ) => ( {
					...item,
					flag: ( item.keys || [] ).some( ( key ) => errors[ key ] )
						? __( 'Needs fixing', 'convocept' )
						: '',
				} ) ),
		} ) )
		.filter( ( group ) => group.pages.length );

	const Page = page.render;

	return (
		<>
			<Sidebar
				name="Convocept"
				meta={ sprintf(
					/* translators: %s: Plugin version. */
					__( 'Comments · %s', 'convocept' ),
					boot.version
				) }
				groups={ navGroups }
				current={ page.id }
				hrefFor={ tabUrl }
				onNavigate={ navigate }
			/>
			<div
				className={ `convocept-main${
					dirty.length > 0 ? ' convocept-main--has-bar' : ''
				}` }
			>
				<PageHeader
					title={ page.title }
					chips={
						saved.enabled ? (
							<StatusChip tone="success" dot>
								{ __( 'Comments replaced', 'convocept' ) }
							</StatusChip>
						) : (
							<StatusChip>
								{ __( 'Off', 'convocept' ) }
							</StatusChip>
						)
					}
				/>
				<main
					className={ `convocept-content${ page.wide ? ' convocept-content--wide' : '' }` }
				>
					{ saveError && <Notice tone="error" title={ saveError } /> }
					<Page
						draft={ draft }
						saved={ saved }
						update={ update }
						errors={ errors }
						boot={ boot }
					/>
				</main>
				<PageFooter
					meta={ createInterpolateElement(
						sprintf(
							/* translators: %s: Plugin version. <a> links to the maker's website. */
							__(
								'Convocept %s · by <a>Foxe Labs</a>',
								'convocept'
							),
							boot.version
						),
						{ a: <ExternalLink href={ boot.urls.foxelabs } /> }
					) }
					label={ __( 'About Convocept', 'convocept' ) }
					links={ [
						{
							label: 'docs.foxelabs.com',
							href: boot.urls.docs,
						},
						{
							label: 'convocept.com',
							href: boot.urls.website,
						},
						{
							label: 'wordpress.org',
							href: boot.urls.wporg,
						},
					] }
				/>
			</div>
			{ dirty.length > 0 && (
				<FloatingBar
					text={
						errorCount
							? sprintf(
									/* translators: %d: Number of settings with an error. */
									_n(
										'%d setting needs fixing',
										'%d settings need fixing',
										errorCount,
										'convocept'
									),
									errorCount
								)
							: sprintf(
									/* translators: %d: Number of changed settings. */
									_n(
										'%d unsaved change',
										'%d unsaved changes',
										dirty.length,
										'convocept'
									),
									dirty.length
								)
					}
					undoLabel={ __( 'Discard', 'convocept' ) }
					onUndo={ discard }
					actionLabel={ __( 'Save changes', 'convocept' ) }
					onAction={ save }
					busy={ saving }
					disabled={ errorCount > 0 }
				/>
			) }
			<Snackbars />
		</>
	);
}
