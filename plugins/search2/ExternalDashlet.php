<?php

/**
 * $Id:$
 *
 * KnowledgeTree Open Source Edition
 * Document Management Made Simple
 * Copyright (C) 2004 - 2007 The Jam Warehouse Software (Pty) Limited
 * 
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 3 as published by the
 * Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * You can contact The Jam Warehouse Software (Pty) Limited, Unit 1, Tramber Place,
 * Blake Street, Observatory, 7925 South Africa. or email info@knowledgetree.com.
 * 
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 * 
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * KnowledgeTree" logo and retain the original copyright notice. If the display of the 
 * logo is not reasonably feasible for technical reasons, the Appropriate Legal Notices
 * must display the words "Powered by KnowledgeTree" and retain the original 
 * copyright notice. 
 * Contributor( s): ______________________________________
 *
 */

class ExternalResourceStatusDashlet extends KTBaseDashlet
{
	var $resources = array();

    function ExternalResourceStatusDashlet()
    {
        $this->sTitle = _kt('External Resource Dependancy Status');
        $this->sClass = 'ktError';
    }

    function addIssue($resource, $status)
    {
    	$this->resources[] = array(
    				'name'=>$resource,
    				'status'=>str_replace(

    						array("\n",_kt('Administrator Guide')),
    						array('<br>', sprintf("<a target='_blank' href=\"http://www.knowledgetree.com/go/ktAdminManual\">%s</a>", _kt('Administrator Guide'))), $status));
    }

    function checkResources()
    {
    	$check = true;
    	// check if we have a cached result
		if (isset($_SESSION['ExternalResourceStatus']))
		{
			// we will only do the check every 5 minutes
			if (time() - $_SESSION['ExternalResourceStatus']['time'] < 5 * 60)
			{
				$check = false;
				$this->resources = $_SESSION['ExternalResourceStatus']['resources'];
			}
		}

		// we will only check if the result is not cached, or after 5 minutes
		if ($check)
		{
	    	$this->checkOpenOffice();
    		$this->checkLucene();
    		$_SESSION['ExternalResourceStatus']['time'] = time();
    		$_SESSION['ExternalResourceStatus']['resources'] = $this->resources;
		}

    	return (count($this->resources) > 0);
    }

    function checkOpenOffice()
    {
		$diagnose = SearchHelper::checkOpenOfficeAvailablity();
		if (!is_null($diagnose))
		{
			$this->addIssue(_kt('Open Office Server'), $diagnose);
		}
    }

    function checkLucene()
    {
		$indexer = Indexer::get();
		$diagnose = $indexer->diagnose();
		if (!is_null($diagnose))
		{
			$this->addIssue(_kt('Lucene Indexer'), $diagnose);
		}
    }

	function is_active($oUser)
	{
	    if (!Permission::userIsSystemAdministrator())
	    {
	    	return false;
	    }

	    return $this->checkResources() > 0;
	}

	function render()
	{
	    $oTemplating =& KTTemplating::getSingleton();
	    $oTemplate = $oTemplating->loadTemplate('ktcore/search2/external_resources');

	    $aTemplateData = array(
	    		'context' => $this,
				'resources' => $this->resources
			);

        return $oTemplate->render($aTemplateData);
    }
}

?>
