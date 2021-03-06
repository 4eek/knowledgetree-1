<?php
/**
 * $Id$
 *
 * KnowledgeTree Community Edition
 * Document Management Made Simple
 * Copyright (C) 2008, 2009, 2010 KnowledgeTree Inc.
 *
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
 * You can contact KnowledgeTree Inc., PO Box 7775 #87847, San Francisco,
 * California 94120-7775, or email info@knowledgetree.com.
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

require_once(KT_LIB_DIR . '/actions/documentaction.inc.php');
require_once(KT_LIB_DIR . '/actions/folderaction.inc.php');

// {{{ SharedContentDocumentAction
class SharedContentDocumentAction extends KTDocumentAction {

    var $sName = 'ktcore.actions.document.sharecontent';
    var $_sShowPermission = 'ktcore.permissions.write';
	var $sDisplayName = 'Share';

    var $sIconClass = 'share';
	var $btnOrder = 4;

    function getDisplayName()
    {
    	// Check if we are in the document view and return a link,
    	// otherwise return the display name only.
    	if (is_null($this->oDocument)) {
    	    return _kt('Sharing');
    	}
    	else {
    	    return _kt('Share');

    	    /*
    	    $onclick = "javascript:{kt.app.sharewithusers.shareContentWindow(\"{$this->oDocument->getId()}\", \"D\", \"{$_SESSION['userID']}\");}";
    	    return "<a href='#' onclick='{$onclick}' >". _kt('Share') .'</a>';
    	    */
    	}
    }

    function _show() {
        $check = parent::_show();
        if($check === false) {
            return 'disabled';
        }
        return $check;
    }

    function getOnClick()
    {
        return "javascript:{kt.app.sharewithusers.shareContentWindow(\"{$this->oDocument->getId()}\", \"D\", \"{$_SESSION['userID']}\");}";
    }

	function getURL()
	{
		return '#';
	}

}
// }}}
// {{{ SharedContentDocumentAction
class SharedContentFolderAction extends KTFolderAction {

    var $sName = 'ktcore.actions.folder.sharecontent';
    var $_sShowPermission = 'ktcore.permissions.write';
	var $sDisplayName = 'Share';

    var $cssClass = 'share';

    function getDisplayName()
    {
    	return _kt('Share this Folder');
    }

    function getURL()
    {
        return "javascript:kt.app.sharewithusers.shareContentWindow('{$this->oFolder->getID()}','F','{$_SESSION['userID']}');";
    }

}
// }}}
?>
