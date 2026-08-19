( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var addButton = document.getElementById( 'sevmatic-bcp-add-row' );
		var rows = document.getElementById( 'sevmatic-bcp-tiers-rows' );
		var template = document.getElementById( 'sevmatic-bcp-row-template' );

		if ( ! addButton || ! rows || ! template ) {
			return;
		}

		addButton.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			var index = rows.children.length;
			var markup = template.innerHTML.replace( /\[0\]/g, '[' + index + ']' );
			var wrapper = document.createElement( 'tbody' );
			wrapper.innerHTML = markup.trim();

			var newRow = wrapper.firstElementChild;
			if ( newRow ) {
				rows.appendChild( newRow );
			}
		} );

		rows.addEventListener( 'click', function ( event ) {
			var removeButton = event.target.closest( '.sevmatic-bcp-remove-row' );

			if ( ! removeButton ) {
				return;
			}

			event.preventDefault();

			var row = removeButton.closest( '.sevmatic-bcp-tier-row' );
			if ( row && rows.children.length > 1 ) {
				row.remove();
			}
		} );
	} );
} )();
