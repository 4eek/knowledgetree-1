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

require_once(KT_LIB_DIR . '/dispatcher.inc.php');
require_once(KT_LIB_DIR . '/actions/actionregistry.inc.php');
require_once(KT_LIB_DIR . '/browse/browseutil.inc.php');
require_once(KT_LIB_DIR . "/util/sanitize.inc");

class KTFolderAction extends KTStandardDispatcher {
    var $sName;
    var $sDescription;

    var $_sShowPermission = 'ktcore.permissions.folder_details';
    var $_sDisablePermission;
    var $sHelpPage = 'ktcore/browse.html';

    var $_bAdminAlwaysAvailable = false;

    var $sSection = 'browse';

    function KTFolderAction($oFolder = null, $oUser = null, $oPlugin = null) {
        parent::KTStandardDispatcher();
        $this->oFolder =& $oFolder;
        $this->oUser =& $oUser;
        $this->oPlugin =& $oPlugin;
        $this->aBreadcrumbs = array(
            array('action' => 'browse', 'name' => _kt('Browse')),
        );
        $this->persistParams(array('fFolderId'));
    }

    function setFolder(&$oFolder) {
        $this->oFolder =& $oFolder;
    }

    function setUser(&$oUser) {
        $this->oUser =& $oUser;
    }


    function _show() {
        if (is_null($this->_sShowPermission)) {
            return true;
        }
        $oPermission =& KTPermission::getByName($this->_sShowPermission);
        if (PEAR::isError($oPermission)) {
            return true;
        }

        if ($this->_bAdminAlwaysAvailable) {
            if (KTBrowseUtil::inAdminMode($this->oUser, $this->oFolder)) {
                return true;
            }
        }

        return KTPermissionUtil::userHasPermissionOnItem($this->oUser, $oPermission, $this->oFolder);
    }

    function getURL() {
        $oKTConfig =& KTConfig::getSingleton();
        $sExt = '.php';
        if (KTUtil::arrayGet($_SERVER, 'kt_no_extensions')) {
            $sExt = '';
        }
        if ($oKTConfig->get('KnowledgeTree/pathInfoSupport')) {
            return sprintf('%s/action%s/%s?fFolderId=%d', $GLOBALS['KTRootUrl'], $sExt, $this->sName, $this->oFolder->getID());
        } else {
            return sprintf('%s/action%s?kt_path_info=%s&fFolderId=%d', $GLOBALS['KTRootUrl'], $sExt, $this->sName, $this->oFolder->getID());
        }
    }

    function getInfo() {
        if ($this->_show() === false) {
            return null;
        }

        $aInfo = array(
            'description' => $this->sDescription,
            'name' => $this->getDisplayName(),
            'ns' => $this->sName,
            'url' => $this->getURL(),
        );
        return $this->customiseInfo($aInfo);
    }

    function getName() {
        return sanitizeForSQLtoHTML($this->sName);
    }

    function getDisplayName() {
        // This should be overridden by the i18nised display name
        // This implementation is only here for backwards compatibility
        return sanitizeForSQLtoHTML($this->sDisplayName);
    }

    function getDescription() {
        return sanitizeForSQLtoHTML($this->sDescription);
    }

    function customiseInfo($aInfo) {
        return $aInfo;
    }

    function check() {
        $this->oFolder =& $this->oValidator->validateFolder($_REQUEST['fFolderId']);

        if (!$this->_show()) { return false; }

        $aOptions = array(
            'final' => false,
            'documentaction' => 'viewDocument',
            'folderaction' => 'browse',
        );
        $this->aBreadcrumbs = kt_array_merge($this->aBreadcrumbs,
            KTBrowseUtil::breadcrumbsForFolder($this->oFolder, $aOptions));

        $portlet = new KTActionPortlet(sprintf(_kt('About this folder')));
        $aActions = KTFolderActionUtil::getFolderInfoActionsForFolder($this->oFolder, $this->oUser);
        $portlet->setActions($aActions,$this->sName);
        $this->oPage->addPortlet($portlet);

        $portlet = new KTActionPortlet(sprintf(_kt('Actions on this folder')));
        $aActions = KTFolderActionUtil::getFolderActionsForFolder($this->oFolder, $this->oUser);
        $portlet->setActions($aActions,$this->sName);
        $this->oPage->addPortlet($portlet);

        if (KTPermissionUtil::userHasPermissionOnItem($this->oUser, 'ktcore.permissions.folder_details', $this->oFolder)) {
            $this->oPage->setSecondaryTitle($this->oFolder->getName());
        } else {
            if (KTBrowseUtil::inAdminMode($this->oUser, $this->oFolder)) {
                $this->oPage->setSecondaryTitle(sprintf('(%s)', $this->oFolder->getName()));
            } else {
                $this->oPage->setSecondaryTitle('...');
            }
        }

        return true;
    }

    function do_main() {
        return _kt('Dispatcher component of action not implemented.');
    }

}

class KTFolderActionUtil {
    function getFolderActions() {
        $oRegistry =& KTActionRegistry::getSingleton();
        return $oRegistry->getActions('folderaction');
    }
    function getFolderInfoActions() {
        $oRegistry =& KTActionRegistry::getSingleton();
        return $oRegistry->getActions('folderinfo');
    }
    function &getFolderActionsForFolder($oFolder, $oUser) {
        $aObjects = array();
        foreach (KTFolderActionUtil::getFolderActions() as $aAction) {
            list($sClassName, $sPath, $sPlugin) = $aAction;
            $oRegistry =& KTPluginRegistry::getSingleton();
            $oPlugin =& $oRegistry->getPlugin($sPlugin);
            if (!empty($sPath)) {
                require_once($sPath);
            }
            $aObjects[] =new $sClassName($oFolder, $oUser, $oPlugin);
        }
        return $aObjects;
    }
    function &getFolderInfoActionsForFolder($oFolder, $oUser) {
        $aObjects = array();
        foreach (KTFolderActionUtil::getFolderInfoActions() as $aAction) {
            list($sClassName, $sPath, $sPlugin) = $aAction;
            $oRegistry =& KTPluginRegistry::getSingleton();
            $oPlugin =& $oRegistry->getPlugin($sPlugin);
            if (!empty($sPath)) {
                require_once($sPath);
            }
            $aObjects[] =new $sClassName($oFolder, $oUser, $oPlugin);
        }
        return $aObjects;
    }
}

?>
