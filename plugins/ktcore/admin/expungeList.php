<?php
/**
 * $Id$
 *    
 * KnowledgeTree Open Source Edition
 * Document Management Made Simple
 * Copyright (C) 2004 - 2008 The Jam Warehouse Software (Pty) Limited
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
 */

require_once('../../../config/dmsDefaults.php');
require_once(KT_LIB_DIR . '/browse/browseutil.inc.php');

require_once(KT_LIB_DIR . '/documentmanagement/Document.inc');
require_once(KT_LIB_DIR . '/documentmanagement/DocumentTransaction.inc');
 
$aDocuments =& Document::getList("status_id=" . DELETED);

$pageNum = $_REQUEST['page'];

$items = count($aDocuments);
if(fmod($items, 10) > 0){
	$pages = floor($items/10)+1;
}else{
	$pages = ($items/10);
}
if($pageNum == 1){
	$listStart = 0;
	$listEnd = 9;
}elseif($pageNum == $pages){
	$listStart = (10*($pageNum-1));
	$listEnd = count($aDocuments)-1;
}else{
	$listStart = (10*($pageNum-1));
	$listEnd = $listStart+9;
}
for($i = $listStart; $i <= $listEnd; $i++){
	$output .=  "<tr>
	      <td><input type='checkbox' name='selected_docs[]' value='".$aDocuments[$i]->getId()."'/></td>
	      <td>".$aDocuments[$i]->getName()."</td>
	      <td>".$aDocuments[$i]->getLastModifiedDate()."</td>
	      <td>".$aDocuments[$i]->getLastDeletionComment()."</td>
	    </tr>";
}
echo $output;
?>
