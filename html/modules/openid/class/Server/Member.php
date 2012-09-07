<?php
class Openid_Server_Member
{
    /**
     * Get the logged in xoops user
     *
     * @return mixed (object {@link XoopsUser} OR FALSE)
     */
    static function getLoggedInUser()
    {
        global $xoopsUser;
        if (is_object(@$xoopsUser)) {
            return $xoopsUser;
        }
        return FALSE;
    }

    /**
     * Get XOOPS user from vars-array(ex. $_GET)
     * 
     * @param array $vars
     * @return object {@link XoopsUser} $user OR NULL
     */
    static function getUserByVarArray(array $vars)
    {
        $member_handler =& xoops_gethandler('member');
        if (!empty($vars['uid']) && $uid = intval($vars['uid'])) {
            $user =& $member_handler->getUser($uid);
            if ($user && $user->getVar('level') > 0) {
                return $user;
            }
        } elseif (!empty($vars['uname'])) {
            $uname = mysql_real_escape_string($vars['uname']);
            $criteria = new CriteriaCompo(new Criteria('uname', $uname));
            $criteria->add(new Criteria('level', 0, '>'));
            $users =& $member_handler->getUsers($criteria);
            if ($users) {
                return $users[0];
            }
        }
        return NULL;
    }

    /**
     * 
     * @param array $allowed
     * @return array $user_data
     */
    static function getUserData(array $allowed=NULL)
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
}