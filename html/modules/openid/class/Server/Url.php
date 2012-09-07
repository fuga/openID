<?php
if (!defined('OPENID_EXTRA_URL_FUNC')) {
    define('OPENID_EXTRA_URL_FUNC', XOOPS_ROOT_PATH . '/id/functions.php');
}

/**
 * Static Class for handling URL
 *
 */
class Openid_Server_Url
{
    /**
     * Extract the current action from the request
     * 
     * @return string
     */
    public static function extract()
    {
        if (!empty($_SERVER['PATH_INFO'])) {
            $action = substr($_SERVER['PATH_INFO'], 1);
        } else {
            $action = '';
        }
        return $action;
    }

    /**
     * Build a URL to a server action
     * 
     * @param string $action
     * @param XoopsUser $user
     * @param boolean $local
     */
    public static function build($action=NULL, XoopsUser $user=NULL, $local=FALSE)
    {
        $url = '/modules/openid/server.php';
        if (!$action) {
            return XOOPS_URL . $url;
        }

        $url .= '/' . $action;
        if (file_exists(OPENID_EXTRA_URL_FUNC)) {
            include_once OPENID_EXTRA_URL_FUNC;
            openidBuildServerUrl($url, $action, $user);
        } elseif ($user) {
            $url .= sprintf('?uid=%d', $user->getVar('uid'));
        }
        if ($local) {
            $parsed = parse_url(XOOPS_URL);
            $root_path = isset($parsed['path']) ? $parsed['path'] : '';
            return $root_path . $url;
        } else {
            return XOOPS_URL . $url;
        }
    }

    /**
     * Get user-info from identity url
     * 
     * @param string $url
     * @return array $user_info
     */
    static function idFromURL($url)
    {
        $user_info = array();
        if (file_exists(OPENID_EXTRA_URL_FUNC)) {
            include_once OPENID_EXTRA_URL_FUNC;
            openidGetUserInfoFromIdentyUrl($user_info, $url);
        } else {
            $parsed = parse_url($url);
            $query = isset($parsed['query']) ? $parsed['query'] : '';
            parse_str($query, $user_info);
        }
        return $user_info;
    }
}