/**
 * @file
 * Contains bs_grid.js.
 */

CKEDITOR.dialog.add('bs_grid', function (editor) {
var lang = editor.lang.bs_grid;
var col_columns = [
		['1', 1],
		['2', 2],
		['3', 3],
		['4', 4],
		//['5', 5],
		['6', 6],
		//['7', 7],
		//['8', 8],
		//['9', 9],
		//['10', 10],
		//['11', 11],
		['12', 12],
	];
var col_templates = [
		['col-3+9', '3 + 9'],
		['col-9+3', '9 + 3'],
		['col-4+8', '4 + 8'],
		['col-8+4', '8 + 4'],
		['col-5+7', '5 + 7'],
		['col-7+5', '7 + 5'],
		['col-3+6+3', '3 + 6 + 3'],
		['col-3+3+6', '3 + 3 + 6'],
		['col-6+3+3', '6 + 3 + 3'],
	];
var col_breakpoints = [
		[lang.au, 'col'], // auto for bootstrap 4
		[lang.xs, 'col-'],
		[lang.sm, 'col-sm-'],
		[lang.md, 'col-md-'],
		[lang.lg, 'col-lg-'],
		[lang.xl, 'col-xl-'],
	];

// Whole-positive-integer validator.
function validatorNum(msg) {
	return function () {
	var value = this.getValue(),
		//pass = !!(CKEDITOR.dialog.validate.integer()(value) && value > 0);
		pass = !!(CKEDITOR.dialog.validate.notEmpty()(value));

	if (!pass) {
		alert(msg);
	}

	return pass;
	};
}

return {
	title: lang.editBsGrid,
	minWidth: 300,
	minHeight: 190,
	onShow: function () {
	// Detect if there's a selected table.
	var selection = editor.getSelection(),
		ranges = selection.getRanges();
	var command = this.getName();

	var rowsInput = this.getContentElement('info', 'rowCount'),
		colsInput = this.getContentElement('info', 'colCount'),
		breakpointInput = this.getContentElement('info', 'breakpoint');

	if (command === 'bs_grid') {
		var grid = selection.getSelectedElement();
		// Enable or disable row and cols.
		if (grid) {
			this.setupContent(grid);
			if (rowsInput) {
				rowsInput.disable();
			}
			if (colsInput) {
				colsInput.disable();
			}
			if (breakpointInput) {
				breakpointInput.disable();
			}
		} else {
			var obj = {
				rowsInput:rowsInput._.inputId,
				colsInput:colsInput._.inputId,
				breakpointInput:breakpointInput._.children[3].domId, // col-md default
				lang: lang,
				col_columns: col_columns,
				col_templates: col_templates,
				col_breakpoints: col_breakpoints,
			};
			setTimeout(function(obj) {
				// fill templates
				if (!$('#' + obj.colsInput).find('optgroup').length) {
					var col_options = $('#' + obj.colsInput).html();
					var col_html = '<optgroup label="' + obj.lang.equal_columns + '"></optgroup>' + col_options;

					if (obj.col_templates) {
						col_html += '<optgroup label="' + obj.lang.templates + '"></optgroup>';

						for (i = 0; i < obj.col_templates.length; i++) {
							col_html += '<option value="' + obj.col_templates[i][0] + '">' + obj.col_templates[i][1] + '</option>';
						}
					}

					$('#' + obj.colsInput).html(col_html);
				}
				// set default values
				document.getElementById(obj.rowsInput).value = '1';
				document.getElementById(obj.colsInput).value = '2';
				document.getElementById(obj.breakpointInput).checked = true;
			}, 50, obj);
		}
	}
	},
	contents: [
	{
		id: 'info',
		label: lang.infoTab,
		accessKey: 'I',
		elements: [
		{
			id: 'rowCount',
			type: 'text',
			width: '50px',
			required: true,
			label: lang.numRows,
			validate: validatorNum(lang.numRowsError),
			setup: function (widget) {
				this.setValue(widget.data.rowCount);
			},
			commit: function (widget) {
				widget.setData('rowCount', this.getValue());
			}
		},
		{
			id: 'colCount',
			type: 'select',
			required: true,
			label: lang.numCols,
			items: col_columns,
			validate: validatorNum(lang.numColsError),
			setup: function (widget) {
				this.setValue(widget.data.colCount);
			},
			commit: function (widget) {
				widget.setData('colCount', this.getValue());
			}
		},
		{
			id: 'breakpoint',
			type: 'radio',
			required: true,
			label: lang.breakpoint,
			items: col_breakpoints,
			setup: function (widget) {
				this.setValue(widget.data.breakpoint);
			},
			commit: function (widget) {
				widget.setData('breakpoint', this.getValue());
			}
		}
		]
	}
	]
};
});