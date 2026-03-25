<?php

abstract class PSource_Support_Shortcode {
	public abstract function render( $atts );

	public function end() {
		echo '</div><div style="clear:both">';
		return ob_get_clean();
	}

	public function start() {
		if ( function_exists( 'psource_support' ) && psource_support()->shortcodes ) {
			psource_support()->shortcodes->enqueue_scripts();
		}

		echo '<div id="support-system">';
		ob_start();
	}

}