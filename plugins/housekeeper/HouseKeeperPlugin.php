<?php

/**
 * $Id
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
 */

class HouseKeeperPlugin extends KTPlugin
 {
	var $autoRegister = true;
 	var $sNamespace = 'ktcore.housekeeper.plugin';

 	var $folders = array();

 	function HouseKeeperPlugin($sFilename = null)
 	{
	 	parent::KTPlugin($sFilename);

        $this->sFriendlyName = _kt('Housekeeper');

        $config = KTConfig::getSingleton();
        $tempDir = $config->get('urls/tmpDirectory');
        $cacheDir = $config->get('cache/cacheDirectory');
        $logDir = $config->get('urls/logDirectory');
        $docsDir = $config->get('urls/documentRoot');
        $luceneDir = $config->get('indexer/luceneDirectory');

        $systemDir = OS_UNIX?'/tmp':'c:/windows/temp';

        $this->folders = array(
        	array(
        		'name'=>_kt('Smarty Cache'),
        		'folder'=>$tempDir,
        		'pattern'=>'^%%.*',
        		'canClean'=>true
        	),
        	array(
        		'name'=>_kt('KnowledgeTree Cache'),
        		'folder'=>$cacheDir,
        		'pattern'=>'',
        		'canClean'=>true
        	),
        	array(
        		'name'=>_kt('KnowledgeTree Logs'),
        		'folder'=>$logDir,
        		'pattern'=>'.+\.txt$',
        		'canClean'=>true
        	));

        	$this->folders[] =
        	array(
        		'name'=>_kt('System Temporary Folder'),
        		'folder'=>$systemDir,
        		'pattern'=>'(sess_.+)?(.+\.log$)?',
        		'canClean'=>true
        	);

        $this->folders[] =
        	array(
        		'name'=>_kt('KnowledgeTree Documents'),
        		'folder'=>$docsDir,
        		'pattern'=>'',
        		'canClean'=>false
        	);
        $this->folders[] =
        	array(
        		'name'=>_kt('KnowledgeTree Document Index'),
        		'folder'=>$luceneDir,
        		'pattern'=>'',
        		'canClean'=>false
        	);

    }

 	function getDirectories()
 	{
 		return $this->folders;
 	}

    function getDirectory($folder)
    {
    	foreach($this->folders as $dir)
    	{
    		if ($dir['folder'] == $folder)
    		{
    			return $dir;
    		}
    	}
    	return null;
    }

    function setup()
    {
    	if (OS_UNIX)
    	{
    		// unfortunately, df only seems to be working under linux at this stage. we had
    		// issues getting gnuwin32's df to run in the stack. the application kept on core dumping! ;(
    		$this->registerDashlet('DiskUsageDashlet', 'ktcore.diskusage.dashlet', 'DiskUsageDashlet.inc.php');
    	}
		$this->registerDashlet('FolderUsageDashlet', 'ktcore.folderusage.dashlet', 'FolderUsageDashlet.inc.php');

        $oTemplating =& KTTemplating::getSingleton();
  	 	$oTemplating->addLocation('housekeeper', '/plugins/housekeeper/templates');
    }

}

$oPluginRegistry =& KTPluginRegistry::getSingleton();
$oPluginRegistry->registerPlugin('HouseKeeperPlugin', 'ktcore.housekeeper.plugin', __FILE__);

?>