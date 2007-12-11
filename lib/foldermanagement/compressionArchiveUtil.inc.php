<?php
/**
 * $Id:
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

/**
* Class to create and download a zip file
*/
class ZipFolder {

    var $sTmpPath = '';
    var $sZipFileName = '';
    var $sZipFile = '';
    var $aPaths = array();
    var $aReplaceKeys = array();
    var $aReplaceValues = array();
    var $sOutputEncoding = 'UTF-8';

    /**
    * Constructor
    */
    function ZipFolder($sZipFileName) {
        $this->oKTConfig =& KTConfig::getSingleton();
        $this->oStorage =& KTStorageManagerUtil::getSingleton();

        $sBasedir = $this->oKTConfig->get("urls/tmpDirectory");
        $sTmpPath = tempnam($sBasedir, 'kt_compress_zip');

        unlink($sTmpPath);
        mkdir($sTmpPath, 0700);

        $this->sTmpPath = $sTmpPath;
        $this->sZipFileName = $sZipFileName;
        $this->aPaths = array();

        $aReplace = array(
            "[" => "[[]",
            " " => "[ ]",
            "*" => "[*]",
            "?" => "[?]",
        );

        $this->aReplaceKeys = array_keys($aReplace);
        $this->aReplaceValues = array_values($aReplace);
    }

    /**
    * Add a document to the zip file
    */
    function addDocumentToZip($oDocument, $oFolder = null) {
        if(empty($oFolder)){
            $oFolder = Folder::get($oDocument->getFolderID());
        }

        $sDocPath = $oFolder->getFullPath().'/'.$oFolder->getName();
        $sDocName = $oDocument->getFileName();

        $sParentFolder = str_replace('<', '', str_replace('</', '', str_replace('>', '', sprintf('%s/%s', $this->sTmpPath, $sDocPath))));
        $newDir = $this->sTmpPath;
        $sFullPath = str_replace('<', '', str_replace('</', '', str_replace('>', '', $this->_convertEncoding($sDocPath, true))));
        foreach (split('/', $sFullPath) as $dirPart) {
            $newDir = sprintf("%s/%s", $newDir, $dirPart);
            if (!file_exists($newDir)) {
                mkdir($newDir, 0700);
            }
        }

        $sOrigFile = str_replace('<', '', str_replace('</', '', str_replace('>', '', $this->oStorage->temporaryFile($oDocument))));
        $sFilename = sprintf("%s/%s", $sParentFolder, str_replace('<', '', str_replace('</', '', str_replace('>', '', $sDocName))));
        $sFilename = $this->_convertEncoding($sFilename, true);
        copy($sOrigFile, $sFilename);

        $sPath = str_replace('<', '', str_replace('</', '', str_replace('>', '', sprintf("%s/%s", $sDocPath, $sDocName))));
        $sPath = str_replace($this->aReplaceKeys, $this->aReplaceValues, $sPath);
        $sPath = $this->_convertEncoding($sPath, true);

        $this->aPaths[] = $sPath;
        return true;
    }

    /**
    * Add a folder to the zip file
    */
    function addFolderToZip($oFolder) {
        $sFolderPath = $oFolder->getFullPath().'/'.$oFolder->getName().'/';
        $sParentFolder = str_replace('<', '', str_replace('</', '', str_replace('>', '', sprintf('%s/%s', $this->sTmpPath, $sFolderPath))));
        $newDir = $this->sTmpPath;
        $sFullPath = str_replace('<', '', str_replace('</', '', str_replace('>', '', $this->_convertEncoding($sFolderPath, true))));
        foreach (split('/', $sFullPath) as $dirPart) {
            $newDir = sprintf("%s/%s", $newDir, $dirPart);
            if (!file_exists($newDir)) {
                mkdir($newDir, 0700);
            }
        }

        $sPath = str_replace('<', '', str_replace('</', '', str_replace('>', '', sprintf("%s", $sFolderPath))));
        $sPath = str_replace($this->aReplaceKeys, $this->aReplaceValues, $sPath);
        $sPath = $this->_convertEncoding($sPath, true);

        $this->aPaths[] = $sPath;
        return true;
    }

    /**
    * Zip the temp folder
    */
    function createZipFile($bEchoStatus = FALSE) {
        if(empty($this->aPaths)){
            return PEAR::raiseError(_kt("No folders or documents found to compress"));
            //$this->addErrorMessage(_kt("No folders or documents found to compress"));
            //return false;
        }

        $sManifest = sprintf("%s/%s", $this->sTmpPath, "MANIFEST");
        file_put_contents($sManifest, join("\n", $this->aPaths));
        $sZipFile = sprintf("%s/%s.zip", $this->sTmpPath, $this->sZipFileName);
        $sZipFile = str_replace('<', '', str_replace('</', '', str_replace('>', '', $sZipFile)));
        $sZipCommand = KTUtil::findCommand("export/zip", "zip");
        $aCmd = array($sZipCommand, "-r", $sZipFile, ".", "-i@MANIFEST");
        $sOldPath = getcwd();
        chdir($this->sTmpPath);
        // Note that the popen means that pexec will return a file descriptor
        $aOptions = array('popen' => 'r');
        $fh = KTUtil::pexec($aCmd, $aOptions);

        if($bEchoStatus){
            $last_beat = time();
            while(!feof($fh)) {
                if ($i % 1000 == 0) {
                    $this_beat = time();
                    if ($last_beat + 1 < $this_beat) {
                        $last_beat = $this_beat;
                        print "&nbsp;";
                    }
                }
                $contents = fread($fh, 4096);
                if ($contents) {
                    print nl2br($this->_convertEncoding($contents, false));
                }
                $i++;
            }
        }
        pclose($fh);

        // Save the zip file and path into session
        $_SESSION['zipcompression'] = KTUtil::arrayGet($_SESSION, 'zipcompression', array());
        $sExportCode = KTUtil::randomString();
        $_SESSION['zipcompression'][$sExportCode] = array(
            'file' => $sZipFile,
            'dir' => $this->oZip->sTmpPath,
        );
        $_SESSION['zipcompression']['exportcode'] = $sExportCode;

        $this->sZipFile = $sZipFile;
        return $sExportCode;
    }

    /**
    * Download the zip file
    */
    function downloadZipFile($exportCode = NULL) {
        if(!(isset($exportCode) && !empty($exportCode))) {
            $exportCode = KTUtil::arrayGet($_SESSION['zipcompression'], 'exportcode');
        }
        $aData = KTUtil::arrayGet($_SESSION['zipcompression'], $exportCode);

        if(!empty($aData)){
            $sZipFile = $aData['file'];
            $sTmpPath = $aData['dir'];
        }else{
            $sZipFile = $this->sZipFile;
            $sTmpPath = $this->sTmpPath;
        }

        if (!file_exists($sZipFile)) {
            return PEAR::raiseError(_kt('The ZIP file can only be downloaded once - if you cancel the download, you will need to reload the page.'));
        }

		header("Content-Type: application/zip");
        header("Content-Length: ". filesize($sZipFile));
        header("Content-Disposition: attachment; filename=\"" . $this->sZipFileName . ".zip" . "\"");
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: must-revalidate");
        readfile($sZipFile);
        $sTmpDir = $sTmpPath;
        KTUtil::deleteDirectory($sTmpDir);
        return true;
    }

    /**
    * Check that iconv exists and that the selected encoding is supported.
    */
    function checkConvertEncoding() {
        if(!function_exists("iconv")) {
            return PEAR::raiseError(_kt('IConv PHP extension not installed. The zip file compression could not handle output filename encoding conversion !'));
        }
        $oKTConfig = $this->oKTConfig;
        $this->sOutputEncoding = $oKTConfig->get('export/encoding', 'UTF-8');

        // Test the specified encoding
        if(iconv("UTF-8", $this->sOutputEncoding, "") === FALSE) {
            return PEAR::raiseError(_kt('Specified output encoding for the zip files compression does not exists !'));
        }
        return true;
    }

    function _convertEncoding($sMystring, $bEncode) {
    	if (strcasecmp($this->sOutputEncoding, "UTF-8") === 0) {
    		return $sMystring;
    	}
    	if ($bEncode) {
    		return iconv("UTF-8", $this->sOutputEncoding, $sMystring);
    	} else {
    		return iconv($this->sOutputEncoding, "UTF-8", $sMystring);
    	}
    }
}
?>