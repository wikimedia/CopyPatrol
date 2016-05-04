function saveState( id, val ) {
    str = 'id=' + id + '&value=' + val;
    $.ajax( {
        type: "POST",
        dataType: "text",
        url: "response.php",
        data: str,
        success: function ( ret ) {
            // Move the button style changes here after the database edit rights are granted.
            console.log( ret ); // For testing purposes
        }
    } );
    if ( val == 'Success' ) {
        $( '#success' + id ).removeClass( 'btn-success' ).addClass( 'btn-success-clicked' );
        $( '#warning' + id ).prop( 'disabled', 'disabled' );
        $( '#danger' + id ).prop( 'disabled', 'disabled' );
    } else if ( val == 'Warning' ) {
        $( '#warning' + id ).removeClass( 'btn-warning' ).addClass( 'btn-warning-clicked' );
        $( '#success' + id ).prop( 'disabled', 'disabled' );
        $( '#danger' + id ).prop( 'disabled', 'disabled' );
    } else if ( val == 'Danger' ) {
        $( '#danger' + id ).removeClass( 'btn-danger' ).addClass( 'btn-danger-clicked' );
        $( '#warning' + id ).prop( 'disabled', 'disabled' );
        $( '#success' + id ).prop( 'disabled', 'disabled' );
    }
}
