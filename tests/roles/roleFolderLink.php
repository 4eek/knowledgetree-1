<?php
/**
* @package tests.roles
*
* Unit tests for class RoleFolderLinkFoldersLink found in /lib/roles/RoleFolderLinkFoldersLink.inc
*
* @author Rob Cherry, Jam Warehouse (Pty) Ltd, South Africa
* @date 20 January 2003
*/

require_once("../../config/dmsDefaults.php");

if (checkSession) {
	require_once("$default->owl_fs_root/lib/roles/RoleFolderLink.inc");
	
	$oRoleFolderLink = & new RoleFolderLink(1,1,1,getCurrentDateTime(), true);
	echo "Create ? " . ($oRoleFolderLink->create() ? "Yes" : "No") . "<br>";
	$oRoleFolderLink = & new RoleFolderLink(1,1,1,getCurrentDateTime(), true);
	$oRoleFolderLink->create();
	$oRoleFolderLink = & new RoleFolderLink(1,1,1,getCurrentDateTime(), true);
	$oRoleFolderLink->create();
	$oRoleFolderLink = & new RoleFolderLink(1,1,1,getCurrentDateTime(), true);
	$oRoleFolderLink->create();
	$oRoleFolderLink = & new RoleFolderLink(1,1,1,getCurrentDateTime(), true);
	$oRoleFolderLink->create();
	echo "Update ? " . ($oRoleFolderLink->update() ? "Yes" : "No") . "<br>";
	echo "Delete ? " . ($oRoleFolderLink->delete() ? "Yes" : "No") . "<br>";
	$oNewRoleFolderLink = RoleFolderLink::get(1);
	echo "Get ? <pre>" . print_r($oNewRoleFolderLink) . "</pre>";
	$oNewRoleFolderLink = RoleFolderLink::getList();
	echo "GetList ? <pre>" . print_r($oNewRoleFolderLink) . "</pre>";
	$oNewRoleFolderLink = RoleFolderLink::getList("WHERE id > 5");
	echo "GetList ? <pre>" . print_r($oNewRoleFolderLink) . "</pre>";
	
}

?>
