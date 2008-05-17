<?php
// contact to member
// $Id: index.php,v 1.17 2008/05/17 05:55:47 nobu Exp $

include "../../mainfile.php";
include "functions.php";

$errors = array();
$op = "form";
$myts =& MyTextSanitizer::getInstance();

$id= isset($_GET['form'])?intval($_GET['form']):0;

$cond = "active";
if (is_object($xoopsUser)) {
    $conds = array();
    foreach ($xoopsUser->getGroups() as $gid) {
	$conds[] = "grpperm LIKE '%|$gid|%'";
    }
    if ($conds) $cond .= " AND (".join(' OR ', $conds).")";
} else {
    $cond .= " AND grpperm LIKE '%|".XOOPS_GROUP_ANONYMOUS."|%'";
}
if ($id) $cond .= " AND formid=$id";
$res = $xoopsDB->query("SELECT * FROM ".FORMS." WHERE $cond ORDER BY weight,formid");

if (!$res) {
    redirect_header('index.php', 3, _NOPERM);
    exit;
}

$breadcrumbs = new XoopsBreadcrumbs();

if ($xoopsDB->getRowsNum($res)!=1) {
    include XOOPS_ROOT_PATH."/header.php";
    $xoopsOption['template_main'] = "ccenter_index.html";
    $forms = array();
    while ($form=$xoopsDB->fetchArray($res)) {
	if ($form['priuid']<0) continue; // need uid setting
	$forms[] = $form;
    }
    $xoopsTpl->assign('forms', $forms);
    include XOOPS_ROOT_PATH."/footer.php";
    exit;
}

if (isset($_POST['op']) && !isset($_POST['edit'])) $op = $_POST['op'];
$form = $xoopsDB->fetchArray($res);
$items = get_form_attribute($form['defs']);
if ($form['priuid']< 0) {	// assign group member
    $priuid = isset($_GET['uid'])?intval($_GET['uid']):0;
    if ($priuid) {
	$member_handler =& xoops_gethandler('member');
	$priuser = $member_handler->getUser($priuid);
	if (!is_object($priuser) || !in_array(-$form['priuid'], $priuser->groups())) $priuid=0;
    }
    if (empty($priuid)) {
	$back = isset($_SERVER['HTTP_REFERER'])?$_SERVER['HTTP_REFERER']:XOOPS_URL;
	redirect_header($back, 3, _NOPERM);
	exit;
    } else {
	$form['priuser'] = array('uid'=>$priuser->getVar('uid'),
				 'uname'=>$priuser->getVar('uname'),
				 'name'=>$priuser->getVar('name'));
    }
}

$errors = array();
if ($op!="form") {
    $errors = assign_post_values($items);
    if (count($errors)) {
	$op = 'form';
	assign_form_widgets($items);
    } elseif ($op == 'store') {
	$errors = store_message($items, $form);
    } elseif ($op == 'confirm') {
	assign_form_widgets($items, true);
    }
} else {
    assign_form_widgets($items);
}

$cust = $form['custom'];
$form['items'] =& $items;
$dirname = basename(dirname(__FILE__));
$action = XOOPS_URL."/modules/$dirname/index.php?form=".$form['formid'];
if (!empty($form['priuser'])) $action .= '&amp;uid='.$form['priuser']['uid'];
$form['action'] = $action;

set_checkvalue($form);

$title = htmlspecialchars($form['title']);
$breadcrumbs->set($title, "index.php?form=$id");

if ($cust) {
    $out = custom_template($form, $items, $op == 'confirm');
    switch ($cust) {
    case _CC_TPL_FRAME:
    case _CC_TPL_BLOCK:
	include XOOPS_ROOT_PATH."/header.php";
	if ($cust == _CC_TPL_FRAME) {
	    $xoopsTpl->assign(array('xoops_showcblock'=>0,'xoops_showlblock'=>0,'xoops_showrblock'=>0));
	}
	$breadcrumbs->assign();
	$xoopsTpl->assign('xoops_pagetitle', $title);
	if ($errors) xoops_error($errors);
	echo $out;
	include XOOPS_ROOT_PATH."/footer.php";
	break;
	
    default:
	echo $out;
	break;
    }
} else {
    $str = $rep = array();
    if (!empty($form['priuser'])) {
	$priuser =& $form['priuser'];
	$str[] = "{TO_UNAME}";
	$rep[] = $priuser['uname'];
	$str[] = "{TO_NAME}";
	$rep[] = $priuser['name'];
    }
    $form['description'] = $myts->displayTarea(str_replace($str, $rep, $form['description']));
    include XOOPS_ROOT_PATH."/header.php";
    $breadcrumbs->assign();
    $xoopsTpl->assign('xoops_pagetitle', $title);
    $xoopsOption['template_main'] = ($op=='confirm')?"ccenter_confirm.html":"ccenter_form.html";

    $xoopsTpl->assign('errors', $errors);
    $xoopsTpl->assign('form', $form);
    $xoopsTpl->assign('op', 'confirm');

    include XOOPS_ROOT_PATH."/footer.php";
}

function store_message($items, $form) {
    global $xoopsUser, $xoopsDB, $xoopsModuleConfig;

    $uid = is_object($xoopsUser)?$xoopsUser->getVar('uid'):0;
    $store = $form['store'];
    if ($store==_DB_STORE_NONE) {
	$showaddr = true;	// no store to need show address
    } else {
	$showaddr = get_attr_value(array(), 'notify_with_email');
    }
    $from = $email = "";
    $attach = array();
    $vals = array();
    foreach ($items as $item) {
	if (empty($item['name'])) continue;
	$name = $item['name'];
	$vals[$name] = $item['value'];
	switch ($item['type']) {
	case 'mail':
	    if (empty($email)) { // save first email for contact
		$email = $vals[$name];
		if (empty($showaddr)) {
		    unset($vals[$name]);
		} else {
		    $from = $email;
		}
	    }
	    break;
	case 'file':
	    $val = $vals[$name];
	    if ($val) {
		$vals[$name] = "file=".$val;
		$attach[] = $val;
	    }
	    break;
	}
    }
    $text = serialize_text($vals);
    $onepass = ($uid==0)?cc_onetime_ticket($email):"";
    if ($form['priuid'] < 0) {
	$touid = empty($form['priuser'])?0:$form['priuser']['uid'];
    } else {
	$touid = $form['priuid'];
    }
    $now = time();
    $values = array(
	'uid'=>$uid, 'touid'=>$touid,
	'ctime'=>$now, 'mtime'=>$now,
	'fidref'=>$form['formid'],
	'email'=>$xoopsDB->quoteString($email),
	'onepass'=>$xoopsDB->quoteString($onepass));
    $parg = $onepass?"&p=".urlencode($onepass):"";
    if ($store==_DB_STORE_YES) {
	$values['body']=$xoopsDB->quoteString($text);
    }

    if ($store!=_DB_STORE_NONE) {
	$res = $xoopsDB->query("INSERT INTO ".CCMES. "(".join(',',array_keys($values)).") VALUES (".join(',', $values).")");
	if ($res===false) return array("Error in DATABASE insert");
	$id = $xoopsDB->getInsertID();
	if (empty($id)) return array("Internal Error in Store Message");
    } else {
	$id = 0;
    }
    $member_handler =& xoops_gethandler('member');
    if ($touid) {
	$toUser = $member_handler->getUser($touid);
	$toUname = $toUser->getVar('uname');
    } else {
	$toUser = false;
	$toUname = _MD_CONTACT_NOTYET;
    }
    $atext = "";		// reply sender
    $btext = "";		// to contact and monitors
    if (count($attach)) {
	$atext = $btext = "\n"._MD_ATTACHMENT."\n";
	foreach ($attach as $i=>$file) {
	    move_attach_file('', $file, $id);
	    $a = cc_attach_image($id, $file, true);
	    $atext .= "$a$parg\n";
	    $btext .= "$a\n";
	}
	rmdir(XOOPS_UPLOAD_PATH.cc_attach_path(0, ''));
    }
    $dirname = basename(dirname(__FILE__));
    $uname = $xoopsUser?$xoopsUser->getVar('uname'):$GLOBALS['xoopsConfig']['anonymous'];
    $tags = array('SUBJECT'=>$form['title'],
		  'TO_USER'=>$toUname,
		  'FROM_USER'=>$uname,
		  'FROM_EMAIL'=>$email,
		  'REMOTE_ADDR'=>$_SERVER["REMOTE_ADDR"],
		  'HTTP_USER_AGENT'=>$_SERVER["HTTP_USER_AGENT"]);
    $tpl = 'form_confirm.tpl';
    $msgurl = XOOPS_URL.($id?"/modules/$dirname/message.php?id=$id":'/');
    if ($email) {		// reply automaticaly
	$tags['VALUES'] = "$text$atext";
	$tags['MSG_URL'] = ($store==_DB_STORE_NONE)?'':"\n"._MD_NOTIFY_URL."\n$msgurl$parg";
	cc_notify_mail($tpl, $tags, $email, $toUser?$toUser->getVar('email'):'');
    }
    $tags['VALUES'] = "$text$btext";
    $tags['MSG_URL'] = ($store==_DB_STORE_NONE)?'':"\n"._MD_NOTIFY_URL."\n".$msgurl;

    $notification_handler =& xoops_gethandler('notification');
    $notification_handler->triggerEvent('global', $id, 'new', $tags);
    // force subscribe sender and recipient
    if ($id) $notification_handler->subscribe('message', $id, 'comment');
    if ($touid) {
	if ($id) $notification_handler->subscribe('message', $id, 'comment', null, null, $touid);
	cc_notify_mail($tpl, $tags, $toUser, $from);
    } elseif ($form['cgroup']) { // contact group notify
	$users = $member_handler->getUsersByGroup($form['cgroup'], true);
	cc_notify_mail($tpl, $tags, $users, $from);
    }

    $msgurl .= $parg;
    if (!empty($form['redirect'])) $msgurl = $form['redirect'];
    redirect_header($msgurl, 3, _MD_CONTACT_DONE);
    exit;
}
?>