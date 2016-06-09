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

    $.get( 'addreview',
        { id: id, val: val }
    ).done( function ( ret ) {
            if ( ret == false ) {
                $( buttonId ).addClass( 'btn-' + buttonClass ).removeClass( 'btn-' + buttonClass + '-clicked' ).blur();
                $( unusedButtonId ).removeClass( 'btn-secondary' ).prop( 'disabled', false ).addClass( 'btn-' + unusedButtonClass );
                alert( 'There was an error in connecting to database.' );
            }
        }
    );
}
