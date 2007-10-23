<?php

/**
 * $Id$
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

require_once(KT_LIB_DIR . '/plugins/plugin.inc.php');
require_once(KT_LIB_DIR . '/plugins/pluginregistry.inc.php');
require_once(KT_LIB_DIR . '/dashboard/dashlet.inc.php');

class KTWebDAVDashletPlugin extends KTPlugin {
    var $sNamespace = "ktstandard.ktwebdavdashlet.plugin";
    var $autoRegister = true;

    function KTWebDAVDashletPlugin($sFilename = null) {
        $res = parent::KTPlugin($sFilename);
        $this->sFriendlyName = _kt('WebDAV Dashlet Plugin');
        return $res;
    }        

    function setup() {
        $this->registerDashlet('KTWebDAVDashlet', 'ktstandard.ktwebdavdashlet.dashlet', __FILE__);

        require_once(KT_LIB_DIR . "/templating/templating.inc.php");
        $oTemplating =& KTTemplating::getSingleton();
    }
}

class KTWebDAVDashlet extends KTBaseDashlet {
    var $sClass = "ktInfo";
    
    function KTWebDAVDashlet( ) {
        $this->sTitle = "WebDAV Connection Information";
    }

    function render() {
        $oTemplating =& KTTemplating::getSingleton();
        $oTemplate = $oTemplating->loadTemplate('ktstandard/ktwebdavdashlet/dashlet');

	$oConfig =& KTConfig::getSingleton();
	$bSSL = $oConfig->get('sslEnabled', false);
	$sRoot = $oConfig->get('rootUrl');

	if($bSSL) { $sProtocol = 'https'; }
	else { $sProtocol = 'http'; }

	$sURL = $sProtocol . '://' . $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $sRoot . "/";

        $aTemplateData = array(
            'url' => $sURL,
        );
        return $oTemplate->render($aTemplateData);
    }
}

$oPluginRegistry =& KTPluginRegistry::getSingleton();
$oPluginRegistry->registerPlugin('KTWebDAVDashletPlugin', 'ktstandard.ktwebdavdashlet.plugin', __FILE__);
?>
