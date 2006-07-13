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

require_once(KT_LIB_DIR . '/dispatcher.inc.php');
require_once(KT_LIB_DIR . '/templating/templating.inc.php');
require_once(KT_LIB_DIR . '/templating/kt3template.inc.php');

require_once(KT_LIB_DIR . '/documentmanagement/DocumentField.inc');
require_once(KT_LIB_DIR . '/metadata/fieldset.inc.php');
require_once(KT_LIB_DIR . '/metadata/metadatautil.inc.php');

require_once(KT_LIB_DIR . '/widgets/fieldWidgets.php');
require_once(KT_LIB_DIR . '/documentmanagement/MDTree.inc');


require_once(KT_LIB_DIR . '/plugins/pluginutil.inc.php');

// FIXME shouldn't this inherit from AdminDispatcher?
class KTDocumentFieldDispatcher extends KTAdminDispatcher {
    var $bAutomaticTransaction = true;
	var $bHaveConditional = null;
    var $sHelpPage = 'ktcore/admin/document fieldsets.html';

    function check() {
        $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _kt('Document Field Management'));
        return true;
    }

    // {{{ do_main
    function do_main () {
        $this->oPage->setBreadcrumbDetails(_kt("view fieldsets"));
        
        // function KTBaseWidget($sLabel, $sDescription, $sName, $value, $oPage, $bRequired = false, $sId = null, $aErrors = null, $aOptions = null) {
        // use widgets for the create form.
        $createFields = array();
        $createFields[] = new KTStringWidget(_kt('Name'), _kt('A human-readable name, used in add and edit forms.'), 'name', null, $this->oPage, true);
        $createFields[] = new KTTextWidget(_kt('Description'), _kt('A brief description of the information stored in this fieldset.'), 'description', null, $this->oPage, true);
        $createFields[] = new KTCheckboxWidget(_kt('Generic'), _kt('A generic fieldset is one that is available for every document by default.  These fieldsets will be available for users to edit and add for every document in the document management system.'), 'generic', false, $this->oPage, false);
    
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate =& $oTemplating->loadTemplate('ktcore/metadata/listFieldsets');
        $oTemplate->setData(array(
		    'context' => $this,
            'fieldsets' => KTFieldset::getList(),
            'creation_fields' => $createFields,
        ));
        return $oTemplate;
    }
    // }}}

	function getTypesForFieldset($oFieldset) {
	    if ($oFieldset->getIsGeneric()) {
		    return _kt('All types use this generic fieldset.');
		}
		
	    $types = $oFieldset->getAssociatedTypes();
		if (PEAR::isError($types)) {
		    return _kt('Error retrieving list of types.');
		}
		if (empty($types)) { 
		    return _kt('None');
		}
		$aNames = array();
		foreach ($types as $oType) { 
		    $aNames[] = $oType->getName();
		}
		return implode(', ', $aNames);
	}

    // {{{ do_edit
    function do_edit() {
        $this->oPage->setBreadcrumbDetails(_kt("edit"));
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate =& $oTemplating->loadTemplate('ktcore/metadata/editFieldset');
        $oFieldset =& KTFieldset::get($_REQUEST['fFieldsetId']);
        
        $editFieldset = array();
        $editFieldset[] = new KTStringWidget(_kt('Name'), _kt('A human-readable name, used in add and edit forms.'), 'name',$oFieldset->getName(), $this->oPage, true);
        $editFieldset[] = new KTStringWidget(_kt('Namespace'), _kt('Every fieldset needs to have a system name (used internally by the document management system).  For fieldsets which you create, this is automatically created by the system, but for fieldsets created by plugins, this controls how the fieldset works.'), 'namespace', $oFieldset->getNamespace(), $this->oPage, true);
        $editFieldset[] = new KTTextWidget(_kt('Description'), _kt('A brief description of the information stored in this fieldset.'), 'description', $oFieldset->getDescription(), $this->oPage, true);                
        $editFieldset[] = new KTCheckboxWidget(_kt('Generic'), _kt('Whether this fieldset applies to all documents, or only those directly associated with it.  Note that changing this setting will cause the fieldset to be removed from document types which are related to it.'), 'generic', $oFieldset->getIsGeneric(), $this->oPage, false);        
        
        $createFields = array();
        $createFields[] = new KTStringWidget(_kt('Name'), _kt('A human-readable name, used in add and edit forms.'), 'name',null, $this->oPage, true);
        $createFields[] = new KTTextWidget(_kt('Description'), _kt('A brief description of the information stored in this field.'), 'description', null, $this->oPage, true);                
		$createFields[] = new KTCheckboxWidget(_kt('Required'), _kt('Are the values in this field required?'), 'is_required', false, $this->oPage, false);                
        
        // type is a little more complex.
        $vocab = array();
        if (!$oFieldset->getIsConditional()) {
           $vocab["normal"] = _kt('Normal');
        }
        $vocab['lookup'] = _kt('Lookup');
        $vocab['tree'] = _kt('Tree');
        $typeOptions = array("vocab" => $vocab);
        if (!$oFieldset->getIsConditional()) {
            $overallDescription = _kt('Fields may be of type "Normal", "Lookup", or "Tree". Normal fields are simple text entry fields. Lookups are drop-down controls populated with values by your chosen values. Tree fields provide a rich means of selecting values from tree-like information structures.');
        } else {
            $overallDescription = _kt('Since this is a conditional fieldset, fields may be of type "Lookup", or "Tree". Lookups are drop-down controls populated with values by your chosen values. Tree fields provide a rich means of selecting values from tree-like information structures.');        
        }
        
        $createFields[] =& new KTLookupWidget(_kt('Type'), $overallDescription, 
        'type', null, $this->oPage, true, null,  null, $typeOptions);
        
        
        $this->aBreadcrumbs[] = array(
            'url' => $_SERVER['PHP_SELF'],
            'query' => 'action=edit&fFieldsetId=' . $_REQUEST['fFieldsetId'],
            'name' => $oFieldset->getName()
        );
        $oTemplate->setData(array(
		    'context' => $this,
            'oFieldset' => $oFieldset,
            'edit_fieldset_fields' => $editFieldset,
            'create_field_fields' => $createFields,
        ));
        return $oTemplate;
    }
    // }}}

    // {{{ edit_object
    function do_editobject() {
		$aErrorOptions = array(
			'redirect_to' => array('main'),
		);
        $oFieldset =& $this->oValidator->validateFieldset($_REQUEST['fFieldsetId'], $aErrorOptions);
		$aErrorOptions = array(
			'redirect_to' => array('edit', sprintf('fFieldsetId=%d', $oFieldset->getId())),
		);
        $aErrorOptions['empty_message'] = _kt("No name was given for the fieldset");
        $aErrorOptions['duplicate_message'] = _kt("A fieldset with that name already exists");
        $aErrorOptions['rename'] = $oFieldset->getId();
        $sName = $this->oValidator->validateEntityName("KTFieldset", $_REQUEST['name'], $aErrorOptions);
        $aErrorOptions['message'] = sprintf(_kt("The field '%s' is a required field"), _kt("Namespace"));
        $sNamespace = $this->oValidator->validateString($_REQUEST['namespace'], $aErrorOptions);
        $aErrorOptions['message'] = sprintf(_kt("The field '%s' is a required field"), _kt("Description"));
        $sDescription = $this->oValidator->validateString($_REQUEST['description'], $aErrorOptions);

        $bGeneric = KTUtil::arrayGet($_REQUEST, 'generic', false);
        if ($bGeneric !== false) { $bGeneric = true; }
        
        $this->startTransaction();        
        
        if ($bGeneric != $oFieldset->getIsGeneric) {
            // delink it from all doctypes.
            $aTypes = $oFieldset->getAssociatedTypes();
            foreach ($aTypes as $oType) {
                $res = KTMetadataUtil::removeSetsFromDocumentType($oType, $oFieldset->getId());
                if (PEAR::isError($res)) {
                    $this->errorRedirectTo('edit', _kt('Could not save fieldset changes'), 'fFieldsetId=' . $oFieldset->getId());
                    exit(0);
                }
            }
        }

        $oFieldset->setName($sName);
        $oFieldset->setNamespace($sNamespace);
        $oFieldset->setDescription($sDescription);
        $oFieldset->setIsGeneric($bGeneric);
        $res = $oFieldset->update();
        if (PEAR::isError($res) || ($res === false)) {
            $this->errorRedirectTo('edit', _kt('Could not save fieldset changes'), 'fFieldsetId=' . $oFieldset->getId());
            exit(0);
        }
        $this->successRedirectTo('edit', _kt('Changes saved'), 'fFieldsetId=' . $oFieldset->getId());
        exit(0);
    }
    // }}}

    // {{{ do_new
    function do_new() {
		$aErrorOptions = array(
			'redirect_to' => array('main'),
		);
	
        $bIsGeneric = false;
        $bIsSystem = false;

        if (KTUtil::arrayGet($_REQUEST, 'generic')) {
            $bIsGeneric = true;
        }

		// basic validation
        $aErrorOptions['empty_message'] = _kt("No name was given for the fieldset");
        $aErrorOptions['duplicate_message'] = _kt("A fieldset with that name already exists");
        $sName = $this->oValidator->validateEntityName("KTFieldset", $_REQUEST['name'], $aErrorOptions);
			
		$sDescription = $this->oValidator->validateString(KTUtil::arrayGet($_REQUEST, 'description'), 
			KTUtil::meldOptions($aErrorOptions, array('message' => _kt("You must provide a description"))));
				
        $sNamespace = KTUtil::arrayGet($_REQUEST, 'namespace');
		
        if (empty($sNamespace)) {
            $sNamespace = KTUtil::nameToLocalNamespace('fieldsets', $sName);
        }
		
        $res = KTFieldset::createFromArray(array(
            'name' => $sName,
            'namespace' => $sNamespace,
            'description' => $sDescription,
            'mandatory' => false,
            'isconditional' => false,
            'isgeneric' => $bIsGeneric,
            'issystem' => false,
        ));
        if (PEAR::isError($res) || ($res === false)) {
            $this->errorRedirectToMain('Could not create fieldset');
            exit(0);
        }
        $this->successRedirectTo('edit', _kt('Fieldset created') . ': '.$sName, 'fFieldsetId=' . $res->getId());
        exit(0);
    }
    // }}}

    // {{{ do_newfield
    function do_newfield() {
        $aErrorOptions = array(
			'redirect_to' => array('main'),
		);	
        $oFieldset =& $this->oValidator->validateFieldset($_REQUEST['fFieldsetId']);
        $aErrorOptions = array(
			'redirect_to' => array('edit', sprintf('fFieldsetId=%d', $oFieldset->getId())),
		);
		
		$is_required = KTUtil::arrayGet($_REQUEST, 'is_required', false);
		if (empty($is_required)) { $is_required = false; }
        $is_lookup = false;
        $is_tree = false;
        if ($_REQUEST['type'] === "lookup") {
            $is_lookup = true;
        }
        if ($_REQUEST['type'] === "tree") {
            $is_lookup = true;
            $is_tree = true;
        }
		
        $aErrorOptions['condition'] = array('parent_fieldset' => $oFieldset->getId());
		$sName = $this->oValidator->validateEntityName("DocumentField", $_REQUEST['name'], $aErrorOptions);
        unset($aErrorOptions['condition']);
		
		$sDescription = $this->oValidator->validateString(KTUtil::arrayGet($_REQUEST, 'description'), 
			KTUtil::meldOptions($aErrorOptions, array('message' => _kt("You must provide a description"))));
		
		
        $oFieldset = KTFieldset::get($_REQUEST['fFieldsetId']);
        $oField =& DocumentField::createFromArray(array(
            'name' => $_REQUEST['name'],
            'datatype' => 'STRING',
	        'description' => $sDescription,
            'haslookup' => $is_lookup,
            'haslookuptree' => $is_tree,
            'parentfieldset' => $oFieldset->getId(),
			'ismandatory' => $is_required,
        ));
        if (PEAR::isError($res) || ($res === false)) {
            $this->errorRedirectTo('edit', _kt('Could not create field') . ': '.$_REQUEST['name'], 'fFieldsetId=' . $oFieldset->getId());
            exit(0);
        }
        if ($is_lookup) {
            $this->successRedirectTo('editField', _kt('Field created') . ': '.$_REQUEST['name'], 'fFieldsetId=' . $oFieldset->getId() . '&fFieldId=' . $oField->getId());
        } else {
            $this->successRedirectTo('edit', _kt('Field created') . ': ' . $_REQUEST['name'], 'fFieldsetId=' . $oFieldset->getId());
        }
        exit(0);
    }
    // }}}

    // {{{ do_editField
    function do_editField() {
        $this->oPage->setBreadcrumbDetails(_kt("Edit field"));
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate =& $oTemplating->loadTemplate('ktcore/metadata/editField');
        $oFieldset =& KTFieldset::get($_REQUEST['fFieldsetId']);
        $oField =& DocumentField::get($_REQUEST['fFieldId']);
        
        $this->aBreadcrumbs[] = array(
            'url' => $_SERVER['PHP_SELF'],
            'query' => 'action=edit&fFieldsetId=' . $_REQUEST['fFieldsetId'],
            'name' => $oFieldset->getName()
        );
        $this->aBreadcrumbs[] = array(
            'name' => $oField->getName()
        );
        $this->oPage->setBreadcrumbDetails(_kt('edit field'));
        
        $oTemplate->setData(array(
            'oFieldset' => $oFieldset,
            'oField' => $oField,
        ));
        return $oTemplate;
    }
    // }}}

    // {{{ do_editFieldObject
    function do_editFieldObject() {
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate =& $oTemplating->loadTemplate('ktcore/metadata/editField');
		
        $oFieldset =& KTFieldset::get($_REQUEST['fFieldsetId']);
        $oField =& DocumentField::get($_REQUEST['fFieldId']);

		$aErrorOptions = array(
			'redirect_to' => array('editField','fFieldsetId=' . $oFieldset->getId() . '&fFieldId=' . $oField->getId()),
		);	
		
		$sName = $this->oValidator->validateString(KTUtil::arrayGet($_REQUEST, 'name'), 
			KTUtil::meldOptions($aErrorOptions, array('message' => _kt("You must provide a name"))));

        $aErrorOptions['condition'] = array('parent_fieldset' => $oFieldset->getId());
        $aErrorOptions['rename'] = $oField->getId();
		$sName = $this->oValidator->validateEntityName("DocumentField", $_REQUEST['name'], $aErrorOptions);
        unset($aErrorOptions['condition']);
        unset($aErrorOptions['rename']);

		$sDescription = $this->oValidator->validateString(KTUtil::arrayGet($_REQUEST, 'description'), 
			KTUtil::meldOptions($aErrorOptions, array('message' => _kt("You must provide a description"))));

        $oField->setName($_REQUEST['name']);
        $oField->setDescription($sDescription);
		$is_required = KTUtil::arrayGet($_REQUEST, 'is_required', false);
		
		if (!$is_required) { $is_required = false; }
		else { $is_required = true; }
		
		$oField->setIsMandatory($is_required);
		
        $res = $oField->update();
        if (PEAR::isError($res) || ($res === false)) {
            $this->errorRedirectTo('editField', _kt('Could not save field changes'), 'fFieldsetId=' . $oFieldset->getId() . '&fFieldId=' . $oField->getId());
            exit(0);
        }
        $this->successRedirectTo('editField', _kt('Changes saved'), 'fFieldsetId=' . $oFieldset->getId() . '&fFieldId=' . $oField->getId());
        exit(0);
    }
    // }}}

    // {{{ do_addLookups
    function do_addLookups() {
        $oFieldset =& KTFieldset::get($_REQUEST['fFieldsetId']);
        $oField =& DocumentField::get($_REQUEST['fFieldId']);
        if (empty($_REQUEST['value'])) {
            $this->errorRedirectTo('editField', _kt('Empty lookup not added'), 'fFieldsetId=' . $oFieldset->getId() . '&fFieldId=' .  $oField->getId());
        }
        $oMetaData =& MetaData::createFromArray(array(
            'name' => $_REQUEST['value'],
            'docfieldid' => $oField->getId(),
        ));
        $this->successRedirectTo('editField', _kt('Lookup added'), 'fFieldsetId=' . $oFieldset->getId() . '&fFieldId=' .  $oField->getId());
        exit(0);
    }
    // }}}

    // {{{ do_metadataMultiAction
    function do_metadataMultiAction() {
        $subaction = array_keys(KTUtil::arrayGet($_REQUEST, 'submit', array()));
        $this->oValidator->notEmpty($subaction, array("message" => _kt("No action specified")));
        $subaction = $subaction[0];
        $method = null;
        if (method_exists($this, 'lookup_' . $subaction)) {
            $method = 'lookup_' . $subaction;
        }
        $this->oValidator->notEmpty($method, array("message" => _kt("Unknown action specified")));
        return $this->$method();
    }
    // }}}
    
    // {{{ lookup_remove
    function lookup_remove() {
        $oFieldset =& $this->oValidator->validateFieldset($_REQUEST['fFieldsetId']);
        $oField =& DocumentField::get($_REQUEST['fFieldId']);
        $aMetadata = KTUtil::arrayGet($_REQUEST, 'metadata');
        if (empty($aMetadata)) {
            $this->errorRedirectTo('editField', _kt('No lookups selected'), 'fFieldsetId=' . $oFieldset->getId() . '&fFieldId=' .  $oField->getId());
        }
        foreach ($_REQUEST['metadata'] as $iMetaDataId) {
            $oMetaData =& MetaData::get($iMetaDataId);
			if (PEAR::isError($oMetaData)) {
			    $this->errorRedirectTo('editField', _kt('Invalid lookup selected'), 'fFieldsetId=' . $oFieldset->getId() . '&fFieldId=' .  $oField->getId());
			}
            $oMetaData->delete();
        }
        $this->successRedirectTo('editField', _kt('Lookups removed'), 'fFieldsetId=' . $oFieldset->getId() . '&fFieldId=' .  $oField->getId());
        exit(0);
    }
    // }}}

    // {{{ lookup_disable
    function lookup_disable() {
        $oFieldset =& $this->oValidator->validateFieldset($_REQUEST['fFieldsetId']);
        $oField =& DocumentField::get($_REQUEST['fFieldId']);
        $aMetadata = KTUtil::arrayGet($_REQUEST, 'metadata');
        if (empty($aMetadata)) {
            $this->errorRedirectTo('editField', _kt('No lookups selected'), 'fFieldsetId=' . $oFieldset->getId() . '&fFieldId=' .  $oField->getId());
        }
        foreach ($_REQUEST['metadata'] as $iMetaDataId) {
            $oMetaData =& MetaData::get($iMetaDataId);
			if (PEAR::isError($oMetaData)) {
			    $this->errorRedirectTo('editField', _kt('Invalid lookup selected'), 'fFieldsetId=' . $oFieldset->getId() . '&fFieldId=' .  $oField->getId());
			}
            $oMetaData->setDisabled(true);
            $oMetaData->update();
        }
        $this->successRedirectTo('editField', _kt('Lookups disabled'), 'fFieldsetId=' . $oFieldset->getId() . '&fFieldId=' .  $oField->getId());
        exit(0);
    }
    // }}}

    // {{{ lookup_enable
    function lookup_enable() {
        $oFieldset =& $this->oValidator->validateFieldset($_REQUEST['fFieldsetId']);
        $oField =& DocumentField::get($_REQUEST['fFieldId']);
        $aMetadata = KTUtil::arrayGet($_REQUEST, 'metadata');
        if (empty($aMetadata)) {
            $this->errorRedirectTo('editField', _kt('No lookups selected'), 'fFieldsetId=' . $oFieldset->getId() . '&fFieldId=' .  $oField->getId());
        }
        foreach ($_REQUEST['metadata'] as $iMetaDataId) {
            $oMetaData =& MetaData::get($iMetaDataId);
			if (PEAR::isError($oMetadata)) {
				$this->errorRedirectTo('editField', _kt('Invalid lookup selected'), 'fFieldsetId=' . $oFieldset->getId() . '&fFieldId=' .  $oField->getId());
			}
            $oMetaData->setDisabled(false);
            $oMetaData->update();
        }
        $this->successRedirectTo('editField', _kt('Lookups enabled'), 'fFieldsetId=' . $oFieldset->getId() . '&fFieldId=' .  $oField->getId());
        exit(0);
    }
    // }}}

    // {{{ lookup_togglestickiness
    function lookup_togglestickiness() {
        $oFieldset =& $this->oValidator->validateFieldset($_REQUEST['fFieldsetId']);
        $oField =& DocumentField::get($_REQUEST['fFieldId']);
        $aMetadata = KTUtil::arrayGet($_REQUEST, 'metadata');
        if (empty($aMetadata)) {
            $this->errorRedirectTo('editField', _kt('No lookups selected'), 'fFieldsetId=' . $oFieldset->getId() . '&fFieldId=' .  $oField->getId());
        }
        foreach ($_REQUEST['metadata'] as $iMetaDataId) {
            $oMetaData =& MetaData::get($iMetaDataId);
			if (PEAR::isError($oMetaData)) {
				$this->errorRedirectTo('editField', _kt('Invalid lookups selected'), 'fFieldsetId=' . $oFieldset->getId() . '&fFieldId=' .  $oField->getId());
			}
            $bStuck = (boolean)$oMetaData->getIsStuck();
            $oMetaData->setIsStuck(!$bStuck);
            $oMetaData->update();
        }
        $this->successRedirectTo('editField', _kt('Lookup stickiness toggled'), 'fFieldsetId=' . $oFieldset->getId() . '&fFieldId=' .  $oField->getId());
        exit(0);
    }
    // }}}

    // {{{ do_becomeconditional
    function do_becomeconditional() {
        $oFieldset =& KTFieldset::get($_REQUEST['fFieldsetId']);
        $oFieldset->setIsConditional(true);
        $oFieldset->setIsComplete(false);
        $res = $oFieldset->update();
        if (PEAR::isError($res) || ($res === false)) {
            $this->errorRedirectTo('edit', _kt('Could not become conditional'), 'fFieldsetId=' . $oFieldset->getId());
            exit(0);
        }
        $this->successRedirectTo('edit', _kt('Became conditional'), 'fFieldsetId=' . $oFieldset->getId());
        exit(0);
    }
    // }}}

    // {{{ do_removeconditional
    function do_removeconditional() {
        $oFieldset =& KTFieldset::get($_REQUEST['fFieldsetId']);
        $oFieldset->setIsConditional(false);
        $oFieldset->setIsComplete(true);
		$oFieldset->setIsComplex(false);
		
		// also, clear the conditional types, etc.
		$iFieldsetId = KTUtil::getId($oFieldset);
		KTMetadataUtil::removeFieldOrdering($oFieldset);
		
		// need to do some per-field cleanup.
		$aFields = DocumentField::getByFieldset($oFieldset);
		if (!empty($aFields)) {
		    $aFieldIds = array();
			foreach ($aFields as $oField) { $aFieldIds[] = $oField->getId(); }
			
			// value instances
		    $sTable = KTUtil::getTableName('field_value_instances');
			$aQuery = array(
			    "DELETE FROM $sTable WHERE field_id IN (" . DBUtil::paramArray($aFieldIds) . ")",
				$aFieldIds,
			);
			$res = DBUtil::runQuery($aQuery);		
			//$this->addInfoMessage('value instances: ' . print_r($res, true));
			
			// behaviours
		    $sTable = KTUtil::getTableName('field_behaviours');
			$aQuery = array(
			    "DELETE FROM $sTable WHERE field_id IN (" . DBUtil::paramArray($aFieldIds) . ")",
				$aFieldIds,
			);
			$res = DBUtil::runQuery($aQuery);	
			//$this->addInfoMessage('behaviours: ' . print_r($res, true));
		}
        KTEntityUtil::clearAllCaches('KTFieldBehaviour');        
        KTEntityUtil::clearAllCaches('KTValueInstance');     		
        $res = $oFieldset->update();
        if (PEAR::isError($res) || ($res === false)) {
            $this->errorRedirectTo('edit', _kt('Could not stop being conditional'), 'fFieldsetId=' . $oFieldset->getId());
            exit(0);
        }
        $this->successRedirectTo('edit', _kt('No longer conditional'), 'fFieldsetId=' . $oFieldset->getId());
        exit(0);
    }
    // }}}

    // {{{ do_removeFields
    function do_removeFields() {
        $oFieldset =& KTFieldset::get($_REQUEST['fFieldsetId']);
        foreach ($_REQUEST['fields'] as $iFieldId) {
            $oField =& DocumentField::get($iFieldId);
            $oField->delete();
        }
        $this->successRedirectTo('edit', _kt('Fields removed'), 'fFieldsetId=' . $oFieldset->getId());
        exit(0);
    }
    // }}}

    // {{{ do_manageConditional
    function do_manageConditional () {
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate =& $oTemplating->loadTemplate('ktcore/metadata/conditional/manageConditional');
        $oFieldset =& KTFieldset::get($_REQUEST['fFieldsetId']);
        $iMasterFieldId = $oFieldset->getMasterFieldId();
        if (!empty($iMasterFieldId)) {
            $oMasterField =& DocumentField::get($iMasterFieldId);
            if (PEAR::isError($oMasterField)) {
                $oMasterField = null;
            }
        } else {
            $oMasterField = null;
        }
        $sTable = KTUtil::getTableName('field_orders');
        $aQuery = array(
            "SELECT parent_field_id, child_field_id FROM $sTable WHERE fieldset_id = ?",
            array($oFieldset->getId())
        );
        $aFieldOrders = DBUtil::getResultArray($aQuery);
        $aFields = $oFieldset->getFields();

        $aFreeFieldIds = array();
        foreach ($aFields as $oField) {
            $aFreeFieldIds[] = $oField->getId();
        }
        if ($oMasterField) {
            $aParentFieldIds = array($oMasterField->getId());
            foreach ($aFieldOrders as $aRow) {
                $aParentFieldIds[] = $aRow['child_field_id'];
            }
            $aParentFields = array();
            foreach (array_unique($aParentFieldIds) as $iId) {
                $aParentFields[] =& DocumentField::get($iId);
            }
            $aFreeFields = array();
            foreach ($aFreeFieldIds as $iId) {
                if (in_array($iId, $aParentFieldIds)) {
                    continue;
                }
                $aFreeFields[] =& DocumentField::get($iId);
            }
        }
        $res = KTMetadataUtil::checkConditionalFieldsetCompleteness($oFieldset);
        if (PEAR::isError($res)) {
            $sIncomplete = $res->getMessage();
        } else {
            $sIncomplete = null;
        }
        $this->aBreadcrumbs[] = array(
            'url' => $_SERVER['PHP_SELF'],
            'query' => 'action=edit&fFieldsetId=' . $_REQUEST['fFieldsetId'],
            'name' => $oFieldset->getName()
        );
        $this->aBreadcrumbs[] = array(
            'url' => $_SERVER['PHP_SELF'],
            'query' => 'action=manageConditional&fFieldsetId=' . $_REQUEST['fFieldsetId'],
            'name' => _kt('Manage conditional field'),
        );
        $oTemplate->setData(array(
            'oFieldset' => $oFieldset,
            'free_fields' => $aFreeFields,
            'parent_fields' => $aParentFields,
            'aFieldOrders' => $aFieldOrders,
            'oMasterField' => $oMasterField,
            'sIncomplete' => $sIncomplete,
        ));
        return $oTemplate;
    }
    // }}}

    // {{{ do_orderFields
    function do_orderFields() {
        $oFieldset =& KTFieldset::get($_REQUEST['fFieldsetId']);
        $aFreeFieldIds = $_REQUEST['fFreeFieldIds'];
        if (empty($aFreeFieldIds)) {
            $this->errorRedirectTo('manageConditional', 'No children fields selected', 'fFieldsetId=' . $oFieldset->getId());
        }
        $iParentFieldId = $_REQUEST['fParentFieldId'];
        if (in_array($aParentFieldId, $aFreeFieldIds)) {
            $this->errorRedirectTo('manageConditional', _kt('Field cannot be its own parent field'), 'fFieldsetId=' . $oFieldset->getId());
        }
        foreach ($aFreeFieldIds as $iChildFieldId) {
            $res = KTMetadataUtil::addFieldOrder($iParentFieldId, $iChildFieldId, $oFieldset);
            $this->oValidator->notError($res, array(
                'redirect_to' => array('manageConditional', 'fFieldsetId=' . $oFieldset->getId()),
                'message' => _kt('Error adding Fields'),
            ));
        }
        $this->successRedirectTo('manageConditional', _kt('Fields ordered'), 'fFieldsetId=' . $oFieldset->getId());
        exit(0);
    }
    // }}}

    // {{{ do_setMasterField
    function do_setMasterField() {
        $oFieldset =& $this->oValidator->validateFieldset($_REQUEST['fFieldsetId']);
        $oField =& $this->oValidator->validateField($_REQUEST['fFieldId']);

        $res = KTMetadataUtil::removeFieldOrdering($oFieldset);
        $oFieldset->setMasterFieldId($oField->getId());
        $res = $oFieldset->update();

        $this->oValidator->notError($res, array(
            'redirect_to' => array('manageConditional', 'fFieldsetId=' . $oFieldset->getId()),
            'message' => _kt('Error setting master field'),
        ));
        $this->successRedirectTo('manageConditional', _kt('Master field set'), 'fFieldsetId=' . $oFieldset->getId());
        exit(0);
    }
    // }}}

    // {{{ do_checkComplete
    /**
     * Checks whether the fieldset is complete, and if it is, sets it to
     * be complete in the database.  Otherwise, set it to not be
     * complete in the database (just in case), and set the error
     * messages as to why it isn't.
     */
    function do_checkComplete() {
        $oFieldset =& $this->oValidator->validateFieldset($_REQUEST['fFieldsetId']);
        $res = KTMetadataUtil::checkConditionalFieldsetCompleteness($oFieldset);
        if ($res === true) {
            $oFieldset->setIsComplete(true);
            $oFieldset->update();
            $this->successRedirectTo('manageConditional', _kt('Set to complete'), 'fFieldsetId=' . $oFieldset->getId());
        }
        $oFieldset->setIsComplete(false);
        $oFieldset->update();
        // Success, as we want to save the incompleteness to the
        // database...
        $this->successRedirectTo('manageConditional', _kt('Could not to complete'), 'fFieldsetId=' . $oFieldset->getId());
    }
    // }}}

    // {{{ do_changeToSimple
    function do_changeToSimple() {
        $this->startTransaction();
        $oFieldset =& $this->oValidator->validateFieldset($_REQUEST['fFieldsetId']);
        $oFieldset->setIsComplex(false);
        $res = $oFieldset->update();
        $this->oValidator->notError($res, array(
            'redirect_to' => array('manageConditional', 'fFieldsetId=' . $oFieldset->getId()),
            'message' => _kt('Error changing to simple'),
        ));
        
		$aFields = DocumentField::getByFieldset($oFieldset);
		if (!empty($aFields)) {
		    $aFieldIds = array();
			foreach ($aFields as $oField) { $aFieldIds[] = $oField->getId(); }
			
			// value instances
		    $sTable = KTUtil::getTableName('field_value_instances');
			$aQuery = array(
			    "DELETE FROM $sTable WHERE field_id IN (" . DBUtil::paramArray($aFieldIds) . ")",
				$aFieldIds,
			);
			$res = DBUtil::runQuery($aQuery);		
			//$this->addInfoMessage('value instances: ' . print_r($res, true));
			
			// behaviours
		    $sTable = KTUtil::getTableName('field_behaviours');
			$aQuery = array(
			    "DELETE FROM $sTable WHERE field_id IN (" . DBUtil::paramArray($aFieldIds) . ")",
				$aFieldIds,
			);
			$res = DBUtil::runQuery($aQuery);	
			//$this->addInfoMessage('behaviours: ' . print_r($res, true));
		}        
        $this->oValidator->notError($res, array(
            'redirect_to' => array('manageConditional', 'fFieldsetId=' . $oFieldset->getId()),
            'message' => _kt('Error changing to simple'),
        ));
        KTEntityUtil::clearAllCaches('KTFieldBehaviour');        
        KTEntityUtil::clearAllCaches('KTValueInstance');                
        $this->successRedirectTo('manageConditional', _kt('Changed to simple'), 'fFieldsetId=' . $oFieldset->getId());
    }
    // }}}

    // {{{ do_changeToComplex
    function do_changeToComplex() {
        $oFieldset =& $this->oValidator->validateFieldset($_REQUEST['fFieldsetId']);
        $oFieldset->setIsComplex(true);
        $res = $oFieldset->update();
        $this->oValidator->notError($res, array(
            'redirect_to' => array('manageConditional', 'fFieldsetId=' . $oFieldset->getId()),
            'message' => _kt('Error changing to complex'),
        ));
        $this->successRedirectTo('manageConditional', _kt('Changed to complex'), 'fFieldsetId=' . $oFieldset->getId());
    }
    // }}}

    // {{{ do_delete
    function do_delete() {
        $oFieldset =& $this->oValidator->validateFieldset($_REQUEST['fFieldsetId']);
        $res = $oFieldset->delete();
        $this->oValidator->notErrorFalse($res, array(
            'redirect_to' => array('main', ''),
            'message' => _kt('Could not delete fieldset'),
        ));
        $this->successRedirectToMain(_kt('Fieldset deleted'));
    }
    // }}}


// {{{ TREE
    // create and display the tree editing form.
    function do_editTree() {
        global $default;
        // extract.

	$iFieldsetId = KTUtil::arrayGet($_REQUEST, 'fFieldsetId', false);
	$iFieldId = KTUtil::arrayGet($_REQUEST, 'field_id', false);

        $oFieldset =& KTFieldset::get($iFieldsetId);
	if(PEAR::isError($oFieldset)) {
	    $this->errorRedirectTo('main', _kt('Unable to find fieldset'), sprintf('fFieldId=%d&fFieldsetId=%d', $iFieldsetId, $iFieldId));
	    exit(0);
	}

        $oField =& DocumentField::get($iFieldId);
	if(PEAR::isError($oField)) {
	    $this->errorRedirectTo('main', _kt('Unable to find field'), sprintf('fFieldId=%d&fFieldsetId=%d', $iFieldsetId, $iFieldId));
	    exit(0);
	}



	

        $this->aBreadcrumbs[] = array(
            'url' => $_SERVER['PHP_SELF'],
            'query' => 'action=edit&fFieldsetId=' . $iFieldsetId,
            'name' => $oFieldset->getName()
        );
        $this->aBreadcrumbs[] = array(
            'url' => $_SERVER['PHP_SELF'],
            'query' => 'action=editField&fFieldsetId=' . $iFieldsetId . '&fFieldId=' . $oField->getId(),
            'name' => $oField->getName()
        );
        $this->oPage->setBreadcrumbDetails(_kt('edit lookup tree'));

        $field_id = KTUtil::arrayGet($_REQUEST, 'field_id');
        $current_node = KTUtil::arrayGet($_REQUEST, 'current_node', 0);
        $subaction = KTUtil::arrayGet($_REQUEST, 'subaction');

        // validate
        if (empty($field_id)) { return $this->errorRedirectToMain(_kt("Must select a field to edit.")); }
        $oField =& DocumentField::get($field_id);
        if (PEAR::isError($oField)) { return $this->errorRedirectToMain(_kt("Invalid field.")); }

        $aErrorOptions = array(
            'redirect_to' => array('editTree', sprintf('field_id=%d', $field_id)),
        );

        // under here we do the subaction rendering.
        // we do this so we don't have to do _very_ strange things with multiple actions.
        //$default->log->debug("Subaction: " . $subaction);
        $fieldTree =& new MDTree();
        $fieldTree->buildForField($oField->getId());

        if ($subaction !== null) {
            $target = 'editTree';
            $msg = _kt('Changes saved.');
            if ($subaction === "addCategory") {
                $new_category = KTUtil::arrayGet($_REQUEST, 'category_name');
                if (empty($new_category)) { 
		    return $this->errorRedirectTo("editTree", _kt("Must enter a name for the new category."), array("field_id" => $field_id, "fFieldsetId" => $iFieldsetId)); 
		} else { 
		    $this->subact_addCategory($field_id, $current_node, $new_category, $fieldTree);
		}
                $msg = _kt('Category added'). ': ' . $new_category;
            }
            if ($subaction === "deleteCategory") {
                $this->subact_deleteCategory($fieldTree, $current_node);
                $current_node = 0;      // clear out, and don't try and render the newly deleted category.
                $msg = _kt('Category removed.');
            }
            if ($subaction === "linkKeywords") {
                $keywords = KTUtil::arrayGet($_REQUEST, 'keywordsToAdd');
                $aErrorOptions['message'] = _kt("No keywords selected");
                $this->oValidator->notEmpty($keywords, $aErrorOptions);
                $this->subact_linkKeywords($fieldTree, $current_node, $keywords);
                $current_node = 0;      // clear out, and don't try and render the newly deleted category.
                $msg = _kt('Keywords added to category.');
            }
            if ($subaction === "unlinkKeyword") {
                $keyword = KTUtil::arrayGet($_REQUEST, 'keyword_id');
                $this->subact_unlinkKeyword($fieldTree, $keyword);
                $msg = _kt('Keyword moved to base of tree.');
            }
            // now redirect
            $query = sprintf('field_id=%d&fFieldsetId=%d', $field_id, $iFieldsetId);
            return $this->successRedirectTo($target, $msg, $query);
        }
        if ($fieldTree->root === null) {
            return $this->errorRedirectToMain(_kt("Error building tree. Is this a valid tree-lookup field?"));
        }

        // FIXME extract this from MDTree (helper method?)
        $free_metadata = MetaData::getList('document_field_id = '.$oField->getId().' AND (treeorg_parent = 0 OR treeorg_parent IS NULL) AND (disabled = 0)');

        // render edit template.
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate("ktcore/edit_lookuptrees");
        $renderedTree = $this->_evilTreeRenderer($fieldTree);

        $this->oPage->setTitle(_kt('Edit Lookup Tree'));

        //$this->oPage->requireJSResource('thirdparty/js/MochiKit/Base.js');
		
		if ($current_node == 0) { $category_name = 'Root'; }
		else {
			$oNode = MDTreeNode::get($current_node);
			$category_name = $oNode->getName();
		}
		
        $aTemplateData = array(
            "field" => $oField,
            "oFieldset" => $oFieldset,
            "tree" => $fieldTree,
            "renderedTree" => $renderedTree,
            "currentNode" => $current_node,
			'category_name' => $category_name,
            "freechildren" => $free_metadata,
            "context" => $this,
        );
        return $oTemplate->render($aTemplateData);
    }

    function subact_addCategory($field_id, $current_node, $new_category, &$constructedTree) {
        $newCategory = MDTreeNode::createFromArray(array (
             "iFieldId" => $field_id,
             "sName" => $new_category,
             "iParentNode" => $current_node,
        ));
        if (PEAR::isError($newCategory))
        {
            return false;
        }
        $constructedTree->addNode($newCategory);
        return true;
    }

    function subact_deleteCategory(&$constructedTree, $current_node) {
        $constructedTree->deleteNode($current_node);
        return true;
    }

    function subact_unlinkKeyword(&$constructedTree, $keyword) {
        $oKW = MetaData::get($keyword);
		if (PEAR::isError($oKW)) {
		    return true;
		}
        $constructedTree->reparentKeyword($oKW->getId(), 0);
        return true;
    }


    function subact_linkKeywords(&$constructedTree, $current_node, $keywords) {
        foreach ($keywords as $md_id)
        {
            $constructedTree->reparentKeyword($md_id, $current_node);
        }
        return true;
    }

    /* ----------------------- EVIL HACK --------------------------
     *
     *  This whole thing needs to replaced, as soon as I work out how
     *  to non-sucking Smarty recursion.
     */

    function _evilTreeRecursion($subnode, $treeToRender)
    {
	// deliver us from evil....
	$iFieldId = $treeToRender->field_id;
	$oField = DocumentField::get($iFieldId);
	$iFieldsetId = $oField->getParentFieldsetId();

        $treeStr = "<ul>";
        foreach ($treeToRender->contents[$subnode] as $subnode_id => $subnode_val)
        {
            if ($subnode_id !== "leaves") {
                $treeStr .= '<li class="treenode active"><a class="pathnode inactive"  onclick="toggleElementClass(\'active\', this.parentNode); toggleElementClass(\'inactive\', this.parentNode);">' . $treeToRender->mapnodes[$subnode_val]->getName() . '</a>';
                $treeStr .= $this->_evilActionHelper($iFieldsetId, $iFieldId, false, $subnode_val);
                $treeStr .= $this->_evilTreeRecursion($subnode_val, $treeToRender);
                $treeStr .= '</li>';
            }
            else
            {
                foreach ($subnode_val as $leaf)
                {
                    $treeStr .= '<li class="leafnode">' . $treeToRender->lookups[$leaf]->getName();
                    $treeStr .= $this->_evilActionHelper($iFieldsetId, $iFieldId, true, $leaf);
                    $treeStr .=  '</li>';            }
                }
        }
        $treeStr .= '</ul>';
        return $treeStr;

    }

    // I can't seem to do recursion in smarty, and recursive templates seems a bad solution.
    // Come up with a better way to do this (? NBM)
    function _evilTreeRenderer($treeToRender) {
        //global $default;

        $treeStr = "<!-- this is rendered with an unholy hack. sorry. -->";
        $stack = array();
        $exitstack = array();

	// deliver us from evil....
	$iFieldId = $treeToRender->field_id;
	$oField = DocumentField::get($iFieldId);
	$iFieldsetId = $oField->getParentFieldsetId();
	$sBaseQS = sprintf('field_id=%d&fFieldsetId=%d&', $iFieldId, $iFieldsetId);

        // since the root is virtual, we need to fake it here.
        // the inner section is generised.
        $treeStr .= '<ul class="kt_treenodes"><li class="treenode active"><a class="pathnode"  onclick="toggleElementClass(\'active\', this.parentNode);toggleElementClass(\'inactive\', this.parentNode);">Root</a>';
        $treeStr .= ' (<a href="' . KTUtil::addQueryStringSelf($sBaseQS . 'action=editTree&current_node=0') . '">edit</a>)';
        $treeStr .= '<ul>';

        //$default->log->debug("EVILRENDER: " . print_r($treeToRender, true));
        foreach ($treeToRender->getRoot() as $node_id => $subtree_nodes)
        {
            //$default->log->debug("EVILRENDER: ".$node_id." => ".$subtree_nodes." (".($node_id === "leaves").")");
            // leaves are handled differently.
            if ($node_id !== "leaves") {
                // $default->log->debug("EVILRENDER: " . print_r($subtree_nodes, true));
                $treeStr .= '<li class="treenode active"><a class="pathnode" onclick="toggleElementClass(\'active\', this.parentNode);toggleElementClass(\'inactive\', this.parentNode);">' . $treeToRender->mapnodes[$subtree_nodes]->getName() . '</a>';
                $treeStr .= $this->_evilActionHelper($iFieldsetId, $iFieldId, false, $subtree_nodes);
                $treeStr .= $this->_evilTreeRecursion($subtree_nodes, $treeToRender);
                $treeStr .= '</li>';
            }
            else
            {
                foreach ($subtree_nodes as $leaf)
                {
                    $treeStr .= '<li class="leafnode">' . $treeToRender->lookups[$leaf]->getName();
                    $treeStr .= $this->_evilActionHelper($iFieldsetId, $iFieldId, true, $leaf);
                    $treeStr .=  '</li>';
                }
            }
        }
        $treeStr .= '</ul></li>';
        $treeStr .= '</ul>';

        return $treeStr;
    }

    // BS: don't hate me.
    // BD: sorry. I hate you.
    
    function _evilActionHelper($iFieldsetId, $iFieldId, $bIsKeyword, $current_node) {
	$sBaseQS = sprintf('fFieldsetId=%d&field_id=%d&', $iFieldsetId, $iFieldId);

        $actionStr = " (";
        if ($bIsKeyword === true) {
           $actionStr .= '<a href="' . KTUtil::addQueryStringSelf($sBaseQS . 'action=editTree&keyword_id='.$current_node.'&subaction=unlinkKeyword') . '">unlink</a>';
        }
        else
        {
           $actionStr .= '<a href="' . KTUtil::addQueryStringSelf($sBaseQS . 'action=editTree&current_node=' . $current_node) .'">attach keywords</a> ';
           $actionStr .= '| <a href="' . KTUtil::addQueryStringSelf($sBaseQS . 'action=editTree&current_node='.$current_node.'&subaction=deleteCategory') . '">delete</a>';
        }
        $actionStr .= ")";
        return $actionStr;
    }
	
	
    function do_viewOverview() {
        $fieldset_id = KTUtil::arrayGet($_REQUEST, "fieldset_id");
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate("ktcore/metadata/conditional/conditional_overview");

        
        $oFieldset =& KTFieldset::get($fieldset_id);
        $aFields =& $oFieldset->getFields();
        
        $this->aBreadcrumbs[] = array(
            'url' => $_SERVER['PHP_SELF'],
            'query' => 'action=edit&fFieldsetId=' . $fieldset_id,
            'name' => $oFieldset->getName()
        );
        $this->aBreadcrumbs[] = array(
            'url' => $_SERVER['PHP_SELF'],
            'query' => 'action=manageConditional&fFieldsetId=' . $_REQUEST['fieldset_id'],
            'name' => _kt('Manage conditional field'),
        );  
        $this->aBreadcrumbs[] = array(
            'url' => $_SERVER['PHP_SELF'],
            'query' => 'action=viewOverview&fieldset_id=' . $_REQUEST['fieldset_id'],
            'name' => _kt('Overview'),
        );        
        
        $aBehaviours = array();
		foreach ($aFields as $oField) {
		    $aOpts = KTFieldBehaviour::getByField($oField);
		    $aBehaviours = kt_array_merge($aBehaviours, $aOpts);
		}
        
        $aTemplateData = array(
            "context" => &$this,
            "fieldset_id" => $fieldset_id,
            "aFields" => $aFields,
	    "behaviours" => $aBehaviours,
            "iMasterFieldId" => $oFieldset->getMasterFieldId(),
        );
        return $oTemplate->render($aTemplateData);
    }
	
	function getSetsForBehaviour($oBehaviour, $fieldset_id) {
	    $oFieldset = KTFieldset::get($fieldset_id);
		if (is_null($oBehaviour)) {
		    $fid = $oFieldset->getMasterFieldId();
			$aQuery = array(
			    sprintf('SELECT df.name as field_name, ml.name as lookup_name, fb.id as behaviour_id, fb.name as behaviour_name FROM 
				    %s as fvi
					LEFT JOIN %s as fb ON (fvi.behaviour_id = fb.id) 
					LEFT JOIN %s AS df ON (fvi.field_id = df.id) 
					LEFT JOIN metadata_lookup AS ml ON (fvi.field_value_id = ml.id) 
					WHERE fvi.field_id = ?
					ORDER BY df.name ASC, ml.name ASC', 
					KTUtil::getTableName('field_value_instances'),
					KTUtil::getTableName('field_behaviours'),
					KTUtil::getTableName('document_fields'),
					KTUtil::getTableName('metadata')),
				array($fid),
			);
			$res = DBUtil::getResultArray($aQuery);
			return $res;
		} else {
		    $bid = $oBehaviour->getId();
			$aQuery = array(
			    sprintf('SELECT df.name as field_name, ml.name as lookup_name, fb.id as behaviour_id, fb.name as behaviour_name FROM 
			        %s AS fbo 
					LEFT JOIN %s as fvi ON (fbo.instance_id = fvi.id) 
					LEFT JOIN %s as fb ON (fvi.behaviour_id = fb.id) 
					LEFT JOIN %s AS df ON (fvi.field_id = df.id) 
					LEFT JOIN metadata_lookup AS ml ON (fvi.field_value_id = ml.id) 
					WHERE fbo.behaviour_id = ?
					ORDER BY df.name ASC, ml.name ASC', 
					KTUtil::getTableName('field_behaviour_options'),
					KTUtil::getTableName('field_value_instances'),
					KTUtil::getTableName('field_behaviours'),
					KTUtil::getTableName('document_fields'),
					KTUtil::getTableName('metadata')),
				array($bid),
			);
			
			$res = DBUtil::getResultArray($aQuery);
			return $res;
		}

		return $aNextFieldValues;
	}
	
	function do_renameBehaviours() {
        $fieldset_id = KTUtil::arrayGet($_REQUEST, "fieldset_id");
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate("ktcore/metadata/conditional/conditional_rename_behaviours");

        
        $oFieldset =& KTFieldset::get($fieldset_id);
        $aFields =& $oFieldset->getFields();
        
        $aBehaviours = array();
		foreach ($aFields as $oField) {
		    $aOpts = KTFieldBehaviour::getByField($oField);
		    $aBehaviours = kt_array_merge($aBehaviours, $aOpts);
		}
        
        $aTemplateData = array(
            "context" => &$this,
            "fieldset_id" => $fieldset_id,
			"behaviours" => $aBehaviours,
        );
        return $oTemplate->render($aTemplateData);
    }
	
	function do_finalRename() {
        $fieldset_id = KTUtil::arrayGet($_REQUEST, "fieldset_id");
	    $aRenamed = (array) KTUtil::arrayGet($_REQUEST, "renamed");
				
		$this->startTransaction(); 
		
		foreach ($aRenamed as $bid => $new_name) {
			$oBehaviour = KTFieldBehaviour::get($bid);
			if (PEAR::isError($oBehaviour)) { continue; } // skip it...
			$oBehaviour->setName(trim($new_name));
			$res = $oBehaviour->update();
			if (PEAR::isError($res)) { 
			    $this->errorRedirectToMain(_kt('Failed to change name of behaviour.'), sprintf('action=edit&fFieldsetId=%s',$fieldset_id)); 
			}
		}
		
		$this->successRedirectToMain(_kt('Names changed.'), sprintf('action=edit&fFieldsetId=%s', $fieldset_id));
	}
	

	function haveConditional() {
		if (is_null($this->bHaveConditional)) {
			$this->bHaveConditional = KTPluginUtil::pluginIsActive('ktextra.conditionalmetadata.plugin');
		}
		
		return $this->bHaveConditional; 
	}
	
	function getIncomplete($oFieldset) {
        $res = KTMetadataUtil::checkConditionalFieldsetCompleteness($oFieldset);
        if (PEAR::isError($res)) {
            $sIncomplete = $res->getMessage();
        } else {
            $sIncomplete = null;
        }	
        return $sIncomplete;
        
	}

// }}}
}

?>
