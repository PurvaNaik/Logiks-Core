<?php
/*
 * This file contains only the initiating functions or auto functions that are called forward during
 * loading or shutdown sequences for logiks request processing.
 * Most of the functions can be called only once and after that can't be called forth at all.
 *
 * Author: Bismay Kumar Mohapatra bismay4u@gmail.com
 * Version: 1.0
 */

include_once dirname(__FILE__). "/libs/logikssingleton.inc";
include_once dirname(__FILE__). "/libs/logiksclassloader.inc";

if(!function_exists("__cleanup")) {
	function __cleanup() {
		if(isset($_ENV['SOFTHOOKS']['SHUTDOWN'])) {
			foreach ($_ENV['SOFTHOOKS']['SHUTDOWN'] as $hook) {
				executeUserParams($hook["FUNC"],$hook["OBJ"]);
			}
		}

		runHooks("shutdown");

		// saveSettings();
		// saveSiteSettings();

		MetaCache::getInstance()->dumpAllCache();
		DataCache::getInstance()->dumpAllCache();
		//RequestCache::getInstance()->dumpAllCache();

		//Database::closeAll();
	}

	function logiksRequestPreboot() {
		register_shutdown_function("__cleanup");

		// platform neurtral url handling
		if(isset($_SERVER['REQUEST_URI'] ) ) {
			$request_uri = $_SERVER['REQUEST_URI'];
		} else {
			$request_uri = $_SERVER['SCRIPT_NAME'];
			// Append the query string if it exists and isn't null
			if(isset( $_SERVER['QUERY_STRING'] ) && !empty( $_SERVER['QUERY_STRING'] ) ) {
				$request_uri .= '?' . $_SERVER['QUERY_STRING'];
			}
			$_SERVER['REQUEST_URI'] = $request_uri;
		}
		if(!isset($_SERVER['ACTUAL_URI'])) {
			$_SERVER['ACTUAL_URI']=$_SERVER['REQUEST_URI'];
		}
		if(empty( $_SERVER['PHP_SELF'])) {
			$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
		}

		$hostProtocol="http://";
		if(isset($_SERVER['HTTPS'])) {
			$hostProtocol="https://";
		}
		define('SiteProtocol',str_replace("://","",$hostProtocol));
	}

	function logiksRequestBoot() {
		if(LogiksSingleton::funcCheckout("logiksRequestBoot")) {
			$page=str_replace("?".$_SERVER['QUERY_STRING'],"",$_SERVER['REQUEST_URI']);
			$page=str_replace(InstallFolder, "", $page);
			if(substr($page, 0,1)=="/") $page=substr($page, 1);
			if($page==null || strlen($page)<=0) {
				$page="home";
			}
			define("PAGE",$page);
			$_SESSION['QUERY']=$_GET;

			$_SERVER['REQUEST_PATH']=SiteProtocol."://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];

			$dm=new DomainMap();
			$dm->detect();

			if(!defined("SITENAME")) {
				trigger_error("SITE NOT DEFINED", E_USER_ERROR);
			}

			if(!isset($_SESSION['SESS_USER_ID'])) $_SESSION['SESS_USER_ID']="";
			if(!isset($_SESSION['SESS_USER_NAME'])) $_SESSION['SESS_USER_NAME']="Guest";
		}
	}

	LogiksSingleton::funcRegister("logiksPreboot");
	LogiksSingleton::funcRegister("logiksRequestBoot");

	LogiksClassLoader::getInstance();
}
?>
