<?php
if (!defined('XOOPS_ROOT_PATH')) exit;

/**
 * Build a extra URL 
 * 
 * @param string &$url
 * @param string $action
 * @param XoopsUser $user
 */
function openidBuildServerUrl(&$url, $action, XoopsUser $user=NULL)
{
    $dirname = basename(dirname(__FILE__));
    switch ($action) {
        case 'idpXrds':
            $url = "/$dirname/idp.xrds";
            break;
        case 'user':
            $url = sprintf('/%s/%s', $dirname, $user->getVar('uname'));
            break;
        default:
            // Nothing to do
    }
}

/**
 * Set user-info from extra identity-url to referenced param
 * 
 * @param array &$user_info reference
 * @param string $url
 */
function openidGetUserInfoFromIdentyUrl(array &$user_info, $url)
{
    $dirname = basename(dirname(__FILE__));
    $path = strstr($url, "/$dirname/");
    if ($path) {
        $path = strrchr($path, '/');
        if (strlen($path) > 1) {
            $user_info['uname'] = substr($path, 1);
        }
    }
}