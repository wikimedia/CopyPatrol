$( document ).ready( function () {
	/** Listeners */
	$('body').tooltip({
		selector: '[data-toggle="tooltip"]'
	});
	$( '.records' ).on( 'click', '.js-save-state', function () {
		saveState( $( this ).data( 'id' ), $( this ).data( 'status' ) );
	} );
	$( '.js-load-more' ).on( 'click', loadMoreResults );

	/**
	 * Save a review
	 * @param id int ID of the record
	 * @param val string Save value 'fixed' or 'false'
	 */
	function saveState( id, val ) {
		var buttonId, unusedButtonId, buttonClass, unusedButtonClass;

		if ( val === 'fixed' ) {
			buttonId = '#success' + id;
			unusedButtonId = '#danger' + id;
			buttonClass = 'success';
			unusedButtonClass = 'danger';
		} else if ( val.toString() === 'false' ) { // needs toString() as jQuery's .data will assume 'false' to be boolean
			buttonId = '#danger' + id;
			unusedButtonId = '#success' + id;
			unusedButtonClass = 'success';
			buttonClass = 'danger';
		}
		$( buttonId ).removeClass( 'btn-' + buttonClass ).addClass( 'btn-' + buttonClass + '-clicked' ).blur();
		$( unusedButtonId ).removeClass( 'btn-' + unusedButtonClass ).addClass( 'btn-secondary' ).prop( 'disabled', 'disabled' ).blur();

		$.ajax( {
			url: 'review/add',
			data: {
				id: id,
				val: val
			},
			dataType: 'json'
		} ).done( function ( ret ) {
			if ( ret.user ) {
				$reviewerNode = $( '.status-div-reviewer-' + id );
				$reviewerNode.find( '.reviewer-link' ).prop( 'href', ret.userpage ).text( ret.user );
				$reviewerNode.find( '.reviewer-timestamp' ).text( ret.timestamp );
				$reviewerNode.fadeIn( 'slow' );
			} else if ( ret.error === 'Unauthorized' ) {
				alert( 'You need to be logged in to be able to review.' );
				$( buttonId ).addClass( 'btn-' + buttonClass ).removeClass( 'btn-' + buttonClass + '-clicked' ).blur();
				$( unusedButtonId ).removeClass( 'btn-secondary' ).prop( 'disabled', false ).addClass( 'btn-' + unusedButtonClass );
			} else {
				alert( 'There was an error in connecting to database.' );
				$( buttonId ).addClass( 'btn-' + buttonClass ).removeClass( 'btn-' + buttonClass + '-clicked' ).blur();
				$( unusedButtonId ).removeClass( 'btn-secondary' ).prop( 'disabled', false ).addClass( 'btn-' + unusedButtonClass );
			}
		} );
	}

	/**
	 * Open the compare pane and do an AJAX request to Copyvios to fetch comparison data
	 * @param id Ithenticate ID of record
	 * @param index Index of the link in the copyvios list for the record
	 * @param copyvio Copyvio URL
	 * @param diffId Oldid of diff
	 */
	function toggleComparePane( id, index, copyvio, diffId ) {
		var compareDiv = '#comp' + id + '-' + index;
		$( compareDiv ).slideToggle( 500 );
		if ( !$( compareDiv ).hasClass( 'copyvios-fetched' ) ) {
			$.ajax( {
				type: 'GET',
				url: 'https://tools.wmflabs.org/copyvios/api.json',
				data: {
					oldid: diffId,
					url: copyvio,
					action: 'compare',
					project: 'wikipedia',
					lang: 'en',
					format: 'json',
					detail: 'true'
				},
				dataType: 'json',
				jsonpCallback: 'callback'
			} ).done( function ( ret ) {
				console.log( 'XHR Success' );
				if ( ret.detail ) {
					// Add a class to the compare panel once we fetch the details to avoid making repetitive API requests
					$( compareDiv ).find( '.compare-pane-left' ).html( ret.detail.article );
					$( compareDiv ).find( '.compare-pane-right' ).html( ret.detail.source );
				} else {
					$( compareDiv ).find( '.compare-pane-left' ).html( '<span class="text-danger">Error! API returned no data.</span>' );
					$( compareDiv ).find( '.compare-pane-right' ).html( '<span class="text-danger">Error! API returned no data.</span>' );
				}
				$( compareDiv ).addClass( 'copyvios-fetched' );
			} );
		}
	}


	function loadMoreResults() {
		$( '#btn-load-more' ).text( '' ).addClass( 'btn-loading' );
		var lastId = $( '.ithenticate-id:last' ).text();
		console.log( lastId );
		$.ajax( {
			url: 'loadmore',
			data: {
				lastId: lastId
			}
		} ).done( function ( ret ) {
			$( '#btn-load-more' ).text( 'Load More' ).removeClass( 'btn-loading' );
			$newRecords = $( ret ).find( '.record-container' );
			console.log( $newRecords );
			$( '.record-container' ).append( $newRecords.html() );
		} );
	}
} );
