<?php
/**
* BL information for adding a unit
*
* @author Mukhtar Dharsey
* @date 5 February 2003
* @package presentation.lookAndFeel.knowledgeTree.
*
*/
require_once("../../../../../config/dmsDefaults.php");

if (checkSession()) {
    require_once("$default->fileSystemRoot/lib/visualpatterns/PatternListBox.inc");
    require_once("$default->fileSystemRoot/lib/visualpatterns/PatternCreate.inc");
    require_once("addUnitToOrgUI.inc");
    require_once("$default->fileSystemRoot/lib/unitmanagement/Unit.inc");
    require_once("$default->fileSystemRoot/lib/unitmanagement/UnitOrganisationLink.inc");
    require_once("$default->fileSystemRoot/lib/orgManagement/Organisation.inc");
    require_once("$default->fileSystemRoot/lib/security/permission.inc");
    require_once("$default->fileSystemRoot/presentation/webpageTemplate.inc");
    require_once("$default->fileSystemRoot/lib/visualpatterns/PatternCustom.inc");
    require_once("$default->fileSystemRoot/lib/foldermanagement/Folder.inc");
    require_once("$default->fileSystemRoot/presentation/lookAndFeel/knowledgeTree/foldermanagement/folderUI.inc");
    require_once("$default->fileSystemRoot/presentation/Html.inc");

    $oPatternCustom = & new PatternCustom();
	
	$oPatternCustom->addHtml(renderHeading("Add Unit to an Organisation"));    
    
    if (isset($fUnitID)) {    	    
    	if ($fOrgID == "" && $fAdd == 1){
	    	$main->setErrorMessage("Please choose an Organisation.");	
    		$main->setFormAction($_SERVER["PHP_SELF"] . "?fUnitID=$fUnitID&fAdd=1" );
    	}	
		if ($fOrgID > 0) {    	
	    	$oUnitOrgLink = & new UnitOrganisationLink($fUnitID,$fOrgID);    	
			if ($oUnitOrgLink->create()) {
				$oPatternCustom->addHtml(getAddSuccessPage());
			}else{
				$main->setErrorMessage("Unit cannot be linked to the Organisation.");
				$oPatternCustom->addHtml(getAddFailPage());
			}   	    	    	   
	    } else{
	    	$oUnit = Unit::get($fUnitID);    	
	    	$oPatternCustom->addHtml(getAddUnitsPage($oUnit));
	    	$main->setFormAction($_SERVER["PHP_SELF"] . "?fUnitID=$fUnitID&fAdd=1" );	    	
		}    	
    }
    else {  
    
	    if (isset($fForStore)) {
	        if($fUnitName != "" and $fOrgID != "") {
	            $oUnit = new Unit($fUnitName);
	
	            // if creation is successfull..get the unit id
	            if ($oUnit->create()) {
	                $unitID = $oUnit->getID();
	                $oUnitOrg = new UnitOrganisationLink($unitID,$fOrgID);
	
	                if($oUnitOrg->create()) {
	                    // if successfull print out success message
	                    $oPatternCustom->setHtml(getAddPageSuccess());
	                } else {
	                    // if fail print out fail message
	                    $oPatternCustom->setHtml(getAddToOrgFail());
	                }
	            } else {
	                // if fail print out fail message
	                $oPatternCustom->setHtml(getAddPageFail());
	            }
	        } else {
	            $oPatternCustom->setHtml(getPageFail());
	        }
	
	    } else if (isset($fUnitID)) {
	        // post back on Unit select from manual edit page
	        $oPatternCustom->setHtml(getAddPage($fUnitID));
	        $main->setFormAction($_SERVER["PHP_SELF"] . "?fForStore=1");
	    } else {
	        // if nothing happens...just reload edit page
	        $oPatternCustom->setHtml(getAddPage(null));
	        $main->setFormAction($_SERVER["PHP_SELF"]. "?fForStore=1");
	
	    }
    
    }
    
    $main->setCentralPayload($oPatternCustom);    
    $main->render();
}
?>
