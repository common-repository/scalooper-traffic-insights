<?php
/**
 * Class scalooper\trafficinsights\Tracker
 *
 * @package scalooper_traffic_insights
 */

namespace scalooper\trafficinsights;

/**
 * Tracker class
 */
class Tracker {
	/**
	 * Static property to hold our singleton instance
	 *
	 * @var Tracker
	 */
	protected static $instance = false;

	/**
	 * Options array
	 *
	 * @var string[]
	 */
	protected $options = array(
		'matomo_api_url' => '',
		'matomo_api_key' => '',
		'matomo_site_id' => '',
	);

	/**
	 * Current pageview id
	 *
	 * @var string
	 */
	protected $id_pageview;

	/**
	 * Current visitor id
	 *
	 * @var string
	 */
	protected $visitor_id;

	/**
	 * Constructor
	 */
	protected function __construct() {

		add_action( 'wp_ajax_scalooper_traffic_insights_nojs', array( $this, 'mbnojs' ) );
		add_action( 'wp_ajax_nopriv_scalooper_traffic_insights_nojs', array( $this, 'mbnojs' ) );

		add_action( 'wp_ajax_scalooper_ti_create_cookie', array( $this, 'ajax_scalooper_ti_create_cookie' ) );
		add_action( 'wp_ajax_nopriv_scalooper_ti_create_cookie', array( $this, 'ajax_scalooper_ti_create_cookie' ) );

		add_action( 'wp_scalooper_track_download', array( $this, 'track_download' ) );

		$this->load_options();

		if ( ! is_admin() ) {
			add_action( 'template_redirect', array( $this, 'track_page_view' ), 2000 );
		}
	}

	/**
	 * Load the options
	 *
	 * @return void
	 */
	protected function load_options() {
		$overwrite = false;
		if ( is_multisite() ) {
			$network_option = get_site_option(
				'scalooper_ti_site_option',
				array(
					'matomo_api_url'   => '',
					'matomo_api_key'   => '',
					'matomo_overwrite' => 0,
				)
			);

			$overwrite = $network_option['matomo_overwrite'];
		}

		$options = get_option( 'scalooper_ti_option' );

		if ( $overwrite ) {
			$this->options['matomo_api_url'] = $network_option['matomo_api_url'] ?? '';
			$this->options['matomo_api_key'] = $network_option['matomo_api_key'] ?? '';
		} else {
			$this->options['matomo_api_url'] = $options['matomo_api_url'] ?? '';
			if ( empty( $this->options['matomo_api_url'] ) ) {
				$this->options['matomo_api_url'] = $network_option['matomo_api_url'] ?? '';
			}

			$this->options['matomo_api_key'] = $options['matomo_api_key'] ?? '';
			if ( empty( $this->options['matomo_api_key'] ) ) {
				$this->options['matomo_api_key'] = $network_option['matomo_api_key'] ?? '';
			}
		}
		$this->options['matomo_site_id'] = $options['matomo_site_id'] ?? '';
	}

	/**
	 * Check if it is a page
	 *
	 * @return bool
	 */
	protected function is_front_page(): bool {
		if ( is_page()
			|| is_single()
			|| is_singular()
			|| is_archive()
			|| is_home()
			|| is_front_page()
			|| is_category()
			|| is_tag()
			|| is_author()
			|| is_search()
			|| is_feed()
		) {
			return true;
		}
		return false;
	}

	/**
	 * If an instance exists, this returns it. If not, it creates one and
	 * retuns it.
	 *
	 * @return Tracker
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Track the current download
	 *
	 * @param string $download_url The Download URL.
	 * @return void
	 */
	public function track_download( $download_url ) {

		if ( empty( $this->options['matomo_site_id'] ) || empty( $this->options['matomo_api_url'] ) || empty( $this->options['matomo_api_key'] ) ) {
			return;
		}

		$this->do_track_action_download( $download_url );
	}

	/**
	 * Track if the user has javascript activated
	 *
	 * @return void
	 */
	public function mbnojs() {
		$is_js       = wp_validate_boolean( sanitize_text_field( wp_unslash( $_GET['js'] ?? false ) ) );
		$id_pageview = sanitize_text_field( wp_unslash( $_GET['idPageview'] ?? null ) );

		if ( ctype_xdigit( $id_pageview ) && strlen( $id_pageview ) === 6 ) {
			$this->id_pageview = $id_pageview;
		} else {
			$this->id_pageview = $this->create_id_page_view();
		}

		if ( $is_js ) {
			header( 'Content-Type: application/javascript' );

			$this->do_track_event( 'Matomo', 'JS is loaded' );
		} else {
			$this->do_track_event( 'Matomo', 'NOJS' );
		}

		wp_die();
	}

	/**
	 * Track the current page view
	 *
	 * @return void
	 */
	public function track_page_view() {

		if ( ! $this->is_front_page() || empty( $this->options['matomo_site_id'] ) || empty( $this->options['matomo_api_url'] ) || empty( $this->options['matomo_api_key'] ) ) {
			return;
		}

		$this->id_pageview = $this->create_id_page_view();

		$this->visitor_id = $this->get_visitor_id();

		$this->do_track_page_view();

		wp_register_script( 'scalooper_traffic_insights', plugins_url( '/m.js', __FILE__ ), array(), SCALOOPER_TRAFFICINSIGHTS_VERSION, true );
		wp_enqueue_script( 'scalooper_traffic_insights' );

		$translation_array = array(
			'visitorId'                            => $this->visitor_id,
			'matomo_site_id'                       => $this->options['matomo_site_id'],
			'url_host'                             => wp_parse_url( $this->options['matomo_api_url'], PHP_URL_HOST ),
			'idPageview'                           => rawurlencode( $this->id_pageview ),
			'admin_url_scalooper_ti_create_cookie' => admin_url( 'admin-ajax.php' ) . '?action=scalooper_ti_create_cookie',
		);
		wp_localize_script( 'scalooper_traffic_insights', 'scalooper_traffic_insights_obj', $translation_array );

		wp_enqueue_script( 'scalooper_traffic_insights_js', admin_url( 'admin-ajax.php' ) . '?action=scalooper_traffic_insights_nojs&js=1&idPageview=' . rawurlencode( $this->id_pageview ), array(), SCALOOPER_TRAFFICINSIGHTS_VERSION, array( 'in_footer' => true ) );

		add_action(
			'wp_footer',
			function () {

				?>
			<noscript><img alt="" style="display:none" src="<?php echo esc_url( admin_url( 'admin-ajax.php' ) . '?action=mbnojs&idPageview=' . rawurlencode( $this->id_pageview ) ); ?>"></noscript>
				<?php
			}
		);
	}

	/**
	 * Track the current download.
	 *
	 * @param string $download_url The download url.
	 * @return void
	 */
	protected function do_track_action_download( $download_url ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		if ( \WP_Http::is_ip_address( $ip ) === false ) {
			return;
		}

		$data = array(
			'idsite'     => $this->options['matomo_site_id'],
			'rec'        => 1,
			'apiv'       => 1,
			'r'          => substr( strval( wp_rand() ), 2, 6 ),
			'token_auth' => $this->options['matomo_api_key'],
			'cip'        => $ip,
			'_idts'      => time(),
			'cid'        => $this->get_visitor_id(),
			'urlref'     => esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ), array( 'http', 'https' ) ),
			'send_image' => 0,
			'download'   => $download_url,
		);

		$this->send_request( $data );
	}

	/**
	 * Track the current event
	 *
	 * @param string $category Matomo categorie Name.
	 * @param string $action Matomo  action.
	 * @return void
	 */
	protected function do_track_event( $category, $action ) {
		global $wp;

		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		if ( \WP_Http::is_ip_address( $ip ) === false ) {
			return;
		}

		$data = array(
			'idsite'     => $this->options['matomo_site_id'],
			'rec'        => 1,
			'apiv'       => 1,
			'r'          => substr( strval( wp_rand() ), 2, 6 ),
			'token_auth' => $this->options['matomo_api_key'],
			'cip'        => $ip,
			'_idts'      => time(),
			'cid'        => $this->get_visitor_id(),
			'urlref'     => esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ), array( 'http', 'https' ) ),
			'pv_id'      => $this->id_pageview,
			'send_image' => 0,
			'e_c'        => $category,
			'e_a'        => $action,
		);

		$this->send_request( $data );
	}

	/**
	 * Track the current page
	 *
	 * @return void
	 */
	protected function do_track_page_view() {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		if ( \WP_Http::is_ip_address( $ip ) === false ) {
			return;
		}

		$data = array(
			'idsite'      => $this->options['matomo_site_id'],
			'rec'         => 1,
			'apiv'        => 1,
			'r'           => substr( strval( wp_rand() ), 2, 6 ),
			'action_name' => wp_get_document_title(),
			'url'         => esc_url_raw( home_url() . wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ),
			'token_auth'  => $this->options['matomo_api_key'],
			'cip'         => $ip,
			'_idts'       => time(),
			'cid'         => $this->get_visitor_id(),
			'urlref'      => esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ), array( 'http', 'https' ) ),
			'pv_id'       => $this->id_pageview,
			'send_image'  => 0,
		);

		$this->send_request( $data );
	}

	/**
	 * Send a post requests to the matomo server.
	 *
	 * @param array $data Array of data.
	 * @return void
	 */
	protected function send_request( array $data ) {
		$api_url  = rtrim( $this->options['matomo_api_url'], '/' );
		$api_url  = $api_url . '/matomo.php';
		$response = wp_remote_post(
			$api_url,
			array(
				'method'   => 'POST',
				'headers'  => array(
					'content-type'    => 'application/x-www-form-urlencoded',
					'Accept-Language' => sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '' ) ),
					'User-Agent'      => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? false ) ),
				),
				'blocking' => false,
				'timeout'  => 5,
				'body'     => $data,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Matomo tracking failed: ' . $response->get_error_message() );
		}
	}

	/**
	 * Get the current visitor id or create a new visitor id
	 *
	 * @return string
	 */
	protected function get_visitor_id() {
		$visitor_id = sanitize_text_field( wp_unslash( $_COOKIE[ SCALOOPER_TRAFFICINSIGHTS_PREFIX . '_id' ] ?? null ) );

		if ( empty( $visitor_id ) ) {
			$visitor_id = $this->create_visitor_id();
		}

		if ( strlen( $visitor_id ) !== 16 || ! ctype_xdigit( $visitor_id ) ) {
			$visitor_id = $this->create_visitor_id();
		}

		return $visitor_id;
	}

	/**
	 * Create visitor id from client data
	 *
	 * @return string
	 */
	protected function create_visitor_id() {
		$salt          = NONCE_SALT;
		$ip            = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$user_agent    = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
		$date          = gmdate( 'Y-m-d' );
		$config_string = $ip . $user_agent . $date . $salt;
		$hash          = md5( $config_string, true );
		return bin2hex( substr( $hash, 0, 8 ) );
	}

	/**
	 * Create a uniquid id for the page view
	 *
	 * @return string
	 */
	protected function create_id_page_view() {
		return substr( md5( uniqid( wp_rand(), true ) ), 0, 6 );
	}

	/**
	 * Set The visitor Cookie
	 *
	 * @return void
	 */
	public function ajax_scalooper_ti_create_cookie() {
		$id = $this->get_visitor_id();

		setcookie(
			SCALOOPER_TRAFFICINSIGHTS_PREFIX . '_id',
			$id,
			array(

				'expires'  => time() + 365 * 24 * 60 * 60,
				'path'     => '/',
				'samesite' => 'None',
				'secure'   => true,
				'httponly' => true,
			)
		);
	}
}
