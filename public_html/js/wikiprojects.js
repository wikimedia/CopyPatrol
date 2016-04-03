//
function colorizeWikiprojects() {
	projects = $( '.wikiproject-div' ).text();
	uniques = {};
	colors = randomColor({
		count: projects.length,
		luminosity: 'light',
		hue: 'random',
		format: 'rgb'
	});
	for( i=0; i < projects.length; i++ ) {
		if( $.inArray( projects[i], uniques ) == -1 ) {
			uniques[projects[i]] = colors[i];
		}
	}
	console.log( uniques );
	console.log( colors );
	console.log( projects );
}

