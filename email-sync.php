<?php

/**
 * Plugin Name: Email Sync
 * Plugin URI:  https://github.com/Dev7studios/Email-Sync
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
		add_action( 'personal_options', array( $this, 'personal_options' ) );
		add_action( 'personal_options_update', array( $this, 'edit_user_profile_update' ) );
		add_action( 'edit_user_profile_update', array( $this, 'edit_user_profile_update' ) );
		add_filter( 'manage_users_columns', array( $this, 'manage_users_columns' ) );
		add_action( 'manage_users_custom_column',  array( $this, 'manage_users_custom_column' ), 10, 3 );
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

		delete_transient( 'dev7es_'. $user_id .'_subscribed' );
	}

	public function update_subscriber( $user_id )
	{
		if ( !$this->integration ) return;

		$this->integration->update_subscription( $user_id );

		delete_transient( 'dev7es_'. $user_id .'_subscribed' );
	}

	public function unsubscribe_user( $user_id )
	{
		if ( !$this->integration ) return;

		$this->integration->unsubscribe( $user_id );

		delete_transient( 'dev7es_'. $user_id .'_subscribed' );
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

	public function personal_options( $user )
	{
		if ( !$this->integration ) return;

		// Clear cache when visiting profile page
		delete_transient( 'dev7es_'. $user->ID .'_subscribed' );

		$subscribed = $this->integration->is_subscribed( $user->ID );
		?>
		<tr class="dev7-email-sync">
			<th scope="row"><?php _e( 'Subscribe', 'dev7-email-sync' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><span><?php _e( 'Subscribe', 'dev7-email-sync' ); ?></span></legend>
					<label for="dev7_email_sync_subscribe">
						<input type="hidden" name="dev7_email_sync_subscribe" value="0" />
						<input name="dev7_email_sync_subscribe" type="checkbox" id="dev7_email_sync_subscribe" value="1"<?php checked( $subscribed ); ?> />
						<?php echo apply_filters( 'dev7es_profile_subscribe_label', __( 'Subscribe to receive marketing emails and updates', 'dev7-email-sync' ) ); ?>
						<p class="description"><?php _e( 'When subscribing this will update automatically once your email address is confirmed', 'dev7-email-sync' ); ?></p>
					</label><br />
				</fieldset>
			</td>
		</tr>
		<?php
	}

	public function edit_user_profile_update( $user_id )
	{
		if ( !$this->integration ) return;
		if ( !wp_verify_nonce( $_REQUEST['_wpnonce'], 'update-user_' . $user_id ) ) return;

		$subscribe = isset( $_POST['dev7_email_sync_subscribe'] ) ? absint( $_POST['dev7_email_sync_subscribe'] ) : null;
		if ( $subscribe !== null ) {
			$is_subscribed = $this->integration->is_subscribed( $user_id );

			if ( $subscribe && !$is_subscribed ) {
				$this->integration->subscribe( $user_id );
			}
			if ( !$subscribe && $is_subscribed ) {
				$this->integration->unsubscribe( $user_id );
			}

			delete_transient( 'dev7es_'. $user_id .'_subscribed' );
		}
	}

	public function manage_users_columns( $columns )
	{
		if ( !$this->integration ) return $columns;

		$columns['dev7es_is_subscribed'] = __( 'Subscribed', 'dev7-email-sync' );
		return $columns;
	}

	public function manage_users_custom_column( $value, $column_name, $user_id )
	{
		if ( !$this->integration ) return $value;

		if ( $column_name == 'dev7es_is_subscribed' ) {
			if ( ( $value = get_transient( 'dev7es_'. $user_id .'_subscribed' ) ) === false ) {
				$value = $this->integration->is_subscribed( $user_id ) ? 1 : 0;
				set_transient( 'dev7es_'. $user_id .'_subscribed', $value, 4 * WEEK_IN_SECONDS );
			}

			if ( $value ) {
				$value = '&#10003;';
			} else {
				$value = '-';
			}
		}

		return $value;
	}

}
new Dev7EmailSync();