<?php

if ($argc < 3) {
	fwrite(STDERR, "Usage: php scripts/openai_product_feed_snapshot.php <feed-url-or-file> <output.jsonl.gz>\n");
	exit(2);
}

$source = $argv[1];
$output = $argv[2];
if (substr($output, -9) !== '.jsonl.gz') {
	fwrite(STDERR, "Output filename must end in .jsonl.gz\n");
	exit(2);
}

$content = readSource($source);
if ($content === false || trim($content) === '') {
	fwrite(STDERR, "Unable to read feed source.\n");
	exit(1);
}

$normalized = str_replace(array("\r\n", "\r"), "\n", $content);
$records = 0;
foreach (explode("\n", $normalized) as $line_number => $line) {
	if (trim($line) === '') {
		continue;
	}
	json_decode($line, true);
	if (json_last_error() !== JSON_ERROR_NONE) {
		fwrite(STDERR, 'Invalid JSON on line ' . ($line_number + 1) . ': ' . json_last_error_msg() . "\n");
		exit(1);
	}
	$records++;
}
if ($records === 0) {
	fwrite(STDERR, "Feed has no records.\n");
	exit(1);
}

$directory = dirname($output);
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
	fwrite(STDERR, "Unable to create output directory.\n");
	exit(1);
}

$temporary = $output . '.tmp-' . getmypid();
$compressed = gzencode(rtrim($normalized, "\n") . "\n", 9, ZLIB_ENCODING_GZIP);
if ($compressed === false || file_put_contents($temporary, $compressed, LOCK_EX) === false) {
	@unlink($temporary);
	fwrite(STDERR, "Unable to write compressed snapshot.\n");
	exit(1);
}
if (!rename($temporary, $output)) {
	@unlink($temporary);
	fwrite(STDERR, "Unable to atomically publish compressed snapshot.\n");
	exit(1);
}

echo 'OK: Wrote ' . $records . ' records to ' . $output . ' (' . filesize($output) . " bytes).\n";

function readSource($source) {
	if (is_readable($source) && !preg_match('#^https?://#i', $source)) {
		return file_get_contents($source);
	}
	$ch = curl_init($source);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 300,
		CURLOPT_USERAGENT => 'GlazbenaKutija-OpenAISnapshot/1.0',
		CURLOPT_SSL_VERIFYPEER => strpos($source, '.test') === false
	));
	$body = curl_exec($ch);
	$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	return $body === false || $status >= 400 ? false : $body;
}
