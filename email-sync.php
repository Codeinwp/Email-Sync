<?php

/**
 * Plugin Name: Email Sync
 * Plugin URI:  http://dev7studios.com/email-sync
 * Description: Sync your WordPress email addresses with your favorite email marketing software
 * Version:     1.0.0
 * Author:      Dev7studios
 * Author URI:  http://dev7studios.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: dev7-email-sync
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Dev7EmailSync {

	private $integration;

	public function __construct()
	{
		require 'vendor/autoload.php';

		load_plugin_textdomain( 'dev7-email-sync', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'user_register', array( $this, 'subscribe_user' ) );
		add_action( 'profile_update', array( $this, 'update_subscriber' ) );
		add_action( 'delete_user', array( $this, 'unsubscribe_user' ) );
	}

	public function admin_init()
	{
		register_setting( 'dev7es_option_group', 'dev7_email_sync_settings', array( $this, 'sanitize' ) );

		add_settings_section(
			'dev7es_general_settings',
			__( 'General Settings', 'dev7-email-sync' ),
			array( $this, 'settings_section_info' ),
			'dev7-email-sync'
		);

		add_settings_field(
			'integration',
			__( 'Select Email Provider', 'dev7-email-sync' ),
			array( $this, 'setting_integration' ),
			'dev7-email-sync',
			'dev7es_general_settings'
		);

		$options = get_option( 'dev7_email_sync_settings' );
		$options['integration'] = isset( $options['integration'] ) ? $options['integration'] : null;
		if ( $options['integration'] == 'mailchimp' ) {
			$this->integration = new Dev7EmailSync\Integrations\Mailchimp( $options );
			$this->integration->settings_init();
		}
	}

	public function admin_menu()
	{
		add_options_page(
			__( 'Email Sync Settings', 'dev7-email-sync' ),
			__( 'Email Sync', 'dev7-email-sync' ),
			'manage_options',
			'dev7-email-sync',
			array( $this, 'settings_page' )
		);
	}

	public function subscribe_user( $user_id )
	{
		if ( !$this->integration ) return;

		if ( !$this->integration->is_subscribed( $user_id ) ) {
			$this->integration->subscribe( $user_id );
		}
	}

	public function update_subscriber( $user_id )
	{
		if ( !$this->integration ) return;

		$this->integration->update_subscription( $user_id );
	}

	public function unsubscribe_user( $user_id )
	{
		if ( !$this->integration ) return;

		$this->integration->unsubscribe( $user_id );
	}

	public function settings_page()
	{
		?>
		<div class="wrap">
			<h2><?php _e( 'Email Sync Settings', 'dev7-email-sync' ); ?></h2>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'dev7es_option_group' );
				do_settings_sections( 'dev7-email-sync' );
				submit_button( $this->integration ? __( 'Save Changes', 'dev7-email-sync' ) : __( 'Save & Continue', 'dev7-email-sync' ) );
				?>
			</form>
		</div>
		<?php
	}

	public function sanitize( $input )
	{
		$input['integration'] = sanitize_text_field( $input['integration'] );

		if ( !$this->integration ) return $input;
		return $this->integration->sanitize( $input );
	}

	public function settings_section_info()
	{

	}

	public function setting_integration()
	{
		$options = get_option( 'dev7_email_sync_settings' );
		$options['integration'] = isset( $options['integration'] ) ? esc_attr( $options['integration'] ) : '';
		echo '<select id="integration" name="dev7_email_sync_settings[integration]">
			<option value="">- '. __( 'Select Email Provider', 'dev7-email-sync' ) .' -</option>
			<option value="mailchimp"'. ($options['integration'] == 'mailchimp' ? ' selected="selected"' : '') .'>'. __( 'Mailchimp', 'dev7-email-sync' ) .'</option>
		</select>';
	}

}
new Dev7EmailSync();