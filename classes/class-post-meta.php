<?php

defined( 'ABSPATH' ) || exit;

class InstaWP_Post_Meta {

	public $id = 'post_meta';

	public function __construct() {
		// Add Settings.
		add_filter( 'instawp/filters/2waysync/event_providers', array( $this, 'add_provider' ) );

		// Post Meta Actions.
		add_action( 'add_post_meta', array( $this, 'add_post_meta' ), 10, 3 );
		add_action( 'update_post_meta', array( $this, 'update_post_meta' ), 10, 4 );

		// Process Events.
		add_filter( 'instawp/filters/2waysync/process_event', array( $this, 'process_event' ), 10, 2 );
	}

	public function add_provider( $providers ) {
		$providers[] = array(
			'id'      => $this->id,
			'title'   => __( 'Post Meta', 'iwp-2waysync-sample-plugin' ),
			'tooltip' => __( 'Enabling this option will allow plugin to log events related to all posts, pages and custom post types meta.', 'iwp-2waysync-sample-plugin' ),
			'default' => 'on', // Can be on/off, 'off' is default.
		);

		return $providers;
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

		if ( in_array( $meta_key, array( '_edit_lock', 'instawp_event_sync_reference_id' ), true ) ) {
			return;
		}

		$post          = get_post( $object_id );
		$reference_id  = InstaWP_Sync_Helpers::get_post_reference_id( $object_id ); // See InstaWP Connect plugin for details.
		$singular_name = InstaWP_Sync_Helpers::get_post_type_name( $post->post_type ); // See InstaWP Connect plugin for details.
		$event         = [
			'name'  => sprintf( __( '%s meta updated', 'iwp-2waysync-sample-plugin' ), $singular_name ), // Event name.
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

		// Pass $event object, $reference_id and unique identifier for event to be recorded. 
		do_action( 'instawp/actions/2waysync/record_event', $event, $reference_id, $this->id );
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

		if ( in_array( $meta_key, [ '_edit_lock', 'instawp_event_sync_reference_id' ], true ) ) {
			return;
		}

		$post          = get_post( $object_id );
		$reference_id  = InstaWP_Sync_Helpers::get_post_reference_id( $object_id ); // See InstaWP Connect plugin for details.
		$singular_name = InstaWP_Sync_Helpers::get_post_type_name( $post->post_type ); // See InstaWP Connect plugin for details.
		$event         = [
			'name'  => sprintf( __( '%s meta updated', 'iwp-2waysync-sample-plugin' ), $singular_name ), // Event name.
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

		// Pass $event object, $reference_id and unique identifier for event to be recorded. 
		do_action( 'instawp/actions/2waysync/record_event', $event, $reference_id, $this->id );
	}

	/**
	 * Process Events.
	 *
	 * @param array $response
	 * @param array $event
	 * @return array
	 */
	public function process_event( $response, $event ) {
		$reference_id = $event->reference_id;
		$details      = InstaWP_Sync_Helpers::object_to_array( $event->details );

		// create and update
		if ( in_array( $event->event_slug, array( 'post_meta_added', 'post_meta_updated' ), true ) ) {
			$wp_post              = $details['post'];
			$post_by_reference_id = InstaWP_Sync_Helpers::get_post_by_reference( $wp_post['post_type'], $reference_id, $wp_post['post_name'] );
			
			if ( ! empty( $post_by_reference_id ) ) {
				if ( $event->event_slug === 'post_meta_added' ) {
					add_post_meta( $post_by_reference_id->ID, $details['meta_key'], maybe_unserialize( $details['meta_value'] ) );
				} else {
					update_post_meta( $post_by_reference_id->ID, $details['meta_key'], maybe_unserialize( $details['meta_value'] ) );
				}
			}

			return InstaWP_Sync_Helpers::sync_response( $data, [], [] );
		}

		return $response;
	}
}

new InstaWP_Post_Meta();
