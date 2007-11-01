using NUnit.Framework;
using System;
using System.IO;

namespace MonoTests.KnowledgeTree
{    
	[TestFixture]
	public class FolderTest  
    	{
	
		private String 			_session;
		private KnowledgeTreeService 	_kt;
		private int			_folder_id;
		private int			_subfolder_id;
		private bool			_skip;
		
		[SetUp]
		public void SetUp() 
		{
			this._skip = true; 
			if (this._skip) return;
			this._kt = new KnowledgeTreeService();	
			kt_response response = this._kt.login("admin","admin","127.0.0.1");
			this._session = response.message; 
			 
		}

		[TearDown]
		public void TearDown() 
		{
			if (this._skip) return;
			this._kt.logout(this._session);
		}
		 
		[Test]
		public void GetFolderDetail() 
		{
			if (this._skip) return;
			kt_folder_detail response = this._kt.get_folder_detail(this._session, 1);
			Assert.AreEqual(0, response.status_code);
			Assert.AreEqual(1, response.id);
			Assert.AreEqual("Root Folder", response.folder_name);
			Assert.AreEqual(0, response.parent_id);
			Assert.AreEqual("/Root Folder", response.full_path); // ??? DOESNT SEEM CONSISTENT - should be 'Root Filder'
    		}
	
		[Test]
		public void AddFolder() 
		{
			if (this._skip) return;
	    		kt_folder_detail response = this._kt.create_folder(this._session, 1, "kt_unit_test");
		       	Assert.AreEqual(0,response.status_code);
			
			this._folder_id = response.id;
			
	    		response = this._kt.create_folder(this._session, this._folder_id, "subfolder");
		       	Assert.AreEqual(0,response.status_code);
			
			this._subfolder_id = response.id;
			
	    	}		

		[Test]
		public void GetFolderByName() 
		{
			if (this._skip) return;
			kt_folder_detail response = this._kt.get_folder_detail_by_name(this._session, "/kt_unit_test");
			Assert.AreEqual(0,response.status_code);
			Assert.AreEqual(this._folder_id, response.id);	  

			response = this._kt.get_folder_detail_by_name(this._session, "kt_unit_test");
			Assert.AreEqual(0,response.status_code);
			Assert.AreEqual(this._folder_id, response.id);				 

			response = this._kt.get_folder_detail_by_name(this._session, "kt_unit_test/subfolder");
			Assert.AreEqual(0,response.status_code);
			Assert.AreEqual(this._subfolder_id,response.id);				 

			response = this._kt.get_folder_detail_by_name(this._session, "kt_unit_test/subfolder2");
			Assert.IsFalse(response.status_code == 0);
						 

    		}
		
		[Test]
		public void GetFolderContents() 
		{
			if (this._skip) return;
	    		kt_folder_contents response = this._kt.get_folder_contents(this._session, this._folder_id, 1, "DF");
			Assert.AreEqual(0,response.status_code);
			Assert.AreEqual(this._folder_id,response.folder_id);
			Assert.AreEqual("kt_unit_test", response.folder_name);
			Assert.AreEqual("Root Folder/kt_unit_test", response.full_path);

	    		kt_folder_contents response2 = this._kt.get_folder_contents(this._session, this._subfolder_id, 1, "DF");
			Assert.AreEqual(0, response2.status_code);
			Assert.AreEqual(this._subfolder_id, response2.folder_id);
			Assert.AreEqual("subfolder", response2.folder_name);
			Assert.AreEqual("Root Folder/kt_unit_test/subfolder", response2.full_path);
	    	}
		
		[Test]
		public void RenameFolder() 
		{
			if (this._skip) return;
	    		kt_response response = this._kt.rename_folder(this._session, this._subfolder_id, "subfolder2");
			Assert.AreEqual(0, response.status_code);
			
			kt_folder_detail response2 = this._kt.get_folder_detail(this._session, this._subfolder_id);
			Assert.AreEqual(0, response2.status_code);
			Assert.AreEqual(this._subfolder_id, response2.id);
			Assert.AreEqual("subfolder2", response2.folder_name);
			Assert.AreEqual(this._folder_id, response2.parent_id);
			Assert.AreEqual("Root Folder/kt_unit_test/subfolder2", response2.full_path);			
	    	}	

		[Test]
		public void CopyFolder() 
		{
			if (this._skip) return;
			// TODO copy
			// 
	    	}	
		
		[Test]
		public void MoveFolder() 
		{
			if (this._skip) return;
			// TODO move
			// 
	    	}	

		[Test]
		public void RemoveFolder() 
		{
			if (this._skip) return;
	    		kt_response response = this._kt.delete_folder(this._session, this._folder_id, "unit testing remove");
			Assert.AreEqual(0, response.status_code);
	    	}		

	}
}