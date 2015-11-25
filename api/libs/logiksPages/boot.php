<?php
/*
 * LogiksPages helps generate pages and related components including
 *    Pages, SiteMap, Theme, Layout, HTMLAssets
 *
 * This includes interdependent classes which come togethar to result in ui rendering.
 *
 * Author: Bismay Kumar Mohapatra bismay4u@gmail.com on 24/02/2012
 * Version: 1.0
 */

if(!defined('ROOT')) exit('No direct script access allowed');

include_once dirname(__FILE__)."/HTMLAssets.inc";
include_once dirname(__FILE__)."/LogiksPage.inc";
include_once dirname(__FILE__)."/LogiksTheme.inc";

if(!function_exists("_css")) {
	function _cssLink($cssLnk,$themeName=null) {
		if(is_array($cssLnk) && count($cssLnk)<=0) return false;
		elseif(is_array($cssLnk) && count($cssLnk)==1 && strlen($cssLnk[0])==0) return false;
		elseif(is_string($cssLnk)) $cssLnk=explode(",", $cssLnk);

		if($themeName=="*" || $themeName==null) $themeName=APPS_THEME;

		$lx=_service("resources","","raw")."&type=css&src=".implode(",", $cssLnk)."&theme=$themeName";

		return $lx;
	}
	function _css($cssLnk,$themeName=null,$browser="",$media="") {
		//$lx=_cssLink($cssLnk, $themeName);
		$lx=LogiksSession::getInstance()->htmlAssets()->getAssetURL($cssLnk,'css');
		if(strlen($lx)<=0) return false;

		$html="";
		if($browser!=null && strlen($browser)>0) {
			$html.="<!--[if $browser]>\n";
			$html.="<link href='$lx' rel='stylesheet' type='text/css'";
			if($media!=null && strlen($media)>0) $html.=" media='$media'";
			$html.=" />\n";
			$html.="<![endif]-->\n";
		} else {
			$html.="<link href='$lx' rel='stylesheet' type='text/css'";
			if($media!=null && strlen($media)>0) $html.=" media='$media'";
			$html.=" />\n";
		}
		return $html;
	}

	function _jsLink($jsLnk,$themeName=null) {
		if(is_array($jsLnk) && count($jsLnk)<=0) return false;
		elseif(is_array($jsLnk) && count($jsLnk)==1 && strlen($jsLnk[0])==0) return false;
		elseif(is_string($jsLnk)) $jsLnk=explode(",", $jsLnk);

		if($themeName=="*" || $themeName==null) $themeName=APPS_THEME;

		$lx=_service("resources","","raw")."&type=js&src=".implode(",", $jsLnk)."&theme=$themeName";

		return $lx;
	}
	function _js($jsLnk,$themeName=null,$browser="") {
		//$lx=_jsLink($jsLnk, $themeName);
		$lx=LogiksSession::getInstance()->htmlAssets()->getAssetURL($jsLnk,'js');
		if(strlen($lx)<=0) return false;

		$html="";
		if($browser!=null && strlen($browser)>0) {
			$html.="<!--[if $browser]>\n";
			$html.="<script src='$lx' type='text/javascript' language='javascript'></script>\n";
			$html.="<![endif]-->\n";
		} else {
			$html.="<script src='$lx' type='text/javascript' language='javascript'></script>\n";
		}
		return $html;
	}

	function _slug() {
		if(isset($_ENV['PAGESLUG'])) return $_ENV['PAGESLUG'];
		return array();
	}

  	//used only for printing pages
	function _templatePage($file,$dataArr=null) {
		if($dataArr==null) $dataArr=[];
		if(isset($_ENV['PAGEVAR']) && is_array($_ENV['PAGEVAR'])) {
			foreach ($_ENV['PAGEVAR'] as $key => $value) {
				$dataArr['PAGE'][$key]=$value;
			}
		}
		return _template($file,$dataArr);
	}
}
?>
