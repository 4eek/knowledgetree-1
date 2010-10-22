<?php
class siteapi extends client_service{
	
function uploadFile($params) {
		global $default;
		
		$documents = $params['documents'];
		
		$default->log->debug('Uploading files '.print_r($documents, true));
				
		$index = 0;
		$retDocuments = array();
		
		foreach($documents as $document){
			try
			{
		
				$baseFolderID = $document['baseFolderID'];
				
		    	$oStorage = KTStorageManagerUtil::getSingleton();
		    	
		    	$folderID = $document['folderID'];
		    	
		    	$documentTypeID = $document['docTypeID'];
		    	
		    	//file_put_contents('uploadFile.txt', "\n\r$documentTypeID", FILE_APPEND);
		    	
		    	$fileName = $document['fileName'];
		    	
		    	$sS3TempFile  = $document['s3TempFile'];
		    	
		    	$metadata = $document['metadata']; 
		    	
		    	//file_put_contents('uploadFile.txt', "\n\rmetadata".print_r($metadata, true), FILE_APPEND);
		    	
		    	$MDPack = array();
		    	//assemble the metadata and convert to fileds and fieldsets
		    	foreach($metadata as $MD) {
		    		//file_put_contents('uploadFile.txt', "\n\rMD ".print_r($MD, true), FILE_APPEND);
		    		$oField = DocumentField::get($MD['id']);
		    		
		    		//file_put_contents('uploadFile.txt', "\n\rField ".print_r($oField, true), FILE_APPEND);
		    		
		    		$MDPack[] = array(
		    			$oField,
		    			$MD['value']
	                );
		    	}
		    	
		    	//file_put_contents('uploadFile.txt', "\n\rMDPack ".print_r($MDPack, true), FILE_APPEND);
		       	
		       	$aString = "\n\rfolderID: $folderID documentTypeID: $documentTypeID fileName: $fileName S3TempFile: $sS3TempFile";
		    	
		    	$default->log->debug("uploading with options $aString");
		
		        $options['uploaded_file'] = 'true';
		
		        $oFolder = Folder::get($folderID);
		        if (PEAR::isError($oFolder)) {
		        	$default->log->error("\n\rFolder $folderID: {$oFolder->getMessage()}");
		       		throw new Exception($oFolder->getMessage());
		        }
		
		        $oUser = User::get($_SESSION['userID']);
		        if (PEAR::isError($oUser)) {
		        	$default->log->error("\n\rUser {$_SESSION['userID']}: {$oUser->getMessage()}");
		       		throw new Exception($oUser->getMessage());
		        }
		
		        $oDocumentType = DocumentType::get($documentTypeID);
		        if (PEAR::isError($oDocumentType)) {
		        	$default->log->error("\n\rDocumentType: {$oDocumentType->getMessage()}");
		       		throw new Exception($oDocumentType->getMessage());
		        }
		
		        //remove extension to generate title
		        $aFilename = explode('.', $fileName);
		        $cnt = count($aFilename);
		        $sExtension = $aFilename[$cnt - 1];
		        $title = preg_replace("/\.$sExtension/", '', $fileName);
		        
		        /*file_put_contents('uploadFile.txt', "\n\r".print_r(array(
		            'temp_file' => $sS3TempFile,
		            'documenttype' => $oDocumentType,
		            'metadata' => $metadata,
		            'description' => $title,
		            'cleanup_initial_file' => true
		        ), true), FILE_APPEND);*/
		
		        $aOptions = array(
		            'temp_file' => $sS3TempFile,
		            'documenttype' => $oDocumentType,
		            'metadata' => $MDPack,
		            'description' => $title,
		            'cleanup_initial_file' => true
		        );
		
		        if($document['doBulk']=='true'){
		        	$dir = realpath(dirname(__FILE__).'/../../../../');
		        	require_once($dir . '/plugins/ktlive/lib/import/amazons3zipimportstorage.inc.php');
					require_once($dir . '/plugins/ktlive/lib/import/amazons3bulkimport.inc.php');
					
		        	//TODO: change deb to ar
		        	
					$fileData = array();
		        	$fileData['name'] = $fileName;
		        	$fileData['tmp_name'] = $sS3TempFile;
		        	
		        	//file_put_contents('uploadFile.txt', "\n\rdocument['doBulk']", FILE_APPEND);
		        	$fs = new KTAmazonS3ZipImportStorage('', $fileData);
	        	    $response = $oStorage->headS3Object($sS3TempFile);
	        	    //file_put_contents('uploadFile.txt', "\n\rresponse $response", FILE_APPEND);
	        	    $size = 0;
	        	    if (($response instanceof ResponseCore) && $response->isOK()) {
	        	        $size = $response->header['content-length'];
	        	    }
	        	    
	        	    $aOptions = array('documenttype' => $oDocumentType,
	        	    				'metadata' => $MDPack);        	    
	        	    
					$bm = new KTAmazonS3BulkImportManager($oFolder, $fs, $oUser, $aOptions);
			        $res = $bm->import($sS3TempFile, $size);
			        //file_put_contents('uploadFile.txt', "\n\rres $res", FILE_APPEND);
			        $archives[] = $res; 
	
			        //give dummy response
			        $this->addResponse('addedDocuments', '');
		        	
		        } else {
					//add to KT     
		        	$oDocument =& KTDocumentUtil::add($oFolder, $fileName, $oUser, $aOptions);
				
		        	if (PEAR::isError($oDocument)) {	        		
	        			//$default->log->error("Document add failed {$oDocument->getMessage()}");
		        		
	        			throw new Exception($oDocument->getMessage());
		        	} 
		        	
					//get the icon path
					$mimetypeid = (method_exists($oDocument,'getMimeTypeId')) ? $oDocument->getMimeTypeId():'0';
					$iconFile = 'resources/mimetypes/newui/'.KTMime::getIconPath($mimetypeid).'.png';
					$iconExists = file_exists(KT_DIR.'/'.$iconFile);
					if($iconExists){
						$mimeIcon = str_replace('\\','/',$GLOBALS['default']->rootUrl.'/'.$iconFile);
						$mimeIcon = "background-image: url(".$mimeIcon.")";
					}else{
						$mimeIcon = '';
					}
				
					//file_put_contents('uploadFile.txt', "\n\rencoded Document ".print_r($oDocument, true), FILE_APPEND);
					
					$oOwner = User::get($oDocument->getOwnerID());
					
					$oCreator = User::get($oDocument->getCreatorID());
					$oModifier = User::get($oDocument->getModifiedUserId());
				
					//assemble the item
					$item['baseFolderID'] = $baseFolderID;
					$item['id'] = $oDocument->getId();
					$item['owned_by'] = $oOwner->getName();
					$item['created_by'] = $oCreator->getName();
					$item['modified_by'] = $oModifier->getName();
					$item['filename'] = $fileName;
					$item['title'] = $oDocument->getName();
					$item['mimeicon'] = $mimeIcon;
					$item['created_date'] = $oDocument->getCreatedDateTime();
					$item['modified_date'] = $oDocument->getLastModifiedDate();
				
					$json['success'] = $item;
					
					$retDocuments[] = json_encode($json);
				
					//file_put_contents('uploadFile.txt', "\n\r".print_r($retDocuments, true), FILE_APPEND);
					
					$this->addResponse('addedDocuments', $retDocuments);
	        	}
					
						        
			}
	        catch(Exception $e) {
	        	$default->log->error("Document add failed {$e->getMessage()}");
	        	
	        	//construct error message
        		$item['message'] = $e->getMessage();
        		$item['filename'] = $fileName;
        		$json['error'] = $item;
        		
        		$retDocuments[] = json_encode($json);
        		$this->addResponse('addedDocuments', $retDocuments);
	        }
		}
		
		//$this->addResponse('addedDocuments', $retDocuments);
	}
	
	/**
	 * Check whether the specified document type has required fields
	 * @param $params
	 * @return unknown_type
	 */
	public function docTypeHasRequiredFields($params){
		$docType=$params['docType'];
		
		$aGenericFieldsetIds = KTFieldset::getGenericFieldsets(array('ids' => false));
        $aSpecificFieldsetIds = KTFieldset::getForDocumentType($docType, array('ids' => false));
        $fieldSets = kt_array_merge($aGenericFieldsetIds, $aSpecificFieldsetIds);	
        
		$hasRequiredFields = false;
	    
	    foreach($fieldSets as $fieldSet){
			$fields=$fieldSet->getFields();
			//fwrite($fh, "\r\nfields ".print_r($fields, true));
			foreach($fields as $field){
				if ($field->getIsMandatory()) {
					$hasRequiredFields = true;
					break;
				}
			}
	    }
		
		$this->addResponse('hasRequiredFields',$hasRequiredFields);
	}
	
	/**
	 * Get all fields for the specified DocType
	 * @param $params
	 * @return unknown_type
	 */
	public function docTypeFields($params){
		$type=$params['type'];
		$filter=is_array($params['filter'])?$params['filter']:NULL;
		$oDT=DocumentType::get($type);
		
		$aGenericFieldsetIds = KTFieldset::getGenericFieldsets(array('ids' => false));
        $aSpecificFieldsetIds = KTFieldset::getForDocumentType($oDT->getID(), array('ids' => false));
        $fieldSets = kt_array_merge($aGenericFieldsetIds, $aSpecificFieldsetIds);		
		
		$ret=array();
		foreach($fieldSets as $fieldSet){
			$ret[$fieldSet->getID()]['properties']=$fieldSet->getProperties();
			$fields=$fieldSet->getFields();
			foreach($fields as $field){
				$properties=$field->getProperties();
				
				/*if(isset($properties['has_lookup'])) {
					if($properties['data_type']=='LARGE TEXT'){
						file_put_contents('docTypeFields.txt', "\n\rI have large text ".$properties['name'], FILE_APPEND);
					}	
				}*/
				
				if(isset($properties['has_lookup'])) {
					if($properties['has_lookup']==1){
						if($properties['has_lookuptree']==1){
							//need to recursively populate tree lookup fields!
							$properties['tree_lookup_values'] = $this->get_metadata_tree($field->getId());
						} else {
							$properties['lookup_values'] = $this->get_metadata_lookup($field->getId());
					
						}
					}
				}
				
				if(isset($properties['has_inetlookup'])) { 
					if($properties['has_inetlookup']==1) {
						if($properties['inetlookup_type']=="multiwithlist") {
							$properties['multi_lookup_values'] = $this->get_metadata_lookup($field->getId());
						} else if($properties['inetlookup_type']=="multiwithcheckboxes") {
							$properties['checkbox_lookup_values'] = $this->get_metadata_lookup($field->getId());
						}
					}
				}
				
				if(is_array($filter)){
					$requirements=true;
					foreach($filter as $elem=>$value){
						if($properties[$elem]!=$value)$requirements=false;
					}
					if($requirements)$ret[$fieldSet->getID()]['fields'][$field->getID()]=$properties;
				}else{
					$ret[$fieldSet->getID()]['fields'][$field->getID()]=$properties;
				}
			}
		}
		$this->addResponse('fieldsets',$ret);
	}
	
	/**
	 * Get the required fields for the specified docType
	 * @param $params
	 * @return unknown_type
	 */
	public function docTypeRequiredFields($params){
		$nparams=$params;
		$nparams['filter']=array(
			'is_mandatory'=>1
		);
		$this->docTypeFields($nparams);
	}
	
	
	public function getDocTypes($params){
		$types=DocumentType::getList();
		$ret=array();
		foreach($types as $type){
			$ret[$type->aFieldArr['id']]=$type->aFieldArr;
		}
		$this->addResponse('documentTypes',$ret);
	}
	
	/**
	* This returns an array for a metadata tree lookup or an error object.
	*
    * @author KnowledgeTree Team
	* @access public
	* @param integer $fieldid The field id to get metadata for
	* @return array|object $results SUCCESS - the array of metedata for the field | FAILURE - an error object
	*/
	public function get_metadata_lookup($fieldid)
	{
		$sql = "SELECT id, name FROM metadata_lookup WHERE disabled=0 AND document_field_id=$fieldid ORDER BY id";
		$rows = DBUtil::getResultArray($sql);
		/*if (is_null($rows) || PEAR::isError($rows))
		{
			$results = new KTAPI_Error(KTAPI_ERROR_INTERNAL_ERROR, $rows);
		}
		else
		{*/
		$results = array();
		foreach($rows as $row)
		{
			//need to prepend "id" otherwise it sees it as the i-th element of the array!
			$results[] = array('id'.$row['id']=> $row['name']);
		}
		//}
		return json_encode($results);
	}
	
	/**
	* This returns a metadata tree or an error object.
	*
    * @author KnowledgeTree Team
	* @access public
	* @param integer $fieldid The id of the tree field to get the metadata for
	* @return array|object $results SUCCESS - the array of metadata for the field | FAILURE - an error object
	*/
	public function get_metadata_tree($fieldid, $parentid=0)
	{
		//$myFile = "siteapi.txt";
		//$fh = fopen($myFile, 'a');
		
		$sql = "(SELECT mlt.metadata_lookup_tree_parent AS parentid, ml.treeorg_parent AS treeid, mlt.name AS treename, ml.id AS id, ml.name AS fieldname 
				FROM metadata_lookup ml 
				INNER JOIN (metadata_lookup_tree mlt) ON (ml.treeorg_parent = mlt.id) 
				WHERE ml.disabled=0 AND ml.document_field_id=$fieldid)
				UNION
				(SELECT -1 AS parentid, 0 AS treeid, \"Root\" AS treename, ml.id AS id, ml.name AS fieldname
				FROM metadata_lookup ml 
				LEFT JOIN (metadata_lookup_tree mlt) ON (ml.treeorg_parent = mlt.id) 
				WHERE ml.disabled=0 AND ml.document_field_id=$fieldid AND (ml.treeorg_parent IS NULL OR ml.treeorg_parent = 0))
				ORDER BY parentid, id";
		$rows = DBUtil::getResultArray($sql);
		
		$results = array();
		
		if (sizeof($rows) > 0) {			
			$results = $this->convertToTree($rows);			
		}
		
		//fclose($fh);
				
		return json_encode($results);
	}
	
	private function convertToTree(array $flat) {
		$idTree = 'treeid';
		$idField = 'id';
		$parentIdField = 'parentid';

		$root = 0;
		
	    $indexed = array();
	    // first pass - get the array indexed by the primary id
	   	foreach ($flat as $row) {
        	$treeID = $row[$idTree];
        	if (!isset($indexed[$treeID])) {
        		$indexed[$treeID] = array('treeid' => $treeID,
        									'parentid' => $row[$parentIdField],
        									'treename' => $row['treename'],
        									'type' => 'tree');//$row;
	        	$indexed[$treeID]['fields'] = array();
        	}
	        
	        $indexed[$treeID]['fields'][$row[$idField]] = array('fieldid' => $row[$idField],
	        													'parentid' => $treeID,
	        													'name' =>  $row['fieldname'],
	        													'type' => 'field');
	        
	        if ($row[$parentIdField] < $root) {
	        	$root = $row[$parentIdField];
	        }
	    }
	    
	    //file_put_contents('convertToTree.txt', "\n\rroot $root ".print_r($indexed, true), FILE_APPEND);
	    
	    //second pass
	    //$root = 0;
	    foreach ($indexed as $id => $row) {	    	
	        $indexed[$row[$parentIdField]]['fields'][$id] =& $indexed[$id];
	    }
	    
	    $results = array($root => $indexed[$root]);
	    
	    return $results;
	} 
	
	
	/**
	 * Get the subfolders of the specified folder
	 * @param $params
	 * @return unknown_type
	 */
	public function getSubFolders($params){
		$folderId=isset($params['folderId']) ? $params['folderId'] : 1;
		$filter=isset($params['fields']) ? $params['fields'] : '';
		$options = array( 'orderby'=>'name' );
		$folders = Folder::getList ( array ('parent_id = ?', $folderId ), $options );
		$subfolders=array();
		foreach($folders as $folder){
			$subfolders[$folder->aFieldArr['id']]=$this->filter_array($folder->aFieldArr,$filter,false);
		}	
		$this->addResponse('children',$subfolders);
	}
	
	/**
	 * Get the ancestors and direct descendants of the specified folder;
	 * @param $params
	 * @return unknown_type
	 */
	public function getFolderHierarchy($params){
		$folderId=$params['folderId'];
		$filter=isset($params['fields']) ? $params['fields'] : '';

		$oFolder = Folder::get($folderId);
		$ancestors = array();
		
		if ($oFolder) {
			
			if ($oFolder->getParentFolderIDs() != '') {
				$ancestors=($this->ext_explode(",",$oFolder->getParentFolderIDs()));
				$ancestors=Folder::getList(array('id IN ('.join(',',$ancestors).')'),array());
				$parents=array();
				
				foreach($ancestors as $obj){
					$parents[$obj->getID()]=$this->filter_array($obj->aFieldArr,$filter,false);
				}
			}
		}
		
		$this->addResponse('currentFolder',$this->filter_array($oFolder->_fieldValues(),$filter,false));
		$this->addResponse('parents', $parents);
		$this->addResponse('amazoncreds', $this->getAmazonCredentials());
		
		$this->getSubFolders($params);
	}
	
	public function getAmazonCredentials()
	{
		require_once(KT_LIVE_DIR . '/thirdparty/AWS_S3_PostPolicy/AWS_S3_PostPolicy.php');
		
		/* Amazon Prep Work */
		ConfigManager::load('/etc/ktlive.cnf', KT_LIVE_DIR . '/config/config-path');
        if (ConfigManager::error()) {
        	global $default;
        	$default->log->error("Configuration file not found.");
        }
		// load amazon authentication information
        $aws = ConfigManager::getSection('aws');
		
		
        $buckets = ConfigManager::getSection('buckets');
		$bucket = $buckets['accounts'];
		
		$oUser = User::get($_SESSION['userID']);
		$username = $oUser->getUserName();
		$randomfile = mt_rand();// . '_';
		$aws_tmp_path = ACCOUNT_NAME . '/' . 'tmp/' . $username . '/';
		
		
		
		/* OVERRIDE FOR TESTING */
		//$bucket = 'testa';
		//$aws_tmp_path = 'martin/';
		
		
		
		
		
		// TODO : Is there a callback handler? Create one.
		$success_action_redirect = KTLiveUtil::getServerUrl() . '/plugins/ktlive/webservice/callback.php';
		$aws_form_action = 'https://' . $bucket . '.s3.amazonaws.com/';
		
		// Create a new POST policy document
		$s3policy = new Aws_S3_PostPolicy($aws['key'], $aws['secret'], $bucket, 86400);
		$s3policy->addCondition('', 'acl', 'private')
				 ->addCondition('', 'bucket', $bucket)
				 ->addCondition('starts-with', '$key', $aws_tmp_path)
				 ->addCondition('starts-with', '$Content-Type', '')
				 ->addCondition('', 'success_action_redirect', $success_action_redirect);
		
		
		return array(
			'formAction' => $aws_form_action,
			'awstmppath'				=> $aws_tmp_path,
			'randomfile'				=> $randomfile,
			
			'AWSAccessKeyId' 			=> $s3policy->getAwsAccessKeyId(),
			'acl'            			=> $s3policy->getCondition('acl'),
			'policy'         			=> $s3policy->getPolicy(true),
			'signature'      			=> $s3policy->getSignedPolicy(),
			'success_action_redirect'   => $s3policy->getCondition('success_action_redirect'),
		);
	}
	
}