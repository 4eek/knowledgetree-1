<?php
/**
 * $Id$
 *
 * KnowledgeTree Community Edition
 * Document Management Made Simple
 * Copyright (C) 2008, 2009, 2010 KnowledgeTree Inc.
 *
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
 * You can contact KnowledgeTree Inc., PO Box 7775 #87847, San Francisco,
 * California 94120-7775, or email info@knowledgetree.com.
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

require_once('config/dmsDefaults.php');
require_once(KT_LIB_DIR . '/templating/kt3template.inc.php');
require_once(KT_LIB_DIR . '/dispatcher.inc.php');
require_once(KT_LIB_DIR . '/widgets/portlet.inc.php');

require_once(KT_LIB_DIR . '/plugins/KTAdminNavigation.php');

class AdminSplashDispatcher extends KTAdminDispatcher {

    var $category = '';
    var $sSection = 'administration';

    function AdminSplashDispatcher()
    {
        $this->aBreadcrumbs = array(
            array('url' => KTUtil::getRequestScriptName($_SERVER), 'name' => _kt('Settings')),
        );

        parent::KTAdminDispatcher();
    }

    function do_main()
    {
        if ($this->category !== '') {
            return $this->do_viewCategory();
        };

        // are we categorised, or not?
        $oRegistry =& KTAdminNavigationRegistry::getSingleton();
        $categories = $oRegistry->getCategories();
		$KTConfig =& KTConfig::getSingleton();
        $condensed_admin = $KTConfig->get('condensedAdminUI');

        $aAllItems = array();
        // we need to investigate sub_url solutions.
        if ($condensed_admin) {
            foreach ($categories as $aCategory) {
                $aItems = $oRegistry->getItemsForCategory($aCategory['name']);
                $aAllItems[$aCategory['name']] = $aItems;
            }
        }

        $this->oPage->hideSection();
        $oTemplating =& KTTemplating::getSingleton();

        global $default;
        if (ACCOUNT_ROUTING_ENABLED && $default->tier == 'trial') {
            $this->includeOlark();
        }

        if ($condensed_admin) {
            $oTemplate = $oTemplating->loadTemplate('kt3/admin_fulllist');
        } else {
            $oTemplate = $oTemplating->loadTemplate('kt3/admin_categories');
        }

        //$lefCats = array('contentManagement', 'contentSetup', 'contentIndexing');
        $rightCats = array('accountInformation', 'userSetup', 'sysConfig');
        foreach ($categories as $cat) {
            if (in_array($cat['name'], $rightCats)) {
                $rightmenu[$cat['name']] = $categories[$cat['name']];
            } else {
                $leftmenu[$cat['name']] = $categories[$cat['name']];
            }
        }

        /**REMOVE
		foreach (array('contentManagement', 'contentSetup', 'contentIndexing') as $leftcat) {
        	$leftmenu[$leftcat] = isset($categories[$leftcat]) ? $categories[$leftcat] : '';
        }

		foreach (array('accountInformation', 'userSetup', 'sysConfig') as $rightcat) {
			$rightmenu[$rightcat] = isset($categories[$rightcat]) ? $categories[$rightcat] : '';
		}
		REMOVE**/

        $aTemplateData = array(
              'context' => $this,
              'categories' => $categories,
              'leftmenu' => $leftmenu,
              'rightmenu' => $rightmenu,
              'all_items' => $aAllItems,
              'baseurl' => $_SERVER['PHP_SELF'],
        );

        return $oTemplate->render($aTemplateData);
    }

    function do_viewCategory()
    {
        // are we categorised, or not?
        $category = KTUtil::arrayGet($_REQUEST, 'fCategory', $this->category);

        //Removing bad contentSetup/fieldmanagement links from the Document Metadata and Workflow Configuration page.
		$oPage =& $GLOBALS['main'];

		if ($category == 'contentSetup') {
			$aJavascript[] = 'thirdpartyjs/jquery/jquery-1.4.2.js';
			$oPage->requireJSResources($aJavascript);
			$jscript .= "<script src='resources/js/kt_hideadminlink.js' type='text/javascript'></script>";
		}

		$aJavascript[] = 'resources/js/newui/hide_system_links.js';
		$oPage->requireJSResources($aJavascript);

        $oRegistry =& KTAdminNavigationRegistry::getSingleton();
        $aCategory = $oRegistry->getCategory($category);
        if(ACCOUNT_ROUTING_ENABLED && $category == 'contentIndexing')
        {
			$aItems = null;
			$message = 'Indexing of full-text content in KnowledgeTree is carried out through shared queue processes using SOLR. <br/>Content Indexing statistics coming soon!';
        }
        else
        {
        	$aItems = $oRegistry->getItemsForCategory($category);
        	$message = null;
        }

        if (count($aItems) == 1) {
            // skip the list of admin pages and go direct to the first / only page
            $url = KTUtil::ktLink('admin.php', $aItems[0]['fullname']);
            redirect($url);
        }

        $this->aBreadcrumbs[] = array('name' => $aCategory['title'], 'url' => KTUtil::ktLink('admin.php',$category));

        $this->oPage->title = _kt('Settings') . ': ' . $aCategory['title'];
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate('kt3/admin_items');
        $aTemplateData = array(
              'context' => $this,
              'category' => $aCategory,
              'items' => $aItems,
              'baseurl' =>  $_SERVER['PHP_SELF'],
        	  'jscript' => $jscript,
        	  'message' => $message,
        );

        return $oTemplate->render($aTemplateData);
    }

    private function includeOlark()
	{
	    $user = User::get($_SESSION['userID']);
	    $js = preg_replace('/.*[\/\\\\]plugins/', 'plugins', KT_LIVE_DIR) . '/resources/js/olark/olark.js';
	    $this->oPage->requireJsResource($js);
	    $this->oPage->setBodyOnload("javascript: ktOlark.setUserData('" . $user->getName() . "', '" . $user->getEmail() . "');");
	}

}

$sub_url = KTUtil::arrayGet($_SERVER, 'PATH_INFO');
$sub_url = trim($sub_url);
$sub_url= trim($sub_url, '/');

if (empty($sub_url)) {
    $oDispatcher = new AdminSplashDispatcher();
} else {
    $oRegistry =& KTAdminNavigationRegistry::getSingleton();
    if ($oRegistry->isRegistered($sub_url)) {
       $oDispatcher = $oRegistry->getDispatcher($sub_url);

       $aParts = explode('/',$sub_url);

       $oRegistry =& KTAdminNavigationRegistry::getSingleton();
       $aCategory = $oRegistry->getCategory($aParts[0]);

       $oDispatcher->aBreadcrumbs = array();
       $oDispatcher->aBreadcrumbs[] = array('action' => 'administration', 'name' => _kt('Settings'));
       $oDispatcher->aBreadcrumbs[] = array('name' => $aCategory['title'], 'url' => KTUtil::ktLink('admin.php',$aParts[0]));
    } else {
       // FIXME (minor) redirect to no-suburl?
       $oDispatcher = new AdminSplashDispatcher();
       $oDispatcher->category = $sub_url;
    }
}

// Implement an electronic signature for accessing the admin section, it will appear every 10 minutes
global $main;
global $default;
if ($default->enableAdminSignatures && ($_SESSION['electronic_signature_time'] < time())) {
    $sBaseUrl = KTUtil::kt_url();
    $sUrl = KTPluginUtil::getPluginPath('electronic.signatures.plugin', true);
    $heading = _kt('You are attempting to access Settings');
    $main->setBodyOnload("javascript: showSignatureForm('{$sUrl}', '{$heading}', 'dms.administration.administration_section_access', 'admin', '{$sBaseUrl}/browse.php', 'close');");
}

$oDispatcher->dispatch(); // we _may_ be redirected at this point (see KTAdminNavigation)
?>
