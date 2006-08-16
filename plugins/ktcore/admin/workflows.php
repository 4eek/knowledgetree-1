<?php

/**
 * $Id$
 *
 * The contents of this file are subject to the KnowledgeTree Public
 * License Version 1.1 ("License"); You may not use this file except in
 * compliance with the License. You may obtain a copy of the License at
 * http://www.ktdms.com/KPL
 * 
 * Software distributed under the License is distributed on an "AS IS"
 * basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 * 
 * The Original Code is: KnowledgeTree Open Source
 * 
 * The Initial Developer of the Original Code is The Jam Warehouse Software
 * (Pty) Ltd, trading as KnowledgeTree.
 * Portions created by The Jam Warehouse Software (Pty) Ltd are Copyright
 * (C) 2006 The Jam Warehouse Software (Pty) Ltd;
 * All Rights Reserved.
 *
 */

require_once(KT_LIB_DIR . '/dispatcher.inc.php');
require_once(KT_LIB_DIR . '/validation/dispatchervalidation.inc.php');
require_once(KT_LIB_DIR . '/templating/templating.inc.php');
require_once(KT_LIB_DIR . '/widgets/fieldWidgets.php');

require_once(KT_LIB_DIR . '/workflow/workflow.inc.php');
require_once(KT_LIB_DIR . '/workflow/workflowstate.inc.php');
require_once(KT_LIB_DIR . '/workflow/workflowtransition.inc.php');
require_once(KT_LIB_DIR . '/workflow/workflowstatepermissionsassignment.inc.php');

require_once(KT_LIB_DIR . "/templating/kt3template.inc.php");

require_once(KT_LIB_DIR . '/permissions/permission.inc.php');
require_once(KT_LIB_DIR . '/groups/Group.inc');
require_once(KT_LIB_DIR . '/roles/Role.inc');
require_once(KT_LIB_DIR . '/search/savedsearch.inc.php');

require_once(KT_LIB_DIR . '/actions/documentaction.inc.php');

require_once(KT_LIB_DIR . '/widgets/portlet.inc.php');

require_once(KT_LIB_DIR . '/users/User.inc');
require_once(KT_LIB_DIR . '/groups/Group.inc');
require_once(KT_LIB_DIR . '/roles/Role.inc');

class WorkflowNavigationPortlet extends KTPortlet {   
    var $oWorkflow;
    var $sHelpPage = 'ktcore/admin/workflow.html';
    var $bActive = true;
    
    function WorkflowNavigationPortlet($sTitle, $oWorkflow = null) {
        $this->oWorkflow = $oWorkflow;
        parent::KTPortlet($sTitle);
    }

    function render() {
        if (is_null($this->oWorkflow)) { return _kt('No Workflow Selected.'); }
    
        $aAdminPages = array();
        $aAdminPages[] = array('name' => _kt('Overview'), 'query' => 'action=editWorkflow&fWorkflowId=' . $this->oWorkflow->getId());
        $aAdminPages[] = array('name' => _kt('States'), 'query' => 'action=manageStates&fWorkflowId=' . $this->oWorkflow->getId());
        $aAdminPages[] = array('name' => _kt('Transitions'), 'query' => 'action=manageTransitions&fWorkflowId=' . $this->oWorkflow->getId());
        $aAdminPages[] = array('name' => _kt('Actions'), 'query' => 'action=manageActions&fWorkflowId=' . $this->oWorkflow->getId());
    
        $oTemplating =& KTTemplating::getSingleton();        
        $oTemplate = $oTemplating->loadTemplate("ktcore/workflow/admin_portlet");
        $aTemplateData = array(
            "context" => $this,
            "aAdminPages" => $aAdminPages,
        );

        return $oTemplate->render($aTemplateData);     
    }
}

class KTWorkflowDispatcher extends KTAdminDispatcher {
    var $bAutomaticTransaction = true;
    var $sHelpPage = 'ktcore/admin/workflow.html';
    var $aWorkflowInfo;
    var $oWorkflow;

    function check() {
        $this->aBreadcrumbs[] = array(
            'url' => $_SERVER['PHP_SELF'],
            'name' => _kt('Workflows'),
        );
        $this->oWorkflow =& KTWorkflow::get($_REQUEST['fWorkflowId']);
        if (!PEAR::isError($this->oWorkflow)) {
            $this->aBreadcrumbs[] = array(
               'url' => $_SERVER['PHP_SELF'],
               'query' => 'action=editWorkflow&fWorkflowId=' . $this->oWorkflow->getId(),
               'name' => $this->oWorkflow->getName(),
            );
            $this->oPage->addPortlet(new WorkflowNavigationPortlet(_kt('Workflow'), $this->oWorkflow));        
        }
    
        return true;
    }
    
    // helper function to construct the set of workflow information
    function buildWorkflowInfo($oWorkflow) {
        if ($this->aWorkflowInfo != null) { return $this->aWorkflowInfo; }
    
        $aInfo = array();
        $aInfo['workflow'] = $oWorkflow;
        
        // roles
        $aRoles = Role::getList();
        $aKeyRoles = array();
        foreach ($aRoles as $oRole) { $aKeyRoles[$oRole->getId()] = $oRole; }        
        $aInfo['roles'] = $aKeyRoles;
        
        // groups
        $aGroups = Group::getList();
        $aKeyGroups = array();
        foreach ($aGroups as $oGroup) { $aKeyGroups[$oGroup->getId()] = $oGroup; }
        $aInfo['groups'] = $aKeyGroups;
        
        // states.
        $aStates = KTWorkflowState::getByWorkflow($oWorkflow);
        $aKeyStates = array();
        foreach ($aStates as $oState) { $aKeyStates[$oState->getId()] = $oState; }
        $aInfo['states'] = $aKeyStates;
        
        // transitions
        $aTransitions = KTWorkflowTransition::getByWorkflow($oWorkflow);
        $aKeyTransitions = array();
        foreach ($aTransitions as $oTransition) { $aKeyTransitions[$oTransition->getId()] = $oTransition; }
        $aInfo['transitions'] = $aKeyTransitions;
        
        // permissions
        $aPermissions = KTPermission::getList();
        $aKeyPermissions = array();
        foreach ($aPermissions as $oPermission) { $aKeyPermissions[$oPermission->getId()] = $oPermission; }
        $aInfo['permissions'] = $aKeyPermissions;
        
        // actions
        $aInfo['actions'] = KTDocumentActionUtil::getAllDocumentActions();
        $aKeyActions = array();
        foreach ($aInfo['actions'] as $oAction) { $aKeyActions[$oAction->getName()] = $oAction; }
        $aInfo['actions_by_name'] = $aKeyActions;
        
        $aInfo['controlled_actions'] = KTWorkflowUtil::getControlledActionsForWorkflow($oWorkflow);
        
        /*
         * now we need to do the crossmappings.
         */
        
        $aActionsByState = array();
        foreach ($aInfo['states'] as $oState) {
            $aActionsByState[$oState->getId()] = KTWorkflowUtil::getEnabledActionsForState($oState);;
        }
        $aInfo['actions_by_state'] = $aActionsByState;
        
        // FIXME handle notified users and groups
        $aTransitionsFromState = array();
        foreach ($aInfo['states'] as $oState) {
            $aTransitionsFromState[$oState->getId()] = KTWorkflowUtil::getTransitionsFrom($oState);
        }
        $aInfo['transitions_from_state'] = $aTransitionsFromState;
        
        $aTransitionsToState = array();
        foreach ($aInfo['states'] as $oState) {
            $aTransitionsToState[$oState->getId()] = KTWorkflowTransition::getByTargetState($oState);
        }
        $aInfo['transitions_to_state'] = $aTransitionsToState;
        
        $aPerms = KTPermission::getList();
        $aKeyPerms = array();
        foreach ($aPerms as $oPerm) { $aKeyPerms[$oPerm->getName()] = $oPerm; }
        $aInfo['permissions'] = $aKeyPerms;
        
        // temporarily create a debug mapping.
        $aPermissionsByState = array();
        foreach ($aInfo['states'] as $oState) {
            $aPerms = KTWorkflowStatePermissionAssignment::getByState($oState->getId());
            $aPermsAssigned = array();
            foreach ($aPerms as $oPermAlloc) {
                $oPerm = KTPermission::get($oPermAlloc->getPermissionId());
                $aPermsAssigned[$oPermAlloc->getId()] = $oPerm->getName();
            }            
            $aPermissionsByState[$oState->getId()] = $aPermsAssigned;
        }
        $aInfo['permissions_by_state'] = $aPermissionsByState;       
        
        // finally, check if any documents are associated with this workflow,
        // and set the "delete" toggle.
        $sQuery = 'SELECT document_id FROM ' . KTUtil::getTableName('document_metadata_version');
        $sQuery .= ' WHERE workflow_id = ? ';
        $aParams = array($oWorkflow->getId());
        
        $aDocList = DBUtil::getResultArray(array($sQuery, $aParams));
        $aInfo['can_delete'] = (empty($aDocList));
        
        $this->aWorkflowInfo = $aInfo;
        
        return $aInfo;
    }
    
    function getPermissionAssignmentsForState($oState) {
        $aAllocs = array();
        foreach ($this->aWorkflowInfo['permissions_by_state'][$oState->getId()] as $iAllocId => $sPermName) {
            $oAlloc = KTWorkflowStatePermissionAssignment::get($iAllocId);
            $aAllocs[$sPermName] = $oAlloc->getAllowed();
        }
        $this->aWorkflowInfo['permission_allocations_for_state'] = array();
        $this->aWorkflowInfo['permission_allocations_for_state'][$oState->getId()] = $aAllocs;
        return $aAllocs;
    }
    
    function getRoleHasPermissionInState($oRole, $sPermName, $oState) {
        $perms = $this->aWorkflowInfo['permission_allocations_for_state'][$oState->getId()];
        if (is_null($perms)) { return false; }
        $aAllowed = $perms[$sPermName];
        if (is_null($aAllowed['role'])) { return false; }
        $aRoles = $aAllowed['role'];
        if (array_search($oRole->getId(), $aRoles) === false) { return false; }
        else { return true; }
    }
    
    function getGroupHasPermissionInState($oGroup, $sPermName, $oState) {
        $perms = $this->aWorkflowInfo['permission_allocations_for_state'][$oState->getId()];
        if (is_null($perms)) { return false; }
        $aAllowed = $perms[$sPermName];
        if (is_null($aAllowed['group'])) { return false; }
        $aGroups = $aAllowed['group'];
        if (array_search($oGroup->getId(), $aGroups) === false) { return false; }
        else { return true; }
    }    
    
    
    function getActionStringForState($oState) {
        $aInfo = $this->aWorkflowInfo;
        
        // no controlled actions => all available
        if (empty($aInfo['controlled_actions'])) { return _kt('All actions available.'); }
        
        
        $aAlways = array();
        /*
        foreach ($aInfo['actions'] as $iActionId => $aAction) {
            if (!array_key_exists($iActionId, $aInfo['controlled_actions'])) {
                $aAlways[$iActionId] = $aAction; 
            }
        }
        */
        
        $aNamedActions = array();
        foreach ($aInfo['actions_by_state'][$oState->getId()] as $sName) {
            $aNamedActions[] = $aInfo['actions_by_name'][$sName];
        }
        
        $aThese = kt_array_merge($aAlways, $aNamedActions);
        // some controlled.  we need to be careful here:  list actions that _are always_ available
        if (empty($aThese)) { return _kt('No actions available.'); }
    
        // else
        $aActions = array();
        foreach ($aThese as $oAction) { $aActions[] = $oAction->getDisplayName(); }
        return  implode(', ', $aActions);
    }
    
    function getTransitionToStringForState($oState) {
        $aInfo = $this->aWorkflowInfo;
        //var_dump($aInfo['transitions_to_state'][$oState->getId()]);
        if (($aInfo['workflow']->getStartStateId() != $oState->getId()) && (empty($aInfo['transitions_to_state'][$oState->getId()]))) {
            return '<strong>' . _kt('This state is unreachable.') . '</strong>';
        }
        
        
        if ($aInfo['workflow']->getStartStateId() == $oState->getId() && (empty($aInfo['transitions_to_state'][$oState->getId()]))) {
            return '<strong>' . _kt('Documents start in this state') . '</strong>';            
        }
        $aT = array();
        if ($aInfo['workflow']->getStartStateId() == $oState->getId()) {
            $aT[] = '<strong>' . _kt('Documents start in this state') . '</strong>';            
        }
        
        foreach ($aInfo['transitions_to_state'][$oState->getId()] as $aTransition) { 
            $sUrl = KTUtil::addQueryStringSelf(sprintf('action=editTransition&fWorkflowId=%d&fTransitionId=%d', $aInfo['workflow']->getId(), $aTransition->getId())); 
            $aT[] = sprintf('<a href="%s">%s</a>', $sUrl, $aTransition->getName()); 
        }
        
        return implode(', ',$aT);
    }
    
    function getNotificationStringForState($oState) {
        $aAllowed = KTWorkflowUtil::getInformedForState($oState);
        
        $aUsers = array();
        $aGroups = array();
        $aRoles = array();
        
        foreach (KTUtil::arrayGet($aAllowed,'user',array()) as $iUserId) {
            $oU = User::get($iUserId);
            if (PEAR::isError($oU) || ($oU == false)) {
                continue;
            } else {
                $aUsers[] = $oU->getName();
            }
        }
        
        foreach (KTUtil::arrayGet($aAllowed,'group',array()) as $iGroupId) {
            $oG = Group::get($iGroupId);
            if (PEAR::isError($oG) || ($oG == false)) {
                continue;
            } else {
                $aGroups[] = $oG->getName();
            }
        }
        
        foreach (KTUtil::arrayGet($aAllowed,'role',array()) as $iRoleId) {
            $oR = Role::get($iRoleId);
            if (PEAR::isError($oR) || ($oR == false)) {
                continue;
            } else {
                $aRoles[] = $oR->getName();
            }
        }
        
        $sNotify = '';
        if (!empty($aUsers)) {
            $sNotify .= '<em>' . _kt('Users:') . '</em> ';
            $sNotify .= implode(', ', $aUsers);
        }
        
        if (!empty($aGroups)) {
            if (!empty($sNotify)) { $sNotify .= ' &mdash; '; }
            $sNotify .= '<em>' . _kt('Groups:') . '</em> ';
            $sNotify .= implode(', ', $aGroups);
        }
        
        if (!empty($aRoles)) {
            if (!empty($sNotify)) { $sNotify .= ' &mdash; '; }
            $sNotify .= '<em>' . _kt('Roles:') . '</em> ';
            $sNotify .= implode(', ', $aRoles);
        }
        
        if (empty($sNotify)) { $sNotify = _kt('No users to be notified.'); }
        
        return $sNotify;
    }
  
    function transitionAvailable($oTransition, $oState) {
        $aInfo = $this->aWorkflowInfo;
        
        $val = false;
        foreach ($aInfo['transitions_from_state'][$oState->getId()] as $oT) {
            if ($oTransition->getId() == $oT->getId()) { $val = true; }
        }
        
        return $val;
    }
    
    function actionAvailable($sAction, $oState) {
        $aInfo = $this->aWorkflowInfo;
        
        $val = false;
        
        foreach ($aInfo['actions_by_state'][$oState->getId()] as $oA) {
            
            if ($sAction == $oA) { $val = true; }
        }
        
        return $val;
    }
    
    function getTransitionFromStringForState($oState) {
        $aInfo = $this->aWorkflowInfo;      
        
        if (empty($aInfo['transitions_from_state'][$oState->getId()])) {
            return '<strong>' . _kt('No transitions available') . '</strong>';
        }
        
        $aT = array();
        foreach ($aInfo['transitions_from_state'][$oState->getId()] as $aTransition) { 
            $sUrl = KTUtil::addQueryStringSelf(sprintf('action=editTransition&fWorkflowId=%d&fTransitionId=%d', $aInfo['workflow']->getId(), $aTransition->getId())); 
            $aT[] = sprintf('<a href="%s">%s</a>', $sUrl, $aTransition->getName()); 
        }
        return implode(', ', $aT);
    }
    
    function getPermissionStringForState($oState) {
        $aStr = '';
        $aInfo = $this->aWorkflowInfo;
        $aPerms = (array) $aInfo['permissions_by_state'][$oState->getId()];
        
        if (empty($aPerms)) {
            $aStr = _kt('No permissions are changed in this state.');
            return $aStr;
        }
        
        $aPermNames = array();
        foreach ($aPerms as $sPerm) {
            $aPermNames[] = $aInfo['permissions'][$sPerm]->getHumanName();
        }
        $aStr = implode(', ', $aPermNames);        
        
        return $aStr;
    }
    
    
    // {{{ WORKFLOW HANDLING
    // {{{ do_main
    function do_main () {
        
        $add_fields = array();
        $add_fields[] = new KTStringWidget(_kt('Name'), _kt('A human-readable name for the workflow.'), 'fName', null, $this->oPage, true);
        
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate =& $oTemplating->loadTemplate('ktcore/workflow/listWorkflows');
        $oTemplate->setData(array(
            'context' => $this,
            'aWorkflow' => KTWorkflow::getList(),
            'add_fields' => $add_fields,            
        ));
        return $oTemplate;
    }
    // }}}
    
    // {{{ do_editWorkflow
    function do_editWorkflow() {
        
        $oTemplate =& $this->oValidator->validateTemplate('ktcore/workflow/editWorkflow');
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        
        
        
        $aInfo = $this->buildWorkflowInfo($oWorkflow);
        
        $aPermissions = $aInfo['permissions'];
        $aStates = $aInfo['states'];
        
        $edit_fields = array();
        $edit_fields[] = new KTStringWidget(_kt('Name'), _kt('A human-readable name for the workflow.'), 'fName', $oWorkflow->getName(), $this->oPage, true);
        $aOptions = array();
        $vocab = array();
        $vocab[0] = _kt('None - documents cannot use this workflow.');
        foreach($aStates as $state) {
            $vocab[$state->getId()] = $state->getName();
        } 
        $aOptions['vocab'] = $vocab;        
        $edit_fields[] = new KTLookupWidget(_kt('Starting State'), _kt('When a document has this workflow applied to it, to which state should it initially be set.  <strong>Note that workflows without a starting state cannot be applied to documents.</strong>'), 'fStartStateId', $oWorkflow->getStartStateId(), $this->oPage, false, null, null, $aOptions);
        if (is_null($oWorkflow->getStartStateId())) {
            $this->oPage->addInfo(_kt('This workflow is currently disabled.  To enable it, please assign a starting state in the "Edit workflow properties" box.'));
        }
        
        /*
        $add_state_fields = array();
        $add_state_fields[] = new KTStringWidget(_kt('Name'), _kt('A human-readable name for the state.'), 'fName', null, $this->oPage, true);

        
        */
        
        
        $oTemplate->setData(array(
            'context' => $this,
            'oWorkflow' => $oWorkflow,
            
            'aStates' => $aStates,
            'aTransitions' => $aInfo['transitions'],
            'aPermissions' => $aPermissions,
            'aActions' => $aInfo['actions'],
            'aActionsSelected' => $aInfo['controlled_actions'],
            
            // info
            'workflow_info' => $aInfo,
            
            // subform
            'edit_fields' => $edit_fields,
        ));
        return $oTemplate;
    }
    // }}}

    // {{{ do_saveWorkflow
    function do_saveWorkflow() {
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        
        $aOptions = array(
            'redirect_to' => array('editWorkflow', 'fWorkflowId=' .  $oWorkflow->getId()),
        );
        
        $sName = $this->oValidator->validateString($_REQUEST['fName'], $aOptions);
        
        $oWorkflow->setName($sName);
        $oWorkflow->setHumanName($sName);
        
        if (!empty($_REQUEST['fStartStateId'])) {
            $oWorkflow->setStartStateId($_REQUEST['fStartStateId']);
        } else {
            $oWorkflow->setStartStateId(null);
        }
        
        $res = $oWorkflow->update();
        
        $this->oValidator->notErrorFalse($res, array(
            'redirect_to' => array('editWorkflow', 'fWorkflowId=' . $oWorkflow->getId()),
            'message' => _kt('Error saving workflow'),
        ));
        
        $this->successRedirectTo('editWorkflow', _kt('Changes saved'), 'fWorkflowId=' . $oWorkflow->getId());
        exit(0);
    }
    // }}}

    // {{{ do_newWorkflow
    function do_newWorkflow() {
        $aErrorOptions = array(
            'redirect_to' => array('main'),
        );
        
        $sName = KTUtil::arrayGet($_REQUEST, 'fName');
        $sName = $this->oValidator->validateEntityName('KTWorkflow', $sName, $aErrorOptions);
            

/*        if(!PEAR::isError(KTWorkflow::getByName($sName))) {
            $this->errorRedirectToMain(_kt("A state with that name already exists"));
        }*/
            
        $res = KTWorkflow::createFromArray(array(
            'name' => $sName,
            'humanname' => $sName,
        ));
        $this->oValidator->notError($res, array(
            'redirect_to' => array('main'),
            'message' => _kt('Could not create workflow'),
        ));
        $this->successRedirectTo('editWorkflow', _kt('Workflow created'), 'fWorkflowId=' . $res->getId());
        exit(0);
    }
    // }}}

    // {{{ do_disableWorkflow
    function do_disableWorkflow() {
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        
        $this->startTransaction();
        
        $oWorkflow->setStartStateId(null);       
        $res = $oWorkflow->update();
        
        $this->oValidator->notErrorFalse($res, array(
            'redirect_to' => array('main'),
            'message' => _kt('Error saving workflow'),
        ));

        $this->commitTransaction();
        
        $this->successRedirectToMain(_kt('Changes saved'));
        exit(0);
    }
    // }}}

    function do_manageActions() {
        $oTemplate =& $this->oValidator->validateTemplate('ktcore/workflow/manageActions');
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);     
        
        $aInfo = $this->buildWorkflowInfo($oWorkflow);
        
        $aActionsOrig = $aInfo['actions'];
        $aActions = array();
        foreach ($aActionsOrig as $oAction) {
            if ($oAction->getName() != 'ktcore.actions.document.displaydetails') {
                $aActions[] = $oAction;
            }
        }
        
        $oTemplate->setData(array(
            'context' => $this,
            'oWorkflow' => $oWorkflow,

            'aActions' => $aActions,
            'aActionsSelected' => $aInfo['controlled_actions'],
                       
            // info
            'workflow_info' => $aInfo,
        ));
        return $oTemplate;        
        
    }

    function do_manageStates() {
        $oTemplate =& $this->oValidator->validateTemplate('ktcore/workflow/manageStates');
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);        
        
        $aInfo = $this->buildWorkflowInfo($oWorkflow);
        
        
        $oTemplate->setData(array(
            'context' => $this,
            'oWorkflow' => $oWorkflow,
            
            // info
            'workflow_info' => $aInfo,
        ));
        return $oTemplate;
    }

    function do_createState() {
        $oTemplate =& $this->oValidator->validateTemplate('ktcore/workflow/createState');
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);        
        
        $aInfo = $this->buildWorkflowInfo($oWorkflow);
        
        $add_fields = array();
        $add_fields[] = new KTStringWidget(_kt('Name'), _kt('A human-readable name for the state.'), 'fName', null, $this->oPage, true);
        
        
        
        $oTemplate->setData(array(
            'context' => $this,
            'oWorkflow' => $oWorkflow,
            
            // info
            'workflow_info' => $aInfo,

            'aActions' => KTDocumentActionUtil::getDocumentActionsByNames(KTWorkflowUtil::getControlledActionsForWorkflow($oWorkflow)),
            'aActionsSelected' => KTWorkflowUtil::getEnabledActionsForState($oState),
            'aGroups' => Group::getList(),
            'aRoles' => Role::getList('id NOT IN (-3,-4)'),
            'aUsers' => User::getList(),            
            
            // subform
            'add_fields' => $add_fields,
        ));
        return $oTemplate;
    }

    function do_manageTransitions() {
        $oTemplate =& $this->oValidator->validateTemplate('ktcore/workflow/manageTransitions');
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);        
        
        $aInfo = $this->buildWorkflowInfo($oWorkflow);
            
        $oTemplate->setData(array(
            'context' => $this,
            'oWorkflow' => $oWorkflow,
                       
            // info
            'workflow_info' => $aInfo,
            
            // subform
            'add_fields' => $add_transition_fields,
        ));
        return $oTemplate;
    }

    function do_createTransition() {
        $oTemplate =& $this->oValidator->validateTemplate('ktcore/workflow/createTransition');
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);        
        
        $aInfo = $this->buildWorkflowInfo($oWorkflow);
        $aPermissions = $aInfo['permissions'];
        $aGroups = $aInfo['groups'];
        $aRoles = $aInfo['roles'];
        $aConditions = KTSavedSearch::getConditions();
        
        $add_transition_fields = array();
        $add_transition_fields[] = new KTStringWidget(_kt('Name'), _kt('A human-readable name for the transition.'), 'fName', null, $this->oPage, true);
        $aOptions = array();
        $vocab = array();
        foreach($aInfo['states'] as $state) {
            $vocab[$state->getId()] = $state->getName();
        } 
        $aOptions['vocab'] = $vocab;
        $add_transition_fields[] = new KTLookupWidget(_kt('Destination State'), _kt('Once this transition is complete, which state should the document be in?'), 'fTargetStateId', $oWorkflow->getStartStateId(), $this->oPage, true, null, null, $aOptions);
        /*        
        $aOptions = array();
        $vocab = array();
        $vocab[0] = _kt('None');
        foreach($aInfo['permissions'] as $permission) {
            $vocab[$permission->getId()] = $permission->getHumanName();
        } 
        $aOptions['vocab'] = $vocab;
        $add_transition_fields[] = new KTLookupWidget(_kt('Guard Permission.'), _kt('Which permission must the user have in order to follow this transition?'), 'fPermissionId', NULL, $this->oPage, true, null, null, $aOptions);
        
        $aOptions = array();
        $vocab = array();
        $vocab[0] = _kt('None');
        foreach($aGroups as $group) {
            $vocab[$group->getId()] = $group->getName();
        } 
        $aOptions['vocab'] = $vocab;
        $add_transition_fields[] = new KTLookupWidget(_kt('Guard Group.'), _kt('Which group must the user belong to in order to follow this transition?'), 'fGroupId', NULL, $this->oPage, false, null, null, $aOptions);
        $aOptions = array();
        $vocab = array();
        $vocab[0] = _kt('None');
        foreach($aRoles as $role) {
            $vocab[$role->getId()] = $role->getName();
        } 
        $aOptions['vocab'] = $vocab;
        $add_transition_fields[] = new KTLookupWidget(_kt('Guard Role.'), _kt('Which role must the user have in order to follow this transition?'), 'fRoleId', NULL, $this->oPage, false, null, null, $aOptions);

        if (!empty($aConditions)) {
            $aOptions = array();
            $vocab = array();
            $vocab[0] = _kt('None');
            foreach($aConditions as $condition) {
                $vocab[$condition->getId()] = $condition->getName();
            } 
            $aOptions['vocab'] = $vocab;
            $edit_fields[] = new KTLookupWidget(_kt('Guard Condition.'), _kt('Which condition (stored search) must be satisfied before the transition can take place?'), 'fConditionId', NULL, $this->oPage, false, null, null, $aOptions);
        }
        */        
        
        
        $oTemplate->setData(array(
            'context' => $this,
            'oWorkflow' => $oWorkflow,
                       
            // info
            'workflow_info' => $aInfo,
            
            // subform
            'add_fields' => $add_transition_fields,
        ));
        return $oTemplate;
    }

    function do_setTransitionAvailability() {
        $oTemplate =& $this->oValidator->validateTemplate('ktcore/workflow/editWorkflow');
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        
        $transitionMap = (array) KTUtil::arrayGet($_REQUEST, 'fTransitionAvailability');
        
        $aInfo = $this->buildWorkflowInfo($oWorkflow);
        
        $this->startTransaction();
        foreach ($aInfo['states'] as $oState) {
            
            $a = (array) $transitionMap[$oState->getId()];
            $transitions = array();
            foreach ($a as $tid => $on) { $transitions[] = $tid; }
            
            $res = KTWorkflowUtil::saveTransitionsFrom($oState, $transitions);
            if (PEAR::isError($res)) {
                $this->errorRedirectTo('manageTransitions', _kt('Error updating transitions:') . $res->getMessage(), sprintf('fWorkflowId=%d', $oWorkflow->getId()));
            }
        }
        $this->commitTransaction();
        
        $this->successRedirectTo('manageTransitions', _kt('Transition Availability updated.'), sprintf('fWorkflowId=%d', $oWorkflow->getId()));
    }
    
    
    function do_updateActionAvailability() {
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        
        $actionMap = (array) KTUtil::arrayGet($_REQUEST, 'fAvailableActions');
        
        $aInfo = $this->buildWorkflowInfo($oWorkflow);
        
        $this->startTransaction();
        foreach ($aInfo['states'] as $oState) {
            
            $a = (array) $actionMap[$oState->getId()];
            $actions = array_keys($a);
            
            
            
            $res = KTWorkflowUtil::setEnabledActionsForState($oState, $actions);
            if (PEAR::isError($res)) {
                $this->errorRedirectTo('manageActions', _kt('Error updating actions:') . $res->getMessage(), sprintf('fWorkflowId=%d', $oWorkflow->getId()));
            }
        }
        $this->commitTransaction();
        
        $this->successRedirectTo('manageActions', _kt('Action availability updated.'), sprintf('fWorkflowId=%d', $oWorkflow->getId()));
    }
    
    // {{{ do_setWorkflowActions
    function do_setWorkflowActions() {
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        $res = KTWorkflowUtil::setControlledActionsForWorkflow($oWorkflow, $_REQUEST['fActions']);
        $this->oValidator->notErrorFalse($res, array(
            'redirect_to' => array('editWorkflow', 'fWorkflowId=' . $oWorkflow->getId()),
            'message' => _kt('Error saving workflow controlled actions'),
        ));
        $this->successRedirectTo('manageActions', _kt('Controlled actions changed.'), 'fWorkflowId=' . $oWorkflow->getId());
        exit(0);
    }
    // }}}

    // }}}

    // {{{ STATE HANDLING
    //
    // {{{ do_newState
    function do_newState() {
        $iWorkflowId = (int) $_REQUEST['fWorkflowId'];
        
        $aErrorOptions = array(
            'redirect_to' => array('editWorkflow', sprintf('fWorkflowId=%d', $iWorkflowId)),
        );

        $oWorkflow =& $this->oValidator->validateWorkflow($iWorkflowId);
        
        // validate name
        $sName = $this->oValidator->validateString($_REQUEST['fName'], $aErrorOptions);
        
        // check there are no other states by that name in this workflow
        $aStates = KTWorkflowState::getList(sprintf("workflow_id = %d and name = '%s'", $iWorkflowId, $sName));
        if(count($aStates)) {
            $this->errorRedirectTo(implode('&', $aErrorOptions['redirect_to']), _kt("A state by that name already exists"));
        }
        
        $oState = KTWorkflowState::createFromArray(array(
            'workflowid' => $oWorkflow->getId(),
            'name' => $sName,
            'humanname' => $sName,
        ));
        
        $this->oValidator->notError($oState, array(
            'redirect_to' => array('createState', 'fWorkflowId=' .  $oWorkflow->getId()),
            'message' => _kt('Could not create workflow state'),
        ));
        
        $res = KTWorkflowUtil::setEnabledActionsForState($oState, $_REQUEST['fActions']);
        $this->oValidator->notErrorFalse($res, array(
            'redirect_to' => array('editState', 'fWorkflowId=' . $oWorkflow->getId(), '&fStateId=' .  $oState->getId()),
            'message' => _kt('Error saving state enabled actions'),
        ));
        
        $this->successRedirectTo('editState', _kt('Workflow state created'), 'fWorkflowId=' . $oWorkflow->getId() . '&fStateId=' .  $oState->getId());
        exit(0);
    }
    // }}}

    // {{{ do_editState
    function do_editState() {
        $oTemplate =& $this->oValidator->validateTemplate('ktcore/workflow/editState');
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        $oState =& $this->oValidator->validateWorkflowState($_REQUEST['fStateId']);
        
        $aInfo = $this->buildWorkflowInfo($oWorkflow);
        
        $aTransitionsTo =& KTWorkflowTransition::getByTargetState($oState);
        $aTransitionIdsTo = array();
        foreach ($aTransitionsTo as $oTransition) {
            $aTransitionIdsTo[] = $oTransition->getId();
        }
        $aAllTransitions =& KTWorkflowTransition::getByWorkflow($oWorkflow);
        $aTransitions = array();
        foreach ($aAllTransitions as $oTransition) {
            if (!in_array($oTransition->getId(), $aTransitionIdsTo)) {
                $aTransitions[] = $oTransition;
            }
        }
        $aTransitionsSelected = KTWorkflowUtil::getTransitionsFrom($oState, array('ids' => true));

        $this->oPage->setBreadcrumbDetails(_kt('State') . ': ' . $oState->getName());
        
        $aInformed = KTWorkflowUtil::getInformedForState($oState);
        
        $editForm = array();
        $editForm[] = new KTStringWidget(_kt('Name'), _kt('A human-readable name for this state.  This is shown on the "Browse" page, as well as on the user\'s workflow page.'), 'fName', $oState->getName(), $this->oPage, true);
        
        
        $this->getPermissionAssignmentsForState($oState);
        $aActionOrig = KTDocumentActionUtil::getDocumentActionsByNames(KTWorkflowUtil::getControlledActionsForWorkflow($oWorkflow));
        $aActions = array();
        foreach ($aActionOrig as $k => $oAction) {
            if ($oAction->getName() == 'ktcore.actions.document.displaydetails') {
                continue;
            }
            $aActions[] = $oAction;
        }
        
        $oTemplate->setData(array(
            'context' => $this,
            'oWorkflow' => $oWorkflow,
            'oState' => $oState,
            'oNotifyRole' => $oRole,
            'aTransitionsTo' => $aTransitionsTo,
            'aTransitions' => $aTransitions,
            'aTransitionsSelected' => $aTransitionsSelected,
            'aActions' => $aActions, 
            'aActionsSelected' => KTWorkflowUtil::getEnabledActionsForState($oState),
            'aGroups' => Group::getList(),
            'aRoles' => Role::getList('id NOT IN (-3,-4)'),
            'aUsers' => User::getList(),
            'aInformed' => $aInformed,
            'editForm' => $editForm,
            'permissions' => $aInfo['permissions'],
            'state_permissions' => $aInfo['permissions_by_state'][$oState->getId()],
        ));
        return $oTemplate;
    }
    // }}}

    // {{{ do_saveState
    function do_saveState() {
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        $oState =& $this->oValidator->validateWorkflowState($_REQUEST['fStateId']);
        $oState->setName($_REQUEST['fName']);
        $oState->setHumanName($_REQUEST['fName']);
        $res = $oState->update();
        $this->oValidator->notErrorFalse($res, array(
            'redirect_to' => array('editState', 'fWorkflowId=' . $oWorkflow->getId() . '&fStateId=' . $oState->getId()),
            'message' => _kt('Error saving state'),
        ));
        $this->successRedirectTo('editState', _kt('Changes saved'), 'fWorkflowId=' . $oWorkflow->getId() . '&fStateId=' .  $oState->getId());
        exit(0);
    }
    // }}}

    function do_deleteState() {
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        $oState =& $this->oValidator->validateWorkflowState($_REQUEST['fStateId']);
        $aInfo = $this->buildWorkflowInfo($oWorkflow);
        if (!$aInfo['can_delete']) { $this->errorRedirectTo('manageStates', _kt('May not delete items from an active workflow'), 'fWorkflowId=' . $oWorkflow->getId()); }
        
        $this->startTransaction();

        // clearing for integrity
        // transitions starting from that state.
        $sTable = KTUtil::getTableName('workflow_state_transitions');
        $aQuery = array(
            "DELETE FROM $sTable WHERE state_id = ?",
            array($oState->getId()),
        );
        $res = DBUtil::runQuery($aQuery);        
        if (PEAR::isError($res)) { $this->errorRedirectTo('manageStates', _kt('Unable to clear references to item'), 'fWorkflowId=' . $oWorkflow->getId()); }
        
        // transitions ending in that state
        $aTransitionNames = array();
        $aTransitionsToDelete = KTWorkflowTransition::getList('target_state_id = ' . $oState->getId());
        foreach ($aTransitionsToDelete as $oTransition) {
            $sTable = KTUtil::getTableName('workflow_state_transitions');
            $aQuery = array(
                "DELETE FROM $sTable WHERE transition_id = ?",
                array($oTransition->getId()),
            );
            $res = DBUtil::runQuery($aQuery);        
            if (PEAR::isError($res)) { $this->errorRedirectTo('manageStates', _kt('Unable to remove transition references for: ') . $oTransition->getName(), 'fWorkflowId=' . $oWorkflow->getId()); }
            
            $res = $oTransition->delete();
            if (PEAR::isError($res)) { $this->errorRedirectTo('manageStates', _kt('Unable to remove transition: ') . $oTransition->getName(), 'fWorkflowId=' . $oWorkflow->getId()); }
            
            $aTransitionNames[] = $oTransition->getName();
        }
        
        // if its the default state, change.
        if ($oState->getId() == $oWorkflow->getStartStateId()) {
            $oWorkflow->setStartStateId(null);
            $res = $oWorkflow->update();
            if (PEAR::isError($res)) { $this->errorRedirectTo('manageStates', _kt('Unable to change workflow starting state: ') . $res->getMessage(), 'fWorkflowId=' . $oWorkflow->getId()); }
        }
        
        // finally, delete the state
        $res = $oState->delete();         // does this handle referential integrity?
        if (PEAR::isError($res)) { $this->errorRedirectTo('manageStates', _kt('Unable to delete item: ') . $res->getMessage(), 'fWorkflowId=' . $oWorkflow->getId()); }        
        
        $this->commitTransaction();
        if (!empty($aTransitionNames)) {
            $sTransitionNames = implode (', ', $aTransitionNames);
            $msg = sprintf(_kt('State deleted. Also deleted transitions ending in that state: %s'), $sTransitionNames);
        } else {
            $msg = _kt('State deleted.');
        }
        
        $this->successRedirectTo('manageStates', $msg, 'fWorkflowId=' . $oWorkflow->getId());
        
    }

    function do_setStatePermissions() {
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        $oState =& $this->oValidator->validateWorkflowState($_REQUEST['fStateId']);
        
        $aInfo = $this->buildWorkflowInfo($oWorkflow);
        $aExistingAlloc = $aInfo['permissions_by_state'][$oState->getId()];
        
        $aPermissions = (array) KTUtil::arrayGet($_REQUEST, 'fPermissions');
        
        $doWork = false;
        $aEmptyAllowed = array();
        
        $this->startTransaction();
        
        foreach ($aPermissions as $sPermName) {
            // lets not do too much work here.
            $id = array_search($sPermName, $aExistingAlloc);
            if ($id === false) {
                $doWork = true;       // going to need to regen the perm array
                $oDescriptor = KTPermissionUtil::getOrCreateDescriptor($aEmptyAllowed);
                $aOpts = array(
                    'StateId' => $oState->getId(),
                    'PermissionId' => $aInfo['permissions'][$sPermName]->getId(),
                    'DescriptorId' => $oDescriptor->getId(),
                );
                $res = KTWorkflowStatePermissionAssignment::createFromArray($aOpts);
                if (PEAR::isError($res)) {
                    $this->errorRedirectTo('editState', _kt('Failed to create permission assignment: ') . $res->getMessage(),sprintf('fStateId=%d&fWorkflowId=%d',$oState->getId(),$oWorkflow->getId()));
                }
                
            } else {
                // now, _don't_ delete later
                unset($aExistingAlloc[$id]);
            }
        }
        
        // now remove the _old_ (unset) assignments.
        foreach ($aExistingAlloc as $iAllocId => $sPerm) {
            $oAlloc = KTWorkflowStatePermissionAssignment::get($iAllocId);
            $oAlloc->delete();
        }
        
        // FIXME implement:
        // $this->_regenStatePermissionLookups($oState);
        
        KTPermissionUtil::updatePermissionLookupForState($oState);
        
        $this->successRedirectTo('editState', _kt('Permissions for workflow assigned'),sprintf('fStateId=%d&fWorkflowId=%d',$oState->getId(),$oWorkflow->getId())); 
    }
    
    function do_assignStatePermissions() {
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        $oState =& $this->oValidator->validateWorkflowState($_REQUEST['fStateId']);
        
        $aInfo = $this->buildWorkflowInfo($oWorkflow);
        $aAlloc = $aInfo['permissions_by_state'][$oState->getId()];
        
        $aPermissionAllowed = (array) KTUtil::arrayGet($_REQUEST, 'fPermissions');
        $exitQS = sprintf('fStateId=%d&fWorkflowId=%d',$oState->getId(),$oWorkflow->getId());
        
        $this->startTransaction();
        
        // we now walk the alloc'd perms, and go.
        foreach ($aAlloc as $iAllocId => $sPermName) {
            $aAllowed = (array) $aPermissionAllowed[$sPermName]; // is already role, group, etc.
            $oAlloc = KTWorkflowStatePermissionAssignment::get($iAllocId);
            //var_dump($aAllowed);
            $oDescriptor = KTPermissionUtil::getOrCreateDescriptor($aAllowed);            
            if (PEAR::isError($oDescriptor)) { $this->errorRedirectTo('editState', _kt('Failed to allocate as specified.'), $exitQS); } 
            
            $oAlloc->setDescriptorId($oDescriptor->getId());
            $res = $oAlloc->update();
            if (PEAR::isError($res)) { $this->errorRedirectTo('editState', _kt('Failed to allocate as specified.'), $exitQS); }             
        }
        
        KTPermissionUtil::updatePermissionLookupForState($oState);
        
        $this->successRedirectTo('editState', _kt('Permissions Allocated.'), $exitQS);
    }

    // {{{ do_saveTransitions
    function do_saveTransitions() {
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        $oState =& $this->oValidator->validateWorkflowState($_REQUEST['fStateId']);
        $res = KTWorkflowUtil::saveTransitionsFrom($oState, $_REQUEST['fTransitionIds']);
        $this->oValidator->notErrorFalse($res, array(
            'redirect_to' => array('editState', 'fWorkflowId=' . $oWorkflow->getId() . '&fStateId=' . $oState->getId()),
            'message' => _kt('Error saving transitions'),
        ));
        $this->successRedirectTo('editState', _kt('Changes saved'), 'fWorkflowId=' . $oWorkflow->getId() . '&fStateId=' .  $oState->getId());
        exit(0);
    }
    // }}}
    
    function do_deleteTransition() {
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        $oTransition =& KTWorkflowTransition::get($_REQUEST['fTransitionId']);
        if (PEAR::isError($oTransition)) {
            $this->errorRedirectTo('manageTransitions', _kt('Invalid transition'),'fWorkflowId=' . $oWorkflow->getId()); 
        }
        $aInfo = $this->buildWorkflowInfo($oWorkflow);
        if (!$aInfo['can_delete']) { $this->errorRedirectTo('manageTransitions', _kt('May not delete items from an active workflow'), 'fWorkflowId=' . $oWorkflow->getId()); }
        $this->startTransaction();
        
        $sTable = KTUtil::getTableName('workflow_state_transitions');
        $aQuery = array(
            "DELETE FROM $sTable WHERE transition_id = ?",
            array($oTransition->getId()),
        );
        $res = DBUtil::runQuery($aQuery);        
        if (PEAR::isError($res)) { $this->errorRedirectTo('manageTransitions', _kt('Unable to remove transition references for: ') . $oTransition->getName(), 'fWorkflowId=' . $oWorkflow->getId()); }                            
        
        $res = $oTransition->delete();         // does this handle referential integrity?
        if (PEAR::isError($res)) { $this->errorRedirectTo('manageTransitions', _kt('Unable to delete item'), 'fWorkflowId=' . $oWorkflow->getId()); }
        $this->commitTransaction();
        $this->successRedirectTo('manageTransitions', _kt('Transition deleted.'), 'fWorkflowId=' . $oWorkflow->getId());
    }    
    
    // {{{ do_setStateActions
    function do_setStateActions() {
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        $oState =& $this->oValidator->validateWorkflowState($_REQUEST['fStateId']);
        $res = KTWorkflowUtil::setEnabledActionsForState($oState, $_REQUEST['fActions']);
        $this->oValidator->notErrorFalse($res, array(
            'redirect_to' => array('editState', 'fWorkflowId=' . $oWorkflow->getId(), '&fStateId=' .  $oState->getId()),
            'message' => _kt('Error saving state enabled actions'),
        ));
        $this->successRedirectTo('manageActions', _kt('Controlled Actions changed.'), 'fWorkflowId=' . $oWorkflow->getId() . '&fStateId=' .  $oState->getId());
        exit(0);
    }
    // }}}

    // {{{ do_saveInform
    function do_saveInform() {
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        $oState =& $this->oValidator->validateWorkflowState($_REQUEST['fStateId']);
        $sTargetAction = 'editState';
        $sTargetParams = 'fWorkflowId=' . $oWorkflow->getId() .  '&fStateId=' .  $oState->getId();
        $aNotification = (array) KTUtil::arrayGet($_REQUEST, 'fNotification');
        
        if (empty($aNotification['role'])) {
            $aNotification['role'] = array();
        }
        if (!is_array($aNotification['role'])) {
            $this->errorRedirectTo($sTargetAction, _kt('Invalid roles specified'), $sTargetParams);
        }
        
        if (empty($aNotification['group'])) {
            $aNotification['group'] = array();
        }
        if (!is_array($aNotification['group'])) {
            $this->errorRedirectTo($sTargetAction, _kt('Invalid groups specified'), $sTargetParams);
        }
        
        $aNotification['user'] = array(); // force override
        
        $res = KTWorkflowUtil::setInformedForState($oState, $aNotification);
        if (PEAR::isError($res)) {
            $this->errorRedirectTo($sTargetAction, sprintf(_kt('Failed to update the notification lists: %s'),$res->getMessage()), $sTargetParams); 
        }
        $this->successRedirectTo($sTargetAction, _kt('Changes saved'), $sTargetParams);
    }
    // }}}

    // }}}

    // {{{ TRANSITION HANDLING
    //
    // {{{ do_newTransition
    function do_newTransition() {
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        $oState =& $this->oValidator->validateWorkflowState($_REQUEST['fTargetStateId']);
        
        $aInfo = $this->buildWorkflowInfo($oWorkflow);
        
        // setup error options for later
        $aErrorOptions = array(
            'redirect_to' => array('editWorkflow', sprintf('fWorkflowId=%d', $oWorkflow->getId())),
        );

        $iPermissionId = KTUtil::arrayGet($_REQUEST, 'fPermissionId');
        $iGroupId = KTUtil::arrayGet($_REQUEST, 'fGroupId');
        $iRoleId = KTUtil::arrayGet($_REQUEST, 'fRoleId');
        $iConditionId = KTUtil::arrayGet($_REQUEST, 'fConditionId', null);
        
        // validate name
        $sName = $this->oValidator->validateString(KTUtil::arrayGet($_REQUEST, 'fName'), $aErrorOptions);
        

        // check there are no other transitions by that name in this workflow
        $aTransitions = KTWorkflowTransition::getList(sprintf("workflow_id = %d and name = '%s'", $oWorkflow->getId(), $sName));
        if(count($aTransitions)) {
            $this->errorRedirectTo(implode('&', $aErrorOptions['redirect_to']), _kt("A transition by that name already exists"));
        }


        // validate permissions, roles, and group
        if ($iPermissionId) {
            $this->oValidator->validatePermission($_REQUEST['fPermissionId']);
        }
        if ($iGroupId) {
            $this->oValidator->validateGroup($_REQUEST['fGroupId']);
        }
        if ($iRoleId) {
            $this->oValidator->validateRole($_REQUEST['fRoleId']);
        }
        if ($iConditionId) {
            $this->oValidator->validateCondition($_REQUEST['fConditionId']);
        }
        
        $res = KTWorkflowTransition::createFromArray(array(
            'workflowid' => $oWorkflow->getId(),
            'name' => $_REQUEST['fName'],
            'humanname' => $_REQUEST['fName'],
            'targetstateid' => $oState->getId(),
            'guardpermissionid' => $iPermissionId,
            'guardgroupid' => $iGroupId,
            'guardroleid' => $iRoleId,
        ));
        $this->oValidator->notError($res, array(
            'redirect_to' => array('editWorkflow', 'fWorkflowId=' .  $oWorkflow->getId()),
            'message' => _kt('Could not create workflow transition'),
        ));
        
        // now attach it to the appropriate states.
        $aStateId = (array) KTUtil::arrayGet($_REQUEST, 'fStatesAvailableIn');
        $aStateId = array_keys($aStateId);
        $newTransition = $res;
        
        foreach ($aStateId as $iStateId) {
            if ($iStateId == ($newTransition->getTargetStateId())) { continue; }
            $oState = $aInfo['states'][$iStateId];
            
            $aTransitions = KTWorkflowTransition::getBySourceState($oState);
            $aTransitions[] = $newTransition;
            $aTransitionIds = array();
            foreach ($aTransitions as $oTransition) {
                $aTransitionIds[] = $oTransition->getId();
            }
            $res = KTWorkflowUtil::saveTransitionsFrom($oState, $aTransitionIds);
            if (PEAR::isError($res)) {
                $this->errorRedirectTo('manageTransitions',sprintf(_kt('Unable to assign new transition to state %s'),$oState->getName()), sprintf('fWorkflowId=%d', $oWorkflow->getId()));
            }
        }
        
        
        $this->successRedirectTo('manageTransitions', _kt('Workflow transition created'), sprintf('fWorkflowId=%d', $oWorkflow->getId()));
        exit(0);
    }
    // }}}

    // {{{ do_editTransition
    function do_editTransition() {
        $oTemplate =& $this->oValidator->validateTemplate('ktcore/workflow/editTransition');
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        $oTransition =& $this->oValidator->validateWorkflowTransition($_REQUEST['fTransitionId']);
        
        $aStates = KTWorkflowState::getByWorkflow($oWorkflow);
        $aPermissions = KTPermission::getList();
        $aGroups = Group::getList();
        $aRoles = Role::getList();
        $aConditions = KTSavedSearch::getConditions();
        
        $edit_fields = array();
        $edit_fields[] = new KTStringWidget(_kt('Name'), _kt('A human-readable name for the state.'), 'fName', $oTransition->getName(), $this->oPage, true);
        $aOptions = array();
        $vocab = array();
        foreach($aStates as $state) {
            $vocab[$state->getId()] = $state->getName();
        } 
        $aOptions['vocab'] = $vocab;
        $edit_fields[] = new KTLookupWidget(_kt('Destination State'), _kt('Once this transition is complete, which state should the document be in?'), 'fTargetStateId', $oTransition->getTargetStateId(), $this->oPage, true, null, null, $aOptions);

        // triggers 
        $add_trigger_fields = array();
        $vocab = array();
        $vocab[0] = _kt('-- Please select a trigger --');
        $oTriggerSingleton =& KTWorkflowTriggerRegistry::getSingleton();
        $aTriggerList = $oTriggerSingleton->listWorkflowTriggers(); // only want registered triggers - no other kind exists.
        foreach ($aTriggerList as $ns => $aTriggerInfo) {
            $aInfo = $aTriggerInfo; // i am lazy.
            //var_dump($aInfo);
            $actions = array();
            if ($aInfo['guard']) {
                $actions[] = _kt('Guard');
            }
            if ($aInfo['action']) {
                $actions[] = _kt('Action');
            }
            $sActStr = implode(', ', $actions);
            $vocab[$ns] = sprintf(_kt("%s (%s)"), $aInfo['name'], $sActStr);
        }    
        
        $aOptions['vocab'] = $vocab;
        $add_trigger_fields[] = new KTLookupWidget(_kt('Trigger'), _kt('Select the trigger to add to this transition.  Each trigger indicates whether it controls who can see this transition, what occurs when the transition is performed, or both.'), 'fTriggerId', '0', $this->oPage, true, null, null, $aOptions);
        $aOptions = array();
        
        
        // attached triggers.
        $aGuardTriggers = KTWorkflowUtil::getGuardTriggersForTransition($oTransition);
        $aActionTriggers = KTWorkflowUtil::getActionTriggersForTransition($oTransition);
        
        $this->aBreadcrumbs[] = array(
            'url' => $_SERVER['PHP_SELF'],
            'query' => 'action=editTransition&fWorkflowId=' . $oWorkflow->getId() . '&fTransitionId=' . $oTransition->getId(),
            'name' => $oTransition->getName(),
        );
        $oTemplate->setData(array(
            'oWorkflow' => $oWorkflow,
            'oTransition' => $oTransition,
            'aStates' => $aStates,
            'aPermissions' => $aPermissions,
            'aGroups' => $aGroups,
            'aRoles' => $aRoles,
            'aConditions' => $aConditions,
            'aGuardTriggers' => $aGuardTriggers,
            'aActionTriggers' => $aActionTriggers,
            
            // fields 
            'add_trigger_fields' => $add_trigger_fields,
            'edit_fields' => $edit_fields,
        ));
        return $oTemplate;
    }
    // }}}

    // {{{ do_saveTransition
    function do_saveTransition() {
        $aRequest = $this->oValidator->validateDict($_REQUEST, array(
            'fWorkflowId' => array('type' => 'workflow'),
            'fTransitionId' => array('type' => 'workflowtransition'),
        ));
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        $oTransition =& $this->oValidator->validateWorkflowTransition($_REQUEST['fTransitionId']);
        $oState =& $this->oValidator->validateWorkflowState($_REQUEST['fTargetStateId']);
        $iPermissionId = KTUtil::arrayGet($_REQUEST, 'fPermissionId', null);
        $iGroupId = KTUtil::arrayGet($_REQUEST, 'fGroupId', null);
        $iRoleId = KTUtil::arrayGet($_REQUEST, 'fRoleId', null);
        $iConditionId = KTUtil::arrayGet($_REQUEST, 'fConditionId', null);
        if ($iPermissionId) {
            $this->oValidator->validatePermission($_REQUEST['fPermissionId']);
        }
        if ($iGroupId) {
            $this->oValidator->validateGroup($_REQUEST['fGroupId']);
        }
        if ($iRoleId) {
            $this->oValidator->validateRole($_REQUEST['fRoleId']);
        }
        if ($iConditionId) {
            $this->oValidator->validateCondition($_REQUEST['fConditionId']);
        }
        $oTransition->updateFromArray(array(
            'workflowid' => $oWorkflow->getId(),
            'name' => $_REQUEST['fName'],
            'humanname' => $_REQUEST['fName'],
            'targetstateid' => $oState->getId(),
            'guardpermissionid' => $iPermissionId,
            'guardgroupid' => $iGroupId,
            'guardroleid' => $iRoleId,
            'guardconditionid' => $iConditionId,
        ));
        $res = $oTransition->update();
        $this->oValidator->notErrorFalse($res, array(
            'redirect_to' => array('editTransition', 'fWorkflowId=' . $oWorkflow->getId() . '&fTransitionId=' . $oTransition->getId()),
            'message' => _kt('Error saving transition'),
        ));
        
        // also grab the list of transitions for the dest state, and remove this one if application
        $aDestTransitions = KTWorkflowUtil::getTransitionsFrom($oState, array('ids' => true));
        $bClean = true;
        $aNewTransitions = array();
        foreach ($aDestTransitions as $iOldTransitionId) {
            if ($oTransition->getId() == $iOldTransitionId) {
                $bClean = false;
            } else {
                $aNewTransitions[] = $iOldTransitionId;
            }
        }
        if (!$bClean) {
            KTWorkflowUtil::saveTransitionsFrom($oState, $aNewTransitions);
        }
        
        $this->successRedirectTo('editTransition', _kt('Changes saved'), 'fWorkflowId=' . $oWorkflow->getId() . '&fTransitionId=' .  $oTransition->getId());
        exit(0);
    }
    // }}}

    function do_addTrigger() {
        $aRequest = $this->oValidator->validateDict($_REQUEST, array(
            'fWorkflowId' => array('type' => 'workflow'),
            'fTransitionId' => array('type' => 'workflowtransition'),
        ));
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        $oTransition =& $this->oValidator->validateWorkflowTransition($_REQUEST['fTransitionId']);

        // grab the transition ns from the request.
        $KTWFTriggerReg =& KTWorkflowTriggerRegistry::getSingleton();

        $this->startTransaction();

        $oTrigger = $KTWFTriggerReg->getWorkflowTrigger(KTUtil::arrayGet($_REQUEST, 'fTriggerId'));
        if (PEAR::isError($oTrigger)) {
            $this->errorRedirectTo('editTransition', _kt('Unable to add trigger.'), 'fWorkflowId=' . $oWorkflow->getId() . '&fTransitionId=' .  $oTransition->getId());
            exit(0);
        }

        $oTriggerConfig = KTWorkflowTriggerInstance::createFromArray(array(
            'transitionid' => KTUtil::getId($oTransition),
            'namespace' =>  KTUtil::arrayGet($_REQUEST, 'fTriggerId'),
            'config' => array(),
        ));
        
        if (PEAR::isError($oTriggerConfig)) {
            $this->errorRedirectTo('editTransition', _kt('Unable to add trigger.' . $oTriggerConfig->getMessage()), 'fWorkflowId=' . $oWorkflow->getId() . '&fTransitionId=' .  $oTransition->getId());
            exit(0);
        }

        $this->successRedirectTo('editTransition', _kt('Trigger added.'), 'fWorkflowId=' . $oWorkflow->getId() . '&fTransitionId=' .  $oTransition->getId());
        exit(0);
    }

    function do_editTrigger() {
        $this->oPage->setBreadcrumbDetails(_kt('editing trigger'));
        $aRequest = $this->oValidator->validateDict($_REQUEST, array(
            'fWorkflowId' => array('type' => 'workflow'),
            'fTransitionId' => array('type' => 'workflowtransition'),
        ));
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        $oTransition =& $this->oValidator->validateWorkflowTransition($_REQUEST['fTransitionId']);
        $oTriggerInstance =& KTWorkflowTriggerInstance::get($_REQUEST['fTriggerInstanceId']);        
        if (PEAR::isError($oTriggerInstance)) {
            $this->errorRedirectTo('editTransition', _kt('Unable to load trigger.'), 'fWorkflowId=' . $oWorkflow->getId() . '&fTransitionId=' .  $oTransition->getId());
            exit(0);        
        }

        // grab the transition ns from the request.
        $KTWFTriggerReg =& KTWorkflowTriggerRegistry::getSingleton();

        $this->startTransaction();

        $oTrigger = $KTWFTriggerReg->getWorkflowTrigger($oTriggerInstance->getNamespace());
        if (PEAR::isError($oTrigger)) {
            $this->errorRedirectTo('editTransition', _kt('Unable to add trigger.'), 'fWorkflowId=' . $oWorkflow->getId() . '&fTransitionId=' .  $oTransition->getId());
            exit(0);
        }
        $oTrigger->loadConfig($oTriggerInstance);
                
    
        // simplify our 'config' stuff.
        $args = array();
        $args['fWorkflowId'] = $_REQUEST['fWorkflowId'];
        $args['fTriggerInstanceId'] = $_REQUEST['fTriggerInstanceId'];
        $args['fTransitionId'] = $_REQUEST['fTransitionId'];                
        $args['action'] = 'saveTrigger';                   

        return $oTrigger->displayConfiguration($args);
    }

    // }}}

    function do_saveTrigger() {
        $this->oPage->setBreadcrumbDetails(_kt('editing trigger'));
        $aRequest = $this->oValidator->validateDict($_REQUEST, array(
            'fWorkflowId' => array('type' => 'workflow'),
            'fTransitionId' => array('type' => 'workflowtransition'),
        ));
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        $oTransition =& $this->oValidator->validateWorkflowTransition($_REQUEST['fTransitionId']);
        $oTriggerInstance =& KTWorkflowTriggerInstance::get($_REQUEST['fTriggerInstanceId']);        
        if (PEAR::isError($oTriggerInstance)) {
            $this->errorRedirectTo('editTransition', _kt('Unable to load trigger.'), 'fWorkflowId=' . $oWorkflow->getId() . '&fTransitionId=' .  $oTransition->getId());
            exit(0);        
        }

        // grab the transition ns from the request.
        $KTWFTriggerReg =& KTWorkflowTriggerRegistry::getSingleton();

        $this->startTransaction();

        $oTrigger = $KTWFTriggerReg->getWorkflowTrigger($oTriggerInstance->getNamespace());
        if (PEAR::isError($oTrigger)) {
            $this->errorRedirectTo('editTransition', _kt('Unable to load trigger.'), 'fWorkflowId=' . $oWorkflow->getId() . '&fTransitionId=' .  $oTransition->getId());
            exit(0);
        }
        $oTrigger->loadConfig($oTriggerInstance);
        
        $res = $oTrigger->saveConfiguration();
        if (PEAR::isError($res)) {
            $this->errorRedirectTo('editTransition', _kt('Unable to save trigger: ') . $res->getMessage(), 'fWorkflowId=' . $oWorkflow->getId() . '&fTransitionId=' .  $oTransition->getId());
            exit(0);            
        }
    
        $this->successRedirectTo('editTransition', _kt('Trigger saved.'), 'fWorkflowId=' . $oWorkflow->getId() . '&fTransitionId=' .  $oTransition->getId());
        exit(0);    
    }

    function do_deleteTrigger() {
        $aRequest = $this->oValidator->validateDict($_REQUEST, array(
            'fWorkflowId' => array('type' => 'workflow'),
            'fTransitionId' => array('type' => 'workflowtransition'),
        ));
        $oWorkflow =& $this->oValidator->validateWorkflow($_REQUEST['fWorkflowId']);
        $oTransition =& $this->oValidator->validateWorkflowTransition($_REQUEST['fTransitionId']);
        $oTriggerInstance =& KTWorkflowTriggerInstance::get($_REQUEST['fTriggerInstanceId']);        
        if (PEAR::isError($oTriggerInstance)) {
            $this->errorRedirectTo('editTransition', _kt('Unable to load trigger.'), 'fWorkflowId=' . $oWorkflow->getId() . '&fTransitionId=' .  $oTransition->getId());
            exit(0);        
        }

        // grab the transition ns from the request.
        $KTWFTriggerReg =& KTWorkflowTriggerRegistry::getSingleton();

        $this->startTransaction();

        $oTrigger = $KTWFTriggerReg->getWorkflowTrigger($oTriggerInstance->getNamespace());
        if (PEAR::isError($oTrigger)) {
            $this->errorRedirectTo('editTransition', _kt('Unable to load trigger.'), 'fWorkflowId=' . $oWorkflow->getId() . '&fTransitionId=' .  $oTransition->getId());
            exit(0);
        }
        $oTrigger->loadConfig($oTriggerInstance);
        
        $res = $oTriggerInstance->delete();
        if (PEAR::isError($res)) {
            $this->errorRedirectTo('editTransition', _kt('Unable to delete trigger: ') . $res->getMessage(), 'fWorkflowId=' . $oWorkflow->getId() . '&fTransitionId=' .  $oTransition->getId());
            exit(0);            
        }
    
        $this->successRedirectTo('editTransition', _kt('Trigger deleted.'), 'fWorkflowId=' . $oWorkflow->getId() . '&fTransitionId=' .  $oTransition->getId());
        exit(0);    
    }


    // }}}

}

?>
