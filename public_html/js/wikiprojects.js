//
function colorizeWikiprojects() {
	projects = $( '.wikiproject-div' );
	colors = randomColor({
		count: projects.length,
		luminosity: 'random',
		hue: 'random',
		format: 'rgb'
	});
	console.log( colors );
}

