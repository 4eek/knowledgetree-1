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

require_once(KT_LIB_DIR . '/actions/actionregistry.inc.php');
require_once(KT_LIB_DIR . '/workflow/workflowutil.inc.php');
require_once(KT_LIB_DIR . '/dispatcher.inc.php');
require_once(KT_LIB_DIR . '/browse/browseutil.inc.php');
require_once(KT_LIB_DIR . "/util/sanitize.inc");

class KTDocumentAction extends KTStandardDispatcher {
    var $sName;
    var $sDescription;

    var $_sShowPermission = 'ktcore.permissions.read';
    var $_sDisablePermission;
    var $bAllowInAdminMode = false;
    var $sHelpPage = 'ktcore/browse.html';

    var $sSection = 'view_details';

    var $_bMutator = false;
    var $_bMutationAllowedByAdmin = true;

    var $sIconClass;

    function KTDocumentAction($oDocument = null, $oUser = null, $oPlugin = null) {
        $this->oDocument =& $oDocument;
        $this->oUser =& $oUser;
        $this->oPlugin =& $oPlugin;
        $this->aBreadcrumbs = array(
            array('action' => 'browse', 'name' => _kt('Browse')),
        );

        $this->persistParams('fDocumentId');

        parent::KTStandardDispatcher();
    }

    function setDocument(&$oDocument) {
        $this->oDocument =& $oDocument;
    }

    function setUser(&$oUser) {
        $this->oUser =& $oUser;
    }

    function _show() {
        if (is_null($this->_sShowPermission)) {
            return true;
        }
        $oFolder = Folder::get($this->oDocument->getFolderId());

        if ($this->_bMutator && $this->oDocument->getImmutable()) {
            if ($this->_bMutationAllowedByAdmin === true) {
                if (!KTBrowseUtil::inAdminMode($this->oUser, $oFolder)) {
                    return false;
                }
            } else {
                return false;
            }
        }

        if ($this->_bAdminAlwaysAvailable) {
            if (Permission::userIsSystemAdministrator($this->oUser->getId())) {
                return true;
            }
            if (Permission::isUnitAdministratorForFolder($this->oUser, $this->oDocument->getFolderId())) {
                return true;
            }
        }
        $oPermission =& KTPermission::getByName($this->_sShowPermission);
        if (PEAR::isError($oPermission)) {
            return true;
        }
        if (!KTWorkflowUtil::actionEnabledForDocument($this->oDocument, $this->sName)) {
            return false;
        }
        // be nasty in archive/delete status.
        $status = $this->oDocument->getStatusID();
        if (($status == DELETED) || ($status == ARCHIVED)) { return false; }
        if ($this->bAllowInAdminMode) {
            // check if this user is in admin mode
            if (KTBrowseUtil::inAdminMode($this->oUser, $oFolder)) {
                return true;
            }
        }
        return KTPermissionUtil::userHasPermissionOnItem($this->oUser, $oPermission, $this->oDocument);
    }

    function getURL() {
        $oKTConfig =& KTConfig::getSingleton();
        $sExt = '.php';
        if (KTUtil::arrayGet($_SERVER, 'kt_no_extensions')) {
            $sExt = '';
        }
        if ($oKTConfig->get('KnowledgeTree/pathInfoSupport')) {
            return sprintf('%s/action%s/%s?fDocumentId=%d', $GLOBALS['KTRootUrl'], $sExt, $this->sName, $this->oDocument->getID());
        } else {
            return sprintf('%s/action%s?kt_path_info=%s&fDocumentId=%d', $GLOBALS['KTRootUrl'], $sExt, $this->sName, $this->oDocument->getID());
        }
    }

    function getInfo() {
        if ($this->_show() === false) {
            return null;
        }

        $url = $this->getURL();

        $aInfo = array(
            'description' => $this->sDescription,
            'name' => $this->getDisplayName(),
            'ns' => $this->sName,
            'url' => $url,
            'icon_class' => $this->sIconClass,
        );

        $aInfo = $this->customiseInfo($aInfo);
        return $aInfo;
    }

    function getName() {
        return sanitizeForSQLtoHTML($this->sName);
    }

    function getDisplayName() {
        // Should be overridden by the i18nised display name
        // This is here solely for backwards compatibility
        return sanitizeForSQLtoHTML($this->sDisplayName);
    }

    function getDescription() {
        return sanitizeForSQLtoHTML($this->sDescription);
    }

    function customiseInfo($aInfo) {
        return $aInfo;
    }

    function check() {
        $this->oDocument =& $this->oValidator->validateDocument($_REQUEST['fDocumentId']);

    if (!$this->_show()) { return false; }

        $aOptions = array('final' => false,
              'documentaction' => 'viewDocument',
              'folderaction' => 'browse',
        );
        $this->aBreadcrumbs = kt_array_merge($this->aBreadcrumbs,
            KTBrowseUtil::breadcrumbsForDocument($this->oDocument, $aOptions));

    	$actions = KTDocumentActionUtil::getDocumentActionsForDocument($this->oDocument, $this->oUser, 'documentinfo');
        $oPortlet = new KTActionPortlet(sprintf(_kt('Document info')));
	    $oPortlet->setActions($actions, $this->sName);
	    $this->oPage->addPortlet($oPortlet);

    	$actions = KTDocumentActionUtil::getDocumentActionsForDocument($this->oDocument, $this->oUser);
        $oPortlet = new KTActionPortlet(sprintf(_kt('Document actions')));
	    $oPortlet->setActions($actions, $this->sName);
	    $this->oPage->addPortlet($oPortlet);

	    $this->oPage->setSecondaryTitle($this->oDocument->getName());

        return true;
    }

    function do_main() {
        return _kt('Dispatcher component of action not implemented.');
    }
}

class KTDocumentActionUtil {
    function getDocumentActionInfo($slot = 'documentaction') {
        $oRegistry =& KTActionRegistry::getSingleton();
        return $oRegistry->getActions($slot);
    }

    function &getDocumentActionsForDocument(&$oDocument, $oUser, $slot = 'documentaction') {
        $aObjects = array();
        foreach (KTDocumentActionUtil::getDocumentActionInfo($slot) as $aAction) {
            list($sClassName, $sPath, $sPlugin) = $aAction;
            $oRegistry =& KTPluginRegistry::getSingleton();
            $oPlugin =& $oRegistry->getPlugin($sPlugin);
            if (!empty($sPath)) {
                require_once($sPath);
            }
            $aObjects[] = new $sClassName($oDocument, $oUser, $oPlugin);
        }
        return $aObjects;
    }

    function getAllDocumentActions($slot = 'documentaction') {
        $aObjects = array();
        $oDocument = null;
        $oUser = null;
        foreach (KTDocumentActionUtil::getDocumentActionInfo($slot) as $aAction) {
            list($sClassName, $sPath, $sName, $sPlugin) = $aAction;
            $oRegistry =& KTPluginRegistry::getSingleton();
            $oPlugin =& $oRegistry->getPlugin($sPlugin);
            if (!empty($sPath)) {
                require_once($sPath);
            }
            $aObjects[] = new $sClassName($oDocument, $oUser, $oPlugin);
        }
        return $aObjects;
    }

    function getDocumentActionsByNames($aNames, $slot = 'documentaction', $oDocument = null, $oUser = null) {
        $aObjects = array();
        foreach (KTDocumentActionUtil::getDocumentActionInfo($slot) as $aAction) {
            list($sClassName, $sPath, $sName, $sPlugin) = $aAction;
            $oRegistry =& KTPluginRegistry::getSingleton();
            $oPlugin =& $oRegistry->getPlugin($sPlugin);
            if (!in_array($sName, $aNames)) {
                continue;
            }
            if (!empty($sPath)) {
                require_once($sPath);
            }
            $aObjects[] = new $sClassName($oDocument, $oUser, $oPlugin);
        }
        return $aObjects;
    }
}

?>
