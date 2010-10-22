/* Initializing kt.app if it wasn't initialized before */
if(typeof(kt.app)=='undefined')kt.app={};

/**
 * The multi-file upload widget. This object contains all the code
 * for the client-side management of single instance of the widget.
 */
kt.app.upload=new function(){
	//Stores the objects that deal with the individual files being uploaded. Elements in here is of type uploadStructure
	var data=this.data={};
	
	this.data.files={};
	
	//contains a list of fragments that will get preloaded
	var fragments=this.fragments=['upload.dialog','upload.dialog.item','upload.metadata.fieldset'];
	
	//contains a list of executable fragments that will get preloaded
	var execs=this.execs=['upload.doctypes','upload.metadata.dialog'];
	
	//scope protector. inside this object referrals to self happen via 'self' rather than 'this' to make sure we call the functionality within the right scope.
	var self=this;
	
	//a storage container for various DOM elements that need to be accessed repeatedly
	var elems=this.elems={};
	
	//container for qq.fileUploader (AjaxUploader2 code)
	this.uploader=null;
	
	this.uploadfolder=null;
	
	//Initializes the upload widget on creation. Currently does preloading of resources.
	this.init=function(){
		for(var idx in fragments){
			kt.api.preloadFragment(fragments[idx]);
		}
		for(var idx in execs){
			kt.api.preloadExecutable(execs[idx]);
		}
	}
	
	//Container for the EXTJS window
	this.uploadWindow=null;
	
	//TODO: removeItem also needs to remove it from the actual upload?
	
	
	//Add a file item to the list of files to upload and manage. 
	//Must not be called directly, but as a result of adding a file using AjaxUploader)
	this.addUpload=function(fileName, container, docTypeHasRequiredFields){		
		var metadata = {};
		var docTypeId = 1;
		
		if (self.data['applyMetaDataToAll'] && self.data['globalMetaData'] != undefined) {
			metadata = self.data['globalMetaData']['metadata'];
			docTypeId = self.data['globalMetaData']['docTypeID'];
			docTypeHasRequiredFields = !self.data['globalMetaDataRequiredDone'];
		}		
		
		var item=jQuery(kt.api.getFragment('upload.dialog.item'));
		jQuery(self.elems.item_container).append(item);
		
		if(fileName.length > 50){
			jQuery('.ul_filename').addClass('ellipsis');
		}
		
		var obj=new self.uploadStructure({'fileName':(fileName+''), 'elem':item, 'metadata': metadata, 'docTypeId':docTypeId, 
			'has_required_metadata': docTypeHasRequiredFields, 'required_metadata_done':!docTypeHasRequiredFields, 'parent':self});
		kt.lib.meta.set(item[0],'item',obj);
		obj.startUpload();
		
		self.data.files[fileName]=obj;
		
		//are we dealing with a possible bulk upload?
		var index = fileName.lastIndexOf('.');
		var ext = fileName.substr(index).toLowerCase();
		
		var e = kt.lib.meta.get(item[0],'item');
		
		//do we need to suggest a bulk upload?
		if(this.isBulkExtension(ext)) {
			jQuery('#'+e.options.elem[0].id+' .ul_bulk_checkbox').css('display','block');
		}
		
		return obj;
	}
		
	//check if is bulk type extension
	this.isBulkExtension = function(ext) {
		var bulkExtensions = new Array('.ar', '.bz', '.bz2', '.deb', '.gz', '.rar', '.tgz', '.tar', '.tbz', '.zip');
		
		var isBulk = false;
		
		for (var i =0; i < bulkExtensions.length; i++) {
			if (bulkExtensions[i] == ext){
				isBulk = true;
				break;
			}
		}
		
		return isBulk;
	}
	
	/*this.uniqueFileName=function(){
		var fileName='';
		var size=16;
       var alpha = "abcdefghijklmnopqrstuvwxyz1234567890_";
       var asize=alpha.length;
       for(var i=0; i<size; i++){
       	fileName=fileName+''+alpha[Math.floor(Math.random()*asize)];
       }
    
       return fileName;
	}*/
	
	//A DOM helper function that will take elem as any dom element inside a file item fragment 
	//and return the js object related to that element.
	this.getItem=function(elem){
		var e=jQuery(elem).parents('.ul_item')[0];
		var meta = kt.lib.meta.get(e,'item');
		return meta;
	}
	
	this.getMetaItem=function(elem){
		var e=jQuery(elem).parents('.metadataTable')[0];
		var meta = kt.lib.meta.get(e,'item');
		return meta;
	}
	
	//metadata is object in format {"docTypeID":docTypeID, "metadata":metadata}
	this.applyMetadataToAll=function(applyToAll, metadata, requiredDone) {
		if(applyToAll) {			
			self.data['applyMetaDataToAll'] = true;
			self.data['globalMetaData'] = metadata;
			
			//cycle through every file and apply the metadata!
			jQuery.each(self.data.files, function(key, value) {			
				value.options.metadata = metadata['metadata'];
				value.options.required_metadata_done = requiredDone;
			});
			
		} else {
			self.data['applyMetaDataToAll'] = false;
			self.data['globalMetaData'] = {};
		}
		
		self.data['globalMetaDataRequiredDone'] = requiredDone;
	}
	
	//Find the js object matching a given filename
	this.findItem=function(fileName){
		if(typeof(self.data.files[fileName])!='undefined'){
			return self.data.files[fileName];
		}
		return null;
	}
	
	this.getNodeTxt = function(html)
	{
		if (strpos(html, '<') == false) {
			nodeText = trim(html);
		} else {
			nodeText = trim(html.substr(0, strpos(html, '<')));
		}
		
		return nodeText;
	}
	
	this.getNodePath = function(folderId)
	{
		nodeInTree = jQuery('ul#loadedpath li[folderid='+folderId+']');
		
		if (folderId == 1) {
			pathToItem = ' / (Root Directory)';
		} else {
			pathToItem = kt.app.upload.getNodeTxt(nodeInTree.html());
			
			nodeInTree.parentsUntil('#loadedpath').each(function(i){
				
				if (jQuery(this).get(0).tagName == 'LI') {
					//console.log('Parent folder id '+jQuery(this).attr('folderid'));
					
					if (jQuery(this).attr('folderid') == 1) {
						pathToItem = '/'+pathToItem;
					} else {
						pathToItem = kt.app.upload.getNodeTxt(jQuery(this).html())+'/'+pathToItem;
					}
					
					
				}
			});
		}
		
		return pathToItem;
	}
	
	
	this.loadFolderPath = function(currentId)
	{
		
		html = '<ul id="currentPathStuff">';
		
		currentNode = jQuery('ul#loadedpath li[folderid='+currentId+']');
		
		if (currentId+'' != '1') {
			
			html += '<li class="folder_up" folderid="'+currentNode.parent().parent().attr('folderid')+'">[Folder Up]'+'</li>';
		}
		
		if (currentNode.length == 0) {
			// NEED TO RELOAD TREE
			//console.log('NEED TO RELOAD TREE');
		} else {
			if (currentNode.hasClass('loadedchildren')) {
				
				childItems = jQuery('ul#loadedpath li[folderid='+currentId+']>ul');
				
				if (childItems.length == 0) {
					
				} else {
					childItems.children().each(function(i){
						child = jQuery(this);
						
						nodeText = kt.app.upload.getNodeTxt(child.html());
						
						
						html += '<li folderid="'+child.attr('folderid')+'">'+nodeText+'</li>';
					});
				}
				
				html += '</ul>';
				jQuery('#folderpathchooser').html(html);
				
			} else {
				jQuery('#folderpathchooser').html('<div class="loading"></div>');
				
				kt.api.getSubFolders(currentId,function(result){
					
					if (result.data.children.length == 0) {
						//console.log('no children');
					} else {
						parentUl = jQuery('ul#loadedpath li[folderid='+currentId+'] > ul');
						
						if (parentUl.length == 0) {
							parentUl = jQuery('ul#loadedpath li[folderid='+currentId+']').append('<ul></ul>');
						}
						
						jQuery.each(result.data.children, function(i,item){
							
							if (jQuery('ul#loadedpath li[folderid='+currentId+'] > ul > li[folderid='+item.id+']').length == 0) {
								jQuery('ul#loadedpath li[folderid='+currentId+'] > ul').append('<li class="notloaded" folderid="'+item.id+'">'+item.name+'</li>');
							}
							
							
							html += '<li folderid="'+item.id+'">'+item.name+'</li>';
						});
					}
					
					jQuery('ul#loadedpath li[folderid='+currentId+']').removeClass('notloaded').addClass('loadedchildren');
					
					html += '</ul>';
					 jQuery('#folderpathchooser').html(html);
				}, function(){});
			}
		}
		
		
		
	   
	}
	
	//add the uploaded files to the repo
	this.addDocuments = function() {		
		var progressWidgetShown = false;
		
		//hide the window!
		this.hideWindow();
		
		//create array of files to add		
		filesToAdd = {};
		var i = 0;
		
		var atLeastOneSingle = false;
		var atLeastOneBulk = false;
		var hasError = false;
		
		//what folder to upload to?
		var folderID = jQuery("#currentPath").val();
		
		//iterate through files to see which are ready to be added
		jQuery.each(self.data.files, function(key, value) {
			if(!progressWidgetShown) {	// && !value.options.do_bulk_upload) {
				progressWidgetShown = true;
				//show the progress widget
				kt.app.upload.unhideProgressWidget();
			}
			
			//create the array of files to be uploaded
			if(value.options.is_uploaded) {
				var fileName = value.options['fileName'];
				var doBulk = value.options.do_bulk_upload;
				
				if (doBulk) {
					atLeastOneBulk = true;
				} else {
					atLeastOneSingle = true;
				}
				
				var docTypeID = value.options['docTypeId'];
				
				//assemble the metadata
				var metadata = {};
				var j = 0;
				jQuery.each(value.options['metadata'], function(key, value){					
					metadata[j++] = {'id':key, 'value':value};
				});
				
				fileName = encodeURIComponent(fileName);
				
				var tempFile = self.data['s3TempPath']+fileName;
				
				filesToAdd[i++] = {'baseFolderID':self.data['baseFolderID'], 'fileName':fileName, 'folderID':folderID, 'docTypeID':docTypeID, 'metadata':metadata, 's3TempFile':tempFile, 'doBulk':doBulk};
			}
		});
		
		kt.api.addDocuments(filesToAdd, function(data){
			//put this in a try...catch because error occurs if user browses away before the upload completes
			//BUT upload still does complete, error occurs because tries to add item to non-existent page
			try {
				//console.log(self.data['baseFolderID']+' '+folderID);
				//if(self.data['baseFolderID'] == folderID){
				jQuery.each(data.data.addedDocuments, function(key, value){
					//get the response from the server
					var responseJSON = jQuery.parseJSON(value);
					
					if (responseJSON.error) {
						//console.log(responseJSON.error.message);
						hasError = true;
						//errorMessage += responseJSON.error.filename+': '+responseJSON.error.message+' ';
					} else if (responseJSON.success) {
						//delete the file from the array because we don't want to upload it again!
						delete self.data.files[responseJSON.success.filename];
						
						if (responseJSON.success.baseFolderID = folderID) {						
							//now add the new item to the grid
							var item = {
								id: responseJSON.success.id,
					    		is_immutable: false,
					    		is_checkedout: false,
					    		filename: responseJSON.success.filename,
					    		title: responseJSON.success.title,
					    		owned_by: responseJSON.success.owned_by,
					    		created_by: responseJSON.success.created_by,
					    		created_date: responseJSON.success.created_date,
					    		modified_by: responseJSON.success.modified_by,
					    		modified_date: responseJSON.success.modified_date,
					    		mimeicon: responseJSON.success.mimeicon,
					    		thumbnail: '',
					    		thumbnailclass: 'nopreview'
					    	};
							
							//remove the "folder is empty" widget from the Browse View
					    	jQuery('.page .notification').remove();
							
							//now add the item to the Browse View
					    	kt.pages.browse.addDocumentItem(item);
						}
					}
			    	
				});
					
					//kt.lib.setFooter();
				//}
				
				kt.lib.setFooter();
				
				if (atLeastOneSingle && atLeastOneBulk) {
					var progressMessage = 'Files added. E-mail link to files sent.';
				} else if (atLeastOneSingle) {
					var progressMessage = 'Files added.';
				} else if (atLeastOneBulk) {
					progressMessage = ' E-mail link to files sent.';
				}				
				
				kt.app.upload.updateProgress(progressMessage);
				
				jQuery('#uploadProgress').fadeOut(10000); 
				
				if (hasError) {
					progressMessage = 'One or more files failed to upload.';
				
					kt.app.upload.updateProgress(progressMessage);
					
					jQuery('#uploadProgress').fadeIn(); 
					
					jQuery('#uploadProgress').fadeOut(10000); 
				}
				
				
				
			} catch(e){
			 //console.dir(e);
			}
			
		}, function(){}, i*30000);
		//20 seconds for each file!
		
		this.closeWindow();
	}
	
	this.closeWindow = function() {
		uploadWindow = Ext.getCmp('extuploadwindow');
		self.data = {};
		self.data.files = {};
		uploadWindow.destroy();
	}
	
	this.hideWindow = function() {
		uploadWindow = Ext.getCmp('extuploadwindow');
		uploadWindow.hide();
	}
	
	this.enableAddButton = function() {
		var btn = jQuery('#ul_actions_upload_btn');
		btn.removeAttr("disabled");
	}
	
	this.disableAddButton = function() {
		var btn = jQuery('#ul_actions_upload_btn');
    	btn.attr("disabled", "true");
	}
	
	this.unhideProgressWidget = function(){

		//need to hide the license feedback widget
		/*var activationNotice = document.getElementById('activationNotice');
		if(activationNotice != null) {
			activationNotice.style.visibility = 'hidden';
			activationNotice.style.display = 'none';
	    }*/		
		
		var progress = jQuery('.uploadProgress');
		progress.text('Adding files ...');
		progress.css('display', 'block');
		progress.css('visibility', 'visible');
		
		//jQuery('.uploadProgress .title').text('Adding files ...');
		
		progress.append('<img src="/resources/graphics/newui/large-loading.gif" style="float: right;"/>');
	}
	
	this.updateProgress = function(message){
		var progress = jQuery('.uploadProgress');
		
		//jQuery('.uploadProgress .title').text(message);

	    if(progress != null) {
	    	if (isNaN(message)) {
	    		progress.text(message);
	    	} else if (message <= 100) {
	    		progress.text(message+"%");
			}
	    }
	}
	
	//iterates through all the files and checks whether they have been added to S3
	this.allFilesReadyForUpload = function() {
		var allReady = true;
		//check whether we can enable Upload button
		//iterate through all files and check whether all ready for upload
		jQuery.each(self.data.files, function(key, value) {
			if(!value.options.is_uploaded) {
				allReady = false;
				//return false;
			}
		});
		
		return allReady;
	}
	
	//ENTRY POINT: Calling this function will set up the environment, display the upload dialog, 
	//and hook up the AjaxUploader callbacks to the correct functions.
	this.showUploadWindow = function(){
		
		var docTypeHasRequiredFields = false;
		
		self.data = {};
		self.data.files = {};
		
		//does the Default Doc Type have required fields?
		kt.api.docTypeHasRequiredFields("1", function(data){
			//if so, we need to disable the Upload button
			docTypeHasRequiredFields = data.data.hasRequiredFields;			
		});
		
	    var uploadWin = new Ext.Window({
			id          : 'extuploadwindow',
	        layout      : 'fit',
	        width       : 520,
	        resizable   : false,
	        closable    : true,
	        closeAction :'destroy',
	        y           : 50,
	        autoScroll  : false,
	        bodyCssClass: 'ul_win_body',
	        cls			: 'ul_win',
	        shadow: true,
	        modal: true,
	        title: 'Upload Files',
	        html: kt.api.getFragment('upload.dialog')
	    });
	    uploadWin.addListener('show',function(){
	    	//disable the Add Documents button on show since won't be any to add yet!
	    	kt.app.upload.disableAddButton();
	    	self.elems.item_container=jQuery('.uploadTable .ul_list')[0];
	    	self.elems.qq=jQuery('#upload_add_file .qq-uploader')[0];
	    	self.uploader=new qq.FileUploader({
	    		element: document.getElementById('upload_add_file'),
	    		action: 'test.php',
	    		params: {},
	    		buttonText: 'Choose File',
	    		allowedExtensions: [],
	    		sizeLimit: 0,
	    		//taken out multiple uploads until able to figure out how to make it work with S3
	            //issue is that need to force handlerClass = 'UploadHandlerForm' (see below) so that it works with S3
	            //BUT this breaks multiple uploads!
	    		multiple: false,
	    		onSubmit: function(id,fileName){
	    			//remove the 'No Files Selected' message
	    			jQuery('.no_files_selected').css('display', 'none');
	    			//disable the Upload button as can only upload once upload to S3 completes
    				kt.app.upload.disableAddButton();
	    		    
	    			self.addUpload(fileName, self.elems.qq, docTypeHasRequiredFields);
	    		},
	    		onComplete: function(id,fileName,responseJSON){
	    			try{
	    				self.findItem(fileName).completeUpload();
	    			} catch(e){
	    				
	    			}
	    		},
	    		//TODO: need to implement this!
	    		/*onCancel: function(id,fileName){
	    			console.log('onCancel '+fileName);
	    		},*/
	    		showMessage: function(message){alert(message);}
	    	});
	    				
			if (jQuery("input[name='fFolderId']").length == 0) {
				jQuery("#currentPath").val(1);
            } else {
                jQuery("#currentPath").val(jQuery("input[name='fFolderId']").val());
            }
			
			
			kt.api.getFolderHierarchy(jQuery('#currentPath').val(),function(result){
                //console.dir(result);
                
				if (jQuery('#currentPath').val() == 1) {
					jQuery('ul#loadedpath').append('<li class="loadedchildren" folderid="'+jQuery('#currentPath').val()+'">'+result.data.currentFolder.name+'</li>');
				} else {
					jQuery.each(result.data.parents, function(i,item){
						//console.dir(item);
						if (item.parent_id == null) {
							jQuery('ul#loadedpath').append('<li class="notloaded" folderid="'+item.id+'">'+item.name+'</li>');
						} else {
							jQuery('ul#loadedpath li[folderid='+item.parent_id+']').append('<ul><li class="notloaded" folderid="'+item.id+'">'+item.name+'</li></ul>');
						}
					});
					
					jQuery('ul#loadedpath li[folderid='+result.data.currentFolder.parent_id+']').append('<ul><li class="loadedchildren" folderid="'+jQuery('#currentPath').val()+'">'+result.data.currentFolder.name+'</li></ul>');
					
					
				}
				
				parentNode = jQuery('ul#loadedpath li[folderid='+jQuery('#currentPath').val()+'] ul');
				
				if (parentNode.length == 0) {
					parentNode = jQuery('ul#loadedpath li[folderid='+jQuery('#currentPath').val()+']').append('<ul></ul>');
				}
				
				jQuery.each(result.data.children, function(i,item){
					jQuery('ul#loadedpath li[folderid='+jQuery('#currentPath').val()+'] ul').append('<li class="notloaded" folderid="'+item.id+'">'+item.name+'</li>');
					
				});
				
				var path = kt.app.upload.getNodePath(jQuery('#currentPath').val());
				
				var limit = 45;
				if (path.length > limit) {
					var index = path.length - limit;
					path = '.../'+path.substr(index, limit);
				}
				
				jQuery('#uploadpathstring').html(path);
				
				//var uniqueFileName = result.data.amazoncreds.awstmppath+kt.app.upload.uniqueFileName();
				//console.log('uniqueFileName '+uniqueFileName);
				
				//console.log('random '+result.data.amazoncreds.randomfile);

				self.uploader.setParams({
					AWSAccessKeyId          : result.data.amazoncreds.AWSAccessKeyId,
					acl                     : result.data.amazoncreds.acl,
					key                     : result.data.amazoncreds.awstmppath+"${filename}",	//result.data.amazoncreds.awstmppath+result.data.amazoncreds.randomfile,
					policy                  : result.data.amazoncreds.policy,
					'Content-Type'          : "binary/octet-stream",
					signature               : result.data.amazoncreds.signature,
					success_action_redirect : result.data.amazoncreds.success_action_redirect
				});
				
				//get the S3 temp location where all the uploads will be stored
				self.data['s3TempPath'] = result.data.amazoncreds.awstmppath;	//+result.data.amazoncreds.randomfile;
				
				self.uploader._options.action = result.data.amazoncreds.formAction; //doesnt work
				self.uploader._handler._options.action = result.data.amazoncreds.formAction; //works
                
            }, function(){});
            
            jQuery("#changepathlink").live("click", function(){
				//console.log('changepathlink');
                jQuery('#folderpathchooser').toggle();
                
                if (jQuery('#folderpathchooser').css('display') == 'none') {
                    jQuery('#changepathlink').html('Change');
                } else {
                    jQuery('#changepathlink').html('Done');
                    kt.app.upload.loadFolderPath(jQuery('#currentPath').val());
                }
                
                
            });
            
            jQuery("#folderpathchooser li").live("click", function(){
                node = jQuery(this);
				
                jQuery('#currentPath').val(node.attr('folderid'));
                
                
                jQuery('#uploadpathstring').html(kt.app.upload.getNodePath(node.attr('folderid')));
                
                kt.app.upload.loadFolderPath(node.attr('folderid'));
            });
			
			
	    });
		self.uploadWindow=uploadWin;		
		
	    uploadWin.show();
	    
	    //set the folder id of the folder we are in
	    self.data['baseFolderID'] = jQuery("#currentPath").val();
	}
	
	
	
	// Call the initialization function at object instantiation.
	this.init();
}


/**
 * 
 */
kt.app.upload.uploadStructure=function(options){
	var self=this;
	var options=self.options=kt.lib.Object.extend({
		is_uploaded					:false,
		has_required_metadata		:false,
		required_metadata_done		:false,
		do_bulk_upload				:false,
		elem						:null,
		docTypeId					:1,
		docTypeFieldData			:null,
		metadata					:{},
		parent						:null
	},options);
	
	
	
	this.init=function(options){
		self.setFileName(self.options.fileName);
	}

	
	this.setFileName=function(text){
		var e=jQuery('.ul_filename',self.options.elem);
		e.html(text);
	}
	
	this.setProgress=function(text,state){
		var state=kt.lib.Object.ktenum(state,'uploading,waiting,ui_meta,add_doc,done','waiting');
				
		var e=jQuery('.ul_progress',self.options.elem);
		e.html(text);
		
		if(state == 'uploading') {
			jQuery('.ul_progress_spinner',self.options.elem).css('visibility', 'visible');
		} else {
			jQuery('.ul_progress_spinner',self.options.elem).css('visibility', 'hidden');
		}
		
		//make the 'Enter metadata' progress message clickable!
		if(state == 'ui_meta') {
			jQuery(e).css("cursor", "pointer");
			jQuery(e).bind('click', function() {
				self.showMetadataWindow();
			});
		} else {
			jQuery(e).css("cursor", "default");
			jQuery(e).unbind();
		}
		
		jQuery(self.options.elem).removeClass('ul_f_uploading ul_f_waiting ul_f_ui_meta ul_f_add_doc ul_f_done').addClass('ul_f_'+state);
	}
	
	this.startUpload=function(){
		self.setProgress('Uploading','uploading');
	}
	
	this.completeUpload=function(){
		self.options.is_uploaded=true;		
		
		//has all the required metadata for the doc been entered?
		if(self.options.has_required_metadata && !self.options.required_metadata_done){
			self.setProgress('Enter metadata','ui_meta');
		} else {
			self.setProgress('Ready to add','waiting');
			//iterate through all the files and check whether they have been uploaded!
			if(kt.app.upload.allFilesReadyForUpload()) {
				kt.app.upload.enableAddButton();
			} else {
				kt.app.upload.disableAddButton();
			}
		}
	}
	
	this.setDocType=function(docTypeId){
		self.options.docTypeId=docTypeId;
		self.options.docTypeFieldData=kt.api.docTypeFields(docTypeId);
	}
	
	this.setMetaData=function(key,value){
		self.options.metadata[key]=value;
	};
	
	//remove the upload from the file dialog AND from the list of files
	this.removeItem = function() {
		var id = self.options.elem[0].id;
		jQuery('#'+id).remove();
		//also remove it from the list
		delete self.options.parent.data.files[self.options.fileName];
		
		if (jQuery.isEmptyObject(self.options.parent.data.files)) {
			jQuery('.no_files_selected').css('display', 'block');
			kt.app.upload.disableAddButton();
		} else {	
			if(kt.app.upload.allFilesReadyForUpload()) {
				kt.app.upload.enableAddButton();
			} else {
				kt.app.upload.disableAddButton();
			}
		}		
	}
	
	
	
	//flags the upload as being a bulk upload
	this.setAsBulk = function() {		
		if(jQuery('#'+self.options.elem[0].id+' .ul_bulk_checkbox input#unzip_checkbox').attr('checked')) {
			self.options.do_bulk_upload = true;
		} else {
			self.options.do_bulk_upload = false;
		}
	}
	
	this.showMetadataWindow=function(){
		var metaWin = new Ext.Window({
	        layout      : 'fit',
	        width       : 400,
	        resizable   : false,
	        closable    : false,
	        closeAction :'destroy',
	        y           : 50,
	        autoScroll  : false,
	        bodyCssClass: 'ul_meta_body',
	        cls			: 'ul_meta',
	        shadow: true,
	        modal: true,
	        title: 'Document Properties',
	        html: kt.api.execFragment('upload.metadata.dialog')
	    });
		metaWin.addListener('close',function(){
			//have all required metadata fields been completed?
			var requiredDone = self.checkRequiredFieldsCompleted();
			self.options.required_metadata_done = requiredDone;
			
			//is "Apply To All" checked?
			var el = jQuery('#ul_meta_actionbar_apply_to_all')[0];
			var applyMetaToAll = el.checked;
			kt.app.upload.applyMetadataToAll(applyMetaToAll, {'docTypeID':self.options.docTypeId, 'metadata':self.options.metadata}, self.options.required_metadata_done);
			
			var allRequiredMetadataDone = true;			
			jQuery.each(self.options.parent.data.files, function(key, value) {
				if(value.options.has_required_metadata) {
					if(!value.options.required_metadata_done) {
						value.setProgress('Enter metadata','ui_meta');
						allRequiredMetadataDone = false;
						return;
					} else {
						value.setProgress('Ready to add','waiting');
					}
				} else {
					//value.setProgress('Ready to upload','waiting');
					allRequiredMetadataDone = true;
				}
			});
	    	
			//enable/disable the "Add Documents" button as appropriate
			if(allRequiredMetadataDone) {
				kt.app.upload.enableAddButton();
			} else {
				kt.app.upload.disableAddButton();
			}
		});
		
		
		self.options.metaWindow=metaWin;
		metaWin.show();
		
		var e=jQuery('.metadataTable')[0];
		self.options.metaDataTable=e;
		kt.lib.meta.set(e,'item',self);
		
		//do we need to check Apply To All?
		if (self.options.parent.data['applyMetaDataToAll'] && self.options.parent.data['globalMetaData'] != undefined) {
			var el = jQuery('#ul_meta_actionbar_apply_to_all')[0];
			el.checked = true;			
		}
		
		self.changeDocType(self.options.docTypeId?self.options.docTypeId:1);
		
		self.populateValues();
	}
	
	//TODO: enforce length limit for large text fields!
	
	//populate the metadata fields that have been cached
	this.populateValues=function(){
		for(var idx in self.options.metadata){
			//console.log(idx);
			var field=jQuery('.ul_meta_field_'+idx,self.options.metaDataTable);
			//console.dir(field);
			if(field.length>0){
				field=field[0];
				var tag=(field.tagName+'').toLowerCase();
				//console.log('tag '+tag);
				switch(tag){				
				//sometimes, esp where we have multiple html fields for one KTDMS field (eg ckeckboxes)
				//we embed these in a span and then need to iterate through the spans children
					case 'span':
						var children = jQuery('.ul_meta_field_'+idx,self.options.metaDataTable).children();
						for (var c = 0; c < children.length; c++) {
							var child = children[c];
							var type = (child.type+'').toLowerCase();
							//console.log(type);
							switch(type){
								case 'checkbox':
									//convert the comma-delimited string into an array for processing 
									var stringToArray =  self.options.metadata[idx].split(',');
									for (var i = 0; i < stringToArray.length; i++) {
										if (child.value == trim(stringToArray[i])) {
											child.checked = true;
										}
									}
									break;
								case 'radio':
									if (child.value == self.options.metadata[idx]) {
										//console.log('found');
										child.checked = true;
									}
									break;
							}
						}
						
						
						break;
					case 'select':
						//are we dealing with a multi-select array?
						if(jQuery('.ul_meta_field_'+idx,self.options.metaDataTable).attr('multiple')) {
							//convert the comma-delimited string into an array for processing 
							var stringToArray =  self.options.metadata[idx].split(',');
							//remove whitespace!
							for (var i = 0; i < stringToArray.length; i++) {
								stringToArray[i] = trim(stringToArray[i]);
							}
							for (var j = 0; j < field.options.length; j++) {
								if (jQuery.inArray(field.options[j].text, stringToArray) > -1) {
									field.options[j].selected = true;
									//break;
								}
							}
						} else {
							for (var i = 0; i < field.options.length; i++) {
								if (field.options[i].text == self.options.metadata[idx]) {
									field.selectedIndex = i;
									break;
								}
							}
						}
						break;
					case 'input':
						var type=field.type;
						switch(type){							
							case 'text':
								field.value=self.options.metadata[idx];
								break;
						}
						break;
					case 'textarea':
						field.value=self.options.metadata[idx];
						break;
				}
			}
		}
	}
	
	this.checkRequiredFieldsCompleted = function() {
		var requiredFieldsCompleted = true;
		
		if(jQuery('.ul_metadata').find('.required').length <= 0) {
			requiredFieldsCompleted = true;
		} else {
			jQuery('.ul_metadata').find('.required').each(function(index) {
				var field = jQuery(this)[0];
				var tag=(field.tagName+'').toLowerCase();
				//console.log('tag '+tag);
				//TODO: need to do for all the diferent field types, incl tree!!
				
				switch(tag){
					case 'input':
						var type=field.type;
						//console.log('type '+type);
						switch(type){							
							case 'text':
								if (field.value.length == 0){
									requiredFieldsCompleted = false;
									//return requiredFieldsCompleted;
								}
								break;
						}
						break;
						
					case 'select':						
						//are we dealing with a multi-select array?
						if(jQuery(field).attr('multiple')) {
							if(field.selectedIndex < 0 ){
								requiredFieldsCompleted = false;
							}
						} else {
							if(field.selectedIndex <= 0 ){
								requiredFieldsCompleted = false;
							}
						}
						break;
					case 'span':
						//console.log('span');
						var children = jQuery(field).children();
						//console.log('children '+children.length);
						
						var childChecked = false;
						
						for (var c = 0; c < children.length; c++) {
							//console.log('child '+c);
							var child = children[c];
							var type = (child.type+'').toLowerCase();
							//console.log(type);
							switch(type){
								case 'checkbox':
									//console.log('child.name '+child.name+' '+child.checked);
									if(child.checked) {
										childChecked = true;
									}
									break;
								case 'radio':
									//console.log('child.name '+child.name+' '+child.checked);
									if(child.checked) {
										childChecked = true;
									}
									break;
							}
						}
						
						if(!childChecked) {
							requiredFieldsCompleted = false;
						}
						
						break;
					case 'textarea':
						//console.log('textarea :'+field.value+':'+field.value.length);
						//TODO: if you click in an HTML field, without entering anything, it comes through as length = 1!
						if (field.value == ''){ //field.value.length == 0 || 
							requiredFieldsCompleted = false;
							//return requiredFieldsCompleted;
						}
						break;
				}
			});
		}
		
		/*for(var idx in self.options.metadata){
			//console.dir(self.options.metadata[idx]);
			console.log('required '+self.options.metadata[idx]['required']);
			if(self.options.metadata[idx]['required']==1) {
				console.log('required field');
				var field=jQuery('.ul_meta_field_'+idx,self.options.metaDataTable);
				//console.dir(field);
				if(field.length>0){
					field=field[0];
					file.attr('background-color', 'red');
				}
			}
		}*/
		
		return requiredFieldsCompleted;
	}
	
	this.changeDocType=function(docType){
		self.options.docTypeId=docType;
		
		try {
		//TODO: what does this do exactly?
		var selectBox=jQuery('.ul_doctype',self.options.metaDataTable)[0];
		//for(var idx in selectBox.options){
		for(var idx = 0; idx < selectBox.options.length; idx++) {
			if(selectBox.options[idx].value==docType){
				selectBox.selectedIndex=idx;
			}
		}
		
		var data=kt.api.docTypeFields(docType);
		self.options.docTypeFieldData=data.fieldsets;
		var container=jQuery('.ul_metadata',self.options.metaDataTable);
		
		container.html('');
		
		//if the fieldsets come through as an array, then it is empty
		if (!(data.fieldsets instanceof Array)) {			
			for(var idx in self.options.docTypeFieldData){
				var fieldSet=self.options.docTypeFieldData[idx].properties;
				var fields=self.options.docTypeFieldData[idx].fields;
				var t_fieldSet=jQuery(kt.lib.String.parse(kt.api.getFragment('upload.metadata.fieldset'),fieldSet));
				
				container.append(t_fieldSet);
				
				for(var fidx in fields){
					var field=fields[fidx];
					var fieldType=self.getFieldType(field);
					var t_field_filename='upload.metadata.field.' + fieldType;
					var t_field=jQuery(kt.lib.String.parse(kt.api.getFragment(t_field_filename),field));
					t_fieldSet.append(t_field);
				}
			}
		}
		} catch(e){}
	};
	
	this.getFieldType=function(field){
		var datatype = (''+field.data_type).toLowerCase();

		//Fields set to type STRING
		if(datatype=='string'){
			if(field.has_inetlookup==1){
				return field.inetlookup_type;
			}
			if(field.has_lookuptree==1)return 'tree';
			if(field.has_lookup==1)return 'lookup';
		}
		
		if(datatype=='large text'){
			if(field.is_html==1)return 'large-html';
			return 'large-text';
		}
		return datatype;
	};
	
	this.init(options);
};


/**
 * Functions from http://phpjs.org/
 *
 */
function strpos (haystack, needle, offset) {
	// http://kevin.vanzonneveld.net

	var i = (haystack+'').indexOf(needle, (offset || 0));
	return i === -1 ? false : i;
}


function trim (str, charlist) {
	// http://kevin.vanzonneveld.net

	var whitespace, l = 0, i = 0;
	str += '';
	
	if (!charlist) {
		// default list
		whitespace = " \n\r\t\f\x0b\xa0\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u200b\u2028\u2029\u3000";
	} else {
		// preg_quote custom list
		charlist += '';
		whitespace = charlist.replace(/([\[\]\(\)\.\?\/\*\{\}\+\$\^\:])/g, '$1');
	}
	
	l = str.length;
	for (i = 0; i < l; i++) {
		if (whitespace.indexOf(str.charAt(i)) === -1) {
			str = str.substring(i);
			break;
		}
	}
	
	l = str.length;
	for (i = l - 1; i >= 0; i--) {
		if (whitespace.indexOf(str.charAt(i)) === -1) {
			str = str.substring(0, i + 1);
			break;
		}
	}
	
	return whitespace.indexOf(str.charAt(0)) === -1 ? str : '';
}