/**
 * Nexura Upload Notice — Adds a beautiful "Increase Limit" badge
 * next to the WordPress media uploader max upload size text.
 * Runs on all admin pages so it works in media popups everywhere.
 */
( function ( $ ) {
	'use strict';

	if ( typeof nexura_upload_notice === 'undefined' || ! nexura_upload_notice.settings_url ) {
		return;
	}

	var badgeHtml =
		'<a href="' + nexura_upload_notice.settings_url + '" ' +
		'class="nexura-increase-badge" ' +
		'title="' + nexura_upload_notice.label_tooltip + '" ' +
		'style="' +
			'display:inline-flex;' +
			'align-items:center;' +
			'gap:4px;' +
			'margin-left:8px;' +
			'padding:4px 11px;' +
			'background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);' +
			'color:#fff;' +
			'border-radius:20px;' +
			'font-size:11px;' +
			'font-weight:600;' +
			'text-decoration:none;' +
			'letter-spacing:0.3px;' +
			'box-shadow:0 2px 8px rgba(99,102,241,0.45);' +
			'vertical-align:middle;' +
			'line-height:1.4;' +
			'transition:all 0.2s ease;' +
		'" ' +
		'onmouseover="this.style.transform=\'translateY(-1px) scale(1.04)\';this.style.boxShadow=\'0 5px 14px rgba(99,102,241,0.65)\';" ' +
		'onmouseout="this.style.transform=\'translateY(0) scale(1)\';this.style.boxShadow=\'0 2px 8px rgba(99,102,241,0.45)\';"' +
		'>' +
		'&#9889; ' + nexura_upload_notice.label_btn +
		'</a>';

	/**
	 * Inject badge whenever .max-upload-size element appears.
	 * Uses MutationObserver (fast, no polling) + immediate check.
	 */
	function injectBadge() {
		$( '.max-upload-size' ).each( function () {
			if ( $( this ).find( '.nexura-increase-badge' ).length === 0 ) {
				$( this ).append( badgeHtml );
			}
		} );
	}

	// Run once on DOM ready
	$( document ).ready( function () {
		injectBadge();

		// Watch for dynamic content (media modal opens later)
		if ( window.MutationObserver ) {
			var observer = new MutationObserver( function ( mutations ) {
				mutations.forEach( function ( mutation ) {
					if ( mutation.addedNodes.length ) {
						injectBadge();
					}
				} );
			} );
			observer.observe( document.body, { childList: true, subtree: true } );
		}
	} );

} )( jQuery );
