<?php
/**
 * $Id$
 *
 * KnowledgeTree Community Edition
 * Document Management Made Simple
 * Copyright (C) 2008, 2009, 2010 KnowledgeTree Inc.
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
 * Contributor(s): ______________________________________
 */

class ViewDocumentDispatcher extends KTStandardDispatcher {
    
    public $sName = 'ktcore.actions.document.displaydetails';
    public $sSection = 'view_details';
    public $sHelpPage = 'ktcore/browse.html';

    public $actions;

    public function ViewDocumentDispatcher() {
        $this->aBreadcrumbs = array(
            array('action' => 'browse', 'name' => _kt('Browse')),
        );

        parent::KTStandardDispatcher();
    }

    public function check() {
        if (!parent::check()) { return false; }

        $this->persistParams(array('fDocumentId'));

        return true;
    }

    // FIXME identify the current location somehow.
    public function addPortlets($currentaction = null) {
        $currentaction = $this->sName;

    	$actions = KTDocumentActionUtil::getDocumentActionsForDocument($this->oDocument, $this->oUser, 'documentinfo');
        $oPortlet = new KTActionPortlet(sprintf(_kt('Info')));
        $oPortlet->setActions($actions, $currentaction);
        $this->oPage->addPortlet($oPortlet);

        $this->actions = KTDocumentActionUtil::getDocumentActionsForDocument($this->oDocument, $this->oUser);
        $oPortlet = new KTActionPortlet(sprintf(_kt('Actions'), $this->oDocument->getName()));
        $oPortlet->setActions($this->actions, $currentaction);
        $this->oPage->addPortlet($oPortlet);
    }

    public function do_main() {
        // fix legacy, broken items.
        if (KTUtil::arrayGet($_REQUEST, 'fDocumentID', true) !== true) {
            $_REQUEST['fDocumentId'] = sanitizeForSQL(KTUtil::arrayGet($_REQUEST, 'fDocumentID'));
            unset($_REQUEST['fDocumentID']);
        }

        $document_data = array();
        $document_id = sanitizeForSQL(KTUtil::arrayGet($_REQUEST, 'fDocumentId'));
        if ($document_id === null) {
            $this->oPage->addError(sprintf(_kt("No document was requested.  Please <a href=\"%s\">browse</a> for one."), KTBrowseUtil::getBrowseBaseUrl()));
            return $this->do_error();
        }
        // try get the document.
        $oDocument =& Document::get($document_id);
        if (PEAR::isError($oDocument)) {
            $this->oPage->addError(sprintf(_kt("The document you attempted to retrieve is invalid.   Please <a href=\"%s\">browse</a> for one."), KTBrowseUtil::getBrowseBaseUrl()));

            $this->oPage->booleanLink = true;

            return $this->do_error();
        }

        $document_id = $oDocument->getId();
        $document_data['document_id'] = $oDocument->getId();

        if (!KTBrowseUtil::inAdminMode($this->oUser, $oDocument->getFolderId())) {
            if ($oDocument->getStatusID() == ARCHIVED) {
                $this->oPage->addError(_kt('This document has been archived.'));
                return $this->do_request($oDocument);
            } else if ($oDocument->getStatusID() == DELETED) {
                $this->oPage->addError(_kt('This document has been deleted.'));
                return $this->do_error();
            } else if (!Permission::userHasDocumentReadPermission($oDocument)) {
                $this->oPage->addError(_kt('You are not allowed to view this document'));
                return $this->permissionDenied();
            }
        }

        if ($oDocument->getStatusID() == ARCHIVED) {
            $this->oPage->addError(_kt('This document has been archived.'));
        } else if ($oDocument->getStatusID() == DELETED) {
            $this->oPage->addError(_kt('This document has been deleted.'));
        }

        $this->oPage->setSecondaryTitle($oDocument->getName());

        $aOptions = array(
            'documentaction' => 'viewDocument',
            'folderaction' => 'browse',
        );

        $this->oDocument =& $oDocument;

        //Figure out if we came here by navigating through a shortcut.
        //If we came here from a shortcut, the breadcrumbspath should be relative
        //to the shortcut folder.
    	$iSymLinkFolderId = KTUtil::arrayGet($_REQUEST, 'fShortcutFolder', null);
        if (is_numeric($iSymLinkFolderId)) {
            $oBreadcrumbsFolder = Folder::get($iSymLinkFolderId);
            $aOptions['final'] = false;
            $this->aBreadcrumbs = kt_array_merge($this->aBreadcrumbs, KTBrowseUtil::breadcrumbsForFolder($oBreadcrumbsFolder,$aOptions));
            $this->aBreadcrumbs[] = array('name'=>$this->oDocument->getName());
        } else {
            $this->aBreadcrumbs = kt_array_merge($this->aBreadcrumbs, KTBrowseUtil::breadcrumbsForDocument($oDocument, $aOptions, $iSymLinkFolderId));
        }

        $this->addPortlets('Document Details');

        $document_data['document'] = $oDocument;
        $document_data['document_type'] =& DocumentType::get($oDocument->getDocumentTypeID());
        $is_valid_doctype = true;
        
        $document_types = & DocumentType::getList("disabled=0");

        if (PEAR::isError($document_data['document_type'])) {
            $this->oPage->addError(_kt('The document you requested has an invalid <strong>document type</strong>.  Unfortunately, this means that we cannot effectively display it.'));
            $is_valid_doctype = false;
        }

        // we want to grab all the md for this doc, since its faster that way.
        $mdlist =& DocumentFieldLink::getByDocument($oDocument);

        $GLOBALS['default']->log->debug('mdlist '.print_r($mdlist, true));
        
        $field_values = array();
        foreach ($mdlist as $oFieldLink) {
            $field_values[$oFieldLink->getDocumentFieldID()] = $oFieldLink->getValue();
        }

        //var_dump($field_values);

        $document_data['field_values'] = $field_values;

        // Fieldset generation.
        //
        //   we need to create a set of FieldsetDisplay objects
        //   that adapt the Fieldsets associated with this lot
        //   to the view (i.e. ZX3).   Unfortunately, we don't have
        //   any of the plumbing to do it, so we handle this here.
        $generic_fieldsets = array();
        $fieldsets = array();
        
        // we always have a generic.
        array_push($generic_fieldsets, new GenericFieldsetDisplay());

        $fieldsetDisplayReg =& KTFieldsetDisplayRegistry::getSingleton();
        $aDocFieldsets = KTMetadataUtil::fieldsetsForDocument($oDocument);
        
        //$GLOBALS['default']->log->debug('viewdocument aDocFieldsets '.print_r($aDocFieldsets, true));
        
        foreach ($aDocFieldsets as $oFieldset) {
        	//$GLOBALS['default']->log->debug('viewdocument oFieldset namespace :'.$oFieldset->getNamespace().':');
        	//$GLOBALS['default']->log->debug('viewdocument oFieldset namespace !=== tagcloud '.$oFieldset->getNamespace() !== 'tagcloud');
        	//Tag Cloud displayed elsewhere
        	if ($oFieldset->getNamespace() !== 'tagcloud')
			{
	        	//$GLOBALS['default']->log->debug('viewdocument oFieldset '.print_r($oFieldset, true));
	            $displayClass = $fieldsetDisplayReg->getHandler($oFieldset->getNamespace());
	            
	            //$GLOBALS['default']->log->debug('fieldsetdisplayclass '.print_r(new $displayClass($oFieldset), true));
	            array_push($fieldsets, new $displayClass($oFieldset));
			}
        }
        
        //$GLOBALS['default']->log->debug('viewdocument fieldsets '.print_r($fieldsets, true));

        $checkout_user = 'Unknown user';
        if ($oDocument->getIsCheckedOut() == 1) {
            $oCOU = User::get($oDocument->getCheckedOutUserId());
            if (!(PEAR::isError($oCOU) || ($oCOU == false))) {
                $checkout_user = $oCOU->getName();
            }
        }

        // is the checkout action active?
        $bCanCheckin = false;
        foreach ($this->actions as $oDocAction) {
            if ($oDocAction->sName == 'ktcore.actions.document.cancelcheckout') {
                if ($oDocAction->getInfo()) {
                    $bCanCheckin = true;
                }
                break;
            }
        }
        
		$bCanEdit = true;
		
        // viewlets
        $aViewlets = array();
        $aViewlets2 = array();
        $aViewletActions = KTDocumentActionUtil::getDocumentActionsForDocument($this->oDocument, $this->oUser, 'documentviewlet');
        foreach ($aViewletActions as $oAction) {
            $aInfo = $oAction->getInfo();
            if ($aInfo !== null) {
                if (($aInfo['ns'] == 'ktcore.viewlet.document.activityfeed') || ($aInfo['ns'] == 'thumbnail.viewlets')) {
                    $aViewlets[] = $oAction->display_viewlet(); // use the action, since we display_viewlet() later.
                } else {
                    $aViewlets2[] = $oAction->display_viewlet(); // use the action, since we display_viewlet() later.
                }
            }
        }

        $viewlet_data = implode(' ', $aViewlets);
        $viewlet_data = trim($viewlet_data);
        $viewlet_data2 = implode(' ', $aViewlets2);
        $viewlet_data2 = trim($viewlet_data2);

        $content_class = 'view';
        if (!empty($viewlet_data)) {
            $content_class = 'view withviewlets';
        }
        $this->oPage->setContentClass($content_class);

        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate('ktcore/document/view');

		if (KTPluginUtil::pluginIsActive('instaview.processor.plugin')) {
			$path = KTPluginUtil::getPluginPath ('instaview.processor.plugin');
			try {
				require_once($path . 'instaViewLinkAction.php');
				$oLivePreview = new instaViewLinkAction($oDocument, $this->oUser, null);
		        $live_preview = $oLivePreview->do_main();
			} catch(Exception $e) {}
		}

        $ownerUser = KTUserUtil::getUserField($oDocument->getOwnerID(), 'name');
        $creatorUser = KTUserUtil::getUserField($oDocument->getCreatorID(), 'name');
        $lastModifierUser = KTUserUtil::getUserField($oDocument->getModifiedUserId(), 'name');
        
        $FieldsetDisplayHelper = new KTFieldsetDisplay();

        // create the document transaction record
        $oDocumentTransaction = new DocumentTransaction($oDocument, 'Document details page view', 'ktcore.transactions.view');
        $oDocumentTransaction->create();

        $documentBlocks = KTDocumentActionUtil::getDocumentActionsForDocument($this->oDocument, $this->oUser, 'documentblock');
        
        $aTemplateData = array(
        	'doc_data' => array(
        		'owner' => $ownerUser[0]['name'],
        		'creator' => $creatorUser[0]['name'],
        		'lastModifier' => $lastModifierUser[0]['name']
        	),
			'context' => $this,
			'sCheckoutUser' => $checkout_user,
			'isCheckoutUser' => ($this->oUser->getId() == $oDocument->getCheckedOutUserId()),
			'canCheckin' => $bCanCheckin,
			'bCanEdit' => $bCanEdit,
			'document_id' => $document_id,
			'document' => $oDocument,
			'documentName' => $oDocument->getName(),
			'document_data' => $document_data,
        	'document_types' => $document_types,
			'generic_fieldsets' => $generic_fieldsets,
        	'fieldsets' => $fieldsets,
			'viewlet_data' => $viewlet_data,
			'viewlet_data2' => $viewlet_data2,
        	'hasNotifications' => false,
			'fieldsetDisplayHelper' => $FieldsetDisplayHelper,
			'documentBlocks' => $documentBlocks,
        );

        // Conditionally include live_preview
        if ($live_preview) { $aTemplateData['live_preview'] = $live_preview; }

        // Setting Document Notifications Status
        if ($oDocument->getIsCheckedOut() || $oDocument->getImmutable()) { $aTemplateData['hasNotifications'] = true; }

        $this->oPage->setBreadcrumbDetails(_kt("Document Details"));
        
        return $oTemplate->render($aTemplateData);
    }

    // FIXME refactor out the document-info creation into a single utility function.
    // this gets in:
    //   fDocumentId (document to compare against)
    //   fComparisonVersion (the metadata_version of the appropriate document)
    public function do_viewComparison() {
        $document_data = array();
        $document_id = KTUtil::arrayGet($_REQUEST, 'fDocumentId');
        if ($document_id === null) {
            $this->oPage->addError(sprintf(_kt("No document was requested.  Please <a href=\"%s\">browse</a> for one."), KTBrowseUtil::getBrowseBaseUrl()));
            return $this->do_error();
        }

        $document_data['document_id'] = $document_id;

        $base_version = KTUtil::arrayGet($_REQUEST, 'fBaseVersion');

        // try get the document.
        $oDocument =& Document::get($document_id, $base_version);
        if (PEAR::isError($oDocument)) {
            $this->oPage->addError(sprintf(_kt("The base document you attempted to retrieve is invalid.   Please <a href=\"%s\">browse</a> for one."), KTBrowseUtil::getBrowseBaseUrl()));
            return $this->do_error();
        }

        if (!Permission::userHasDocumentReadPermission($oDocument)) {
            // FIXME inconsistent.
            $this->oPage->addError(_kt('You are not allowed to view this document'));
            return $this->permissionDenied();
        }

        $this->oDocument =& $oDocument;
        $this->oPage->setSecondaryTitle($oDocument->getName());
        $aOptions = array(
                'documentaction' => 'viewDocument',
                'folderaction' => 'browse',
            );

        $this->aBreadcrumbs = kt_array_merge($this->aBreadcrumbs, KTBrowseUtil::breadcrumbsForDocument($oDocument, $aOptions));
        $this->oPage->setBreadcrumbDetails(_kt('compare versions'));

        $comparison_version = KTUtil::arrayGet($_REQUEST, 'fComparisonVersion');
        if ($comparison_version=== null) {
            $this->oPage->addError(sprintf(_kt("No comparison version was requested.  Please <a href=\"%s\">select a version</a>."), KTUtil::addQueryStringSelf('action=history&fDocumentId=' . $document_id)));
            return $this->do_error();
        }

        $oComparison =& Document::get($oDocument->getId(), $comparison_version);
        if (PEAR::isError($oComparison)) {
            $this->errorRedirectToMain(_kt('Invalid document to compare against.'));
        }

        $comparison_data = array();
        $comparison_data['document_id'] = $oComparison->getId();
        $document_data['document'] = $oDocument;
        $comparison_data['document'] = $oComparison;
        $document_data['document_type'] =& DocumentType::get($oDocument->getDocumentTypeID());
        $comparison_data['document_type'] =& DocumentType::get($oComparison->getDocumentTypeID());

        // follow twice:  once for normal, once for comparison.
        $is_valid_doctype = true;

        if (PEAR::isError($document_data['document_type'])) {
            $this->oPage->addError(_kt('The document you requested has an invalid <strong>document type</strong>.  Unfortunately, this means that we cannot effectively display it.'));
            $is_valid_doctype = false;
        }

        // we want to grab all the md for this doc, since its faster that way.
        $mdlist =& DocumentFieldLink::getList(array('metadata_version_id = ?', array($base_version)));

        $field_values = array();
        foreach ($mdlist as $oFieldLink) {
                $field_values[$oFieldLink->getDocumentFieldID()] = $oFieldLink->getValue();
        }

        $document_data['field_values'] = $field_values;
        $mdlist =& DocumentFieldLink::getList(array('metadata_version_id = ?', array($comparison_version)));

        $field_values = array();
        foreach ($mdlist as $oFieldLink) {
            $field_values[$oFieldLink->getDocumentFieldID()] = $oFieldLink->getValue();
        }

        $comparison_data['field_values'] = $field_values;

        // Fieldset generation.
        //
        //   we need to create a set of FieldsetDisplay objects
        //   that adapt the Fieldsets associated with this lot
        //   to the view (i.e. ZX3).   Unfortunately, we don't have
        //   any of the plumbing to do it, so we handle this here.
        $fieldsets = array();

        // we always have a generic.
        array_push($fieldsets, new GenericFieldsetDisplay());

        // FIXME can we key this on fieldset namespace?  or can we have duplicates?
        // now we get the other fieldsets, IF there is a valid doctype.

        if ($is_valid_doctype) {
            // these are the _actual_ fieldsets.
            $fieldsetDisplayReg =& KTFieldsetDisplayRegistry::getSingleton();

            // and the generics
            $activesets = KTFieldset::getGenericFieldsets();
            foreach ($activesets as $oFieldset) {
	            $displayClass = $fieldsetDisplayReg->getHandler($oFieldset->getNamespace());
	            array_push($fieldsets, new $displayClass($oFieldset));
            }

            $activesets = KTFieldset::getForDocumentType($oDocument->getDocumentTypeID());
            foreach ($activesets as $oFieldset) {
	            $displayClass = $fieldsetDisplayReg->getHandler($oFieldset->getNamespace());
	            array_push($fieldsets, new $displayClass($oFieldset));
            }
        }

        // FIXME handle ad-hoc fieldsets.
        $this->addPortlets();
        $oTemplate = $this->oValidator->validateTemplate('ktcore/document/compare');
        $aTemplateData = array(
                       'context' => $this,
                       'document_id' => $document_id,
                       'document' => $oDocument,
                       'document_data' => $document_data,
                       'comparison_data' => $comparison_data,
                       'comparison_document' => $oComparison,
                       'fieldsets' => $fieldsets,
                       );
        //var_dump($aTemplateData['comparison_data']);
        return $oTemplate->render($aTemplateData);
    }

    public function do_error() {
        return '&nbsp;'; // don't actually do anything.
    }

    public function do_request($oDocument) {
        // Display form for sending a request through the the sys admin to unarchive the document
        // name, document, request, submit

        $oForm = new KTForm;
        $oForm->setOptions(array(
            'submit_label' => _kt('Send request'),
            'identifier' => '',
            'cancel_url' => KTBrowseUtil::getUrlForFolder($oFolder),
            'fail_action' => 'main',
            'context' => $this,
        ));

        $oForm->addWidget(
                array('ktcore.widgets.text', array(
                    'label' => _kt('Note'),
                    'name' => 'reason',
                    'required' => true,
                ))
            );

        $data = isset($_REQUEST['data']) ? $_REQUEST['data'] : array();

        $iFolderId = $oDocument->getFolderID();
        $oFolder = Folder::get($iFolderId);
        $sFolderUrl = KTBrowseUtil::getUrlForFolder($oFolder);

        if (!empty($data)) {
            $res = $oForm->validate();
            if (!empty($res['errors'])) {
                return $oForm->handleError('', $aError);
            }

            $aAdminGroups = Group::getAdministratorGroups();
            if (!PEAR::isError($aAdminGroups) && !empty($aAdminGroups)) {
                foreach ($aAdminGroups as $oGroup) {
                    $aGroupUsers = $oGroup->getMembers();

                    // ensure unique users
                    foreach ($aGroupUsers as $oUser) {
                        $aUsers[$oUser->getId()] = $oUser;
                    }
                }

                $sSubject = _kt('Request for an archived document to be restored');
                $sDetails = $data['reason'];

                // Send request
                foreach ($aUsers as $oU) {
                    if (!PEAR::isError($oU)) {
                        include_once(KT_DIR.'/plugins/ktcore/KTAssist.php');
                        KTAssistNotification::newNotificationForDocument($oDocument, $oU, $this->oUser, $sSubject, $sDetails);
                    }
                }

                // Redirect to folder
                $this->addInfoMessage(_kt('The administrator has been notified of your request.'));
                redirect($sFolderUrl);
                exit();
            }
        }

        return $oForm->renderPage(_kt('Archived document request') . ': '.$oDocument->getName());
    }

    public function getUserForId($iUserId) {
        $u = User::get($iUserId);
        if (PEAR::isError($u) || ($u == false)) { return _kt('User no longer exists'); }
        return $u->getName();
    }
    
}
?>