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

require_once(KT_DIR . '/plugins/ktstandard/contents/BaseIndexer.php');

class KTRtfIndexerTrigger extends KTBaseIndexerTrigger {
    var $mimetypes = array(
       'text/rtf' => true,
    );
    var $command = 'catdoc';          // could be any application.
    var $commandconfig = 'indexer/catdoc';          // could be any application.
    var $args = array("-w", "-d", "utf-8");
    var $use_pipes = true;
    
    function findLocalCommand() {
        $sCommand = KTUtil::findCommand($this->commandconfig, $this->command);
        return $sCommand;
    }
    
    function getDiagnostic() {
        if (OS_WINDOWS) {
            return _kt("The RTF indexer does not currently index RTF documents on Windows.");
        }
        $sCommand = $this->findLocalCommand();
        
        // can't find the local command.
        if (empty($sCommand)) {
            return sprintf(_kt('Unable to find required command for indexing.  Please ensure that <strong>%s</strong> is installed and in the KnowledgeTree Path.  For more information on indexers and helper applications, please <a href="%s">visit the KTDMS site</a>.'), $this->command, $this->support_url);
        }
        
        return null;
    }
}

?>
