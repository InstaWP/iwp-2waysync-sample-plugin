<?php

defined( 'ABSPATH' ) || exit;

class InstaWP_Post_Meta {

	public $id = 'post_meta';

	public function __construct() {
		// Add Settings.
		add_filter( 'INSTAWP_CONNECT/Filters/migrate_settings', array( $this, 'migrate_settings' ), 10, 1 );

		// Set Default Settings.
		add_filter( 'INSTAWP_CONNECT/Filters/default_two_way_sync_settings', array( $this, 'default_settings' ), 10, 2 );

		// Post Meta Actions.
		add_action( 'add_post_meta', array( $this, 'add_post_meta' ), 10, 3 );
		add_action( 'update_post_meta', array( $this, 'update_post_meta' ), 10, 4 );

		// Process Events.
		add_filter( 'INSTAWP_CONNECT/Filters/process_two_way_sync', array( $this, 'parse_event' ), 10, 2 );
	}

	public function migrate_settings( $settings ) {
		$settings['sync_events_settings']['fields'][] = array(
			'id'      => 'instawp_sync_' . $this->id, // instawp_sync_ should be added as prefix to the ID of the field.
			'type'    => 'toggle',
			'title'   => __( 'Posts Meta', 'instawp-connect' ),
			'tooltip' => __( 'Enabling this option will allow plugin to log events related to all posts, pages and custom post types meta.', 'instawp-connect' ),
			'class'   => 'save-ajax',
			'default' => 'off', // be default on, to make by default off, this flag need to set as off as well as the default value filter need to be used.
		);

		return $settings;
	}

	public function default_settings( $settings, $key ) {
		if ( $key === $this->id ) {
			$settings = 'off';
		}

		return $settings;
	}

	/**
	 * Fires immediately before meta of a specific type is added.
	 *
	 * @param int    $object_id  ID of the object metadata is for.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value Metadata value.
	 */
	public function add_post_meta( $object_id, $meta_key, $meta_value ) {
		if ( ! InstaWP_Sync_Helpers::can_sync( $this->id ) ) { // See InstaWP Connect plugin for details.
			return;
		}

		$post          = get_post( $object_id );
		$reference_id  = InstaWP_Sync_Helpers::get_post_reference_id( $object_id ); // See InstaWP Connect plugin for details.
		$singular_name = InstaWP_Sync_Helpers::get_post_type_name( $post->post_type ); // See InstaWP Connect plugin for details.
		$event         = [
			'name'  => sprintf( __( '%s meta updated', 'instawp-connect' ), $singular_name ), // Event name.
			'slug'  => 'post_meta_added', // Event slug i.e. post_meta_added/post_meta_deleted.
			'type'  => $post->post_type, // Event type i.e. Object type i.e. Post/User/Term   .
			'title' => $meta_key, // Event title.
			'data'  => array(
				'object_id'   => $object_id,
				'meta_key'    => $meta_key,
				'meta_value'  => maybe_serialize( $meta_value ),
				'post'        => $post,
			), // Event data.
		];

		// Here we are assuming generating a unique reference id for each event based on the object id.
		// This should be unique and in sync with the reference id in the sync response.
		do_action( 'INSTAWP_CONNECT/Actions/parse_two_way_sync', $event, $reference_id );
	}

	/**
	 * Fires immediately before updating a post's metadata.
	 *
	 * @param int    $meta_id    ID of the metadata entry to update.
	 * @param int    $object_id  ID of the object metadata is for.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value Metadata value.
	 */
	public function update_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( ! InstaWP_Sync_Helpers::can_sync( $this->id ) ) { // See InstaWP Connect plugin for details.
			return;
		}

		$post          = get_post( $object_id );
		$reference_id  = InstaWP_Sync_Helpers::get_post_reference_id( $object_id ); // See InstaWP Connect plugin for details.
		$singular_name = InstaWP_Sync_Helpers::get_post_type_name( $post->post_type ); // See InstaWP Connect plugin for details.
		$event         = [
			'name'  => sprintf( __( '%s meta updated', 'instawp-connect' ), $singular_name ), // Event name.
			'slug'  => 'post_meta_updated', // Event slug i.e. post_meta_added/post_meta_deleted.
			'type'  => $post->post_type, // Event type i.e. Object type i.e. Post/User/Term   .
			'title' => $meta_key, // Event title.
			'data'  => array(
				'meta_id'     => $meta_id,
				'object_id'   => $object_id,
				'meta_key'    => $meta_key,
				'meta_value'  => maybe_serialize( $meta_value ),
				'post'        => $post,
			), // Event data.
		];

		// Here we are assuming generating a unique reference id for each event based on the object id.
		// This should be unique and in sync with the reference id in the sync response.
		do_action( 'INSTAWP_CONNECT/Actions/parse_two_way_sync', $event, $reference_id );
	}

	/**
	 * Process Events.
	 *
	 * @param array $response
	 * @param array $data
	 * @return array
	 */
	public function parse_event( $response, $data ) {
		$reference_id = $data->source_id;
		$details      = InstaWP_Sync_Helpers::object_to_array( $data->details );

		// create and update
		if ( in_array( $data->event_slug, array( 'post_meta_added', 'post_meta_updated' ), true ) ) {
			$wp_post   = isset( $details['post'] ) ? $details['post'] : array();
			$post_name = $wp_post['post_name'];

			$post_by_reference_id = InstaWP_Sync_Helpers::get_post_by_reference( $wp_post['post_type'], $reference_id, $post_name );
			if ( ! empty( $post_by_reference_id ) ) {
				if ( $data->event_slug === 'post_meta_added' ) {
					add_post_meta( $post_by_reference_id->ID, $details['meta_key'], maybe_unserialize( $details['meta_value'] ) );
				} else {
					update_post_meta( $post_by_reference_id->ID, $details['meta_key'], maybe_unserialize( $details['meta_value'] ) );
				}
			}

			return InstaWP_Sync_Helpers::sync_response( $data );
		}

		return $response;
	}
}

new InstaWP_Post_Meta();
