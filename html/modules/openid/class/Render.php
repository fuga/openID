<?php
/**
 * Render page by using XOOPS render system.
 *
 */
class Openid_Render
{
    /**
     * XOOPS template name
     * 
     * @var string
     */
    public $template = '';

    /**
     * XOOPS template vars
     * 
     * @var array
     */
    public $tpl_vars = array();

    public function view()
    {
        $GLOBALS['xoopsOption']['template_main'] = $this->template;
        foreach ($this->tpl_vars as $k => $v) {
            $GLOBALS['xoopsTpl']->assign($k, $v);
        }
    }

    /**
     * 
     * @param Exception $e
     */
    public function errorPage(Exception $e)
    {
        header('X-XRDS-Location: ' . Openid_Server_Url::build('idpXrds'));
        $this->template = 'openid_server_default.html';
        $this->tpl_vars['message'] = $e->getMessage();
    }

    /**
     * Build trust form.
     * 
     * @param string $identity
     * @param string $trust_root
     * @param object {@link XoopsUser} $user
     */
    public function trustForm($identity, $trust_root, XoopsUser $user)
    {
        $this->template = 'openid_server_trust.html';
        $this->tpl_vars['trust_root'] = $trust_root;
        $this->tpl_vars['identifier'] = $identity;
        $this->tpl_vars['trust_url'] = Openid_Server_Url::build('auth');
        $this->tpl_vars['email'] = $xoopsUser->getVar('email');
        $this->tpl_vars['name'] = $xoopsUser->getVar('name');
    }

    /**
     * Build login form.
     * 
     * @param object {@link XoopsUser} $needed
     */
    public function loginForm(XoopsUser $needed=NULL)
    {
        $this->template = 'openid_server_login.html';
        $this->tpl_vars['redirect_page'] = Openid_Server_Url::build('trust', NULL, TRUE);
        $this->tpl_vars['cancel_url'] = Openid_Server_Url::build('cancel');
        if ($needed) {
            $login_as = $needed->getVar('uname');
        } else {
            $login_as = _MEMBERS;
        }
        $this->tpl_vars['login_as'] = $login_as;
    }

    /**
     * Build User Identifier Page
     * 
     * @param object {@link XoopsUser} $user
     */
    public function userIdentifier(XoopsUser $user)
    {
        $this->template = 'openid_server_default.html';

        $xrds_location = Openid_Server_Url::build('userXrds');
        header('X-XRDS-Location: ' . $xrds_location);

        $header = '<meta http-equiv="X-XRDS-Location" content="%s"/>';
        $header = sprintf($header, $xrds_location);
        $this->tpl_vars['xoops_module_header'] = $header;

        $message = 'This is the identity page of <a href="%s/userinfo.php?uid=%u">%s</a>';
        $message = sprintf($message, XOOPS_URL, $user->getVar('uid'), $user->getVar('uname'));
        $this->tpl_vars['message'] = $message;
    }
}