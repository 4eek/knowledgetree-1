<?php
/**
* Document collaboration business logic - contains business logic to set up
* document approval process
*
* Expected form variables:
*	o fFolderCollaborationID - 
*	o fForAdd - 
*
* @author Rob Cherry, Jam Warehouse (Pty) Ltd, South Africa
* @date 28 January 2003
* @package presentation.lookAndFeel.knowledgeTree.foldermanagement
*
*/

require_once("../../../../config/dmsDefaults.php");

if (checkSession()) {	
	require_once("$default->fileSystemRoot/lib/visualpatterns/PatternListBox.inc");
	require_once("$default->fileSystemRoot/lib/visualpatterns/PatternEditableListFromQuery.inc");
	require_once("$default->fileSystemRoot/lib/foldermanagement/FolderCollaboration.inc");
	require_once("$default->fileSystemRoot/lib/foldermanagement/Folder.inc");
	require_once("collaborationUI.inc");
	require_once("$default->fileSystemRoot/lib/security/permission.inc");	
	require_once("$default->fileSystemRoot/lib/visualpatterns/PatternCustom.inc");
	require_once("$default->fileSystemRoot/presentation/lookAndFeel/knowledgeTree/foldermanagement/folderUI.inc");
	require_once("$default->fileSystemRoot/presentation/Html.inc");
	
	if (isset($fForAdd)) {
		//we are adding a new entry
		require_once("$default->fileSystemRoot/presentation/webpageTemplate.inc");
		$oPatternCustom = & new PatternCustom();
		$oPatternCustom->setHtml(getEditPage($fFolderCollaborationID, $fFolderID));
		$main->setCentralPayload($oPatternCustom);
		$main->setFormAction($_SERVER["PHP_SELF"] . "?fFolderID=$fFolderID&fForCreate=1");
		$main->render();
	} else if (isset($fForStore)) {
		//we are storing a new entry
		$oFolderCollaboration = & FolderCollaboration::get($fFolderCollaborationID);
		$oFolderCollaboration->setGroupID($fGroupID);
		if ($fRoleID != -1) {
			$oFolderCollaboration->setRoleID($fRoleID);
		} else {
			$oFolderCollaboration->setRoleID(null);
		}
		$oFolderCollaboration->setSequenceNumber($fSequenceNumber);
		$oFolderCollaboration->update();
		redirect("$default->rootUrl/control.php?action=editFolder&fFolderID=$fFolderID");
	} else {		
		$oFolderCollaboration = FolderCollaboration::get($fFolderCollaborationID);
		if ($oFolderCollaboration->hasDocumentInProcess()) {
			//you cannot alter collaboration process at the folder level if a document is currently
			//going through the process
			redirect("$default->rootUrl/control.php?action=editFolder&fFolderID=$fFolderID&fCollaborationEdit=0");			
		} else {
			//we are editing an existing entry
			require_once("$default->fileSystemRoot/presentation/webpageTemplate.inc");
			$oPatternCustom = & new PatternCustom();
			$oPatternCustom->setHtml(getEditPage($fFolderCollaborationID, $fFolderID));
			$main->setCentralPayload($oPatternCustom);
			$main->setFormAction("../store.php?fReturnURL=" . urlencode("$default->rootUrl/control.php?action=editFolder&fFolderID=$fFolderID"));
			$main->render();
		}
	}	
}
?>
