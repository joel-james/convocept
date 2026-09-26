/**
 * Convocept settings app (wp-admin only).
 *
 * The React settings screen arrives in M9. Add-ons will register panels
 * through window.convoceptAdmin.registerPanel().
 */
const panels = [];

window.convoceptAdmin = window.convoceptAdmin || {
	registerPanel( panel ) {
		panels.push( panel );
	},
	getPanels() {
		return panels.slice();
	},
};
