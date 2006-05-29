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

// main library routines and defaults
require_once("config/dmsDefaults.php");
require_once(KT_LIB_DIR . "/unitmanagement/Unit.inc");

require_once(KT_LIB_DIR . "/templating/templating.inc.php");
require_once(KT_LIB_DIR . "/templating/kt3template.inc.php");
require_once(KT_LIB_DIR . "/dispatcher.inc.php");

require_once(KT_LIB_DIR . '/widgets/fieldWidgets.php');

class PreferencesDispatcher extends KTStandardDispatcher {
    var $sSection = 'preferences';

    function check() {
	$oConfig =& KTConfig::getSingleton();
	if ($this->oUser->getId() == -2 || 
	    ($oConfig->get('user_prefs/restrictPreferences', false) && !Permission::userIsSystemAdministrator($this->oUser->getId()))) { 
	    return false; 
	}	
	
	return parent::check();
    }

    function PreferencesDispatcher() {
        $this->aBreadcrumbs = array(
            array('action' => 'preferences', 'name' => _kt('Preferences')),
        );
        return parent::KTStandardDispatcher();
    }

    function do_main() {
	$this->oPage->setBreadcrumbDetails(_kt("Your Preferences"));
	$this->oPage->title = _kt("Dashboard");
		
		
	$oUser =& $this->oUser;
		
	$aOptions = array('autocomplete' => false);
		
	$edit_fields = array();
        $edit_fields[] =  new KTStringWidget(_kt('Name'), _kt('Your full name.  This is shown in reports and listings.  e.g. <strong>John Smith</strong>'), 'name', $oUser->getName(), $this->oPage, true, null, null, $aOptions);        
        $edit_fields[] =  new KTStringWidget(_kt('Email Address'), _kt('Your email address.  Notifications and alerts are mailed to this address if <strong>email notifications</strong> is set below. e.g. <strong>jsmith@acme.com</strong>'), 'email_address', $oUser->getEmail(), $this->oPage, false, null, null, $aOptions);        
        $edit_fields[] =  new KTCheckboxWidget(_kt('Email Notifications'), _kt('If this is specified then the you will receive certain notifications.  If it is not set, then you will only see notifications on the <strong>Dashboard</strong>'), 'email_notifications', $oUser->getEmailNotification(), $this->oPage, false, null, null, $aOptions);        
        $edit_fields[] =  new KTStringWidget(_kt('Mobile Number'), _kt('Your mobile phone number.  e.g. <strong>+27 99 999 9999</strong>'), 'mobile_number', $oUser->getMobile(), $this->oPage, false, null, null, $aOptions);        
		
		$oTemplating =& KTTemplating::getSingleton();
		$oTemplate = $oTemplating->loadTemplate("ktcore/principals/preferences");
        $iSourceId = $oUser->getAuthenticationSourceId();
        $bChangePassword = true;
        if ($iSourceId) {
            $bChangePassword = false;
        }
		$aTemplateData = array(
              "context" => $this,
			  'edit_fields' => $edit_fields,
              "show_password" => $bChangePassword,
		);
		return $oTemplate->render($aTemplateData);    
    }

    function do_setPassword() {
		$this->oPage->setBreadcrumbDetails(_kt("Your Password"));
		$this->oPage->title = _kt("Dashboard");
		
		
		$oUser =& $this->oUser;
		
		$aOptions = array('autocomplete' => false);
		
		$edit_fields = array();
        $edit_fields[] =  new KTPasswordWidget(_kt('Password'), _kt('Specify your new password.'), 'password', null, $this->oPage, true, null, null, $aOptions);        
        $edit_fields[] =  new KTPasswordWidget(_kt('Confirm Password'), _kt('Confirm the password specified above.'), 'confirm_password', null, $this->oPage, true, null, null, $aOptions);        
		
	
		$oTemplating =& KTTemplating::getSingleton();
		$oTemplate = $oTemplating->loadTemplate("ktcore/principals/password");
		$aTemplateData = array(
              "context" => $this,
			  'edit_fields' => $edit_fields,
		);
		return $oTemplate->render($aTemplateData);    
    }



    function do_updatePassword() {
        
        $password = KTUtil::arrayGet($_REQUEST, 'password');
        $confirm_password = KTUtil::arrayGet($_REQUEST, 'confirm_password');        
        
        if (empty($password)) { 
            $this->errorRedirectTo("setPassword", _kt("You must specify a password."));
        } else if ($password !== $confirm_password) {
            $this->errorRedirectTo("setPassword", _kt("The passwords you specified do not match."));
        }
		
		$KTConfig =& KTConfig::getSingleton();
		$minLength = ((int) $KTConfig->get('user_prefs/passwordLength', 6));
		
		if (strlen($password) < $minLength) {
            $this->errorRedirectTo("setPassword", sprintf(_kt("Your password is too short - passwords must be at least %d characters long."), $minLength));		
		}
		
        // FIXME more validation would be useful.
        // validated and ready..
        $this->startTransaction();
        
        $oUser =& $this->oUser;
        
        
        // FIXME this almost certainly has side-effects.  do we _really_ want 
        $oUser->setPassword(md5($password)); // 
        
        $res = $oUser->update(); 
        //$res = $oUser->doLimitedUpdate(); // ignores a fix blacklist of items.
        
        
        if (PEAR::isError($res) || ($res == false)) {
            $this->errorRedirectoToMain(_kt('Failed to update user.'));
        }
        
        $this->commitTransaction();
        $this->successRedirectToMain(_kt('Your password has been changed.'));
        
    }


    function do_updatePreferences() {
        $aErrorOptions = array(
            'redirect_to' => array('main'),
        );
	
        $oUser =& $this->oUser;
        
        $name = $this->oValidator->validateString(KTUtil::arrayGet($_REQUEST, 'name'),
												  KTUtil::meldOptions($aErrorOptions, array('message' => _kt('You must specify your name.'))));
        
        $email_address = KTUtil::arrayGet($_REQUEST, 'email_address');
        if(strlen(trim($email_address))) {
            $email_address = $this->oValidator->validateEmailAddress($email_address, 
                    KTUtil::meldOptions($aErrorOptions, array('message') => _kt('Invalid email address.')));
        }                                          }
		
        $email_notifications = KTUtil::arrayGet($_REQUEST, 'email_notifications', false);
        if ($email_notifications !== false) $email_notifications = true;
        $mobile_number = KTUtil::arrayGet($_REQUEST, 'mobile_number');
        
        
        $this->startTransaction();
        
        $oUser->setName($name);
        $oUser->setEmail($email_address);
        $oUser->setEmailNotification($email_notifications);
        $oUser->setMobile($mobile_number);
        
        
        // old system used the very evil store.php.
        // here we need to _force_ a limited update of the object, via a db statement.
        //
        // $res = $oUser->update(); 
        $res = $oUser->doLimitedUpdate(); // ignores a fix blacklist of items.
        
        if (PEAR::isError($res) || ($res == false)) {
            $this->errorRedirectoToMain(_kt('Failed to update your details.'));
        }
        
        $this->commitTransaction();
        $this->successRedirectToMain(_kt('Your details have been updated.'));
        
    }


}

$oDispatcher = new PreferencesDispatcher();
$oDispatcher->dispatch();

?>
