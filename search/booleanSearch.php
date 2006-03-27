<?php

/**
 * $Id$
 *
 * Copyright (c) 2006 Jam Warehouse http://www.jamwarehouse.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; using version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * -------------------------------------------------------------------------
 *
 * You can contact the copyright owner regarding licensing via the contact
 * details that can be found on the KnowledgeTree web site:
 *
 *         http://www.ktdms.com/
 */

// boilerplate includes
require_once("../config/dmsDefaults.php");
require_once(KT_LIB_DIR . "/templating/templating.inc.php");
require_once(KT_LIB_DIR . "/database/dbutil.inc");
require_once(KT_LIB_DIR . "/util/ktutil.inc");
require_once(KT_LIB_DIR . "/dispatcher.inc.php");
require_once(KT_LIB_DIR . "/browse/Criteria.inc");
require_once(KT_LIB_DIR . "/search/savedsearch.inc.php");
require_once(KT_LIB_DIR . "/search/searchutil.inc.php");

require_once(KT_LIB_DIR . "/browse/DocumentCollection.inc.php");
require_once(KT_LIB_DIR . "/browse/BrowseColumns.inc.php");
require_once(KT_LIB_DIR . "/browse/PartialQuery.inc.php");

class BooleanSearchDispatcher extends KTStandardDispatcher {
    var $sSection = "browse";

    function BooleanSearchDispatcher() {
        $this->aBreadcrumbs = array(
            array('action' => 'browse', 'name' => _kt('Browse')),
        );
        return parent::KTStandardDispatcher();
    }

   function do_main() {
        $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _kt("Advanced Search"));
        $this->oPage->setBreadcrumbDetails(_kt('defining search'));
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate("ktcore/boolean_search");
        
        $aCriteria = Criteria::getAllCriteria();
        
        $aTemplateData = array(
            "context" => &$this,
            "aCriteria" => $aCriteria,
        );
        return $oTemplate->render($aTemplateData);
    }

    function do_performSearch() {
        $title = null;
        $datavars = KTUtil::arrayGet($_REQUEST, 'boolean_search');

        if (!is_array($datavars)) {
            $datavars = unserialize($datavars);
        }
        $boolean_search_id = KTUtil::arrayGet($_REQUEST, 'boolean_search_id');
        if ($boolean_search_id) {
            $datavars = $_SESSION['boolean_search'][$boolean_search_id];
        }
        $iSavedSearchId = KTUtil::arrayGet($_REQUEST, 'fSavedSearchId');
        if (!empty($iSavedSearchId)) {
            $oSearch = KTSavedSearch::get($iSavedSearchId);
            $datavars = $oSearch->getSearch();
            $title = $oSearch->getName();
        }

        if (is_null(KTUtil::arrayGet($datavars["subgroup"][0], "values"))) {
            $this->errorRedirectToMain("No search parameters given");
        }
        
        if (empty($datavars)) {
            $this->errorRedirectToMain(_kt('You need to have at least 1 condition.'));
        }
        
        $res = $this->handleCriteriaSet($datavars, KTUtil::arrayGet($_REQUEST, 'fStartIndex', 1), $title);
        
        return $res;
    }
    
    function handleCriteriaSet($aCriteriaSet, $iStartIndex, $sTitle=null) {
        
        if ($sTitle == null) {
            $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _kt('Advanced Search'));
            $sTitle =  _kt('Search Results');
        } else {
           $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _kt('Saved Search'));
            $this->oPage->setTitle(_kt('Saved Search: ') . $sTitle);
        }
        $this->oPage->setBreadcrumbDetails($sTitle);
        
        $collection = new DocumentCollection;
        $this->browseType = "Folder";

        //$collection->addColumn(new SelectionColumn("Browse Selection","selection"));
        $t =& new TitleColumn("Test 1 (title)","title");
        $t->setOptions(array('documenturl' => $GLOBALS['KTRootUrl'] . '/view.php'));
        $collection->addColumn($t);
        $collection->addColumn(new DownloadColumn('','download'));
        $collection->addColumn(new DateColumn(_kt("Created"),"created", "getCreatedDateTime"));
        $collection->addColumn(new DateColumn(_kt("Last Modified"),"modified", "getLastModifiedDate"));
        $collection->addColumn(new UserColumn(_kt('Creator'),'creator_id','getCreatorID'));
        $collection->addColumn(new WorkflowColumn(_kt('Workflow State'),'workflow_state'));

        $searchable_text = KTUtil::arrayGet($_REQUEST, "fSearchableText");

        $batchPage = (int) KTUtil::arrayGet($_REQUEST, "page", 0);
        $batchSize = 20;

        $sSearch = md5(serialize($aCriteriaSet));
        $_SESSION['boolean_search'][$sSearch] = $aCriteriaSet;
        $resultURL = KTUtil::addQueryStringSelf("action=performSearch&boolean_search_id=" . urlencode($sSearch));
        $collection->setBatching($resultURL, $batchPage, $batchSize);


        // ordering. (direction and column)
        $displayOrder = KTUtil::arrayGet($_REQUEST, 'sort_order', "asc");
        if ($displayOrder !== "asc") { $displayOrder = "desc"; }
        $displayControl = KTUtil::arrayGet($_REQUEST, 'sort_on', "title");

        $collection->setSorting($displayControl, $displayOrder);

        // add in the query object.
        $qObj = new BooleanSearchQuery($aCriteriaSet);
        $collection->setQueryObject($qObj);

        $collection->getResults();
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate("kt3/browse");
        $aTemplateData = array(
              "context" => $this,
              "collection" => $collection,
              "custom_title" => $sTitle,
        );
        return $oTemplate->render($aTemplateData);
    }
}

$oDispatcher = new BooleanSearchDispatcher();
$oDispatcher->dispatch();

?>
