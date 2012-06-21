<?php
/**
 * Plugin Name: D64 LSR-Stopper
 * Description: Mit diesem Plugin verhinderst du ungewollte Verlinkungen zu Medien, welche das Leitungsschutzrecht unterstützen bzw. in Anspruch nehmen.
 * Author: Dennis Morhardt, D64 e.V.
 * Author URI: http://www.dennismorhardt.de/
 * Plugin URI: http://leistungsschutzrecht-stoppen.d-64.org/
 * Version: 1.0.2
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston,
 * MA 02110-1301 USA
 */

/**
 * Define blacklist update URL
 */
define("LSR_BLACKLIST_URL", "http://leistungsschutzrecht-stoppen.d-64.org/blacklist.txt");

/**
 * Load Simple HTML DOM
 */
require_once dirname(__FILE__) . '/simple_html_dom.php';

/**
 * Function to update the local blacklist storage
 */
function d64_lsr_update_blacklist($updateNow = false) {
	// Get current blacklist
	$blacklist = get_option("d64_lsr_blacklist", new stdClass());
	
	// Check if blacklist exists or is outdated
	if ( true == $updateNow || !isset( $blacklist->nextUpdate ) || time() > $blacklist->nextUpdate ):
		// Basic update time, 30 mins
		$blacklist->nextUpdate = time() + ( 60 * 30 );
		
		// Download new blacklist
		$response = wp_remote_get(LSR_BLACKLIST_URL);

		// Check for errors
		if ( false == is_wp_error( $response ) && 200 == $response['response']['code'] && isset( $response['body'] ) ):
			// Save new blacklist
			$blacklist->sites = array_filter(array_map('trim', explode(",", $response['body'])));
		
			// Set next update time, in 6 hours
			$blacklist->nextUpdate = time() + ( 60 * 60 * 6 );
		endif;
		
		// Update local storage
		update_option("d64_lsr_blacklist", $blacklist);
	endif;
}

/**
 * Check string for link to blacklisted sites and replace them
 */
function d64_lsr_check($string) {
	// Get blacklist
	$blacklist = get_option("d64_lsr_blacklist");
	$blacklist->sites = array_filter(array_map('trim', $blacklist->sites));
	
	// Check if blacklist has entries
	if ( false == is_array( $blacklist->sites ) || 0 == count( $blacklist->sites ) )
		return $string;

	// Get DOM for string
	$dom = str_get_html($string);
	
	// Prepare regex
	$regex = "^(" . implode( "|", $blacklist->sites ) . ")^";
		
	// Find all links
	foreach( $dom->find('a') as $element ):
		// Check if it's a valid and external link
		if ( false == filter_var( $element->href, FILTER_VALIDATE_URL ) || 'http' != substr( $element->href, 0, 4 ) )
			continue;
	
		// Get domain
		$parts = parse_url($element->href);

		// Check for blacklisted domain
		if ( preg_match( $regex, strtolower( $parts["host"] ) ) )
			$element->href = "http://leistungsschutzrecht-stoppen.d-64.org/blacklisted/?url=" . base64_encode($element->href);
	endforeach;

	// Return checked string
	return $dom->save();
}

/**
 * Display stats
 */
function d64_lsr_stats() {
	// Get blacklist
	$blacklist = get_option("d64_lsr_blacklist");
	
	// Display stats
	echo '<p><strong>Schutz gegen das Leistungschutzrecht ist aktiv.</strong> ' . count($blacklist->sites) . ' Seiten werden aktuell blockiert. Nächstes Update der Blacklist in ' . human_time_diff(time(), $blacklist->nextUpdate) . '. <a href="http://leistungsschutzrecht-stoppen.d-64.org/">Jetzt informieren &amp; das Leistungsschutzrecht stoppen &rarr;</a></p>';
}

/**
 * Install or update plugin
 */
function d64_install() {
	// Get a fresh blacklist
	d64_lsr_update_blacklist(true);
}

/**
 * Add filters and actions
 */
add_action("wp_footer", "d64_lsr_update_blacklist");
add_action("activity_box_end", "d64_lsr_stats");
add_filter("the_content", "d64_lsr_check", 999);
add_filter("comment_text", "d64_lsr_check", 999);
register_activation_hook(__FILE__, 'd64_install');