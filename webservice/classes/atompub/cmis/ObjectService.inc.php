<?php

require_once KT_LIB_DIR . '/api/ktcmis/ktObjectService.inc.php';

/**
 * CMIS Service class which hooks into the KnowledgeTree interface
 * for processing of CMIS queries and responses via atompub/webservices
 */

class ObjectService extends KTObjectService {

    /**
     * Fetches the properties for the specified object
     *
     * @param string $repositoryId
     * @param string $objectId
     * @param boolean $includeAllowableActions
     * @param boolean $includeRelationships
     * @param boolean $returnVersion
     * @param string $filter
     * @return object CMIS object properties
     */
    public function getProperties($repositoryId, $objectId, $includeAllowableActions, $includeRelationships,
                                  $returnVersion = false, $filter = '')
    {
        $result = parent::getProperties($repositoryId, $objectId, $includeAllowableActions,
                                        $returnVersion, $filter);

        if ($result['status_code'] == 0) {
            return $result['results'];
        }
        else {
            return new PEAR_Error($result['message']);
        }
    }

    /**
     * Creates a new document within the repository
     *
     * @param string $repositoryId The repository to which the document must be added
     * @param array $properties Array of properties which must be applied to the created document object
     * @param string $folderId The id of the folder which will be the parent of the created document object
     *                         This parameter is optional IF unfilingCapability is supported
     * @param string $contentStream optional content stream data - expected as a base64 encoded string
     * @param string $versioningState optional version state value: none/checkedout/major/minor
     * @param $policies List of policy ids that MUST be applied
     * @param $addACEs List of ACEs that MUST be added
     * @param $removeACEs List of ACEs that MUST be removed
     * @return string $objectId The id of the created folder object
     */
    public function createDocument($repositoryId, $properties, $folderId = null, $contentStream = null, 
                                   $versioningState = 'none', $policies = array(), $addACEs = array(), 
                                   $removeACEs = array())
    {
        $result = parent::createDocument($repositoryId, $properties, $folderId, $contentStream, $versioningState, 
                                         $policies, $addACEs, $removeACEs);
                                         
        if ($result['status_code'] == 0) {
            return $result['results'];
        }
        else {
            return new PEAR_Error($result['message']);
        }
    }

    /**
     * Creates a new folder within the repository
     *
     * @param string $repositoryId The repository to which the folder must be added
     * @param string $typeId Object Type id for the folder object being created
     * @param array $properties Array of properties which must be applied to the created folder object
     * @param string $folderId The id of the folder which will be the parent of the created folder object
     * @return string $objectId The id of the created folder object
     */
    public function createFolder($repositoryId, $typeId, $properties, $folderId)
    {
        $result = parent::createFolder($repositoryId, $typeId, $properties, $folderId);

        if ($result['status_code'] == 0) {
            return $result['results'];
        }
        else {
            return new PEAR_Error($result['message']);
        }
    }
    
    /**
     * Fetches the content stream data for an object
     *  
     * @param string $repositoryId
     * @param string $objectId
     * @return string $contentStream (binary or text data)
     */
    function getContentStream($repositoryId, $objectId)
    {
        $result = parent::getContentStream($repositoryId, $objectId);

        if ($result['status_code'] == 0) {
            return $result['results'];
        }
        else {
            return new PEAR_Error($result['message']);
        }
    }
    
    /**
     * Moves a fileable object from one folder to another.
     * 
     * @param object $repositoryId
     * @param object $objectId
     * @param object $changeToken [optional]
     * @param object $targetFolderId
     * @param object $sourceFolderId [optional] 
     */
    public function moveObject($repositoryId, $objectId, $changeToken = '', $targetFolderId, $sourceFolderId = null)
    {
        $result = parent::moveObject($repositoryId, $objectId, $changeToken, $targetFolderId, $sourceFolderId);

        if ($result['status_code'] == 0) {
            return $result['results'];
        }
        else {
            return new PEAR_Error($result['message']);
        }
    }
    
    /**
     * Deletes an object from the repository
     * 
     * @param string $repositoryId
     * @param string $objectId
     * @param string $changeToken [optional]
     * @return boolean true on success, false on failure
     */
    // NOTE Invoking this service method on an object SHALL not delete the entire version series for a Document Object. 
    //      To delete an entire version series, use the deleteAllVersions() service
    public function deleteObject($repositoryId, $objectId, $changeToken = null)
    {
        $result = parent::deleteObject($repositoryId, $objectId, $changeToken);

        if ($result['status_code'] == 0) {
            return $result['results'];
        }
        else {
            return new PEAR_Error($result['message']);
        }
    }
    
    /**
     * Deletes an entire tree including all subfolders and other filed objects
     * 
     * @param string $repositoryId
     * @param string $objectId
     * @param string $changeToken [optional]
     * @param boolean $unfileNonfolderObject [optional] - note that since KnowledgeTree does not allow unfiling this will be ignored
     * @param boolean $continueOnFailure [optional] - note that since KnowledgeTree does not allow continue on failure this will be ignored
     * @return array $failedToDelete A list of identifiers of objects in the folder tree that were not deleted.
     */
    public function deleteTree($repositoryId, $objectId, $changeToken = null, $unfileNonfolderObject = 'delete', $continueOnFailure = false)
    {
        $result = parent::deleteTree($repositoryId, $objectId, $changeToken, $unfileNonfolderObject, $continueOnFailure);

        if ($result['status_code'] == 0) {
            return $result['results'];
        }
        else if (is_array($result['message'])) {
            return $result['message'];
        }
        else {
            return new PEAR_Error($result['message']);
        }
    }

}

?>
