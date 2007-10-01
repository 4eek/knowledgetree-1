<?php

session_start();
require_once("config/dmsDefaults.php");
require_once(KT_LIB_DIR . "/unitmanagement/Unit.inc");

require_once(KT_LIB_DIR . "/templating/templating.inc.php");
require_once(KT_LIB_DIR . "/dispatcher.inc.php");
require_once(KT_LIB_DIR . "/widgets/forms.inc.php");
require_once(KT_LIB_DIR . "/actions/bulkaction.php");
require_once(KT_DIR . '/search2/search/search.inc.php');


class SearchDispatcher extends KTStandardDispatcher {

	private $curUserId;
	private $sysAdmin;
	private $savedSearchId;

	const RESULTS_PER_PAGE = 25;
	const MAX_PAGE_MOVEMENT = 10;

	public function __construct()
	{
		parent::KTStandardDispatcher();

		$this->curUserId = $_SESSION['userID'];

		$this->sysAdmin=Permission::userIsSystemAdministrator();

		if (array_key_exists('fSavedSearchId',$_GET))
		{
			$this->savedSearchId = sanitizeForSQL($_GET['fSavedSearchId']);
		}
	}

    function do_main()
    {
    	redirect(KTBrowseUtil::getBrowseBaseUrl());
    }

    /**
     * This proceses any given search expression.
     * On success, it redirects to the searchResults page.
     *
     * @param string $query
     */
    private function processQuery($query)
    {
    	try
    	{
    		$expr = parseExpression($query);

    		$result = $expr->evaluate();
    		usort($result, 'rank_compare');

    		$_SESSION['search2_results'] = serialize($result);
    		$_SESSION['search2_query'] = $query;
    		$_SESSION['search2_sort'] = 'rank';

    		$this->redirectTo('searchResults');
    	}
    	catch(Exception $e)
    	{
    		$this->errorRedirectTo('guiBuilder', _kt('Could not process query.') . $e->getMessage());
    	}
    }

    /**
     * Processes a query sent by HTTP POST in searchQuery.
     *
     */
    function do_process()
    {
    	if (empty($_REQUEST['txtQuery']))
    	{
    		$this->errorRedirectTo('searchResults', _kt('Please reattempt the query. The query is missing.'));
    	}
    	$query = $_REQUEST['txtQuery'];

		session_unregister('search2_savedid');

    	$this->processQuery($query);
    }

    /**
     * Returns the saved query is resolved from HTTP GET fSavedSearchId field.
     *
     * @return mixed False if error, else string.
     */
    private function getSavedExpression()
    {
    	if (is_null($this->savedSearchId))
		{
			$this->errorRedirectToParent(_kt('The saved search id was not passed correctly.'));
		}
		$_SESSION['search2_savedid'] = $this->savedSearchId;

		$sql = "SELECT name, expression FROM search_saved WHERE type='S' AND id=$this->savedSearchId";
		if (!$this->sysAdmin)
		{
			$sql .= "  and ( user_id=$this->curUserId OR shared=1 ) ";
		}

		$query = DBUtil::getOneResult($sql);
		if (PEAR::isError($query))
		{
			$this->errorRedirectToParent(_kt('The saved search could not be resolved.'));
		}

		$_SESSION['search2_savedname'] = $query['name'];
		return array($query['name'],$query['expression']);
    }

    /**
     * Processes a saved query HTTP GET fSavedSearchId
     *
     */
    function do_processSaved()
    {
    	list($name, $expr) = $this->getSavedExpression();

		$this->processQuery($expr);
    }

    /**
     * Renders the search results.
     *
     * @return string
     */
    function do_searchResults()
    {
		$this->oPage->setBreadcrumbDetails(_kt("Search Results"));
        $this->oPage->title = _kt("Search Results");

    	$oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate("ktcore/search2/search_results");

        $results = unserialize($_SESSION['search2_results']);

        if (!is_array($results)  || count($results) == 0)
        {
        	$results=array();
        }

        $numRecs = count($results);
        $showall = $_GET['showAll'];
		if (is_numeric($showall))
		{
			$showall = ($showall+0) > 0;
		}
		else
		{
			$showall = ($showall == 'true');
		}
		$config = KTConfig::getSingleton();
		$resultsPerPage = ($showall)?$numRecs:$config->get('search/resultsPerPage', SearchDispatcher::RESULTS_PER_PAGE);

        $maxPageMove = SearchDispatcher::MAX_PAGE_MOVEMENT;

        $pageOffset = 1;
        if (isset($_GET['pageOffset']))
        {
        	$pageOffset = $_GET['pageOffset'];
        }

        $maxPages = ceil($numRecs / $resultsPerPage) ;
        if ($pageOffset <= 0 || $pageOffset > $maxPages)
        {
        	$pageOffset = 1;
        }

         $firstRec = ($pageOffset-1) * $resultsPerPage;
         $lastRec = $firstRec + $resultsPerPage;
         if ($lastRec > $numRecs)
         {
         	$lastRec = $numRecs;
         }

        $display = array_slice($results,$firstRec ,$resultsPerPage);

        $startOffset = $pageOffset - $maxPageMove;
        if ($startOffset < 1)
        {
        	$startOffset = 1;
        }
        $endOffset = $pageOffset + $maxPageMove;
        if ($endOffset > $maxPages)
        {
        	$endOffset = $maxPages;
        }

		$pageMovement = array();
		for($i=$startOffset;$i<=$endOffset;$i++)
		{
			$pageMovement[] = $i;
		}

		 $aBulkActions = KTBulkActionUtil::getAllBulkActions();

        $aTemplateData = array(
              "context" => $this,
              'bulkactions'=>$aBulkActions,
              'firstRec'=>$firstRec,
              'lastRec'=>$lastRec,
              'showAll'=>$showall,
              'numResults' => count($results),
              'pageOffset' => $pageOffset,
              'resultsPerPage'=>$resultsPerPage,
              'maxPages' => $maxPages,
              'results' => $display,
              'pageMovement'=>$pageMovement,
              'startMovement'=>$startOffset,
              'endMovement'=>$endOffset,
              'txtQuery' => $_SESSION['search2_query'],
              'iSavedID' => $_SESSION['search2_savedid'],
              'txtSavedName' => $_SESSION['search2_savedname']
        );

        return $oTemplate->render($aTemplateData);
    }

	function do_manage()
	{
		$this->oPage->setBreadcrumbDetails(_kt("Manage Saved Searches"));
        $this->oPage->title = _kt("Manage Saved Searches");

		$sql = "SELECT ss.id, ss.name, u.name as username, user_id is not null as editable, shared
				FROM search_saved ss
				LEFT OUTER JOIN users u on ss.user_id = u.id
				WHERE ss.type='S' ";

		if (!$this->sysAdmin)
		{
			$sql .= " AND (ss.user_id=$this->curUserId OR ss.shared=1)";
		}

		$saved = DBUtil::getResultArray($sql);

        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate("ktcore/search2/manage_saved_search");
        $aTemplateData = array(
              "context" => $this,
              'saved'=>$saved,
              'sysadmin'=>$this->sysAdmin
        );

        return $oTemplate->render($aTemplateData);
	}

	function do_share()
	{
		if (is_null($this->savedSearchId))
		{
			$this->errorRedirectTo('manage', _kt('The saved search id was not passed correctly.'));
		}
		if (!array_key_exists('share',$_GET))
		{
			$this->errorRedirectTo('manage', _kt('The sharing option was not passed correctly.'));
		}

		if ($_GET['share']=='no')
		{
			$share=0;
			$msg = _kt("The saved search can only be seen by you.");
		}
		else
		{
			$share=1;
			$msg = _kt("The saved search is now visible to all users.");
		}


		$sql = "UPDATE search_saved SET shared=$share WHERE type='S' AND id=$this->savedSearchId";
		if (!$this->sysAdmin)
		{
			$sql .= " AND ss.user_id=$this->curUserId";
		}

		DBUtil::runQuery($sql);
		$this->successRedirectTo('manage', $msg);

	}

	function do_delete()
	{
		if (is_null($this->savedSearchId))
		{
			$this->errorRedirectTo('manage', _kt('The saved search id was not passed correctly.'));
		}

		$sql = "DELETE FROM search_saved WHERE type='S' AND id=$this->savedSearchId";
		if (!$this->sysAdmin)
		{
			$sql .= " AND user_id=$this->curUserId ";
		}


		DBUtil::runQuery($sql);
		$this->successRedirectTo('manage', _kt('The saved search was deleted successfully.'));

	}

	function do_guiBuilder()
	{
		$this->oPage->setBreadcrumbDetails(_kt("Advanced Search"));
        $this->oPage->title = _kt("Advanced Search");

		$result = array();

		// TODO: need to escape the parameters

		$result['fieldsets'] = SearchHelper::getFieldsets();
		$result['fieldset_str'] = SearchHelper::getJSfieldsetStruct($result['fieldsets']);

		$result['workflows'] = SearchHelper::getWorkflows();
        $result['workflow_str'] = SearchHelper::getJSworkflowStruct($result['workflows']);

		$result['fields'] = SearchHelper::getSearchFields();
		$result['fields_str'] = SearchHelper::getJSfieldsStruct($result['fields']);

		$result['users_str'] = SearchHelper::getJSusersStruct();
		$result['mimetypes_str'] = SearchHelper::getJSmimeTypesStruct();
		$result['documenttypes_str'] = SearchHelper::getJSdocumentTypesStruct();

        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate("ktcore/search2/adv_query_builder");
        $aTemplateData = array(
              "context" => $this,
              'metainfo'=> $result
        );

        return $oTemplate->render($aTemplateData);
	}

	function do_queryBuilder()
	{
		$this->oPage->setBreadcrumbDetails(_kt("Query Editor"));
        $this->oPage->title = _kt("Query Editor");
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate("ktcore/search2/adv_query_search");


        $registry = ExprFieldRegistry::getRegistry();
        $aliases = $registry->getAliasNames();
        sort($aliases);

        $edit = is_numeric($this->savedSearchId);
        $name = '';
        if ($edit)
        {
			list($name, $expr) = $this->getSavedExpression();
        }
        else
        {
        	$expr = $_SESSION['search2_query'];
        }

        $aTemplateData = array(
              "context" => $this,
              'aliases' => $aliases,
              'bSave'=>$edit,
              'edtSaveQueryName'=>$name,
              'txtQuery'=>$expr,
              'iSavedSearchId'=>$this->savedSearchId

        );
        return $oTemplate->render($aTemplateData);
	}
}


$oDispatcher = new SearchDispatcher();
$oDispatcher->dispatch();

?>