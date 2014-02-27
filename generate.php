<?php

include_once("../utils/db.php");

class TableInfo {
	var $className;
	var $primaryKey;
	var $foreignKey;

	function __construct($c, $f = null, $p = "id") {
		$this->className = $c;
		$this->primaryKey = $p;
		$this->foreignKey = $f;
	}
}

$tables = array();
//$tables['hunpo'] = new TableInfo("Hunpo", array("id"=>"kapai"));
//$tables['kapai'] = new TableInfo("Kapai", array("skillid"=>"skill", "beidongid"=>"beidongskill", "zuheid"=>"zuheskill"));
//$tables['skill'] = new TableInfo("Skill", array("texiaoid"=>"texiao"));
//$tables['texiao'] = new TableInfo("Texiao");
//$tables['beidongskill'] = new TableInfo("BeidongSkill");
//$tables['zuheskill'] = new TableInfo("ZuheSkill");
$tables['duihuan'] = new TableInfo("Duihuan");

class Column{
	var $field;
	var $isString;
}

function isForeignKey($col, $forArr) {
	foreach($forArr as $key=>$tab) {
		if($col->field == $key) {
			return array($key, $tab);
		}
	}
	return null;
}

function getColumns($table) {
	$desc = DB::getInstance()->select("desc ".$table.";");
	$ret = array();
	foreach($desc as $col) {
		$c = new Column();
		$c->field = $col['Field'];
		if(substr($col['Type'], 0, 7) == "varchar") {
			$c->isString = true;
		}else {
			$c->isString = false;
		}
		$ret[] = $c;
	}
	return $ret;
}

function tableToClass($tableName, $tableInfo) {
	global $tables;
	// file init
	$fp = fopen("../model/$tableName"."_model.php", "w");
	fwrite($fp, "<?php".PHP_EOL.PHP_EOL);

	// header files
	if($tableInfo->foreignKey) {
		foreach($tableInfo->foreignKey as $key=>$tab) {
			fwrite($fp, "include_once(\"../model/$tab"."_model.php\");".PHP_EOL);
		}
	}

	// model class
	fwrite($fp, PHP_EOL."class $tableInfo->className"."Model {".PHP_EOL);
	$cols = getColumns($tableName);
	foreach($cols as $col) {
		$found = false;
		// foreign key to foreign class
		if($tableInfo->foreignKey) {
			$ret = isForeignKey($col, $tableInfo->foreignKey);
			if($ret) {
				fwrite($fp, "\tvar \$$ret[1];".PHP_EOL);
				continue;
			}
		}
		fwrite($fp, "\tvar \$$col->field;".PHP_EOL);
	}
	// static data from database;
	fwrite($fp, "\tprivate static \$data = array(".PHP_EOL);
	$data = DB::getInstance()->select2($tableName, "*", null, false);
	foreach($data as $line) {
		fwrite($fp, "\t\t".$line[$tableInfo->primaryKey]."=>array(".PHP_EOL);
		foreach($cols as $col) {
			if($tableInfo->primaryKey == $col->field) {
				continue;
			}
			$value = "";
			if($col->isString) {
				$value = "'".$line[$col->field]."'";
			}else {
				$value = $line[$col->field];
			}
			fwrite($fp, "\t\t\t\"".$col->field."\"=>".$value.",".PHP_EOL);
		}
		fwrite($fp, "\t\t),".PHP_EOL);
	}
	fwrite($fp, "\t);".PHP_EOL);

	// contruct function
	fwrite($fp, PHP_EOL."\tfunction __construct(\$id) {".PHP_EOL);
	foreach($cols as $col) {
		if($tableInfo->foreignKey) {
			$ret = isForeignKey($col, $tableInfo->foreignKey);
			if($ret) {
				$arg = "";
				if($ret[0] == $tableInfo->primaryKey) {
					$arg = "\$id";
				}else {
					$arg = "self::\$data[\$id]['$col->field']";
				}
				fwrite($fp, "\t\t\$this->$ret[1] = new ".$tables[$ret[1]]->className."Model($arg);".PHP_EOL);
				continue;
			}
		}
		if($tableInfo->primaryKey == $col->field) {
			fwrite($fp, "\t\t\$this->$col->field = \$id;".PHP_EOL);
		}else {
			fwrite($fp, "\t\t\$this->$col->field = self::\$data[\$id]['$col->field'];".PHP_EOL);
		}
	}

	fwrite($fp, "\t}".PHP_EOL);
	fwrite($fp, "}".PHP_EOL);
	fclose($fp);
}


foreach($tables as $tableName=>$tableInfo) {
	tableToClass($tableName, $tableInfo);
}
