<?php if ( $errors ): ?>
	<?php foreach ( $errors as $error ): ?>
		<div class="error">
			<p><?php echo esc_html( $error['message'] ); ?></p>
		</div>
	<?php endforeach; ?>
<?php endif; ?>

<form method="post" action="">
	<table class="form-table">
		
		<?php ob_start(); ?>
			<input type="checkbox" name="activate_front" value="true" <?php checked( $front_active ); ?>/>
		<?php $this->render_row( __( 'Aktiviere Front-End', 'psource-support' ), ob_get_clean() ); ?>
	</table>
	
	<div id="front-options" class="<?php echo $front_active ? '' : 'disabled'; ?>">
		<table class="form-table">
			
			<?php ob_start(); ?>
				<input type="checkbox" name="use_default_styles" value="true" <?php checked( $use_default_styles ); ?>/>
			<?php $this->render_row( __( 'Verwende Support-Systemstile', 'psource-support' ), ob_get_clean() ); ?>

			<?php if ( is_multisite() ): ?>
				<?php ob_start(); ?>
					<input type="number" class="small-text" value="<?php echo $blog_id; ?>" name="support_blog_id" />
					<span class="description"><?php _e( 'Mit dem Support-System können Tickets auf einer Deiner Webseiten im Front-End angezeigt werden...' ); ?></span>
				<?php $this->render_row( __( 'Blog ID', 'psource-support' ), ob_get_clean() ); ?>
			<?php endif; ?>

			<?php if ( $pages_dropdowns ): ?>
				<?php $this->render_row( __( 'Support Seite', 'psource-support' ), $support_pages_dropdown ); ?>
				<?php $this->render_row( __( 'Ticket-Editor Seite', 'psource-support' ), $submit_ticket_pages_dropdown ); ?>
				<?php $this->render_row( __( 'FAQs Seite', 'psource-support' ), $faqs_pages_dropdown ); ?>
			<?php endif; ?>
		</table>
		

	</div>

	<?php do_action( 'support_sytem_front_settings' ); ?>
		
	<?php $this->render_submit_block(); ?>
</form>
