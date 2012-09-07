<?php
if (!defined('XOOPS_ROOT_PATH')) exit();

class Openid_Provider extends XCube_ActionFilter
{
    public function preBlockFilter()
    {
        $this->mRoot->mDelegateManager->add("Legacypage.Top.Access", 'Openid_Provider::setXrdsLocation');
    }

    public static function setXrdsLocation()
    {
        require_once XOOPS_ROOT_PATH . '/modules/openid/class/Server/Url.php';
        header('X-XRDS-Location: ' . Openid_Server_Url::build('idpXrds'));
        // @todo set <meta http-equiv="X-XRDS-Location"> to html header.
    }
}