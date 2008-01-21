<?php
/**
 * $Id:$
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
 *
 */
chdir(dirname(__FILE__));
require_once('../config/dmsDefaults.php');
require_once(KT_LIB_DIR . '/database/dbutil.inc');

// Set the time limit to 0 to prevent the script timing out
set_time_limit(0);


/* ** Set up functions ** */

// Calculate the next run time based on the frequency of iteration and the given time
function calculateRunTime($sFreq, $iTime) {

    switch($sFreq){
        case 'monthly':
            $iDays = date('t');
            $iDiff = (60*60)*24*$iDays;
            break;
        case 'weekly':
            $iDiff = (60*60)*24*7;
            break;
        case 'daily':
            $iDiff = (60*60)*24;
            break;
        case 'hourly':
            $iDiff = (60*60);
            break;
        case 'half_hourly':
            $iDiff = (60*30);
            break;
        case 'quarter_hourly':
            $iDiff = (60*15);
            break;
        case '10mins':
            $iDiff = (60*10);
            break;
        case '5mins':
            $iDiff = (60*5);
            break;
        case '1min':
            $iDiff = 60;
            break;
        case '30secs':
            $iDiff = 30;
            break;
        case 'once':
            $iDiff = 0;
            break;
        }
    $iNextTime = $iTime + $iDiff;
    return $iNextTime;
}

// Update the task information in the database
function updateTask($aFieldValues, $iId) {
    DBUtil::autoUpdate('scheduler_tasks', $aFieldValues, $iId);
}

// Get the list of tasks due to be run from the database
function getTaskList() {
    $now = date('Y-m-d H:i:s'); //time();

    $query = "SELECT * FROM scheduler_tasks WHERE is_complete = 0 AND run_time < '{$now}' AND status != 'disabled'";

    $result = DBUtil::getResultArray($query);

    if (PEAR::isError($result)){
        return false;
    }
    return $result;
}


/* ** Scheduler script ** */

global $default;

$default->log->debug('Scheduler: starting');

// Get task list
$aList = getTaskList();
if (empty($aList))
{
	$default->log->debug('Scheduler: stopping - nothing to do');
	return;
}

// Loop through tasks and run

    foreach($aList as $item)
    {
        $aUpdate = array();
        $iEnd = 0; $iStart = 0; $iDuration = 0;
        $sFreq = ''; $sParameters = '';
        $retval = TRUE;

        // Set up start variables
        $sTask = $item['task'];
        $sTaskUrl = $item['script_url'];
        $iDuration = $item['run_duration'];
        $sFreq = $item['frequency'];
        $sParameters = $item['script_params'];

        // Check if script is windows or *nix compatible
        $ext = pathinfo($sTaskUrl, PATHINFO_EXTENSION);
        $script = substr($sTaskUrl,0,-strlen($ext)-1);

        if(OS_WINDOWS)
        {
        	$mapping = array('sh'=>'bin','bat'=>'exe');
        	if (array_key_exists($ext, $mapping))
        	{
        		$sTaskUrl = $script . '.' . $mapping[$ext];
        	}
        }
        else
        {
        	$mapping = array('bat'=>'sh', 'exe'=>'bin');

        	if (array_key_exists($ext, $mapping))
        	{
        		switch ($ext)
        		{
        			case 'exe':
        				if (is_executable(KT_DIR . '/' . $script))
        				{
        					$sTaskUrl = $script;
        					break;
        				}
        			default:
        				$sTaskUrl = $script . '.' . $mapping[$ext];
        		}
        	}

        	if (!is_executable(KT_DIR . '/' . $script) && $ext != 'php')
        	{
        		$default->log->error("Scheduler: The script '{$sTaskUrl}' is not executable.");
        		continue;
        	}
        }

        $file = realpath(KT_DIR . '/' . $sTaskUrl);

        if ($file === false)
        {
        	$default->log->error("Scheduler: The script '{$sTaskUrl}' cannot be resolved.");
            continue;
        }

        $iTime = time();
        $iStart = KTUtil::getBenchmarkTime();

        // Run the script

        $cmd = "\"$file\" {$sParameters}";

        if ($ext == 'php')
        {
        	$config = KTConfig::getSingleton();
        	$phpPath = $config->get('externalBinary/php');

        	// being protective as some scripts work on relative paths
        	$dirname = dirname($file);
        	chdir($dirname);

        	$cmd = "\"$phpPath\" $cmd";
        }

        if (OS_WINDOWS)
		{   $default->log->info("Scheduler - dirname: $dirname cmd: $cmd");
			//$WshShell = new COM("WScript.Shell");
			//$res = $WshShell->Run($cmd, 0, true);

			 KTUtil::pexec($cmd);

		}
		else
		{
			 $res = shell_exec($cmd." 2>&1");
		}

		// On completion - reset run time
        $iEnd = KTUtil::getBenchmarkTime();
        $iDuration = number_format($iEnd - $iStart,2);

		if (!empty($res))
		{
			$default->log->info("Scheduler - Task: $sTask");
			$default->log->info("Scheduler - Command: $cmd");
			$default->log->info("Scheduler - Output: $res");
			$default->log->info("Scheduler - Background tasks should not produce output. Please review why this is producing output.");
		}
		else
		{
			$default->log->debug("Scheduler - Task: {$sTask} completed in {$iDuration}s.");
		}

        if(($sFreq == 'once' || empty($sFreq)) && $retval !== FALSE)
        {
            // Set is_complete to true
            $aUpdate['is_complete'] = '1';
        }
        else
        {
            $iNextTime = calculateRunTime($sFreq, $iTime);
            $aUpdate['run_time'] = date('Y-m-d H:i:s', $iNextTime);
        }

        $aUpdate['previous_run_time'] = date('Y-m-d H:i:s', $iTime);
        $aUpdate['run_duration'] = $iDuration;

        updateTask($aUpdate, $item['id']);
    }

$default->log->debug('Scheduler: stopping');

?>