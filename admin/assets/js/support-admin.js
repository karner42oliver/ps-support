(function($, window, document, undefined) {
	'use strict';

	var i18n = window.support_system_admin_i18n || {};

	function buildAttachmentsOptions() {
		return {
			attachments: {
				container_selector: '.support-attachments',
				button_text: i18n.attachmentButtonText || 'Dateien hinzufügen...',
				button_class: 'button-secondary',
				remove_file_title: i18n.attachmentRemoveTitle || 'Datei löschen',
				remove_link_class: 'button-secondary',
				remove_link_text: i18n.attachmentRemoveText || 'Datei löschen'
			}
		};
	}

	function initSupportSystem() {
		if ( typeof $.fn.support_system !== 'function' ) {
			return;
		}

		if ( $( '.support-attachments' ).length ) {
			$( '.support-system-admin' ).support_system( buildAttachmentsOptions() );
			return;
		}

		if ( $( '.faq-category-wrap' ).length ) {
			$( '.support-system-admin' ).support_system();
		}
	}

	function initDeleteConfirmation() {
		var message = i18n.deleteConfirm || 'Möchtest Du dieses Ticket wirklich löschen?';

		$( document ).on( 'click', 'span.delete > a', function() {
			return window.confirm( message );
		} );
	}

	function togglePageSelectorButtons() {
		$( '.support-page-selector-wrap' ).each( function() {
			var container = $( this );
			var selectBox = container.find( 'select' ).first();
			var createButton = container.find( '.support-create-page' );
			var viewButton = container.find( '.support-view-page' );

			createButton.hide();
			viewButton.hide();

			if ( ! selectBox.val() ) {
				createButton.css( 'display', 'inline-block' );
			} else {
				viewButton.css( 'display', 'inline-block' );
			}
		} );
	}

	function initFrontSettings() {
		if ( ! $( '#front-options' ).length ) {
			return;
		}

		togglePageSelectorButtons();

		$( document ).on( 'change', '.support-page-selector-wrap select', togglePageSelectorButtons );

		$( document ).on( 'change', 'input[name="activate_front"]', function() {
			$( '#front-options' ).toggleClass( 'disabled', ! $( this ).is( ':checked' ) );
		} );
	}

	$( function() {
		initSupportSystem();
		initDeleteConfirmation();
		initFrontSettings();
	} );
}(jQuery, window, window.document));