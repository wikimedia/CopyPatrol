function saveState( id, val ) {
    buttonId = null;
    unusedButtonId = null;
    buttonClass = null;
    unusedButtonClass = null;
    if ( val == 'fixed' ) {
        buttonId = '#success' + id;
        unusedButtonId = '#danger' + id;
        buttonClass = 'success';
        unusedButtonClass = 'danger';
    } else if ( val == 'false' ) {
        buttonId = '#danger' + id;
        unusedButtonId = '#success' + id;
        unusedButtonClass = 'success';
        buttonClass = 'danger';
    }
    $( buttonId ).removeClass( 'btn-' + buttonClass ).addClass( 'btn-' + buttonClass + '-clicked' ).blur();
    $( unusedButtonId ).removeClass( 'btn-' + unusedButtonClass ).addClass( 'btn-secondary' ).prop( 'disabled', 'disabled' ).blur();

    $.get( 'review/add',
        { id: id, val: val }
    ).done( function ( ret ) {
            console.log( ret );
            if ( ret == 'false' ) {
                $( buttonId ).addClass( 'btn-' + buttonClass ).removeClass( 'btn-' + buttonClass + '-clicked' ).blur();
                $( unusedButtonId ).removeClass( 'btn-secondary' ).prop( 'disabled', false ).addClass( 'btn-' + unusedButtonClass );
                alert( 'There was an error in connecting to database.' );
            } else if ( ret == 'Unauthorized' ) {
                alert( 'You need to be logged in to be able to review.' );
                $( buttonId ).addClass( 'btn-' + buttonClass ).removeClass( 'btn-' + buttonClass + '-clicked' ).blur();
                $( unusedButtonId ).removeClass( 'btn-secondary' ).prop( 'disabled', false ).addClass( 'btn-' + unusedButtonClass );
            }
        }
    );
}
