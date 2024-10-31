var _paq = window._paq = window._paq || [];

_paq.push( ['disableCookies'] );
_paq.push( ['enableLinkTracking'] );
_paq.push( ['appendToTrackingUrl', 'pv_id=' + scalooper_traffic_insights_obj.idPageview] );
_paq.push( ['enableHeartBeatTimer', 10] );
_paq.push( ['enableBrowserFeatureDetection'] );
_paq.push( ['setVisitorId', scalooper_traffic_insights_obj.visitorId] );
_paq.push( ['trackEvent', 'Matomo', 'load', 'tracking'] );

(function () {
	var u = "//" + scalooper_traffic_insights_obj.url_host + "/";
	_paq.push( ['setTrackerUrl', u + 'matomo.php'] );
	_paq.push( ['setSiteId', scalooper_traffic_insights_obj.matomo_site_id] );
	var d   = document, g = d.createElement( 'script' ), s = d.getElementsByTagName( 'script' )[0];
	g.async = true; g.src = u + 'matomo.js'; s.parentNode.insertBefore( g,s );
})();

function scalooper_traffic_insights_create_cookie() {
	fetch( scalooper_traffic_insights_obj.admin_url_scalooper_ti_create_cookie );
}