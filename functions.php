<?php
// ccenter common functions
// $Id: functions.php,v 1.24 2008/05/17 05:55:47 nobu Exp $

global $xoopsDB;		// for blocks scope
// using tables
define("FORMS", $xoopsDB->prefix("ccenter_form"));
define('CCMES', $xoopsDB->prefix('ccenter_message'));
define('CCLOG', $xoopsDB->prefix('ccenter_log'));

$myts =& MyTextSanitizer::getInstance();

define('_STATUS_NONE',   '-');
define('_STATUS_ACCEPT', 'a');
define('_STATUS_REPLY',  'b');
define('_STATUS_CLOSE',  'c');
define('_STATUS_DEL',    'x');

define('_DB_STORE_LOG',  0);	// logging only in db
define('_DB_STORE_YES',  1);	// store information in db
define('_DB_STORE_NONE', 2);	// query not store in db

if (defined('_CC_STATUS_NONE')) {
    global $msg_status, $export_range;
    $msg_status = array(
	_STATUS_NONE  =>_CC_STATUS_NONE,
	_STATUS_ACCEPT=>_CC_STATUS_ACCEPT,
	_STATUS_REPLY =>_CC_STATUS_REPLY,
	_STATUS_CLOSE =>_CC_STATUS_CLOSE,
	_STATUS_DEL   =>_CC_STATUS_DEL);

    $export_range = array(
	'm0'=>_CC_EXPORT_THIS_MONTH,
	'm1'=>_CC_EXPORT_LAST_MONTH,
	'y0'=>_CC_EXPORT_THIS_YEAR,
	'y1'=>_CC_EXPORT_LAST_YEAR,
	'all'=>_CC_EXPORT_ALL);

    define('_CC_TPL_NONE',  0);
    define('_CC_TPL_BLOCK', 1);
    define('_CC_TPL_FULL',  2);
    define('_CC_TPL_FRAME', 3);
}

define('LABEL_ETC', '*');	// radio, checkbox widget 'etc' text input.
define('OPTION_ATTRS', 'size,rows,maxlength,cols,prop,notify_with_email');

// attribute config option expanding
function get_attr_value($pri, $name) {
    static $defs;		// default option value

    if (isset($pri) && isset($pri[$name])) return $pri[$name];
    if (!isset($defs)) {
	$defs = array();
	foreach (explode(',', OPTION_ATTRS) as $key) {
	    $defs[$key] = 0;
	}
	// override module config values
	$mydirname = basename(dirname(__FILE__));
	if (is_object($GLOBALS['xoopsModule']) &&
	    $GLOBALS['xoopsModule']->getVar('dirname')==$mydirname) {
	    $def_attr = $GLOBALS['xoopsModuleConfig']['def_attrs'];
	} else {
	    $module_handler =& xoops_gethandler('module');
	    $module =& $module_handler->getByDirname($mydirname);
	    $config_handler =& xoops_gethandler('config');
	    $configs =& $config_handler->getConfigsByCat(0, $module->getVar('mid'));
	    $def_attr = $configs['def_attrs'];
	}
	foreach (preg_split('/(,|\r?\n)/', $def_attr) as $ln) {
	    if (preg_match('/^\s*([^=]+)\s*=\s*(.+)$/', $ln, $d)
		&& isset($defs[$d[1]])) {
		$defs[$d[1]] = intval($d[2]); // XXX: only numeric?
	    }
	}
    }
    if (isset($defs[$name])) return $defs[$name];
    return null;
}

function get_form_attribute($defs) {
    $num = 0;
    $result = array();
    $types = array('text', 'checkbox', 'radio', 'textarea', 'select', 'hidden','const', 'mail', 'file');
    foreach (preg_split('/\r?\n/', $defs) as $ln) {
	$ln = trim($ln);
	if (empty($ln)) continue;
	if (preg_match('/^\s*#/', $ln)) {
	    $result[] = array('comment'=>preg_replace('/^\s*#/','', $ln));
	    continue;
	}
	$opts = explode(",", $ln);
	$name = array_shift($opts);
	if (preg_match('/=(.*)$/', $name, $d)) { // use alternative label
	    $label = $d[1];
	    $name = preg_replace('/=(.*)$/', '', $name);
	} else {
	    $label = $name;
	}
	$type='text';
	$comment='';
	$attr = array();
	if (count($opts) && in_array($opts[0], $types)) {
	    $type = array_shift($opts);
	}
	if (preg_match('/\*$/', $name)) { // syntax convention
	    $attr['check'] = 'require';
	    $name = preg_replace('/\s*\*$/', '', $name);
	    $label = preg_replace('/\s*\*$/', _MD_REQUIRE_MARK, $label);
	}
	while (isset($opts[0]) && (preg_match('/^(size|rows|maxlength|cols|prop)=(\d+)$/', $opts[0], $d) || preg_match('/^(check)=(.+)$/', $opts[0], $d))) {
	    array_shift($opts);
	    $attr[$d[1]] = $d[2];
	}
	$options = array();
	if (count($opts)) {
	    while(count($opts) && !preg_match('/^\s*#/', $opts[0])) {
		$v = array_shift($opts);
		$sv = preg_split('/=/', $v, 2);
		if (count($sv)>1) {
		    $options[$sv[0]] = $sv[1];
		} else {
		    $options[strip_tags($v)] = preg_replace('/\+$/', '', $v);
		}
	    }
	    if (count($opts)) {
		$opts[0] = preg_replace('/^\s*#/','', $opts[0]);
		$comment = join(',',$opts);
	    }
	}
	$fname = "cc".++$num;
	$result[$name] = array(
	    'name'=>$name, 'label'=>$label, 'field'=>$fname,
	    'options'=>$options, 'type'=>$type, 'comment'=>$comment,
	    'attr'=>$attr);
    }
    return $result;
}

function assign_post_values(&$items) {
    global $myts;
    $errors = array();
    foreach ($items as $key=>$item) {
	if (empty($item['field'])) continue;
	$name = $item['field'];
	$type = $item['type'];
	$lab = $item['label'];
	$attr = &$item['attr'];
	$check = !empty($attr['check'])?$attr['check']:"";
	$val = '';
	if (isset($_POST[$name])) {
	    $val = $_POST[$name];
	    if (is_array($val)) {
		foreach ($val as $n=>$v) {
		    $val[$n] = $myts->stripSlashesGPC($v);
		}
	    } else {
		$val = $myts->stripSlashesGPC($val);
	    }
	}
	switch ($check) {
	case '':
	    break;
	case 'require':
	    if ($val==='') $errors[] = $lab.": "._MD_REQUIRE_ERR;
	    break;
	case 'num':
	case 'numeric':
	    if (!preg_match('/^[-+]?\d+$/', $val)) $errors[] = $lab.": "._MD_NUMITEM_ERR;
	    break;
	case 'mail':
	    if (!checkEmail($val)) $errors[] = $lab.": "._MD_ADDRESS_ERR;
	    break;
	default:
	    if (!preg_match('/^'.$check.'$/', $val)) $errors[] = $lab.": "._MD_REGEXP_ERR;
	    break;
	}
	switch ($type) {
	case 'checkbox':
	    if (empty($val)) $val = array();
	    $idx = array_search(LABEL_ETC, $val);	 // etc
	    if (is_int($idx)) {
		$val[$idx] = strip_tags($item['options'][LABEL_ETC])." ".$myts->stripSlashesGPC($_POST[$name."_etc"]);
	    }
	    break;
	case 'radio':
	    if ($val == LABEL_ETC) {			// etc
		$val = strip_tags($item['options'][LABEL_ETC])." ".$myts->stripSlashesGPC($_POST[$name."_etc"]);
	    }
	    break;
	case 'hidden':
	case 'const':
	    $val = join(',', $item['options']);
	    break;
	case 'file':
	    $val = '';		// filename
	    $upfile = isset($_FILES[$name])?$_FILES[$name]:array('name'=>'');
	    if (isset($_POST[$name."_prev"])) {
		$val = $myts->stripSlashesGPC($_POST[$name."_prev"]);
		if (!empty($upfile['name'])) {
		    unlink(XOOPS_UPLOAD_PATH.cc_attach_path(0, $val));
		    $val = '';
		}
	    }
	    if (empty($val)) {
		$val = $upfile['name'];
		if ($val) move_attach_file($upfile['tmp_name'], $val);
		elseif (isset($_POST[$name])) {	// confirm
		    $val = $myts->stripSlashesGPC($_POST[$name]);
		}
	    }
	    break;
	case 'mail':
	    $name .= '_conf';
	    if (!checkEmail($val)) {
		$errors[] = $lab.": "._MD_ADDRESS_ERR;
	    }
	    if (isset($_POST[$name])) {
		if ($val != $myts->stripSlashesGPC($_POST[$name])) {
		    $errors[] = sprintf(_MD_CONF_LABEL, $lab).": "._MD_CONFIRM_ERR;
		}
	    }
	    break;
	}
	$items[$key]['value'] = $val;
    }
    return $errors;
}

function assign_form_widgets(&$items, $conf=false) {
    $mconf = !$conf;
    $updates = array();
    foreach ($items as $item) {
	if (empty($item['field'])) { // comment only
	    $updates[] = $item;
	    continue;
	}
	if ($item['type']=='hidden' && !$conf) continue;
	$val =& $item['value'];
	$fname =& $item['field'];
	if ($conf) {
	    if (is_array($val)) {
		$input = htmlspecialchars(join(', ', $val), ENT_QUOTES);
		$fmt = "<input type='hidden' name='{$fname}[]' value='%s' />";
		foreach ($val as $v) {
		    $v = htmlspecialchars($v, ENT_QUOTES);
		    $input .= sprintf($fmt, $v);
		}
	    } else {
		$v = htmlspecialchars($val, ENT_QUOTES);
		if ($item['type']=='hidden') $input = $v;
		else $input = "$v<input type='hidden' name='$fname' value='$v' />";
	    }
	} else {
	    $input = cc_make_widget($item);
	    if ($mconf && isset($item['type']) && $item['type']=='mail' &&
		isset($item['attr']['check'])&& $item['attr']['check']=='require') {
		$cfname = $fname.'_conf';
		$citem = array(
		    'name'=>sprintf(_MD_CONF_LABEL, $item['name']),
		    'label'=>sprintf(_MD_CONF_LABEL, $item['label']),
		    'field'=>$cfname, 'type'=>$item['type'],
		    'comment'=>_MD_CONF_DESC, 'attr'=>$item['attr']);
		$item['input'] = $input;
		$updates[] = $item;
		$input = cc_make_widget($citem);
		$item = $citem;
		$mconf = false;
	    }
	}
	$item['input'] = $input;
	$updates[] = $item;
    }
    $items = $updates;
    return $updates;
}

function cc_make_widget($item) {
    global $myts, $xoopsUser, $defuser;
    if (empty($defuser)) {
	$defuser = array();
	$user = is_object($xoopsUser)?$xoopsUser:new XoopsUser;
	$keys = array_keys($user->getVars());
	if (is_object($xoopsUser)) {
	    foreach ($keys as $k) {
		$defuser['{X_'.strtoupper($k).'}'] = $xoopsUser->getVar($k, 'e');
	    }
	} else {
	    foreach ($keys as $k) {
		$defuser['{X_'.strtoupper($k).'}'] = '';
	    }
	}
    }
    $input = '';
    $fname = $item['field'];
    $names = "name='$fname' id='$fname'";
    $options =& $item['options'];
    $type =& $item['type'];
    $attr =& $item['attr'];
    $astr = '';
    if (isset($attr['prop'])) $astr .= ' '.$attr['prop'];
    $etcreg = empty($item['options'][LABEL_ETC])?'':'/^'.preg_quote(strip_tags($item['options'][LABEL_ETC]), '/').'\s+/';
    $etcval = '';
    switch($type) {
    case 'hidden':
    case 'const':
	$input=htmlspecialchars(join(',', $options), ENT_QUOTES);
	break;
    case 'select':
	$def = '';
	if (isset($_POST[$fname])) { // ovarride post value
	    $def = $myts->stripSlashesGPC($_POST[$fname]);
	}
	$input = "<select name='".htmlspecialchars($fname, ENT_QUOTES)."'$astr>\n";
	foreach ($options as $key=>$val) {
	    $lab = preg_replace('/\+$/', '', $key);
	    if (empty($def) && $lab != $key) {
		$def = $lab;
	    }
	    $ck = ($def == $lab)?" selected='selected'":"";
	    $lab = htmlspecialchars($lab, ENT_QUOTES);
	    $input .= "<option value='$lab'$ck />$val</option>\n";
	}
	$input .= "</select>\n";
	break;
    case 'radio':
	$def = '';
	$etclab = "{$fname}_etc";
	if (isset($_POST[$fname])) { // ovarride post value
	    $def = $myts->stripSlashesGPC($_POST[$fname]);
	    if ($etcreg && preg_match($etcreg, $def)) {
		$etcval = preg_replace($etcreg, '', $def);
		$def = LABEL_ETC;
	    }
	}
	if (isset($_POST[$etclab])) {
	    $etcval = $myts->stripSlashesGPC($_POST[$etclab]);
	}
	$input = "";
	$estr = get_attr_value($attr, 'size');
	$estr = empty($estr)?'':' size="'.$estr.'"';
	foreach ($options as $key=>$val) {
	    $lab = preg_replace('/\+$/', '', $key);
	    if (empty($def) && $lab != $key) {
		$def = $lab;
	    }
	    $ck = ($def === $lab)?" checked='checked'":"";
	    if ($lab == LABEL_ETC && $lab!=strip_tags($val)) {
		$val .= " <input name='$etclab' value='$etcval' onChange='checkedEtcText(\"$fname\")'$estr />";
		$ck .= " id='{$fname}_eck'";
	    }
	    $input .= "<span class='ccradio'><input type='radio' name='$fname' value='$lab'$ck /> $val</span> ";
	}
	break;
    case 'checkbox':
	$etclab = "{$fname}_etc";
	$def = ($_SERVER['REQUEST_METHOD']=='POST')?array():null;
	if (isset($_POST[$etclab])) {
	    $etcval = $myts->stripSlashesGPC($_POST[$etclab]);
	}
	if (isset($_POST[$fname])) { // ovarride post value
	    foreach ($_POST[$fname] as $v) {
		$v = $myts->stripSlashesGPC($v);
		if ($etcreg && preg_match($etcreg, $v)) {
		    $etcval = preg_replace($etcreg, '', $v);
		    $v = LABEL_ETC;
		}
		$def[] = $v;
	    }
	}
	$input = "";
	$estr = get_attr_value($attr, 'size');
	$estr = empty($estr)?'':' size="'.$estr.'"';
	foreach ($options as $key=>$val) {
	    $lab = preg_replace('/\+$/', '', $key);
	    if ($def==null) {
		$ck = ($key!=$lab)?" checked='checked'":"";
	    } else {
		$ck = in_array($lab, $def)?" checked='checked'":"";
	    }
	    if ($lab == LABEL_ETC && $lab!=strip_tags($val)) {
		$val .= " <input name='$etclab' value='$etcval' onChange='checkedEtcText(\"$fname\")'$estr />";
		$ck .= " id='{$fname}_eck'";
	    }
	    $input .= "<span class='cccheckbox'><input type='checkbox' name='".$fname."[]' value='$lab'$ck$astr /> $val</span> ";
	}
	break;
    case 'textarea':
    default:
	$val = is_array($options)?join(',', $options):$options;
	if (isset($_POST[$fname])) { // ovarride post value
	    $val = $myts->stripSlashesGPC($_POST[$fname]);
	} else {
	    global $xoopsUser;
	    if ($type=='mail' && empty($val)) {
		$orig = preg_replace('/_conf$/', '', $fname);
		if (isset($_POST[$orig])) {
		    $val = $myts->stripSlashesGPC($_POST[$orig]);
		} elseif ($type=='mail') {
		    $val = "{X_EMAIL}";
		}
	    }
	}
	$val = htmlspecialchars(str_replace(array_keys($defuser), $defuser, $val), ENT_QUOTES);
	if ($type == 'textarea') {
	    $estr = get_attr_value($attr, 'rows');
	    if (!empty($estr)) $astr .= ' rows="'.$estr.'"';
	    $estr = get_attr_value($attr, 'cols');
	    if (!empty($estr)) $astr .= ' cols="'.$estr.'"';
	    $input = "<textarea $names $astr>$val</textarea>";
	} else {
	    $input = "";
	    if ($type=='file') {
		if ($val) $input .= "$val<input type='hidden' name='{$fname}_prev' value='$val' /><br />";
	    } else $type = 'text';
	    $estr = get_attr_value($attr, 'size');
	    if (!empty($estr)) $astr .= ' size="'.$estr.'"';
	    $estr = get_attr_value($attr, 'maxlength');
	    if (!empty($estr)) $astr .= ' maxlength="'.$estr.'"';
	    $input .= "<input type='$type' $names value='$val'$astr />";
	    if ($type=='file') {
	    }
	}
	break;
    }
    return $input;
}

if (!function_exists("unserialize_vars")) {
    // expand: label=value[,\n](label=value...) 
    function unserialize_vars($text,$rev=false) {
	$array = array();
	foreach (preg_split('/(,|\r?\n)/',$text) as $ln) {
	    if (preg_match('/^\s*([^=]+)\s*=\s*(.+)$/', $ln, $d)) {
		if ($rev) {
		    $array[$d[2]] = $d[1];
		} else {
		    $array[$d[1]] = $d[2];
		}
	    }
	}
	return $array;
    }
}
if (!function_exists("serialize_text")) {
    function serialize_text($array) {
	$text = '';
	foreach ($array as $name => $val) {
	    if (is_array($val)) $val = join(', ', $val);
	    if (preg_match('/\n/', $val)) {
		$val = preg_replace('/\n\r?/', "\n\t", $val);
	    }
	    $text .= "$name: $val\n";
	}
	return $text;
    }

    function unserialize_text($text) {
	$array = array();
	foreach (preg_split("/\r?\n/", $text) as $ln) {
	    if (preg_match('/^\s/', $ln)) {
		$val .= "\n".substr($ln, 1);
	    } elseif (preg_match('/^([^:]*):\s?(.*)$/', $ln, $d)) {
		$name = $d[1];
		$array[$name] = $d[2];
		$val =& $array[$name];
	    }
	}
	return $array;
    }
}

function move_attach_file($tmp, $file, $id=0) {
    global $xoopsConfig;

    $path = XOOPS_UPLOAD_PATH.cc_attach_path($id, $file);
    $dir = dirname($path);
    $base = dirname($dir);
    if (!is_dir($base)) {
	if (!mkdir($base)) die("UPLOADS permittion error");
	$fp = fopen("$base/.htaccess", "w");
	fwrite($fp, "deny from all\n");	// not access direct
	fclose($fp);
    }
    if (!is_dir($dir) && !mkdir($dir)) die("UPLOADS permittion error");
    if (empty($tmp)) $tmp = XOOPS_UPLOAD_PATH.cc_attach_path(0, $file);
    if (@rename($tmp, $path) || move_uploaded_file($tmp, $path)) return true;
    return false;
}

if (!function_exists("template_dir")) {
    function template_dir($file='') {
	global $xoopsConfig;
	$lang = $xoopsConfig['language'];
	$dir = dirname(__FILE__).'/language/%s/mail_template/%s';
	$path = sprintf($dir,$lang, $file);
	if (file_exists($path)) {
	    $path = sprintf($dir,$lang, '');
	} else {
	    $path = sprintf($dir,'english', '');
	}
	return $path;
    }
}

function cc_attach_path($id, $file) {
    $dirname = basename(dirname(__FILE__));
    $dir = $id?sprintf("%05d", $id):"work".substr(session_id(), 0, 8);
    return "/$dirname/$dir".($file?"/$file":"");
}

function cc_attach_image($id, $file, $urlonly=false, $add='') {
    if (empty($file)) return "";
    $rurl = "file.php?".($id?"id=$id&":"")."file=".urlencode($file).($add?"&$add":"");
    if ($urlonly) return XOOPS_URL."/modules/".basename(dirname(__FILE__))."/$rurl";
    $path = XOOPS_UPLOAD_PATH.cc_attach_path($id, $file);
    $xy = getimagesize($path);
    if ($xy) {
	if ($xy[0]>$xy[1] && $xy[0]>300) $extra = " width='300'";
	elseif ($xy[1]>300) $extra = " height='300'";
	else $extra = "";
	$extra .= " alt='".htmlspecialchars($file, ENT_QUOTES)."'";
	return "<img src='$rurl' class='myphoto' $extra />";
    } else {
	$size = return_unit_bytes(filesize($path));
	return "<a href='$rurl' class='myattach'>$file ($size)</a>";
    }
}

function return_unit_bytes($size) {
    $unit = defined('_MD_BYTE_UNIT')?_MD_BYTE_UNIT:"bytes";
    if ($size<10*1024) return number_format($size);
    $size /= 1024;
    if ($size<10*1024) return round($size, 1).'K'.$unit;
    $size /= 1024;
    if ($size<10*1024) return round($size, 1).'M'.$unit;
    $size /= 1024;
    return round($size, 1).'G'.$unit;
}

// Access allow:
//   1. onetime password matched
//   2. administrator
//   3. order from/to users
function cc_check_perm($data) {
    global $xoopsUser, $xoopsModule;
    $uid = is_object($xoopsUser)?$xoopsUser->getVar('uid'):0;

    $pass = isset($_GET['p'])?$_GET['p']:(empty($_SESSION['onepass'])?"":$_SESSION['onepass']);
    if (!empty($data['onepass']) && $data['onepass']==$pass) return true;

    $mid = is_object($xoopsModule)?$xoopsModule->getVar('mid'):0;
    if ($uid && $xoopsUser->isAdmin($mid)) return true;
    $cgrp = $data['cgroup'];
    if ($cgrp && $uid && in_array($cgrp, $xoopsUser->getGroups())) return true;
    if ($uid && ($data['uid']==$uid || $data['touid'] == $uid)) return true;
    return false;
}

function cc_onetime_ticket($genseed="mypasswdbasestring") {
    return substr(base64_encode(pack("H*",md5($genseed.time()))), 0, 8);
}

function cc_delete_message($msgid) {
    global $xoopsDB;
    //$res = $xoopsDB->query("DELETE FROM ".CCMES." WHERE msgid=".$msgid);
    $dir = XOOPS_UPLOAD_PATH.cc_attach_path(0,'');
    $dh = opendir($dir);
    while ($file = readdir($dh)) {
	if ($file==".." || $file==".") continue;
	$path = "$dir/$file";
	unlink($path);
    }
}

function cc_message_entry($data, $link="message.php") {
    global $msg_status;
    $id = $data['msgid'];
    return  array(
	'msgid'=>$id,
	'mdate'=>myTimestamp($data['mtime'], 'm', _MD_TIME_UNIT),
	'title'=>"<a href='message.php?id=$id'>".$data['title']."</a>", 
	'uname'=> xoops_getLinkedUnameFromId($data['uid']),
	'status'=>$msg_status[$data['status']],
	'raw'=>$data);
}

function is_cc_evaluate($id, $uid, $pass) {
    global $xoopsDB;
    $cond = $pass?'onepass='.$xoopsDB->quoteString($pass):"uid=$uid";
    $res = $xoopsDB->query("SELECT count(uid) FROM ".CCMES." WHERE msgid=$id AND $cond AND status=".$xoopsDB->quoteString(_STATUS_REPLY));
    list($ret) = $xoopsDB->fetchRow($res);
    return $ret;
}

function cc_notify_mail($tpl, $tags, $users, $from="") { // return: error count
    global $xoopsConfig, $xoopsModuleConfig, $xoopsUser, $xoopsModule;
    $xoopsMailer =& getMailer();
    if (is_array($users)) {
	$err = 0;
	foreach ($users as $u) {
	    $err += cc_notify_mail($tpl, $tags, $u, $from);
	}
	return $err;
    }
    if (is_object($users)) {
	switch ($users->getVar('notify_method')) {
        case XOOPS_NOTIFICATION_METHOD_PM:
            $xoopsMailer->usePM();
	    $sender = is_object($xoopsUser)?$xoopsUser:new XoopsUser;
	    $xoopsMailer->setFromUser($sender);
	    break;
        case XOOPS_NOTIFICATION_METHOD_EMAIL:
            $xoopsMailer->useMail();
	    break;
	case XOOPS_NOTIFICATION_METHOD_DISABLE:
	    return 0;
        default:
            return 1;
        }
	$xoopsMailer->setToUsers($users);
    } else {
	if (empty($users)) return 0;
	$xoopsMailer->useMail();
	$xoopsMailer->setToEmails($users);
    }

    $xoopsMailer->setFromEmail($from?$from:$xoopsConfig['adminmail']);
    $xoopsMailer->setFromName($xoopsModule->getVar('name'));
    $xoopsMailer->setSubject(_CC_NOTIFY_SUBJ);
    $xoopsMailer->assign($tags);
    $xoopsMailer->setTemplateDir(template_dir($tpl));
    $xoopsMailer->setTemplate($tpl);
    return $xoopsMailer->send()?0:1;
}

function check_form_tags($defs, $desc) {
    global $xoopsConfig;
    $base = dirname(__FILE__).'/language/';
    $path = $base.$xoopsConfig['language'].'/main.php';
    if (file_exists($path)) include_once($path);
    else include_once("$base/english/main.php");
    $items = get_form_attribute($defs);
    assign_form_widgets($items);
    $checks = array('{FORM_ATTR}', '{SUBMIT}', '{BACK}', '{CHECK_SCRIPT}');
    foreach ($items as $item) {
	if (empty($item['type'])) continue;
	$checks[] = '{'.$item['name'].'}';
    }
    $error = "";
    foreach ($checks as $check) {
	$n = substr_count($desc, $check);
	if ($n!=1) {
	    $error .= $check.": ".($n?_AM_CHECK_DUPLICATE:_AM_CHECK_NOEXIST)."<br />\n";
	}
    }
    return $error;
}

function custom_template($form, $items, $conf=false) {
    global $xoopsConfig;
    $str = $rep = array();
    $hasfile = "";
    $id = $form['formid'];
    foreach ($items as $item) {
	$str[] = '{'.$item['name'].'}';
	$rep[] = empty($item['input'])?"":$item['input'];
	$fname = $item['field'];
	if ($item['type']=='file') {
	    $hasfile = ' enctype="multipart/form-data"';
	}
    }
    $action = $form['action'];
    if (!empty($form['priuser'])) {
	$priuser =& $form['priuser'];
	$action .= '&amp;'.$priuser['uid'];
	$str[] = "{TO_UNAME}";
	$rep[] = $priuser['uname'];
	$str[] = "{TO_NAME}";
	$rep[] = $priuser['name'];
    }
    $str[] = "{SUBMIT}";
    $str[] = "{BACK}";
    $str[] = "{FORM_ATTR}";
    if ($conf) {
	$out = preg_replace('/\\[desc\\](.*)\\[\\/desc\\]/sU', '', $form['description']);
	$rep[] = "<input type='hidden' name='op' value='store' />".
	    "<input type='submit' value='"._MD_SUBMIT_SEND."' />";
	$rep[] = "<input type='submit' name='edit' value='"._MD_SUBMIT_EDIT."' />";
	$rep[] = " action='$action' method='post' name='ccenter'";
	$checkscript = "";
    } else {
	$out = preg_replace('/\\[desc\\](.*)\\[\\/desc\]/sU', '\\1', $form['description']);
	$rep[] = "<input type='hidden' name='op' value='confirm' />".
	    "<input type='submit' value='"._MD_SUBMIT_CONF."' />";
	$rep[] = "";		// back
	$rep[] = " action='$action' method='post' name='ccenter' onsubmit='return xoopsFormValidate_ccenter();'".$hasfile;
	$checkscript = empty($form['check_script'])?"":$form['check_script'];
    }
    $str[] = "{CHECK_SCRIPT}";
    $rep[] = $checkscript;
    $str[] = "{XOOPS_URL}";
    $rep[] = XOOPS_URL;
    $str[] = "{XOOPS_SITENAME}";
    $rep[] = $xoopsConfig['sitename'];
    $str[] = "{TITLE}";
    $rep[] = $form['title'];
    return str_replace($str, $rep, $out);
}

function cc_log_message($formid, $comment, $msgid=0) {
    global $xoopsDB, $xoopsUser;
    $uid = is_object($xoopsUser)?$xoopsUser->getVar('uid'):0;
    $now = time();
    $xoopsDB->queryF("INSERT INTO ".CCLOG."(ltime, fidref, midref, euid, comment)VALUES($now, $formid, $msgid, $uid, ".$xoopsDB->quoteString(preg_replace('/\n/', ", ", $comment)).")");
    if ($msgid) {
	$msgurl = XOOPS_URL."/modules/".basename(dirname(__FILE__))."/message.php?id=$msgid";
	$res = $xoopsDB->query("SELECT title FROM ".FORMS." WHERE formid=".$formid);
	list($title) = $xoopsDB->fetchRow($res);
	$tags = array('LOG_STATUS'=>$comment,
		      'FORM_NAME'=>$title,
		      'CHANGE_BY'=>$xoopsUser?$xoopsUser->getVar('uname'):"",
		      'MSG_ID'=>$msgid,
		      'MSG_URL'=>$msgurl);
	$notification_handler =& xoops_gethandler('notification');
	$notification_handler->triggerEvent('message', $msgid, 'status', $tags);
    }
    return $comment;
}

function cc_log_status($data, $nstat) {
    global $msg_status;
    $fid = empty($data['fidref'])?$data['formid']:$data['fidref'];
    $log = sprintf(_CC_LOG_STATUS, $msg_status[$data['status']], $msg_status[$nstat]);
    return cc_log_message($fid, $log, $data['msgid']);
}

define('PAST_TIME_MIN', 3600);	     // 1hour
define('PAST_TIME_HOUR', 24*3600);   // 1day
define('PAST_TIME_DAY', 14*24*3600); // 2week

function myTimestamp($t, $fmt="l", $unit="%dmin,%dhour,%dday,past %s") {
    $past = time()-$t;
    if ($past > PAST_TIME_DAY) {
	return formatTimestamp($t, $fmt);
    }
    $units = split(',', $unit);
    if ($past < PAST_TIME_MIN) {
	$ret = sprintf($units[0], intval($past/60));
    } elseif ($past < PAST_TIME_HOUR) {
	$ret = sprintf($units[1], intval($past/3600)); // hours
	$v = intval(($past % 3600)/60);	     // min
	if ($v) $ret .= sprintf($units[0], $v);
    } else {
	$ret = sprintf($units[2], intval($past/86400)); // days
	$v = intval(($past % 86400)/3600);    // hours
	if ($v) $ret .= sprintf($units[1], $v);
    }
    return sprintf($units[3], $ret);
}

// adhoc class - not for reuse
class ListCtrl {
    var $name;
    var $vars;
    var $combo;

    function ListCtrl($name, $init=array(), $combo='') {
	if (empty($combo)) {
	    global $xoopsModuleConfig;
	    $combo = $xoopsModuleConfig['status_combo'];
	}
	$this->name = $name;
	$this->combo = unserialize_text($combo);
	if (!isset($_SESSION['listctrl'])) $_SESSION['listctrl'] = array();
	if (!isset($_SESSION['listctrl'][$name]) ||
	    (isset($_GET['reset'])&&$_GET['reset']=='yes')) {
	    if (!isset($init['stat'])) {
		list($init['stat']) = array_values($this->combo);
	    }
	    $_SESSION['listctrl'][$name] = $init;
	}
	$this->vars =& $_SESSION['listctrl'][$name];
	$this->updateVars($_REQUEST);
    }

    function getVar($name) {
	return isset($this->vars[$name])?$this->vars[$name]:"";
    }

    function setVar($name, $val) { $this->vars[$name]=$val; }

    function getLabels($labels) {
	$result = array();
	$orders = $this->getVar('orders');
	foreach ($labels as $k => $v) {
	    $lab = array('text'=>$v, 'name'=>$k);
	    if (isset($this->vars[$k])) { // with ctrl
		$n = array_search($k, $orders);
		if (is_int($n)) {
		    $val = strtolower($this->getVar($k));
		    $lab['value'] = $val;
		    $lab['next'] = $val=='desc'?'asc':'desc';
		    $lab['extra'] = " class='ccord$n'";
		} else {
		    $lab['value'] = 'none';
		    $lab['next'] = 'asc';
		}
	    }
	    $result[] = $lab;
	}
	return $result;
    }

    function updateVars($args) {
	$myts =& MyTextSanitizer::getInstance();
	$changes = array();
	foreach (array_keys($this->vars) as $k) {
	    if (isset($args[$k])) {
		$val = trim($args[$k]);
		if (empty($val)) continue;
		switch ($k) {
		case 'stat':
		    $val = preg_replace('/[^a-dx\- ]/', '', trim($val));
		    break;
		default:
		    $val = strtolower($val)=='asc'?'ASC':'DESC';
		    $orders = $this->getVar('orders');
		    if ($k != $orders[0]) {
			$this->setVar('orders', array($k, $orders[0]));
		    }
		}
		$this->setVar($k, $val);
		$changes[$k] = $val;
	    }
	}
	return $changes;
    }

    function sqlcondition($fname='status') {
	global $xoopsDB;
	$stat = $this->getVar('stat');
	if (preg_match('/\s+/', $stat)) {
	    return "$fname IN ('".join("','", preg_split('/\s+/',$stat))."')";
	}
	return "$fname=".$xoopsDB->quoteString($stat);
    }

    function sqlorder() {
	$order = array();
	foreach ($this->getVar('orders') as $name) {
	    $order[] = $name." ".$this->getVar($name);
	}
	if ($order) return " ORDER BY ".join(',', $order);
	return "";
    }

    function renderStat() {
	$ctrl = "<select name='stat' onChange='submit();'>\n";
	$stat = $this->getVar('stat');
	foreach ($this->combo as $k => $v) {
	    $ck = $v == $stat?" selected='selected'":"";
	    $ctrl .= "<option value='$v'$ck>$k</option>\n";
	}
	$ctrl .= "</select>";
	return $ctrl;
    }
}

function change_message_status($msgid, $touid, $stat) {
    global $xoopsDB, $msg_status, $xoopsUser, $xoopsModule;

    $isadmin = is_object($xoopsUser) && $xoopsUser->isAdmin($xoopsModule->getVar('mid'));
    $own_status = array_slice($msg_status, $isadmin?0:1, $isadmin?5:3);
    if (empty($own_status[$stat])) return false; // Invalid status
    $s = $xoopsDB->quoteString($stat);
    $cond = "msgid=".$msgid;
    if ($touid) $cond .= " AND touid=".$touid;
    $res = $xoopsDB->query("SELECT msgid,fidref,status FROM ".CCMES." WHERE $cond AND status<>$s");
    if (!$res || $xoopsDB->getRowsNum($res)==0) return false;
    $data = $xoopsDB->fetchArray($res);
    $now = time();
    $res = $xoopsDB->queryF("UPDATE ".CCMES." SET status=$s,mtime=$now WHERE msgid=$msgid");
    if (!$res) die('DATABASE error');	// unknown error?
    cc_log_status($data, $stat);
    return true;
}

function checkScript($checks, $confirm) {
    $script = "<script type=\"text/javascript\">
<!--//
function checkItem(obj, lab) {
  msg = lab+\": "._MD_REQUIRE_ERR."\\n\";
  if (typeof(obj.selectedIndex)==\"number\" && obj.value != \"\") return \"\";
  if (obj.value == \"\") return msg;
  if (typeof(obj.length)==\"number\") {
     for (i=0; i<obj.length; i++) {
        if (obj[i].checked) return \"\";
     }
     return msg;
  }
  return \"\";
}
function xoopsFormValidate_ccenter() {
    myform = window.document.ccenter;
    msg = \"\";
    obj = null;
";
    foreach ($checks as $name => $msg) {
	$script .= "
    msg = msg+checkItem(myform['$name'], \"$msg\");
    if(msg && obj==null)obj=myform['$name'];\n";
    }
    if (count($confirm)) {
	foreach ($confirm as $name => $msg) {
	    $script .= "
    if ( myform.$name.value != myform.{$name}_conf.value ) {
        msg = msg+\"$msg: "._MD_CONFIRM_ERR."\\n\";
        if(obj==null)obj=myform.{$name}_conf;
}\n";
	}
    }
    $script .= "
    if (msg == \"\") return true;
    window.alert(msg);
    if (typeof(obj.length)!=\"number\") obj.focus();
    return false;
}
function checkedEtcText(lab) {
   obj = xoopsGetElementById(lab+\"_eck\");
   if (obj) obj.checked=true;
}
//--></script>";
    return $script;
}

function set_checkvalue(&$form) {
    $hasfile = false;
    $require = array();
    $confirm = array();
    foreach ($form['items'] as $item) {
	if (empty($item['field'])) continue;
	$fname = $item['field'];
	$type = $item['type'];
	$lab = $item['label'];
	$check = isset($item['attr']['check'])?$item['attr']['check']:'';
	if ($type == 'file') {
	    $hasfile=true;
	} elseif (preg_match('/_conf$/', $fname)) {
	    $confirm[preg_replace('/_conf$/', '', $fname)] = $lab;
	} elseif ($check=='require') {
	    if ($type == 'checkbox') $fname .= '[]';
	    $require[$fname] = htmlspecialchars(strip_tags($lab));
	}
    }

    $form['check_script'] = checkScript($require, $confirm);
    $form['confirm'] = $confirm;
    $form['hasfile'] = $hasfile;
}

class XoopsBreadcrumbs {
    var $moddir;
    var $pairs;

    function XoopsBreadcrumbs() {
	global $xoopsTpl, $xoopsModule;
	$this->moddir = XOOPS_URL."/modules/".$xoopsModule->getVar('dirname').'/';
	$this->pairs = array(array('name'=>$xoopsModule->getVar('name'), 'url'=>$this->moddir));
    }

    function set($name, $url) {
	if (preg_match('/^\w+:\/\//', $url)) $url = $this->moddir.$url;
	$this->pairs[] = array('name'=>htmlspecialchars(strip_tags($name), ENT_QUOTES), 'url'=>$url);
    }

    function get() {
	return $this->pairs;
    }

    function assign() {
	global $xoopsTpl;
	return $xoopsTpl->assign('xoops_breadcrumbs', $this->get());
    }

}

?>