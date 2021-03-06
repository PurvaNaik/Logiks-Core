<?php
if(!defined('ROOT')) exit('No direct script access allowed');

if(!isset($_POST['mauth'])) {
	echo "<h5>Securing Access Authentication ... </h5>";
}

runHooks("preAuth");

$userid=clean($_POST['userid']);
$pwd=clean($_POST['password']);

if(!isValidMd5($pwd)) $pwd=md5($pwd);

if(isset($_POST['site'])) $domain=$_POST['site']; 
elseif(isset($_REQUEST['site'])) $domain=$_REQUEST['site']; 
else $domain=SITENAME;

loadConfigs(ROOT . "config/auth.cfg");
include ROOT."api/helpers/pwdhash.php";
//include ROOT."api/security.php";

/*
CLEAR_OLD_SESSION=true
@session_start();
session_destroy();
session_start();
*/
$dbLink=_db(true);
$dbLogLink=null;//LogDB::getInstance()->getLogDBCon();

if(!$dbLink->isAlive()) {
	relink("Database Connection Error",$domain);
}
if($userid == '') {
	relink('Login ID missing',$domain);
}
if($pwd == '') {
	relink('Password missing',$domain);
}

$date=date('Y-m-d');

$userFields=explode(",", USERID_FIELDS);
if(count($userFields)<=0) $userFields=["userid"];
if(CASE_SENSITIVE_AUTH=="true") {
	foreach ($userFields as $key => $value) {
		unset($userFields[$key]);
		$userFields["BINARY {$value}"]=$userid;
	}
} else {
	foreach ($userFields as $key => $value) {
		unset($userFields[$key]);
		$userFields["{$value}"]=$userid;
	}
}

$sql=_db(true)->_selectQ(_dbTable("users",true),"id, guid, userid, pwd, pwd_salt, privilegeid, accessid, name, email, mobile, blocked, avatar, avatar_type")->_whereOR("expires",[
			"0000-00-00",["NULL","NU"],["now()","GT"]
		])->_where($userFields,"AND","OR");

$result=$sql->_get();

if(!empty($result)) {
	$data=$result[0];
} else {
	relink("Sorry, you have not yet joined us or your userid has expired.",$domain);
}

if(!matchPWD($data['pwd'],$pwd, $data['pwd_salt'])) {
	relink("UserID/Password Wrong/Mismatch",$domain);
}
if($data['blocked']=="true") {
	relink("Sorry, you are currently blocked by system admin.",$domain);
}

$accessData=_db(true)->_selectQ(_dbTable("access",true),"sites,name as access_name")->_where([
		"id"=>$data['accessid'],
		"blocked"=>"false"
	])->_get();
$privilegeData=_db(true)->_selectQ(_dbTable("privileges",true),"id,md5(concat(id,name)) as hash,name as privilege_name")->_where([
		"id"=>$data['privilegeid'],
		"blocked"=>"false"
	])->_get();

if(empty($accessData)) {
	relink("No Accessibilty Defined For You Or Blocked By Admin.",$domain);
} else {
	$accessData=$accessData[0];
}
if(empty($privilegeData)) {
	relink("No Privileges Defined For You Or Blocked By Admin.", $domain);
} else {
	$privilegeData=$privilegeData[0];
}

$allSites=explode(",",$accessData['sites']);
if($accessData['sites']=="*") {
	$allSites=getAccessibleSitesArray();
}
if(count($allSites)>0) {
	$_SESSION['SESS_ACCESS_SITES']=$allSites;
} else {
	relink("No Accessible Site Found For Your UserID");
}
if(!in_array($domain,$allSites)) {
	relink("Sorry, You [UserID] do not have access to requested site.", $domain);
}

$_ENV['AUTH-DATA']=array_merge($data,$accessData);
$_ENV['AUTH-DATA']=array_merge($_ENV['AUTH-DATA'],$privilegeData);

loadHelpers("mobility");

$_ENV['AUTH-DATA']['device']=getUserDeviceType();
$_ENV['AUTH-DATA']['client']=_server("REMOTE_ADDR");
if(isset($_POST['persistant']) && $_POST['persistant']=="true") {
	$_ENV['AUTH-DATA']['persistant']="true";
} else {
	$_ENV['AUTH-DATA']['persistant']="false";
}
$_ENV['AUTH-DATA']['sitelist']=$allSites;

checkBlacklists($data,$domain,$dbLink,$userid);

runHooks("postAuth");

initializeLogin($userid, $domain);


//All Functions Required By Authentication System
function relink($msg,$domain) {
	_log("Login Attempt Failed","login",LogiksLogger::LOG_ALERT,[
				"userid"=>$_POST['userid'],
				"site"=>$domain,
				"device"=>getUserDeviceType(),
				"client_ip"=>$_SERVER['REMOTE_ADDR'],
				"msg"=>$msg]);

	$_SESSION['SESS_ERROR_MSG']=$msg;
	
	$onerror="";
	if((ALLOW_LOGIN_RELINKING=="true" || ALLOW_LOGIN_RELINKING)) {
		if(isset($_REQUEST['onerror'])) $onerror=$_REQUEST['onerror'];
	}
	if(strlen($onerror)==0 || $onerror=="*") {
		$s=SiteLocation."login.php";
		if(strlen($domain)>0) $s.="?site=$domain";
		$onerror=$s;
	}
	if(substr($onerror,0,7)=="http://" || substr($onerror,0,8)=="https://" ||
		substr($onerror,0,2)=="//" || substr($onerror,0,2)=="./" || substr($onerror,0,1)=="/") {
			header("Location:$onerror");
			exit($msg);
	} else {
		header("SESS_ERROR_MSG:".$msg,false);
		exit($onerror);
	}
}
function getAccessibleSitesArray() {
	$arr=scandir(ROOT.APPS_FOLDER);
	unset($arr[0]);unset($arr[1]);
	$out=array();
	foreach($arr as $a=>$b) {
		if(is_file(ROOT.APPS_FOLDER.$b)) {
			unset($arr[$a]);
		} elseif(is_dir(ROOT.APPS_FOLDER.$b) && !file_exists(ROOT.APPS_FOLDER.$b."/apps.cfg")) {
			unset($arr[$a]);
		} else {
			array_push($out,$b);
		}
	}
	return $out;
}
//Logging And Checking Functions
function checkBlacklists($data,$domain,$dbLink,$userid) {
	$ls=new LogiksSecurity();
	if($ls->isBlacklisted("login",$domain)) {
		relink("You are currently Blacklisted On Server, Please contact Site Admin.",$domain);
	} else {
		return false;
	}
}

//LogBook Checking
function initializeLogin($userid,$domain,$params=array()) {
	startNewSession($userid, $domain, $params);

	_log("Login Successfull","login",LogiksLogger::LOG_WARNING,[
				"guid"=>$_SESSION['SESS_GUID'],
				"userid"=>$_SESSION['SESS_USER_ID'],
				"username"=>$_SESSION['SESS_USER_NAME'],
				"site"=>$domain,
				"device"=>$_ENV['AUTH-DATA']['device'],
				"client_ip"=>$_SERVER['REMOTE_ADDR']]);
	
	gotoSuccessLink();
}
//All session functions
function startNewSession($userid, $domain, $params=array()) {
	session_regenerate_id();
	$data=$_ENV['AUTH-DATA'];
	//printArray($data);exit();

	$_SESSION['SESS_GUID'] = $data['guid'];
	$_SESSION['SESS_USER_ID'] = $data['userid'];
	$_SESSION['SESS_PRIVILEGE_ID'] = $data['privilegeid'];
	$_SESSION['SESS_ACCESS_ID'] = $data['accessid'];
	
	$_SESSION['SESS_PRIVILEGE_NAME'] = $data['privilege_name'];
	$_SESSION['SESS_ACCESS_NAME'] = $data['access_name'];
	$_SESSION['SESS_ACCESS_SITES'] = $data['sitelist'];

	$_SESSION["SESS_PRIVILEGE_HASH"]=md5($_SESSION["SESS_PRIVILEGE_NAME"].$_SESSION["SESS_PRIVILEGE_ID"]);

	$_SESSION['SESS_USER_NAME'] = $data['name'];
	$_SESSION['SESS_USER_EMAIL'] = $data['email'];
	$_SESSION['SESS_USER_CELL'] = $data['mobile'];

	$_SESSION['SESS_USER_AVATAR'] = $data['avatar_type']."::".$data['avatar'];

	$_SESSION['SESS_LOGIN_SITE'] = $domain;
	$_SESSION['SESS_ACTIVE_SITE'] = $domain;
	$_SESSION['SESS_TOKEN'] = session_id();
	$_SESSION['SESS_SITEID'] = SiteID;
	$_SESSION['SESS_LOGIN_TIME'] =time();
	$_SESSION['MAUTH_KEY'] = generateMAuthKey();
	
	if($data['privilegeid']<=1) {
		$_SESSION["SESS_FS_FOLDER"]=ROOT;
		$_SESSION["SESS_FS_URL"]=SiteLocation;
	} else {
		$_SESSION["SESS_FS_FOLDER"]=ROOT.APPS_FOLDER.$domain."/";
		$_SESSION["SESS_FS_URL"]=SiteLocation.APPS_FOLDER.$domain."/";
	}

	if(strlen($_SESSION['SESS_USER_NAME'])<=0) {
		$_SESSION['SESS_USER_NAME']=$_SESSION['SESS_USER_ID'];
	}

	LogiksSession::getInstance(true);

	header_remove("SESSION-KEY");
	header("SESSION-KEY:".session_id(),false);
	header("SESSION-MAUTH:".$_SESSION['MAUTH_KEY'],false);

	setcookie("LOGIN", "true", time()+36000);
	setcookie("USER", $_SESSION['SESS_USER_ID'], time()+36000);
	setcookie("TOKEN", $_SESSION['SESS_TOKEN'], time()+36000);
	setcookie("SITE", $_SESSION['SESS_LOGIN_SITE'], time()+36000);

	if($data['persistant']=="true") {
		_db(true)->_deleteQ(_dbTable("cache_sessions",true),[
				"guid"=>$_SESSION['SESS_GUID'],
				"userid"=>$_SESSION['SESS_USER_ID'],
				"site"=>$domain,
			])->_run();
		_db(true)->_insertQ1(_dbTable("cache_sessions",true),[
				"guid"=>$_SESSION['SESS_GUID'],
				"userid"=>$_SESSION['SESS_USER_ID'],
				"site"=>$domain,
				"device"=>$_ENV['AUTH-DATA']['device'],
				"session_key"=>$_SESSION['SESS_TOKEN'],
				"session_data"=>json_encode($_SESSION),
				"global_data"=>json_encode($GLOBALS),
				"client_ip"=>$_SERVER['REMOTE_ADDR'],
				"creator"=>$_SESSION['SESS_USER_ID'],
			])->_run();
	}
}
function logoutOldSessions($userid, $domain, $params=array()) {
	_db(true)->_deleteQ(_dbTable("cache_sessions",true),[
				"guid"=>$_SESSION['SESS_GUID'],
				"userid"=>$_SESSION['SESS_USER_ID'],
				"site"=>$domain,
			])->_run();
}
function restoreOldSession($sessionData, $userid, $domain, $params=array()) {
	$data=$_ENV['AUTH-DATA'];
	$sessionID=$sessionData['token'];

	$logData=_db(true)->_selectQ(_dbTable("cache_sessions",true),"*",
						array(
							"session_key"=>$sessionID,
							"userid"=>$userid,
							"site"=>$domain,
							"device"=>getUserDeviceType(),
							"client_ip"=>$_SERVER['REMOTE_ADDR'])
					)->_get();

	if(!empty($logData)) {
		$logData=$logData[0];

		$logData['session_data']=stripslashes($logData['session_data']);
		$logData['session_data']=json_decode($logData['session_data'],true);
		
		session_regenerate_id();
		foreach($logData['session_data'] as $key => $value) {
			$_SESSION[$key]=$value;
		}
		setcookie("LOGIN", "true", time()+36000);
		setcookie("USER", $_SESSION['SESS_USER_ID'], time()+36000);
		setcookie("TOKEN", $_SESSION['SESS_TOKEN'], time()+36000);
		setcookie("SITE", $_SESSION['SESS_LOGIN_SITE'], time()+36000);

		//$logData['global_data']$GLOBALS
		//printArray($_SESSION);exit();

		gotoSuccessLink();
	} else {
		logoutOldSessions($userid, $domain, $params);
		startNewSession($userid, $domain, $params);
	}
}
function gotoSuccessLink() {
	$onsuccess="";
	if((ALLOW_LOGIN_RELINKING=="true" || ALLOW_LOGIN_RELINKING)) {
		if(isset($_REQUEST['onsuccess'])) $onsuccess=$_REQUEST['onsuccess'];
	}

	$domain=$_SESSION['SESS_ACTIVE_SITE'];//ACTIVE
	if(ALLOW_MAUTH=="true") {
		if(isset($_POST['mauth']) && $_POST['mauth']=="authkey") {
			echo $_SESSION['MAUTH_KEY'];
		} elseif(isset($_POST['mauth']) && $_POST['mauth']=="jsonkey") {
			$arr=array(
					"user"=>$_SESSION['SESS_USER_ID'],
					"authkey"=>$_SESSION['MAUTH_KEY'],
					"date"=>date("Y-m-d"),
					"time"=>date("H:i:s"),
					"site"=>$domain,
					"client"=>_server('REMOTE_ADDR'),
					"token"=>$_SESSION['SESS_TOKEN'],
				);
			header("Content-Type:text/json");
			echo json_encode($arr);
		} else {
			echo "<h5>Securing Access Authentication ... </h5>";
			if(strlen($onsuccess)==0 || $onsuccess=="*")
				header("location: ".SiteLocation.$domain);
			else {
				if(substr($onsuccess,0,7)=="http://" || substr($onsuccess,0,8)=="https://" ||
					substr($onsuccess,0,2)=="//" || substr($onsuccess,0,2)=="./" || substr($onsuccess,0,1)=="/") {
						header("location: $onsuccess");
				}
			}
		}
	} else {
		//echo "<h5>Securing Access Authentication ... </h5>";
		if(strlen($onsuccess)==0 || $onsuccess=="*") {
			header("location: "._link(getConfig("PAGE_HOME")));
		} else {
			if(substr($onsuccess,0,7)=="http://" || substr($onsuccess,0,8)=="https://" ||
				substr($onsuccess,0,2)=="//" || substr($onsuccess,0,2)=="./" || substr($onsuccess,0,1)=="/") {
					header("location: $onsuccess");
			}
		}
	}
	exit();
}
?>
