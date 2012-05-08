<?php

require 'Robot.php';

$robot = new Robot;

$url = 'http://pubmed.gov/';
$response = $robot->get($url);
$url = $robot->findHandheldURL($response); // handheld version has more structured HTML
$response = $robot->get($url);
$url = $robot->findSearchDescriptionURL($response);
$response = $robot->get($url, array('Accept: application/opensearchdescription+xml'));
$template = $robot->chooseSearchTemplate($response);
$url = $robot->fillURITemplate($template, array('searchTerms' => 'FOXP3'));
$response = $robot->get($url);
$items = $robot->findChapters($response); print_r($items);
$item = $items[0]; 
print_r($item); // TODO: pick interesting items (not seen already?)

// FIXME: no way to detect the full-text URL, without resorting to eUtils
