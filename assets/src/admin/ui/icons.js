/**
 * Stroke icons for the admin UI.
 *
 * 24px grid, 1.5px stroke in currentColor, as the Foxe Plugin Admin UI asks
 * for. Drawn here rather than importing `@wordpress/icons`, which would be
 * bundled into the script.
 */

/**
 * Render a stroke icon.
 *
 * @param {Object} props        Props.
 * @param {string} props.name   Icon name (a key of PATHS).
 * @param {number} [props.size] Rendered size in px.
 * @return {Element} SVG element.
 */
export function Icon( { name, size = 18 } ) {
	return (
		<svg
			width={ size }
			height={ size }
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.5"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
			focusable="false"
		>
			{ PATHS[ name ] || PATHS.dot }
		</svg>
	);
}

const PATHS = {
	general: (
		<>
			<path d="M5 6.5h14M5 12h14M5 17.5h14" />
			<circle cx="9" cy="6.5" r="1.8" fill="#fff" />
			<circle cx="15" cy="12" r="1.8" fill="#fff" />
			<circle cx="8" cy="17.5" r="1.8" fill="#fff" />
		</>
	),
	appearance: (
		<>
			<circle cx="12" cy="12" r="8" />
			<path d="M12 4a8 8 0 0 0 0 16z" fill="currentColor" />
		</>
	),
	composer: (
		<>
			<path d="M4 20h4L19 9a2.1 2.1 0 0 0-3-3L5 17z" />
			<path d="m14.5 7.5 3 3" />
		</>
	),
	moderation: (
		<>
			<path d="M12 3.5 19 6v5.5c0 4.2-3 7.4-7 9-4-1.6-7-4.8-7-9V6z" />
			<path d="m9 12 2 2 4-4" />
		</>
	),
	subscriptions: (
		<>
			<rect x="3.5" y="5.5" width="17" height="13" rx="2" />
			<path d="m4 7 8 6 8-6" />
		</>
	),
	advanced: (
		<>
			<circle cx="12" cy="12" r="3" />
			<path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21M5.6 5.6l1.8 1.8M16.6 16.6l1.8 1.8M5.6 18.4l1.8-1.8M16.6 7.4l1.8-1.8" />
		</>
	),
	import: (
		<>
			<path d="M12 4v11M7.5 10.5 12 15l4.5-4.5" />
			<path d="M4.5 15.5v3a1.5 1.5 0 0 0 1.5 1.5h12a1.5 1.5 0 0 0 1.5-1.5v-3" />
		</>
	),
	help: (
		<>
			<circle cx="12" cy="12" r="8.5" />
			<path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .8-1 1.5v.7M12 17h.01" />
		</>
	),
	warning: (
		<>
			<path d="M12 4.5 20 18.5H4z" />
			<path d="M12 10v3.5M12 16h.01" />
		</>
	),
	info: (
		<>
			<circle cx="12" cy="12" r="8" />
			<path d="M12 11v5M12 8h.01" />
		</>
	),
	dot: <circle cx="12" cy="12" r="3" />,
};

/**
 * Render the Convocept mark: an ink tile with a white speech bubble.
 *
 * A placeholder until Convocept has its own logo.
 *
 * @return {Element} SVG element.
 */
export function Logo() {
	return (
		<svg
			width="34"
			height="34"
			viewBox="0 0 32 32"
			aria-hidden="true"
			focusable="false"
		>
			<rect width="32" height="32" rx="8" fill="#111" />
			<path
				d="M8 9.5A2.5 2.5 0 0 1 10.5 7h11A2.5 2.5 0 0 1 24 9.5v8a2.5 2.5 0 0 1-2.5 2.5H15l-4.5 4v-4a2.5 2.5 0 0 1-2.5-2.5z"
				fill="#fff"
			/>
			<path
				d="M12 12.5h8M12 16h5"
				stroke="#111"
				strokeWidth="1.5"
				strokeLinecap="round"
			/>
		</svg>
	);
}
