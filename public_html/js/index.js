/**
 * Save a review
 * @param id int ID of the record
 * @param val string Save value 'fixed' or 'false'
 */
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
    $.ajax(
        {
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
            jsonpCallback: 'callback',
            success: function ( ret ) {
                console.log( 'XHR Success' );
                if ( ret.detail ) {
                    $( compareDiv ).find( '.compare-pane-left' ).html( ret.detail.article );
                    $( compareDiv ).find( '.compare-pane-right' ).html( ret.detail.source );
                } else {
                    $( compareDiv ).find( '.compare-pane-left' ).html( '<span class="text-danger">Error! API returned no data.</span>' );
                    $( compareDiv ).find( '.compare-pane-right' ).html( '<span class="text-danger">Error! API returned no data.</span>' );
                }
            },
            error: function () {
                console.log( 'XHR Fail' );
            }
        }
    );
}
