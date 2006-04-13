<?php

/*
 * Document Link Type management
 *
 * Copyright (c) 2006 Jam Warehouse http://www.jamwarehouse.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; using version 2 of the License.
 *
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
 * @version $Revision$
 * @author Brad Shuttleworth <brad@jamwarehouse.com>, Jam Warehouse (Pty) Ltd, South Africa
 * @package documentmanagement
 */

require_once(KT_LIB_DIR . '/dispatcher.inc.php');
require_once(KT_LIB_DIR . '/templating/kt3template.inc.php');

require_once(KT_LIB_DIR . '/widgets/fieldWidgets.php');
require_once(KT_LIB_DIR . "/roles/Role.inc");

class RoleAdminDispatcher extends KTAdminDispatcher {
    function check() {
        return true;
    }

    function do_main() {
        $this->aBreadcrumbs[] = array('url' => $_SERVER['PHP_SELF'], 'name' => _kt('Role Management'));
        $this->oPage->setTitle(_kt('Role Management'));
        
        $edit_fields = array();
        $role_id = KTUtil::arrayGet($_REQUEST, 'role_id', null);
        if (is_null($role_id)) {
            $oRole = null; // handle broken case of role == -1
        } else {
            $oRole = Role::get($role_id);
        }
        
        if (PEAR::isError($oRole) || ($oRole == false)) { $for_edit = false; }
        else {
            $for_edit = true;
            $edit_fields[] = new KTStringWidget(_kt('Name'), _kt('A short, human-readable name for the role.'), 'name', $oRole->getName(), $this->oPage, true);        
        }
        
        $aRoles =& Role::getList('id > 0');
        
        $add_fields = array();
        $add_fields[] = new KTStringWidget(_kt('Name'), _kt('A short, human-readable name for the role.'), 'name', null, $this->oPage, true);        
        
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate('ktcore/principals/roleadmin');       
        $oTemplate->setData(array(
            "context" => $this,
            "add_fields" => $add_fields,
            "for_edit" => $for_edit,
            'edit_role' => $oRole,
            'edit_fields' => $edit_fields,            
            'roles' => $aRoles,
        ));
        return $oTemplate;
    }
    
    function do_createRole() {
        $name = KTUtil::arrayGet($_REQUEST, 'name', null);
        if ($name === null) {
            $this->errorRedirectToMain(_kt('Please give the role a name.'));
        }
        
        $this->startTransaction();
        $oRole = Role::createFromArray(array('name' => $name));        
        
        if (PEAR::isError($oRole) || ($oRole == false)) {
            $this->errorRedirectToMain(_kt('Unable to create role.'));
        }
        
        $this->successRedirectToMain(sprintf(_kt('Role "%s" created.'), $name));
    }

    function do_updateRole() {
        $role_id = KTUtil::arrayGet($_REQUEST, 'role_id');
        $oRole = Role::get($role_id);
        if (PEAR::isError($oRole) || ($oRole == false)) {
            $this->errorRedirectToMain(_kt('Please select a valid role first.'));
        }
        
        $name = KTUtil::arrayGet($_REQUEST, 'name', null);
        if ($name === null) {
            $this->errorRedirectToMain(_kt('Please give the role a name.'));
        }
        
        $this->startTransaction();
        $oRole->setName($name);
        $res = $oRole->update();
        if (PEAR::isError($res) || ($res == false)) {
            $this->errorRedirectToMain(_kt('Unable to update role.'));
        }
        
        $this->successRedirectToMain(sprintf(_kt('Role "%s" updated.'), $name));
    }

    function do_deleteRole() {
        $role_id = KTUtil::arrayGet($_REQUEST, 'role_id');
        $oRole = Role::get($role_id);
        if (PEAR::isError($oRole) || ($oRole == false)) {
            $this->errorRedirectToMain(_kt('Please select a valid role first.'));
        }
        $name = $oRole->getName();
        
        $this->startTransaction();  
        $res = $oRole->delete();
        if (PEAR::isError($res) || ($res == false)) { 
            $this->errorRedirectToMain(_kt('Unable to delete the role.') . '  ' . _kt('Possible cause') . ': ' . $_SESSION['errorMessage']); 
        }
            
        $this->successRedirectToMain(sprintf(_kt('Role "%s" deleted. '), $name));
    }

}

