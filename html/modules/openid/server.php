<?php
require '../../mainfile.php';
/*
if (!defined('XOOPS_TRUST_PATH')) {
    exit;
}
require_once XOOPS_TRUST_PATH . '/modules/openid/class/Openid.php';
*/
require_once XOOPS_ROOT_PATH  . '/modules/openid/class/Openid.php';
$openid = new Openid();
$openid->execute();

// if not need template view, script is already exited.
require XOOPS_ROOT_PATH . '/header.php';
$openid->view();
require XOOPS_ROOT_PATH . '/footer.php';
