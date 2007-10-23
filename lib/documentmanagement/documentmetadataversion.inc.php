<?php
/**
 * $Id$
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

require_once(KT_LIB_DIR . '/ktentity.inc');
require_once(KT_LIB_DIR . "/util/sanitize.inc");

class KTDocumentMetadataVersion extends KTEntity {
    var $_bUsePearError = true;

    /** Which document we are a version of */
    var $iDocumentId;

    /** Which metadata version of the document we are describing */
    var $iMetadataVersion;

    /** Which content was associated with this metadata version */
    var $iContentVersionId;

    /** The document type of the document during this version */
    var $iDocumentTypeId;

    /** The name of the document during this version */
    var $sName;

    /** The description of the document during this version */
    var $sDescription;

    /** The status of the document during this version */
    var $iStatusId;

    /** When this version was created */
    var $dVersionCreated;

    /** Who created this version */
    var $iVersionCreatorId;

    var $iWorkflowId;
    var $iWorkflowStateId;

    var $_aFieldToSelect = array(
        "iId" => "id",

        "iDocumentId" => 'document_id',
        "iMetadataVersion" => 'metadata_version',
        "iContentVersionId" => 'content_version_id',

        "iDocumentTypeId" => 'document_type_id',

        "sName" => 'name',
        "sDescription" => 'description',

        "iStatusId" => 'status_id',

        "dVersionCreated" => 'version_created',
        "iVersionCreatorId" => 'version_creator_id',

        "iWorkflowId" => 'workflow_id',
        "iWorkflowStateId" => 'workflow_state_id',
    );

    // {{{ getters/setters
    function getDocumentId() { return $this->iDocumentId; }
    function setDocumentId($iNewValue) { $this->iDocumentId = $iNewValue; }
    function getMetadataVersion() { return $this->iMetadataVersion; }
    function setMetadataVersion($iNewValue) { $this->iMetadataVersion = $iNewValue; }
    function getContentVersionId() { return $this->iContentVersionId; }
    function setContentVersionId($iNewValue) { $this->iContentVersionId = $iNewValue; }
    function setContentVersion($iNewValue) { $this->iContentVersion = $iNewValue; }
    function getDocumentTypeId() { return $this->iDocumentTypeId; }
    function setDocumentTypeId($iNewValue) { $this->iDocumentTypeId = $iNewValue; }
    function getName() { return sanitizeForSQLtoHTML($this->sName); }
    function setName($sNewValue) { $this->sName = sanitizeForSQL($sNewValue); }
    function getDescription() { return sanitizeForSQLtoHTML($this->sDescription); }
    function setDescription($sNewValue) { $this->sDescription = sanitizeForSQL($sNewValue); }
    function getStatusId() { return $this->iStatusId; }
    function setStatusId($iNewValue) { $this->iStatusId = $iNewValue; }
    function getVersionCreated() { return $this->dVersionCreated; }
    function setVersionCreated($dNewValue) { $this->dVersionCreated = $dNewValue; }
    function getVersionCreatorId() { return $this->iVersionCreatorId; }
    function setVersionCreatorId($iNewValue) { $this->iVersionCreatorId = $iNewValue; }
    function getWorkflowId() { return $this->iWorkflowId; }
    function setWorkflowId($mValue) { $this->iWorkflowId = $mValue; }
    function getWorkflowStateId() { return $this->iWorkflowStateId; }
    function setWorkflowStateId($mValue) { $this->iWorkflowStateId = $mValue; }
    // }}}

    function &createFromArray($aOptions) {
        return KTEntityUtil::createFromArray('KTDocumentMetadataVersion', $aOptions);
    }

    function _table() {
        return KTUtil::getTableName('document_metadata_version');
    }

    function create() {
        if (is_null($this->iMetadataVersion)) {
            $this->iMetadataVersion = 0;
        }
        if (is_null($this->dVersionCreated)) {
            $this->dVersionCreated = getCurrentDateTime();
        }
        return parent::create();
    }

    function &get($iId) {
        return KTEntityUtil::get('KTDocumentMetadataVersion', $iId);
    }

    function &getByDocument($oDocument) {
        $iDocumentId = KTUtil::getId($oDocument);
        return KTEntityUtil::getByDict('KTDocumentMetadataVersion', array(
            'document_id' => $iDocumentId,
        ), array(
            'multi' => true,
            'orderby' => 'version_created DESC',
        ));
    }

    function bumpMetadataVersion() {
        $this->iMetadataVersion++;
    }
}

?>
