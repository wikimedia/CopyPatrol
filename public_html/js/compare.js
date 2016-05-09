function compare( copyvio, page, divId ) {
    //All magic goes here
    // Testing...
    divId = '#comp' + divId;
    if ( $( divId ).is( ":visible" ) ) {
        $( divId ).hide( 500 );
    } else {
        $( divId ).show( {
            'duration': 500,
            'easing': 'linear'
        } );
    }
}