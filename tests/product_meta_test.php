<?php
// Offline regression coverage: php tests/product_meta_test.php
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) throw new ErrorException($message, 0, $severity, $file, $line);
});
require dirname(__DIR__) . '/system/library/product_meta.php';

$checks = 0;
function productMetaAssert($condition, $message) {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}

$descriptions = array(
    2 => array(
        'name' => 'Činele za bubnjeve',
        'description' => '<p>Opis proizvoda.</p>',
        'meta_title' => 'Činele za bubnjeve | Glazbena kutija',
        'meta_description' => 'Ručno uneseni hrvatski opis.',
        'tag' => 'ručna oznaka'
    ),
    3 => array(
        'name' => 'Drum cymbals',
        'description' => '<p>Product description.</p>',
        'meta_title' => 'Buy drum cymbals | Glazbena kutija',
        'meta_description' => 'Manually entered English description.',
        'tag' => 'manual tag'
    )
);

$result = ProductMeta::autofillDescriptions($descriptions);
foreach ($descriptions as $language_id => $description) {
    productMetaAssert($result[$language_id]['meta_title'] === $description['meta_title'], 'Saving must preserve the custom meta title for language ' . $language_id . '.');
    foreach (array('name', 'description', 'meta_description', 'tag') as $field) {
        productMetaAssert($result[$language_id][$field] === $description[$field], 'Saving must preserve ' . $field . ' for language ' . $language_id . '.');
    }
}
productMetaAssert(ProductMeta::autofillDescriptions($result) === $result, 'Saving a product again must preserve both custom meta titles.');

// Renaming an article must not overwrite its independent, manually entered SEO title.
$renamed = $result;
$renamed[2]['name'] = 'Novi naziv činela';
$renamed[3]['name'] = 'New cymbal name';
$result = ProductMeta::autofillDescriptions($renamed);
foreach ($descriptions as $language_id => $description) {
    productMetaAssert($result[$language_id]['meta_title'] === $description['meta_title'], 'Renaming an article must preserve the custom meta title for language ' . $language_id . '.');
}

// Empty titles still use the corresponding language's article name.
foreach (array('', " \t\r\n", null) as $empty_title) {
    $empty = $descriptions;
    $empty[2]['meta_title'] = $empty_title;
    unset($empty[3]['meta_title']);
    $result = ProductMeta::autofillDescriptions($empty);
    productMetaAssert($result[2]['meta_title'] === $empty[2]['name'], 'A blank Croatian title must fall back to the Croatian article name.');
    productMetaAssert($result[3]['meta_title'] === $empty[3]['name'], 'A missing English title must fall back to the English article name.');
}

$mixed = $descriptions;
$mixed[3]['meta_title'] = '';
$result = ProductMeta::autofillDescriptions($mixed);
productMetaAssert($result[2]['meta_title'] === $descriptions[2]['meta_title'] && $result[3]['meta_title'] === $descriptions[3]['name'], 'Auto-filling one language must not change the other language.');

$result = ProductMeta::autofillDescriptions(array(2 => array('name' => 'Činele za bubnjeve', 'tag' => '')));
productMetaAssert($result[2]['meta_keyword'] === 'činele, bubnjeve' && $result[2]['tag'] === 'činele, bubnjeve', 'Automatic keyword and empty tag generation must keep working.');

echo 'Product meta tests passed (' . $checks . " checks).\n";
