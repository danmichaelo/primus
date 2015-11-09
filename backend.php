<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/vendor/autoload.php';
use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement;

function search($query) {
	//$querySub = 'sub,contains,' . $searchTerm; // Alle emneord
	//$query3 = 'lsr14,exact,' . $searchTerm; // HUMORD
	//$query4 = 'lsr12,exact,' . $searchTerm; // TEKORD

	$base_url = 'http://bibsys-primo.hosted.exlibrisgroup.com/PrimoWebServices/xservice/search/brief?';
	$query = array(
		'institution' => 'UBO',	  // Relevant for restricted scopes or for when searching against Primo Central
		'query' => $query,
		//'query_inc' => 'local20,exact,' . $searchTerm,
		'onCampus' => 'true',	  // Relevant for restricted scopes
								  // Men nøyaktig hva gjør den?
		'indx' => 1,	  	// first record
		'bulkSize' => 20, 	// number of records
		'sortField' => 'scdate', // sort by date desc
	//	'loc' => 'local,scope:(UBO)',
	//	'loc' => 'adaptor,primo_central_multiple_fe',
	);
	$url = $base_url . http_build_query($query);
	$result = file_get_contents($url);

	$doc = new QuiteSimpleXMLElement($result);
	$doc->registerXPathNamespace('p', 'http://www.exlibrisgroup.com/xsd/primo/primo_nm_bib');
	$doc->registerXPathNamespace('s', 'http://www.exlibrisgroup.com/xsd/jaguar/search');

	$result = $doc->first('/s:SEGMENTS/s:JAGROOT/s:RESULT');

	if ($err = $result->first('s:ERROR')) {
		echo json_encode(array(
			'error' => $err->attr('MESSAGE')
		));
		exit;
	}

	$docset = $result->first('s:DOCSET');

	$results = array(
		'url' => $url,
		'time' => $docset->attr('TOTAL_TIME'),
		'hits' => $docset->attr('TOTALHITS'),
		'docs' => array(),
	);

	$ignore_keys = array('availlibrary', 'availinstitution', 'availpnx');
	foreach ($docset->xpath('s:DOC') as $searDoc) {

		$doc = array(
			'libraries' => array(),
			'thumbnails' => array(),
		);

		// CONTROL:

		$searControl = $searDoc->first('p:PrimoNMBib/p:record/p:control');
		$doc['sourcerecordid'] = $searControl->text('p:sourcerecordid');
		$doc['sourceid'] = $searControl->text('p:sourceid');
		$doc['realfagstermer'] = array();
		$doc['humord'] = array();
		$doc['tekord'] = array();

		// DISPLAY:
		foreach ($searDoc->first('p:PrimoNMBib/p:record/p:display')->children('p') as $kid) {
			$n = $kid->getName();
			$v = $kid->text();
			if (!in_array($n, $ignore_keys)) {
				$doc[$n] = $v;
			}
		}

		// EMNEORD:
		foreach ($searDoc->xpath('p:PrimoNMBib/p:record/p:search/p:lsr20') as $kid) {
			$doc['realfagstermer'][] = $kid->text();
		}
		foreach ($searDoc->xpath('p:PrimoNMBib/p:record/p:search/p:lsr14') as $kid) {
			$doc['humord'][] = $kid->text();
		}
		foreach ($searDoc->xpath('p:PrimoNMBib/p:record/p:search/p:lsr12') as $kid) {
			$doc['tekord'][] = $kid->text();
		}

		// LIBRARIES:
		foreach ($searDoc->xpath('s:LIBRARIES/s:LIBRARY') as $searLib) {
			$lib = array();
			foreach ($searLib->children('s') as $kid) {
				$n = $kid->getName();
				$v = $kid->text();
				if (!in_array($n, $ignore_keys)) {
					$lib[$n] = $v;
				}
			}
			$doc['libraries'][] = $lib;
		}

		// LINKS:
		foreach ($searDoc->xpath('s:LINKS/s:thumbnail') as $searThumb) {
			$v = $searThumb->text();
			if (!empty($v)) {
				$doc['thumbnails'][] = $v;
			}
		}

		$results['docs'][] = $doc;
	}
	return $results; 
}


/*

	In order to point to Primo Central scope you should give in the URL: 
	loc=adaptor,primo_central_multiple_fe 
	 
	Pointing to a blended scope is also possible; in this case the “loc” 
	parameter must be given twice in the URL: 
	loc=adaptor,primo_central_multiple_fe&loc=<type,value>

	loc=local,scope:(scopeName) 
	loc=remote,

*/

$searchTerm = isset($_GET['query']) ? $_GET['query'] : '';
$searchTerm = filter_var($searchTerm, FILTER_SANITIZE_STRING);
if (empty($searchTerm)) {
	echo json_encode(array(
		'error' => 'No \'query\' parameter given'
	));
	exit;
}

$idx = isset($_GET['idx']) ? $_GET['idx'] : 'villvest';

if ($idx == 'rt') {
	$query = 'lsr20,exact,' . $searchTerm; // Realfagstermer
} else if ($idx == 'humord') {
	$query = 'lsr14,exact,' . $searchTerm; // HUMORD
} else {
	$query = 'any,contains,' . $searchTerm;
}


header("Content-Type: application/json; charset=utf-8");
$results = search($query);

echo json_encode($results);

