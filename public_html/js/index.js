(function ( $, document, window ) {
	'use strict';

	$( document ).ready( function () {
		/** Listeners */
		$( 'body' ).tooltip( {
			selector: '[data-toggle="tooltip"]'
		} );
		$( '.records' ).on( 'click', '.js-save-state', function () {
			var status = $( this ).data( 'status' ),
				id = $( this ).data( 'id' ),
				reviewFn;

			$( this ).addClass( 'loading' );

			// undo review if they click on the button with the same status as the record
			if ( status === $( '.record-' + id ).data( 'status' ) ) {
				reviewFn = undoReview;
			} else {
				reviewFn = saveReview;
			}

			// perform review action then cleanup
			reviewFn( id, status, function () {
				document.activeElement.blur(); // remove focus from button
				$( this ).removeClass( 'loading' );
			}.bind( this ) );
		} );
		$( '.records' ).on( 'click', '.js-compare-button', function () {
			// pass the dataset of the element as an object to toggleComparePane
			toggleComparePane.call( this, this.dataset );
		} );
		$( '.js-load-more' ).on( 'click', loadMoreResults );

		/**
		 * Save a review
		 * @param id int ID of the record
		 * @param val string Save value 'fixed' or 'false'
		 * @param cb function callback
		 */
		function saveReview( id, val, cb ) {
			// update styles before AJAX to make it seem more responsive
			setReviewState( id, val );

			$.ajax( {
				url: location.pathname + '/review/add',
				data: {
					id: id,
					val: val
				},
				dataType: 'json'
			} ).done( function ( ret ) {
				if ( ret.user ) {
					var $reviewerNode = $( '.status-div-reviewer-' + id );
					$reviewerNode.find( '.reviewer-link' ).prop( 'href', ret.userpage ).text( ret.user );
					$reviewerNode.find( '.reviewer-timestamp' ).text( ret.timestamp );
					$reviewerNode.fadeIn( 'slow' );
				} else if ( ret.error === 'Unauthorized' ) {
					window.alert( 'You need to be logged in to be able to review.' );
					// go back to initial state
					setReviewState( id, 'open' );
				} else {
					window.alert( 'There was an error in connecting to database.' );
					setReviewState( id, 'open' );
				}

				cb();
			} );
		}

		/**
		 * Undo a review
		 * @param id int ID of the record
		 * @param oldStatus string current review state of the record
		 * @param cb function callback
		 */
		function undoReview( id, oldStatus, cb ) {
			$.ajax( {
				url: location.pathname + '/review/undo',
				data: {
					id: id
				},
				dataType: 'json'
			} ).done( function ( ret ) {
				if ( ret.user ) {
					var $reviewerNode = $( '.status-div-reviewer-' + id );
					$reviewerNode.fadeOut( 'slow' );
					setReviewState( id, 'open' );
				} else if ( ret.error === 'false' ) {
					window.alert( 'There was an error in connecting to database.' );
					setReviewState( id, oldStatus ); // revert back to old state
				} else {
					window.alert( 'You can only undo your own reviews.' );
					setReviewState( id, oldStatus );
				}

				cb();
			} );
		}

		/**
		 * Set the CSS class of the record in view, which updates the appearance of the review buttons
		 * @param id int ID of the record
		 * @param state string record state, must be 'open', 'fixed' or 'false'
		 */
		function setReviewState( id, state ) {
			$( '.record-' + id )
				.removeClass( 'record-status-open' )
				.removeClass( 'record-status-false' )
				.removeClass( 'record-status-fixed' )
				.addClass( 'record-status-' + state )
				.data( 'status', state );
		}

		/**
		 * Load more results to the page when 'Load More' is clicked
		 */
		function loadMoreResults() {
			$( '#btn-load-more' ).text( '' ).addClass( 'btn-loading' );
			var lastId = $( '.ithenticate-id:last' ).text();
			$.ajax( {
				url: location.pathname + '/loadmore',
				data: {
					lastId: lastId,
					filter: $( 'input[name=filter]:checked' ).val()
				}
			} ).done( function ( ret ) {
				$( '#btn-load-more' ).text( 'Load More' ).removeClass( 'btn-loading' );
				var $newRecords = $( ret ).find( '.record-container' );

				if ( $newRecords.find( '.js-record' ).length ) {
					$( '.record-container' ).append( $newRecords.html() );
				} else {
					$( '.js-load-more' ).replaceWith( '<p>No more records!</p>' );
				}
			} ).fail( function () {
				alert( 'An unknown error occurred when loading results. Please try again.' );
				$( '#btn-load-more' ).text( 'Load More' ).removeClass( 'btn-loading' );
			} );
		}

		/**
		 * Open the compare pane and do an AJAX request to Copyvios to fetch comparison data
		 * @oaram object params a hash of the necessary params, should include:
		 *   integer id Ithenticate ID of record
		 *   integer index Index of the link in the copyvios list for the record
		 *   string copyvio Copyvio URL
		 *   integer diffid Oldid of diff
		 */
		function toggleComparePane( params ) {
			var compareDiv = '#comp' + params.id + '-' + params.index;

			$( compareDiv ).slideToggle( 500 );
			if ( !$( compareDiv ).hasClass( 'copyvios-fetched' ) ) {
				$.ajax( {
					type: 'GET',
					url: 'https://tools.wmflabs.org/copyvios/api.json',
					data: {
						oldid: params.diffid,
						url: params.copyvio,
						action: 'compare',
						project: 'wikipedia',
						lang: 'en',
						format: 'json',
						detail: 'true'
					},
					dataType: 'json',
					jsonpCallback: 'callback'
				} ).always( function ( ret ) { // use always to handle 500s, etc.
					if ( ret.detail ) { // ret.detail means we had success
						// Add a class to the compare panel once we fetch the details to avoid making repetitive API requests
						$( compareDiv ).find( '.compare-pane-left-body' ).html( ret.detail.article );
						$( compareDiv ).find( '.compare-pane-right-body' ).html( ret.detail.source );
					} else {
						// use API-provided error message, otherwise a blanket unknown error message as it could be unrelated to the API
						var errorMessage = ret.error && ret.error.info ? ret.error.info : 'An unknown error occurred.';
						$( compareDiv ).find( '.compare-pane-body' ).html( '<span class="text-danger">' + errorMessage + '</span>' );
					}
					$( compareDiv ).addClass( 'copyvios-fetched' );
				} );
			}
		}
	} );
})( jQuery, document, window );
