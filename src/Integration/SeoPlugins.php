<?php
/**
 * SEO plugin schema integration.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Integration;

use Convocept\Schema\StructuredData;

defined( 'ABSPATH' ) || exit;

/**
 * Adds comments to the schema graph of Yoast SEO and Rank Math.
 *
 * Both plugins print one JSON-LD graph describing the page and its
 * article. Adding the comments to that article, instead of printing a
 * second article node of our own, keeps a single entity for search
 * engines. When neither filter runs (plugin inactive, or its schema
 * output turned off), StructuredData prints its own node in the footer.
 *
 * @since 1.0.0
 */
class SeoPlugins {

	/**
	 * Structured data service.
	 *
	 * @since 1.0.0
	 * @var StructuredData
	 */
	private $schema;

	/**
	 * Create the integration.
	 *
	 * @since 1.0.0
	 *
	 * @param StructuredData $schema Structured data service.
	 */
	public function __construct( StructuredData $schema ) {
		$this->schema = $schema;
	}

	/**
	 * Register hooks.
	 *
	 * Registering a filter for a plugin that isn't installed costs
	 * nothing, and avoids depending on either plugin's load order.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		// Yoast SEO: the whole graph, a list of nodes.
		add_filter( 'wpseo_schema_graph', array( $this, 'filter_graph' ), 20 );

		// Rank Math: entities keyed by name; late, after its own schema types are added.
		add_filter( 'rank_math/json_ld', array( $this, 'filter_graph' ), 999 );
	}

	/**
	 * Add the current post's comments to an SEO plugin's graph.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $graph Graph nodes from the SEO plugin.
	 * @return mixed The graph, with comments added to its article or web page node.
	 */
	public function filter_graph( $graph ) {
		return is_array( $graph ) ? $this->schema->attach_to_graph( $graph ) : $graph;
	}
}
