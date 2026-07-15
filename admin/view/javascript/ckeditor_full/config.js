/**
 * @license Copyright (c) 2003-2015, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or http://ckeditor.com/license
 */

CKEDITOR.editorConfig = function( config ) {
	ck_clicker_on_config();

	config.font_names = 'Open Sans/Open Sans;Open Sans Condensed/Open Sans Condensed;'+
			'Roboto/Roboto;Roboto Condensed/Roboto Condensed;'+
			'Lato/Lato;Slabo/Slabo;Oswald/Oswald;'+
			'Source Sans Pro/Source Sans Pro;'+
			'Montserrat/Montserrat;'+
			'Raleway/Raleway;'+
			'PT Sans/PT Sans;'+
			'PT Serif/PT Serif;'+
			'Lora/Lora;'+
			'Noto Sans/Noto Sans;'+
			'Nunito Sans/Nunito Sans;'+
			'Concert One/Concert One;'+
			'Prompt/Prompt;'+
			'Work Sans/Work Sans;'+
			'Prompt/Prompt;'+
			config.font_names;

	// Define changes to default configuration here. For example:
	// config.language = 'fr';
	// config.uiColor = '#AADC6E';

	var user_token = '';
	if (typeof getURLVar == 'function') {
		var var_token = getURLVar('token');
		if (var_token) {
			var_token = '&token=' + var_token;
		} else {
			var_token = '&user_token=' + getURLVar('user_token');
		}

		if (var_token) {
			user_token = var_token;
		}
	}

	config.filebrowserBrowseUrl = 'index.php?route=common/filemanager&iframe=1' + user_token;
	//config.filebrowserImageBrowseUrl = 'index.php?route=common/filemanager';
	//config.filebrowserFlashBrowseUrl = 'index.php?route=common/filemanager';
	//config.filebrowserUploadUrl = 'index.php?route=common/filemanager';
	//config.filebrowserImageUploadUrl = 'index.php?route=common/filemanager';
	//config.filebrowserFlashUploadUrl = 'index.php?route=common/filemanager';

	//config.filebrowserWindowWidth = '960';
	//config.filebrowserWindowHeight = '580';

	//config.enterMode = CKEDITOR.ENTER_BR;
	//config.shiftEnterMode = CKEDITOR.ENTER_P;

	config.extraPlugins = 'codemirror,imagecaptioned,bs_grid,qrc';
	config.removePlugins = 'wsc,scayt,preview,newpage,save,print,language,osem_googlemaps,bt_table';
	//config.removeButtons = 'searchCode,autoFormat,CommentSelectedRange,UncommentSelectedRange,AutoComplete';

	// Add a domain-restricted browser key locally if the map plugin is used.
	config.leaflet_maps_google_api_key = '';

	config.forcePastePopup = true;
	config.forceDomainRemove = true;

	// All content will be pasted as plain text.
	//config.forcePasteAsPlainText = true;
	// Only Microsoft Word content formatting will be preserved.
	//config.forcePasteAsPlainText = 'allow-word';

	config.skin = 'moono-lisa';

	config.htmlEncodeOutput = false;
	config.entities = false;
	config.startupOutlineBlocks = true;
	config.extraAllowedContent = '*{*}';
	config.allowedContent = true;

	config.autoParagraph = false;

	config.height = 300;
	config.resize_enabled = true;
	config.resize_dir = 'vertical';

	config.embed_provider = '//ckeditor.iframe.ly/api/oembed?url={url}&callback={callback}'; // for ckeditor 4.7

	config.contentsLangDirection = $('html').attr('dir');
	//config.contentsLangDirection = 'ltr';
	//config.contentsLangDirection = 'rtl';
	//config.skin = 'moono';
	//config.toolbar = 'full';
	//config.toolbar = 'Custom';

	/*config.toolbarGroups: [
		{ name: 'document',	   groups: [ 'mode', 'document' ] },
		{ name: 'clipboard',   groups: [ 'clipboard', 'undo' ] },
		'/',
		{ name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
		{ name: 'links' }
	];*/

	// FontAwesome configs
	config.fontawesomePath = 'view/javascript/font-awesome/css/font-awesome.min.css';
	/*$.ajax({
		url: config.fontawesomePath,
		type: 'HEAD',
		async: false,
		cache: true,
		error: function() {
			config.fontawesomePath = '';
		},
		success: function() {
		}
	});*/

	var spec_chars = ['&plusmn;', '&Omega;'];

	for (var i = 0; i < spec_chars.length; i++) {
		if (!config.specialChars.includes(spec_chars[i])) {
			config.specialChars.push(spec_chars[i]);
		}
	}

	CKEDITOR.dtd.$removeEmpty['span'] = false;
	CKEDITOR.dtd.$removeEmpty['i'] = false;
	/*config.toolbar = [
		{ name: 'insert', items: [ 'FontAwesome', 'Source' ] }
	];*/
	//config.startupMode = 'source';

	if (!config.contentsCss) {
		config.contentsCss = [];
	} else if (config.contentsCss && typeof config.contentsCss != 'array') {
		config.contentsCss = [config.contentsCss];
	}

	//CKEDITOR.addCss(".cke_editable{font-family: Arial,Helvetica,sans-serif;font-size:14px;}");

	// Add Bootstrap styles
	//config.contentsCss  = ['https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css','mycustom.css'];
	//config.contentsCss  = ['https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css','mycustom.css'];
	//config.contentsCss  = ['/admin/view/stylesheet/bootstrap.css'];

	$('html link[rel="stylesheet"][href*="bootstrap"]').each(function(k,v) {
		if (String($(this).attr('href')).indexOf('/bootstrap.css') !== -1
			|| String($(this).attr('href')).indexOf('/bootstrap.min.css') !== -1
			|| String($(this).attr('href')).indexOf('/opencart.css') !== -1
			) {
				config.contentsCss.push($(this).attr('href'));
		}
	});

	//console.log(config.contentsCss);

	CKEDITOR.on('instanceLoaded', function(evt) {
		ck_clicker_on_ready(evt);
	});
};

function ck_clicker_on_config(evt = {}) {
	setTimeout(function() {
		var cssId = 'ck_css';
		if (!document.getElementById(cssId)) {
			var head  = document.getElementsByTagName('head')[0];
			var link  = document.createElement('link');
			link.id   = cssId;
			link.rel  = 'stylesheet';
			link.type = 'text/css';
			link.href = 'view/javascript/ckeditor_full/ck_clicker.css';
			link.media = 'all';
			head.appendChild(link);
		}
	}, 10);
}

var ck_clicker_on_ready_to = {};
function ck_clicker_on_ready(evt) {
	if (typeof ck_clicker_on_ready_to[evt.editor.name] != 'undefined') {
		clearTimeout(ck_clicker_on_ready_to[evt.editor.name]);
	}
	ck_clicker_on_ready_to[evt.editor.name] = setTimeout(function(evt) {
		var toolbar_before = {
			'a.cke_button.cke_button__image': 'a.cke_button__bs_grid',
			'a.cke_button.cke_button__video': 'a.cke_button__bs_grid',
			'a.cke_button.cke_button__html5video': 'a.cke_button__bs_grid',
			'a.cke_button.cke_button__html5audio': 'a.cke_button__bs_grid',
			'a.cke_button.cke_button__youtube': 'a.cke_button__bs_grid',
		};
		var toolbar_after = {};
		var toolbar_hide = {};
		for (instance in CKEDITOR.instances) {
			//var toolbar_class = '#cke_' + instance + ' .cke_toolbox ';
			var toolbar_class = '#cke_' + evt.editor.name + ' .cke_toolbox ';
			for (idx in toolbar_before) {
				//console.log(toolbar_class, $(toolbar_class + idx));
				if ($(toolbar_class + toolbar_before[idx]).length && $(toolbar_class + idx).length) {
					var ckebtn = $(toolbar_class + idx).detach();
					$(toolbar_class + toolbar_before[idx]).before(ckebtn);
				}
			}
		}
	}, 50, evt);
}
