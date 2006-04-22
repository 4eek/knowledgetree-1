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

require_once(KT_LIB_DIR . '/ktentity.inc');

class KTPluginEntity extends KTEntity {
    var $_bUsePearError = true;
    
    var $sNamespace;
    var $sPath;
    var $iVersion;
    var $bDisabled;
    var $sData;
    var $bUnavailable;

    // {{{ KTEntity-related
    var $_aFieldToSelect = array(
        'iId' => 'id',
        'sNamespace' => 'namespace',
        'sPath' => 'path',
        'iVersion' => 'version',
        'bDisabled' => 'disabled',
        'sData' => 'data',
        'bUnavailable' => 'unavailable',
        'sFriendlyName' => 'friendly_name',
    );

    function _table() {
        return KTUtil::getTableName('plugins');
    }

    function _cachedGroups() {
        return array('getlist', 'getList', 'getByNamespace');
    }
    // }}}

    // {{{ getters/setters
    function getNamespace() { return $this->sNamespace; }
    function getPath() { return $this->sPath; }
    function getVersion() { return $this->iVersion; }
    function getDisabled() { return $this->bDisabled; }
    function getData() { return $this->sData; }
    function getUnavailable() { return $this->bUnavailable; }
    function getFriendlyName($sValue) { return $this->sFriendlyName; }
    function setNamespace($sValue) { $this->sNamespace = $sValue; }
    function setPath($sValue) { $this->sPath = $sValue; }
    function setVersion($iValue) { $this->iVersion = $iValue; }
    function setDisabled($bValue) { $this->bDisabled = $bValue; }
    function setData($sValue) { $this->sData = $sValue; }
    function setUnavailable($mValue) { $this->bUnavailable = $mValue; }
    function setFriendlyName($sValue) { $this->sFriendlyName = $sValue; }
    // }}}

    function get($iId) {
        return KTEntityUtil::get('KTPluginEntity', $iId);
    }
    // STATIC
    function &createFromArray($aOptions) {
        return KTEntityUtil::createFromArray('KTPluginEntity', $aOptions);
    }

    // STATIC
    function &getList($sWhereClause = null) {
        return KTEntityUtil::getList2('KTPluginEntity', $sWhereClause, $aOptions);
    }

    // STATIC
    function &getByNamespace($sName) {
        $aOptions = array('fullselect' => false, 'cache' => 'getByNamespace');
        return KTEntityUtil::getBy('KTPluginEntity', 'namespace', $sName, $aOptions);
    }

    // STATIC
    function &getAvailable() {
        $aOptions = array('multi' => true);
        return KTEntityUtil::getBy('KTPluginEntity', 'unavailable', false, $aOptions);
    }

    function &getEnabledPlugins() {
        $aOptions = array(
            'ids' => true,
            'multi' => true,
        );
        return KTEntityUtil::getBy('KTPluginEntity', 'disabled', false,
                $aOptions);
    }
    
    function setEnabled($aIds) {
        $sTable = KTPluginEntity::_table();
        $sIds = DBUtil::paramArray($aIds);
        $sQuery = sprintf('UPDATE %s SET disabled = 1 WHERE id NOT IN (%s)', $sTable, $sIds);
        DBUtil::runQuery(array($sQuery, $aIds));
        $sQuery = sprintf('UPDATE %s SET disabled = 0 WHERE id IN (%s)', $sTable, $sIds);
        DBUtil::runQuery(array($sQuery, $aIds));
        KTPluginEntity::clearAllCaches();
    }

    function clearAllCaches() {
        return KTEntityUtil::clearAllCaches('KTPluginEntity');
    }
    
        // either return the friendly name, or the namespace (for failback).
    function getUserFriendlyName() {
        $n = trim($this->sFriendlyName);
        if (!empty($n)) { return $this->sFriendlyName; }
        return $this->sNamespace;
    }
}
