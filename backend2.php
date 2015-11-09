<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once('vendor/autoload.php');
require_once('PNXTranslator.php');

use \BCLib\PrimoServices\PrimoServices;
use \BCLib\PrimoServices\Query;
use \BCLib\PrimoServices\QueryTerm;

class Router
{

	protected $host = 'bibsys-primo.hosted.exlibrisgroup.com'; //Your Primo host.
	#$host = 'http://bc-primo.hosted.exlibrisgroup.com';
	protected $inst = 'UBO'; // Your Primo institution code.
	#$inst = 'BCU'; // Your Primo institution code.

	function __construct()
	{
		$this->primo = new PrimoServices($this->host, $this->inst);
		$this->primo['pnx_translator'] = function () {
			return new PNXTranslator();
		};
	}

	public function route($get)
	{
		$action = $get['action'];

		switch ($action) {
			case 'search':
				$this->search($get);
				exit;
			case 'getWork':
				$this->getWork($get);
				exit;
		}
		die("No/unknown action");
	}

	public function makeSearchQuery($get) {

		$query = new Query($this->inst);

		$searchTerm = isset($get['query']) ? $get['query'] : '';
		$searchTerm = filter_var($searchTerm, FILTER_SANITIZE_STRING);
		if (empty($searchTerm)) {
			echo json_encode(array(
				'error' => 'No \'query\' parameter given'
			));
			exit;
		}

		$idx = isset($get['idx']) ? $get['idx'] : 'villvest';

		$term = new QueryTerm();
		if ($idx == 'rt') {
			$term->set('lsr20', QueryTerm::EXACT, $searchTerm);  // Realfagstermer
		} else if ($idx == 'humord') {
			$term->set('lsr14', QueryTerm::EXACT, $searchTerm);  // Humord
		} else {
			$term->keyword($searchTerm);
		}

		$term2 = new QueryTerm();
		$term2->set('facet_creator', QueryTerm::EXACT, 'Ganugi G'); // REMEBER: REPLACE , with space!!

		$term3 = new QueryTerm();
		$term3->set('facet_rtype', QueryTerm::EXACT, 'journals');

		$lang = isset($get['lang']) ? $get['lang'] : 'eng';

		$scopes = isset($get['scope']) ? explode(',', $get['scope']) : array();

		$query->addTerm($term)
			//->addTerm($term2)
			//->addTerm($term3, 'include')
			->onCampus(true)
			->lang($lang)
			->sortField('date')
			->start(1)     // start(0) may give LESS RESULTS, should send a pull request to make 1 the default
			->bulkSize(20);

		if (in_array('ubo', $scopes)) $query->local('"UBO"')->local('SC_OPEN_ACCESS');
		if (in_array('bibsys', $scopes)) $query->local('BIBSYS_ILS');
		if (in_array('duo', $scopes)) $query->local('DUO');
		if (in_array('primo', $scopes)) $query->articles();

		//	->local('BIBSYS_ILS');

			// 
			// ->local('DUO')
			// 
			//->articles();

		// defaults to: local,scope:(<institution code>)
		//$query->loc('scope:(SC_OPEN_ACCESS),scope:(DUO),scope:("UBO"),primo_central_multiple_fe');

		return $query;
	}

	public function search($get) {

		$query = $this->makeSearchQuery($get);

		$rs = $this->primo->search($query);

		$out = array(
			'query' => strval($query),
			'total_results' => intval($rs->total_results),
			'docs' => $rs->results,
			'facets' => $rs->facets
		);
		if ($rs->error) {
			$out['error'] = $rs->error;
			$out['raw_response'] = $rs->raw_response;
		}
		echo json_encode($out);
		exit;
	}

	public function getWork($get)
	{

		$query = new Query($this->inst);
		$workId = $get['workId'];

		$term = new QueryTerm();
		$term->set('facet_frbrgroupid', QueryTerm::EXACT, $workId);

		$lang = isset($get['lang']) ? $get['lang'] : 'eng';

		$query->addTerm($term)
			->onCampus(true)
			->lang($lang)
			->start(1)     // start(0) may give LESS RESULTS, should send a pull request to make 1 the default
			->bulkSize(20);

		$scopes = isset($get['scope']) ? explode(',', $get['scope']) : array();
		if (in_array('ubo', $scopes)) $query->local('"UBO"')->local('SC_OPEN_ACCESS');
		if (in_array('bibsys', $scopes)) $query->local('BIBSYS_ILS');


		$rs = $this->primo->search($query);

		$out = array(
			'query' => strval($query),
			'total_results' => intval($rs->total_results),
			'docs' => $rs->results,
			'facets' => $rs->facets
		);
		if ($rs->error) $out['error'] = $rs->error;
		echo json_encode($out);
		exit;
	}

}

header("Content-Type: application/json; charset=utf-8");
$r = new Router;
$r->route($_GET);
