<?php namespace Dev7EmailSync\Integrations;

use Dev7EmailSync\Integration;

class Mailchimp implements Integration {

	private $mc;
	private $metaKey = 'd7es_mailchimp';
	protected $apiKey;
	protected $listId;

	public function __construct( $options )
	{
		$this->apiKey = isset( $options['api_key'] ) ? $options['api_key'] : '';
		$this->listId = isset( $options['list_id'] ) ? $options['list_id'] : '';

		try {
			$this->mc = new \Mailchimp( $this->apiKey );
		} catch ( \Exception $e ) {}
	}

	public function is_subscribed( $user_id )
	{
		$user = get_userdata( $user_id );
		$email = $user->user_email;

		$subscriber_uid = get_user_meta( $user_id, $this->metaKey, true );
		if ( !$subscriber_uid ) {
			return false;
		}

		try {
			$resp = $this->mc->lists->memberInfo( $this->listId, array(array( 'leid' => $subscriber_uid )) );
			if ( isset( $resp['data'][0]['status'] ) && $resp['data'][0]['status'] == 'subscribed' ) {
				return true;
			}
		} catch ( \Exception $e ) {}

		return false;
	}

	public function subscribe( $user_id )
	{
		$user = get_userdata( $user_id );
		$email = $user->user_email;

		try {
			$resp = $this->mc->lists->subscribe( $this->listId, array( 'email' => $email ) );
			if ( isset( $resp['leid'] ) && $resp['leid'] ) {
				update_user_meta( $user_id, $this->metaKey, $resp['leid'] );
				return true;
			}
		} catch ( \Exception $e ) {}

		return false;
	}

	public function update_subscription( $user_id )
	{
		$user = get_userdata( $user_id );
		$email = $user->user_email;

		if ( !$this->is_subscribed( $user_id ) ) {
			return $this->subscribe( $user_id );
		}

		try {
			$subscriber_uid = get_user_meta( $user_id, $this->metaKey, true );
			$resp = $this->mc->lists->updateMember( $this->listId, array( 'leid' => $subscriber_uid ), array(
				'EMAIL' => $email
			) );
		} catch ( \Exception $e ) {}

		return false;
	}

	public function unsubscribe( $user_id )
	{
		$user = get_userdata( $user_id );
		$email = $user->user_email;

		try {
			$resp = $this->mc->lists->unsubscribe( $this->listId, array( 'email' => $email ) );
			if ( isset( $resp['complete'] ) && $resp['complete'] ) {
				delete_user_meta( $user_id, $this->metaKey );
				return true;
			}
		} catch ( \Exception $e ) {}

		return false;
	}

	public function settings_init()
	{
		add_settings_section(
			'dev7es_mailchimp_settings',
			__( 'Mailchimp Settings', 'dev7-email-sync' ),
			array( $this, 'settings_section_info' ),
			'dev7-email-sync'
		);

		add_settings_field(
			'api_key',
			__( 'API Key', 'dev7-email-sync' ),
			array( $this, 'setting_api_key' ),
			'dev7-email-sync',
			'dev7es_mailchimp_settings'
		);

		add_settings_field(
			'list_id',
			'List ID',
			array( $this, 'setting_list_id' ),
			'dev7-email-sync',
			'dev7es_mailchimp_settings'
		);
	}

	public function settings_section_info()
	{
		echo '<p>'. sprintf(
				'%s <a href="%s" target="_blank">%s</a> %s',
				__( 'Enter your', 'dev7-email-sync' ),
				'http://mailchimp.com',
				__( 'Mailchimp', 'dev7-email-sync' ),
				__( 'settings below to sync with a Mailchimp list.', 'dev7-email-sync' )
			) .'</p>';
	}

	public function setting_api_key()
	{
		$options = get_option( 'dev7_email_sync_settings' );
		$value = isset( $options['api_key'] ) ? esc_attr( $options['api_key'] ) : '';
		echo '<input type="text" id="api_key" name="dev7_email_sync_settings[api_key]" value="'. $value .'" />';
		echo '<p class="description">'. __( 'Can be found in Mailchimp: Account &gt; Extras &gt; API keys', 'dev7-email-sync' ) .'</p>';
	}

	public function setting_list_id()
	{
		$options = get_option( 'dev7_email_sync_settings' );
		$value = isset( $options['list_id'] ) ? esc_attr( $options['list_id'] ) : '';
		echo '<input type="text" id="list_id" name="dev7_email_sync_settings[list_id]" value="'. $value .'" />';
		echo '<p class="description">'. __( 'Can be found in Mailchimp: List Settings &gt; List name and Campaign defaults', 'dev7-email-sync' ) .'</p>';
	}

	public function sanitize( $input )
	{
		$input['api_key'] = sanitize_text_field( $input['api_key'] );
		$input['list_id'] = sanitize_text_field( $input['list_id'] );

		return $input;
	}

}