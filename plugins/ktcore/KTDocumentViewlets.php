<?php

/**
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

require_once(KT_LIB_DIR . "/actions/documentviewlet.inc.php");
require_once(KT_LIB_DIR . "/workflow/workflowutil.inc.php");
require_once(KT_LIB_DIR . "/database/datetime.inc");
require_once(KT_DIR . '/plugins/comments/comments.php');

// {{{ KTDocumentDetailsAction
class KTWorkflowViewlet extends KTDocumentViewlet {
    public $sName = 'ktcore.viewlets.document.workflow';
	public $_sShowPermission = 'ktcore.permissions.write';
    public $showIfRead = false;
    public $showIfWrite = true;

    function display_viewlet() {
        $oKTTemplating =& KTTemplating::getSingleton();
        $oTemplate =& $oKTTemplating->loadTemplate("ktcore/document/viewlets/workflow");
        if (is_null($oTemplate)) { return ""; }

        $oWorkflowState = KTWorkflowState::get($this->oDocument->getWorkflowStateId());
        if (PEAR::isError($oWorkflowState)) {
            return "";
        }

        $aDisplayTransitions = array();
        $aTransitions = KTWorkflowUtil::getTransitionsForDocumentUser($this->oDocument, $this->oUser);
        if (empty($aTransitions)) {
            return "";
        }

        // Check if the document has been checked out
        $bIsCheckedOut = $this->oDocument->getIsCheckedOut();
        $iId = $this->oDocument->getId();
        if($bIsCheckedOut){
            // If document is checked out, don't link into the workflow.
            $aDisplayTransitions = array();
        }else{
            foreach ($aTransitions as $oTransition) {
            	if(is_null($oTransition) || PEAR::isError($oTransition)){
                	continue;
                }

                $aDisplayTransitions[] = array(
                    'url' => KTUtil::ktLink('action.php', 'ktcore.actions.document.workflow', array("fDocumentId" => $iId, "action" => "quicktransition", "fTransitionId" => $oTransition->getId())),
                    'name' => $oTransition->getName(),
                );
            }
        }

		//Retreive the comment for the previous transition
		$aCommentQuery = array(
            "SELECT comment FROM document_transactions
            where transaction_namespace='ktcore.transactions.workflow_state_transition'
            AND document_id = ?
            ORDER BY id DESC LIMIT 1;"
		);
		$aCommentQuery[] = array($iId);
		$aTransitionComments = DBUtil::getResultArray($aCommentQuery);
		$oLatestTransitionComment = null;
		if(!empty($aTransitionComments))
		{
			$aRow = $aTransitionComments[0];
			$oLatestTransitionComment = $aRow['comment'];
			$iCommentPosition = strpos($oLatestTransitionComment,':'); //comment found after first colon in string

			if($iCommentPosition>0) //if comment found
			{
				$oLatestTransitionComment = substr($oLatestTransitionComment, $iCommentPosition+2, (strlen($oLatestTransitionComment)-$iCommentPosition));
			}
			else //if no comment found - i.e. first state in workflow
			{
				$oLatestTransitionComment = null;
			}
		}

        $oTemplate->setData(array(
            'context' => $this,
            'bIsCheckedOut' => $bIsCheckedOut,
            'transitions' => $aDisplayTransitions,
            'state_name' => $oWorkflowState->getName(),
			'comment' => $oLatestTransitionComment,
        ));
        return $oTemplate->render();
    }
}
// }}}


// {{{ KTDocumentActivityFeedAction
class KTDocumentActivityFeedAction extends KTDocumentViewlet {
    public $sName = 'ktcore.viewlet.document.activityfeed';
    public $showIfRead = true;
    public $showIfWrite = true;

    function display_viewlet()
    {
        $iDocumentId = $this->oDocument->getId();
        $mainArray = array();

        /* *** Get the document transactions *** */
        $sQuery = 'SELECT DTT.name AS transaction_name, DT.transaction_namespace, U.name AS user_name, U.email as email,
            DT.version AS version, DT.comment AS comment, DT.datetime AS datetime
            FROM ' . KTUtil::getTableName('document_transactions') . ' AS DT
            INNER JOIN ' . KTUtil::getTableName('users') . ' AS U ON DT.user_id = U.id
            LEFT JOIN ' . KTUtil::getTableName('transaction_types') . ' AS DTT ON DTT.namespace = DT.transaction_namespace
            WHERE DT.document_id = ?
            ORDER BY DT.id DESC';
            //  ORDER BY DT.datetime DESC => replaced the order by so that they come out in the order they were added, reversed.
        $aParams = array($iDocumentId);

        $aTransactions = array();
        $res = DBUtil::getResultArray(array($sQuery, $aParams));
        if (PEAR::isError($res)) {
            global $default;
            $default->log->error('Error getting the transactions - ' . $res->getMessage());
            $res = array();
        }
        $aTransactions = $res;

        // Set the namespaces where not in the transactions lookup
        foreach($aTransactions as $key => $transaction){
            if(empty($transaction['transaction_name'])){
                $aTransactions[$key]['transaction_name'] = $this->_getActionNameForNamespace($transaction['transaction_namespace']);
            }


            $mainArray[] = array(
                'name' => $transaction['user_name'],
                'email' => md5(strtolower($transaction['email'])),
                'transaction_name' => $transaction['transaction_name'],
                'datetime' => getDateTimeDifference($transaction['datetime']),
                'actual_datetime' => $transaction['datetime'],
                'version' => $transaction['version'],
                'comment' => $transaction['comment'],
                'type' => 'transaction'
            );
        }

        /* *** Get the document versions *** */
    	$aMetadataVersions = KTDocumentMetadataVersion::getByDocumentContent($iDocumentId);
    	if (PEAR::isError($aMetadataVersions)) {
            global $default;
            $default->log->error('Error getting the versions - ' . $aMetadataVersions->getMessage());
            $aMetadataVersions = array();
        }

        $aVersions = array();
        $prevContentVersion = 0;
        foreach ($aMetadataVersions as $version) {
             // For each content version there can be multiple metadata versions
             // Allow the earliest metadata version to override the later ones
             $contentVersion = $version['content_version_id'];
             if($contentVersion == $prevContentVersion) {
                 continue;
             }
             $prevContentVersion = $contentVersion;

             $aVersions[] = array(
                'name' => $version['name'],
                'transaction_name' => _kt('New Document Version'),
                'datetime' => datetimeutil::getLocaleDate($version['datetime']),
                'actual_datetime' => $version['datetime'],
                'version' => $version['major_version'] . '.' . $version['minor_version'],
                'comment' => '',
                'type' => 'version'
            );
        }

        $aVersions = array_reverse($aVersions);
        $mainArray = array_merge($mainArray, $aVersions);

        /* *** Get the document comments *** */
        try {
            $comments = Comments::get_comments($iDocumentId);
            foreach ($comments as $comment)
            {
                $mainArray[] = array(
                    'name' => $comment['user_name'],
                    'email' => md5(strtolower($comment['email'])),
                    'transaction_name' => _kt('Comment'),
                    'datetime' => getDateTimeDifference($comment['date']),
                    'actual_datetime' => $comment['date'],
                    'version' => '',
                    'comment' => $comment['comment'],
                    'type' => 'comment'
                );
            }
        } catch (Exception $e) {
            global $default;
            $default->log->error('Error getting the comments - ' . $e->getMessage());
            $comments = array();
        }

		// Sort by Date
        usort($mainArray, array($this, 'sortTable'));

		// Reverse so that top most is on top
		//$mainArray = array_reverse($mainArray);

		$oKTTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oKTTemplating->loadTemplate("ktcore/document/viewlets/activity_feed");

        $aTemplateData = array(
              'context' => $this,
              'document_id' => $iDocumentId,
              'document' => $this->oDocument,
              'versions' => $mainArray,
        );
        return $oTemplate->render($aTemplateData);
    }

    function sortTable($a, $b)
    {
        $d1 = new DateTime($a['actual_datetime']);
        $d2 = new DateTime($b['actual_datetime']);

        return $d1 < $d2 ? 1: -1;
    }

    function _getActionNameForNamespace($sNamespace) {
        $aNames = split('\.', $sNamespace);
        $sName = array_pop($aNames);
        $sName = str_replace('_', ' ', $sName);
        $sName = ucwords($sName);
        return $sName;
    }

    function getUserForId($iUserId) {
        $u = User::get($iUserId);
        if (PEAR::isError($u) || ($u == false)) { return _kt('User no longer exists'); }
        return $u->getName();
    }

	function getEmailForId($iUserId) {
        $u = User::get($iUserId);

        if (PEAR::isError($u) || ($u == false)) { return _kt('User no longer exists'); }
        return $u->getEmail();
    }

}
// }}}

// {{{ KTDocumentDetailsAction
class KTInlineEditViewlet extends KTDocumentViewlet {
    public $sName = 'ktcore.viewlets.document.inline.edit';
	public $_sShowPermission = 'ktcore.permissions.write';
    public $showIfRead = true;
    public $showIfWrite = true;

    function display_viewlet() {
        $oKTTemplating =& KTTemplating::getSingleton();
        $oTemplate =& $oKTTemplating->loadTemplate("ktcore/document/viewlets/inline_edit");
        if (is_null($oTemplate)) { return ""; }
		// Get document fieldsets
		$fieldsets = array();
        $fieldsetDisplayReg = KTFieldsetDisplayRegistry::getSingleton();
        $aDocFieldsets = KTMetadataUtil::fieldsetsForDocument($this->oDocument);
        foreach ($aDocFieldsets as $oFieldset) {
            $displayClass = $fieldsetDisplayReg->getHandler($oFieldset->getNamespace());
            array_push($fieldsets, new $displayClass($oFieldset));
        }
        
        $oTemplate->setData(array(
            'context' => $this,
            'document' => $this->oDocument,
            'fieldsetDisplayHelper' => new KTFieldsetDisplay(),
        ));
        return $oTemplate->render();
    }
}
// }}}
?>