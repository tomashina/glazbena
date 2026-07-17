<?php

$base_url = isset($argv[1]) ? rtrim($argv[1], '/') : 'https://www.glazbena-kutija.hr';
$paths = array('/', '/robots.txt', '/sitemap-index.xml', '/llms.txt', '/openai-products.jsonl?limit=1');
$agents = array(
	'OAI-SearchBot' => 'Mozilla/5.0 (compatible; OAI-SearchBot/1.4; +https://openai.com/searchbot)',
	'ChatGPT-User' => 'Mozilla/5.0 (compatible; ChatGPT-User/1.0; +https://openai.com/bot)',
	'Claude-SearchBot' => 'Claude-SearchBot/1.0',
	'Claude-User' => 'Claude-User/1.0',
	'PerplexityBot' => 'Mozilla/5.0 (compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)',
	'Perplexity-User' => 'Mozilla/5.0 (compatible; Perplexity-User/1.0; +https://perplexity.ai/perplexity-user)',
	'Googlebot' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
	'Bingbot' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'
);
$errors = array();

foreach ($agents as $name => $user_agent) {
	foreach ($paths as $path) {
		$status = fetchStatus($base_url . $path, $user_agent);
		echo str_pad($name, 18) . ' ' . str_pad($path, 38) . ' HTTP ' . $status . "\n";
		if ($status < 200 || $status >= 400) {
			$errors[] = $name . ' receives HTTP ' . $status . ' for ' . $path;
		}
	}
}

if ($errors) {
	echo "FAIL\n";
	foreach ($errors as $error) {
		echo '- ' . $error . "\n";
	}
	exit(1);
}

echo "OK: Discovery crawler endpoints are reachable.\n";

function fetchStatus($url, $user_agent) {
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_USERAGENT => $user_agent,
		CURLOPT_SSL_VERIFYPEER => strpos($url, '.test') === false
	));
	curl_exec($ch);
	$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	return $status;
}
