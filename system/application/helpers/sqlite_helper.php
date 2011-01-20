<?php

if (!function_exists('describe_sqlite_database')) {
	function describe_sqlite_database($dbFilePath) {
		$dbh = new PDO("sqlite:" . $dbFilePath);
		$pragmaQuery = $dbh->prepare("select * from sqlite_master WHERE type='table';");
		$pragmaQuery->execute();
		$tableInfo = $pragmaQuery->fetchAll();
		$pragmaQuery->closeCursor();
		
		$sqliteTables = array();
		
		foreach ($tableInfo as $table) {
			if ($table['name'] == "sqlite_sequence" || $table['name'] == "sqlite_master") {
				continue;
			}
			$tinfo = array();
			$tinfo['table_name'] = $table['name'];
			$tinfo['create_statement'] = $table['sql'];
			$tinfo['columns'] = array();
			
			$q = $dbh->prepare("PRAGMA table_info(" . $table['name'] . ");");
			$q->execute();
			$cols = $q->fetchAll();
			$q->closeCursor();
			
			foreach ($cols as $col) {
				$tinfo['columns'][] = array(
					'col_name' => $col['name'],
					'type' => $col['type'],
					'notnull' => $col['notnull'],
					'default_value' => $col['dflt_value'],
					'pk' => $col['pk'], 
				);
			}
			$sqliteTables[] = $tinfo;
		}
		
		return $sqliteTables;
	}
}

if(!function_exists('get_sqlite_java_converter_array')) {
	function get_sqlite_java_converter_array() {
		$genericGS = file_get_contents(BASEPATH . "../static/raw/GenericGaS.txt");
		$sqliteTypeArray = array(
			'BOOL' => array(
				'javaType' => "boolean",
				'javaDefault' => "false",
				'javaHydrate' => "\t\t\t{SetterName}(c.getInt(c.getColumnIndexOrThrow({FieldNameVar})) == 1);",
				'javaCvPut' => "cv.put({FieldNameVar}, {FieldValueVar});",
				'javaGettersAndSetters' => $genericGS,
				'javaFind' => "{FieldNameVar} + \" = \" + (val ? 1 : 0), null",
				'javaToJSON' => "\t\t\tobj.put({FieldNameVar}, {FieldValueVar});",
				'javaFromJSON' => "\t\t\t{FieldValueVar} = obj.getBoolean({FieldNameVar});",
			),
			'DOUBLE' => array(
				'javaType' => "double",
				'javaDefault' => "0",
				'javaHydrate' => "\t\t\t{SetterName}(c.getDouble(c.getColumnIndexOrThrow({FieldNameVar})));",
				'javaCvPut' => "cv.put({FieldNameVar}, {FieldValueVar});",
				'javaGettersAndSetters' => $genericGS,
				'javaFind' => "{FieldNameVar} + \" = '\" + val + \"'\", null",
				'javaToJSON' => "\t\t\tobj.put({FieldNameVar}, {FieldValueVar});",
				'javaFromJSON' => "\t\t\t{FieldValueVar} = obj.getDouble({FieldNameVar});",
			),
			'FLOAT' => array(
				'javaType' => "float",
				'javaDefault' => "0f",
				'javaHydrate' => "\t\t\t{SetterName}(c.getFloat(c.getColumnIndexOrThrow({FieldNameVar})));",
				'javaCvPut' => "cv.put({FieldNameVar}, {FieldValueVar});",
				'javaGettersAndSetters' => $genericGS,
				'javaFind' => "{FieldNameVar} + \" = '\" + val + \"'\", null",
				'javaToJSON' => "\t\t\tobj.put({FieldNameVar}, (double){FieldValueVar});",
				'javaFromJSON' => "\t\t\t{FieldValueVar} = (float)obj.getDouble({FieldNameVar});",
			),
			'INTEGER' => array(
				'javaType' => "int",
				'javaDefault' => "0",
				'javaHydrate' => "\t\t\t{SetterName}(c.getInt(c.getColumnIndexOrThrow({FieldNameVar})));",
				'javaCvPut' => "cv.put({FieldNameVar}, {FieldValueVar});",
				'javaGettersAndSetters' => $genericGS,
				'javaFind' => "{FieldNameVar} + \" = \" + val, null",
				'javaToJSON' => "\t\t\tobj.put({FieldNameVar}, {FieldValueVar});",
				'javaFromJSON' => "\t\t\t{FieldValueVar} = obj.getInt({FieldNameVar});",
			),
			'CHAR' => array(
				'javaType' => "char",
				'javaDefault' => "(char) 0x0",
				'javaHydrate' => "\t\t\tString tmp = c.getString(c.getColumnIndexOrThrow({FieldNameVar}));\n\t\t\tif (tmp == null || tmp.length() <= 0) {\n\t\t\t\t{SetterName}((char) 0x0);\n\t\t\t} else {\n\t\t\t\t{SetterName}(tmp.charAt(0));\n\t\t\t}\n\t\t\ttmp = null;",
				'javaCvPut' => "cv.put({FieldNameVar}, {FieldValueVar} + \"\");",
				'javaGettersAndSetters' => $genericGS,
				'javaFind' => "{FieldNameVar} + \" = '\" + val + \"'\", null",
				'javaToJSON' => "\t\t\tobj.put({FieldNameVar}, {FieldValueVar} + \"\");",
				'javaFromJSON' => "\t\t\tString tmp = obj.getString({FieldNameVar});\n\t\t\tif (tmp == null || tmp.length() <= 0) {\n\t\t\t\t{SetterName}((char) 0x0);\n\t\t\t} else {\n\t\t\t\t{SetterName}(tmp.charAt(0));\n\t\t\t}\n\t\t\ttmp = null;",
			),
			'TEXT' => array(
				'javaType' => "String",
				'javaDefault' => "\"\"",
				'javaHydrate' => "\t\t\t{SetterName}(c.getString(c.getColumnIndexOrThrow({FieldNameVar})));",
				'javaCvPut' => "cv.put({FieldNameVar}, {FieldValueVar});",
				'javaGettersAndSetters' => $genericGS,
				'javaFind' => "{FieldNameVar} + \" = ?\", new String[] {val}",
				'javaToJSON' => "\t\t\tobj.put({FieldNameVar}, {FieldValueVar});",
				'javaFromJSON' => "\t\t\t{FieldValueVar} = obj.getString({FieldNameVar});",
			),
			'BLOB' => array(
				'javaType' => "ByteBuffer",
				'javaDefault' => "null",
				'javaHydrate' => "\t\t\t{SetterName}Bytes(c.getBlob(c.getColumnIndexOrThrow({FieldNameVar})));",
				'javaCvPut' => "cv.put({FieldNameVar}, ({FieldValueVar} == null ? null : {FieldValueVar}.array()));",
				'javaGettersAndSetters' => file_get_contents(BASEPATH . "../static/raw/BlobGaS.txt"),
				'javaFind' => "",
				'javaToJSON' => "\t\t\tobj.put({FieldNameVar}, ({FieldValueVar} == null ? null : {FieldValueVar}.array()));",
				'javaFromJSON' => "\t\t\t{SetterName}Bytes((byte[])obj.get({FieldNameVar}));",
			),
			'DATETIME' => array(
				'javaType' => "long",
				'javaDefault' => "System.currentTimeMillis()",
//				'javaHydrate' => "\t\t\t{SetterName}(c.getLong(c.getColumnIndexOrThrow({FieldNameVar})));",
				'javaHydrate' => "\t\t\tString textVal = c.getString(c.getColumnIndexOrThrow({FieldNameVar}));\n\t\t\tif (textVal != null) {\n\t\t\t\ttry {\n\t\t\t\t\t{SetterName}(Long.parseLong(textVal));\n\t\t\t\t} catch (Exception e1) {\n\t\t\t\t\ttry {\n\t\t\t\t\t\t{SetterName}(SQLITE_DATE_FORMAT.parse(textVal));\n\t\t\t\t\t} catch (Exception e2) {e2.printStackTrace();}\n\t\t\t\t}\n\t\t\t}",
				'javaCvPut' => "cv.put({FieldNameVar}, {FieldValueVar});",
				'javaGettersAndSetters' => file_get_contents(BASEPATH . "../static/raw/DatetimeGaS.txt"),
				'javaFind' => "{FieldNameVar} + \" = \" + val, null",
				'javaToJSON' => "\t\t\tobj.put({FieldNameVar}, {FieldValueVar});",
				'javaFromJSON' => "\t\t\t{FieldValueVar} = obj.getLong({FieldNameVar});",
			),
			'PRIMARY_KEY' => array(
				'javaType' => "long",
				'javaDefault' => "0",
				'javaHydrate' => "\t\t\tsetId(c.getLong(c.getColumnIndexOrThrow({FieldNameVar})));",
				'javaCvPut' => "",
				'javaGettersAndSetters' => "",
				'javaFind' => "{FieldNameVar} + \" = \" + val, null",
				'javaToJSON' => "\t\t\tobj.put({FieldNameVar}, getId());",
				'javaFromJSON' => "\t\t\tsetId(obj.getLong({FieldNameVar}));",
			),
			
		);
		
		$sqliteTypeArray['REAL'] = $sqliteTypeArray['DOUBLE'];
		$sqliteTypeArray['INT'] = $sqliteTypeArray['INTEGER'];
		$sqliteTypeArray['NUMERIC'] = $sqliteTypeArray['DOUBLE'];
		$sqliteTypeArray['VARCHAR'] = $sqliteTypeArray['TEXT'];
		$sqliteTypeArray['STRING'] = $sqliteTypeArray['TEXT'];
		$sqliteTypeArray['CLOB'] = $sqliteTypeArray['TEXT'];
		return $sqliteTypeArray;
	}
}