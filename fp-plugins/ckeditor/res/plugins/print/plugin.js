﻿/**
 * @license Copyright (c) 2003-2023, CKSource Holding sp. z o.o. All rights reserved.
 * CKEditor 4 LTS ("Long Term Support") is available under the terms of the Extended Support Model.
 */

/**
 * @fileOverview Print Plugin
 */
( function() {
	'use strict';

	CKEDITOR.plugins.add( 'print', {
		requires: 'preview',
		// jscs:disable maximumLineLength
		lang: 'cs,da,de,el,en,es,fr,it,ja,nl,pt-br,ru,si,sl', // %REMOVE_LINE_CORE%
		// jscs:enable maximumLineLength
		icons: 'print,', // %REMOVE_LINE_CORE%
		hidpi: true, // %REMOVE_LINE_CORE%
		init: function( editor ) {
			var pluginName = 'print';

			editor.addCommand( pluginName, CKEDITOR.plugins.print );
			editor.ui.addButton && editor.ui.addButton( 'Print', {
				label: editor.lang.print.toolbar,
				command: pluginName,
				toolbar: 'document,50'
			} );
		}
	} );

	/**
	 * Allows to customize the implementation of printing the editor content provided
	 * by the [Print](https://ckeditor.com/cke4/addon/print) plugin. For example, the official
	 * CKEditor 4 [Export to PDF](https://ckeditor.com/cke4/addon/exportpdf) plugin
	 * can be used here (see the {@glink features/exporttopdf#installation installation guide}):
	 *
	 * ```javascript
	 * // Using an 'Export to PDF' plugin to generate PDF.
	 * CKEDITOR.plugins.print = {
	 * 	exec: function( editor ) {
	 * 		editor.execCommand( 'exportPdf' );
	 * 	}
	 * };
	 * ```
	 *
	 * **Note**: This class represents the {@link CKEDITOR.commandDefinition}
	 * type and should be compatible with its API.
	 *
	 * @singleton
	 * @since 4.14.0
	 * @class CKEDITOR.plugins.print
	 */
	CKEDITOR.plugins.print = {
		exec: function( editor ) {
			CKEDITOR.plugins.preview.createPreview( editor, function( previewWindow ) {
				var nativePreviewWindow = previewWindow.$;

				if ( CKEDITOR.env.gecko ) {
					nativePreviewWindow.print();
				} else {
					nativePreviewWindow.document.execCommand( 'Print' );
				}

				nativePreviewWindow.close();
			} );
		},
		canUndo: false,
		readOnly: 1,
		modes: { wysiwyg: 1 }
	};
} )();
