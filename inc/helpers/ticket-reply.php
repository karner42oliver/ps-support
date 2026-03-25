<?php

function psource_support_get_ticket_reply_table() {
	return psource_support()->model->tickets_messages_table;
}

function psource_support_get_ticket_reply_rows( $ticket_id ) {
	global $wpdb;

	$table = psource_support_get_ticket_reply_table();
	$query = $wpdb->prepare(
		"SELECT * FROM $table
		WHERE ticket_id = %d
		ORDER BY message_id ASC",
		$ticket_id
	);

	return $wpdb->get_results( $query );
}

function psource_support_insert_ticket_reply_row( $insert ) {
	global $wpdb;

	$wpdb->insert(
		psource_support_get_ticket_reply_table(),
		$insert,
		array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
	);

	return (int) $wpdb->insert_id;
}

function psource_support_delete_ticket_reply_row( $reply_id ) {
	global $wpdb;

	return $wpdb->query( $wpdb->prepare( "DELETE FROM " . psource_support_get_ticket_reply_table() . " WHERE message_id = %d", $reply_id ) );
}

function psource_support_get_ticket_replies( $ticket_id ) {
	$_replies = array();

	$ticket = psource_support_get_ticket( $ticket_id );
	if ( ! $ticket )
		return $_replies;
	
	$results = wp_cache_get( 'support-ticket-' . $ticket_id, 'support_system_ticket_replies' );

	if ( $results === false ){
		$results = psource_support_get_ticket_reply_rows( $ticket_id );
		wp_cache_set( 'support-ticket-' . $ticket_id, $results, 'support_system_ticket_replies' );
	}

	if ( $results )
		$_replies = $results;

	$replies = array();
	$i = 0;
	foreach ( $_replies as $_reply ) {
		$reply = psource_support_get_ticket_reply( $_reply );

		if ( $i === 0 )
			$reply->is_main_reply = true;

		$replies[] = $reply;
		$i++;
	}

	$replies = apply_filters( 'support_system_get_ticket_replies', $replies, $ticket_id );
	return $replies;

}

function psource_support_get_ticket_reply( $ticket_reply ) {
	$ticket_reply = PSource_Support_Ticket_Reply::get_instance( $ticket_reply );
	$ticket_reply = apply_filters( 'support_system_get_ticket_reply', $ticket_reply );
	return $ticket_reply;
}

function psource_support_get_uploaded_attachment_urls( $files ) {
	if ( empty( $files ) ) {
		return array();
	}

	$files_uploaded = psource_support_upload_ticket_attachments( $files );
	if ( ! $files_uploaded['error'] ) {
		return ! empty( $files_uploaded['result'] ) ? wp_list_pluck( $files_uploaded['result'], 'url' ) : array();
	}

	$error = new WP_Error();
	foreach ( $files_uploaded['result'] as $message ) {
		$error->add( 'file_upload_error', $message );
	}

	return $error;
}

/**
 * Insert a new reply for a ticket
 * 
 * @param  int $ticket_id
 * @param  array  $args
 * @return int|boolean
 */
function psource_support_insert_ticket_reply( $ticket_id, $args = array() ) {
	global $current_site;

	$current_site_id = ! empty ( $current_site ) ? $current_site->id : 1;

	$ticket = psource_support_get_ticket( absint( $ticket_id ) );

	if ( ! $ticket )
		return false;

	wp_cache_delete( 'support-ticket-' . $ticket_id, 'support_system_ticket_replies' );
	wp_cache_delete( $ticket_id, 'support_system_tickets' );

	$defaults = array(
		'site_id' => $current_site_id,
		'poster_id' => 0,
		'subject' => 'Re: ' . wp_unslash( $ticket->title ),
		'message' => '',
		'message_date' => current_time( 'mysql', 1 ),
		'attachments' => array(),
		'send_emails' => true
	);

	$args = wp_parse_args( $args, $defaults );
	$site_id      = $args['site_id'];
	$poster_id    = $args['poster_id'];
	$subject      = $args['subject'];
	$message      = $args['message'];
	$message_date = $args['message_date'];
	$attachments  = $args['attachments'];
	$send_emails  = $args['send_emails'];

	$message = wp_kses_post( wp_unslash( $message ) );

	$reply_id = psource_support_insert_ticket_reply_row(
		array(
			'site_id' => $site_id,
			'ticket_id' => absint( $ticket_id ),
			'admin_id' => is_super_admin( $poster_id ) ? absint( $poster_id ) : 0,
			'user_id' => is_super_admin( $poster_id ) ? 0 : absint( $poster_id ),
			'subject' => $subject,
			'message' => $message,
			'message_date' => $message_date,
			'attachments' => maybe_serialize( $attachments )
		)
	);

	if ( ! $reply_id )
		return false;

	$reply = psource_support_get_ticket_reply( $reply_id );
	psource_support_recount_ticket_replies( $reply->ticket_id );

	$users_tagged = psource_support_get_ticket_meta( $ticket_id, 'tagged_users', array() );
	if ( ! in_array( $poster_id, $users_tagged ) ) {
		$users_tagged[] = $poster_id;
		psource_support_update_ticket_meta( $ticket_id, 'tagged_users', $users_tagged );
	}

	do_action( 'support_system_insert_ticket_reply', $reply_id, $send_emails );
	do_action( 'support_ticket_reply_added', $reply_id, $ticket_id, $reply );

	return $reply_id;

}


function psource_support_delete_ticket_reply( $reply_id ) {
	global $wpdb, $current_site;

	$ticket_reply = psource_support_get_ticket_reply( $reply_id );
	if ( ! $ticket_reply )
		return false;

	$ticket = psource_support_get_ticket( $ticket_reply->ticket_id );
	if ( ! $ticket )
		return false;

	wp_cache_delete( 'support-ticket-' . $ticket->ticket_id, 'support_system_ticket_replies' );
	wp_cache_delete( $ticket->ticket_id, 'support_system_tickets' );

	$replies = $ticket->get_replies();

	$main_reply = wp_list_filter( $replies, array( 'is_main_reply' => true ) );
	$main_reply = $main_reply[0];
	if ( $main_reply->message_id == $reply_id ) {
		// Do not allow to delete the main reply
		return false;
	}

	psource_support_delete_ticket_reply_row( $reply_id );
	psource_support_recount_ticket_replies( $ticket_reply->ticket_id );

	$old_ticket_reply = $ticket_reply;
	do_action( 'support_system_delete_ticket_reply', $reply_id, $old_ticket_reply );

	return true;
}