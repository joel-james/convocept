/**
 * Editor side of the Comment Subscription block.
 *
 * The block is rendered on the server (SubscribeForm::render_block()); the
 * editor shows the same output through ServerSideRender, with one setting:
 * follow this post's comments or the whole site's.
 */

/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Internal dependencies
 */
import metadata from './block.json';

/**
 * Render the block in the editor.
 *
 * @param {Object}                  props               Block props.
 * @param {Object}                  props.attributes    Attributes.
 * @param {(attrs: Object) => void} props.setAttributes Attribute setter.
 * @return {Element} Editor element.
 */
function Edit( { attributes, setAttributes } ) {
	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'convocept' ) }>
					<SelectControl
						label={ __(
							'Readers get new comments on',
							'convocept'
						) }
						value={ attributes.scope }
						options={ [
							{
								label: __( 'This post', 'convocept' ),
								value: 'post',
							},
							{
								label: __( 'The whole site', 'convocept' ),
								value: 'site',
							},
						] }
						onChange={ ( scope ) => setAttributes( { scope } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block={ metadata.name }
				attributes={ attributes }
			/>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
