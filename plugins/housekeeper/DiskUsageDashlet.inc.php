<?php

/**
 * $Id: $
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

class DiskUsageDashlet extends KTBaseDashlet
{
	private $dfCmd;
	private $usage;
	private $warningPercent;
	private $urgentPercent;

	function DiskUsageDashlet()
	{
		$this->sTitle = _kt('Storage Utilization');
		$this->sClass = "ktInfo";
	}

	function is_active($oUser)
	{
		$dfCmd = KTUtil::findCommand('externalBinary/df','df');
		if ($dfCmd === false)
		{
			return false;
		}
		$this->dfCmd = $dfCmd;

		$config = KTConfig::getSingleton();
		$this->warningPercent = $config->get('DiskUsage/warningThreshold', 15);
		$this->urgentPercent = $config->get('DiskUsage/urgentThreshold', 5);

		$this->getUsage();

		return Permission::userIsSystemAdministrator();
	}

	function getUsage($refresh=false)
	{
    	$check = true;
    	// check if we have a cached result
		if (isset($_SESSION['DiskUsage']))
		{
			// we will only do the check every 5 minutes
			if (time() - $_SESSION['DiskUsage']['time'] < 5 * 60)
			{
				$check = false;
				$this->usage = $_SESSION['DiskUsage']['usage'];
			}
		}

		// we will only check if the result is not cached, or after 5 minutes
		if ($check)
		{
			$cmd = $this->dfCmd;

			if (OS_WINDOWS)
			{
				$cmd = str_replace( '/','\\',$cmd);
				$result = `"$cmd" 2>&1`;
			}
			else
			{
				$result = shell_exec($cmd." 2>&1");
			}

			$result = explode("\n", $result);

			unset($result[0]); // gets rid of headings

			$usage=array();
			foreach($result as $line)
			{
				if (empty($line)) continue;
				preg_match('/(.*)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\%\s+(.*)/', $line, $matches);
				list($line, $filesystem, $size, $used, $avail, $usedp, $mount) = $matches;

				if ($size === 0) continue;

				if ($usedp >= 100 - $this->urgentPercent)
				{
					$colour = 'red';
				}
				elseif ($usedp >= 100 - $this->warningPercent)
				{
					$colour = 'orange';
				}
                elseif ( $usedp < 100 - $this->warningPercent)
                {
                    $colour = 'none';
                }

				$usage[] = array(
						'filesystem'=>$filesystem,
						'size'=>KTUtil::filesizeToString($size),
						'used'=>KTUtil::filesizeToString($used),
						'available'=>KTUtil::filesizeToString($avail),
						'usage'=>$usedp . '%',
						'mounted'=>$mount,
						'colour'=>$colour
					);
			}

			$this->usage = $usage;

    		$_SESSION['DiskUsage']['time'] = time();
    		$_SESSION['DiskUsage']['usage'] = $this->usage;
		}
	}

	function render()
	{
		$oTemplating =& KTTemplating::getSingleton();
       	$oTemplate = $oTemplating->loadTemplate('DiskUsage');

		$oRegistry =& KTPluginRegistry::getSingleton();
		$oPlugin =& $oRegistry->getPlugin('ktcore.housekeeper.plugin');

		$dispatcherURL = $oPlugin->getURLPath('HouseKeeperDispatcher.php');


		$aTemplateData = array(
			'context' => $this,
			'usages'=>$this->usage,
			'warnPercent'=>$this->warningPercent,
			'urgentPercent'=>$this->urgentPercent,
			'dispatcherURL'=>$dispatcherURL
		);

        return $oTemplate->render($aTemplateData);
    }
}


?>
