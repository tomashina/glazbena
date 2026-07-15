/**
 * @file Bootstrap Grid plugin.
 */

(function(/* $, Drupal, CKEDITOR */) {
	"use strict";
	CKEDITOR.plugins.add(
		'bs_grid',
		{
			lang : 'en',
			requires : 'widget,dialog',
			icons : 'bs_grid',
			init: function(editor) {
				var maxGridColumns = 12;
				var lang = editor.lang.bs_grid;

				CKEDITOR.dialog.add('bs_grid', this.path + 'dialogs/bs_grid.js');
				editor.addContentsCss(this.path + 'css/editor.css');

				editor.ui.addButton('bs_grid', {
					label : lang.createBsGrid,
					command : 'bs_grid',
					icon : this.path + 'icons/bs_grid.png'
				});

				editor.widgets.add(
					'bs_grid',
					{
						allowedContent: 'div(!ckebs-grid);div(!row,!row-*);div(!col-*-*)',
						requiredContent: 'div(ckebs-grid)',
						parts: {
							bs_grid: 'div.ckebs-grid',
						},
						editables: {
							content: '',
						},
						template: '<div class="ckebs-grid"></div>',
						dialog: 'bs_grid',
						defaults: {},
						// Before init.
						upcast: function(element) {
							return element.name === 'div' && element.hasClass('ckebs-grid');
						},
						// Initialize.
						// Init function is useful after
						// copy paste rebuild.
						init : function() {
							var rowNumber = 1;
							var rowCount = this.element.getChildCount();
							for (rowNumber; rowNumber <= rowCount; rowNumber++) {
								this.createEditable(maxGridColumns, rowNumber);
							}
						},
						// Prepare data.
						data: function() {
							if (this.data.colCount && this.element.getChildCount() < 1) {
								var colCount = this.data.colCount;
								var rowCount = this.data.rowCount;
								var breakpoint = this.data.breakpoint;
								var row = this.parts['bs_grid'];
								for (var i = 1; i <= rowCount; i++) {
									this.createGrid(colCount, row, i, breakpoint);
								}
							}
						},
						str_replace: function(search,replace,string) {string=String(string).split(search);string=string.join(replace);return string;},
						// Helper functions.
						// Create grid.
						createGrid: function(colCount, row, rowNumber, breakpoint) {
							var content = '<div class="row row-' + rowNumber + '">';

							if (String(colCount).indexOf('col-') === 0) { // template
								var colTemplate = this.str_replace('col-', '', colCount);
								colTemplate = String(colTemplate).split('+');
								colCount = colTemplate.length;

								if (breakpoint == 'col') { // class col
									breakpoint = 'col-';
								}

								for (var i = 0; i < colCount; i++) {
									content = content + '<div class="' + breakpoint + colTemplate[i] + '">' + '  Col ' + (i+1) + '</div>';
								}
							} else { // equal width columns
								for (var i = 1; i <= colCount; i++) {
									if (breakpoint == 'col') { // class col
										content = content + '<div class="col">' + '  Col ' + i + '</div>';
									} else {
										content = content + '<div class="' + breakpoint + Math.floor(maxGridColumns / colCount) + '">' + '  Col ' + i + '</div>';
									}
								}
							}


							content = content + '</div>';
							row.appendHtml(content);
							this.createEditable(colCount, rowNumber);
						},
						// Create editable.
						createEditable: function(
								colCount, rowNumber) {
							for (var i = 1; i <= colCount; i++) {
								this.initEditable(
												'ckebs-grid' + rowNumber + i,
												{ selector : '.row-' + rowNumber + ' > div:nth-child(' + i + ')' }
								);
							}
						}
				});
			}
		});

})(/* jQuery, Drupal, CKEDITOR */);