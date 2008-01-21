<?php
/**
 * $Id$
 *
 * KnowledgeTree Open Source Edition
 * Document Management Made Simple
 * Copyright (C) 2004 - 2008 The Jam Warehouse Software (Pty) Limited
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

//require_once("../../../../../config/dmsDefaults.php");

require_once(KT_LIB_DIR . "/templating/templating.inc.php");
require_once(KT_LIB_DIR . "/help/helpreplacement.inc.php");
require_once(KT_LIB_DIR . "/help/helpentity.inc.php");
require_once(KT_LIB_DIR . "/help/help.inc.php");

require_once(KT_LIB_DIR . "/dispatcher.inc.php");

require_once(KT_LIB_DIR . "/templating/kt3template.inc.php");

class ManageHelpDispatcher extends KTAdminDispatcher {

    var $sHelpPage = 'ktcore/admin/help administration.html';

    function do_main() {
        return $this->getData();
    }

    function getData() {
        $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _kt('Help Administration'));
        $this->oPage->setBreadcrumbDetails(_kt('select a section'));
        $this->oPage->setTitle(_kt('Help Administration'));
        $oTemplating =& KTTemplating::getSingleton();
        $aHelpReplacements =& KTHelpReplacement::getList();
        //$aHelps =& KTHelpEntity::getList();
        $oTemplate = $oTemplating->loadTemplate("ktcore/manage_help");
        $aTemplateData = array(
            "context" => &$this,
            //"helps" => $aHelps,
            "helpreplacements" => $aHelpReplacements,
        );

        return $oTemplate->render($aTemplateData);
    }

    function getReplacementItemData($oHelpReplacement) {
        $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _kt('Help Administration'));
        $this->oPage->setTitle(_kt('Editing: ') . $oHelpReplacement->getTitle());
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate("ktcore/manage_help_item");
        $aTemplateData = array(
            "context" => &$this,
            "help" => $oHelpReplacement,
        );
        $this->aBreadcrumbs[] = array(
            'name' => _kt('Edit help item'),
        );
        return $oTemplate->render($aTemplateData);
    }

    function do_editReplacement() {
        $id = KTUtil::arrayGet($_REQUEST, 'id');
        $oHelpReplacement = KTHelpReplacement::get($id);
        if (PEAR::isError($oHelpReplacement)) {
            return $this->errorRedirectToMain(_kt("Could not find specified item"));
        }
        return $this->getReplacementItemData($oHelpReplacement);
    }

    function do_deleteReplacement() {
        $id = KTUtil::arrayGet($_REQUEST, 'id');
        $oHelpReplacement = KTHelpReplacement::get($id);
        if (PEAR::isError($oHelpReplacement)) {
            return $this->errorRedirectToMain(_kt("Could not find specified item"));
        }
        $res = $oHelpReplacement->delete();
        if (PEAR::isError($res)) {
            return $this->errorRedirectToMain(_kt("Could not delete specified item"));
        }
        return $this->successRedirectToMain(_kt("Item deleted"));
    }
    
    function do_updateReplacement() {
        $id = KTUtil::arrayGet($_REQUEST, 'id');
        $oHelpReplacement = KTHelpReplacement::get($id);
        if (PEAR::isError($oHelpReplacement)) {
            return $this->errorRedirectToMain(_kt("Could not find specified item"));
        }
        $description = KTUtil::arrayGet($_REQUEST, 'description');
        if (empty($description)) {
            return $this->errorRedirectToMain(_kt("No description given"));
        }
        $oHelpReplacement->setDescription($description);
        
        $title = KTUtil::arrayGet($_REQUEST, 'title');
        if (empty($title)) {
            return $this->errorRedirectToMain(_kt("No title given"));
        }
        $oHelpReplacement->setTitle($title);
        
        $res = $oHelpReplacement->update();
        if (PEAR::isError($res)) {
            return $this->errorRedirectToMain(_kt("Error updating item"));
        }
        return $this->successRedirectToMain(_kt("Item updated"));
    }

    function do_customise() {
        $name = KTUtil::arrayGet($_REQUEST, 'name');
        $aPathInfo = KTHelp::_getLocationInfo($name);
        $oHelpReplacement = KTHelpReplacement::getByName($aPathInfo['internal']);
        // XXX: Check against "already exists"
        
        //var_dump($name);
        
        if (!PEAR::isError($oHelpReplacement)) {
            // Already exists...
            return $this->successRedirectTo('editReplacement', _kt('Replacement already exists. Editing the existing copy instead of replacing.'),'id=' .  $oHelpReplacement->getId());
        }

	    $info = KTHelp::getHelpInfo($name);
        if (PEAR::isError($info)) { 
            $info = array('name' => $aPathInfo['internal']);
            $info['title'] = _kt('New Help File');
            $info['body'] = _kt('New Help File');
        }

        $oHelpReplacement = KTHelpReplacement::createFromArray(array(
            'name' => $aPathInfo['internal'],
            'description' => $info['body'],
            'title' => $info['title'],
        ));

        if (PEAR::isError($oHelpReplacement)) {
            return $this->errorRedirectToMain(_kt("Unable to create replacement"));
        }
        return $this->successRedirectTo('editReplacement', _kt('Created replacement.'), 'id=' .  $oHelpReplacement->getId());
    }
}

//$oDispatcher = new ManageHelpDispatcher();
//$oDispatcher->dispatch();

?>
