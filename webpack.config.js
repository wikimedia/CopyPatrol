const Encore = require( '@symfony/webpack-encore' );

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if ( !Encore.isRuntimeEnvironmentConfigured() ) {
	Encore.configureRuntimeEnvironment( process.env.NODE_ENV || 'dev' );
}

Encore
	// Directory where compiled assets will be stored.
	.setOutputPath( 'public/assets/' )

	// Public URL path used by the web server to access the output path.
	.setPublicPath( '/assets' )

	.setManifestKeyPrefix( 'assets' )

	.copyFiles( {
		from: './assets/images',
		to: 'images/[path][name].[ext]'
	} )

	// Main asset entry.
	.addEntry( 'app', [
		'./assets/app.js',
		'./assets/app.less'
	] )

	// Other options.
	.autoProvidejQuery()
	.enableLessLoader()
	.cleanupOutputBeforeBuild()
	.disableSingleRuntimeChunk()
	.enableSourceMaps( !Encore.isProduction() )
	.enableVersioning( Encore.isProduction() );

module.exports = Encore.getWebpackConfig();
