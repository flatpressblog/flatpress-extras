﻿/**
 * @license Copyright (c) 2003-2023, CKSource Holding sp. z o.o. All rights reserved.
 * CKEditor 4 LTS ("Long Term Support") is available under the terms of the Extended Support Model.
 */

( function() {
	CKEDITOR.plugins.liststyle = {
		requires: 'dialog,contextmenu',
		// jscs:disable maximumLineLength
		lang: 'cs,da,de,el,en,es,fr,it,ja,nl,pt-br,ru,si,sl', // %REMOVE_LINE_CORE%
		// jscs:enable maximumLineLength
		init: function( editor ) {
			if ( editor.blockless )
				return;

			var def, cmd;

			def = new CKEDITOR.dialogCommand( 'numberedListStyle', {
				requiredContent: 'ol',
				allowedContent: 'ol{list-style-type}[start]; li{list-style-type}[value]',
				contentTransformations: [
					[ 'ol: listTypeToStyle' ]
				]
			} );
			cmd = editor.addCommand( 'numberedListStyle', def );
			editor.addFeature( cmd );
			CKEDITOR.dialog.add( 'numberedListStyle', this.path + 'dialogs/liststyle.js' );

			def = new CKEDITOR.dialogCommand( 'bulletedListStyle', {
				requiredContent: 'ul',
				allowedContent: 'ul{list-style-type}',
				contentTransformations: [
					[ 'ul: listTypeToStyle' ]
				]
			} );
			cmd = editor.addCommand( 'bulletedListStyle', def );
			editor.addFeature( cmd );
			CKEDITOR.dialog.add( 'bulletedListStyle', this.path + 'dialogs/liststyle.js' );

			//Register map group;
			editor.addMenuGroup( 'list', 108 );

			editor.addMenuItems( {
				numberedlist: {
					label: editor.lang.liststyle.numberedTitle,
					group: 'list',
					command: 'numberedListStyle'
				},
				bulletedlist: {
					label: editor.lang.liststyle.bulletedTitle,
					group: 'list',
					command: 'bulletedListStyle'
				}
			} );

			editor.contextMenu.addListener( function( element ) {
				if ( !element || element.isReadOnly() )
					return null;

				while ( element ) {
					var name = element.getName();
					if ( name == 'ol' )
						return { numberedlist: CKEDITOR.TRISTATE_OFF };
					else if ( name == 'ul' )
						return { bulletedlist: CKEDITOR.TRISTATE_OFF };

					element = element.getParent();
				}
				return null;
			} );
		}
	};

	CKEDITOR.plugins.add( 'liststyle', CKEDITOR.plugins.liststyle );
} )();
