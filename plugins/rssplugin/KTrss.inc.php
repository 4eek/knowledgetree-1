<?php
/*
 * $Id:$
 *
 * KnowledgeTree Open Source Edition
 * Document Management Made Simple
 * Copyright (C) 2004 - 2007 The Jam Warehouse Software (Pty) Limited
 * 
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 3 as published by the
 * Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * You can contact The Jam Warehouse Software (Pty) Limited, Unit 1, Tramber Place,
 * Blake Street, Observatory, 7925 South Africa. or email info@knowledgetree.com.
 * 
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 * 
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * KnowledgeTree" logo and retain the original copyright notice. If the display of the 
 * logo is not reasonably feasible for technical reasons, the Appropriate Legal Notices
 * must display the words "Powered by KnowledgeTree" and retain the original 
 * copyright notice. 
 * Contributor( s): ______________________________________
 *
 */

// boilerplate.
require_once(KT_LIB_DIR . "/templating/templating.inc.php");
require_once(KT_LIB_DIR . "/templating/kt3template.inc.php");
require_once(KT_LIB_DIR . "/dispatcher.inc.php");
require_once(KT_LIB_DIR . "/util/ktutil.inc");
require_once(KT_LIB_DIR . "/database/dbutil.inc");

// document related includes
require_once(KT_LIB_DIR . "/documentmanagement/Document.inc");
require_once(KT_LIB_DIR . "/documentmanagement/DocumentType.inc");
require_once(KT_LIB_DIR . "/documentmanagement/DocumentFieldLink.inc");
require_once(KT_LIB_DIR . "/documentmanagement/documentmetadataversion.inc.php");
require_once(KT_LIB_DIR . "/documentmanagement/documentcontentversion.inc.php");
require_once(KT_LIB_DIR . "/metadata/fieldset.inc.php");
require_once(KT_LIB_DIR . "/security/Permission.inc");

require_once(KT_LIB_DIR . "/actions/documentaction.inc.php");
require_once(KT_LIB_DIR . "/browse/browseutil.inc.php");

class KTrss{
    // Gets a listing of external feeds for user
    function getExternalFeedsList($iUserId){
    	$sQuery = "SELECT id, url, title FROM plugin_rss WHERE user_id = ?";
        $aParams = array($iUserId);
        $aFeeds = DBUtil::getResultArray(array($sQuery, $aParams));

        if (PEAR::isError($aFeeds)) {
            // XXX: log error
            return false;
        }
        if ($aFeeds) {
            return $aFeeds;
        }
    }

    // Gets full listing of data of documents and folders subscribed to
    function getInternalFeed($iUserId){
    	$documents=KTrss::getDocuments($iUserId);
    	$folders=KTrss::getFolders($iUserId);
    	if (is_null($documents)) $documents=array();
    	if (is_null($folders)) $folders=array();
    	$aFullList = kt_array_merge($documents,$folders );
    	if($aFullList){
    		$internalFeed = KTrss::arrayToXML($aFullList);
    		$response = rss2arrayBlock($internalFeed);
    	}
    	return $response;
    }

    // Get list of document subscriptions
    function getDocumentList($iUserId){
    	$sQuery = "SELECT document_id as id FROM document_subscriptions WHERE user_id = ?";
        $aParams = array($iUserId);
        $aDocumentList = DBUtil::getResultArrayKey(array($sQuery, $aParams), 'id');

        if (PEAR::isError($aDocumentList)) {
            // XXX: log error
            return false;
        }
        if($aDocumentList){
            return $aDocumentList;
        }
    }

    // Get list of folder subscriptions
    function getFolderList($iUserId){
        $sQuery = "SELECT folder_id as id FROM folder_subscriptions WHERE user_id = ?";
        $aParams = array($iUserId);
        $aFolderList = DBUtil::getResultArrayKey(array($sQuery, $aParams), 'id'); 

        if (PEAR::isError($aFolderList)) {
            // XXX: log error
            return false;
        }
        if ($aFolderList) {
            return $aFolderList;
        }
    }

    // Get data for all documents subscribed to
    function getDocuments($iUserId){
    	$aDList = KTrss::getDocumentList($iUserId);
    	if($aDList){
	    	foreach($aDList as $document_id){
		        $document = KTrss::getOneDocument($document_id, $iUserId);
		        if($document){
		        	$aDocuments[] = $document;
		        }
	    	}
    	}
    	if (PEAR::isError($aDocuments)) {
            // XXX: log error
            return false;
        }
        if ($aDocuments) {
            return $aDocuments;
        }
    }

    // Get data for all folders subscribed to
    function getFolders($iUserId){
    	$aFList = KTrss::getFolderList($iUserId);

    	if($aFList){
	    	foreach($aFList as $folderElement){
		        $folder_id = $folderElement['id'];
		        $folder = KTrss::getOneFolder($folder_id, $iUserId);
		        if($folder){
		        	$aFolders[] = $folder;
		        }
	    	}
    	}

    	if (PEAR::isError($aFolders)) {
            // XXX: log error
            return false;
        }
        if ($aFolders){
            return $aFolders;
        }
    }

    function getChildrenFolderTransactions($iParentFolderId, $depth = '1'){
    	if($depth == '1'){
	    	$sQuery = "SELECT id from folders WHERE parent_folder_ids LIKE ?";
	    	$aParams = array('%'.$iParentFolderId);
    	}//else

        $aFolderList = DBUtil::getResultArray(array($sQuery, $aParams));
        if (PEAR::isError($aFolderList)) {
            // XXX: log error
            return false;
        }
        if ($aFolderList) {
            foreach($aFolderList as $folderElement){
		        $folder_id = $folderElement['id'];
		        $aFolderTransactions = kt_array_merge($aFolderTransactions, KTrss::getFolderTransactions($folder_id));
	    	}
        }
        if ($aFolderTransactions){
            return $aFolderTransactions;
        }
    }

    function getChildrenDocumentTransactions($iParentFolderId, $depth = '1'){
    	if($depth == '1'){
    		$sQuery = "SELECT id from documents WHERE parent_folder_ids LIKE ? ";
    		$aParams = array('%'.$iParentFolderId);
    	}//else

        $aDocumentList = DBUtil::getResultArray(array($sQuery, $aParams));

        if (PEAR::isError($aDocumentList)) {
            // XXX: log error
            return false;
        }
        if ($aDocumentList) {
            foreach($aDocumentList as $documentElement){
		        $document_id = $documentElement['id'];
		        $aDocumentTransactions = kt_array_merge($aDocumentTransactions, KTrss::getDocumentTransactions($document_id));
	    	}
        }
        if ($aDocumentTransactions){
            return $aDocumentTransactions;
        }
    }

    // get information on document
    function getOneDocument($iDocumentId, $iUserId){
        $aDData = KTrss::getDocumentData($iUserId, $iDocumentId);
        $aDTransactions = KTrss::getDocumentTransactions($iDocumentId);
        if($aDData){
        	$aDData['itemType'] = 'document';

    		// create mime info
			$aMimeInfo = KTrss::getMimeTypeInfo($iUserId, $iDocumentId);
			$aDData['mimeTypeFName'] = $aMimeInfo['typeFName'];
			$aDData['mimeTypeIcon'] = $aMimeInfo['typeIcon'];

        	$aDocument[] = $aDData;
        	$aDocument[] = $aDTransactions;
        }
    	if (PEAR::isError($aDData)) {
            return false;
        }
        if ($aDocument){
            return $aDocument;
        }
    }

    // get information for folder
    function getOneFolder($iFolderId){
    	$aFData = KTrss::getFolderData($iFolderId);
        $aFTransactions = kt_array_merge(KTrss::getChildrenFolderTransactions($iFolderId), KTrss::getFolderTransactions($iFolderId));
        $aFTransactions = kt_array_merge($aFTransactions, KTrss::getChildrenDocumentTransactions($iFolderId));

        $code = 'if (strtotime($a[datetime]) == strtotime($b[datetime])){
	        return 0;
	    }
	    return (strtotime($a[datetime]) > strtotime($b[datetime])) ? -1 : 1;';

		$compare = create_function('$a,$b', $code);

        usort($aFTransactions, $compare);
        for($i=0; $i<4; $i++){
        	$aFTransactions_new[] = $aFTransactions[$i];
        }
		$aFTransactions = $aFTransactions_new;

        if($aFData){
        	$aFData['itemType'] = 'folder';

    		// create mime info
			$aFData['mimeTypeFName'] = 'Folder';
			$aFData['mimeTypeIcon'] = KTrss::getFolderIcon();

        	$aFolder[] = $aFData;
        	$aFolder[] = $aFTransactions;
        	$aFolderBox[] = $aFolder;
        }
    	if (PEAR::isError($aFData)) {
            return false;
        }
        if ($aFolder){
            return $aFolder;
        }
    }

    function rss_sanitize($str, $do_amp=true)
    {

        $result = str_replace("\\\"","\"",str_replace('\\\'','\'',htmlentities($str,ENT_NOQUOTES, 'UTF-8')));
        if ($do_amp)
        {
            $result = str_replace('&','&amp;',$result);
        }
        return $result;
    }

    // Takes in an array as a parameter and returns rss2.0 compatible xml
    function arrayToXML($aItems){
    	// Build path to host
    	$aPath = explode('/', trim($_SERVER['PHP_SELF']));
    	global $default;
    	$hostPath = "http" . ($default->sslEnabled ? "s" : "") . "://".$_SERVER['HTTP_HOST']."/".$aPath[1]."/";
    	$feed = "<?xml version=\"1.0\"?>\n";
    	$feed .= "<rss version=\"2.0\">\n".
    			 "<channel>\n" .
	    			"<title>".APP_NAME." RSS</title>\n" .
	    			"<copyright>(c) 2007 The Jam Warehouse Software (Pty) Ltd. All Rights Reserved</copyright>\n" .
	    			"<link>".$hostPath."</link>\n" .
	    			"<description>KT-RSS</description>\n" .
	    			"<image>\n".
					"<title>".APP_NAME." RSS</title>\n".
					"<width>140</width>\n".
					"<height>28</height>".
					"<link>".$hostPath."knowledgeTree/</link>\n".
					"<url>".$hostPath."resources/graphics/ktlogo_rss.png</url>\n".
					"</image>\n";
	    foreach($aItems as $aItems){
	    	if($aItems[0][itemType] == 'folder'){
	    		$sTypeSelect = 'folder.transactions&amp;fFolderId';
	    	}elseif($aItems[0][itemType] == 'document'){
	    		$sTypeSelect = 'document.transactionhistory&amp;fDocumentId';
	    	}
	    	$feed .= "<item>\n" .
	    	         	"<title>".htmlentities(KTrss::rss_sanitize($aItems[0][0][name],false), ENT_QUOTES, 'UTF-8')."</title>\n" .
	    	         	"<link>".$hostPath."action.php?kt_path_info=ktcore.actions.".$sTypeSelect."=".$aItems[0][0]['id']."</link>\n" .
	    	         	"<description>\n" .
	    	         	"&lt;table border='0' width='90%'&gt;\n".
			 				"&lt;tr&gt;\n".
								"&lt;td width='5%' height='16px'&gt;" .
									"&lt;a href='".$hostPath."action.php?kt_path_info=ktcore.actions.".$sTypeSelect."=".$aItems[0][0][id]."' &gt;&lt;img src='".$aItems[0][mimeTypeIcon]."' align='left' height='16px' width='16px' alt='' border='0' /&gt;&lt;/a&gt;" .
								"&lt;/td&gt;\n".
								"&lt;td align='left'&gt; ".$aItems[0][mimeTypeFName]."&lt;/td&gt;\n".
							"&lt;/tr&gt;\n".
							"&lt;tr&gt;\n".
								"&lt;td colspan='2'&gt;\n".
									ucfirst($aItems[0]['itemType'])." Information (ID: ".$aItems[0][0][id].")&lt;/&gt;\n".
									"&lt;hr&gt;\n".
									"&lt;table width='95%'&gt;\n".
										"&lt;tr&gt;\n".
											"&lt;td&gt;Filename: ".KTrss::rss_sanitize($aItems[0][0][filename])."&lt;/td&gt;\n".
											"&lt;td&gt;\n".
										"&lt;/tr&gt;\n".
										"&lt;tr&gt;\n".
											"&lt;td&gt;Author: ".$aItems[0][0][author]."&lt;/td&gt;\n".
											"&lt;td&gt;\n".
										"&lt;/tr&gt;\n".
										"&lt;tr&gt;\n".
											"&lt;td&gt;Owner: ";if($aItems[0][0][owner]){$feed .= $aItems[0][0][owner];}else{$feed .= "None";}
											$feed .= "&lt;/td&gt;\n".
											"&lt;td&gt;&lt;/td&gt;\n".
										"&lt;/tr&gt;\n".
										"&lt;tr&gt;\n";if($aItems[0][0][type]){
											$feed .= "&lt;td&gt;Document type: ".$aItems[0][0][type]."&lt;/td&gt;\n".
											"&lt;td&gt;&lt;/td&gt;\n";}
										$feed .= "&lt;/tr&gt;\n".
										"&lt;tr&gt;\n".
											"&lt;td&gt;Workflow status: ";if($aItems[0][0][workflow_status]){$feed .= $aItems[0][0][workflow_status];}else{$feed .= "No Workflow";}
											$feed .= "&lt;/td&gt;\n".
											"&lt;td&gt;&lt;/td&gt;\n".
										"&lt;/tr&gt;\n".
									"&lt;/table&gt;&lt;br&gt;\n".
									"Transaction Summary (Last 3)\n".
									"&lt;hr&gt;\n".
									"&lt;table width='100%'&gt;\n";
										foreach($aItems[1] as $item){
										$feed .= "&lt;tr&gt;\n".
											"&lt;td&gt;".$item[type]." name:&lt;/td&gt;\n".
											"&lt;td&gt;".KTrss::rss_sanitize($item[name] )."&lt;/td&gt;\n".
										"&lt;/tr&gt;\n".
										"&lt;tr&gt;\n".
											"&lt;td&gt;Path:&lt;/td&gt;\n".
											"&lt;td&gt;".KTrss::rss_sanitize($item[fullpath] )."&lt;/td&gt;\n".
										"&lt;/tr&gt;\n".
										"&lt;tr&gt;\n".
											"&lt;td&gt;Transaction:&lt;/td&gt;\n".
											"&lt;td&gt;".$item[transaction_name]."&lt;/td&gt;\n".
										"&lt;/tr&gt;\n".
										"&lt;tr&gt;\n".
											"&lt;td&gt;Comment:&lt;/td&gt;\n".
											"&lt;td&gt;".KTrss::rss_sanitize($item[comment] )."&lt;/td&gt;\n".
										"&lt;/tr&gt;\n".
										"&lt;tr&gt;\n";if($item[version]){
											$feed .= "&lt;td&gt;Version:&lt;/td&gt;\n".
											"&lt;td&gt;".$item[version]."&lt;/td&gt;\n";}
										$feed .= "&lt;/tr&gt;\n".
										"&lt;tr&gt;\n".
											"&lt;td&gt;Date:&lt;/td&gt;\n".
											"&lt;td&gt;".$item[datetime]."&lt;/td&gt;\n".
										"&lt;/tr&gt;\n".
										"&lt;tr&gt;\n".
											"&lt;td&gt;User:&lt;/td&gt;\n".
											"&lt;td&gt;".$item[user_name]."&lt;/td&gt;\n".
										"&lt;/tr&gt;\n".
										"&lt;tr&gt;\n".
											"&lt;td colspan='2'&gt;&lt;hr width='100' align='left'&gt;&lt;/td&gt;\n".
										"&lt;/tr&gt;\n";}
								$feed .= "&lt;/table&gt;\n".
								"&lt;/td&gt;\n".
							"&lt;/tr&gt;\n".
						"&lt;/table&gt;".
						"</description>\n".
	    			 "</item>\n";
	    }
	    $feed .= "</channel>\n" .
	    		 "</rss>\n";

	   return $feed;
    }

    // Takes in an array as a parameter and returns rss2.0 compatible xml
    function errorToXML($sError){
    	// Build path to host
    	$aPath = explode('/', trim($_SERVER['PHP_SELF']));
    	global $default;
    	$hostPath = "http" . ($default->sslEnabled ? "s" : "") . "://".$_SERVER['HTTP_HOST']."/".$aPath[1]."/";
    	$feed = "<?xml version=\"1.0\"?>\n";
    	$feed .= "<rss version=\"2.0\">\n".
    			 "<channel>\n" .
	    			"<title>".APP_NAME." RSS</title>\n" .
	    			"<copyright>(c) 2007 The Jam Warehouse Software (Pty) Ltd. All Rights Reserved</copyright>\n" .
	    			"<link>".$hostPath."</link>\n" .
	    			"<description>KT-RSS</description>\n" .
	    			"<image>\n".
					"<title>".APP_NAME." RSS</title>\n".
					"<width>140</width>\n".
					"<height>28</height>".
					"<link>".$hostPath."knowledgeTree/</link>\n".
					"<url>".$hostPath."resources/graphics/ktlogo_rss.png</url>\n".
					"</image>\n";
    	$feed .= "<item>\n".
    	         	"<title>Feed load error</title>\n" .
    	         	"<description>".$sError."</description>\n".
    			 "</item>\n";
	    $feed .= "</channel>\n" .
	    		 "</rss>\n";

	   return $feed;
    }

    // Delete feed function
    function deleteFeed($iFeedId){
    	$res = DBUtil::autoDelete('plugin_rss', $iFeedId);
    }

    // Get title for external feed
    function getExternalFeedTitle($iFeedId){
    	$sQuery = "SELECT title FROM plugin_rss WHERE id = ?";
        $aParams = array($iFeedId);
        $sFeedTitle = DBUtil::getOneResultKey(array($sQuery, $aParams), 'title');

        if (PEAR::isError($sFeedTitle)) {
            // XXX: log error
            return false;
        }
        if ($sFeedTitle) {
            return $sFeedTitle;
        }
    }

    // Get url for external feed
    function getExternalFeedUrl($iFeedId){
    	$sQuery = "SELECT url FROM plugin_rss WHERE id = ?";
        $aParams = array($iFeedId);
        $sFeedUrl = DBUtil::getOneResultKey(array($sQuery, $aParams), 'url');

        if (PEAR::isError($sFeedUrl)) {
            // XXX: log error
            return false;
        }
        if ($sFeedUrl) {
            return $sFeedUrl;
        }
    }

    // Update external feed data
    function updateFeed($iFeedId, $sFeedTitle, $sFeedUrl){
    	$sQuery = "UPDATE plugin_rss SET title=?, url=? WHERE id=?";
        $aParams = array($sFeedTitle, $sFeedUrl, $iFeedId);
        $res = DBUtil::runQuery(array($sQuery, $aParams));

        return $res;
    }

    // Create new external feed
    function createFeed($sFeedTitle, $sFeedUrl, $iUserId){
        $aParams = array(
        'user_id' => $iUserId,
        'url' => $sFeedUrl,
        'title' => $sFeedTitle,
        );
        $res = DBUtil::autoInsert('plugin_rss', $aParams);

        return $res;
    }

    // Function to validate that a user has permissions for a specific document
    function validateDocumentPermissions($iUserId, $iDocumentId){
		// check if user id is in session. If not, set it
		if(!isset($_SESSION["userID"])){
			$_SESSION['userID'] = $iUserId;
		}
		// get document object
		$oDocument =& Document::get($iDocumentId);
		if (PEAR::isError($oDocument)) {
            return false;
        }

		// check permissions for document
		if(Permission::userHasDocumentReadPermission($oDocument)){
		    return true;
		}else{
			return false;
		}
	}

	// Function to validate that a user has permissions for a specific folder
	function validateFolderPermissions($iUserId, $iFolderId){
		// check if user id is in session. If not, set it
		if(!isset($_SESSION["userID"])){
			$_SESSION['userID'] = $iUserId;
		}
		// get folder object
		$oFolder = Folder::get($iFolderId);
		if (PEAR::isError($oFolder)) {
            return false;
        }

		// check permissions for folder
		if(Permission::userHasFolderReadPermission($oFolder)){
		    return true;
		}else{
			return false;
		}
	}

	// get icon link for rss
	function getRssLinkIcon(){
    	// built server path
        global $default;
    	$sHostPath = "http" . ($default->sslEnabled ? "s" : "") . "://".$_SERVER['HTTP_HOST']."/".$GLOBALS['KTRootUrl']."/";

        // create image
        $icon = "<img src='".$sHostPath."resources/graphics/rss.gif' alt='RSS' border=0/>";

        return $icon;
    }

    // get rss link for a document/folder
    function getRssLink($iItemId, $sItemType){
        $item = strToLower($sItemType);
        if($item == 'folder'){
        	$sItemParameter = '?folderId';
        }else if($item == 'document'){
        	$sItemParameter = '?docId';
        }

        // built server path
        global $default;
        $sHostPath = "http" . ($default->sslEnabled ? "s" : "") . "://" . $_SERVER['HTTP_HOST'];

        // build link
    	$sLink = $sHostPath.KTBrowseUtil::buildBaseUrl('rss').$sItemParameter.'='.$iItemId;

    	return $sLink;
    }

    // get rss icon link
    function getImageLink($iItemId, $sItemType){
    	return "<a href='".KTrss::getRssLink($iItemId, $sItemType)."' target='_blank'>".KTrss::getRssLinkIcon()."</a>";
    }

    // get the mime type id for a document
    function getDocumentMimeTypeId($iUserId, $iDocumentId){
		if(!isset($_SESSION["userID"])){
			$_SESSION['userID'] = $iUserId;
		}
		// get document object
		$oDocument =& Document::get($iDocumentId);

		$docMime = $oDocument->getMimeTypeID();
		return $docMime;
	}

	// get mime information for a document
    function getMimeTypeInfo($iUserId, $iDocumentId){
        global $default;
    	$mimeinfo['typeId'] = KTrss::getDocumentMimeTypeId($iUserId, $iDocumentId); // mime type id
		$mimeinfo['typeName'] = KTMime::getMimeTypeName($mimeinfo['typeId']); // mime type name
		$mimeinfo['typeFName'] = KTMime::getFriendlyNameForString($mimeinfo['typeName']); // mime type friendly name
		$mimeinfo['typeIcon'] = "http" . ($default->sslEnabled ? "s" : "") . "://".$_SERVER['HTTP_HOST']."/".$GLOBALS['KTRootUrl']."/resources/mimetypes/".KTMime::getIconPath($mimeinfo['typeId']).".png"; //icon path

		return $mimeinfo;
    }

    // get the default folder icon
    function getFolderIcon(){
    	global $default;
    	return $mimeinfo['typeIcon'] = "http" . ($default->sslEnabled ? "s" : "") . "://".$_SERVER['HTTP_HOST']."/".$GLOBALS['KTRootUrl']."/thirdparty/icon-theme/16x16/mimetypes/x-directory-normal.png"; //icon path
    }

    // get a document information
    function getDocumentData($iUserId, $iDocumentId){
    	if(!isset($_SESSION["userID"])){
			$_SESSION['userID'] = $iUserId;
		}
		// get document object
		$oDocument =& Document::get($iDocumentId);

		$cv = $oDocument->getContentVersionId();
		$mv = $oDocument->getMetadataVersionId();

		$sQuery = "SELECT dcv.document_id AS id, dmver.name AS name, dcv.filename AS filename, c.name AS author, o.name AS owner, dtl.name AS type, dwfs.name AS workflow_status " .
				"FROM documents AS d LEFT JOIN document_content_version AS dcv ON d.id = dcv.document_id " .
				"LEFT JOIN users AS o ON d.owner_id = o.id " .
				"LEFT JOIN users AS c ON d.creator_id = c.id " .
				"LEFT JOIN document_metadata_version AS dmv ON d.id = dmv.document_id " .
				"LEFT JOIN document_types_lookup AS dtl ON dmv.document_type_id = dtl.id " .
				"LEFT JOIN document_metadata_version AS dmver ON d.id = dmver.document_id " .
				"LEFT JOIN workflow_states AS dwfs ON dmver.workflow_state_id = dwfs.id " .
				"WHERE d.id = ? " .
				"AND dmver.id = ? " .
				"AND dcv.id = ? " .
				"LIMIT 1";

		$aParams = array($iDocumentId, $mv, $cv);
        $aDocumentData = DBUtil::getResultArray(array($sQuery, $aParams));
        if($aDocumentData){
			return $aDocumentData;
        }
    }

    // get a folder information
    function getFolderData($iFolderId){
		$sQuery = "SELECT f.id AS id, f.name AS name, f.name AS filename, c.name AS author, o.name AS owner, f.description AS description " .
				"FROM folders AS f " .
				"LEFT JOIN users AS o ON f.owner_id = o.id " .
				"LEFT JOIN users AS c ON f.creator_id = c.id " .
				"WHERE f.id = ? " .
				"LIMIT 1";

		$aParams = array($iFolderId);
        $aFolderData = DBUtil::getResultArray(array($sQuery, $aParams));
        if($aFolderData){
			return $aFolderData;
        }
    }

    // get a listing of the latest 3 transactions for a document
    function getDocumentTransactions($iDocumentId){
    	$sQuery = "SELECT DT.datetime AS datetime, 'Document' AS type, DMV.name, D.full_path AS fullpath, DTT.name AS transaction_name, U.name AS user_name, DT.version AS version, DT.comment AS comment " .
    			"FROM document_transactions AS DT INNER JOIN users AS U ON DT.user_id = U.id " .
    			"INNER JOIN document_transaction_types_lookup AS DTT ON DTT.namespace = DT.transaction_namespace " .
    			"LEFT JOIN document_metadata_version AS DMV ON DT.document_id = DMV.document_id " .
    			"LEFT JOIN documents AS D ON DT.document_id = D.id " .
    			"WHERE DT.document_id = ? " .
    			"ORDER BY DT.datetime DESC " .
    			"LIMIT 4";

    	$aParams = array($iDocumentId);
    	$aDocumentTransactions = DBUtil::getResultArray(array($sQuery, $aParams));
    	if($aDocumentTransactions){
			return $aDocumentTransactions;
        }
    }

    // Get a listing of the latest 3 transactions for a folder
    function getFolderTransactions($iFolderId){
    	$sQuery = "SELECT FT.datetime AS datetime, 'Folder' AS type, F.name, F.full_path AS fullpath, DTT.name AS transaction_name, U.name AS user_name, FT.comment AS comment " .
    			"FROM folder_transactions AS FT LEFT JOIN users AS U ON FT.user_id = U.id " .
    			"LEFT JOIN document_transaction_types_lookup AS DTT ON DTT.namespace = FT.transaction_namespace " .
    			"LEFT JOIN folders AS F ON FT.folder_id = F.id " .
    			"WHERE FT.folder_id = ? " .
    			"ORDER BY FT.datetime DESC " .
    			"LIMIT 4";

    	$aParams = array($iFolderId);
    	$aFolderTransactions = DBUtil::getResultArray(array($sQuery, $aParams));
    	if($iFolderId){
			return $aFolderTransactions;
        }
    }
}
?>
