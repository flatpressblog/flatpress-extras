/**
 * @license Copyright (c) 2003-2023, CKSource Holding sp. z o.o. All rights reserved.
 * CKEditor 4 LTS ("Long Term Support") is available under the terms of the Extended Support Model.
 */

/**
 * @fileOverview Plugin definition for the a11yhelp, which provides a dialog
 * with accessibility related help.
 */

( function() {
	var pluginName = 'a11yhelp',
		commandName = 'a11yHelp';

	CKEDITOR.plugins.add( pluginName, {
		requires: 'dialog',

		// List of available localizations.
		// jscs:disable
		availableLangs: { cs:1,da:1,de:1,el:1,en:1,es:1,fr:1,it:1,ja:1,nl:1,'pt-br':1,ru:1,si:1,sl:1 },
		// jscs:enable

		init: function( editor ) {
			var plugin = this;
			editor.addCommand( commandName, {
				exec: function() {
					var langCode = editor.langCode;
					langCode =
						plugin.availableLangs[ langCode ] ? langCode :
						plugin.availableLangs[ langCode.replace( /-.*/, '' ) ] ? langCode.replace( /-.*/, '' ) :
						'en';

					CKEDITOR.scriptLoader.load( CKEDITOR.getUrl( plugin.path + 'dialogs/lang/' + langCode + '.js' ), function() {
						editor.lang.a11yhelp = plugin.langEntries[ langCode ];
						editor.openDialog( commandName );
					} );
				},
				modes: { wysiwyg: 1, source: 1 },
				readOnly: 1,
				canUndo: false
			} );

			editor.setKeystroke( CKEDITOR.ALT + 48 /*0*/, 'a11yHelp' );
			CKEDITOR.dialog.add( commandName, this.path + 'dialogs/a11yhelp.js' );

			editor.on( 'ariaEditorHelpLabel', function( evt ) {
				evt.data.label = editor.lang.common.editorHelp;
			} );
		}
	} );
} )();
