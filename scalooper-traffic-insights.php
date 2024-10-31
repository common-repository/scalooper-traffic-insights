<?php
/**
 * Plugin Name:  Scalooper Traffic Insights
 * Plugin URI:   https://scalooper.de/en/home-2/
 * Description:  Discover up to 90% more of your hidden traffic with triple tracking: cookie-based, cookie-less and server-side. Up and running in 5 minutes.
 * Version:      1.0.0
 * Author:       MBmedien Group GmbH
 * License:      GPLv2 or later
 *
 * @package scalooper_traffic_insights
 */

namespace scalooper\trafficinsights;

require_once __DIR__ . '/class-tracker.php';
require_once __DIR__ . '/class-settings.php';

define( 'SCALOOPER_TRAFFICINSIGHTS_VERSION', '1.0.0' );
define( 'SCALOOPER_TRAFFICINSIGHTS_PREFIX', 'scalooper_ti_' );

// Instantiate our class.
$scalooper_perfmon = Tracker::get_instance();

if ( is_admin() ) {
	new Settings();
}
