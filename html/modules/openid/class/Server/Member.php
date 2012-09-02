<?php
class Openid_Server_Member
{
    /**
     * Get the logged in xoops user
     *
     * @return mixed $xoopsUser or FALSE 
     */
    static function getLoggedInUser()
    {
        global $xoopsUser;
        if (is_object(@$xoopsUser)) {
            return Openid_Server_Url::build('user', $xoopsUser);
        }
        return FALSE;
    }

    /**
     * 
     * @param array $allowed
     * @return array $user_data
     */
    static function getUserData(array $allowed)
    {
        global $xoopsUser;
        $user_data = array(
            'nickname' => $xoopsUser->getVar('uname'),
            'language' => _LANGCODE
        );
        if ($tz = self::ofsetToTimeZone($xoopsUser->getVar('timezone_offset'))) {
            $user_data['timezone'] = $tz;
        }
        if (!empty($allowed)) {
            foreach ($allowed as $v) {
                if ($v == 'name') {
                    $user_data['fullname'] = $xoopsUser->getVar('name'); 
                } elseif ($v == 'email') {
                    $user_data['email'] = $xoopsUser->getVar('email'); 
                }
            }
        }
        return $user_data;
    }

    static function getUnameFromUrl($url)
    {
        $member_handler =& xoops_gethandler('member');
        $parsed = Openid_Server_Url::idFromURL($url);
        if (!empty($parsed['uid'])) {
            $user =& $member_handler->getUser($parsed['uid']);
            if ($user && $user->getVar('level') > 0) {
                return $user->getVar('uname');
            }
        } elseif (!empty($parsed['uname'])) {
            $criteria = new CriteriaCompo(new Criteria('uname', $parsed['uname']));
            if ($member_handler->getUserCount($criteria) > 0) {
                return $parsed['uname'];
            }
        }
        return FALSE;
    }

    private static function ofsetToTimeZone($timezone_offset)
    {
        $timezone_offset = intval($timezone_offset);
        if (function_exists('timezone_abbreviations_list')) {
            $timezones = timezone_abbreviations_list();
            foreach ($timezones as $timezone) {
                foreach ($timezone as $tz) {
                    if ($tz['dst'] === FALSE && $tz['offset'] == $timezone_offset * 3600) {
                        return $tz['timezone_id'];
                    }
                }
            }
        }
        return NULL;
    }

    //not use
    static function getUserByGetVar()
    {
        $uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
        if ($uid > 0) {
            $member_handler =& xoops_gethandler('member');
            $user =& $member_handler->getUser($uid);
            if ($user && $user->getVar('level') > 0) {
                return $user;
            }
        }
        return FALSE;
    }
}