<?php namespace Dev7EmailSync;

interface Integration {

	public function is_subscribed( $user_id );

	public function subscribe( $user_id );

	public function update_subscription( $user_id );

	public function unsubscribe( $user_id );

	public function settings_init();

	public function sanitize( $input );

}