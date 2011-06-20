<?php
/**
 * $Id: $
 *
 * The contents of this file are subject to the KnowledgeTree
 * Commercial Editions On-Premise License ("License");
 * You may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * http://www.knowledgetree.com/about/legal/
 * The terms of this license may change from time to time and the latest
 * license will be published from time to time at the above Internet address.
 *
 * This edition of the KnowledgeTree software
 * is NOT licensed to you under Open Source terms.
 * You may not redistribute this source code.
 * For more information please see the License above.
 *
 * (c) 2008, 2009, 2010 KnowledgeTree Inc.
 * All Rights Reserved.
 *
 */
session_start();
require_once("config/dmsDefaults.php");
require_once(KT_LIB_DIR . '/config/config.inc.php');
/**
 * Utility class to switch between support portals
 *
 */
class SupportUtil {
    static function getSupport()
    {
		$oConfig = KTConfig::getSingleton();
    	$sDefault = 'zendesk';
    	$klass = $oConfig->get('support/manager', $sDefault);
    	
		require_once("$klass.php");
    	$singleton = new $klass;
	}
}

SupportUtil::getSupport();
?>