<?php

require 'include.php';

$url = 'http://pubmed.gov/';

function getHandheldURL($url) {
	$response = curl_get($url);

	$nodes = $response['xpath']->query("//*[local-name() = 'a' or local-name() = 'link'][@rel = 'alternate'][@media = 'handheld']");
	if (!$nodes->length) throw new Exception('No handheld version was found');
	return rel2abs($nodes->item(0)->getAttribute('href'), $response['url']);
}

$url = getHandheldURL($url); print "$url\n";
//$url = 'http://www.ncbi.nlm.nih.gov/m/pubmed/';

function getSearchDescription($url){
	$response = curl_get($url);

	$nodes = $response['xpath']->query("//*[local-name() = 'a' or local-name() = 'link'][@rel = 'search'][@type = 'application/opensearchdescription+xml']");
	if (!$nodes->length) throw new Exception('No OpenSearch description document was found');
	return rel2abs($nodes->item(0)->getAttribute('href'), $response['url']);
}

$url = getSearchDescription($url); print "$url\n";
//$url = 'http://www.ncbi.nlm.nih.gov/corehtml/query/static/pubmedsearch.xml';

function getSearchTemplate($url) {
	$response = curl_get($url, array('Accept: application/opensearchdescription+xml'));
	$response['xpath']->registerNamespace('opensearch', 'http://a9.com/-/spec/opensearch/1.1/');

	$nodes = $response['xpath']->query("opensearch:Url[@type = 'text/html'][@rel = 'results' or string-length(@rel) = 0]");
	if (!$nodes->length) throw new Exception('No search template found');
	return rel2abs($nodes->item(0)->getAttribute('template'), $response['url']);
}

$template = getSearchTemplate($url); print "$template\n";
//$template = 'http://www.ncbi.nlm.nih.gov/sites/entrez?db=pubmed&cmd=search&term={searchTerms}';

function fillURITemplate($url, $params = array()) {
	$callback = function($matches) use ($params){
		$field = $matches[1];
		if (array_key_exists($field, $params)) return $params[$field];
	};

	return preg_replace_callback('/\{(\w+)\??\}/', $callback, $url);
}

$url = fillURITemplate($template, array('searchTerms' => 'FOXP3')); print "$url\n";
// only HTML, no machine-readable items or links to alternates

function getSearchResults($url) {
	$response = curl_get($url);
	//$response['xpath']->registerNamespace('xhtml', 'http://www.w3.org/1999/xhtml'); // served as text/html, but is XHTML
	//$nodes = $response['xpath']->query("//xhtml:a[@rel = 'chapter']"); // http://wiki.whatwg.org/wiki/RelExtensions
	$nodes = $response['xpath']->query("//a[@rel = 'chapter']"); // http://wiki.whatwg.org/wiki/RelExtensions
	if (!$nodes->length) throw new Exception('No search results found');

	$items = array();
	foreach ($nodes as $node) {
		$items[] = array(
			'url' => rel2abs($node->getAttribute('href'), $response['url']),
			'title' => $node->textContent,
		);
	}
	return $items;
}

$items = getSearchResults($url); print_r($items);
$item = $items[3]; print_r($item); // TODO: pick interesting items (not seen already?)

/*
$item = array(
	'url' => 'http://www.ncbi.nlm.nih.gov/m/pubmed/22554834/?i=6&from=FOXP3',
	'title' => 'The therapeutic effects of daphnetin in collagen-induced arthritis involve its regulation of Th17 cells.',
);
*/

// FIXME: no way to detect the full-text URL, without resorting to eUtils
