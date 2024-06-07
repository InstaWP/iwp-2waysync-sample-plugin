This is the sample plugin for integrating with InstaWP's 2 Way Sync with any 3rd party plugin. 

When you are integrating with 2 way sync, your unique event type(s) can be defined by setting the id in your class. 

In this example, we are adding support for Post Meta. If a Post Meta is added or updated for a particular post, this sample will record the event in the source WordPress site and process the event in the destination WordPress site.

Example class : https://github.com/InstaWP/iwp-2waysync-sample-plugin/blob/main/classes/class-post-meta.php 

#### Settings ID

Set this ID to be a unique identified for your Event type. 

```
$this->id = 'post_meta';
```

#### Adding Toggle for Event

This code will add a toggle to switch ON or OFF your event type to be recorded in 2 way sync. It is recommended to provided this toggle for users. 

`title` will be shown to end users, so make it Human friendly to understand that its connected to your plugin. 

```
add_filter( 'INSTAWP_CONNECT/Filters/migrate_settings', function ( $settings ) {
    $settings['sync_events_settings']['fields'][] = array(
        'id'      => 'instawp_sync_' . $this->id, // instawp_sync_ should be added as prefix to the ID of the field.
        'type'    => 'toggle',
        'title'   => __( 'Term Meta', 'iwp-2waysync-sample-plugin' ),
        'tooltip' => __( 'Enabling this option will allow plugin to log events related to all posts, pages and custom post types meta.', 'iwp-2waysync-sample-plugin' ),
        'class'   => 'save-ajax',
        'default' => 'off', // be default on, to make by default off, this flag need to set as off as well as the default value filter need to be used.
    );

    return $settings;
} );
```

#### Set Default Settings value as 'off' - remove this and shift to wrapper.


```
add_filter( 'INSTAWP_CONNECT/Filters/default_two_way_sync_settings', function ( $settings ) {
    if ( $key === $this->id ) {
        $settings = 'off';
    }

    return $settings;
} );
```

#### Recording Events

This filter will allow you to record an event from your plugin. For example, if you wish to add an event when `add_post_meta` action is triggered. 

```
add_action( 'add_post_meta', function add_post_meta( $object_id, $meta_key, $meta_value ) {

    if ( ! InstaWP_Sync_Helpers::can_sync( $this->id ) ) { // Checking if the toggle is ON.
        return;
    }

    //add event related data into the "event data" array. to be used during processing of the event.
    $post          = get_post( $object_id );
    // Here we are assuming a unique reference id for each event based on the object id.
    // This should be unique and will be used to match content in the destination during sync.
    // We provide some default functions to generate a reference ID, feel free to write your own.
    // For posts -> , For user -> , For term -> . If you use a custom table then its recommended to duplicate one of our provided function and modify it based on your needs. 
    $reference_id  = InstaWP_Sync_Helpers::get_post_reference_id( $object_id ); 
    $singular_name = InstaWP_Sync_Helpers::get_post_type_name( $post->post_type ); // Use this to generate event name. 
    $event         = [
        'name'  => sprintf( __( '%s meta updated', 'iwp-2waysync-sample-plugin' ), $singular_name ), // Event name. Arbitrary text is fine. 
        'slug'  => 'post_meta_added', // Event slug e.g post_meta_added or post_meta_deleted. 
        'type'  => $post->post_type, // Event type e.g Object type i.e. Post/User/Term. Arbitrary text is fine. 
        'title' => $meta_key, // Event title.
        'data'  => array(
            'object_id'   => $object_id,
            'meta_key'    => $meta_key,
            'meta_value'  => maybe_serialize( $meta_value ),
            'post'        => $post,
        ), // Event data.
    ];

    //Pass $event object and $reference_id for event to be recorded. 
    do_action( 'INSTAWP_CONNECT/Actions/parse_two_way_sync', $event, $reference_id );
}, 10, 3 );
```

#### Processing Events

This will be executed on the destination site during the sync operation. It is important for us to know how you want to process the event. 

For example - the post meta event will be processed as below:

```
add_filter( 'INSTAWP_CONNECT/Filters/process_two_way_sync', function process_event( $response, $event ) {
    //$response = empty array to be filled during the processing.
    //$event = event object.
    $reference_id = $event->get_reference_id();
    $details      = InstaWP_Sync_Helpers::object_to_array( $event->details );

    // create or update the post meta based on the event details.
    if ( in_array( $data->event_slug, array( 'post_meta_added', 'post_meta_updated' ), true ) && isset( $details['post'] ) ) { //depends on your implementation. 
        $wp_post   = $details['post'];
        //in the destination wordpress, this code is trying to find the post to be updated. 
        $post_name = $wp_post['post_name']; //slug of the post.
        //this is a sample implementation if the type of the event is "post".
        //it will search by reference_id, then it will fall back to "slug" i.e $post_name.
        $post_by_reference_id = InstaWP_Sync_Helpers::get_post_by_reference( $wp_post['post_type'], $reference_id, $post_name );
        //if we found a post to be updated
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
}, 10, 2 );
```

#### API Reference for 2 Way Sync

1. get_post_reference_id()‎ - Meaning.
2. 
