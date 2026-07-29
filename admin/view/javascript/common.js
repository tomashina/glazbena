function getURLVar(key) {
	var value = [];

	var query = String(document.location).split('?');

	if (query[1]) {
		var part = query[1].split('&');

		for (i = 0; i < part.length; i++) {
			var data = part[i].split('=');

			if (data[0] && data[1]) {
				value[data[0]] = data[1];
			}
		}

		if (value[key]) {
			return value[key];
		} else {
			return '';
		}
	}
}

$(document).ready(function() {
	//Form Submit for IE Browser
	$('button[type=\'submit\']').on('click', function() {
		$("form[id*='form-']").submit();
	});

	// Highlight any found errors
	$('.text-danger').each(function() {
		var element = $(this).parent().parent();

		if (element.hasClass('form-group')) {
			element.addClass('has-error');
		}
	});

	// tooltips on hover
	$('[data-toggle=\'tooltip\']').tooltip({container: 'body', html: true});

	// Makes tooltips work on ajax generated content
	$(document).ajaxStop(function() {
		$('[data-toggle=\'tooltip\']').tooltip({container: 'body'});
	});

	// https://github.com/opencart/opencart/issues/2595
	$.event.special.remove = {
		remove: function(o) {
			if (o.handler) {
				o.handler.apply(this, arguments);
			}
		}
	}
	
	// tooltip remove
	$('[data-toggle=\'tooltip\']').on('remove', function() {
		$(this).tooltip('destroy');
	});

	// Tooltip remove fixed
	$(document).on('click', '[data-toggle=\'tooltip\']', function(e) {
		$('body > .tooltip').remove();
	});
	
	$('#button-menu').on('click', function(e) {
		e.preventDefault();
		
		$('#column-left').toggleClass('active');
	});

	// Set last page opened on the menu
	$('#menu a[href]').on('click', function() {
		sessionStorage.setItem('menu', $(this).attr('href'));
	});

	if (!sessionStorage.getItem('menu')) {
		$('#menu #dashboard').addClass('active');
	} else {
		// Sets active and open to selected page in the left column menu.
		$('#menu a[href=\'' + sessionStorage.getItem('menu') + '\']').parent().addClass('active');
	}
	
	$('#menu a[href=\'' + sessionStorage.getItem('menu') + '\']').parents('li > a').removeClass('collapsed');
	
	$('#menu a[href=\'' + sessionStorage.getItem('menu') + '\']').parents('ul').addClass('in');
	
	$('#menu a[href=\'' + sessionStorage.getItem('menu') + '\']').parents('li').addClass('active');
	
	// Image Manager
	$(document).on('click', 'a[data-toggle=\'image\']', function(e) {
		var $element = $(this);
		var $popover = $element.data('bs.popover'); // element has bs popover?

		e.preventDefault();

		// destroy all image popovers
		$('a[data-toggle="image"]').popover('destroy');

		// remove flickering (do not re-add popover when clicking for removal)
		if ($popover) {
			return;
		}

		$element.popover({
			html: true,
			sanitize: false,
			placement: 'right',
			trigger: 'manual',
			content: function() {
				return '<button type="button" id="button-image" class="btn btn-primary"><i class="fa fa-pencil"></i></button> <button type="button" id="button-clear" class="btn btn-danger"><i class="fa fa-trash-o"></i></button>';
			}
		});

		$element.popover('show');

		setTimeout(function(){ // fix bind events on new popover when 

			$('#button-image').on('click', function() {
				var $button = $(this);
				var $icon   = $button.find('> i');

				$('#modal-image').remove();

				$.ajax({
					url: 'index.php?route=common/filemanager&user_token=' + getURLVar('user_token') + '&target=' + $element.parent().find('input').attr('id') + '&thumb=' + $element.attr('id'),
					dataType: 'html',
					beforeSend: function() {
						$button.prop('disabled', true);
						if ($icon.length) {
							$icon.attr('class', 'fa fa-circle-o-notch fa-spin');
						}
					},
					complete: function() {
						$button.prop('disabled', false);

						if ($icon.length) {
							$icon.attr('class', 'fa fa-pencil');
						}
					},
					success: function(html) {
						$('body').append('<div id="modal-image" class="modal">' + html + '</div>');

						$('#modal-image').modal('show');
					}
				});

				$element.popover('destroy');
			});

			$('#button-clear').on('click', function() {
				$element.find('img').attr('src', $element.find('img').attr('data-placeholder'));

				$element.parent().find('input').val('');

				$element.popover('destroy');
			});
			
		}, 250); // end timeout fix
			
	});
});

// Restore the Attribute Preset controls when the legacy OCMOD only partially
// modifies the OpenCart 3.0.5.0 product form. Once OCMOD renders the controls
// itself, this compatibility initializer detects them and does nothing.
$(document).ready(function() {
	var $attributeTable = $('#attribute');

	if (!$attributeTable.length || !$('#attemplate').length) {
		return;
	}

	function initializeAttributePresetRow(row) {
		var $row = $(row);
		var rowId = $row.attr('id') || '';
		var rowMatch = rowId.match(/^attribute-row(\d+)$/);

		if (!rowMatch) {
			return;
		}

		var attributeRow = rowMatch[1];
		var $attributeName = $row.find('input[name="product_attribute[' + attributeRow + '][name]"]');
		var $attributeId = $row.find('input[name="product_attribute[' + attributeRow + '][attribute_id]"]');

		if (!$attributeName.length || !$attributeId.length) {
			return;
		}

		// A text field or drop-list means the complete extension is already active.
		var $existingPreset = $row.find('input[name="product_attribute[' + attributeRow + '][preset]"], select[name="product_attribute[' + attributeRow + '][preset_id][]"]');

		if ($existingPreset.length) {
			$existingPreset.closest('.test')
				.css('position', 'relative')
				.children('.dropdown-menu')
				.css('width', '100%');

			return;
		}

		var presetName = 'product_attribute[' + attributeRow + '][preset]';
		var presetIdName = 'product_attribute[' + attributeRow + '][preset_id][]';
		var $presetContainer = $('<div class="test attribute-preset-compat"></div>').css('position', 'relative');
		var $preset = $('<input type="text" class="form-control" autocomplete="off">')
			.attr('name', presetName)
			.attr('placeholder', 'Odaberite spremljenu vrijednost');
		var $presetId = $('<input type="hidden">').attr('name', presetIdName).val('');

		$presetContainer.append($preset, $presetId);
		$attributeName.after($presetContainer).after('<br>');

		$preset.autocomplete({
			'source': function(request, response) {
				var currentAttributeId = $attributeId.val();

				$.ajax({
					url: 'index.php?route=extension/module/atpresets/autocomplete&user_token=' + encodeURIComponent(getURLVar('user_token')) + '&filter_name=' + encodeURIComponent(request) + '&attribute_id=' + encodeURIComponent(currentAttributeId),
					dataType: 'json',
					success: function(json) {
						response($.map(json, function(item) {
							return {
								category: item.attribute_name || '',
								attribute_id: item.attribute_id || currentAttributeId,
								label: item.not_decoded_text,
								label_decoded: item.text,
								values: item.texts || {},
								value: item.preset_id
							};
						}));
					}
				});
			},
			'select': function(item) {
				$preset.val(item.label_decoded);
				$presetId.val(item.value);

				if (item.attribute_id) {
					$attributeId.val(item.attribute_id);

					if (item.category) {
						$attributeName.val(item.category);
					}
				}

				$.each(item.values, function(languageId, text) {
					$row.find('textarea[name="product_attribute[' + attributeRow + '][product_attribute_description][' + languageId + '][text]"]').val(text);
				});
			}
		});

		$presetContainer.children('.dropdown-menu').css('width', '100%');

		var $actions = $row.children('td').last();

		if (!$actions.find('.attribute-preset-save, button[onclick^="add_preset("]').length) {
			var $savePreset = $('<button type="button" class="btn btn-primary attribute-preset-save" style="margin-top:20px;" data-toggle="tooltip"></button>')
				.attr('title', 'Spremi vrijednosti kao novi predložak')
				.append('<i class="fa fa-plus-circle"></i>');

			$savePreset.on('click', function() {
				var texts = {};

				$row.find('textarea[name^="product_attribute[' + attributeRow + '][product_attribute_description]"]').each(function() {
					var languageMatch = ($(this).attr('name') || '').match(/\[product_attribute_description\]\[(\d+)\]\[text\]$/);

					if (languageMatch) {
						texts[languageMatch[1]] = $(this).val();
					}
				});

				$.ajax({
					url: 'index.php?route=extension/module/atpresets/insert&user_token=' + encodeURIComponent(getURLVar('user_token')),
					type: 'post',
					data: {
						attribute_id: $attributeId.val(),
						texts: texts
					},
					dataType: 'json',
					success: function(json) {
						if (json.preset_id) {
							$presetId.val(json.preset_id);
							$preset.val(json.preset_decoded || json.preset || '');
						}

						if (json.message) {
							alert(json.message);
						}
					},
					error: function() {
						alert('Spremljenu vrijednost nije moguće dodati.');
					}
				});
			});

			$actions.append('<br>').append($savePreset);
		}
	}

	function initializeAttributePresetRows() {
		$attributeTable.find('tbody > tr[id^="attribute-row"]').each(function() {
			initializeAttributePresetRow(this);
		});
	}

	initializeAttributePresetRows();

	if (window.MutationObserver) {
		var attributePresetObserver = new MutationObserver(function() {
			initializeAttributePresetRows();
		});

		attributePresetObserver.observe($attributeTable.find('tbody').get(0), {
			childList: true
		});
	}
});

// Autocomplete */
(function($) {
	$.fn.autocomplete = function(option) {
		return this.each(function() {
			var $this = $(this);
			var $dropdown = $('<ul class="dropdown-menu" />');

			this.timer = null;
			this.items = [];

			$.extend(this, option);

			$this.attr('autocomplete', 'off');

			// Focus
			$this.on('focus', function() {
				this.request();
			});

			// Blur
			$this.on('blur', function() {
				setTimeout(function(object) {
					object.hide();
				}, 200, this);
			});

			// Keydown
			$this.on('keydown', function(event) {
				switch(event.keyCode) {
					case 27: // escape
						this.hide();
						break;
					default:
						this.request();
						break;
				}
			});

			// Click
			this.click = function(event) {
				event.preventDefault();

				var value = $(event.target).parent().attr('data-value');

				if (value && this.items[value]) {
					this.select(this.items[value]);
				}
			}

			// Show
			this.show = function() {
				var pos = $this.position();

				$dropdown.css({
					top: pos.top + $this.outerHeight(),
					left: pos.left
				});

				$dropdown.show();
			}

			// Hide
			this.hide = function() {
				$dropdown.hide();
			}

			// Request
			this.request = function() {
				clearTimeout(this.timer);

				this.timer = setTimeout(function(object) {
					object.source($(object).val(), $.proxy(object.response, object));
				}, 200, this);
			}

			// Response
			this.response = function(json) {
				var html = '';
				var category = {};
				var name;
				var i = 0, j = 0;

				if (json.length) {
					for (i = 0; i < json.length; i++) {
						// update element items
						this.items[json[i]['value']] = json[i];

						if (!json[i]['category']) {
							// ungrouped items
							html += '<li data-value="' + json[i]['value'] + '"><a href="#">' + json[i]['label'] + '</a></li>';
						} else {
							// grouped items
							name = json[i]['category'];
							if (!category[name]) {
								category[name] = [];
							}

							category[name].push(json[i]);
						}
					}

					for (name in category) {
						html += '<li class="dropdown-header">' + name + '</li>';

						for (j = 0; j < category[name].length; j++) {
							html += '<li data-value="' + category[name][j]['value'] + '"><a href="#">&nbsp;&nbsp;&nbsp;' + category[name][j]['label'] + '</a></li>';
						}
					}
				}

				if (html) {
					this.show();
				} else {
					this.hide();
				}

				$dropdown.html(html);
			}

			$dropdown.on('click', '> li > a', $.proxy(this.click, this));
			$this.after($dropdown);
		});
	}
})(window.jQuery);
