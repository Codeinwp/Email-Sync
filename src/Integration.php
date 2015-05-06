<?php namespace Dev7EmailSync;

abstract class Integration {

	abstract public function is_subscribed( $user_id );

	abstract public function subscribe( $user_id );

	abstract public function update_subscription( $user_id );

	abstract public function unsubscribe( $user_id );

	public function settings_init()
	{

	}

	public function sanitize( $input )
	{
		return $input;
	}

	protected function get( $url, $args = array() )
	{
		$response = wp_remote_get( $url, $args );

		if ( !is_wp_error( $response ) ) {
			return wp_remote_retrieve_body( $response );
		}

		return null;
	}

	protected function post( $url, $args = array() )
	{
		$response = wp_remote_post( $url, $args );

		if ( !is_wp_error( $response ) ) {
			return wp_remote_retrieve_body( $response );
		}

		return null;
	}

}