<?php
/**
 * Static Class for handling URL
 *
 */
class Openid_Server_Url
{
    /**
     * Extract the current action from the request
     * @return string
     */
    static function extract()
    {
        $path_info = @$_SERVER['PATH_INFO'];
        $action = ($path_info) ? substr($path_info, 1) : '';
        return $action;
    }

    /**
     * Build a URL to a server action
     * 
     * @param string $action
     * @param XoopsUser $user
     * @param boolean $full
     * @return string $url
     */
    static function build($action=NULL, $user=NULL, $full=TRUE)
    {
        $url = '/modules/openid/server.php';
        if (!$action) {
            return XOOPS_URL . $url;
        }

        $url .= '/' . $action;
        if (file_exists(XOOPS_ROOT_PATH . '/id/index.php')) {
            switch ($action) {
                case 'idpXrds':
                    $url = '/id/idp.xrds';
                    break;
                case 'userXrds':
                    $url = '/id/user.xrds';
                    break;
                case 'user':
                    $url = sprintf('/id/%s', $user->getVar('uname'));
                    break;
            }
        } elseif ($user) {
            $url .= sprintf('?uid=%d', $user->getVar('uid'));
        }
        if ($full) {
            return XOOPS_URL . $url;
        } else {
            return $url;
        }
    }

    /**
     * 
     * @param string $url
     * @return array $ret
     */
    static function idFromURL($url)
    {
        $ret = array();
        if (file_exists(XOOPS_ROOT_PATH . '/id/index.php')) {
            $path = strrchr($_SERVER['REQUEST_URI'], '/');
            if (strlen($path) > 1) {
                $ret['uname'] = substr($path, 1);
            }
        } else {
	        $parsed = parse_url($url);
	        if ($parsed) {
	            $query = isset($parsed['query']) ? $parsed['query'] : '';
	            $parts = array();
	            parse_str($query, $parts);
	            if (isset($parts['uid'])) {
	                $ret['uid'] = intval($parts['uid']);
	            } elseif (isset($parts['uname'])) {
	                $ret['uname'] = trim($parts['uname']);
	            } else {
	                $path = isset($parsed['path']) ? $parsed['path'] : '';
	                if (strlen($path) > 1) {
	                    $ret['uname'] = substr($path, 1);
	                }
	            }
	        }
        }
        return $ret;
    }
}