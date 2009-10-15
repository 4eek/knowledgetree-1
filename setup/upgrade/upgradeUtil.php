<?php
/**
* Upgrader Utilities Library
*
* KnowledgeTree Community Edition
* Document Management Made Simple
* Copyright(C) 2008,2009 KnowledgeTree Inc.
* Portions copyright The Jam Warehouse Software(Pty) Limited
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
*
* @copyright 2008-2009, KnowledgeTree Inc.
* @license GNU General Public License version 3
* @author KnowledgeTree Team
* @package Upgrader
* @version Version 0.1
*/

require '../../config/dmsDefaults.php';

class UpgradeUtil {	
	private $bootstrap = null;
	/**
	* Constructs migrateation object
	*
	* @author KnowledgeTree Team
	* @access public
 	*/
	public function __construct() {
		require_once("../wizard/installUtil.php");
		$this->bootstrap = new InstallUtil();
	}
	
	/**
	* Check if system needs to be upgraded
	*
	* @author KnowledgeTree Team
	* @access public
	* @param none
	* @return boolean
 	*/
	public function isSystemUpgradeed() {
		if (file_exists(dirname(__FILE__)."/upgrade")) {

			return true;
		}
		
		return false;
	}

	public function error($error) {
		$template_vars['error'] = $error;
		$file = "templates/error.tpl";
		if (!file_exists($file)) {
			return false;
		}
		extract($template_vars); // Extract the vars to local namespace
		ob_start();
		include($file);
        $contents = ob_get_contents();
        ob_end_clean();
        echo $contents;
	}
    
    /**
     * Function to send output to the browser prior to normal dynamic loading of a template after code execution
     * 
     * @param string $template The name of the template to use
     * @param array $output [optional] Optional array containing output text to be inserted into the template
     * @return 
     */
    public function flushOutput($template, $output = null) {
        if (is_array($output)) {
            foreach ($output as $key => $value) {
                $template_vars[$key] = $value;
            }
        }
        $file = "templates/" . $template;
        if (!file_exists($file)) {
            return false;
        }
        extract($template_vars); // Extract the vars to local namespace
        ob_start();
        include($file);
        $contents = ob_get_contents();
        ob_end_clean();
        echo $contents;
    }
	
	/**
	* Check if system needs to be upgraded
	*
	* @author KnowledgeTree Team
	* @access public
	* @param none
	* @return mixed
 	*/
    public function checkStructurePermissions() {
    	// Check if Wizard Directory is writable
    	if(!$this->_checkPermission(UPGRADE_DIR)) {
    		return 'wizard';
    	}

    	return true;
    }

    
	public function redirect($url, $exit = true, $rfc2616 = false)
    {
		return $this->bootstrap->redirect($url, $exit = true, $rfc2616 = false);
    }

    public function absoluteURI($url = null, $protocol = null, $port = null)
    {
		return $this->bootstrap->absoluteURI($url = null, $protocol = null, $port = null);
    }
    
    /**
     * Check whether a given directory / file path exists and is writable
     *
	 * @author KnowledgeTree Team
     * @access private
     * @param string $dir The directory / file to check
     * @param boolean $create Whether to create the directory if it doesn't exist
     * @return array The message and css class to use
     */
    private function _checkPermission($dir)
    {
        if(is_readable($dir) && is_writable($dir)) {
			return true;
        } else {
        	return false;
        }

    }
    
    public function pexec($aCmd, $aOptions = null) {
		return $this->bootstrap->pexec($aCmd, $aOptions = null);
    }
    
    public function create_restore_stmt($targetfile)
    {
        $oKTConfig =& KTConfig::getSingleton();
    
        $adminUser = $oKTConfig->get('db/dbAdminUser');
        $adminPwd = $oKTConfig->get('db/dbAdminPass');
        $dbHost = $oKTConfig->get('db/dbHost');
        $dbName = $oKTConfig->get('db/dbName');
        $dbPort = trim($oKTConfig->get('db/dbPort'));
        if ($dbPort=='' || $dbPort=='default')$dbPort = get_cfg_var('mysql.default_port');
        if (empty($dbPort)) $dbPort='3306';
        $dbSocket = trim($oKTConfig->get('db/dbSocket'));
        if (empty($dbSocket) || $dbSocket=='default') $dbSocket = get_cfg_var('mysql.default_socket');
        if (empty($dbSocket)) $dbSocket='../tmp/mysql.sock';
    
        $dir = $this->resolveMysqlDir();
    
        $info['dir']=$dir;
    
        $prefix='';
        if (OS_UNIX) {
            $prefix .= "./";
        }
    
        if (@stat($dbSocket) !== false) {
            $mechanism="--socket=\"$dbSocket\"";
        }
        else {
            $mechanism="--port=\"$dbPort\"";
        }
    
        $tmpdir = $this->resolveTempDir();
    
        $stmt = $prefix ."mysqladmin --user=\"$adminUser\" -p $mechanism drop  \"$dbName\"<br/>";
        $stmt .= $prefix ."mysqladmin --user=\"$adminUser\" -p $mechanism create  \"$dbName\"<br/>";
    
    
        $stmt .= $prefix ."mysql --user=\"$adminUser\" -p $mechanism \"$dbName\" < \"$targetfile\"\n";
        $info['display']=$stmt;
    
    
        $stmt = $prefix ."mysqladmin --user=\"$adminUser\" --force --password=\"$adminPwd\" $mechanism drop  \"$dbName\"\n";
        $stmt .= $prefix ."mysqladmin --user=\"$adminUser\" --password=\"$adminPwd\" $mechanism create  \"$dbName\"\n";
    
        $stmt .=  $prefix ."mysql --user=\"$adminUser\" --password=\"$adminPwd\" $mechanism \"$dbName\" < \"$targetfile\"";
        $info['cmd']=$stmt;
        return $info;
    }
    
    public function resolveMysqlDir()
    {
        // possibly detect existing installations:
    
        if (OS_UNIX) {
            $dirs = array('/opt/mysql/bin','/usr/local/mysql/bin');
            $mysqlname ='mysql';
        }
        else
        {
            $dirs = explode(';', $_SERVER['PATH']);
            $dirs[] ='c:/Program Files/MySQL/MySQL Server 5.0/bin';
            $dirs[] = 'c:/program files/ktdms/mysql/bin';
            $mysqlname ='mysql.exe';
        }
    
        $oKTConfig =& KTConfig::getSingleton();
        $mysqldir = $oKTConfig->get('backup/mysqlDirectory',$mysqldir);
        $dirs[] = $mysqldir;
    
        if (strpos(__FILE__,'knowledgeTree') !== false && strpos(__FILE__,'ktdms') != false) {
            $dirs [] = realpath(dirname($FILE) . '/../../mysql/bin');
        }
    
        foreach($dirs as $dir)
        {
            if (is_file($dir . '/' . $mysqlname))
            {
                return $dir;
            }
        }
    
        return '';
    }
    
    public function resolveTempDir()
    {
        if (OS_UNIX) {
            $dir='/tmp/kt-db-backup';
        }
        else {
            $dir='c:/kt-db-backup';
        }
        $oKTConfig =& KTConfig::getSingleton();
        $dir = $oKTConfig->get('backup/backupDirectory',$dir);
    
        if (!is_dir($dir)) {
                mkdir($dir);
        }
        return $dir;
    }

}
?>