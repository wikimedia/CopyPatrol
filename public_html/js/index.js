function saveState( id, val ) {
	var buttonId, unusedButtonId, buttonClass, unusedButtonClass;

	if ( val === 'fixed' ) {
		buttonId = '#success' + id;
		unusedButtonId = '#danger' + id;
		buttonClass = 'success';
		unusedButtonClass = 'danger';
	} else if ( val === 'false' ) {
		buttonId = '#danger' + id;
		unusedButtonId = '#success' + id;
		unusedButtonClass = 'success';
		buttonClass = 'danger';
	}
	$( buttonId ).removeClass( 'btn-' + buttonClass ).addClass( 'btn-' + buttonClass + '-clicked' ).blur();
	$( unusedButtonId ).removeClass( 'btn-' + unusedButtonClass ).addClass( 'btn-secondary' ).prop( 'disabled', 'disabled' ).blur();

	$.ajax({
		url: 'review/add',
		data: {
			id: id,
			val: val
		},
		dataType: 'json'
	}).done( function ( ret ) {
			if ( ret.user ) {
				$reviewerNode = $( '.status-div-reviewer-' + id ).show();
				$reviewerNode.find('.reviewer-link').prop( 'href', ret.userpage ).text( ret.user );
				$reviewerNode.find('.reviewer-timestamp').text( ret.timestamp );
				$( unusedButtonId ).removeClass( 'btn-secondary' ).prop( 'disabled', true ).addClass( 'btn-' + unusedButtonClass );
			} else {
				if ( ret.error === 'Unauthorized' ) {
					alert( 'You need to be logged in to be able to review.' );
				} else {
					alert( 'There was an error in connecting to database.' );
				}
				$( buttonId ).addClass( 'btn-' + buttonClass ).removeClass( 'btn-' + buttonClass + '-clicked' ).blur();
				$( unusedButtonId ).removeClass( 'btn-secondary' ).prop( 'disabled', false ).addClass( 'btn-' + unusedButtonClass );
			}
		}
	);
}
