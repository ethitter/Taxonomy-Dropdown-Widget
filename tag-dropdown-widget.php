<?php
/*
Plugin Name: Taxonomy Dropdown Widget
Plugin URI: http://www.ethitter.com/plugins/taxonomy-dropdown-widget/
Description: Creates a dropdown list of non-hierarchical taxonomies as an alternative to the term (tag) cloud. Widget provides numerous options to tailor the output to fit your site. Dropdown function can also be called directly for use outside of the widget. Formerly known as <strong><em>Tag Dropdown Widget</em></strong>.
Author: Erick Hitter
Version: 2.0.3
Author URI: http://www.ethitter.com/

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 ** TAXONOMY DROPDOWN WIDGET PLUGIN
 **/
class taxonomy_dropdown_widget_plugin {
	/*
	 * Class variables
	 */
	var $option_defaults = array(
		'taxonomy' => 'post_tag',
		'select_name' => 'Select Tag',
		'max_name_length' => 0,
		'cutoff' => '&hellip;',
		'limit' => 0,
		'order' => 'ASC',
		'orderby' => 'name',
		'threshold' => 0,
		'incexc' => 'exclude',
		'incexc_ids' => array(),
		'hide_empty' => true,
		'post_counts' => false
	);

	/*
	 * Register actions and activation/deactivation hooks
	 * @uses add_action, register_activation_hook, register_deactivation_hook
	 * @return null
	 */
	function __construct() {
		add_action( 'widgets_init', array( $this, 'action_widgets_init' ) );

		register_activation_hook( __FILE__, array( $this, 'activation_hook' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivation_hook' ) );
	}

	/*
	 * Run plugin cleanup on activation
	 * @uses this::cleanup
	 * @hook activation
	 * @return null
	 */
	function activation_hook() {
		$this->cleanup();
	}

	/*
	 * Unregister widget when plugin is deactivated and run cleanup
	 * @uses this::cleanup
	 * @hook deactivation
	 * @return null
	 */
	function deactivation_hook() {
		$this->cleanup();
	}

	/*
	 * Remove options related to plugin versions older than 2.0.
	 * @uses delete_option
	 * @return null
	 */
	function cleanup() {
		$legacy_options = array(
			'widget_TagDropdown',
			'widget_TagDropdown_exclude',
			'function_TagDropdown',
			'TDW_direct'
		);

		foreach ( $legacy_options as $legacy_option ) {
			delete_option( $legacy_option );
		}
	}

	/*
	 * Register widget
	 * @uses register_widget
	 * @action widgets_init
	 * @return null
	 */
	function action_widgets_init() {
		if ( class_exists( 'taxonomy_dropdown_widget' ) )
			register_widget( 'taxonomy_dropdown_widget' );
	}

	/*
	 * Render widget
	 * @param array $options
	 * @param string|int $id
	 * @uses wp_parse_args, sanitize_title, apply_filters, get_terms, is_wp_error, is_tag, is_tax, esc_url, get_term_link, selected
	 * @return string or false
	 */
	function render_dropdown( $options, $id = false ) {
		$options = wp_parse_args( $options, $this->option_defaults );
		extract( $options );

		//ID
		if ( is_numeric( $id ) )
			$id = intval( $id );
		elseif ( is_string( $id ) )
			$id = sanitize_title( $id );

		//Set up options array for get_terms
		$options = array(
			'order' => $order,
			'orderby' => $orderby,
			'hide_empty' => $hide_empty,
			'hierarchical' => false
		);

		if ( $limit )
			$options[ 'number' ] = $limit;

		if ( !empty( $incexc_ids ) )
			$options[ $incexc ] = $incexc_ids;

		$options = apply_filters( 'taxonomy_dropdown_widget_options', $options, $id );
		$options = apply_filters( 'TagDropdown_get_tags', $options );

		//Get terms
		$terms = get_terms( $taxonomy, $options );

		if ( !is_wp_error( $terms ) && is_array( $terms ) && !empty( $terms ) ) {
			//CSS ID
			if ( is_int( $id ) )
				$css_id = ' id="taxonomy_dropdown_widget_dropdown_' . $id . '"';
			elseif ( is_string( $id ) && !empty( $id ) )
				$css_id = ' id="' . $id . '"';

			//Start dropdown
			$output = '<select name="taxonomy_dropdown_widget_dropdown_' . $id . '" class="taxonomy_dropdown_widget_dropdown" onchange="document.location.href=this.options[this.selectedIndex].value;"' . ( isset( $css_id ) ? $css_id : '' ) . '>';

			$output .= '<option value="">' . $select_name . '</option>';

			//Populate dropdown
			$i = 1;
			foreach ( $terms as $term ) {
				if ( $threshold > 0 && $term->count < $threshold )
					continue;

				//Set selected attribute if on an archive page for the current term
				$current = is_tag() ? is_tag( $term->slug ) : is_tax( $taxonomy, $term->slug );

				//Open option tag
				$output .= '<option value="' . esc_url( get_term_link( (int)$term->term_id, $taxonomy ) ) . '"' . ( selected( $current, true , false ) ) . '>';

				//Tag name
				$name = esc_attr( $term->name );
				if ( $max_name_length > 0 && strlen( $name ) > $max_name_length )
					$name = substr( $name, 0, $max_name_length ) . $cutoff;
				$output .= $name;

				//Count
				if ( $post_counts )
					$output .= ' (' . intval( $term->count ) . ')';

				//Close option tag
				$output .= '</option>';

				$i++;
			}

			//End dropdown
			$output .= '</select>';

			return $output;
		} else {
			return false;
		}
	}

	/*
	 * Sanitize plugin options
	 * @param array $options
	 * @uses taxonomy_exists, sanitize_text_field, absint, wp_parse_args
	 * @return array
	 */
	function sanitize_options( $options ) {
		$options_sanitized = array(
			'hide_empty' => true,
			'post_counts' => false
		);

		$keys = array_merge( array_keys( $this->option_defaults ), array( 'title' ) );

		if ( is_array( $options ) ) {
			foreach ( $keys as $key ) {
				if ( !array_key_exists( $key, $options ) )
					continue;

				$value = $options[ $key ];

				switch( $key ) {
					case 'taxonomy':
						if ( taxonomy_exists( $value ) )
							$options_sanitized[ $key ] = $value;
					break;

					case 'title':
					case 'select_name':
					case 'cutoff':
						$value = sanitize_text_field( $value );

						if ( !empty( $value ) || $key == 'title' )
							$options_sanitized[ $key ] = $value;
					break;

					case 'max_name_length':
					case 'limit':
					case 'threshold':
						$options_sanitized[ $key ] = absint( $value );
					break;

					case 'order':
						if ( $value == 'ASC' || $value == 'DESC' )
							$options_sanitized[ $key ] = $value;
					break;

					case 'orderby':
						if ( $value == 'name' || $value == 'count' )
							$options_sanitized[ $key ] = $value;
					break;

					case 'incexc':
						if ( $value == 'include' || $value == 'exclude' )
							$options_sanitized[ $key ] = $value;
					break;

					case 'incexc_ids':
						$options_sanitized[ $key ] = array();

						if ( is_string( $value ) )
							$value = explode( ',', $value );

						if ( is_array( $value ) ) {
							foreach ( $value as $term_id ) {
								$term_id = intval( $term_id );

								if ( $term_id > 0 )
									$options_sanitized[ $key ][] = $term_id;

								unset( $term_id );
							}

							sort( $options_sanitized[ $key ], SORT_NUMERIC );
						}
					break;

					case 'hide_empty':
					case 'post_counts':
						$options_sanitized[ $key ] = (bool)$value;
					break;

					default:
						continue;
					break;
				}
			}
		}

		//Ensure array contains all keys by parsing against defaults after options are sanitized
		$options_sanitized = wp_parse_args( $options_sanitized, $this->option_defaults );

		return $options_sanitized;
	}
}
global $taxonomy_dropdown_widget_plugin;
if ( !is_a( $taxonomy_dropdown_widget_plugin, 'taxonomy_dropdown_widget_plugin' ) )
	$taxonomy_dropdown_widget_plugin = new taxonomy_dropdown_widget_plugin;

/**
 ** TAXONOMY DROPDOWN WIDGET
 **/
class taxonomy_dropdown_widget extends WP_Widget {
	/*
	 * Class variables
	 */
	var $defaults = array(
		'title' => 'Tags'
	);

	/*
	 * Register widget and populate class variables
	 * @uses $this::WP_Widget, $taxonomy_dropdown_widget_plugin
	 * @return null
	 */
	function taxonomy_dropdown_widget() {
		$this->WP_Widget( false, 'Taxonomy Dropdown Widget', array( 'description' => 'Displays selected non-hierarchical taxonomy terms in a dropdown list.' ) );

		//Load plugin class and populate defaults
		global $taxonomy_dropdown_widget_plugin;
		if ( !is_a( $taxonomy_dropdown_widget_plugin, 'taxonomy_dropdown_widget_plugin' ) )
			$taxonomy_dropdown_widget_plugin = new taxonomy_dropdown_widget_plugin;

		if ( is_object( $taxonomy_dropdown_widget_plugin ) && property_exists( $taxonomy_dropdown_widget_plugin, 'option_defaults' ) && is_array( $taxonomy_dropdown_widget_plugin->option_defaults ) )
			$this->defaults = array_merge( $taxonomy_dropdown_widget_plugin->option_defaults, $this->defaults );
	}

	/*
	 * Render widget
	 * @param array $args
	 * @param array $instance
	 * @uses $taxonomy_dropdown_widget_plugin, wp_parse_args, apply_filters
	 * @return string or null
	 */
	function widget( $args, $instance ) {
		//Get plugin class for default options and to build widget
		global $taxonomy_dropdown_widget_plugin;
		if ( !is_a( $taxonomy_dropdown_widget_plugin, 'taxonomy_dropdown_widget_plugin' ) )
			$taxonomy_dropdown_widget_plugin = new taxonomy_dropdown_widget_plugin;

		//Options
		$instance = wp_parse_args( $instance, $this->defaults );
		extract( $args );
		extract( $instance );

		//Widget
		if ( $widget = $taxonomy_dropdown_widget_plugin->render_dropdown( $instance, $this->number ) ) {
			//Wrapper and title
			$output = $before_widget;

			if ( !empty( $title ) )
				$output .= $before_title . apply_filters( 'taxonomy_dropdown_widget_title', '<label for="taxonomy_dropdown_widget_dropdown_' . $this->number . '">' . $title . '</label>', $this->number ) . $after_title;

			//Widget
			$output .= $widget;

			//Wrapper
			$output .= $after_widget;

			echo $output;
		}
	}

	/*
	 * Options sanitization
	 * @param array $new_instance
	 * @param array $old_instance
	 * @uses $taxonomy_dropdown_widget_plugin
	 * @return array
	 */
	function update( $new_instance, $old_instance ) {
		//Get plugin class for sanitization function
		global $taxonomy_dropdown_widget_plugin;
		if ( !is_a( $taxonomy_dropdown_widget_plugin, 'taxonomy_dropdown_widget_plugin' ) )
			$taxonomy_dropdown_widget_plugin = new taxonomy_dropdown_widget_plugin;

		return $taxonomy_dropdown_widget_plugin->sanitize_options( $new_instance );
	}

	/*
	 * Widget options
	 * @param array $instance
	 * @uses wp_parse_args, get_taxonomies, _e, $this::get_field_id, $this::get_field_name, esc_attr, selected, checked
	 * @return string
	 */
	function form( $instance ) {
		//Get options
		$options = wp_parse_args( $instance, $this->defaults );
		extract( $options );

		//Get taxonomies and remove certain Core taxonomies that shouldn't be accessed directly.
		$taxonomies = get_taxonomies( array(
			'public' => true,
			'hierarchical' => false
		), 'objects' );

		if ( array_key_exists( 'nav_menu', $taxonomies ) )
			unset( $taxonomies[ 'nav_menu' ] );

		if ( array_key_exists( 'post_format', $taxonomies ) )
			unset( $taxonomies[ 'post_format' ] );

	?>
		<h3><?php _e( 'Basic Settings' ); ?></h3>

		<p>
			<label for="<?php echo $this->get_field_id( 'taxonomy' ); ?>"><?php _e( 'Taxonomy' ); ?>:</label><br />
			<select name="<?php echo $this->get_field_name( 'taxonomy' ); ?>" id="<?php echo $this->get_field_id( 'taxonomy' ); ?>">
				<?php foreach ( $taxonomies as $tax ): ?>
					<option value="<?php echo esc_attr( $tax->name ); ?>"<?php selected( $tax->name, $taxonomy, true ); ?>><?php echo $tax->labels->name; ?></option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label><br />
			<input type="text" name="<?php echo $this->get_field_name( 'title' ); ?>" class="widefat code" id="<?php echo $this->get_field_id( 'title' ); ?>" value="<?php echo esc_attr( $title ); ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'select_name' ); ?>"><?php _e( 'Default dropdown item:' ); ?></label><br />
			<input type="text" name="<?php echo $this->get_field_name( 'select_name' ); ?>" class="widefat code" id="<?php echo $this->get_field_id( 'select_name' ); ?>" value="<?php echo esc_attr( $select_name ); ?>" />
		</p>

		<h3><?php _e( 'Order' ); ?></h3>

		<p>
			<label><?php _e( 'Order terms by:' ); ?></label><br />

			<input type="radio" name="<?php echo $this->get_field_name( 'orderby' ); ?>" value="name" id="<?php echo $this->get_field_name( 'order_name' ); ?>"<?php checked( $orderby, 'name', true ); ?> />
			<label for="<?php echo $this->get_field_name( 'order_name' ); ?>"><?php _e( 'Name' ); ?></label><br />

			<input type="radio" name="<?php echo $this->get_field_name( 'orderby' ); ?>" value="count" id="<?php echo $this->get_field_name( 'order_count' ); ?>"<?php checked( $orderby, 'count', true ); ?> />
			<label for="<?php echo $this->get_field_name( 'order_count' ); ?>"><?php _e( 'Post count' ); ?></label>
		</p>

		<p>
			<label><?php _e( 'Order terms:' ); ?></label><br />

			<input type="radio" name="<?php echo $this->get_field_name( 'order' ); ?>" value="ASC" id="<?php echo $this->get_field_name( 'order_asc' ); ?>"<?php checked( $order, 'ASC', true ); ?> />
			<label for="<?php echo $this->get_field_name( 'order_asc' ); ?>"><?php _e( 'Ascending' ); ?></label><br />

			<input type="radio" name="<?php echo $this->get_field_name( 'order' ); ?>" value="DESC" id="<?php echo $this->get_field_name( 'order_desc' ); ?>"<?php checked( $order, 'DESC', true ); ?> />
			<label for="<?php echo $this->get_field_name( 'order_desc' ); ?>"><?php _e( 'Descending' ); ?></label>
		</p>

		<h3><?php _e( 'Term Display' ); ?></h3>

		<p>
			<label for="<?php echo $this->get_field_id( 'limit' ); ?>"><?php _e( 'Limit number of terms shown to:' ); ?></label><br />
			<input type="text" name="<?php echo $this->get_field_name( 'limit' ); ?>" id="<?php echo $this->get_field_id( 'limit' ); ?>" value="<?php echo intval( $limit ); ?>" size="3" /><br />
			<span class="description"><?php _e( '<small>Enter <strong>0</strong> for no limit.' ); ?></small></span>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'max_name_length' ); ?>"><?php _e( 'Trim long term names to <em>x</em> characters:</label>' ); ?><br />
			<input type="text" name="<?php echo $this->get_field_name( 'max_name_length' ); ?>" id="<?php echo $this->get_field_id( 'max_name_length' ); ?>" value="<?php echo intval( $max_name_length ); ?>" size="3" /><br />
			<span class="description"><?php _e( '<small>Enter <strong>0</strong> to show full tag names.' ); ?></small></span>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'cutoff' ); ?>"><?php _e( 'Indicator that term names are trimmed:' ); ?></label><br />
			<input type="text" name="<?php echo $this->get_field_name( 'cutoff' ); ?>" id="<?php echo $this->get_field_id( 'cutoff' ); ?>" value="<?php echo esc_attr( $cutoff ); ?>" size="3" /><br />
			<span class="description"><?php _e( '<small>Leave blank to use an elipsis (&hellip;).</small>' ); ?></span>
		</p>

		<p>
			<input type="checkbox" name="<?php echo $this->get_field_name( 'hide_empty' ); ?>" id="<?php echo $this->get_field_id( 'hide_empty' ); ?>"  value="0"<?php checked( false, $hide_empty, true ); ?> />
			<label for="<?php echo $this->get_field_id( 'hide_empty' ); ?>"><?php _e( 'Include terms that aren\'t assigned to any objects (empty terms).' ); ?></label>
		</p>

		<p>
			<input type="checkbox" name="<?php echo $this->get_field_name( 'post_counts' ); ?>" id="<?php echo $this->get_field_id( 'post_counts' ); ?>"  value="1"<?php checked( true, $post_counts, true ); ?> />
			<label for="<?php echo $this->get_field_id( 'post_counts' ); ?>"><?php _e( 'Display object (post) counts after term names.' ); ?></label>
		</p>

		<h3><?php _e( 'Include/Exclude Terms' ); ?></h3>

		<p>
			<label><?php _e( 'Include/exclude terms:' ); ?></label><br />

			<input type="radio" name="<?php echo $this->get_field_name( 'incexc' ); ?>" value="include" id="<?php echo $this->get_field_id( 'include' ); ?>"<?php checked( $incexc, 'include', true ); ?> />
			<label for="<?php echo $this->get_field_id( 'include' ); ?>"><?php _e( 'Include only the term IDs listed below' ); ?></label><br />

			<input type="radio" name="<?php echo $this->get_field_name( 'incexc' ); ?>" value="exclude" id="<?php echo $this->get_field_id( 'exclude' ); ?>"<?php checked( $incexc, 'exclude', true ); ?> />
			<label for="<?php echo $this->get_field_id( 'exclude' ); ?>"><?php _e( 'Exclude the term IDs listed below' ); ?></label>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'incexc_ids' ); ?>"><?php _e( 'Term IDs to include/exclude based on above setting:' ); ?></label><br />
			<input type="text" name="<?php echo $this->get_field_name( 'incexc_ids' ); ?>" class="widefat code" id="<?php echo $this->get_field_id( 'incexc_ids' ); ?>" value="<?php echo esc_attr( implode( ', ', $incexc_ids ) ); ?>" /><br />
			<span class="description"><?php _e( '<small>Enter comma-separated list of term IDs.</small>' ); ?></span>
		</p>

		<h3><?php _e( 'Advanced' ); ?></h3>

		<p>
			<label for="<?php echo $this->get_field_id( 'threshold' ); ?>"><?php _e( 'Show terms assigned to at least this many posts:' ); ?></label><br />
			<input type="text" name="<?php echo $this->get_field_name( 'threshold' ); ?>" id="<?php echo $this->get_field_id( 'threshold' ); ?>" value="<?php echo intval( $threshold ); ?>" size="3" /><br />
			<span class="description"><?php _e( '<small>Set to <strong>0</strong> to display all terms matching the above criteria.</small>' ); ?></span>
		</p>

	<?php
	}
}

/**
 ** HELPER FUNCTIONS
 **/

/*
 * Render taxonomy dropdown
 * @param array $options
 * @param string|int $id
 * @uses $taxonomy_dropdown_widget_plugin
 * @return string or false
 */
function taxonomy_dropdown_widget( $options = array(), $id = '' ) {
	global $taxonomy_dropdown_widget_plugin;
	if ( !is_a( $taxonomy_dropdown_widget_plugin, 'taxonomy_dropdown_widget_plugin' ) )
		$taxonomy_dropdown_widget_plugin = new taxonomy_dropdown_widget_plugin;

	//Sanitize options
	$options = $taxonomy_dropdown_widget_plugin->sanitize_options( $options );

	return $taxonomy_dropdown_widget_plugin->render_dropdown( $options, $id );
}

/**
 ** LEGACY FUNCTIONS FOR BACKWARDS COMPATIBILITY
 **/

if ( !function_exists( 'generateTagDropdown' ) ):
	/*
	 * Build tag dropdown based on provided arguments
	 * @since 1.7
	 * @uses $taxonomy_dropdown_widget_plugin
	 * @return string or false
	 */
	function generateTagDropdown( $args ) {
		global $taxonomy_dropdown_widget_plugin;
		if ( !is_a( $taxonomy_dropdown_widget_plugin, 'taxonomy_dropdown_widget_plugin' ) )
			$taxonomy_dropdown_widget_plugin = new taxonomy_dropdown_widget_plugin;

		//Sanitize options
		$options = $taxonomy_dropdown_widget_plugin->sanitize_options( $args );

		return '<!-- NOTICE: The function used to generate this dropdown list is deprecated as of version 2.0 of Taxonomy Dropdown Widget. You should update your template to use `taxonomy_dropdown_widget` instead. -->' . $taxonomy_dropdown_widget_plugin->render_dropdown( $options, 'legacy_gtd' );
	}
endif;

if ( !function_exists( 'TDW_direct' ) ):
	/*
	 * Build tag dropdown based on provided arguments
	 * @since 1.6
	 * @uses $taxonomy_dropdown_widget_plugin
	 * @return string or false
	 */
	function TDW_direct( $limit = false, $count = false, $exclude = false ) {
		global $taxonomy_dropdown_widget_plugin;
		if ( !is_a( $taxonomy_dropdown_widget_plugin, 'taxonomy_dropdown_widget_plugin' ) )
			$taxonomy_dropdown_widget_plugin = new taxonomy_dropdown_widget_plugin;

		//Build options array from function parameters
		$options = array(
			'max_name_length' => $limit,
			'post_count' => $count
		);

		if ( $exclude ) {
			$options[ 'incexc' ] = 'exclude';
			$options[ 'incexc_ids' ] = $exclude;
		}

		//Sanitize options
		$options = $taxonomy_dropdown_widget_plugin->sanitize_options( $options );

		echo '<!-- NOTICE: The function used to generate this dropdown list is deprecated as of version 1.7 of Taxonomy Dropdown Widget. You should update your template to use `taxonomy_dropdown_widget` instead. -->' . $taxonomy_dropdown_widget_plugin->render_dropdown( $options, 'legacy_tdw' );
	}
endif;

if ( !function_exists( 'makeTagDropdown' ) ):
	/*
	 * Build tag dropdown based on provided arguments
	 * @since 1.3
	 * @uses $taxonomy_dropdown_widget_plugin
	 * @return string or false
	 */
	function makeTagDropdown( $limit = false ) {
		global $taxonomy_dropdown_widget_plugin;
		if ( !is_a( $taxonomy_dropdown_widget_plugin, 'taxonomy_dropdown_widget_plugin' ) )
			$taxonomy_dropdown_widget_plugin = new taxonomy_dropdown_widget_plugin;

		//Sanitize options
		$options = array(
			'max_name_length' => intval( $limit )
		);

		echo '<!-- NOTICE: The function used to generate this dropdown list is deprecated as of version 1.6 of Taxonomy Dropdown Widget. You should update your template to use `taxonomy_dropdown_widget` instead. -->' . $taxonomy_dropdown_widget_plugin->render_dropdown( $options, 'legacy_mtd' );
}
endif;
?>