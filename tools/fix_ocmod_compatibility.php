<?php

require dirname(__DIR__) . '/config.php';

$apply = in_array('--apply', $argv, true);
$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);

if ($db->connect_errno) {
	throw new RuntimeException('Database connection failed: ' . $db->connect_error);
}

function textNode(DOMElement $element, $value) {
	while ($element->firstChild) {
		$element->removeChild($element->firstChild);
	}

	$element->appendChild($element->ownerDocument->createCDATASection($value));
}

function fileNode(DOMDocument $document, $path) {
	$matches = array();

	foreach ($document->getElementsByTagName('file') as $file) {
		if ($file->getAttribute('path') === $path) {
			$matches[] = $file;
		}
	}

	if (count($matches) !== 1) {
		throw new RuntimeException("Expected one file node for {$path}, found " . count($matches));
	}

	return $matches[0];
}

function operationNode(DOMElement $file, $search) {
	$matches = array();

	foreach ($file->getElementsByTagName('operation') as $operation) {
		$node = $operation->getElementsByTagName('search')->item(0);

		if ($node && trim($node->textContent) === $search) {
			$matches[] = $operation;
		}
	}

	if (count($matches) !== 1) {
		throw new RuntimeException("Expected one operation for search {$search}, found " . count($matches));
	}

	return $matches[0];
}

function setSearch(DOMElement $operation, $search) {
	textNode($operation->getElementsByTagName('search')->item(0), $search);
}

function setAdd(DOMElement $operation, $add) {
	textNode($operation->getElementsByTagName('add')->item(0), $add);
}

function removeOperation(DOMElement $file, $search) {
	$operation = operationNode($file, $search);
	$operation->parentNode->removeChild($operation);
}

function removeFile(DOMDocument $document, $path, $search = null) {
	$file = fileNode($document, $path);

	if ($search !== null) {
		operationNode($file, $search);
	}

	$file->parentNode->removeChild($file);
}

function appendFileOperation(DOMDocument $document, $path, $search, $add, $position = 'replace', $error = '') {
	$root = $document->getElementsByTagName('modification')->item(0);
	$file = $document->createElement('file');
	$file->setAttribute('path', $path);
	$operation = $document->createElement('operation');

	if ($error !== '') {
		$operation->setAttribute('error', $error);
	}

	$searchNode = $document->createElement('search');
	$searchNode->appendChild($document->createCDATASection($search));
	$addNode = $document->createElement('add');
	$addNode->setAttribute('position', $position);
	$addNode->appendChild($document->createCDATASection($add));
	$operation->appendChild($searchNode);
	$operation->appendChild($addNode);
	$file->appendChild($operation);
	$root->appendChild($file);
}

$patches = array();

$patches[3] = function (DOMDocument $document) {
	$file = fileNode($document, 'system/library/db/mysqli.php');
	$operation = operationNode($file, "SET SQL_MODE = ''");
	setSearch($operation, "SET SESSION sql_mode = 'NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'");

	$file = fileNode($document, 'system/library/template/twig.php');
	$operation = operationNode($file, '$template = $this->twig->loadTemplate($template');
	setSearch($operation, '$twig = new \\Twig\\Environment($loader, $config);');
	setAdd($operation, <<<'PHP'
if (strpos($filename, 'extension/module/product_quick_edit') !== false || strpos($filename, 'extension/module/catalog/product_list') !== false || strpos($filename, 'extension/module/catalog/product_qe_form') !== false) {
				$twig->addFilter(new \Twig\TwigFilter('addslashes', 'addslashes'));
			}
PHP
	);
};

$patches[5] = function (DOMDocument $document) {
	$file = fileNode($document, 'catalog/controller/product/{category,product,manufacturer,search,special}.php');
	$file->setAttribute('path', 'catalog/controller/product/{product,search,special}.php');
};

$patches[6] = function (DOMDocument $document) {
	$file = fileNode($document, 'admin/view/template/catalog/product_form.twig');
	$old = '<td class="text-left" style="width: 40%;"><input type="text" name="product_attribute[{{ attribute_row }}][name]" value="{{ product_attribute.name }}" placeholder="{{ entry_attribute }}" class="form-control"/> <input type="hidden" name="product_attribute[{{ attribute_row }}][attribute_id]" value="{{ product_attribute.attribute_id }}"/></td>';
	$new = '<td class="text-left" style="width: 40%;"><input type="text" name="product_attribute[{{ attribute_row }}][name]" value="{{ product_attribute.name }}" placeholder="{{ entry_attribute }}" class="form-control" style="position:relative;"/> <input type="hidden" name="product_attribute[{{ attribute_row }}][attribute_id]" value="{{ product_attribute.attribute_id }}"/></td>';
	$operation = operationNode($file, $old);
	setSearch($operation, $new);
	$add = $operation->getElementsByTagName('add')->item(0)->textContent;
	$add = preg_replace('/class="form-control"\/>/', 'class="form-control" style="position:relative;"/>', $add, 1);
	setAdd($operation, $add);
};

$patches[7] = function (DOMDocument $document) {
	$file = fileNode($document, 'catalog/view/theme/*/template/common/footer.twig');
	$operation = operationNode($file, '<li><a href="{{ sitemap }}">{{ text_sitemap }}</a></li>');
	setSearch($operation, '<li><a href="{{ contact }}">{{ text_contact }}</a></li>');

	removeFile(
		$document,
		'catalog/view/theme/*/template/information/information.twig',
		'{{ description }}{{ content_bottom }}</div>'
	);
};

$patches[13] = function (DOMDocument $document) {
	$file = fileNode($document, 'system/library/template/twig.php');
	$operation = operationNode($file, '$config = array(\'autoescape\' => false);');
	setSearch($operation, "'debug'       => false,");
	setAdd($operation, "'debug'       => true,");

	$operation = operationNode($file, '$this->twig = new \\Twig_Environment($loader, $config);');
	setSearch($operation, '$twig = new \\Twig\\Environment($loader, $config);');
	setAdd($operation, '$twig->addExtension(new \\Twig\\Extension\\DebugExtension());');
};

$patches[14] = function (DOMDocument $document) {
	$file = fileNode($document, 'catalog/view/theme/*/template/product/product.twig');
	$file->setAttribute('path', 'catalog/view/theme/default/template/product/product.twig');

	$search = '<input type="radio" name="option[{{ option.product_option_id }}]" value="{{ option_value.product_option_value_id }}" data-uid="{{ option_value.sifra }}" />';
	$add = '<input type="radio" name="option[{{ option.product_option_id }}]" value="{{ option_value.product_option_value_id }}" data-uid="{{ option_value.sifra }}" data-sm-image="{{ option_value.option_sm_thumb }}" data-lg-thumb="{{ option_value.option_lg_thumb }}" />';
	appendFileOperation($document, 'catalog/view/theme/basel/template/product/product.twig', $search, $add, 'replace', 'skip');
};

$patches[15] = function (DOMDocument $document) {
	$file = fileNode($document, 'admin/controller/common/filemanager.php');
	removeOperation($file, 'realpath($directory . \'/\' . $filter_name)');
};

$patches[16] = function (DOMDocument $document) {
	$file = fileNode($document, 'catalog/model/tool/image.php');
	$operation = operationNode($file, '$extension = pathinfo($filename, PATHINFO_EXTENSION);');
	setSearch($operation, '$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));');
};

$patches[21] = function (DOMDocument $document) {
	$file = fileNode($document, 'catalog/model/catalog/product.php');
	$search = <<<'PHP'
customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND quantity > 1
PHP;
	removeOperation($file, $search);
};

$patches[22] = function (DOMDocument $document) {
	$file = fileNode($document, 'admin/view/template/customer/custom_field_list.twig');
	removeOperation($file, '<td style="width: 1px;" class="text-center"><input type="checkbox" onclick="$(\'input[name*=\\\'selected\\\']\').trigger(\'click\');"/></td>');

	$file = fileNode($document, 'admin/model/localisation/country.php');
	$search = <<<'PHP'
$this->db->query("UPDATE " . DB_PREFIX . "country SET name = '" . $this->db->escape((string)$data['name']) . "', iso_code_2 = '" . $this->db->escape((string)$data['iso_code_2']) . "', iso_code_3 = '" . $this->db->escape((string)$data['iso_code_3']) . "', address_format = '" . $this->db->escape((string)$data['address_format']) . "', postcode_required = '" . (int)$data['postcode_required'] . "', status = '" . (int)$data['status'] . "' WHERE country_id = '" . (int)$country_id . "'");
PHP;
	removeOperation($file, $search);
};

$patches[25] = function (DOMDocument $document) {
	$file = fileNode($document, 'catalog/view/theme/*/template/account/account.twig');
	$operation = operationNode($file, '<h2>{{ text_my_newsletter }}</h2>');
	setSearch($operation, '{{ text_my_newsletter }}');

	$file = fileNode($document, 'catalog/view/theme/*/template/extension/module/account.twig');
	$operation = operationNode($file, '<a href="{{ account }}" class="list-group-item">{{ text_account }}</a>');
	setSearch($operation, '<a href="{{ account }}"');

	$file = fileNode($document, 'catalog/view/theme/*/template/information/contact.twig');
	$operation = operationNode($file, '<div class="row">{{ column_left }}');
	setSearch($operation, '{{ column_left }}');

	$submitSearch = '<input class="btn btn-primary" type="submit" value="{{ button_submit }}" />';
	$submitOperation = operationNode($file, $submitSearch);
	$submitAdd = $submitOperation->getElementsByTagName('add')->item(0)->textContent;
	$submitOperation->parentNode->removeChild($submitOperation);

	appendFileOperation($document, 'catalog/view/theme/default/template/information/contact.twig', $submitSearch, $submitAdd, 'replace', 'skip');
	appendFileOperation(
		$document,
		'catalog/view/theme/basel/template/information/contact.twig',
		'<input class="btn btn-contrast" type="submit" value="{{ button_submit }}" />',
		str_replace('btn btn-primary', 'btn btn-contrast', $submitAdd),
		'replace',
		'skip'
	);
};

$patches[27] = function (DOMDocument $document) {
	removeFile($document, 'admin/view/template/setting/store_form.twig');
};

$patches[31] = function (DOMDocument $document) {
	$file = fileNode($document, 'catalog/model/catalog/product.php');
	$operation = operationNode($file, 'public function getTotalProductSpecials() {');
	setSearch($operation, 'public function getTotalProductSpecials($data = array()) {');

	$file = fileNode($document, 'catalog/controller/product/{category,manufacturer,search,special}.php');
	$operation = operationNode($file, '$result[\'rating\'],');
	setSearch($operation, "'rating'      =>");

	$file = fileNode($document, 'catalog/view/theme/*/template/product/{category,manufacturer_info,search,special}.twig');
	$file->setAttribute('path', 'catalog/view/theme/default/template/product/{category,manufacturer_info,search,special}.twig');
	$operation = operationNode($file, '{% endif %} </div>');
	setSearch($operation, '</a></div>');
	setAdd($operation, <<<'TWIG'
</a>
			{% if product.variants %}
				<ul class="swatch-variant">
					{% for variant in product.variants %}
					<li>
						<a href="{{ variant.href }}" title="{{ variant.name }}">
							<img src="{{ variant.variant_image }}" alt="{{ variant.name }}" />
						</a>
					</li>
					{% endfor %}
				</ul>
			{% endif %}
			</div>
TWIG
	);
};

$ids = array_keys($patches);
$result = $db->query('SELECT modification_id, name, xml FROM ' . DB_PREFIX . 'modification WHERE modification_id IN (' . implode(',', $ids) . ')');
$rows = array();

while ($row = $result->fetch_assoc()) {
	$rows[(int)$row['modification_id']] = $row;
}

if (count($rows) !== count($ids)) {
	throw new RuntimeException('One or more expected modifications are missing from the database.');
}

$updates = array();

foreach ($patches as $id => $patch) {
	$document = new DOMDocument('1.0', 'UTF-8');
	$document->preserveWhiteSpace = true;

	if (!$document->loadXML($rows[$id]['xml'])) {
		throw new RuntimeException("Invalid XML in modification {$id}");
	}

	$patch($document);
	$xml = $document->saveXML();

	if ($xml === $rows[$id]['xml']) {
		throw new RuntimeException("Modification {$id} was not changed");
	}

	$check = new DOMDocument();

	if (!$check->loadXML($xml)) {
		throw new RuntimeException("Patched XML is invalid for modification {$id}");
	}

	$updates[$id] = $xml;
	echo ($apply ? 'APPLY ' : 'DRY-RUN ') . $id . ' ' . $rows[$id]['name'] . PHP_EOL;
}

if (!$apply) {
	echo 'Dry run complete; no database rows changed.' . PHP_EOL;
	exit(0);
}

$db->begin_transaction();

try {
	$statement = $db->prepare('UPDATE ' . DB_PREFIX . 'modification SET xml = ? WHERE modification_id = ?');

	foreach ($updates as $id => $xml) {
		$statement->bind_param('si', $xml, $id);
		$statement->execute();

		if ($statement->affected_rows !== 1) {
			throw new RuntimeException("Unexpected affected row count for modification {$id}");
		}
	}

	$db->commit();
	echo 'Database modifications updated.' . PHP_EOL;
} catch (Throwable $error) {
	$db->rollback();
	throw $error;
}
