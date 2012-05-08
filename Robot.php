<?php

class Robot {
	function get($url, $headers = array('Accept: text/html')) {
		print "Fetching $url\n";

	    $curl = curl_init();

	    curl_setopt_array($curl, array(
	        CURLOPT_URL => $url,
	        CURLOPT_HTTPHEADER => $headers,
	        CURLOPT_CONNECTTIMEOUT => 10,
	        CURLOPT_TIMEOUT => 10,
	        CURLOPT_FOLLOWLOCATION => true,
	        CURLOPT_MAXREDIRS => 10,
	        CURLOPT_RETURNTRANSFER => true,
	        CURLOPT_USERAGENT => 'How A Robot Reads PubMed',
	        CURLOPT_VERBOSE => true,
	        CURLOPT_NOPROGRESS => false,
	    ));

	    $response = array(
	        'data' => curl_exec($curl),
	        'url' => curl_getinfo($curl, CURLINFO_EFFECTIVE_URL),
	        'status' => curl_getinfo($curl, CURLINFO_HTTP_CODE),
	        'type' => strtolower(trim(array_shift(explode(';', curl_getinfo($curl, CURLINFO_CONTENT_TYPE), 2)))),
	    );

	    return $this->parse_response($response);
	}

	function parse_response($response) {
	    if ($response['status'] !== 200) throw new Exception('Error fetching data from ' . $url);

	    switch ($response['type']) {
	        case 'text/html':
	            $dom = new DOMDocument();
	            $dom->documentURI = $response['url'];
	            @$dom->loadHTML($response['data']); // parse HTML
	            $response['xpath'] = new DOMXPath($dom);
	        break;

	        case 'text/xml':
	        case 'application/xml':
	        case 'application/xhtml+xml':
	        case 'application/opensearchdescription+xml':
	            $dom = new DOMDocument();
	            $dom->documentURI = $response['url'];
	            $dom->loadXML($response['data']); // parse XML
	            $response['xpath'] = new DOMXPath($dom);
	        break;

	        case 'application/json':
	            $response['data'] = json_decode($response['data'], true); // parse JSON to an array
	        break;

	        case 'text/plain':
	            // leave plain text alone
	        break;

	        default:
	            throw new Exception('Unknown response format: ' . $response['type']);
	        break;
	    }

	    return $response;
	}

			// http://stackoverflow.com/a/4444490/145899
	function rel2abs($rel, $base){
	    /* return if already absolute URL */
	    if (parse_url($rel, PHP_URL_SCHEME) != '') return $rel;

	    /* queries and anchors */
	    if ($rel[0] == '#' || $rel[0] == '?') return $base . $rel;

	    /* parse base URL and convert to local variables: $scheme, $host, $path */
	    extract(parse_url($base));

	    /* remove non-directory element from path */
	    $path = preg_replace('#/[^/]*$#', '', $path);

	    /* destroy path if relative url points to root */
	    if ($rel[0] == '/') $path = '';

	    /* dirty absolute URL */
	    $abs = $host . $path . '/' . $rel;

	    /* replace '//' or '/./' or '/foo/../' with '/' */
	    $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
	    for($n = 1; $n > 0; $abs = preg_replace($re, '/', $abs, -1, $n)) {}

	    /* absolute URL is ready! */
	    return $scheme . '://' . $abs;
	}

	function findHandheldURL($response) {
		//return 'http://www.ncbi.nlm.nih.gov/m/pubmed/';
		$nodes = $response['xpath']->query("//*[local-name() = 'a' or local-name() = 'link'][@rel = 'alternate'][@media = 'handheld']");
		if (!$nodes->length) throw new Exception('No handheld version was found');
		return $this->rel2abs($nodes->item(0)->getAttribute('href'), $response['url']);
	}

	function findSearchDescriptionURL($response){
		//return 'http://www.ncbi.nlm.nih.gov/corehtml/query/static/pubmedsearch.xml';
		$nodes = $response['xpath']->query("//*[local-name() = 'a' or local-name() = 'link'][@rel = 'search'][@type = 'application/opensearchdescription+xml']");
		if (!$nodes->length) throw new Exception('No OpenSearch description document was found');
		return $this->rel2abs($nodes->item(0)->getAttribute('href'), $response['url']);
	}

	function chooseSearchTemplate($response) {
		//return 'http://www.ncbi.nlm.nih.gov/sites/entrez?db=pubmed&cmd=search&term={searchTerms}';
		$response['xpath']->registerNamespace('opensearch', 'http://a9.com/-/spec/opensearch/1.1/');
		$nodes = $response['xpath']->query("opensearch:Url[@type = 'text/html'][@rel = 'results' or string-length(@rel) = 0]");
		if (!$nodes->length) throw new Exception('No search template found');
		return $this->rel2abs($nodes->item(0)->getAttribute('template'), $response['url']);
	}

	function fillURITemplate($url, $params = array()) {
		$callback = function($matches) use ($params){
			$field = $matches[1];
			if (array_key_exists($field, $params)) return $params[$field];
		};

		return preg_replace_callback('/\{(\w+)\??\}/', $callback, $url);
	}

	function findChapters($response) {
		/*
		return array(array(
			'url' => 'http://www.ncbi.nlm.nih.gov/m/pubmed/22554834/?i=6&from=FOXP3',
			'title' => 'The therapeutic effects of daphnetin in collagen-induced arthritis involve its regulation of Th17 cells.',
		));
		*/
		//$response['xpath']->registerNamespace('xhtml', 'http://www.w3.org/1999/xhtml'); // served as text/html, but is XHTML
		//$nodes = $response['xpath']->query("//xhtml:a[@rel = 'chapter']"); // http://wiki.whatwg.org/wiki/RelExtensions
		$nodes = $response['xpath']->query("//a[@rel = 'chapter']"); // http://wiki.whatwg.org/wiki/RelExtensions
		if (!$nodes->length) throw new Exception('No search results found');

		$items = array();
		foreach ($nodes as $node) {
			$items[] = array(
				'url' => $this->rel2abs($node->getAttribute('href'), $response['url']),
				'title' => $node->textContent,
			);
		}
		return $items;
	}
}