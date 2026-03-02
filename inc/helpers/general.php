<?php


/**
 * Translate dates
 * 
 * @param string $date The date
 * @return string Date
 */
function psource_support_get_translated_date( $date, $human_read = false ) {
	// get the date from gmt date in Y-m-d H:i:s
	$date_in_gmt = get_date_from_gmt($date);

	if ( $human_read ) {
		$from = mysql2date( 'U', $date_in_gmt, true );
		$transl_date = human_time_diff( $from, current_time( 'timestamp' ) );
	}
	else {
		$format = get_option("date_format") ." ". get_option("time_format");

		//get it localised
		$transl_date = mysql2date( $format, $date_in_gmt, true );
	}

	$transl_date = apply_filters( 'support_system_get_translated_date', $transl_date, $date, $human_read );
	return $transl_date;
}

function psource_support_get_model() {
	return MU_Support_System_Model::get_instance();
}

function psource_support_priority_dropdown( $args = array() ) {
	$defaults = array(
		'name' => 'ticket-priority',
		'id' => false,
		'show_empty' => __( '-- Priorität wählen --', 'psource-support' ),
		'selected' => null,
		'echo' => true
	);
	$args = wp_parse_args( $args, $defaults );

	extract( $args );

	if ( ! $id )
		$id = $name;

	if ( ! $echo )
		ob_start();

	$plugin_class = psource_support();
	$priorities = $plugin_class::$ticket_priority;
	?>
		<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>">
			<?php if ( ! empty( $show_empty ) ): ?>	
				<option value="" <?php selected( $selected === null ); ?>><?php echo esc_html( $show_empty ); ?></option>
			<?php endif; ?>

			<?php foreach ( $priorities as $key => $value ): ?>
				<option value="<?php echo $key; ?>" <?php selected( $selected === $key ); ?>><?php echo esc_html( $value ); ?></option>
			<?php endforeach; ?>

		</select>
	<?php

	if ( ! $echo )
		return ob_get_clean();
}

function psource_support_super_admins_dropdown( $args = array() ) {
	$defaults = array(
		'name' => 'super-admins',
		'id' => false,
		'show_empty' => __( 'Select a staff', 'psource-support' ),
		'selected' => null,
		'echo' => true,
		'value' => 'username' // Or integer
	);
	$args = wp_parse_args( $args, $defaults );

	$plugin = psource_support();
	$super_admins = MU_Support_System::get_super_admins();

	extract( $args );

	if ( ! $id )
		$id = $name;

	if ( ! $echo )
		ob_start();
	?>
		<select name="<?php echo $name; ?>" id="<?php echo $id; ?>">
			<?php if ( ! empty( $show_empty ) ): ?>	
				<option value="" <?php selected( empty( $selected ) ); ?>><?php echo esc_html( $show_empty ); ?></option>
			<?php endif; ?>
			<?php foreach ( $super_admins as $key => $user_name ): ?>
				<?php $user = get_user_by( 'login', $user_name ); ?>
				<?php $option_value = $value === 'username' ? $user_name : $key; ?>
				<?php $option_selected = selected( $selected, $option_value, false ); ?>
				<option value="<?php echo esc_attr( $option_value ); ?>" <?php echo $option_selected; ?>><?php echo $user->display_name; ?></option>
			<?php endforeach; ?>
		</select>
	<?php

	if ( ! $echo )
		return ob_get_clean();
}

function psource_support_assignees_dropdown( $args = array() ) {
	$defaults = array(
		'name' => 'assignee_user_id',
		'id' => false,
		'show_empty' => __( 'Keine', 'psource-support' ),
		'selected' => 0,
		'echo' => true,
	);
	$args = wp_parse_args( $args, $defaults );

	extract( $args );

	if ( ! $id ) {
		$id = $name;
	}

	$selected = absint( $selected );
	$assignees = psource_support_get_available_assignees();

	if ( ! $echo ) {
		ob_start();
	}

	?>
	<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>">
		<?php if ( ! empty( $show_empty ) ) : ?>
			<option value="0" <?php selected( 0, $selected ); ?>><?php echo esc_html( $show_empty ); ?></option>
		<?php endif; ?>

		<?php foreach ( $assignees as $assignee ) : ?>
			<option value="<?php echo esc_attr( absint( $assignee['user_id'] ) ); ?>" <?php selected( absint( $assignee['user_id'] ), $selected ); ?>>
				<?php echo esc_html( $assignee['label'] ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<?php

	if ( ! $echo ) {
		return ob_get_clean();
	}
}

function psource_support_get_available_assignee_user_ids() {
	$assignees = psource_support_get_available_assignees();
	return wp_list_pluck( $assignees, 'user_id' );
}

function psource_support_get_available_assignees() {
	global $wpdb;

	$assignees = array();
	$sync_blog_id = psource_support_get_crm_sync_blog_id();
	$switched = false;

	if ( is_multisite() && $sync_blog_id && get_current_blog_id() !== $sync_blog_id ) {
		switch_to_blog( $sync_blog_id );
		$switched = true;
	}

	$agents_table = $wpdb->prefix . 'smartcrm_agents';
	$roles_table = $wpdb->prefix . 'smartcrm_agent_roles';
	$agents_exists = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $agents_table ) );
	$roles_exists = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $roles_table ) );

	if ( $agents_exists === $agents_table && $roles_exists === $roles_table ) {
		$crm_agents = $wpdb->get_results(
			"SELECT a.user_id, u.display_name, r.role_name
			 FROM $agents_table a
			 INNER JOIN {$wpdb->users} u ON u.ID = a.user_id
			 LEFT JOIN $roles_table r ON r.id = a.role_id
			 WHERE a.status = 'active'
			 ORDER BY u.display_name ASC"
		);

		if ( ! empty( $crm_agents ) ) {
			foreach ( $crm_agents as $agent ) {
				$role_suffix = ! empty( $agent->role_name ) ? ' - ' . $agent->role_name : '';
				$assignees[] = array(
					'user_id' => absint( $agent->user_id ),
					'label' => $agent->display_name . $role_suffix,
				);
			}
		}

		if ( $switched ) {
			restore_current_blog();
		}

		// CRM-Modus: ausschließlich CRM-Agenten der Sync-Site zulassen
		return $assignees;
	}

	if ( $switched ) {
		restore_current_blog();
	}

	$super_admins = MU_Support_System::get_super_admins();
	foreach ( $super_admins as $user_name ) {
		$user = get_user_by( 'login', $user_name );
		if ( ! $user ) {
			continue;
		}

		$assignees[] = array(
			'user_id' => absint( $user->ID ),
			'label' => $user->display_name,
		);
	}

	return $assignees;
}

function psource_support_get_crm_sync_blog_id() {
	if ( ! is_multisite() ) {
		return get_current_blog_id();
	}

	$override_blog_id = absint( psource_support_get_setting( 'psource_support_crm_sync_blog_id' ) );
	if ( $override_blog_id && get_blog_details( $override_blog_id ) ) {
		return $override_blog_id;
	}

	$support_blog_id = absint( psource_support_get_setting( 'psource_support_blog_id' ) );
	if ( $support_blog_id && get_blog_details( $support_blog_id ) ) {
		return $support_blog_id;
	}

	if ( function_exists( 'get_main_site_id' ) ) {
		return (int) get_main_site_id();
	}

	return 1;
}

function psource_support_get_errors($setting = null) {
    global $support_system_errors;

    if (!is_array($support_system_errors) || empty($support_system_errors)) {
        return array();
    }

    if ($setting) {
        $setting_errors = array();
        foreach ($support_system_errors as $details) {
            if (isset($details['setting']) && $setting === $details['setting']) {
                $setting_errors[] = $details;
            }
        }
        return $setting_errors;
    }

    return $support_system_errors;
}

function psource_support_add_error( $setting, $code, $message ) {
	global $support_system_errors;

	$support_system_errors[] = array(
		'setting' => $setting,
		'code'    => $code,
		'message' => $message,
	);
}

function psource_support_get_version() {
	return PSOURCE_SUPPORT_PLUGIN_VERSION;
}


function psource_support_register_main_script() {
	$suffix = '.min';
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG )
		$suffix = '';

	wp_register_script( 'support-system', PSOURCE_SUPPORT_PLUGIN_URL . 'assets/js/support-system' . $suffix . '.js', array( 'jquery' ), psource_support_get_version(), true );

	$l10n = array(
		'ajaxurl' => admin_url( 'admin-ajax.php' )
	);
	wp_localize_script( 'support-system', 'support_system_strings', $l10n );
}

function psource_support_enqueue_main_script() {
	if ( ! wp_script_is( 'support-system', 'registered' ) )
		psource_support_register_main_script();

	wp_enqueue_script( 'support-system' );

}

function psource_support_enqueue_foundation_scripts( $in_footer = true ) {
	$suffix = '.min';
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG )
		$suffix = '';

	wp_enqueue_script( 'support-system-foundation-js', PSOURCE_SUPPORT_PLUGIN_URL . 'assets/js/foundation' . $suffix . '.js', array( 'jquery' ), psource_support_get_version(), $in_footer );
}


/**
 * Adds an integration class to Support System
 *
 * @param $classname The class name of the integrator
 */
function psource_support_add_integrator( $classname ) {
	if ( class_exists( $classname ) ) {
		$plugin = psource_support();
		$r = new ReflectionClass( $classname );
		$plugin->add_integrator( $r->newInstance() );
	}
}

