<?php

/**
 * $Id$
 *
 * Main dashboard page -- This page is presented to the user after login.
 * It contains a high level overview of the users subscriptions, checked out 
 * document, pending approval routing documents, etc. 
 *
 * Copyright (c) 2006 Jam Warehouse http://www.jamwarehouse.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; using version 2 of the License.
 *
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
 * @version $Revision$
 * @author Michael Joseph <michael@jamwarehouse.com>, Jam Warehouse (Pty) Ltd, South Africa
 */

// main library routines and defaults
require_once("config/dmsDefaults.php");
require_once(KT_LIB_DIR . "/unitmanagement/Unit.inc");

require_once(KT_LIB_DIR . "/dashboard/dashletregistry.inc.php");
require_once(KT_LIB_DIR . "/dashboard/dashlet.inc.php");
require_once(KT_LIB_DIR . "/templating/templating.inc.php");
require_once(KT_LIB_DIR . "/templating/kt3template.inc.php");
require_once(KT_LIB_DIR . "/dispatcher.inc.php");

require_once(KT_LIB_DIR . "/dashboard/DashletDisables.inc.php");

$sectionName = "dashboard";

class DashboardDispatcher extends KTStandardDispatcher {
    
    var $notifications = array();
    var $sHelpPage = 'ktcore/dashboard.html';    

    function DashboardDispatcher() {
        $this->aBreadcrumbs = array(
            array('action' => 'dashboard', 'name' => _kt('Dashboard')),
        );
        return parent::KTStandardDispatcher();
    }
    function do_main() {
        $this->oPage->setShowPortlets(false);
        // retrieve action items for the user.
        // FIXME what is the userid?
        
        
        $oDashletRegistry =& KTDashletRegistry::getSingleton();
        $aDashlets = $oDashletRegistry->getDashlets($this->oUser);
        
        $this->sSection = "dashboard";
        $this->oPage->setBreadcrumbDetails(_kt("Home"));
        $this->oPage->title = _kt("Dashboard");
    
        // simplistic improvement over the standard rendering:  float half left
        // and half right.  +Involves no JS -can leave lots of white-space at the bottom.

        $aDashletsLeft = array();
        $aDashletsRight = array(); 

        $i = 0;
        foreach ($aDashlets as $oDashlet) {
            if ($i == 0) { $aDashletsLeft[] = $oDashlet; }
            else {$aDashletsRight[] = $oDashlet; }
            $i += 1;
            $i %= 2;
        }


        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate("kt3/dashboard");
        $aTemplateData = array(
              "context" => $this,
              "dashlets_left" => $aDashletsLeft,
              "dashlets_right" => $aDashletsRight,
        );
        return $oTemplate->render($aTemplateData);
    }
    
    // disable a dashlet.  
    // FIXME this very slightly violates the separation of concerns, but its not that flagrant.
    function do_disableDashlet() {
        $sNamespace = KTUtil::arrayGet($_REQUEST, 'fNamespace');
        $iUserId = $this->oUser->getId();
        
        if (empty($sNamespace)) {
            $this->errorRedirectToMain('No dashlet specified.');
            exit(0);
        }
    
        // do the "delete"
        
        $this->startTransaction();
        $aParams = array('sNamespace' => $sNamespace, 'iUserId' => $iUserId);
        $oDD = KTDashletDisable::createFromArray($aParams);
        if (PEAR::isError($oDD)) {
            $this->errorRedirectToMain('Failed to disable the dashlet.');
        }
    
        $this->commitTransaction();
        $this->successRedirectToMain('Dashlet disabled.');
    }
}

$oDispatcher = new DashboardDispatcher();
$oDispatcher->dispatch();

?>

