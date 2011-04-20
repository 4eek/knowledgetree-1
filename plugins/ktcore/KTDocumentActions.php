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

require_once(KT_LIB_DIR . '/actions/documentaction.inc.php');
require_once(KT_LIB_DIR . '/subscriptions/Subscription.inc');
require_once(KT_LIB_DIR . '/widgets/fieldWidgets.php');
require_once(KT_LIB_DIR . '/browse/browseutil.inc.php');
require_once(KT_LIB_DIR . '/documentmanagement/documentutil.inc.php');
require_once(KT_LIB_DIR . '/documentmanagement/PhysicalDocumentManager.inc');

require_once(KT_LIB_DIR . '/browse/DocumentCollection.inc.php');
require_once(KT_LIB_DIR . '/browse/BrowseColumns.inc.php');
require_once(KT_LIB_DIR . '/browse/PartialQuery.inc.php');

require_once(KT_LIB_DIR . '/widgets/forms.inc.php');
require_once(KT_LIB_DIR . "/util/sanitize.inc");

require_once(KT_LIB_DIR . "/datetime/datetimeutil.inc.php");

// {{{ KTDocumentDetailsAction
class KTDocumentDetailsAction extends KTDocumentAction {

    var $sName = 'ktcore.actions.document.displaydetails';

    function do_main() {
        redirect(generateControllerLink('viewDocument',sprintf(_kt('fDocumentId=%d'),$this->oDocument->getId())));
        exit(0);
    }

    function getDisplayName() {
        // Disabling
        return '';
        return _kt('Display Details');
    }

}

class KTDocumentTransactionHistoryAction extends KTDocumentAction {

    var $sName = 'ktcore.actions.document.transactionhistory';
    var $sIconClass = 'usage-info';
    var $sParentBtn = 'more';

    function getDisplayName() {
        return _kt('Usage Information');
    }

    function do_main() {
        $this->oPage->setSecondaryTitle($this->oDocument->getName());

        $this->oPage->setBreadcrumbDetails(_kt('history'));

        $aTransactions = array();
        // FIXME create a sane "view user information" page somewhere.
        // FIXME do we really need to use a raw db-access here?  probably...
        $sQuery = 'SELECT DTT.name AS transaction_name, DT.transaction_namespace, U.name AS user_name, DT.version AS version, DT.comment AS comment, DT.datetime AS datetime ' .
            'FROM ' . KTUtil::getTableName('document_transactions') . ' AS DT INNER JOIN ' . KTUtil::getTableName('users') . ' AS U ON DT.user_id = U.id ' .
            'LEFT JOIN ' . KTUtil::getTableName('transaction_types') . ' AS DTT ON DTT.namespace = DT.transaction_namespace ' .
            'WHERE DT.document_id = ? ORDER BY DT.datetime DESC';
        $aParams = array($this->oDocument->getId());

        $res = DBUtil::getResultArray(array($sQuery, $aParams));
        if (PEAR::isError($res)) {
           var_dump($res); // FIXME be graceful on failure.
           exit(0);
        }

        $aTransactions = $res;

        // Set the namespaces where not in the transactions lookup
        foreach($aTransactions as $key => $transaction) {
            if (empty($transaction['transaction_name'])) {
                $aTransactions[$key]['transaction_name'] = $this->_getActionNameForNamespace($transaction['transaction_namespace']);
            }
            // Get the datetime offset
            $aTransactions[$key]['datetime'] = datetimeutil::getLocaleDate($aTransactions[$key]['datetime']);
        }


        // render pass.
        $this->oPage->setTitle(_kt('Document History'));

        $oTemplate = $this->oValidator->validateTemplate('ktcore/document/transaction_history');
        $aTemplateData = array(
              'context' => $this,
              'document_id' => $this->oDocument->getId(),
              'document' => $this->oDocument,
              'transactions' => $aTransactions,
        );

        return $oTemplate->render($aTemplateData);
    }

    function _getActionNameForNamespace($sNamespace) {
        $aNames = split('\.', $sNamespace);
        $sName = array_pop($aNames);
        $sName = str_replace('_', ' ', $sName);
        $sName = ucwords($sName);
        return $sName;
    }

}

class KTDocumentVersionHistoryAction extends KTDocumentAction {

    var $sName = 'ktcore.actions.document.versionhistory';

    function getDisplayName() {
        // Disabling
        return '';
        return _kt('Version History');
    }

    /**
     * Display a list of versions for comparing
     *
     * @return unknown
     */
    function do_main() {
        $show_version = KTUtil::arrayGet($_REQUEST, 'show');
        $showall = (isset($show_version) && ($show_version == 'all')) ? true : false;

        $this->oPage->setSecondaryTitle($this->oDocument->getName());
        $this->oPage->setBreadcrumbDetails(_kt('Version History'));

        $aMetadataVersions = KTDocumentMetadataVersion::getByDocument($this->oDocument);
        $aVersions = array();
        foreach ($aMetadataVersions as $oVersion) {
             $version = Document::get($this->oDocument->getId(), $oVersion->getId());
             if ($showall) {
                $aVersions[] = $version;
             } else if ($version->getMetadataStatusID() != VERSION_DELETED) {
                $aVersions[] = $version;
             }
        }

        // render pass.
        $this->oPage->title = _kt('Document History');

        $oTemplate = $this->oValidator->validateTemplate('ktcore/document/metadata_history');

        $aActions = KTDocumentActionUtil::getDocumentActionsByNames(array('ktcore.actions.document.view'));
        $oAction = $aActions[0];
        $oAction->setDocument($this->oDocument);

        // create delete action if user is sys admin or folder admin
        $bShowDelete = false;
        require_once(KT_LIB_DIR . '/security/Permission.inc');
        $oUser =& User::get($_SESSION['userID']);
        $iFolderId = $this->oDocument->getFolderId();
        if (Permission::userIsSystemAdministrator($oUser) || Permission::isUnitAdministratorForFolder($oUser, $iFolderId)) {
            // Check if admin mode is enabled
            $bShowDelete = KTUtil::arrayGet($_SESSION, 'adminmode', false);
        }

        // Check if the document comparison plugin is installed
        $isActive = KTPluginUtil::pluginIsActive('document.comparison.plugin');

        $bShowCompare = false;
        $bShowVersionCompare = false;
        $sUrl = false;

        if ($isActive) {
            $oRegistry =& KTPluginRegistry::getSingleton();
            $oPlugin =& $oRegistry->getPlugin('document.comparison.plugin');

            if ($oPlugin->loadHelpers()) {
                $sUrl = $oPlugin->getPagePath('DocumentComparison');
                $file = $oPlugin->_aPages['document.comparison.plugin/DocumentComparison'][2];

                include_once($file);

                // Check mime type of document for content comparison
                list($bShowCompare, $bShowVersionCompare) = DocumentComparison::checkMimeType($this->oDocument);
            }
        }

        $aTemplateData = array(
              'context' => $this,
              'document_id' => $this->oDocument->getId(),
              'document' => $this->oDocument,
              'versions' => $aVersions,
              'downloadaction' => $oAction,
              'showdelete' => $bShowDelete,
              'showall' => $showall,
              'bShowCompare' => $bShowCompare,
              'bShowVersionCompare' => $bShowVersionCompare,
              'sUrl' => $sUrl
        );

        return $oTemplate->render($aTemplateData);
    }

    /**
     * Display list of metadata versions to compare with the selected version
     *
     * @return unknown
     */
    function do_startComparison() {
        $comparison_version = KTUtil::arrayGet($_REQUEST, 'fComparisonVersion');

        $oDocument =& Document::get($this->oDocument->getId(), $comparison_version);
        if (PEAR::isError($oDocument)) {
            return $this->redirectToMain(_kt('The document you selected was invalid'));
        }

        //if (!Permission::userHasDocumentReadPermission($oDocument)) {
        if (!$this->userHasDocumentReadPermission($oDocument)) {
            return $this->errorRedirectToMain(_kt('You are not allowed to view this document'));
        }
        $this->oDocument =& $oDocument;
        $this->oPage->setSecondaryTitle($oDocument->getName());
        $this->oPage->setBreadcrumbDetails(_kt('Select Document Version to compare against'));

        $aMetadataVersions = KTDocumentMetadataVersion::getByDocument($oDocument);
        $aVersions = array();
        foreach ($aMetadataVersions as $oVersion) {
            $aVersions[] = Document::get($oDocument->getId(), $oVersion->getId());
        }

        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate('ktcore/document/comparison_version_select');
        $aTemplateData = array(
              'context' => $this,
              'document_id' => $this->oDocument->getId(),
              'document' => $oDocument,
              'versions' => $aVersions,
              'downloadaction' => $oAction,
        );

        return $oTemplate->render($aTemplateData);
    }

    /**
     * Display the metadata comparison
     *
     */
    function do_viewComparison() {
        // this is just a redirector
        $QS = array(
            'action' => 'viewComparison',
            'fDocumentId' => $this->oDocument->getId(),
            'fBaseVersion' => $_REQUEST['fBaseVersion'],
            'fComparisonVersion' => $_REQUEST['fComparisonVersion'],
        );

        $frag = array();

        foreach ($QS as $k => $v) {
            $frag[] = sprintf('%s=%s', urlencode($k), urlencode($v));
        }

        redirect(KTUtil::ktLink('view.php',null,implode('&', $frag)));
        // can't use clean urls, they break the functionality.
        //redirect(KTUtil::buildUrl(KTUtil::ktLink('view.php'), $frag));
    }

    function getUserForId($iUserId) {
        $u = User::get($iUserId);
        if (PEAR::isError($u) || ($u == false)) { return _kt('User no longer exists'); }

        return $u->getName();
    }

    /**
     * Confirm the deletion of a version
     *
     * @return unknown
     */
    function do_confirmdeleteVersion() {
        $this->oPage->setSecondaryTitle($this->oDocument->getName());
        $this->oPage->setBreadcrumbDetails(_kt('Delete document version'));

        // Display the version name and number
        $iVersionId = $_REQUEST['version'];
        $oVersion = Document::get($this->oDocument->getId(), $iVersionId);

        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate('ktcore/document/delete_version');
        $aTemplateData = array(
            'context' => $this,
            'fDocumentId' => $this->oDocument->getId(),
            'oVersion' => $oVersion,
        );

        return $oTemplate->render($aTemplateData);
    }

    /**
     * Delete a version
     *
     */
    function do_deleteVersion() {
        $iVersionId = $_REQUEST['versionid'];
        $sReason = $_REQUEST['reason'];
        $oVersion = Document::get($this->oDocument->getId(), $iVersionId);

        $res = KTDocumentUtil::deleteVersion($this->oDocument, $iVersionId, $sReason);

        if (PEAR::isError($res)) {
            $this->addErrorMessage($res->getMessage());
            redirect(KTDocumentAction::getURL());
            exit(0);
        }

        // Record the transaction
        $aOptions['version'] = sprintf('%d.%d', $oVersion->getMajorVersionNumber(), $oVersion->getMinorVersionNumber());
        $oDocumentTransaction = new DocumentTransaction($this->oDocument, _kt('Document version deleted'), 'ktcore.transactions.delete_version', $aOptions);
        $oDocumentTransaction->create();

        redirect(KTDocumentAction::getURL());
    }

}

class KTDocumentViewAction extends KTDocumentAction {

    var $sName = 'ktcore.actions.document.view';
    var $sIconClass = 'download';
	var $bShowIfWriteShared = true;
	var $bShowIfReadShared = true;
	var $btnOrder = 1;

    function getDisplayName() {
        return _kt('Download');
    }

    function getButton() {
        $btn = array();
        $btn['display_text'] = _kt('Download');
        $btn['arrow_class'] = 'arrow_download';
        return $btn;
    }

    function customiseInfo($aInfo) {
        $aInfo['alert'] =  _kt('This will download a copy of the document and is not the same as Checking Out a document.  Changes to this downloaded file will not be managed in the DMS.');
        return $aInfo;
    }

    function do_main() {
        $oStorage = KTStorageManagerUtil::getSingleton();
        $aOptions = array();
        $iVersion = KTUtil::arrayGet($_REQUEST, 'version');
        session_write_close();
        $oDocumentTransaction = new DocumentTransaction($this->oDocument, _kt('Document downloaded'), 'ktcore.transactions.download', $aOptions);
        $oDocumentTransaction->create();

		$oKTTriggerRegistry = KTTriggerRegistry::getSingleton();
        $aTriggers = $oKTTriggerRegistry->getTriggers('download', 'postValidate');
        foreach ($aTriggers as $aTrigger) {
            $sTrigger = $aTrigger[0];
            $oTrigger = new $sTrigger;
            $aInfo = array(
                'document' => $this->oDocument,
            );
            $oTrigger->setInfo($aInfo);
            $ret = $oTrigger->postValidate();
            if (PEAR::isError($ret)) {
                return $ret;
            }
        }

        // fire subscription alerts for the downloaded document
        $oKTConfig =& KTConfig::getSingleton();
        $bNotifications = ($oKTConfig->get('export/enablenotifications', 'on') == 'on') ? true : false;
        if ($bNotifications) {
            $oSubscriptionEvent = new SubscriptionEvent();
            $oFolder = Folder::get($this->oDocument->getFolderID());
            $oSubscriptionEvent->DownloadDocument($this->oDocument, $oFolder);
        }

        if ($iVersion) {
            $oVersion = KTDocumentContentVersion::get($iVersion);
            $aOptions['version'] = sprintf('%d.%d', $oVersion->getMajorVersionNumber(), $oVersion->getMinorVersionNumber());
            $res = $oStorage->downloadVersion($this->oDocument, $iVersion);
        } else {
            $res = $oStorage->download($this->oDocument);
        }

        if ($res === false) {
            session_start();
            $this->addErrorMessage(_kt('The file you requested is not available.'));
            redirect(generateControllerLink('viewDocument',sprintf(_kt('fDocumentId=%d'),$this->oDocument->getId())));
            exit(0);
        }

        exit(0);
    }

}

class KTDocumentCheckOutAction extends KTDocumentAction {

    var $sName = 'ktcore.actions.document.checkout';
    var $_sShowPermission = 'ktcore.permissions.write';
    var $_bMutator = true;
    var $_bMutationAllowedByAdmin = false;
    var $sIconClass = 'checkout';
	var $bShowIfWriteShared = true;
	var $btnOrder = 2;

    function getDisplayName() {
        return _kt('Checkout');
    }

    function _show() {
        $check = parent::_show();
        if($check === false) {
            return 'disabled';
        }
        return $check;
    }

    function getInfo() {
        if ($this->oDocument->getIsCheckedOut()) {
            return null;
        }

        return parent::getInfo();
    }

    function getButton() {
        $btn = array();
        $btn['display_text'] = _kt('Checkout');
        $btn['arrow_class'] = 'check_out';
        return $btn;
    }

    function check() {
        $res = parent::check();
        if ($res !== true) {
            return $res;
        }

        // since we actually check the doc out, then download it ...
        if (($_REQUEST[$this->event_var] == 'checkout_final') && ($this->oDocument->getCheckedOutUserID() == $_SESSION['userID'])) {
             return true;
        }

        // "normal".
        if ($this->oDocument->getIsCheckedOut()) {
            $_SESSION['KTErrorMessage'][] = _kt('This document is already checked out');
            controllerRedirect('viewDocument', 'fDocumentId=' .  $this->oDocument->getId());
            exit(0);
        }

        return true;
    }

    function form_checkout() {
        $oForm = new KTForm;
        $oForm->setOptions(array(
            'action' => 'checkout',
            'fail_action' => 'main',
            'cancel_url' => KTBrowseUtil::getUrlForDocument($this->oDocument),
            'submit_label' => _kt('Checkout document'),
            'context' => &$this,
        ));

        // Electronic Signature if enabled
        global $default;
        if ($default->enableESignatures) {
            $widgets[] = array('ktcore.widgets.info', array(
                    'label' => _kt('This action requires authentication'),
                    'description' => _kt('Please provide your user credentials as confirmation of this action.'),
                    'name' => 'info'
                ));
            $widgets[] = array('ktcore.widgets.string', array(
                    'label' => ($default->useEmailLogin) ? _kt('Email Address') : _kt('Username'),
                    'name' => 'sign_username',
                    'required' => true
                ));
            $widgets[] = array('ktcore.widgets.password', array(
                    'label' => _kt('Password'),
                    'name' => 'sign_password',
                    'required' => true
                ));
        }

        if ($this->oConfig->get('actionreasons/globalReasons') || $default->enableESignatures) {
            $widgets[] = array('ktcore.widgets.reason', array(
                    'label' => _kt('Note'),
    				'required' => ($default->enableESignatures) ? true : false,
                    'name' => 'reason',
                ));
        }
        $widgets[] = array('ktcore.widgets.boolean', array(
                'label' => _kt('Download this file?'),
                'name' => 'download_file',
                'value' => true,
            ));

        $oForm->setWidgets($widgets);

        if ($this->oConfig->get('actionreasons/globalReasons') || $default->enableESignatures) {
            $validators[] = array('ktcore.validators.string', array(
                    'test' => 'reason',
                    'min_length' => 1,
                    'max_length' => 250,
                    'output' => 'reason',
                ));
        }
        $validators[] = array('ktcore.validators.boolean', array(
                'test' => 'download_file',
                'output' => 'download_file',
            ));


        if ($default->enableESignatures) {
            $validators[] = array('electonic.signatures.validators.authenticate', array(
                'object_id' => $this->oDocument->iId,
                'type' => 'document',
                'action' => 'ktcore.transactions.check_out',
                'test' => 'info',
                'output' => 'info'
            ));
        }

        $oForm->setValidators($validators);

        return $oForm;
    }

    function do_main() {
        $this->oPage->setBreadcrumbDetails(_kt('checkout'));
        $oTemplate =& $this->oValidator->validateTemplate('ktcore/action/checkout');

        $oForm = $this->form_checkout();

        $oTemplate->setData(array(
            'context' => &$this,
            'form' => $oForm,
        ));

        return $oTemplate->render();
    }

    function do_checkout() {
        $oForm = $this->form_checkout();
        $res = $oForm->validate();
        if (!empty($res['errors'])) {
            return $oForm->handleError();
        }

        $data = $res['results'];

        $oTemplate =& $this->oValidator->validateTemplate('ktcore/action/checkout_final');
        $sReason = isset($data['reason']) ? $data['reason'] : _kt('Document Checked Out.');

        $this->startTransaction();
        $res = KTDocumentUtil::checkout($this->oDocument, $sReason, $this->oUser);
        if (PEAR::isError($res)) {
            return $this->errorRedirectToMain(sprintf(_kt('Failed to check out the document: %s'), $res->getMessage()));
        }

        $this->commitTransaction();

        if (!$data['download_file']) {
            $this->addInfoMessage(_kt('Document checked out.'));
            redirect(KTBrowseUtil::getUrlForDocument($this->oDocument));
            exit(0);
        }

        $oTemplate->setData(array(
            'context' => &$this,
            'reason' => $sReason,
        ));

        return $oTemplate->render();
    }

    function do_checkout_final() {
        $sReason = KTUtil::arrayGet($_REQUEST, 'reason');
        $this->oValidator->notEmpty($sReason);

        $oKTTriggerRegistry = KTTriggerRegistry::getSingleton();
        $aTriggers = $oKTTriggerRegistry->getTriggers('checkoutDownload', 'postValidate');
        foreach ($aTriggers as $aTrigger) {
            $sTrigger = $aTrigger[0];
            $oTrigger = new $sTrigger;
            $aInfo = array(
                'document' => $this->oDocument,
            );
            $oTrigger->setInfo($aInfo);
            $ret = $oTrigger->postValidate();
            if (PEAR::isError($ret)) {
                return $ret;
            }
        }

        $oStorage = KTStorageManagerUtil::getSingleton();
        $oStorage->download($this->oDocument, true);
        exit(0);
    }

}

class KTDocumentCheckInAction extends KTDocumentAction {

    var $sName = 'ktcore.actions.document.checkin';
    var $_sShowPermission = 'ktcore.permissions.write';
    var $sIconClass = 'checkin';
	var $bShowIfWriteShared = true;
	var $btnOrder = 2;

    function getDisplayName() {
        return _kt('Check-in');
    }

    function getButton() {
        $btn = array();
        $btn['display_text'] = _kt('Check-in');
        $btn['arrow_class'] = 'check_in';
        return $btn;
    }

    function getInfo() {
        if (!$this->oDocument->getIsCheckedOut()) {
            return null;
        }

        $info = parent::getInfo();
        if ($this->oDocument->getCheckedOutUserID() != $this->oUser->getId()) {
            $info['status'] = 'disabled';
        }
        return $info;
    }

    function check() {
        $res = parent::check();
        if ($res !== true) {
            return $res;
        }

        $postExpected = KTUtil::arrayGet($_REQUEST, 'postExpected');
        $postReceived = KTUtil::arrayGet($_REQUEST, 'postReceived');
        if (!empty($postExpected)) {
            $aErrorOptions = array(
                'redirect_to' => array('main', sprintf('fDocumentId=%d', $this->oDocument->getId())),
                'message' => sprintf(_kt('Upload larger than maximum POST size: %s (post_max_size variable in .htaccess or php.ini)'), ini_get('post_max_size')),
            );
            $this->oValidator->notEmpty($postReceived, $aErrorOptions);
        }

        if (!$this->oDocument->getIsCheckedOut()) {
            $_SESSION['KTErrorMessage'][] = _kt('This document is not checked out');
            controllerRedirect('viewDocument', 'fDocumentId=' .  $this->oDocument->getId());
            exit(0);
        }

        if ($this->oDocument->getCheckedOutUserID() != $this->oUser->getId()) {
            $_SESSION['KTErrorMessage'][] = _kt('This document is checked out, but not by you');
            controllerRedirect('viewDocument', 'fDocumentId=' .  $this->oDocument->getId());
            exit(0);
        }

        return true;
    }

    function form_main() {
        global $default;
        $oForm = new KTForm;
        $oForm->setOptions(array(
            'action' => 'checkin',
            'actionparams' => 'postExpected=1&fDocumentId='.$this->oDocument->getId(),
            'fail_action' => 'main',
            'cancel_url' => KTBrowseUtil::getUrlForDocument($this->oDocument),
            'submit_label' => _kt('Check-in'),
            'context' => &$this,
            'file_upload' => true,         // otherwise the post is not received.
        ));

        $major_inc = sprintf('%d.%d', $this->oDocument->getMajorVersionNumber()+1, 0);
        $minor_inc = sprintf('%d.%d', $this->oDocument->getMajorVersionNumber(), $this->oDocument->getMinorVersionNumber()+1);

        // Modify description according to whether the disableForceFilenameOption is set
        if ($default->disableForceFilenameOption) {
            $description = sprintf(_kt('Please specify the file you wish to upload. The file must have the same name as the original: <b>%s</b>'), htmlentities($this->oDocument->getFilename(),ENT_QUOTES,'UTF-8'));
        } else {
            $description = sprintf(_kt('Please specify the file you wish to upload.  Unless you also indicate that you are changing its filename (see "Force Original Filename" below), this will need to be called <b>%s</b>'), htmlentities($this->oDocument->getFilename(),ENT_QUOTES,'UTF-8'));
        }

        // Set the widgets for the form
        $aWidgets = array(
            array('ktcore.widgets.file', array(
                'label' => _kt('File'),
                'description' => $description,
                'name' => 'file',
                'basename' => 'file',
                'required' => true,
            )),
            array('ktcore.widgets.boolean',array(
                'label' => _kt('Major Update'),
                'description' => sprintf(_kt('If this is checked, then the document\'s version number will be increased to %s.  Otherwise, it will be considered a minor update, and the version number will be %s.'), $major_inc, $minor_inc),
                'name' => 'major_update',
                'value' => false,
            ))
        );

        // Electronic Signature if enabled
        if ($default->enableESignatures) {
            $aWidgets[] = array('ktcore.widgets.info', array(
                    'label' => _kt('This action requires authentication'),
                    'description' => _kt('Please provide your user credentials as confirmation of this action.'),
                    'name' => 'info'
                ));
            $aWidgets[] = array('ktcore.widgets.string', array(
                    'label' => ($default->useEmailLogin) ? _kt('Email Address') : _kt('Username'),
                    'name' => 'sign_username',
                    'required' => true
                ));
            $aWidgets[] = array('ktcore.widgets.password', array(
                    'label' => _kt('Password'),
                    'name' => 'sign_password',
                    'required' => true
                ));
        }

        if ($this->oConfig->get('actionreasons/globalReasons') || $default->enableESignatures) {
            $aWidgets[] = array('ktcore.widgets.reason', array(
                    'label' => _kt('Note'),
    				'required' => ($default->enableESignatures) ? true : false,
                    'name' => 'reason',
                ));
        }

        // Set the validators for the widgets
        $aValidators = array(
            array('ktcore.validators.boolean', array(
                'test' => 'major_update',
                'output' => 'major_update',
            )),
            array('ktcore.validators.file', array(
                'test' => 'file',
                'output' => 'file',
            )),
        );

        if ($this->oConfig->get('actionreasons/globalReasons') || $default->enableESignatures) {
            $aValidators[]=array('ktcore.validators.string', array(
                    'test' => 'reason',
                    'min_length' => 1,
                    'max_length' => 250,
                    'output' => 'reason',
            ));
        }

        if ($default->enableESignatures) {
            $aValidators[] = array('electonic.signatures.validators.authenticate', array(
                'object_id' => $this->oDocument->iId,
                'type' => 'document',
                'action' => 'ktcore.transactions.check_in',
                'test' => 'info',
                'output' => 'info'
            ));
        }

        // Add the "Force Original Filename" option if applicable
        if (!$default->disableForceFilenameOption) {
            $aWidgets[] = array('ktcore.widgets.boolean',array(
                'label' => _kt('Force Original Filename'),
                'description' => sprintf(_kt('If this is checked, the uploaded document must have the same filename as the original: <strong>%s</strong>'), htmlentities($this->oDocument->getFilename(),ENT_QUOTES,'UTF-8')),
                'name' => 'forcefilename',
                'value' => true,
            ));

            $aValidators[] = array('ktcore.validators.boolean', array(
                'test' => 'forcefilename',
                'output' => 'forcefilename',
            ));
        }

        // Add widgets and validators to the form
        $oForm->setWidgets($aWidgets);
        $oForm->setValidators($aValidators);

        return $oForm;
    }

    function do_main() {
        $this->oPage->setBreadcrumbDetails(_kt('Check-in'));
        $oTemplate =& $this->oValidator->validateTemplate('ktcore/action/checkin');

        $oForm = $this->form_main();

        $oTemplate->setData(array(
            'context' => &$this,
            'form' => $oForm,
        ));

        return $oTemplate->render();
    }

    function do_checkin() {
        $oForm = $this->form_main();
        $res = $oForm->validate();
        $data = $res['results'];
        $extra_errors = array();

        // If the filename is different to the original check if "Force Original Filename" is set and return an error if it is.
        $docFileName = $this->oDocument->getFilename();
        if ($data['file']['name'] != $docFileName) {
            global $default;

            if ($default->disableForceFilenameOption) {
                $extra_errors['file'] = sprintf(_kt('The file you uploaded was not called "%s". The file must have the same name as the original file.'), htmlentities($docFileName,ENT_QUOTES,'UTF-8'));
            } else if ($data['forcefilename']) {
                $extra_errors['file'] = sprintf(_kt('The file you uploaded was not called "%s". If you wish to change the filename, please set "Force Original Filename" below to false. '), htmlentities($docFileName,ENT_QUOTES,'UTF-8'));
            }
        }

        if (!empty($res['errors']) || !empty($extra_errors)) {
            return $oForm->handleError(null, $extra_errors);
        }

        $sReason = isset($data['reason']) ? $data['reason'] : _kt('Document Checked In.');
        $sCurrentFilename = $docFileName;
        $sNewFilename = $data['file']['name'];
        $aOptions = array();

        if ($data['major_update']) {
            $aOptions['major_update'] = true;
        }

        if ($sCurrentFilename != $sNewFilename) {
            $aOptions['newfilename'] = $sNewFilename;
        }

        // document checkin for the new storage drivers requires the document to be first uploaded
        // to the temp directory from the php upload directory or the checkin will fail
        $oStorage = KTStorageManagerUtil::getSingleton();
        $oKTConfig =& KTConfig::getSingleton();
        $sTempFilename = $oStorage->tempnam($oKTConfig->get("urls/tmpDirectory"), 'kt_storecontents');
        $oStorage->uploadTmpFile($data['file']['tmp_name'], $sTempFilename);
        $data['file']['tmp_name'] = $sTempFilename;
        $res = KTDocumentUtil::checkin($this->oDocument, $data['file']['tmp_name'], $sReason, $this->oUser, $aOptions);
        if (PEAR::isError($res)) {
        	$GLOBALS['default']->log->error('Pear Error on Checkin: '.$res->getMessage());
            $this->errorRedirectToMain(_kt('An error occurred while trying to check in the document'), 'fDocumentId=' . $this->oDocument->getId() . '&reason=' . $sReason);
        }

        redirect(KTBrowseUtil::getUrlForDocument($this->oDocument));
        exit(0);
    }

}

class KTDocumentCancelCheckOutAction extends KTDocumentAction {

    var $sName = 'ktcore.actions.document.cancelcheckout';

    var $_sShowPermission = 'ktcore.permissions.write';
    var $bAllowInAdminMode = true;
    var $bInAdminMode = null;
    //var $sIconClass = 'cancel_checkout';

	var $bShowIfWriteShared = true;
    var $sIconClass = 'cancel-checkout';
    var $sParentBtn = 'ktcore.actions.document.checkin';

    function getDisplayName() {
        return _kt('Cancel Checkout');
    }

    function getInfo() {
        if (!$this->oDocument->getIsCheckedOut()) {
            return null;
        }

        if (is_null($this->bInAdminMode)) {
            $oFolder = Folder::get($this->oDocument->getFolderId());
            if (KTBrowseUtil::inAdminMode($this->oUser, $oFolder)) {
                $this->bAdminMode = true;
                return parent::getInfo();
            }
        } else if ($this->bInAdminMode == true) {
            return parent::getInfo();
        }

        if ($this->oDocument->getCheckedOutUserID() != $this->oUser->getId()) {
            return null;
        }

        return parent::getInfo();
    }

    function check() {
        $res = parent::check();

        if ($res !== true) {
            return $res;
        }

        if (!$this->oDocument->getIsCheckedOut()) {
            $_SESSION['KTErrorMessage'][] = _kt('This document is not checked out');
            controllerRedirect('viewDocument', 'fDocumentId=' .  $this->oDocument->getId());
            exit(0);
        }

        // hard override if we're in admin mode for this doc.
        if (is_null($this->bInAdminMode)) {
            $oFolder = Folder::get($this->oDocument->getFolderId());
            if (KTBrowseUtil::inAdminMode($this->oUser, $oFolder)) {
                $this->bAdminMode = true;
                return true;
            }
        } else if ($this->bInAdminMode == true) {
            return true;
        }

        if ($this->oDocument->getCheckedOutUserID() != $this->oUser->getId()) {
            $_SESSION['KTErrorMessage'][] = _kt('This document is checked out, but not by you');
            controllerRedirect('viewDocument', 'fDocumentId=' .  $this->oDocument->getId());
            exit(0);
        }

        return true;
    }

    function form_main() {
        $oForm = new KTForm;
        $oForm->setOptions(array(
            'action' => 'checkin',
            'fail_action' => 'main',
            'cancel_url' => KTBrowseUtil::getUrlForDocument($this->oDocument),
            'submit_label' => _kt('Cancel Checkout'),
            'context' => &$this,
        ));

        // Electronic Signature if enabled
        global $default;
        if ($default->enableESignatures) {
            $widgets[] = array('ktcore.widgets.info', array(
                    'label' => _kt('This action requires authentication'),
                    'description' => _kt('Please provide your user credentials as confirmation of this action.'),
                    'name' => 'info'
                ));
            $widgets[] = array('ktcore.widgets.string', array(
                    'label' => ($default->useEmailLogin) ? _kt('Email Address') : _kt('Username'),
                    'name' => 'sign_username',
                    'required' => true
                ));
            $widgets[] = array('ktcore.widgets.password', array(
                    'label' => _kt('Password'),
                    'name' => 'sign_password',
                    'required' => true
                ));
        }

        if ($this->oConfig->get('actionreasons/globalReasons') || $default->enableESignatures) {
            $widgets[] = array('ktcore.widgets.reason', array(
                    'label' => _kt('Note'),
    			    'required' => ($default->enableESignatures) ? true : false,
                    'name' => 'reason',
                ));
        }

        $oForm->setWidgets($widgets);

        if ($this->oConfig->get('actionreasons/globalReasons') || $default->enableESignatures) {
            $validators[] = array('ktcore.validators.string', array(
                    'test' => 'reason',
                    'min_length' => 1,
                    'max_length' => 250,
                    'output' => 'reason',
                ));
        }

        // Electronic signature validation - does the authentication
        if ($default->enableESignatures) {
            $validators[] = array('electonic.signatures.validators.authenticate', array(
                'object_id' => $this->oDocument->iId,
                'type' => 'document',
                'action' => 'ktcore.transactions.cancel_checkout',
                'test' => 'info',
                'output' => 'info'
            ));
        }

        $oForm->setValidators($validators);

        return $oForm;
    }

    function do_main() {
        $this->oPage->setBreadcrumbDetails(_kt('cancel checkout'));
        if (ACCOUNT_ROUTING_ENABLED) {
        	$oTemplate =& $this->oValidator->validateTemplate('core/action/cancel_checkout');
        }
        else {
        	$oTemplate =& $this->oValidator->validateTemplate('ktcore/action/cancel_checkout');
        }

        $oForm = $this->form_main();

        $oTemplate->setData(array(
            'context' => &$this,
            'form' => $oForm,
            'document' => $this->oDocument,
        ));

        return $oTemplate->render();
    }

    function do_checkin() {
        $oForm = $this->form_main();
        $res = $oForm->validate();
        if (!empty($res['errors'])) {
            return $oForm->handleError();
        }

        $data = $res['results'];

        $this->startTransaction();
        // actually do the checkin.
        $this->oDocument->setIsCheckedOut(0);
        $this->oDocument->setCheckedOutUserID(-1);
        $res = $this->oDocument->update();
        if (PEAR::isError($res) || ($res === false)) {
            $this->rollbackTransaction();
            return $this->errorRedirectToMain(_kt('Failed to force the document\'s checkin.'),sprintf('fDocumentId=%d'),$this->oDocument->getId());
        }

        // checkout cancelled transaction
        $reason=isset($data['reason']) ? $data['reason'] : _kt('Document Checkout Cancelled.');
        $oDocumentTransaction = new DocumentTransaction($this->oDocument, $reason, 'ktcore.transactions.force_checkin');
        $res = $oDocumentTransaction->create();
        if (PEAR::isError($res) || ($res === false)) {
            $this->rollbackTransaction();
            return $this->errorRedirectToMain(_kt('Failed to force the document\'s checkin.'),sprintf('fDocumentId=%d'),$this->oDocument->getId());
        }

        $this->commitTransaction();
        redirect(KTBrowseUtil::getUrlForDocument($this->oDocument));
    }

}

class KTDocumentDeleteAction extends KTDocumentAction {

    var $sName = 'ktcore.actions.document.delete';
    var $_sShowPermission = 'ktcore.permissions.delete';
    var $_bMutator = true;

    var $sIconClass = 'delete';
    var $sParentBtn = 'more';

    function getDisplayName() {
        return _kt('Delete');
    }

    function getInfo() {
        if ($this->oDocument->getIsCheckedOut()) {
            return null;
        }
        return parent::getInfo();
    }

    function check() {
        $res = parent::check();
        if ($res !== true) {
            return $res;
        }
        if ($this->oDocument->getIsCheckedOut()) {
            $_SESSION['KTErrorMessage'][]= _kt('This document can\'t be deleted because it is checked out');
            controllerRedirect('viewDocument', 'fDocumentId=' .  $this->oDocument->getId());
            exit(0);
        }

        return true;
    }

	function form_confirm() {
        $oForm = new KTForm;
        $oForm->setOptions(array(
            'description' => _kt('There are shortcuts linking to this document; deleting the document will automatically delete them. Would you like to continue?'),
            'action' => 'main',
            'fail_action' => 'main',
            'cancel_url' => KTBrowseUtil::getUrlForDocument($this->oDocument),
            'submit_label' => _kt('Delete Document'),
            'context' => &$this,
        ));

        return $oForm;
    }

    function form_main() {
        $oForm = new KTForm;
        $oForm->setOptions(array(
            'action' => 'delete',
            'fail_action' => 'main',
            'cancel_url' => KTBrowseUtil::getUrlForDocument($this->oDocument),
            'submit_label' => _kt('Delete Document'),
            'context' => &$this,
        ));

        // Electronic Signature if enabled
        global $default;
        if ($default->enableESignatures) {
            $widgets[] = array('ktcore.widgets.info', array(
                    'label' => _kt('This action requires authentication'),
                    'description' => _kt('Please provide your user credentials as confirmation of this action.'),
                    'name' => 'info'
                ));
            $widgets[] = array('ktcore.widgets.string', array(
                    'label' => ($default->useEmailLogin) ? _kt('Email Address') : _kt('Username'),
                    'name' => 'sign_username',
                    'required' => true
                ));
            $widgets[] = array('ktcore.widgets.password', array(
                    'label' => _kt('Password'),
                    'name' => 'sign_password',
                    'required' => true
                ));
        }

        $getReason = $this->oDocument->getImmutable() || $this->oConfig->get('actionreasons/globalReasons') || $default->enableESignatures;
        if ($getReason) {
            $widgets[] = array('ktcore.widgets.reason', array(
                    'label' => _kt('Note'),
    			    'required' => ($default->enableESignatures) ? true : false,
                    'name' => 'reason',
                ));
            $validators[] = array('ktcore.validators.string', array(
                'test' => 'reason',
                'min_length' => 1,
                'max_length' => 250,
                'output' => 'reason',
            ));
        }

        if ($default->enableESignatures) {
            $validators[] = array('electonic.signatures.validators.authenticate', array(
                'object_id' => $this->oDocument->iId,
                'type' => 'document',
                'action' => 'ktcore.transactions.delete',
                'test' => 'info',
                'output' => 'info'
            ));
        }

        $oForm->setWidgets($widgets);
        $oForm->setValidators($validators);

        return $oForm;
    }

    function do_main() {
        $this->oPage->setBreadcrumbDetails(_kt('Delete'));

    	//check if we need confirmation for symblolic links linking to this document
		if (count($this->oDocument->getSymbolicLinks())>0 && KTutil::arrayGet($_REQUEST,'postReceived') != 1) {
        	$this->redirectTo("confirm");
        }

        $oTemplate =& $this->oValidator->validateTemplate('ktcore/action/delete');
        $oForm = $this->form_main();
        $oTemplate->setData(array(
            'context' => &$this,
            'form' => $oForm,
        ));

        return $oTemplate->render();
    }

    function do_confirm() {
    	$this->oPage->setBreadcrumbDetails(_kt('Confirm delete'));
    	$oTemplate =& $this->oValidator->validateTemplate('ktcore/action/delete_confirm');
        $oForm = $this->form_confirm();
    	$oTemplate->setData(array(
            'context' => &$this,
            'form' => $oForm,
        ));

        return $oTemplate->render();
    }

    function do_delete() {
        $oForm = $this->form_main();
        $res = $oForm->validate();
        $data = $res['results'];
        if (!empty($res['errors'])) {
            return $oForm->handleError();
        }

        $sReason = isset($data['reason']) ? $data['reason'] : _kt('Document Deleted.');

        $fFolderId = $this->oDocument->getFolderId();
        $res = KTDocumentUtil::delete($this->oDocument, $sReason);
        if (PEAR::isError($res)) {
            $this->errorRedirectToMain(sprintf(_kt('Unexpected failure deleting document: %s'), $res->getMessage()));
        }

        $_SESSION['KTInfoMessage'][] = sprintf(_kt('Document "%s" Deleted.'),$this->oDocument->getName());

        controllerRedirect('browse', 'fFolderId=' .  $fFolderId);
        exit(0);
    }

}

class KTDocumentMoveAction extends KTDocumentAction {

    var $sName = 'ktcore.actions.document.move';
    var $_sShowPermission = 'ktcore.permissions.write';
    var $_bMutator = true;
    var $sIconClass = 'move';
    var $sParentBtn = 'more';

    function getDisplayName() {
        return _kt('Move');
    }

    function getInfo() {
        if ($this->oDocument->getIsCheckedOut()) {
            return null;
        }

        return parent::getInfo();
    }

    function check() {
        $res = parent::check();
        if ($res !== true) {
            return $res;
        }

        if ($this->oDocument->getIsCheckedOut()) {
            $_SESSION['KTErrorMessage'][]= _kt('This document can\'t be moved because it is checked out');
            controllerRedirect('viewDocument', 'fDocumentId=' .  $this->oDocument->getId());
            exit(0);
        }
        $this->persistParams(array('fFolderId'));
        $iFolderId = KTUtil::arrayGet($_REQUEST, 'fFolderId', $this->oDocument->getFolderId());
        $this->oFolder = $this->oValidator->validateFolder($iFolderId);
        $this->oDocumentFolder = $this->oValidator->validateFolder($this->oDocument->getFolderId());

        return true;
    }

    function do_main() {
        $oForm = $this->form_move();
        return $oForm->renderPage(_kt('Move Document') . ': ' . $this->oDocument->getName());
    }

    function form_move() {
        $oForm = new KTForm;
        $oForm->setOptions(array(
            'submit_label' => _kt('Move'),
            'identifier' => 'ktcore.actions.movedoc',
            'action' => 'move',
            'cancel_url' => KTBrowseUtil::getUrlForDocument($this->oDocument),
            'fail_action' => 'main',
            'context' => $this,
        ));

        /*
         *  This is somewhat more complex than most forms, since the "filename"
         *  and title shouldn't appear unless there's a clash.
         *
         *  This is still not the most elegant solution.
         */

        $widgets[] = array('ktcore.widgets.foldercollection', array(
                'label' => _kt('Target Folder'),
			    'required' => true,
			    'name' => 'browse',
                'folder_id' => $this->oDocument->getFolderID()
        ));

        // Electronic Signature if enabled
        global $default;
        if ($default->enableESignatures) {
            $widgets[] = array('ktcore.widgets.info', array(
                    'label' => _kt('This action requires authentication'),
                    'description' => _kt('Please provide your user credentials as confirmation of this action.'),
                    'name' => 'info'
                ));
            $widgets[] = array('ktcore.widgets.string', array(
                    'label' => ($default->useEmailLogin) ? _kt('Email Address') : _kt('Username'),
                    'name' => 'sign_username',
                    'required' => true
                ));
            $widgets[] = array('ktcore.widgets.password', array(
                    'label' => _kt('Password'),
                    'name' => 'sign_password',
                    'required' => true
                ));
        }

        if ($this->oConfig->get('actionreasons/globalReasons') || $default->enableESignatures) {
            $widgets[] = array('ktcore.widgets.reason', array(
                    'label' => _kt('Note'),
    			    'required' => ($default->enableESignatures) ? true : false,
                    'name' => 'reason',
            ));
        }

        $oForm->setWidgets($widgets);

        if ($this->oConfig->get('actionreasons/globalReasons') || $default->enableESignatures) {
            $validators[] = array('ktcore.validators.string', array(
                    'test' => 'reason',
                    'min_length' => 1,
                    'max_length' => 250,
                    'output' => 'reason',
            ));
        }

        $validators[] = array('ktcore.validators.entity', array(
                'class' => 'Folder',
                'test' => 'browse',
                'output' => 'browse',
        ));

        if ($default->enableESignatures) {
            $validators[] = array('electonic.signatures.validators.authenticate', array(
                'object_id' => $this->oDocument->iId,
                'type' => 'document',
                'action' => 'ktcore.transactions.move',
                'test' => 'info',
                'output' => 'info'
            ));
        }

        $oForm->setValidators($validators);

        // here's the ugly bit.

        $err = $oForm->getErrors();
        if (!empty($err['name']) || !empty($err['filename'])) {
            $oForm->addWidget(
                array('ktcore.widgets.string', array(
                    'label' => _kt('Document Title'),
                    'value' => sanitizeForHTML($this->oDocument->getName()),
                    'important_description' => _kt('Please indicate a new title to use to resolve any title conflicts.'),
                    'name' => 'name',
                    'required' => true,
                ))
            );

            $oForm->addValidator(
                array('ktcore.validators.string', array(
                    'output' => 'name',
                    'test' => 'name'
                ))
            );

            $oForm->addWidget(
                array('ktcore.widgets.string', array(
                    'label' => _kt('Filename'),
                    'value' => sanitizeForHTML($this->oDocument->getFilename()),
                    'important_description' => _kt('Please indicate a new filename to use to resolve any conflicts.'),
                    'name' => 'filename',
                    'required' => true,
                ))
            );

            $oForm->addValidator(
                array('ktcore.validators.string', array(
                    'output' => 'filename',
                    'test' => 'filename'
                ))
            );
        }

        return $oForm;
    }

    function do_move() {
        $oForm = $this->form_move();
        $res = $oForm->validate();
        $errors = $res['errors'];
        $data = $res['results'];
        $sReason = isset($data['reason']) ? $data['reason'] : _kt('Document Moved.');
        $extra_errors = array();

        if (!is_null($data['browse'])) {
            if ($data['browse']->getId() == $this->oDocument->getFolderID()) {
                $extra_errors['browse'] = _kt('You cannot move the document within the same folder.');
            } else {
                $bNameClash = KTDocumentUtil::nameExists($data['browse'], $this->oDocument->getName());
                if ($bNameClash && isset($data['name'])) {
                    $name = $data['name'];
                    $bNameClash = KTDocumentUtil::nameExists($data['browse'], $name);
                } else {
                    $name = $this->oDocument->getName();
                }

                if ($bNameClash) {
                    $extra_errors['name'] = _kt('A document with this title already exists in your chosen folder.  Please choose a different folder, or specify a new title for the copied document.');
            }

                $bFileClash = KTDocumentUtil::fileExists($data['browse'], $this->oDocument->getFilename());
                if ($bFileClash && isset($data['filename'])) {
                    $filename = $data['filename'];
                    $bFileClash = KTDocumentUtil::fileExists($data['browse'], $filename);
                } else {
                    $filename = $this->oDocument->getFilename();
                }

                if ($bFileClash) {
                    $extra_errors['filename'] = _kt('A document with this filename already exists in your chosen folder.  Please choose a different folder, or specify a new filename for the copied document.');
                }

                if (!Permission::userHasFolderWritePermission($data['browse'])) {
                    $extra_errors['browse'] = _kt('You do not have permission to create new documents in that folder.');
                }
            }
        }

        if (!empty($errors) || !empty($extra_errors)) {
            return $oForm->handleError(null, $extra_errors);
        }

        $this->startTransaction();
        // now try update it.

        $res = KTDocumentUtil::move($this->oDocument, $data['browse'], $this->oUser, $sReason);
        if (PEAR::isError($oNewDoc)) {
            $this->errorRedirectTo('main', _kt('Failed to move document: ') . $oNewDoc->getMessage());
            exit(0);
        }

        $this->oDocument->setName($name);       // if needed.
        $this->oDocument->setFilename($filename);   // if needed.

        $res = $this->oDocument->update();
        if (PEAR::isError($res)) {
            return $this->errorRedirectTo('main', _kt('Failed to move document: ') . $res->getMessage());
        }

        $this->commitTransaction();

        controllerRedirect('viewDocument', 'fDocumentId=' .  $this->oDocument->getId());
        exit(0);
    }

}

class KTDocumentCopyColumn extends TitleColumn {

    function KTDocumentCopyColumn($sLabel, $sName, $oDocument) {
        $this->oDocument = $oDocument;
        parent::TitleColumn($sLabel, $sName);
    }
    function buildFolderLink($aDataRow) {
        return KTUtil::addQueryString($_SERVER['PHP_SELF'], sprintf('fDocumentId=%d&fFolderId=%d', $this->oDocument->getId(), $aDataRow['folder']->getId()));
    }

}

// {{{ KTDocumentMoveAction
class KTDocumentCopyAction extends KTDocumentAction {

    var $sName = 'ktcore.actions.document.copy';
    var $_sShowPermission = 'ktcore.permissions.read';

    var $sIconClass = 'copy';
    var $sParentBtn = 'more';

    function getDisplayName() {
        return _kt('Copy');
    }

    function getInfo() {
        if ($this->oDocument->getIsCheckedOut()) {
            return null;
        }

        return parent::getInfo();
    }

    function check() {
        $res = parent::check();
        if ($res !== true) {
            return $res;
        }

        if ($this->oDocument->getIsCheckedOut()) {
            $_SESSION['KTErrorMessage'][]= _kt('This document can\'t be copied because it is checked out');
            controllerRedirect('viewDocument', 'fDocumentId=' .  $this->oDocument->getId());
            exit(0);
        }

        $iFolderId = KTUtil::arrayGet($_REQUEST, 'fFolderId', $this->oDocument->getFolderId());
        $this->oFolder = $this->oValidator->validateFolder($iFolderId);
        $this->oDocumentFolder = $this->oValidator->validateFolder($this->oDocument->getFolderId());

        return true;
    }

    function form_copyselection() {
        $oForm = new KTForm;
        $oForm->setOptions(array(
            'submit_label' => _kt('Copy'),
            'identifier' => 'ktcore.actions.copydoc',
            'action' => 'copy',
            'cancel_url' => KTBrowseUtil::getUrlForDocument($this->oDocument),
            'fail_action' => 'main',
            'context' => $this,
        ));

        /*
         *  This is somewhat more complex than most forms, since the "filename"
         *  and title shouldn't appear unless there's a clash.
         *
         *  This is still not the most elegant solution.
         */

        $widgets = array();
        $widgets[] = array('ktcore.widgets.foldercollection', array(
                'label' => _kt('Target Folder'),
			    'required' => false,
			    'name' => 'browse',
                'folder_id' => $this->oDocument->getFolderID(),
            ));

        // Electronic Signature if enabled
        global $default;
        if ($default->enableESignatures) {
            $widgets[] = array('ktcore.widgets.info', array(
                    'label' => _kt('This action requires authentication'),
                    'description' => _kt('Please provide your user credentials as confirmation of this action.'),
                    'name' => 'info'
                ));
            $widgets[] = array('ktcore.widgets.string', array(
                    'label' => ($default->useEmailLogin) ? _kt('Email Address') : _kt('Username'),
                    'name' => 'sign_username',
                    'required' => true
                ));
            $widgets[] = array('ktcore.widgets.password', array(
                    'label' => _kt('Password'),
                    'name' => 'sign_password',
                    'required' => true
                ));
        }

        if ($this->oConfig->get('actionreasons/globalReasons') || $default->enableESignatures) {
            $widgets[] = array('ktcore.widgets.reason', array(
                'label' => _kt('Note'),
				'required' => ($default->enableESignatures) ? true : false,
                'name' => 'reason',
            ));
        }

        $oForm->setWidgets($widgets);

        $validators = array();
        if ($this->oConfig->get('actionreasons/globalReasons') || $default->enableESignatures) {
            $validators[] = array('ktcore.validators.string', array(
                    'test' => 'reason',
                    'min_length' => 1,
                    'max_length' => 250,
                    'output' => 'reason',
                ));
        }

        $validators[] = array('ktcore.validators.entity', array(
                'class' => 'Folder',
                'test' => 'browse',
                'output' => 'browse',
            ));

        if ($default->enableESignatures) {
            $validators[] = array('electonic.signatures.validators.authenticate', array(
                'object_id' => $this->oDocument->iId,
                'type' => 'document',
                'action' => 'ktcore.transactions.copy',
                'test' => 'info',
                'output' => 'info'
            ));
        }

        $oForm->setValidators($validators);

        // here's the ugly bit.

        $err = $oForm->getErrors();
        if (!empty($err['name']) || !empty($err['filename'])) {
            $oForm->addWidget(
                array('ktcore.widgets.string', array(
                    'label' => _kt('Document Title'),
                    'value' => sanitizeForHTML($this->oDocument->getName()),
                    'important_description' => _kt('Please indicate a new title to use to resolve any title conflicts.'),
                    'name' => 'name',
                    'required' => true,
                ))
            );

            $oForm->addValidator(
                array('ktcore.validators.string', array(
                    'output' => 'name',
                    'test' => 'name'
                ))
            );

            $oForm->addWidget(
                array('ktcore.widgets.string', array(
                    'label' => _kt('Filename'),
                    'value' => sanitizeForHTML($this->oDocument->getFilename()),
                    'important_description' => _kt('Please indicate a new filename to use to resolve any conflicts.'),
                    'name' => 'filename',
                    'required' => true,
                ))
            );

            $oForm->addValidator(
                array('ktcore.validators.string', array(
                    'output' => 'filename',
                    'test' => 'filename'
                ))
            );
        }

        return $oForm;
    }

    function do_main() {
        $this->oPage->setBreadcrumbDetails(_kt('Copy'));
        $oForm = $this->form_copyselection();
        return $oForm->renderPage(_kt('Copy') . ': ' . $this->oDocument->getName());
    }

    function do_copy() {
        $oForm = $this->form_copyselection();
        $res = $oForm->validate();
        $errors = $res['errors'];
        $data = $res['results'];
        $sReason = isset($data['reason']) ? $data['reason'] : _kt('Document Copied.');
        $extra_errors = array();

        if (!is_null($data['browse'])) {
            $bNameClash = KTDocumentUtil::nameExists($data['browse'], $this->oDocument->getName());
            if ($bNameClash && isset($data['name'])) {
                $name = $data['name'];
                $bNameClash = KTDocumentUtil::nameExists($data['browse'], $name);
            } else {
                $name = $this->oDocument->getName();
            }

            if ($bNameClash) {
                $extra_errors['name'] = _kt('A document with this title already exists in your chosen folder.  Please choose a different folder, or specify a new title for the copied document.');
            }

            $bFileClash = KTDocumentUtil::fileExists($data['browse'], $this->oDocument->getFilename());

            if ($bFileClash && isset($data['filename'])) {
                $filename = $data['filename'];
                $bFileClash = KTDocumentUtil::fileExists($data['browse'], $filename);
            } else {
                $filename = $this->oDocument->getFilename();
            }

            if ($bFileClash) {
                $extra_errors['filename'] = _kt('A document with this filename already exists in your chosen folder.  Please choose a different folder, or specify a new filename for the copied document.');
            }

            if (!Permission::userHasFolderWritePermission($data['browse'])) {
                $extra_errors['browse'] = _kt('You do not have permission to create new documents in that folder.');
            }
        }

        if (!empty($errors) || !empty($extra_errors)) {
            return $oForm->handleError(null, $extra_errors);
        }

        // FIXME agree on document-duplication rules re: naming, etc.

        $this->startTransaction();
        // now try update it.

        $oNewDoc = KTDocumentUtil::copy($this->oDocument, $data['browse'], $sReason);
        if (PEAR::isError($oNewDoc)) {
            $this->errorRedirectTo('main', _kt('Failed to copy document: ') . $oNewDoc->getMessage(), sprintf('fDocumentId=%d&fFolderId=%d', $this->oDocument->getId(), $this->oFolder->getId()));
            exit(0);
        }

        $oNewDoc->setName($name);
        $oNewDoc->setFilename($filename);

        $res = $oNewDoc->update();
        if (PEAR::isError($res)) {
            return $this->errorRedirectTo('main', _kt('Failed to copy document: ') . $res->getMessage(), sprintf('fDocumentId=%d&fFolderId=%d', $this->oDocument->getId(), $this->oFolder->getId()));
        }

        $this->commitTransaction();

        $_SESSION['KTInfoMessage'][] = _kt('Document copied.');

        controllerRedirect('viewDocument', 'fDocumentId=' .  $oNewDoc->getId());
        exit(0);
    }

}

class KTDocumentArchiveAction extends KTDocumentAction {

    var $sName = 'ktcore.actions.document.archive';
    var $_sShowPermission = 'ktcore.permissions.write';
    var $_bMutator = false;
    var $sIconClass = 'archive';
    var $sParentBtn = 'more';

    function getDisplayName() {
        return _kt('Archive');
    }

    function getInfo() {
        if ($this->oDocument->getIsCheckedOut()) {
            return null;
        }

        return parent::getInfo();
    }

	function form_confirm() {
        $oForm = new KTForm;
        $oForm->setOptions(array(
            'description' => _kt('There are shortcuts linking to this document; archiving the document automatically will delete them. Would you like to continue?'),
            'action' => 'main',
            'fail_action' => 'main',
            'cancel_url' => KTBrowseUtil::getUrlForDocument($this->oDocument),
            'submit_label' => _kt('Archive Document'),
            'context' => &$this,
        ));


        return $oForm;
    }

    function form_main() {
        $oForm = new KTForm;
        $oForm->setOptions(array(
            'action' => 'archive',
            'fail_action' => 'main',
            'cancel_url' => KTBrowseUtil::getUrlForDocument($this->oDocument),
            'submit_label' => _kt('Archive Document'),
            'context' => &$this,
        ));

        // Electronic Signature if enabled
        global $default;
        if ($default->enableESignatures) {
            $widgets[] = array('ktcore.widgets.info', array(
                    'label' => _kt('This action requires authentication'),
                    'description' => _kt('Please provide your user credentials as confirmation of this action.'),
                    'name' => 'info'
                ));
            $widgets[] = array('ktcore.widgets.string', array(
                    'label' => ($default->useEmailLogin) ? _kt('Email Address') : _kt('Username'),
                    'name' => 'sign_username',
                    'required' => true
                ));
            $widgets[] = array('ktcore.widgets.password', array(
                    'label' => _kt('Password'),
                    'name' => 'sign_password',
                    'required' => true
                ));
        }

        if ($this->oConfig->get('actionreasons/globalReasons') || $default->enableESignatures) {
            $widgets[] = array('ktcore.widgets.reason', array(
                    'label' => _kt('Note'),
    			    'required' => ($default->enableESignatures) ? true : false,
                    'name' => 'reason',
                ));
        }

        $oForm->setWidgets($widgets);

        if ($this->oConfig->get('actionreasons/globalReasons') || $default->enableESignatures) {
            $validators[] = array('ktcore.validators.string', array(
                'test' => 'reason',
                'min_length' => 1,
                'max_length' => 250,
                'output' => 'reason',
            ));
        }

        if ($default->enableESignatures) {
            $validators[] = array('electonic.signatures.validators.authenticate', array(
                'object_id' => $this->oDocument->iId,
                'type' => 'document',
                'action' => 'ktcore.transactions.archive',
                'test' => 'info',
                'output' => 'info'
            ));
        }

        $oForm->setValidators($validators);

        return $oForm;
    }

    function do_main() {
		//if there are symbolic links linking to this document we need confirmation
    	if (count($this->oDocument->getSymbolicLinks())>0 && KTutil::arrayGet($_REQUEST,'postReceived') != 1) {
        	$this->redirectTo("confirm");
        }
        $this->oPage->setBreadcrumbDetails(_kt('Archive Document'));
        $oTemplate =& $this->oValidator->validateTemplate('ktcore/action/archive');

        $oForm = $this->form_main();

        $oTemplate->setData(array(
            'context' => &$this,
            'form' => $oForm,
        ));

        return $oTemplate->render();
    }

	function do_confirm() {
    	$this->oPage->setBreadcrumbDetails(_kt('Confirm archive'));
    	$oTemplate =& $this->oValidator->validateTemplate('ktcore/action/archive_confirm');
        $oForm = $this->form_confirm();
    	$oTemplate->setData(array(
            'context' => &$this,
            'form' => $oForm,
        ));

        return $oTemplate->render();
    }

    function do_archive() {
        $oForm = $this->form_main();
        $res = $oForm->validate();
        $data = $res['results'];
        if (!empty($res['errors'])) {
            return $oForm->handleError();
        }

        $sReason = isset($data['reason']) ? $data['reason'] : _kt('Document Archived.');

        $res = KTDocumentUtil::archive($this->oDocument, $sReason);

        if (PEAR::isError($res)) {
            $_SESSION['KTErrorMessage'][] = $res->getMessage();
            controllerRedirect('viewDocument', 'fDocumentId=' .  $this->oDocument->getId());
            exit(0);
        }

        $_SESSION['KTInfoMessage'][] = _kt('Document archived.');
        controllerRedirect('browse', 'fFolderId=' .  $this->oDocument->getFolderID());
        exit(0);
    }

}

class KTAjaxDocumentWorkflowAction extends KTDocumentAction {
    public $sName = 'ktajax.actions.document.workflow';
    public $_sShowPermission = 'ktcore.permissions.read';
    public $sHelpPage = 'ktcore/user/workflow.html';
    public $sIconClass = 'manage-workflow';
    public $sParentBtn = 'more';
	public $bShowIfWriteShared = true;
	
    public function predispatch() {
        $this->persistParams(array('fTransitionId'));
    }

    public function getDisplayName() {
		return '';
    }

    public function getInfo() {
		return false;
    }

	public function do_main()
    {
        $oTemplate = $this->oValidator->validateTemplate('ktcore/workflow/blocks/documentWorkflowBlock');
        $oDocument = $this->oValidator->validateDocument($_REQUEST['fDocumentId']);

        $oWorkflow = KTWorkflowUtil::getWorkflowForDocument($oDocument);
        $oWorkflowState = KTWorkflowUtil::getWorkflowStateForDocument($oDocument);

        $oUser =& User::get($_SESSION['userID']);

        // If the document is checked out - set transitions and workflows to empty and set checkedout to true
        $bIsCheckedOut = $this->oDocument->getIsCheckedOut();
        if ($bIsCheckedOut) {
            $aTransitions = array();
            $aWorkflows = array();
            $transition_fields = array();
            $bHasPerm = FALSE;
        } else {
            $aTransitions = KTWorkflowUtil::getTransitionsForDocumentUser($oDocument, $oUser);
            $aWorkflows = KTWorkflow::getList('start_state_id IS NOT NULL AND enabled = 1 ');
            $bHasPerm = false;
            if (KTPermissionUtil::userHasPermissionOnItem($oUser, 'ktcore.permissions.workflow', $oDocument)) {
                $bHasPerm = true;
            }
            $fieldErrors = null;
            $transition_fields = array();
            if ($aTransitions) {
                $aVocab = array();
                foreach ($aTransitions as $oTransition) {
                	if (is_null($oTransition) || PEAR::isError($oTransition)) {
                		continue;
                	}

                    $aVocab[$oTransition->getId()] = $oTransition->showDescription();
                }
                $fieldOptions = array('vocab' => $aVocab);
                $transition_fields[] = new KTLookupWidget(_kt('Transition'), _kt(''), 'fTransitionId', null, $this->oPage, false, null, $fieldErrors, $fieldOptions);
                $transition_fields[] = new KTTextWidget(
                    _kt('Comment'), _kt(''),
                    'fComments', '',
                    $this->oPage, false, null, null,
                    array('cols' => 55, 'rows' => 4));
            }
        }

        // Add an electronic signature
    	global $default;
    	if ($default->enableESignatures) {
    	    $sUrl = KTPluginUtil::getPluginPath('electronic.signatures.plugin', true);
    	    $heading = _kt('You are attempting to modify the document workflow');
    	    $submit['type'] = 'button';
    	    $submit['onclick'] = "javascript: showSignatureForm('{$sUrl}', '{$heading}', 'ktcore.transactions.modify_workflow', 'document', 'start_workflow_form', 'submit', {$this->oDocument->iId});";

    	    $heading2 = _kt('You are attempting to transition the document workflow');
    	    $submit2['onclick'] = "javascript: showSignatureForm('{$sUrl}', '{$heading2}', 'ktcore.transactions.transition_workflow', 'document', 'transition_wf_form', 'submit', {$this->oDocument->iId});";
    	} else {
    	    $submit['type'] = 'submit';
    	    $submit['onclick'] = '';
    	    $submit2['onclick'] = '';
    	}

        $aTemplateData = array(
            'oDocument' => $oDocument,
            'oWorkflow' => $oWorkflow,
            'oState' => $oWorkflowState,
            'aTransitions' => $aTransitions,
            'aWorkflows' => $aWorkflows,
            'transition_fields' => $transition_fields,
            'bHasPerm' => $bHasPerm,
            'bIsCheckedOut' => $bIsCheckedOut,
            'submit' => $submit,
            'submit2' => $submit2
        );

        echo $oTemplate->render($aTemplateData);
    	exit(0);
    }

	public function do_startWorkflow() {
        $oDocument =& $this->oValidator->validateDocument($_REQUEST['fDocumentId']);
        if (!empty($_REQUEST['fWorkflowId'])) {
            $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        } else {
            $oWorkflow = null;
        }

        $res = KTWorkflowUtil::startWorkflowOnDocument($oWorkflow, $oDocument);
        if (PEAR::isError($res)) {
            $this->errorRedirectToMain($res->message, sprintf('fDocumentId=%s',$oDocument->getId()));
        }

		echo "Workflow Started";
        exit(0);
    }

    public function do_performTransition() {
        $oDocument = $this->oValidator->validateDocument($_REQUEST['fDocumentId']);
        $oTransition = $this->oValidator->validateWorkflowTransition($_REQUEST['fTransitionId']);

        $aErrorOptions = array(
            'message' => _kt('You must provide a reason for the transition'),
        );

        $sComments = $this->oValidator->validateString($_REQUEST['fComments'], $aErrorOptions);

        $oUser = User::get($_SESSION['userID']);
        $res = KTWorkflowUtil::performTransitionOnDocument($oTransition, $oDocument, $oUser, $sComments);

        if (!$this->userHasDocumentReadPermission($oDocument)) {
            $this->commitTransaction();
            echo _kt('Transition performed') . '. ' . _kt('You no longer have permission to view this document');
        } else {
            echo _kt('Transition performed');
        }

        exit(0);
    }

    public function do_quicktransition() {
        // make sure this gets through.
        $this->persistParams(array('fTransitionId'));

        $transition_id = $_REQUEST['fTransitionId'];
        $oTransition = KTWorkflowTransition::get($transition_id);
        //$oForm = $this->form_quicktransition();

        $oTemplating = KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate('ktcore/workflow/blocks/quicktransition');
        $aTemplateData = array(
        						'oTransition' => $oTransition,
        						'oDocument' => $this->oDocument,
        					);
        echo $oTemplate->render($aTemplateData);
        exit(0);
    }

    public function do_performquicktransition() {
        $this->startTransaction();

        $oTransition = KTWorkflowTransition::get($_REQUEST['fTransitionId']);
        $res = KTWorkflowUtil::performTransitionOnDocument($oTransition, $this->oDocument, $this->oUser, sanitizeForHTML($_REQUEST['fComments']));

        if (!$this->userHasDocumentReadPermission($this->oDocument)) {
            $this->commitTransaction();
            echo _kt('Transition performed') . '. ' . _kt('You no longer have permission to view this document');
        } else {
            $this->commitTransaction();
            echo _kt('Transition performed');
        }

        exit(0);
    }
}

class KTDocumentWorkflowAction extends KTDocumentAction {

    public $sName = 'ktcore.actions.document.workflow';
    public $_sShowPermission = 'ktcore.permissions.read';
    public $sHelpPage = 'ktcore/user/workflow.html';
	public $bShowIfReadShared = true;
	public $bShowIfWriteShared = true;
    public $sIconClass = 'manage-workflow';
    public $sParentBtn = 'more';

    function predispatch() {
        $this->persistParams(array('fTransitionId'));
    }

    function getDisplayName() {
        $oUser = User::get($_SESSION['userID']);
        if (!KTPermissionUtil::userHasPermissionOnItem($oUser, 'ktcore.permissions.workflow', $this->oDocument)) {
            return '';
        }

        return _kt('Workflow');
    }

    function getInfo() {
        if ($this->oDocument->getIsCheckedOut()) {
            return null;
        }

        return parent::getInfo();
    }

    function do_main() {
        $this->oPage->setBreadcrumbDetails(_kt('workflow'));
        $oTemplate =& $this->oValidator->validateTemplate('ktcore/workflow/documentWorkflow');
        $oDocument =& $this->oValidator->validateDocument($_REQUEST['fDocumentId']);

        $oWorkflow = KTWorkflowUtil::getWorkflowForDocument($oDocument);
        $oWorkflowState = KTWorkflowUtil::getWorkflowStateForDocument($oDocument);

        $oUser =& User::get($_SESSION['userID']);

        // If the document is checked out - set transitions and workflows to empty and set checkedout to true
        $bIsCheckedOut = $this->oDocument->getIsCheckedOut();
        if ($bIsCheckedOut) {
            $aTransitions = array();
            $aWorkflows = array();
            $transition_fields = array();
            $bHasPerm = FALSE;

        } else {
            $aTransitions = KTWorkflowUtil::getTransitionsForDocumentUser($oDocument, $oUser);

            $aWorkflows = KTWorkflow::getList('start_state_id IS NOT NULL AND enabled = 1 ');

            $bHasPerm = false;
            if (KTPermissionUtil::userHasPermissionOnItem($oUser, 'ktcore.permissions.workflow', $oDocument)) {
                $bHasPerm = true;
            }

            $fieldErrors = null;

            $transition_fields = array();
            if ($aTransitions) {
                $aVocab = array();
                foreach ($aTransitions as $oTransition) {
                	if (is_null($oTransition) || PEAR::isError($oTransition)) {
                		continue;
                	}

                    $aVocab[$oTransition->getId()] = $oTransition->showDescription();
                }
                $fieldOptions = array('vocab' => $aVocab);
                $transition_fields[] = new KTLookupWidget(_kt('Transition'), _kt(''), 'fTransitionId', null, $this->oPage, false, null, $fieldErrors, $fieldOptions);
                $transition_fields[] = new KTTextWidget(
                    _kt('Comment'), _kt(''),
                    'fComments', '',
                    $this->oPage, false, null, null,
                    array('cols' => 80, 'rows' => 4));
            }
        }

        // Add an electronic signature
    	global $default;
    	if ($default->enableESignatures) {
    	    $sUrl = KTPluginUtil::getPluginPath('electronic.signatures.plugin', true);
    	    $heading = _kt('You are attempting to modify the document workflow');
    	    $submit['type'] = 'button';
    	    $submit['onclick'] = "javascript: showSignatureForm('{$sUrl}', '{$heading}', 'ktcore.transactions.modify_workflow', 'document', 'start_workflow_form', 'submit', {$this->oDocument->iId});";

    	    $heading2 = _kt('You are attempting to transition the document workflow');
    	    $submit2['onclick'] = "javascript: showSignatureForm('{$sUrl}', '{$heading2}', 'ktcore.transactions.transition_workflow', 'document', 'transition_wf_form', 'submit', {$this->oDocument->iId});";
    	} else {
    	    $submit['type'] = 'submit';
    	    $submit['onclick'] = '';
    	    $submit2['onclick'] = '';
    	}

        $aTemplateData = array(
            'oDocument' => $oDocument,
            'oWorkflow' => $oWorkflow,
            'oState' => $oWorkflowState,
            'aTransitions' => $aTransitions,
            'aWorkflows' => $aWorkflows,
            'transition_fields' => $transition_fields,
            'bHasPerm' => $bHasPerm,
            'bIsCheckedOut' => $bIsCheckedOut,
            'submit' => $submit,
            'submit2' => $submit2
        );

        return $oTemplate->render($aTemplateData);
    }

	function do_startWorkflow() {
    	$method = KTUtil::arrayGet($_REQUEST, 'method');
        $oDocument =& $this->oValidator->validateDocument($_REQUEST['fDocumentId']);
        if (!empty($_REQUEST['fWorkflowId'])) {
            $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        } else {
            $oWorkflow = null;
        }

        $res = KTWorkflowUtil::startWorkflowOnDocument($oWorkflow, $oDocument);
        if (PEAR::isError($res)) {
            $this->errorRedirectToMain($res->message, sprintf('fDocumentId=%s',$oDocument->getId()));
        }

        $this->successRedirectToMain(_kt('Workflow started'), array('fDocumentId' => $oDocument->getId()));
        exit(0);
    }

    function do_performTransition() {
        $oDocument =& $this->oValidator->validateDocument($_REQUEST['fDocumentId']);
        $oTransition =& $this->oValidator->validateWorkflowTransition($_REQUEST['fTransitionId']);

        $aErrorOptions = array(
            'redirect_to' => array('main', sprintf('fDocumentId=%d', $_REQUEST['fDocumentId'])),
            'message' => _kt('You must provide a reason for the transition'),
        );

        $sComments =& $this->oValidator->validateString($_REQUEST['fComments'], $aErrorOptions);

        $oUser =& User::get($_SESSION['userID']);
        $res = KTWorkflowUtil::performTransitionOnDocument($oTransition, $oDocument, $oUser, $sComments);

        //if (!Permission::userHasDocumentReadPermission($oDocument)) {
        if (!$this->userHasDocumentReadPermission($oDocument)) {
            $this->commitTransaction();
            $_SESSION['KTInfoMessage'][] = _kt('Transition performed') . '. ' . _kt('You no longer have permission to view this document');
            controllerRedirect('browse', sprintf('fFolderId=%d', $oDocument->getFolderId()));
        } else {
            $this->successRedirectToMain(_kt('Transition performed'),
            array('fDocumentId' => $oDocument->getId()));
        }
    }

    function form_quicktransition() {
        $oForm = new KTForm;
        if ($this->oDocument->getIsCheckedOut()) {
            $this->addErrorMessage(_kt('The workflow cannot be changed while the document is checked out.'));
        } else {
            $oForm->setOptions(array(
                'identifier' => 'ktcore.workflow.quicktransition',
                'submit_label' => _kt('Perform Transition'),
                'context' => $this,
                'action' => 'performquicktransition',
                'fail_action' => 'quicktransition',
                'cancel_url' => KTBrowseUtil::getUrlForDocument($this->oDocument),
            ));

            // Electronic Signature if enabled
            global $default;
            if ($default->enableESignatures) {
                $widgets[] = array('ktcore.widgets.info', array(
                        'label' => _kt('This action requires authentication'),
                        'description' => _kt('Please provide your user credentials as confirmation of this action.'),
                        'name' => 'info'
                    ));
                $widgets[] = array('ktcore.widgets.string', array(
                        'label' => ($default->useEmailLogin) ? _kt('Email Address') : _kt('Username'),
                        'name' => 'sign_username',
                        'required' => true
                    ));
                $widgets[] = array('ktcore.widgets.password', array(
                        'label' => _kt('Password'),
                        'name' => 'sign_password',
                        'required' => true
                    ));
            }

            $widgets[] = array('ktcore.widgets.reason', array(
                        'label' => _kt('Note'),
		  			'required' => ($default->enableESignatures) ? true : false,
                      'name' => 'reason',
                    ));

            $oForm->setWidgets($widgets);

            $oForm->setValidators(array(
                array('ktcore.validators.string', array(
                    'test' => 'reason',
                    'min_length' => 1,
                    'max_length' => 250,
                    'output' => 'reason',
                )),
            ));

            if ($default->enableESignatures) {
                $oForm->addValidator(array('electonic.signatures.validators.authenticate', array(
                    'object_id' => $this->oDocument->iId,
                    'type' => 'document',
                    'action' => 'ktcore.transactions.transition_workflow',
                    'test' => 'info',
                    'output' => 'info'
                )));
            }
        }

        return $oForm;
    }

    function do_quicktransition() {
        // make sure this gets through.
        $this->persistParams(array('fTransitionId'));

        $transition_id = $_REQUEST['fTransitionId'];
        $oTransition = KTWorkflowTransition::get($transition_id);
        $oForm = $this->form_quicktransition();

        return $oForm->renderPage(sprintf(_kt('Perform Transition: %s'), $oTransition->getName()));
    }

    function do_performquicktransition() {
        $oForm = $this->form_quicktransition();
        $res = $oForm->validate();

        if (!empty($res['errors'])) {
            return $oForm->handleError();
        }

        $this->startTransaction();

        $data = $res['results'];
        $oTransition = KTWorkflowTransition::get($_REQUEST['fTransitionId']);

        $res = KTWorkflowUtil::performTransitionOnDocument($oTransition, $this->oDocument, $this->oUser, sanitizeForHTML($data['reason']));

        //if (!Permission::userHasDocumentReadPermission($this->oDocument)) {
        if (!$this->userHasDocumentReadPermission($this->oDocument)) {
            $this->commitTransaction();
            $_SESSION['KTInfoMessage'][] = _kt('Transition performed') . '. ' . _kt('You no longer have permission to view this document');
            controllerRedirect('browse', sprintf('fFolderId=%d', $this->oDocument->getFolderId()));
        } else {
            $this->commitTransaction();
            $_SESSION['KTInfoMessage'][] = _kt('Transition performed');
            controllerRedirect('viewDocument', sprintf('fDocumentId=%d', $this->oDocument->getId()));
        }
    }

}

class KTOwnershipChangeAction extends KTDocumentAction {

    var $sName = 'ktcore.actions.document.ownershipchange';
    var $_sShowPermission = 'ktcore.permissions.security';

    var $sIconClass = 'ownership';
    var $sParentBtn = 'more';

    function getDisplayName() {
        return _kt('Change owner');
    }

    function form_owner() {
        $form = new KTForm;
        $form->setOptions(array(
            'action' => 'reown',
            'cancel_url' => KTBrowseUtil::getUrlForDocument($this->oDocument),
            'fail_action' => 'main',
            'identifier' => 'ktcore.actions.document.owner',
            'context' => $this,
        ));
        $form->setWidgets(array(
            array('ktcore.widgets.entityselection', array(
                'label' => _kt('New Owner'),
                'label_method' => 'getName',
                'vocab' => User::getList('id > 0 AND disabled = 0'),
                'value' => $this->oDocument->getOwnerID(),
                'name' => 'user_id'
            )),
        ));
        $form->setValidators(array(
            array('ktcore.validators.entity', array(
                'test' => 'user_id',
                'class' => 'User',
                'output' => 'user',
            )),
        ));

        return $form;
    }

    function do_main() {
        $this->oPage->setBreadcrumbDetails(_kt('change owner'));
        $oTemplate =& $this->oValidator->validateTemplate('ktcore/document/ownershipchangeaction');

        $change_form = $this->form_owner();

        $oTemplate->setData(array(
            'context' => $this,
            'form' => $change_form,
        ));

        return $oTemplate->render();
    }

    function do_reown() {
        $oForm = $this->form_owner();
        $res = $oForm->validate();
        $data = $res['results'];
        $errors = $res['errors'];

        if (!empty($errors)) {
            return $oForm->handleError();
        }

        $oUser = $data['user'];

        $this->startTransaction();

        $this->oDocument->setOwnerID($oUser->getId());
        $res = $this->oDocument->update();
        if (PEAR::isError($res)) {
            $this->errorRedirectToMain(sprintf(_kt('Failed to update document: %s'), $res->getMessage()), sprintf('fDocumentId=%d', $this->oDocument->getId()));
        }

        $res = KTPermissionUtil::updatePermissionLookup($this->oDocument);

        if (PEAR::isError($res)) {
            $this->errorRedirectToMain(sprintf(_kt('Failed to update document: %s'), $res->getMessage()), sprintf('fDocumentId=%d', $this->oDocument->getId()));
        }

        // create the document transaction record
        $documentTransaction = new DocumentTransaction($this->oDocument, 'Ownership changed', 'ktcore.transactions.ownership_change');
        $documentTransaction->create();

        $this->successRedirectToMain(_kt('Ownership changed.'), sprintf('fDocumentId=%d', $this->oDocument->getId()));
    }

}

class KTDocumentPageUrlAction extends KTDocumentAction {

    public $sName = 'ktcore.actions.document.pageurl';
	public $bShowIfWriteShared = true;
	public $bShowIfReadShared = true;
	public $btnOrder = 1;
	public $sBtnPosition = 'links';
	public $sIconClass = 'page-url';

    function getDisplayName() {
        return _kt('Get page link');
    }

    function getURL() {
        return '#';
    }

    function getOnClick(){
        $onclick = 'javascript: kt.app.docdetails.showPageUrl();';
        return $onclick;
    }

    function do_main() {
    }

}

class KTDocumentDownloadUrlAction extends KTDocumentAction {

    var $sName = 'ktcore.actions.document.downloadurl';
	var $bShowIfWriteShared = true;
	var $bShowIfReadShared = true;
	var $btnOrder = 2;
	var $sBtnPosition = 'links';
	var $sIconClass = 'download-url';

    function getDisplayName() {
        return _kt('Get download link');
    }

    function getURL() {
        return '#';
    }

    function getOnClick(){
        $onclick = "javascript: kt.app.docdetails.getDownloadUrl(\"{$this->sIconClass}\");";
        return $onclick;
    }

    function do_main() {
    }

}

class KTDocumentPreviewUrlAction extends KTDocumentAction {

    var $sName = 'ktcore.actions.document.previewurl';
	var $bShowIfWriteShared = true;
	var $bShowIfReadShared = true;
	var $btnOrder = 3;
	var $sBtnPosition = 'links';
	var $sIconClass = 'preview-url';

    function getDisplayName() {
        return _kt('Preview URL');
    }

    function getInfo() {
        return null;
    }

    function getURL() {
        return '#';
    }

    function getOnClick(){
        $onclick = '';
        return $onclick;
    }

    function do_main() {
    }

}

?>
