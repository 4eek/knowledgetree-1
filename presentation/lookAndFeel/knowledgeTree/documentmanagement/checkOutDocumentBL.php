<?php
/**
 * $Id$
 *
 * Business Logic to check out a document
 *
 * Expected form variable:
 * o $fDocumentID - primary key of document user is checking out
 *
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * @version $Revision$
 * @author Michael Joseph <michael@jamwarehouse.com>, Jam Warehouse (Pty) Ltd, South Africa
 * @package presentation.lookAndFeel.knowledgeTree.documentmanagement
 */

require_once("../../../../config/dmsDefaults.php");

if (checkSession()) {
    require_once("$default->fileSystemRoot/lib/visualpatterns/PatternCustom.inc");
    require_once("$default->fileSystemRoot/lib/foldermanagement/Folder.inc");
    require_once("$default->fileSystemRoot/lib/documentmanagement/Document.inc");
    require_once("$default->fileSystemRoot/lib/documentmanagement/DocumentTransaction.inc");
    require_once("$default->fileSystemRoot/lib/documentmanagement/PhysicalDocumentManager.inc");
    require_once("$default->fileSystemRoot/lib/subscriptions/SubscriptionEngine.inc");    
    require_once("documentUI.inc");

    $oPatternCustom = & new PatternCustom();

    if (isset($fDocumentID)) {
        // instantiate the document
        $oDocument = & Document::get($fDocumentID);
        if ($oDocument) {
            // user has permission to check the document out
            if (Permission::userHasDocumentWritePermission($fDocumentID)) {
                // and its not checked out already
                if (!$oDocument->getIsCheckedOut()) {
                    // if we're ready to perform the updates
                    if ($fForStore) {
                        // flip the checkout status
                        $oDocument->setIsCheckedOut(true);
                        // set the user checking the document out
                        $oDocument->setCheckedOutUserID($_SESSION["userID"]);
                        // update it
                        if ($oDocument->update()) {
                            
                            //create the document transaction record
                            $oDocumentTransaction = & new DocumentTransaction($oDocument->getID(), $fCheckOutComment, CHECKOUT);
                            // TODO: check transaction creation status?
                            $oDocumentTransaction->create();
                            
                            // fire subscription alerts for the checked out document
                            $count = SubscriptionEngine::fireSubscription($fDocumentID, SubscriptionConstants::subscriptionAlertType("CheckOutDocument"),
                                     SubscriptionConstants::subscriptionType("DocumentSubscription"),
                                     array( "modifiedDocumentName" => $oDocument->getName() ));
                            $default->log->info("checkOutDocumentBL.php fired $count subscription alerts for checked out document " . $oDocument->getName());

                            //redirect to the document view page
                            redirect("$default->rootUrl/control.php?action=viewDocument&fDocumentID=" . $oDocument->getID());                        
                            
                        } else {
                            // document update failed
                            $oPatternCustom->setHtml("<p class=\"errorText\">An error occurred while storing this document in the database</p>\n");
                        }
                    } else {
                        // prompt the user for a checkout comment
                        $oPatternCustom->setHtml(renderCheckOutPage($oDocument));
                    }
                } else {
                    // this document is already checked out
                    // TODO: for extra credit, tell the user who has this document checked out
                    $oPatternCustom->setHtml("<p class=\"errorText\">This document is already checked out</p>\n");                    
                }
            } else {
                // no permission to checkout the document
                $oPatternCustom->setHtml("<p class=\"errorText\">Could not check out this document</p>\n");
            }
        } else {
            // couldn't instantiate the document
            $oPatternCustom->setHtml("<p class=\"errorText\">Could not check out this document</p>\n");
        }
    } else {
        // no document id was set when coming to this page,
        $oPatternCustom->setHtml("<p class=\"errorText\">No document is currently selected for check out</p>\n");
    }

    require_once("$default->fileSystemRoot/presentation/webpageTemplate.inc");
    $main->setCentralPayload($oPatternCustom);
    $main->setFormAction($_SERVER["PHP_SELF"]);
    if (isset($sErrorMessage)) {
        $main->setErrorMessage($sErrorMessage);
    }
    $main->render();
}
?>
