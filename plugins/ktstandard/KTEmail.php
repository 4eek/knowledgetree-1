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

require_once(KT_LIB_DIR . '/actions/actionregistry.inc.php');

require_once(KT_LIB_DIR . "/email/Email.inc");
require_once(KT_LIB_DIR . "/users/User.inc");
require_once(KT_LIB_DIR . "/groups/Group.inc");
require_once(KT_LIB_DIR . "/documentmanagement/DocumentTransaction.inc");
require_once(KT_LIB_DIR . "/documentmanagement/Document.inc");

/**
 * Sends emails to the selected groups
 */
function sendGroupEmails($aGroupIDs, $oDocument, $sComment = "", $bAttachDocument, &$aEmailErrors) {
	global $default;
	
    // loop through groups
    for ($i=0; $i<count($aGroupIDs); $i++) {
    	// validate the group id
    	if ($aGroupIDs[$i] > 0) {
		    $oDestGroup = Group::get($aGroupIDs[$i]);
		    $default->log->info("sendingEmail to group " . $oDestGroup->getName());
		    // for each group, retrieve all the users
		    $aUsers = $oDestGroup->getUsers();
		    // FIXME: this should send one email with multiple To: users
		    for ($j=0; $j<count($aUsers); $j++) {
	    		$default->log->info("sendingEmail to group-member " . $aUsers[$j]->getName() . " with email " . $aUsers[$j]->getEmail());	    	
			    // the user has an email address and has email notification enabled
				if (strlen($aUsers[$j]->getEmail())>0 && $aUsers[$j]->getEmailNotification()) {
					//if the to address is valid, send the mail
					if (validateEmailAddress($aUsers[$j]->getEmail())) {	    
						sendEmail($aUsers[$j]->getEmail(), $aUsers[$j]->getName(), $oDocument->getID(), $oDocument->getName(), $sComment, $bAttachDocument, $aEmailErrors);
					} else {
						$default->log->error("email validation failed for " . $aUsers[$j]->getEmail());
					}
				} else {
				$default->log->info("either " . $aUsers[$j]->getUserName() . " has no email address, or notification is not enabled");				
				}
		    }
    	} else {
    		$default->log->info("filtered group id=" . $aGroupIDs[$i]);
    	}
    }
}

/**
 * Sends emails to the selected users
 */
function sendUserEmails($aUserIDs, $oDocument, $sComment = "", $bAttachDocument, &$aEmailErrors) {
	global $default;
	
    // loop through users
    for ($i=0; $i<count($aUserIDs); $i++) {
    	if ($aUserIDs[$i] > 0) {
		    $oDestUser = User::get($aUserIDs[$i]);
	    	$default->log->info("sendingEmail to user " . $oDestUser->getName() . " with email " . $oDestUser->getEmail());	    
		    // the user has an email address and has email notification enabled
			if (strlen($oDestUser->getEmail())>0 && $oDestUser->getEmailNotification()) {
				//if the to address is valid, send the mail
				if (validateEmailAddress($oDestUser->getEmail())) {	    
					sendEmail($oDestUser->getEmail(), $oDestUser->getName(), $oDocument->getID(), $oDocument->getName(), $sComment, $bAttachDocument, $aEmailErrors);
				}
			} else {
				$default->log->info("either " . $oDestUser->getUserName() . " has no email address, or notification is not enabled");
			}
    	} else {
    		$default->log->info("filtered user id=" . $aUserIDs[$i]);
    	}			
    }  	
}

/**
 * Sends emails to the manually entered email addresses
 */
function sendManualEmails($aEmailAddresses, $oDocument, $sComment = "", $bAttachDocument, &$aEmailErrors) {
	global $default;
	
    // loop through users
    foreach ($aEmailAddresses as $sEmailAddress) {
        $default->log->info("sendingEmail to address " .  $sEmailAddress);
        if (validateEmailAddress($sEmailAddress)) {	    
            sendEmail($sEmailAddress, $sEmailAddress, $oDocument->getID(), $oDocument->getName(), $sComment, $bAttachDocument, $aEmailErrors);
        }
    }  	
}

/**
 * Constructs the email message text and sends the message
 */
function sendEmail($sDestEmailAddress, $sDestUserName, $iDocumentID, $sDocumentName, $sComment, $bAttachDocument = false, &$aEmailErrors) {
    if ($bAttachDocument !== true) {
        return sendEmailHyperlink($sDestEmailAddress, $sDestUserName, $iDocumentID, $sDocumentName, $sComment, $aEmailErrors);
    } else {
        return sendEmailDocument($sDestEmailAddress, $sDestUserName, $iDocumentID, $sDocumentName, $sComment, $aEmailErrors);
    }
}

function sendEmailDocument($sDestEmailAddress, $sDestUserName, $iDocumentID, $sDocumentName, $sComment, &$aEmailErrors) {
    global $default;
    $oSendingUser = User::get($_SESSION["userID"]);

    $sMessage = 'Your colleague, ' . $oSendingUser->getName() . ', wishes you to view the attached document entitled "' .  $sDocumentName . '".';
    $sMessage .= "\n\n";
	if (strlen($sComment) > 0) {
		$sMessage .= "<br><br>Comments:<br>$sComment";
	}
    $sTitle = "Document: " . $sDocumentName . " from " .  $oSendingUser->getName();

    $sEmail = null;
    $sEmailFrom = null;
    $oConfig =& KTConfig::getSingleton();
    if (!$oConfig->get('email/sendAsSystem')) {
        $sEmail = $oSendingUser->getEmail();
        $sEmailFrom = $oSendingUser->getName();
    }
    $oEmail = new Email($sEmail, $sEmailFrom);
    $oDocument = Document::get($iDocumentID);

    // Request a standard file path so that it can be attached to the
    // email
    $oStorage =& KTStorageManagerUtil::getSingleton();
    $sDocumentPath = $oStorage->temporaryFile($oDocument);

    $sDocumentFileName = $oDocument->getFileName();
    $res = $oEmail->sendAttachment($sDestEmailAddress, $sTitle, $sMessage, $sDocumentPath, $sDocumentFileName);

    // Tell the storage we don't need the temporary file anymore.
    $oStorage->freeTemporaryFile($sDocumentPath);

    if (PEAR::isError($res)) {
        $default->log->error($res->getMessage());
        $aEmailErrors[] = $res->getMessage();
        return $res;
    } else if ($res === false) {
        $default->log->error("Error sending email ($sTitle) to $sDestEmailAddress");
        $aEmailErrors[] = "Error sending email ($sTitle) to $sDestEmailAddress";
        return PEAR::raiseError("Error sending email ($sTitle) to $sDestEmailAddress");
    } else {
        $default->log->info("Send email ($sTitle) to $sDestEmailAddress");
    }

    // emailed link transaction
    $oDocumentTransaction = & new DocumentTransaction($oDocument, "Document link emailed to " . $sDestEmailAddress, 'ktcore.transactions.email_attachment');
    if ($oDocumentTransaction->create()) {
        $default->log->debug("emailBL.php created email link document transaction for document ID=$iDocumentID");
    } else {
        $default->log->error("emailBL.php couldn't create email link document transaction for document ID=$iDocumentID");
    }
}

function sendEmailHyperlink($sDestEmailAddress, $sDestUserName, $iDocumentID, $sDocumentName, $sComment, &$aEmailErrors) {
    global $default;
    $oSendingUser = User::get($_SESSION["userID"]);
    
	$sMessage = "<font face=\"arial\" size=\"2\">";
    if ($sDestUserName) {
        $sMessage .= $sDestUserName . ",<br><br>";
    }
	$sMessage .= "Your colleague, " . $oSendingUser->getName() . ", wishes you to view the document entitled '" . $sDocumentName . "'.\n  ";
	$sMessage .= "Click on the hyperlink below to view it.";
	// add the link to the document to the mail
	$sMessage .= "<br>" . generateControllerLink("viewDocument", "fDocumentID=$iDocumentID", $sDocumentName);
	// add optional comment
	if (strlen($sComment) > 0) {
		$sMessage .= "<br><br>Comments:<br>$sComment";
	}
	$sMessage .= "</font>";
	$sTitle = "Link: " . $sDocumentName . " from " . $oSendingUser->getName();
	//email the hyperlink
    //
    $sEmail = null;
    $sEmailFrom = null;
    $oConfig =& KTConfig::getSingleton();
    if (!$oConfig->get('email/sendAsSystem')) {
        $sEmail = $oSendingUser->getEmail();
        $sEmailFrom = $oSendingUser->getName();
    }
    $oEmail = new Email($sEmail, $sEmailFrom);

    $res = $oEmail->send($sDestEmailAddress, $sTitle, $sMessage);
    if (PEAR::isError($res)) {
        $default->log->error($res->getMessage());
        $aEmailErrors[] = $res->getMessage();
        return $res;
    } else if ($res === false) {
		$default->log->error("Error sending email ($sTitle) to $sDestEmailAddress");		
		$aEmailErrors[] = "Error sending email ($sTitle) to $sDestEmailAddress";
        return PEAR::raiseError("Error sending email ($sTitle) to $sDestEmailAddress");
    } else {
		$default->log->info("Send email ($sTitle) to $sDestEmailAddress");
	}
	  
	// emailed link transaction
	// need a document to do this.
	$oDocument =& Document::get($iDocumentID);
	
	$oDocumentTransaction = & new DocumentTransaction($oDocument, "Document link emailed to " . $sDestEmailAddress, 'ktcore.transactions.email_link');
	if ($oDocumentTransaction->create()) {
		$default->log->debug("emailBL.php created email link document transaction for document ID=$iDocumentID");                                    	
	} else {
		$default->log->error("emailBL.php couldn't create email link document transaction for document ID=$iDocumentID");
	}
}

function validateEmailAddress($sEmailAddress) {
    $aEmailAddresses = array();
    if (strpos($sEmailAddress, ";")) {
        $aEmailAddresses = explode(";", $sEmailAddress);
    } else {
        $aEmailAddresses[] = $sEmailAddress;
    }
    $bToReturn = true;
    for ($i=0; $i<count($aEmailAddresses); $i++) {
        $bResult = ereg ("^[^@ ]+@[^@ ]+\.[^@ \.]+$", $aEmailAddresses[$i] );
        $bToReturn = $bToReturn && $bResult;
    }
    return $bToReturn;
}

class KTDocumentEmailAction extends KTDocumentAction {
    var $sName = 'ktcore.actions.document.email';

    function getDisplayName() {
        return _kt('Email');
    }

    function do_main() {
        $oTemplate =& $this->oValidator->validateTemplate('ktstandard/action/email');
        $fields = array();
        $oConfig = KTConfig::getSingleton();
        $bAttachment = $oConfig->get('email/allowAttachment', false);
        $bEmailAddresses = $oConfig->get('email/allowEmailAddresses', false);
        if ($bAttachment) {
            $fields[] = new KTCheckboxWidget(_kt("Attach document"), _kt("By default, documents are sent as links into the document management system.  Select this option if you want the document contents to be sent as an attachment in the email."), 'fAttachDocument', null, $this->oPage);
        }
        if ($bEmailAddresses) {
            $fields[] = new KTTextWidget(_kt("Email addresses"), _kt("Add extra email addresses here"), 'fEmailAddresses', "", $this->oPage, false, null, null, array('cols' => 60, 'rows' => 5));
        }
        $fields[] = new KTTextWidget(_kt("Comment"), _kt("A message for those who receive the document"), 'fComment', "", $this->oPage, true, null, null, array('cols' => 60, 'rows' => 5));

        $oKTConfig =& KTConfig::getSingleton();
        $bOnlyOwnGroup = $oKTConfig->get('email/onlyOwnGroup', false);
        if ($bOnlyOwnGroup != true) {
            $aGroups = Group::getList();
            $aUsers = User::getEmailUsers();
        } else {
            $aGroups = GroupUtil::listGroupsForUser($this->oUser);
            $aMembers = array();
            foreach ($aGroups as $oGroup) {
                $aMembers = array_merge($aMembers, $oGroup->getMembers());
            }
            $aUsers = array();
            $aUserIds = array();
            foreach ($aMembers as $oUser) {
                if (in_array($oUser->getId(), $aUserIds)) {
                    continue;
                }
                $aUsers[] = $oUser;
                $aUserIds[] = $oUser->getId();
            }
        }

        $aTemplateData = array(
            'context' => &$this,
            'fields' => $fields,
            'groups' => $aGroups,
            'users' => $aUsers,
        );
        return $oTemplate->render($aTemplateData);
    }

    function do_email() {
        $groupNewRight = trim($_REQUEST['groupNewRight'], chr(160));
        $userNewRight = trim($_REQUEST['userNewRight'], chr(160));

        $fEmailAddresses = trim($_REQUEST['fEmailAddresses']);
        $fAttachDocument = $_REQUEST['fAttachDocument'];
        $fComment = $this->oValidator->validateString($_REQUEST['fComment'],
			array('redirect_to'=>array('', sprintf('fDocumentId=%d', $this->oDocument->getId()))));

        // explode group and user ids
        $aGroupIDs = array();
        $aUserIDs = array();
        $aEmailAddresses = array();
        if (!empty($groupNewRight)) {
            $aGroupIDs = explode(",", $groupNewRight);
        }
        if (!empty($userNewRight)) {
            $aUserIDs = explode(",", $userNewRight);
        }
        if (!empty($fEmailAddresses)) {
            $aEmailAddresses = explode(" ", $fEmailAddresses);
        }

        $oConfig = KTConfig::getSingleton();
        $bAttachment = $oConfig->get('email/allowAttachment', false);
        $bEmailAddresses = $oConfig->get('email/allowEmailAddresses', false);
        
        if (empty($bAttachment)) {
            $fAttachDocument = false;
        }
        if (empty($bEmailAddresses)) {
            $aEmailAddresses = array();
        }


        //if we're going to send a mail, first make there is someone to send it to
        if ((count($aGroupIDs) == 0) && (count($aUserIDs) == 0) && (count($aEmailAddresses) == 0)) {
            $this->errorRedirectToMain(_kt('No recipients set'), sprintf('fDocumentId=%d', $this->oDocument->getId()));
            exit(0);
        }

        $aEmailErrors = array();

        // send group emails
        sendGroupEmails($aGroupIDs, $this->oDocument, $fComment, (boolean)$fAttachDocument, $aEmailErrors);
        // send user emails
        sendUserEmails($aUserIDs, $this->oDocument, $fComment, (boolean)$fAttachDocument, $aEmailErrors);
        // send manual email addresses
        sendManualEmails($aEmailAddresses, $this->oDocument, $fComment, (boolean)$fAttachDocument, $aEmailErrors);

        if (count($aEmailErrors)) {
            $_SESSION['KTErrorMessage'][] = join("<br />\n", $aEmailErrors);
        }

        $_SESSION['KTInfoMessage'][] = _kt("Email sent");
        //go back to the document view page
        controllerRedirect("viewDocument", sprintf("fDocumentId=%d", $this->oDocument->getId()));
    }
}

class KTEmailPlugin extends KTPlugin {
    var $sNamespace = "ktstandard.email.plugin";

	function KTEmailPlugin($sFilename = null) {
        $res = parent::KTPlugin($sFilename);
        $this->sFriendlyName = _kt('Email Plugin');
        return $res;
    }        

    function setup() {
        $this->registerAction('documentaction', 'KTDocumentEmailAction', 'ktcore.actions.document.email');
    }
}

$oRegistry =& KTPluginRegistry::getSingleton();
$oRegistry->registerPlugin('KTEmailPlugin', 'ktstandard.email.plugin', __FILE__);

