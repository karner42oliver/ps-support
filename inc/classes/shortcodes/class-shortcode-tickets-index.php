<?php

class PSource_Support_Tickets_Index_Shortcode extends PSource_Support_Shortcode {
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'process_form' ) );
		if ( !is_admin() ) {
			add_shortcode( 'support-system-tickets-index', array( $this, 'render' ) );
		}
	}

	public function process_form() {
		$this->maybe_handle_ticket_details_update();
		$this->maybe_handle_ticket_close_update();
		$this->maybe_handle_front_reply_submission();
	}

	protected function maybe_handle_ticket_details_update() {
		if ( ! isset( $_POST['submit-ticket-details'] ) || ! psource_support_current_user_can( 'update_ticket' ) ) {
			return;
		}

		$ticket_id = absint( $_POST['ticket_id'] );
		$ticket = psource_support_get_ticket( $ticket_id );
		if ( ! $ticket ) {
			return;
		}

		$action = 'submit-ticket-details-' . $ticket_id;
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], $action ) )
			wp_die( __( 'Sicherheitsüberprüfungsfehler', 'psource-support' ) );

		$args = array();

		$category_id = psource_support_get_valid_ticket_category_id( $_POST['ticket-cat'] );
		if ( $category_id ) {
			$args['cat_id'] = $category_id;
		}

		$priority = psource_support_get_valid_ticket_priority( $_POST['ticket-priority'] );
		if ( $priority !== false ) {
			$args['ticket_priority'] = $priority;
		}

		$admin_id = psource_support_get_valid_ticket_admin_id( isset( $_POST['ticket-staff'] ) ? wp_unslash( $_POST['ticket-staff'] ) : '' );
		if ( $admin_id !== false ) {
			$args['admin_id'] = $admin_id;
		}

		$result = psource_support_update_ticket( $ticket_id, $args );
		if ( $result ) {
			$url = add_query_arg( 'ticket-details-updated', 'true' );
			wp_redirect( $url );
			exit;
		}
	}

	protected function maybe_handle_ticket_close_update() {
		if ( ! isset( $_POST['submit-close-ticket'] ) || ! psource_support_current_user_can( 'close_ticket', psource_support_get_the_ticket_id() ) ) {
			return;
		}

		$ticket_id = absint( $_POST['ticket_id'] );
		$ticket = psource_support_get_ticket( $ticket_id );
		if ( ! $ticket ) {
			return;
		}

		$action = 'submit-close-ticket-' . $ticket_id;
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], $action ) )
			wp_die( __( 'Sicherheitsüberprüfungsfehler', 'psource-support' ) );

		if ( empty( $_POST['close-ticket'] ) )
			psource_support_restore_ticket_previous_status( $ticket_id );
		else
			psource_support_close_ticket( $ticket_id );

		$url = add_query_arg( 'ticket-closed-updated', 'true' );
		wp_redirect( $url );
		exit;
	}

	protected function maybe_handle_front_reply_submission() {
		if ( ! isset( $_POST['support-system-submit-reply'] ) || ! psource_support_current_user_can( 'insert_reply' ) ) {
			return;
		}

		$fields = array_map( 'absint', $_POST['support-system-reply-fields'] );
		$ticket_id = $fields['ticket'];
		$user_id = $fields['user'];
		$blog_id = $fields['blog'];

		$action = 'support-system-submit-reply-' . $ticket_id . '-' . $user_id . '-' . $blog_id;
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], $action ) )
			wp_die( __( 'Sicherheitsüberprüfungsfehler', 'psource-support' ) );

		$message = isset( $_POST['support-system-reply-message'] ) ? wp_kses_post( wp_unslash( $_POST['support-system-reply-message'] ) ) : '';
		if ( empty( $message ) )
			wp_die( __( 'Die Antwortnachricht darf nicht leer sein', 'psource-support' ) );

		$ticket = psource_support_get_ticket( $ticket_id );
		if ( ! $ticket )
			wp_die( __( 'Das Ticket existiert nicht', 'psource-support' ) );

		if ( $user_id != get_current_user_id() )
			wp_die( __( 'Sicherheitsüberprüfungsfehler', 'psource-support' ) );

		$args = array(
			'poster_id' => get_current_user_id(),
			'message' => $message,
		);

		$attachments = psource_support_get_uploaded_attachment_urls( isset( $_FILES['support-attachment'] ) ? $_FILES['support-attachment'] : array() );
		if ( is_wp_error( $attachments ) ) {
			$error_message = '<ul>';
			foreach ( $attachments->get_error_messages() as $message ) {
				$error_message .= '<li>' . $message . '</li>';
			}
			$error_message .= '</ul>';
			wp_die( $error_message );
		}

		if ( ! empty( $attachments ) ) {
			$args['attachments'] = $attachments;
		}

		$result = psource_support_insert_ticket_reply( $ticket_id, $args );
		if ( ! $result )
			wp_die( __( 'Bei der Bearbeitung des Formulars ist ein Fehler aufgetreten. Bitte versuche es später erneut', 'psource-support' ) );

		$ticket = psource_support_get_ticket( $ticket_id );
		$status = psource_support_get_ticket_status_after_reply( $ticket, get_current_user_id() );

		if ( $status != $ticket->ticket_status )
			psource_support_ticket_transition_status( $ticket->ticket_id, $status );

		$url = add_query_arg( 'support-system-reply-added', 'true' );
		$url = preg_replace( '/\#[a-zA-Z0-9\-]*$/', '', $url );
		$url .= '#support-system-reply-' . $result;
		wp_safe_redirect( $url );
		exit;
	}

	public function render( $atts ) {
		$this->start();

		if ( ! psource_support_current_user_can( 'read_ticket' ) ) {
			if ( ! is_user_logged_in() )
				$message = sprintf( __( 'Du musst <a href="%s">angemeldet</a> sein, um Support zu erhalten', 'psource-support' ), wp_login_url( get_permalink() ) );
			else
				$message = __( 'Du hast nicht genügend Berechtigungen, um Unterstützung zu erhalten', 'psource-support' );
			
			$message = apply_filters( 'support_system_not_allowed_tickets_list_message', $message, 'ticket-index' );
			?>
				<div class="support-system-alert warning">
					<?php echo $message; ?>
				</div>
			<?php
			return $this->end();
		}

		if ( psource_support_is_tickets_page() )
			psource_support_get_template( 'index', 'tickets' );
		elseif ( psource_support_is_single_ticket() )
			psource_support_get_template( 'single', 'ticket' );

		return $this->end();
	}
}