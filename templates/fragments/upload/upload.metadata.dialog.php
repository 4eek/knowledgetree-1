<?php

include_once('../../ktapi/ktapi.inc.php');

$KT = new KTAPI(3);
//$KT->get(3);// Set it to Use Web Version 3

//Pick up the session
$session = KTAPI_UserSession::getCurrentBrowserSession($KT);
$KT->start_system_session($session->user->getUserName());

if (!function_exists('getDocTypes')) {
    function getDocTypes()
    {
        $types = DocumentType::getList();

        $ret = array();
        foreach ($types as $type) {
            $ret[$type->aFieldArr['id']] = $type->aFieldArr;
        }

        return $ret;
    }
}

?>
    <div>
       <table class="metadataTable" border="0" cellspacing="0" cellpadding="0">
       	<tr><td class="ul_meta_selectDocType">Select Document Type<span class="ul_meta_docTypeOptions">
	        <select class="ul_doctype" onchange="kt.app.upload.getMetaItem(this).changeDocType(this.options[this.selectedIndex].value);">
	        	<?php
	        	  $docTypes = getDocTypes();
	        	  foreach ($docTypes as $docTypeId => $docType) :
	        	      if (!$docType['disabled']) :
	        	?>
	        	<option value="<?php echo $docTypeId; ?>" ><?php echo $docType['name']; ?></option>

	        	<?php endif; endforeach; ?>
	        </select>
       	</span></td></tr>
       	<tr><td class="ul_metadata"></td></tr>
       	<tr><td class="ul_meta_actionbar">
       		<input type="checkbox" id="ul_meta_actionbar_apply_to_all">
       		<label for="ul_meta_actionbar_apply_to_all">Apply to All</label>
       		<input type="button" value="Apply" onclick="kt.app.upload.getMetaItem(this).options.metaWindow.close();" />
       		<a class="ul_actions_cancel_link" href="#" onclick="javascript:kt.app.upload.getMetaItem(this).options.metaWindow.close();">Cancel</a>
       	</td></tr>
       </table>
    </div>