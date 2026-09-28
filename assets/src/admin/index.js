/**
 * Convocept settings screen (wp-admin only).
 *
 * Mounts the settings app on Comments → Convocept. Add-ons add pages with
 * `window.convoceptAdmin.registerPanel()` from a script that loads after
 * this one (depend on the `convocept-admin` handle); the app mounts once
 * the DOM is ready, after every such script has run.
 */

/**
 * WordPress dependencies
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import App from './app/App';
import {
	AdvancedPage,
	AppearancePage,
	ComposerPage,
	GeneralPage,
	ModerationPage,
	SubscriptionsPage,
} from './pages/settings';
import { ImportPage } from './pages/import';
import { HelpPage } from './pages/help';

const panels = [];

window.convoceptAdmin = window.convoceptAdmin || {
	/**
	 * Add a page to the settings screen.
	 *
	 * @param {Object}                     panel        Page.
	 * @param {string}                     panel.id     Unique ID, used as the `tab` query argument.
	 * @param {string}                     panel.group  'settings', 'manage' or 'more'.
	 * @param {string}                     panel.title  Short noun label.
	 * @param {string}                     [panel.icon] Icon name from the admin UI icon set.
	 * @param {string[]}                   [panel.keys] Settings keys edited on the page.
	 * @param {(props: Object) => Element} panel.render Component receiving `{ draft, saved, update, errors, boot }`.
	 * @return {void}
	 */
	registerPanel( panel ) {
		panels.push( panel );
	},

	/**
	 * Get the pages added by add-ons.
	 *
	 * @return {Object[]} Pages.
	 */
	getPanels() {
		return panels.slice();
	},
};

domReady( () => {
	const root = document.getElementById( 'convocept-admin' );
	const boot = window.convoceptAdminBoot;

	if ( ! root || ! boot ) {
		return;
	}

	const groups = [
		{ id: 'settings', label: __( 'Settings', 'convocept' ) },
		{ id: 'manage', label: __( 'Manage', 'convocept' ) },
		{ id: 'more', label: __( 'More', 'convocept' ) },
	];

	const core = [
		{
			id: 'general',
			group: 'settings',
			title: __( 'General', 'convocept' ),
			icon: 'general',
			keys: [
				'enabled',
				'post_types',
				'default_sort',
				'per_page',
				'max_depth',
				'replies_shown',
				'votes',
				'live_updates',
				'poll_interval',
			],
			render: GeneralPage,
		},
		{
			id: 'appearance',
			group: 'settings',
			title: __( 'Appearance', 'convocept' ),
			icon: 'appearance',
			keys: [ 'color_scheme', 'accent_color' ],
			render: AppearancePage,
		},
		{
			id: 'composer',
			group: 'settings',
			title: __( 'Composer', 'convocept' ),
			icon: 'composer',
			keys: [ 'edit_window' ],
			render: ComposerPage,
		},
		{
			id: 'moderation',
			group: 'settings',
			title: __( 'Moderation & spam', 'convocept' ),
			icon: 'moderation',
			keys: [],
			render: ModerationPage,
		},
		{
			id: 'subscriptions',
			group: 'settings',
			title: __( 'Subscriptions', 'convocept' ),
			icon: 'subscriptions',
			keys: [ 'subscriptions', 'double_opt_in', 'batch_size' ],
			render: SubscriptionsPage,
		},
		{
			id: 'advanced',
			group: 'settings',
			title: __( 'Advanced', 'convocept' ),
			icon: 'advanced',
			keys: [
				'trusted_proxies',
				'proxy_header',
				'commenter_ip',
				'structured_data',
				'delete_data',
			],
			render: AdvancedPage,
		},
		boot.canImport && {
			id: 'import',
			group: 'manage',
			title: __( 'Import', 'convocept' ),
			icon: 'import',
			keys: [],
			render: ImportPage,
		},
		{
			id: 'help',
			group: 'more',
			title: __( 'Help', 'convocept' ),
			icon: 'help',
			keys: [],
			render: HelpPage,
		},
	].filter( Boolean );

	const ids = new Set( core.map( ( page ) => page.id ) );
	const extra = panels.filter(
		( panel ) =>
			panel &&
			panel.id &&
			! ids.has( panel.id ) &&
			groups.some( ( group ) => group.id === panel.group ) &&
			'function' === typeof panel.render
	);

	root.innerHTML = '';
	createRoot( root ).render(
		<App boot={ boot } pages={ [ ...core, ...extra ] } groups={ groups } />
	);
} );
