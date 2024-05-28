<?php // @codingStandardsIgnoreLine
/**
 * Plugin Name: InstaWP Two Way Sync Sample
 * Description: Sample Plugin for InstaWPTwo Way Sync.
 * Version:     1.0.0
 * Author:      InstaWP
 * Author URI:  https://instawp.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: iwp-2waysync-sample-plugin    
 *
 * @package iwp-2waysync-sample-plugin
 * @author  Sayan Datta
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'INSTAWP_PLUGIN_VERSION' ) ) {
	require_once __DIR__ . '/classes/class-post-meta.php';
	require_once __DIR__ . '/classes/class-term-meta.php';
}