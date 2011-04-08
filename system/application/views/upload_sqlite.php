<html>
	<head>
		<title>Upload Sqlite Database</title>
	</head>
	<body>
		<h2>Android ORM Builder</h2>
		<div style="color:red"><?=$error?></div>
		<?=form_open_multipart("build_orm/do_sqlite_upload");?>
		Company Prefix: <?=form_input('co_prefix', '')?><br/>
		Project Prefix: <?=form_input('proj_prefix', '')?><br/>
		Package Name (exclude .database): <?=form_input(array('name'=>"package_name", 'style' => "width:500px", 'value' => "com."));?><br/>
		Include Prebuilt Db? <?=form_checkbox('include_prebuilt', 'true')?><br/>
		Database Version Code: <?=form_input('db_version', '1')?><br/><br/>
		
		Singleton Mode: <?=form_checkbox('singleton', 'true')?><br/>
		(if using singleton mode, you much init and destroy the db manager in your application's onCreate and onTerminate methods)<br/><br/>
		
		Upload Sqlite File: <?=form_upload("userfile");?><br/>
		<br/>Copyright Notice:<br/><?=form_textarea('copyright_notice', "Created By YOURNAME on " . date("m/d/Y", time()) . ".\nCopyright " . date("Y", time()) . " COMPANYNAME, Inc. All rights reserved.")?><br/><br/>
		<?=form_submit("submit", "Submit");?>
		<?=form_close()?>
	</body>
</html>