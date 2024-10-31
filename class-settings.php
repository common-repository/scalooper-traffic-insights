<?php
/**
 * Class scalooper\trafficinsights\Settings
 *
 * @package scalooper_traffic_insights
 */

namespace scalooper\trafficinsights;

/**
 * Settings Class
 */
class Settings {

	/**
	 * Holds the values to be used in the fields callbacks.
	 *
	 * @var array $options
	 */
	private $options;

	/**
	 * Start up
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action( 'wp_ajax_scalooper_test_settings', array( $this, 'ajax_test_settings' ) );

		// Add multisite navigation.
		add_action( 'network_admin_menu', array( $this, 'network_settings_pages' ) );
	}


	/**
	 * Test the api configuration
	 *
	 * @return void
	 */
	public function ajax_test_settings() {

		if ( isset( $_POST['scalooper_ti_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scalooper_ti_nonce'] ) ), 'scalloper-settings-test-nonce' )
		) {

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'WordPress access denied' );
			}

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

			if ( $overwrite ) {
				$options['matomo_api_url'] = $network_option['matomo_api_url'] ?? '';
				$options['matomo_api_key'] = $network_option['matomo_api_key'] ?? '';
			} else {
				$options['matomo_api_url'] = esc_url_raw( wp_unslash( $_POST['scalooper_ti_matomo_api_url'] ?? '' ), array( 'http', 'https' ) );
				if ( empty( $options['matomo_api_url'] ) ) {
					$options['matomo_api_url'] = $network_option['matomo_api_url'] ?? '';
				}

				$options['matomo_api_key'] = sanitize_text_field( wp_unslash( $_POST['scalooper_ti_matomo_api_key'] ?? '' ) );
				if ( empty( $options['matomo_api_key'] ) ) {
					$options['matomo_api_key'] = $network_option['matomo_api_key'] ?? '';
				}
			}
			$options['matomo_site_id'] = absint( $_POST['scalooper_ti_matomo_site_id'] ?? 0 );

			if ( empty( $options['matomo_api_url'] ) ) {
				wp_send_json_error( 'API URL is invalid or not set' );
			}

			$url_parameter = http_build_query(
				array(
					'module' => 'API',
					'method' => 'SitesManager.getSiteFromId',
					'idSite' => $options['matomo_site_id'],
					'format' => 'JSON',

				)
			);
			$response = wp_remote_post(
				$options['matomo_api_url'] . '?' . $url_parameter,
				array(
					'body' => array(
						'token_auth' => $options['matomo_api_key'],
					),
				)
			);
			$response = json_decode( wp_remote_retrieve_body( $response ), true );
			$msg      = 'Matomo API data a incorrect';
			if ( isset( $response['name'] ) ) {
				$msg = 'Success - Matomo Site Name: ' . $response['name'];
			}

			wp_send_json_success( $msg );
		} else {
			wp_send_json_error();
		}
	}

	/**
	 * Add options page
	 */
	public function add_plugin_page() {

		add_menu_page(
			'Scalooper',
			'Scalooper',
			'manage_options',
			'scalooper_setting',
			array( $this, 'network_menu_page_main' ),
			'dashicons-analytics'
		);
		add_submenu_page(
			'scalooper_setting',
			'Scalooper Traffic Insights',
			'Traffic Insights',
			'manage_options',
			'scalooper_setting_real_server_side_tracking_setting',
			array( $this, 'create_admin_page' )
		);
	}

	/**
	 * Options page callback
	 */
	public function create_admin_page() {

		$this->options = get_option( 'scalooper_ti_option' );

		wp_enqueue_script( 'scalloper-settings', plugins_url( '/js/settings.js', __FILE__ ), array( 'wp-util' ), 'v1.2.0', true );

		wp_localize_script(
			'scalloper-settings',
			'scalloper_settings_obj',
			array(
				'test_nonce' => wp_create_nonce( 'scalloper-settings-test-nonce' ),
			)
		);

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>Adjust the configuration of Scalooper Traffic Insights. If you are running a multi-site environment. you may define
				global settings that override the specific settings for gour individual Sites.</p>
			<form method="post" action="options.php">
			<?php
				// This prints out all hidden setting fields.
				settings_fields( 'scalooper_ti_option_group' );

				do_settings_sections( 'scalooper-perfmon-setting-admin' );
			?>
			<input type="button" class="button button-secondary" value="Test setting" id="scalooper_ti_test_settings">
			<?php
				submit_button();
			?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register and add settings
	 */
	public function page_init() {
		register_setting(
			'scalooper_ti_option_group',
			'scalooper_ti_option',
			array( $this, 'sanitize' )
		);

		add_settings_section(
			'setting_section_id',
			'Matomo',
			array( $this, 'print_section_info' ),
			'scalooper-perfmon-setting-admin'
		);

		add_settings_field(
			'matomo_site_id',
			'Matomo Site Id',
			array( $this, 'matomo_form_input_site_id' ),
			'scalooper-perfmon-setting-admin',
			'setting_section_id'
		);

		add_settings_field(
			'matomo_api_url',
			'Matomo API URL',
			array( $this, 'matomo_form_input_api_url' ),
			'scalooper-perfmon-setting-admin',
			'setting_section_id'
		);

		add_settings_field(
			'matomo_api_key',
			'Matomo API Key',
			array( $this, 'matomo_form_input_api_key' ),
			'scalooper-perfmon-setting-admin',
			'setting_section_id'
		);
	}

	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $input Contains all settings fields as array keys.
	 */
	public function sanitize( $input ) {
		$new_input = array();
		if ( isset( $input['matomo_site_id'] ) ) {
			$new_input['matomo_site_id'] = absint( $input['matomo_site_id'] );
		}

		if ( isset( $input['matomo_api_url'] ) ) {
			$new_input['matomo_api_url'] = esc_url_raw( $input['matomo_api_url'], array( 'http', 'https' ) );
		}

		if ( isset( $input['matomo_api_key'] ) ) {
			$new_input['matomo_api_key'] = sanitize_text_field( $input['matomo_api_key'] );
		}

		return $new_input;
	}

	/**
	 * Print the Section text
	 */
	public function print_section_info() {
		if ( is_multisite() ) {
			$network_option = get_site_option(
				'scalooper_ti_site_option',
				array(
					'matomo_api_url'   => '',
					'matomo_api_key'   => '',
					'matomo_overwrite' => 0,
				)
			);

			?>
		<p>
			<strong>Network Settings:</strong><br>
			<strong>Matomo API URL: <?php echo ! empty( $network_option['matomo_api_url'] ) ? esc_html( $network_option['matomo_api_url'] ) : 'leer'; ?></strong> <br>
			<strong>Matomo API Key: <?php echo ( ! empty( $network_option['matomo_api_key'] ) ? esc_html( substr( $network_option['matomo_api_key'], 0, 2 ) . '**************' . substr( $network_option['matomo_api_key'], -2 ) ) : 'leer' ); ?></strong>

		</p>
			<p>Adjust your settings here:</p>
			<?php
		}
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function matomo_form_input_site_id() {
		printf(
			'<input type="number" id="scalooper_ti_matomo_site_id" name="scalooper_ti_option[matomo_site_id]" value="%s" />',
			isset( $this->options['matomo_site_id'] ) ? esc_attr( $this->options['matomo_site_id'] ) : ''
		);
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function matomo_form_input_api_url() {
		printf(
			'<input type="text" id="scalooper_ti_matomo_api_url" name="scalooper_ti_option[matomo_api_url]" value="%s" />',
			isset( $this->options['matomo_api_url'] ) ? esc_attr( $this->options['matomo_api_url'] ) : ''
		);
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function matomo_form_input_api_key() {
		printf(
			'<input type="password" id="scalooper_ti_matomo_api_key" name="scalooper_ti_option[matomo_api_key]" value="%s" />',
			isset( $this->options['matomo_api_key'] ) ? esc_attr( $this->options['matomo_api_key'] ) : ''
		);
	}

	/**
	 * Add multisite menu
	 *
	 * @return void
	 */
	public function network_settings_pages() {
		add_menu_page(
			'Scalooper',
			'Scalooper',
			'manage_network_options',
			'scalooper',
			array( $this, 'network_menu_page_main' ),
			'dashicons-analytics'
		);
		add_submenu_page(
			'scalooper',
			'Scalooper Traffic Insights',
			'Traffic Insights',
			'manage_network_options',
			'scalooper_real_server_side_tracking_setting',
			array( $this, 'network_menu_page_tracking_setting' )
		);
	}

	/**
	 * Info page
	 *
	 * @return void
	 */
	public function network_menu_page_main() {
		?>
			<div class="wrap">
				<h1>Scalooper, a brand of MBmedien Group GmbH</h1>
				<h3>Produkte:</h3><br>
				<strong>Scalooper Traffic Insights</strong>
				<p>
					Scalooper Traffic Insights allows you to collect web analytics data based on server log files, JavaScript and/or cookies. The data is mapped to your own Matomo account via API.
					Thanks to this triple tracking, you have significantly more data at your disposal than with conventional methods. Even if users block cookies and JavaScript, you can still make use of the server access data.
					In a multi-site environment, you can configure the plugin centrally for all sites as well as separately for each site.
					For more information, please visit our website: <a href="https://scalooper.de/" target="_blank" >https://scalooper.de/</a>.

				</p>
			</div>
		<?php
	}

	/**
	 * Create multisite setting page
	 *
	 * @return void
	 */
	public function network_menu_page_tracking_setting() {

		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}

		if ( isset( $_POST['scalooper_real_server_side_tracking_setting'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scalooper_real_server_side_tracking_setting'] ) ), 'scalooper_real_server_side_tracking_setting' ) ) {

			$matomo_overwrite = 0;
			if ( isset( $_POST['network_option_matomo_overwrite'] ) && 1 == $_POST['network_option_matomo_overwrite'] ) {
				$matomo_overwrite = 1;
			}

			update_site_option(
				'scalooper_ti_site_option',
				array(
					'matomo_api_url'   => esc_url_raw( wp_unslash( $_POST['network_option_matomo_api_url'] ?? '' ), array( 'http', 'https' ) ),
					'matomo_api_key'   => sanitize_text_field( wp_unslash( $_POST['network_option_matomo_api_key'] ?? '' ) ),
					'matomo_overwrite' => $matomo_overwrite,
				)
			);

			add_settings_error( 'scalooper_real_server_side_tracking_setting', 'settings_updated', 'Settings saved.', 'updated' );
		}

		$network_option = get_site_option(
			'scalooper_ti_site_option',
			array(
				'matomo_api_url'   => '',
				'matomo_api_key'   => '',
				'matomo_overwrite' => 0,
			)
		);

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors( 'scalooper_real_server_side_tracking_setting' ); ?>
			<p>Adjust the configuration of Scalooper Traffic Insights. If you are running a multi-site environment. you may define
				global settings that override the specific settings for gour individual Sites.</p>
			<form action="" method="post">
				<?php wp_nonce_field( 'scalooper_real_server_side_tracking_setting', 'scalooper_real_server_side_tracking_setting' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="network_option_matomo_api_url">Matomo API URL</label></th>
						<td><input name="network_option_matomo_api_url" type="text" id="network_option_matomo_api_url" value="<?php echo esc_attr( $network_option['matomo_api_url'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="network_option_matomo_api_key">Matomo API Key</label></th>
						<td><input name="network_option_matomo_api_key" type="text" id="network_option_matomo_api_key" value="<?php echo esc_attr( $network_option['matomo_api_key'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<td colspan="3"><label><input value="1" type="checkbox" name="network_option_matomo_overwrite"  id="network_option_matomo_overwrite" <?php echo checked( $network_option['matomo_overwrite'], 1, false ); ?>> Override individual Site Settings?</label></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

