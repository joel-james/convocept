/**
 * The settings app: sidebar, page header with Save, the current page and the footer.
 *
 * Settings are edited as a draft shared by every page, so changes on two
 * pages save together. Settings pages show Save in the page header,
 * disabled until the draft differs from what is saved. Pages are chosen by
 * the `tab` query argument (`&tab=appearance`), so a reload or a shared
 * link opens the same page.
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
	Button,
	ExternalLink,
	Notice,
	PageFooter,
	PageHeader,
	SectionSelect,
	Sidebar,
	Snackbars,
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
	const [ justSaved, setJustSaved ] = useState( false );
	const [ route, setRoute ] = useState( tabFromUrl );
	const navigated = useRef( false );
	const wpNotices = useRef();
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

	/*
	 * WordPress prints admin notices (updates, other plugins) before the
	 * app, where they would sit outside the layout. Move them into the
	 * content column, as core's common.js does for `.wp-header-end` on
	 * classic screens. React renders no children in that box, so it
	 * never touches them.
	 */
	useEffect( () => {
		const box = wpNotices.current;
		const body = document.getElementById( 'wpbody-content' );

		if ( ! box || ! body ) {
			return;
		}

		body.querySelectorAll(
			':scope > .notice, :scope > .updated, :scope > .error, :scope > .update-nag'
		).forEach( ( notice ) => box.appendChild( notice ) );
	}, [] );

	// The browser tab names the page, as a full page load would.
	const baseTitle = useRef( document.title );

	useEffect( () => {
		document.title = sprintf(
			/* translators: 1: Page title, e.g. "Appearance", 2: The screen's own title from WordPress. */
			__( '%1$s ‹ %2$s', 'convocept' ),
			page.title,
			baseTitle.current
		);
	}, [ page.title ] );

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
		setJustSaved( false );
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

	const canSave = ! saving && dirty.length > 0 && 0 === errorCount;

	const save = async () => {
		// Save stays focusable while it can't act (aria-disabled), so guard the click here.
		if ( ! canSave ) {
			return;
		}

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
			setJustSaved( true );
			createSuccessNotice( __( 'Settings saved.', 'convocept' ), {
				type: 'snackbar',
			} );
		} catch ( failure ) {
			if ( failure.data?.fields ) {
				setServerErrors( failure.data.fields );
			} else {
				setSaveError(
					failure.message ||
						__( 'The server didn’t answer.', 'convocept' )
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
	const hasSettings = ( page.keys || [] ).length > 0;

	let status;

	if ( errorCount ) {
		status = {
			tone: 'error',
			text: sprintf(
				/* translators: %d: Number of settings with an error. */
				_n(
					'%d setting needs fixing',
					'%d settings need fixing',
					errorCount,
					'convocept'
				),
				errorCount
			),
		};
	} else if ( dirty.length ) {
		status = {
			tone: 'warning',
			text: sprintf(
				/* translators: %d: Number of changed settings. */
				_n(
					'%d unsaved change',
					'%d unsaved changes',
					dirty.length,
					'convocept'
				),
				dirty.length
			),
		};
	}

	// Save shows on settings pages, and on any page while there is something to save.
	const actions = ( hasSettings || dirty.length > 0 ) && (
		<>
			{ dirty.length > 0 && (
				<Button
					variant="tertiary"
					size="compact"
					onClick={ discard }
					disabled={ saving }
				>
					{ __( 'Discard', 'convocept' ) }
				</Button>
			) }
			<Button
				variant="primary"
				size="compact"
				onClick={ save }
				isBusy={ saving }
				aria-disabled={ ! canSave }
			>
				{ justSaved && ! dirty.length
					? __( 'Saved', 'convocept' )
					: __( 'Save', 'convocept' ) }
			</Button>
		</>
	);

	return (
		<>
			<Sidebar
				name="Convocept"
				label={ __( 'Convocept sections', 'convocept' ) }
				status={
					saved.enabled
						? {
								tone: 'on',
								text: sprintf(
									/* translators: %s: Plugin version. */
									__( 'On · %s', 'convocept' ),
									boot.version
								),
							}
						: {
								tone: 'off',
								text: sprintf(
									/* translators: %s: Plugin version. */
									__( 'Off · %s', 'convocept' ),
									boot.version
								),
							}
				}
				groups={ navGroups }
				current={ page.id }
				hrefFor={ tabUrl }
				onNavigate={ navigate }
			/>
			<div className="convocept-main">
				<PageHeader
					title={ page.title }
					nav={
						<SectionSelect
							groups={ navGroups }
							current={ page.id }
							onNavigate={ navigate }
						/>
					}
					status={ status }
					actions={ actions }
				/>
				<main
					className={ `convocept-content${ page.wide ? ' convocept-content--wide' : '' }` }
				>
					<div className="convocept-wp-notices" ref={ wpNotices } />
					{ saveError && (
						<Notice
							tone="error"
							title={ __(
								'The settings could not be saved',
								'convocept'
							) }
							actions={
								<Button
									variant="primary"
									size="compact"
									onClick={ save }
									aria-disabled={ ! canSave }
								>
									{ __( 'Try again', 'convocept' ) }
								</Button>
							}
						>
							{ sprintf(
								/* translators: %s: Error message from the server. */
								__(
									'%s Your changes are still here, so nothing is lost.',
									'convocept'
								),
								saveError
							) }
						</Notice>
					) }
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
							label: __( 'Documentation', 'convocept' ),
							href: boot.urls.docs,
						},
						{
							label: __( 'Homepage', 'convocept' ),
							href: boot.urls.website,
						},
						{
							// The stars are decorative; screen readers hear "five-star" instead.
							label: createInterpolateElement(
								__(
									/* translators: <stars> holds five star symbols, hidden from screen readers; <sr> is read to screen readers only. */
									'Leave a <stars>★★★★★</stars><sr>five-star</sr> review',
									'convocept'
								),
								{
									stars: (
										<span
											className="convocept-footer__stars"
											aria-hidden="true"
										/>
									),
									sr: <span className="screen-reader-text" />,
								}
							),
							href: boot.urls.review,
						},
					] }
				/>
			</div>
			<Snackbars />
		</>
	);
}
