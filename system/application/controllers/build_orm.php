<?php



class Build_orm extends Controller {
	

	function Build_orm()
	{
		parent::Controller();	
		$this->load->helper("form");
		$this->load->helper("url");
	}
	
	function index()
	{
		$this->load->view("upload_sqlite", array("error" => ""));
	}
	
	function do_sqlite_upload() {
		$config['upload_path'] = '/tmp/';
		$config['allowed_types'] = 'sqlite';
		$config['max_size']	= '10000';
		$config['overwrite'] = TRUE;
		
		$this->load->library('upload', $config);
		
		if ($this->upload->do_upload()) {
			
			$packageName = $this->input->post("package_name");
			$coPrefix = $this->input->post("co_prefix");
			$projPrefix = $this->input->post("proj_prefix");
			$dbVersion = $this->input->post("db_version");
			$includePrebuilt = ($this->input->post("include_prebuilt") == "true");
			$copyrightArray = explode("\n", $this->input->post("copyright_notice"));
			
			$uploadData = $this->upload->data();
			$filepath = $uploadData['full_path'];
			$this->load->helper("sqlite");
			
			$sqliteTables = describe_sqlite_database($filepath);
			$sqliteIndecies = get_sqlite_indecies($filepath);
			$filename = $uploadData['orig_name'];
			
			$this->load->library('zip');
			$this->zip->clear_data();
			$this->_buildAndroidOrm($packageName, $sqliteTables, $coPrefix, $projPrefix, $includePrebuilt, $filename, $copyrightArray, $dbVersion, $sqliteIndecies);
			$this->zip->read_file($filepath);
			$this->zip->download($packageName . ".database.zip");
		} else {
			$this->_displayError($this->upload->display_errors());
		}
	}
	
	function _displayError($error) {
		$this->load->view("upload_sqlite", array("error" => $error));
	}
	
	function _buildAndroidOrm($packageName, $tableArray, $coPrefix, $projPrefix, $includePrebuilt, $sqliteFilename, $copyrightArray, $dbVersionCode, $sqliteIndecies) {

		$SQLITE_TYPE_ARRAY = get_sqlite_java_converter_array();
		
		$PERSISTENT_OBJECT = file_get_contents(BASEPATH . "../static/raw/PersistentObject.txt");
		$DB_MANAGER = file_get_contents(BASEPATH . "../static/raw/DbManager.txt");
		$BASE_OBJECT = file_get_contents(BASEPATH . "../static/raw/BaseObject.txt");
		$OBJECT = file_get_contents(BASEPATH . "../static/raw/Object.txt");
		
		
		$globalReplaceArray = array(
			'PackageName' => $packageName,
			'CoPrefix' => $coPrefix,
			'SqliteFilenameFull' => $sqliteFilename,
			'SqliteResId' => ($includePrebuilt ? "R.raw." . substr($sqliteFilename, 0, strrpos($sqliteFilename, ".")) : 0),
			'PrebuiltIncluded' => ($includePrebuilt ? "true" : "false"),
			'DbManagerDropAndCreate' => "",
			'ObjectClassImports' => "",
			'CopyrightNotice' => "",
			'DbVersionCode' => $dbVersionCode,
		);
		
		foreach($copyrightArray as $copy) {
			$globalReplaceArray['CopyrightNotice'] .= " * " . $copy . "\n";
		}
		
		foreach($tableArray as $table) {
			$tblReplace = $this->_buildObjectArray($table, $projPrefix, $SQLITE_TYPE_ARRAY);
			$globalReplaceArray['DbManagerDropAndCreate'] .= "\n\t\t\tdb.execSQL(" . $tblReplace['ClassName'] . ".DROP_TABLE_STATEMENT);";
			$globalReplaceArray['DbManagerDropAndCreate'] .= "\n\t\t\tdb.execSQL(" . $tblReplace['ClassName'] . ".CREATE_TABLE_STATEMENT);";
			$globalReplaceArray['ObjectClassImports'] .= "import $packageName.database.objects." . $tblReplace['ClassName'] . ";\n";
			
			$base = $this->_replaceFromArrayKeys($this->_replaceFromArrayKeys($BASE_OBJECT, $tblReplace), $globalReplaceArray);
			$obj = $this->_replaceFromArrayKeys($this->_replaceFromArrayKeys($OBJECT, $tblReplace), $globalReplaceArray);
			
			$this->zip->add_data("database/objects/base/Base" . $tblReplace['ClassName'] . ".java" , $base);
			$this->zip->add_data("database/objects/" . $tblReplace['ClassName'] . ".java" , $obj);
		}
		
		foreach($sqliteIndecies as $index) {
			$globalReplaceArray['DbManagerDropAndCreate'] .= "\n\t\t\tdb.execSQL(" . str_replace("\""", "\\\""", $index) . ");";
		}
		
		$persObj = $this->_replaceFromArrayKeys($PERSISTENT_OBJECT, $globalReplaceArray);
		$dbManager = $this->_replaceFromArrayKeys($DB_MANAGER, $globalReplaceArray);
		
		$this->zip->add_data("database/" . $globalReplaceArray['CoPrefix'] . "PersistentObject.java" , $persObj);
		$this->zip->add_data("database/" . $globalReplaceArray['CoPrefix'] . "DbManager.java" , $dbManager);

	}
	
	function _buildObjectArray($tableInfo, $projPrefix, $SQLITE_TYPE_ARRAY) {
		$columnInfoArray = $tableInfo['columns'];
		$numCols = count($columnInfoArray);
		
		$rtr = array(
			'ClassName' => $projPrefix . $this->_getUpperedName($tableInfo['table_name']),
			'ObjErrorMsgs' => "",
			'SqliteTableName' => $tableInfo['table_name'],
			'ObjFieldNamesDefs' => "",
			'ObjFieldNameColList' => "",
			'ObjCreateTableStatement' => str_replace("\r", " ", str_replace("\n", " ", str_replace("\"", "\\\"", $tableInfo['create_statement']))),
			'ObjDropTableStatement' => "DROP TABLE IF EXISTS '" . $tableInfo['table_name'] . "';",
			'ObjFieldDefs' => "",
			'ObjFieldInit' => "",
			'ObjPkFieldVar' => "",
			'ObjHydrateProcedure' => "",
			'ObjNumFieldsSubPk' => $numCols-1,
			'ObjPuCvProcedure' => "",
			'ObjGettersAndSetters' => "",
			'ObjJSONHydrate' => "",
			'ObjJSONCreate' => "",
//			'columns' => array(),
		);
		
		$pkColIndex = 0;
		
		$singleHydrateProc = file_get_contents(BASEPATH . "../static/raw/SingleHydrateProc.txt");
		
		$i = 0;
		foreach ($columnInfoArray as $colInfo) {
			$sqliteType = $colInfo['type'];
			$sqliteColName = $colInfo['col_name'];
			$uCWordsColName = $this->_getUpperedName($sqliteColName);
			$uCaseColName = strtoupper($sqliteColName);
			$isPk = $colInfo['pk'];
			
			if ($isPk) {
				$pkColIndex = $i;
			}
			
			if (!array_key_exists($sqliteType, $SQLITE_TYPE_ARRAY)) {
				echo "UNMATCHABLE SQLITE COLUMN TYPE!\n\n";
				echo print_r($colInfo, true);
				echo print_r($tableInfo, true);
				die();
			}
			$typeArray = $SQLITE_TYPE_ARRAY[$sqliteType];
			$rpl = array (
				'SetterName' => "set" . $uCWordsColName,
				'FieldNameVar' => "F_" . $uCaseColName,
				'FieldValueVar' => "m" . $uCWordsColName,
				'SingleHydrateProcedure' => "",
				'HydrateErrorVarName' => "ERROR_MSG_HYDRATE_NO_" . $uCaseColName,
				'UcFieldName' => $uCWordsColName,
				'FieldJavaType' => $typeArray['javaType'],
				'FieldWhereClause' => "",
			);
			$rpl['SingleHydrateProcedure'] = $this->_replaceFromArrayKeys(($isPk ? $SQLITE_TYPE_ARRAY['PRIMARY_KEY']['javaHydrate'] : $typeArray['javaHydrate']), $rpl);
			$rpl['FieldWhereClause'] = $this->_replaceFromArrayKeys(($isPk ? $SQLITE_TYPE_ARRAY['PRIMARY_KEY']['javaFind'] : $typeArray['javaFind']), $rpl);
			
//			$rtr['ObjJSONHydrate'] .= $this->_replaceFromArrayKeys(($isPk ? $SQLITE_TYPE_ARRAY['PRIMARY_KEY']['javaFromJSON'] : $typeArray['javaFromJSON']), $rpl);
			$rtr['ObjJSONCreate'] .= "\n" . $this->_replaceFromArrayKeys(($isPk ? $SQLITE_TYPE_ARRAY['PRIMARY_KEY']['javaToJSON'] : $typeArray['javaToJSON']), $rpl);
			
			$rtr['ObjErrorMsgs'] .= "\n\tprivate static final String " . $rpl['HydrateErrorVarName'] . " = \"Error fetching column '" .$sqliteColName . "' from table '" . $tableInfo['table_name'] . "'\";";
			$rtr['ObjFieldNamesDefs'] .= "\n\tpublic static final String " . $rpl['FieldNameVar'] . " = \"" . $sqliteColName . "\";";
			if ($rtr['ObjFieldNameColList'] != "") {
				$rtr['ObjFieldNameColList'] .= ", ";
			}
			$rtr['ObjFieldNameColList'] .= $rpl['FieldNameVar'];
			$rtr['ObjHydrateProcedure'] .= "\n" . $this->_replaceFromArrayKeys($singleHydrateProc, $rpl);
			
			//reuse single hydrate proc for json hydrate
			$rpl['SingleHydrateProcedure'] = $this->_replaceFromArrayKeys(($isPk ? $SQLITE_TYPE_ARRAY['PRIMARY_KEY']['javaFromJSON'] : $typeArray['javaFromJSON']), $rpl);
			$rtr['ObjJSONHydrate'] .= "\n" . $this->_replaceFromArrayKeys($singleHydrateProc, $rpl);
			
			
			if (!$isPk) {
				$rtr['ObjFieldDefs'] .= "\n\tprivate " . $typeArray['javaType'] . " " . $rpl['FieldValueVar'] . ";";
				$rtr['ObjFieldInit'] .= "\n\t\t" . $rpl['FieldValueVar'] . " = " . $typeArray['javaDefault'] . ";";
				$rtr['ObjPuCvProcedure'] .= "\n\t\t" . $this->_replaceFromArrayKeys($typeArray['javaCvPut'], $rpl);
				$rtr['ObjGettersAndSetters'] .= "\n\n" . $this->_replaceFromArrayKeys($this->_replaceFromArrayKeys($typeArray['javaGettersAndSetters'], $rpl), $rtr);
			} else {
				$rtr['ObjPkFieldVar'] = $rpl['FieldNameVar'];
			}
			
			$i++;
		}
		return $rtr; 
	}
	
	
	function _replaceFromArrayKeys($str, $arr) {
		$rtr = $str;
		foreach($arr as $key => $val) {
			$rtr = str_replace("{" . $key . "}", $val, $rtr);
		}
		return $rtr;
	}
	
	
	function _getUpperedName($name) {
		$uname = str_replace("_", " ", $name);
		$uname = ucwords($uname);
		$uname = str_replace(" ", "", $uname);
		return $uname;
	}
	
	
}