<?php


function BXStr($str, $typ) {
	$tmpStr = $str;
	for ($i = 0; $i < strlen($typ); $i++)
		switch ($typ[$i]) {
			case "u":
				$tmpStr = BXStrSM($tmpStr);
				break;
			case "n":
				$tmpStr = urldecode($tmpStr);
				break;
			case "a":
				$tmpStr = addslashes($tmpStr);
				break;
			case "s":
				$tmpStr = stripslashes($tmpStr);
				break;
			case "t":
				$tmpStr = trim($tmpStr);
				break;
			case "m":
				// $tmpStr = mysqli_real_escape_string($tmpStr);
				//! 031216.1208 we need a connection to get mysqli_ working
				$tmpStr = mysqli_real_escape_string($GLOBALS['rmsocial']['db']->dbLink, $tmpStr);
				break;
			case "h":
				$tmpStr = htmlentities($tmpStr);
				break;
			case "b":
				//$tmpStr = str_replace("rn", "<br>", $tmpStr);
				$tmpStr = str_replace("\r\n", "<br>", $tmpStr);
				$tmpStr = nl2br($tmpStr);
				break;
			case 'x': // xml entities 040308.2047
				$tmpStr2 = '';
				for ($i = 0; $i < strlen($tmpStr); $i++)
					if (ord($tmpStr[$i]) > 10)
						$tmpStr2 .= $tmpStr[$i];
				$tmpStr = $tmpStr2;

				$asc2uni = array();
				for ($i = 128; $i < 256; $i++) {
					$asc2uni[chr($i)] = "&#x" . dechex($i) . ";";
				}

				$tmpStr = str_replace("&", "&amp;", $tmpStr);
				$tmpStr = str_replace("<", "&lt;", $tmpStr);
				$tmpStr = str_replace(">", "&gt;", $tmpStr);
				$tmpStr = str_replace("'", "&apos;", $tmpStr);
				$tmpStr = str_replace("\"", "&quot;", $tmpStr);
				$tmpStr = str_replace("\r", "", $tmpStr);
				$tmpStr = str_replace('', "&apos;", $tmpStr);
				$tmpStr = strtr($tmpStr, $asc2uni);

				break;
			case 'M': // mail messages
				$tmpStr = str_replace('=20\r\n', "\n", $tmpStr);
				$tmpStr = str_replace('=\r\n', "\n", $tmpStr);
				$tmpStr = str_replace('\r\n', "\n", $tmpStr);
				break;
		} // switch($typ{$i})

	return $tmpStr;
} // function BXStr($str, $typ)

function BXStrSM($str) {
	if (is_null($str)) {
		return null;
	}
	// return BXStr($str, "sm");
	return BXStr($str, "m");
}

function BXStrM($str) {
	return BXStr($str, "m");
}

// 040308.2047
function BXStrX($str) {
	return BXStr($str, 'x');
}

// 010909.1127
function BXStrMail($str) {
	return BXStr($str, 'Msm');
}






// 270308.2045
// 171210.1005

//! 031216.1116 switching to mysqli_
function mysqli_result($result, $row, $field = NULL) {
	mysqli_data_seek($result, $row);
	if ($field != NULL) {
		while ($fInfo = mysqli_fetch_field($result)) {
			if ($field == $fInfo->name) {
				$f = mysqli_fetch_assoc($result);
				$fetch = $f[$field];
			}
		}
	} else {
		$f = mysqli_fetch_array($result);
		$fetch = $f[0];
	}

	return $fetch;
}

class DBEngine {
	public $host;
	public $user;
	public $password;
	public $dbName;
	public $dbLink;
	public $prevSQL;

	function Initialize($host, $user, $password, $dbName) {
		$this->host = $host;
		$this->user = $user;
		$this->password = $password;
		$this->dbName = $dbName;
	}

	function Connect() {
		$this->dbLink = mysqli_connect($this->host, $this->user, $this->password, false); //, MYSQL_CLIENT_COMPRESS);

		//! throw connection error

		mysqli_select_db($this->dbLink, $this->dbName);

		// $this->Go("SET @@global.sql_mode= ''"); // disable mysql strict mode
		if (
			$_SERVER['HTTP_HOST'] == 'test.host'
		) {
			$this->Go("SET time_zone = '+03:00'");
		}
	}

	function SetReadOnly($sts) {
		if ($sts)
			$GLOBALS['rmsocial']->dbReadOnly = 1;
		else
			$GLOBALS['rmsocial']->dbReadOnly = 0;
	}

	function SetPrintSQLs($sts) {
		if ($sts)
			$GLOBALS['rmsocial']->dbPrintSQLs = 1;
		else
			$GLOBALS['rmsocial']->dbPrintSQLs = 0;
	}

	function SetLogSQLs($sts) {
		if ($sts)
			$GLOBALS['rmsocial']->dbLogSQLs = 1;
		else
			$GLOBALS['rmsocial']->dbLogSQLs = 0;
	}

	function SetPrintReadOnly($sts) {
		if ($sts) {
			$GLOBALS['rmsocial']->dbReadOnly = 1;
			$GLOBALS['rmsocial']->dbPrintSQLs = 1;
		} else {
			$GLOBALS['rmsocial']->dbReadOnly = 0;
			$GLOBALS['rmsocial']->dbPrintSQLs = 0;
		}
	}

	function SetLogReadOnly($sts) {
		if ($sts) {
			$GLOBALS['rmsocial']->dbReadOnly = 1;
			$GLOBALS['rmsocial']->dbLogSQLs = 1;
		} else {
			$GLOBALS['rmsocial']->dbReadOnly = 0;
			$GLOBALS['rmsocial']->dbLogSQLs = 0;
		}
	}

	function Insert($in) {
		if ($in['isreplace'] == 1)
			$insertStr = 'REPLACE';
		else
			$insertStr = 'INSERT';

		if (is_array($in['fields'])) {
			$sql = "$insertStr INTO {$in['table']} SET ";

			foreach ($in['fields'] as $field => $value) {
				//echo "$field=$value";
				if ($value === NULL)
					$sqlArr[] = "$field=NULL";
				elseif ($value === 'NOW()')
					$sqlArr[] = "$field=NOW()";
				elseif (strpos($value, 'COMPRESS(') === 0)
					$sqlArr[] = "$field=$value";
				else
					$sqlArr[] = "$field='" . BXStrSM($value) . "'";
			}
			$sql .= implode(', ', $sqlArr);
		} else
			$sql = "$insertStr INTO {$in['table']} VALUES(NULL) ";
		//echo $sql; return;
		$insertId = $this->Go($sql, $in['dbName'], $in['dbLink'], $in['force']);

		return $insertId;
	}

	function Update($in) {
		foreach ($in['fields'] as $field => $value) {
			if (!$in['dontSkipEmptyFields'] && $value === NULL) {
				continue;
			} elseif ($value === 'NOW()')
				$sqlArr[] = "$field=NOW()";
			else
				$sqlArr[] = "$field='" . BXStrSM($value) . "'";
		}
		if (count($sqlArr) == 0) return;
		$sql = "UPDATE {$in['table']} SET " . implode(', ', $sqlArr) . " WHERE {$in['whereSQL']}";
		// \Socialand\Utilities::Log('db-class Update $sql', 'debug', $sql);
		//echo $sql; exit();
		$this->Go($sql, $in['dbName'], $in['dbLink']);
	}

	// 220708.2013
	// 210309.1149 added returnField parameter
	function Replace($in) {
		//print_r($in); exit();

		foreach ($in['replaceby'] as $field) {
			$tmpArr = explode('|', $field);
			if (count($tmpArr) > 1) {
				$str = '(1';
				foreach ($tmpArr as $f)
					$str .= " AND $f = '" . BXStrSM($in['fields'][$f]) . "'";
				$str .= ') ';
				$filterArr[] = $str;
			} else
				$filterArr[] = "$field = '" . BXStrSM($in['fields'][$field]) . "'";
		}
		if ($in['replacebyCondition'] == 'OR')
			$whereSQL = implode(' OR ', $filterArr);
		else
			$whereSQL = implode(' AND ', $filterArr);

		$record = $this->GetRecord(array(
			'dbName' => $in['dbName'],
			'dbLink' => $in['dbLink'],
			'table' => $in['table'],
			'whereSQL' => $whereSQL
		));

		if ($record == '') {
			$insertId = $this->Insert($in);

			//return $insertId;

			if ($in['returnField'] != '' && $in['fields'][$in['returnField']] != '') // 090713.0744
				return $in['fields'][$in['returnField']];
			else
				return $insertId;
		} else {
			$in['whereSQL'] = $whereSQL;
			if ($in['ignoreonupdate'] != '')
				foreach ($in['ignoreonupdate'] as $field)
					unset($in['fields'][$field]);
			$this->Update($in);
			if ($in['returnField'] != '')
				return $record[$in['returnField']];
		}
	}

	function Delete($in) {
		$sql = "DELETE FROM {$in['table']} WHERE {$in['whereSQL']}";
		$res = $this->Go($sql, $in['dbName'], $in['dbLink']);

		if (!$res) {
			throw new Exception('DB Error Delete: ' . $this->currentError . ' SQL: ' . $this->currentErrorSQL, 0);
		}
	}

	// ~110209.1335 added 'MySQL server has gone away' part
	function LogError($sqlStr, $err) {
		$this->currentError = $err;
		$this->currentErrorSQL = $sqlStr;

		// check for db crash
		if (preg_match("|Table '(.*)' is marked as crashed|", $err, $match) > 0) {

			// 030216.2302 added for recent ./booxys_touristalia/bx_sessions style table name
			$match[1] = str_replace('./', '', $match[1]);
			$match[1] = str_replace('/', '.', $match[1]);

			$sql = "REPAIR TABLE " . $match[1];
			RmsocialDebug($sql);
			$this->Go($sql);
		}

		if (preg_match('|MySQL server has gone away|', $err) > 0) {
			// if ($_SERVER["HTTP_HOST"] == "localhost")
			// 	;//$fp = fopen('C:\worx\booxys\clients\hotel-board\site010509\engine\lab\db-error.log', 'a');
			// else
			// 	;//$fp = fopen('/var/www/html/hotel-board/engine/lab/db-error.log', 'a');
			// fwrite($fp, "\n\n".date('Y-m-d H:i:s')."\n".$this->tDbPrevSQL."\n".$sqlStr."\n".$err."\n".print_r($GLOBALS, true));
			// fclose($fp);

			// exit();

			// return;
		}

		// avoid recursive sql errors
		if (substr_count($sqlStr, "INSERT INTO db_errors") > 0) {
			//: escalate the error severity level since db_errors table could not be inserted
			return;
		}

		$sql = "INSERT INTO db_errors SET
				dat=NOW(),
				sqlstr='" . BXStrSM($sqlStr) . "',
				err='" . BXStrSM($err) . "'
			";
		$this->Go($sql);

		if (substr_count($sqlStr, "INSERT INTO db_errors") == 0 && substr_count($sqlStr, "INSERT INTO logs") == 0) {
			throw new Exception('DB Error GetRecord: ' . $this->currentError . ' SQL: ' . $this->currentErrorSQL, 6);
		}
	}

	function Go($sqlStr, $dbName = NULL, $dbLink = NULL, $force = 0, $charSet = NULL) {
		if ($GLOBALS['rmsocial']->dbPrintSQLs) {
			echo $sqlStr . "\n\n";
		} elseif ($GLOBALS['rmsocial']->dbLogSQLs) {
			RmsocialDebug($sqlStr);
		}

		if (($GLOBALS['rmsocial']->dbReadOnly && !$force) && (preg_match('|^INSERT |Ui', $sqlStr) > 0 || preg_match('|^UPDATE |Ui', $sqlStr) > 0 || preg_match('|^REPLACE |Ui', $sqlStr) > 0 || preg_match('|^DELETE |Ui', $sqlStr) > 0 || preg_match('|^TRUNCATE |Ui', $sqlStr) > 0)) {
			//echo $sqlStr."\n";
			return;
		}

		if ($this->dbLink == '') {
			$this->Initialize($GLOBALS['_rmsocial']['settings']['db']['host'], $GLOBALS['_rmsocial']['settings']['db']['user'], $GLOBALS['_rmsocial']['settings']['db']['password'], $GLOBALS['_rmsocial']['settings']['db']['dbName']);
			$this->Connect();
		}

		if ($charSet != NULL)
			$prevCharSet = mysqli_get_charset($this->dbLink);

		if ($dbLink != NULL) {
			$prevLink = $this->dbLink;
			$this->dbLink = $dbLink;
		}

		if ($dbName != NULL && $dbLink != NULL)
			mysqli_select_db($this->dbLink, $dbName);
		elseif ($dbName != NULL)
			mysqli_select_db($this->dbLink, $dbName);

		if ($charSet != NULL)
			mysqli_set_charset($this->dbLink, $charSet);

		if ($GLOBALS['rmsocial']->dbLogSQLs)
			$startTime = BXGetMicroTime();

		//if (! ( $result = mysqli_db_query($this->dbName, $sqlStr, $this->dbLink) ) )
		if (!($result = mysqli_query($this->dbLink, $sqlStr)))
			$this->LogError($sqlStr, mysqli_error($this->dbLink));
		elseif (preg_match('|^INSERT |Ui', $sqlStr) > 0) {
			$insertId = mysqli_insert_id($this->dbLink);
		}

		if ($GLOBALS['rmsocial']->dbLogSQLs) {
			$queryTime = (BXGetMicroTime() - $startTime) * 1000;
			RmsocialDebug(substr($sqlStr, 0, 1000), NULL, 'SQL ' . $queryTime);
		}

		if ($dbLink != NULL) {
			$this->dbLink = $prevLink;
		}

		if ($dbName != NULL)
			mysqli_select_db($this->dbLink, $this->dbName);

		if ($charSet != NULL)
			mysqli_set_charset($this->dbLink, $prevCharSet->charset);

		$this->prevSQL = $sqlStr;

		if (preg_match('|^INSERT |Ui', $sqlStr) > 0) {
			return $insertId;
		} else {
			return $result;
		}
	}

	// 150907.2047
	function GetRecord($in) {
		if ($in['fields'] == '')
			$fieldSQL .= "*";
		else
			$fieldSQL .= $in['fields'];

		if ($in['whereSQL'] == '')
			$in['whereSQL'] = '1';

		if ($in['orderBy'] != '') {
			if (strtoupper($in['orderBy']['order']) != 'DESC')
				$in['orderBy']['order'] = 'ASC';

			$orderBySQL = "ORDER BY {$in['orderBy']['field']} {$in['orderBy']['order']}";
		} elseif ($in['orderSQL'] != '')
			$orderBySQL = "ORDER BY " . $in['orderSQL'];

		$sql = "SELECT $fieldSQL FROM {$in['table']} WHERE {$in['whereSQL']}  $orderBySQL";

		if ($in['debugSQL']) {
			\Socialand\Utilities::Log('sql', 'debug', $sql);
		}

		$res = $this->Go($sql, $in['dbName'], $in['dbLink'], 0, $in['charSet']);

		$info = mysqli_fetch_assoc($res);

		return $info;
	} // function GetRecord($in) {

	// 150907.2050
	function GetRecords($in) {

		if ($in['groupFields'] != '')
			$groupFieldSQL = $in['groupFields'];
		elseif ($in['fields'] == '')
			$fieldSQL .= "*";
		else
			$fieldSQL .= $in['fields'];

		if ($in['groupSQL'] != '')
			$groupSQL = " GROUP BY {$in['groupSQL']}";

		if ($in['orderBy'] != '') {
			if (strtoupper($in['orderBy']['order']) != 'DESC')
				$in['orderBy']['order'] = 'ASC';

			$orderBySQL = "ORDER BY {$in['orderBy']['field']} {$in['orderBy']['order']}";
		} elseif ($in['orderSQL'] != '')
			$orderBySQL = "ORDER BY " . $in['orderSQL'];

		if ($in['limit'] != '') {
			if (($in['limit']['fromLine'] != '' || $in['limit']['fromLine'] == 0) && $in['limit']['lineCount'] != '')
				$limitSQL = " LIMIT {$in['limit']['fromLine']}, {$in['limit']['lineCount']}";
		}

		if ($in['whereSQL'] == '')
			$in['whereSQL'] = '1';
		elseif (preg_match('|^[\s]*AND|Ui', $in['whereSQL']) > 0) // need to get rid of this
			$in['whereSQL'] = '1 ' . $in['whereSQL'];

		$sql = "SELECT $groupFieldSQL $fieldSQL FROM {$in['table']} WHERE {$in['whereSQL']} $groupSQL $orderBySQL $limitSQL";

		//RmsocialDebug($sql);

		/*if ($in['groupSQL'] != '') {
				echo $sql;
				exit();
			}*/
		//\Socialand\Utilities::Log('sql-in','debug', $in);
		if ($in['debugSQL']) {
			\Socialand\Utilities::Log('sql', 'debug', $sql);
		}
		//\Socialand\Utilities::Log('sql','debug', $sql);

		$res = $this->Go($sql, $in['dbName'], $in['dbLink'], 0, $in['charSet']);

		if ($in['returnRes'] == 1) {
			return $res;
		}

		$records = array();
		while ($info = mysqli_fetch_assoc($res)) {
			if ($in['onefieldnam'] != '')
				$records[] = $info[$in['onefieldnam']];
			elseif ($in['onefield'] != '')
				$records[] = $info[$in['fields']];
			else
				$records[] = $info;
		}

		return $records;
	} // function GetRecords($in) {

	// 080708.1018
	function PackInsert($in) {
		if ($in['packsize'] == '')
			$in['packsize'] = 10;

		$fieldCount = count($in['fields']);
		$fieldNo = 0;
		foreach ($in['fields'] as $field) {
			$fieldNo++;
			$fieldSQL .= "$field";
			if ($fieldNo < $fieldCount)
				$fieldSQL .= ", ";
		}

		$packCount = ceil(count($in['values']) / $in['packsize']);

		for ($p = 0; $p < $packCount; $p++) {
			$pack = array_slice($in['values'], $p * $in['packsize'], $in['packsize']);

			$rowCount = count($pack);
			$rowNo = 0;
			$valuesSQL = '';
			foreach ($pack as $row) {
				$rowNo++;
				$valuesSQL .= '(';
				$fieldCount = count($row);
				$fieldNo = 0;
				foreach ($row as $value) {
					$fieldNo++;
					if ($value === NULL)
						$valuesSQL .= "''";
					elseif ($value === 'NOW()')
						$valuesSQL .= "NOW()";
					else
						$valuesSQL .= "'" . BXStrSM($value) . "'";
					if ($fieldNo < $fieldCount)
						$valuesSQL .= ', ';
				}
				$valuesSQL .= ')';
				if ($rowNo < $rowCount)
					$valuesSQL .= ', ';
			} // foreach ($pack as $row) {

			$sql = "INSERT INTO {$in['table']} ($fieldSQL) VALUES $valuesSQL";

			$this->Go($sql, $in['dbName'], $in['dbLink']);
		} // for ($p=0; $p<$packCount; $p++) {

		return mysqli_insert_id($this->dbLink);
	} // function PackInsert($in) {

	function GetCount($res) {
		return mysqli_num_rows($res);
	}
} // class DBEngine





$rmsocial = array(
	'db' => new DBEngine,
	'debug' => 'screen'
);

if ($rmsocial['db']->dbLink == '') {
	$rmsocial['db']->Initialize($GLOBALS['_rmsocial']['settings']['db']['host'], $GLOBALS['_rmsocial']['settings']['db']['user'], $GLOBALS['_rmsocial']['settings']['db']['password'], $GLOBALS['_rmsocial']['settings']['db']['dbName']);
	$rmsocial['db']->Connect();
}

$id = $_REQUEST['id'];
$platform = $_REQUEST['platform'];

$args = array(
	'table' => 'test_table',
	'fields' => array(
		'platform' => $platform
	),
	'whereSQL' => 'id="'.$id.'"'
);

$rmsocial['db']->Update($args);