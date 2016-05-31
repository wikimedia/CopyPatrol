function saveState( id, val ) {
    str = 'id=' + id + '&value=' + val;
    buttonId = null;
    unusedButtonId = null;
    buttonClass = null;
    unusedButtonClass = null;
    if ( val == 'Success' ) {
        buttonId = '#success' + id;
        unusedButtonId = '#danger' + id;
        buttonClass = 'success';
        unusedButtonClass = 'danger';
    } else if ( val == 'Danger' ) {
        buttonId = '#danger' + id;
        unusedButtonId = '#success' + id;
        unusedButtonClass = 'success';
        buttonClass = 'danger';
    }

    $.ajax( {
        type: "POST",
        dataType: "text",
        url: "response.php",
        data: str,
        beforeSend: function () {
            $( buttonId ).removeClass( 'btn-' + buttonClass ).addClass( 'btn-' + buttonClass + '-clicked' ).blur();
            $( unusedButtonId ).removeClass( 'btn-' + unusedButtonClass ).addClass( 'btn-secondary' ).prop( 'disabled', 'disabled' ).blur();
        },
        complete: function () {
        },
        success: function ( ret ) {
            if ( !ret ) {
                $( buttonId ).addClass( 'btn-' + buttonClass ).removeClass( 'btn-' + buttonClass + '-clicked' ).blur();
                $( unusedButtonId ).removeClass( 'btn-secondary' ).prop( 'disabled', false ).addClass( 'btn-' + unusedButtonClass );
                alert( 'There was an error in connecting to database.' );
            }
        }
    } );

}

function loginRequest() {
    $.ajax( {
        type: "POST",
        dataType: "text",
        url: "response.php",
        data: 'login',
        success: function ( ret ) {
            console.log( ret );
            // alert( ret );
            // location.href = ret;
        }
    } );
}
