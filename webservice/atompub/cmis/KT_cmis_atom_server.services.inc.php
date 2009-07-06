<?php
/**
 * AtomPub Service: children
 *
 * Returns a child tree listing starting at the root document, one level only
 * Tree structure obtained by referencing parent id
 *
 */
class KT_cmis_atom_service_children extends KT_cmis_atom_service {
	public function GET_action(){
//		//Create a new response feed
		$feed=new KT_atom_ResponseFeed_GET(KT_APP_BASE_URI);
//
//		//Invoke the KtAPI to get detail about the referenced document
//		$tree=KT_atom_service_helper::getFullTree();
//
//		//Create the atom response feed
//		foreach($tree as $item){
//			$id=$item['id'];
//			$entry=$feed->newEntry();
//			$feed->newField('id',$id,$entry);
//			foreach($item as $property=>$value){
//				$feed->newField($property,$value,$entry);
//			}
//		}
//		//Expose the responseFeed
        $feed->newField('bla','bleh',$feed);
		$this->responseFeed=$feed;
	}

	public function DELETE_action(){
//		$feed = new KT_atom_ResponseFeed_DELETE();
//		$this->responseFeed=$feed;
	}
}




/**
 * AtomPub Service: folder
 *
 * Returns detail on a particular folder
 *
 */
class KT_atom_service_folder extends KT_atom_service {
	public function GET_action(){
		//Create a new response feed
		$feed=new KT_atom_responseFeed(KT_APP_BASE_URI);

		//Invoke the KtAPI to get detail about the referenced document
		$folderDetail=KT_atom_service_helper::getFolderDetail($this->params[0]?$this->params[0]:1);

		//Create the atom response feed
		$entry=$feed->newEntry();
		foreach($folderDetail as $property=>$value){
			$feed->newField($property,$value,$entry);
		}

		//Expose the responseFeed
		$this->responseFeed=$feed;
	}
}




/**
 * AtomPub Service: document
 *
 * Returns detail on a particular document
 *
 */
class KT_atom_service_document extends KT_atom_service {
	public function GET_action(){
		//Create a new response feed
		$feed=new KT_atom_responseFeed(KT_APP_BASE_URI);

		//Invoke the KtAPI to get detail about the referenced document
		$docDetail=KT_atom_service_helper::getDocumentDetail($this->params[0]);

		//Create the atom response feed
		$entry=$feed->newEntry();
		foreach($docDetail['results'] as $property=>$value){
			$feed->newField($property,$value,$entry);
		}
		//Add a downloaduri field manually
		$feed->newField('downloaduri',urlencode(KT_APP_SYSTEM_URI.'/action.php?kt_path_info=ktcore.actions.document.view&fDocumentId='.$docDetail['results']['document_id']),$entry);

		//Expose the responseFeed
		$this->responseFeed=$feed;
	}
}
?>