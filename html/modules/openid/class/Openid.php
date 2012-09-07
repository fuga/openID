<?php
if (!defined('OPENID_UNSUCCESSFUL_EXIT')) {
    // You can change this for debug.
    define('OPENID_UNSUCCESSFUL_EXIT', 1);
}

function __autoload($class)
{
    if (strpos($class, 'Openid_') === 0) {
        $class = substr($class, 7);
        $file_name  = XOOPS_ROOT_PATH . '/modules/openid/class/';
        $file_name .= str_replace('_', DIRECTORY_SEPARATOR, $class);
        $file_name .= '.php';

        require $file_name;
    }
}

class Openid
{
    /**
     * Render by using XOOPS render system
     * 
     * @var Openid_Render
     */
    private $render;

    public function __construct()
    {
    }

    public function execute()
    {
        $this->render = new Openid_Render();
        if (TRUE) {
            $this->server();
        } else {
            $this->consumer();
        }
    }

    public function view()
    {
        $this->render->view();
    }

    private function server()
    {
        $action = Openid_Server_Url::extract();
        if ($action == '') {
            $method = 'action_Default';
        } else {
            $method = 'action_' . ucfirst($action);
        }
        if (!method_exists($this, $method)) {
            exit(OPENID_UNSUCCESSFUL_EXIT);
        }

        try {
            $response = $this->$method();
        } catch (Exception $e) {
            $this->render->errorPage($e);
            return;
        }

        if ($response) {
            // Output response values
            list($headers, $body) = $response;

            // clear output buffer
            while (ob_get_level()) {
                ob_end_clean();
            }

            if (isset($response[3])) {
                $code = $response[3];
                header(sprintf('HTTP/1.1 %d ', $code), TRUE, $code);
            }
            if ($headers) {
                array_walk($headers, 'header');
            }
            header('Connection: close');
            header('Content-length: ' . strlen($body));
            echo $body;
            exit(0);
        } else {
            // continue $this->view() method after include xoops_header
            // data is set in $this->render
        }
    }

    /**
     * Handle a standard OpenID server request
     * 
     * @return array (array $headers, string $body, int code=NULL) OR FALSE
     */
    private function action_Default()
    {
        $library = new Openid_Server_LibWrapper($GLOBALS['xoopsModuleConfig']);
        $end_point = Openid_Server_Url::build();
        $output = $library->handleConsumerRequest($end_point);
        if ($output) {
            return $output;
        }
        $user = Openid_Server_Member::getLoggedInUser();
        if ($user) {
            $this->renderTrust($library, $user);
        } else {
            $needed = NULL;
            $claimed_identity = $library->getClaimedIdentity();
            if ($claimed_identity) {
                $user_info = Openid_Server_Url::idFromURL($claimed_identity);
                $needed = Openid_Server_Member::getUserByVarArray($user_info);
            }
            $this->render->loginForm($needed);
            $library->stash('file');
        }
        return FALSE;
    }

    /**
     * Ask the user whether he wants to trust this site
     * 
     * @return FALSE
     */
    private function action_Trust()
    {
        $library = new Openid_Server_LibWrapper($GLOBALS['xoopsModuleConfig']);
        if ($library->resume('file')) {
	        $user = Openid_Server_Member::getLoggedInUser();
	        if ($user) {
                $this->renderTrust($library, $user);
	            return FALSE;
	        }
        }
        throw new Exception('This page is only show after success login.');
    }

    private function renderTrust($library, $user)
    {
        $identity = Openid_Server_Url::build('user', $user);
        $trust_root = $library->getTrustRoot($identity);
        $this->render->trustForm($identity, $trust_root);
        $library->stash();
    }

    /**
     * Potentially continue the requested identity approval
     * 
     * @return array (array $headers, string $body, int code=NULL)
     */
    private function action_Auth()
    {
        $library = new Openid_Server_LibWrapper($GLOBALS['xoopsModuleConfig']);
        if (!$library->resume()) {
            exit(OPENID_UNSUCCESSFUL_EXIT);
        }
    	if (isset($_POST['cancel'])) {
            return $library->authCancel();
        } elseif (empty($_POST['trust'])) {
    		exit(OPENID_UNSUCCESSFUL_EXIT);
        }
        $user = Openid_Server_Member::getLoggedInUser();
        if ($user) {
            $identity = Openid_Server_Url::build('user', $user);
            $user_data = Openid_Server_Member::getUserData(@$_POST['allowed']);
            //@todo Save trust info to DB
            return $library->doAuth($identity, $user_data);
        } else {
            exit(OPENID_UNSUCCESSFUL_EXIT);
        }
    }

    /**
     * Cancel identify
     * 
     * @return array (array $headers, string $body, int code=NULL)
     */
    private function action_Cancel()
    {
        $library = new Openid_Server_LibWrapper($GLOBALS['xoopsModuleConfig']);
        if ($library->resume('file')) {
            return $library->authCancel();
        } else {
            exit(OPENID_UNSUCCESSFUL_EXIT);
        }
    }

    /**
     * OP Identifier XRDS
     * 
     * @return array (array $headers, string $body, int code=NULL)
     */
    private function action_IdpXrds()
    {
        require_once XOOPS_ROOT_PATH . '/modules/openid/include/formats.php';
        return $this->renderXrds(OPENID_IDP_XRDS);
    }

    /**
     * User XRDS
     * 
     * @return array (array $headers, string $body, int code=NULL)
     */
    private function action_UserXrds()
    {
        require_once XOOPS_ROOT_PATH . '/modules/openid/include/formats.php';
        return $this->renderXrds(OPENID_USER_XRDS);
    }

    /**
     * Build XRDS
     * 
     * @param string $format
     * @return array (array $headers, string $body, int code=NULL)
     */
    private function renderXrds($format)
    {
        $headers = array('Content-type: application/xrds+xml');
        $end_point = Openid_Server_Url::build();
        $body = sprintf($format, $end_point);
        return array($headers, $body);
    }

    /**
     * User Claimed Identifier Page.
     * 
     * @return FALSE
     */
    private function action_User()
    {
        $user = Openid_Server_Member::getUserByVarArray($_GET);
    	if ($user) {
    		$this->render->userIdentifier($user);
    		return FALSE;
        } else {
            throw new Exception("Such user doesn't exist.");
        }
    }

    private function consumer()
    {
        /*
         * Not implemented yet
         * 
        $consumer = new Openid_Consumer_Controller();
        $consumer->execute($this->render);
         */
    }
}