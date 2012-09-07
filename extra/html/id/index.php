<?php
/**
 * Output Extra Identifier and XRDS
 * 
 */
define('_LEGACY_PREVENT_LOAD_CORE_', 1);
$xoopsOption = array('nocommon' => 1);
require '../mainfile.php';
require_once XOOPS_ROOT_PATH . '/modules/openid/include/formats.php';

$end_point = XOOPS_URL . '/modules/openid/server.php';
$base_url = XOOPS_URL . '/' . basename(dirname(__FILE__));

$request = strrchr($_SERVER['REQUEST_URI'], '/');
switch ($request) {
    case '/idp.xrds':
        header('Content-type: application/xrds+xml');
        echo sprintf(OPENID_IDP_XRDS, $end_point);
        break;
    case '/user.xrds':
        header('Content-type: application/xrds+xml');
        echo sprintf(OPENID_USER_XRDS, $end_point);
        break;
    case '/':
        $xrds_location = $base_url . '/idp.xrds';
        header('X-XRDS-Location: ' . $xrds_location);
        echo sprintf(OPENID_IDPAGE, $xrds_location, $end_point, XOOPS_URL);
        break;
    default:
        $xrds_location = $base_url . '/user.xrds';
        header('X-XRDS-Location: ' . $xrds_location);
        echo sprintf(OPENID_IDPAGE, $xrds_location, $end_point, XOOPS_URL);
}