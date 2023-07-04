module.exports = {
	extends: [
		'wikimedia',
		'wikimedia/language/es2019'
	],
	rules: {
		'no-alert': 0
	},
	parserOptions: {
		sourceType: 'module'
	},
	env: {
		browser: true,
		node: true,
		es6: true
	},
	globals: {
		$: true,
		jsUnauthorized: true,
		jsDbError: true,
		jsUndoOwnOnly: true,
		jsUnknownError: true,
		jsLoadMore: true,
		jsNoMore: true,
		wikiLang: true
	}
};
