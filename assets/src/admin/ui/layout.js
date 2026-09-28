/**
 * Layout components of the Foxe Plugin Admin UI: Sidebar, PageHeader,
 * PageFooter, ExternalLink, StatusChip, Card, Notice and FloatingBar.
 */

/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { Icon, Logo } from './icons';

/**
 * The plugin's navigation: brand, then grouped links to pages.
 *
 * Below 782px the links are replaced by a section dropdown (CSS switches
 * which one shows), so every page stays reachable on a phone.
 *
 * @param {Object}                 props            Props.
 * @param {string}                 props.name       Plugin name.
 * @param {string}                 props.meta       One line under the name (what it is · version).
 * @param {Array}                  props.groups     Groups: [{ id, label, pages: [{ id, title, icon, flag }] }].
 * @param {string}                 props.current    Current page ID.
 * @param {(id: string) => string} props.hrefFor    Address of a page, so links can open in a new tab.
 * @param {(id: string) => void}   props.onNavigate Called with a page ID when a link is clicked or the dropdown changes.
 * @return {Element} Sidebar.
 */
export function Sidebar( {
	name,
	meta,
	groups,
	current,
	hrefFor,
	onNavigate,
} ) {
	/**
	 * Switch pages in place on a plain click; let modified clicks open a new tab.
	 *
	 * @param {MouseEvent} event Click event.
	 * @param {string}     id    Page ID.
	 * @return {void}
	 */
	const onClick = ( event, id ) => {
		if (
			event.button !== 0 ||
			event.metaKey ||
			event.ctrlKey ||
			event.shiftKey ||
			event.altKey
		) {
			return;
		}

		event.preventDefault();
		onNavigate( id );
	};

	return (
		<aside className="convocept-sidebar">
			<div className="convocept-brand">
				<Logo />
				<div>
					<div className="convocept-brand__name">{ name }</div>
					<div className="convocept-brand__meta">{ meta }</div>
				</div>
			</div>
			<nav
				className="convocept-nav"
				aria-label={ __( 'Convocept sections', 'convocept' ) }
			>
				{ groups.map( ( group ) => (
					<div key={ group.id }>
						<h2
							className="convocept-nav-group"
							id={ `convocept-nav-${ group.id }` }
						>
							{ group.label }
						</h2>
						<ul
							className="convocept-nav-list"
							aria-labelledby={ `convocept-nav-${ group.id }` }
						>
							{ group.pages.map( ( page ) => (
								<li key={ page.id }>
									<a
										className="convocept-nav-item"
										href={ hrefFor( page.id ) }
										onClick={ ( event ) =>
											onClick( event, page.id )
										}
										aria-current={
											page.id === current
												? 'page'
												: undefined
										}
									>
										<Icon name={ page.icon } />
										<span className="convocept-nav-item__label">
											{ page.title }
										</span>
										{ page.flag && (
											<span
												className="convocept-dot convocept-dot--warning"
												title={ page.flag }
											>
												<span className="screen-reader-text">
													{ page.flag }
												</span>
											</span>
										) }
									</a>
								</li>
							) ) }
						</ul>
					</div>
				) ) }
			</nav>
			<div className="convocept-nav-select">
				<label
					className="screen-reader-text"
					htmlFor="convocept-nav-select"
				>
					{ __( 'Section', 'convocept' ) }
				</label>
				<select
					id="convocept-nav-select"
					value={ current }
					onChange={ ( event ) => onNavigate( event.target.value ) }
				>
					{ groups.map( ( group ) => (
						<optgroup key={ group.id } label={ group.label }>
							{ group.pages.map( ( page ) => (
								<option key={ page.id } value={ page.id }>
									{ page.title }
								</option>
							) ) }
						</optgroup>
					) ) }
				</select>
			</div>
		</aside>
	);
}

/**
 * The strip at the top of a page: its title and status chips.
 *
 * @param {Object}  props           Props.
 * @param {string}  props.title     Page title (the page's only h1).
 * @param {Element} [props.chips]   StatusChips.
 * @param {Element} [props.actions] Up to two buttons.
 * @return {Element} Header.
 */
export function PageHeader( { title, chips, actions } ) {
	return (
		<header className="convocept-header">
			<h1 className="convocept-header__title" tabIndex="-1">
				{ title }
			</h1>
			{ chips }
			{ actions }
		</header>
	);
}

/**
 * A link that opens in a new tab and says so to screen readers.
 *
 * @param {Object}  props          Props.
 * @param {string}  props.href     Address.
 * @param {Element} props.children Link text.
 * @return {Element} Link.
 */
export function ExternalLink( { href, children } ) {
	return (
		<a href={ href } target="_blank" rel="noopener noreferrer">
			{ children }
			<span className="screen-reader-text">
				{ __( '(opens in a new tab)', 'convocept' ) }
			</span>
		</a>
	);
}

/**
 * The last line of the screen: plugin, version, maker and links.
 *
 * Replaces WordPress's own admin footer, which the plugin hides on its
 * screen. Links open in a new tab and say so to screen readers.
 *
 * @param {Object}                                 props       Props.
 * @param {string|Element}                         props.meta  Plugin, version and maker, e.g. "Convocept 1.0.0 · by Foxe Labs"; the maker may be an ExternalLink.
 * @param {string}                                 props.label Name of the link list for screen readers.
 * @param {Array<{ label: string, href: string }>} props.links Links, labelled with their domain (convocept.com).
 * @return {Element} Footer.
 */
export function PageFooter( { meta, label, links } ) {
	return (
		<footer className="convocept-footer">
			<span className="convocept-footer__meta">{ meta }</span>
			<nav className="convocept-footer__links" aria-label={ label }>
				{ links.map( ( link ) => (
					<ExternalLink key={ link.href } href={ link.href }>
						{ link.label }
					</ExternalLink>
				) ) }
			</nav>
		</footer>
	);
}

/**
 * A read-only fact about the current state.
 *
 * @param {Object}  props          Props.
 * @param {string}  [props.tone]   'success' or 'neutral'.
 * @param {boolean} [props.dot]    Whether to show a status dot.
 * @param {string}  props.children Text.
 * @return {Element} Chip.
 */
export function StatusChip( { tone = 'neutral', dot = false, children } ) {
	return (
		<span className={ `convocept-chip convocept-chip--${ tone }` }>
			{ dot && <span className="convocept-dot" aria-hidden="true" /> }
			{ children }
		</span>
	);
}

/**
 * A white panel holding one topic.
 *
 * @param {Object}  props               Props.
 * @param {string}  props.title         Card title.
 * @param {string}  [props.description] One sentence under the title.
 * @param {Element} props.children      Controls.
 * @return {Element} Card.
 */
export function Card( { title, description, children } ) {
	return (
		<section className="convocept-card">
			<h2 className="convocept-card__title">{ title }</h2>
			{ description && (
				<p className="convocept-card__desc">{ description }</p>
			) }
			{ children && (
				<div className="convocept-card__body">{ children }</div>
			) }
		</section>
	);
}

/**
 * A message about a state that stays until it is resolved.
 *
 * @param {Object}  props            Props.
 * @param {string}  [props.tone]     'warning', 'info' or 'error'.
 * @param {string}  props.title      What is true.
 * @param {Element} [props.children] What to do.
 * @param {Element} [props.actions]  At most one fix button, plus Dismiss.
 * @return {Element} Notice.
 */
export function Notice( { tone = 'info', title, children, actions } ) {
	return (
		<div
			className={ `convocept-notice convocept-notice--${ tone }` }
			role={ 'error' === tone ? 'alert' : undefined }
		>
			<Icon name={ 'info' === tone ? 'info' : 'warning' } size={ 20 } />
			<div className="convocept-notice__body">
				<strong>{ title }</strong>
				{ children && <div>{ children }</div> }
				{ actions && (
					<div className="convocept-notice__actions">{ actions }</div>
				) }
			</div>
		</div>
	);
}

/**
 * The ink pill carrying pending work, shown only while there is some.
 *
 * @param {Object}     props             Props.
 * @param {string}     props.text        Count sentence.
 * @param {boolean}    [props.warning]   Whether to show the attention dot.
 * @param {string}     props.undoLabel   Label of the quiet action.
 * @param {() => void} props.onUndo      Quiet action.
 * @param {string}     props.actionLabel Label of the primary action.
 * @param {() => void} props.onAction    Primary action.
 * @param {boolean}    [props.busy]      Whether the primary action is running.
 * @param {boolean}    [props.disabled]  Whether the primary action is unavailable.
 * @return {Element} Floating bar.
 */
export function FloatingBar( {
	text,
	warning = true,
	undoLabel,
	onUndo,
	actionLabel,
	onAction,
	busy = false,
	disabled = false,
} ) {
	return (
		<div
			className="convocept-floatbar"
			role="region"
			aria-label={ __( 'Unsaved changes', 'convocept' ) }
		>
			{ warning && (
				<span
					className="convocept-dot convocept-dot--warning"
					aria-hidden="true"
				/>
			) }
			<span aria-live="polite">{ text }</span>
			<Button onClick={ onUndo } disabled={ busy }>
				{ undoLabel }
			</Button>
			<Button
				variant="primary"
				onClick={ onAction }
				isBusy={ busy }
				disabled={ busy || disabled }
				accessibleWhenDisabled
			>
				{ actionLabel }
			</Button>
		</div>
	);
}
