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

require_once(realpath(dirname(__FILE__) . '/../../queueEvent.php'));

class pdfEvent extends queueEvent 
{
	/**
	 * List of event dependencies
	 * @var array
	 */
	public $list_of_dependencies = array();
	/**
	 * Parameters to be passed with event
	 * @var array
	 */
	public $list_of_parameters = array();
	/**
	 * Callbacks to be envoked
	 * @var array
	 */
	public $list_of_callbacks = array();
	
    /**
    * Construct pdf generator Event
    *
    * @author KnowledgeTree Team
    * @access public
    * @param none
    * @return
    */
	function __construct() 
	{
		parent::setName('pdfEvent');
		parent::setMessage('PDFConvert.run');
	}
	
    /**
    * Create parameters needed by event
    *
    * @author KnowledgeTree Team
    * @access public
    * @param none
    * @return
    */
	public function buildParameters() 
	{
		require_once(dirname(__FILE__) . '/../../../../../config/dmsDefaults.php');
		$this->addParameter('src_file', $this->getSrcFile());
		$this->addParameter('dest_file', $this->getDestFile());
		$this->addParameter('filetype', $this->getFileType());
		$this->addParameter('mimetype', $this->getMimeType());
	}
	
    /**
    * 
    *
    * @author KnowledgeTree Team
    * @access public
    * @param none
    * @return
    */
	private function getDocId() 
	{
		return $this->document->getId();
	}
	
    /**
    * Create pdf source url
    *
    * @author KnowledgeTree Team
    * @access public
    * @param none
    * @return
    */
	private function getSrcFile() 
	{
		global $default;
		$fileSystemRoot = $default->fileSystemRoot;
		$documentRoot = $default->documentRoot;
		$docFile = $documentRoot . '/' . $this->document->getStoragePath();
		$srcFile = str_replace($fileSystemRoot.'/','http://' . $default->serverName . ':' . $default->server_port . $default->rootUrl . '/', $docFile);
		
		return $srcFile;
	}
	
    /**
    * Create pdf destination url
    *
    * @author KnowledgeTree Team
    * @access public
    * @param none
    * @return
    */
	private function getDestFile() 
	{
		global $default;
		$fileSystemRoot = $default->fileSystemRoot;
		$pdfDir = $default->pdfDirectory;
		$pdfFile = $pdfDir . '/' . $this->document->getId() . '.pdf';
		$destFile = str_replace($fileSystemRoot.'/','http://' . $default->serverName . ':'  . $default->server_port . '' . $default->rootUrl . '/', $pdfFile);
		
		return $destFile;
	}
	
    /**
    * 
    *
    * @author KnowledgeTree Team
    * @access public
    * @param none
    * @return
    */
	private function getFileType() 
	{
		return KTMime::getFileType($this->document->getMimeTypeID());
	}
	
	private function getMimeType() 
	{
		return KTMime::getMimeTypeName($this->document->getMimeTypeID());
	}
}
?>