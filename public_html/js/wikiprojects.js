//
function colorizeWikiprojects( projects ) {
	$colors = randomColor({
		count: projects.length,
		luminosity: 'random',
		hue: 'random',
		format: 'rgb'
	});
	console.log( $colors );
}

