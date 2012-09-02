<?php
define('OPENID_BAD_REQUEST', 400);
class Openid
{
    /**
     * Endpoint URL of this server
     * @var string
     */
    private $end_point = '';

    /**
     * XOOPS template name
     * @var string
     */
    private $template = '';

    /**
     * XOOPS template vars
     * @var string
     */
    private $tpl_vars = array();

    public function __construct()
    {
        if (!@$GLOBALS['xoopsModuleConfig']['openid_rand_souce']) {
            define('Auth_OpenID_RAND_SOURCE', NULL);
        } else if (!@is_readable($GLOBALS['xoopsModuleConfig']['openid_rand_souce'])) {
            redirect_header(XOOPS_URL, 2, 'Please set rand_source on admin panel');
        } else {
            define('Auth_OpenID_RAND_SOURCE', $GLOBALS['xoopsModuleConfig']['openid_rand_souce']);
        }
        if (@$GLOBALS['xoopsModuleConfig']['curl_cainfo_file']) {
            $cainfo = str_replace('XOOPS_ROOT_PATH', XOOPS_ROOT_PATH, $GLOBALS['xoopsModuleConfig']['curl_cainfo_file']);
            define('Auth_OpenID_CURLOPT_CAINFO_FILE', $cainfo);
        }

        $path_extra = XOOPS_ROOT_PATH . '/modules/openid/class/php5-openid';
        $path = ini_get('include_path');
        $path = $path_extra . PATH_SEPARATOR . $path;
        ini_set('include_path', $path);
    }

    public function execute()
    {
        if (TRUE) {
            $this->server();
        } else {
            $this->consumer();
        }
    }

    public function view()
    {
        $GLOBALS['xoopsOption']['template_main'] = $this->template;
        foreach ($this->tpl_vars as $k => $v) {
            $GLOBALS['xoopsTpl']->assign($k, $v);
        }
    }

    private function server()
    {
        require_once XOOPS_ROOT_PATH . '/modules/openid/class/Server/Url.php';
        $action = Openid_Server_Url::extract();
        if ($action == '') {
            $method = 'action_Default';
        } else {
            $method = 'action_' . ucfirst($action);
        }
        if (!method_exists($this, $method)) {
            exit(OPENID_BAD_REQUEST);
        }

        $this->end_point = Openid_Server_Url::build();
        try {
            $response = $this->$method();
        } catch (Exception $e) {
            header('X-XRDS-Location: ' . Openid_Server_Url::build('idpXrds'));
            $this->template = 'openid_server_default.html';
            $this->tpl_vars['message'] = $e->getMessage();
            return;
        }

        if ($response) {
            // clear output buffer
            while (ob_get_level()) {
                ob_end_clean();
            }

            if (isset($response[2])) {
                header("HTTP/1.1 %d ", $response[2], TRUE, $response[2]);
            }
            array_walk($response[0], 'header');
            header('Connection: close');
            header('Content-length: ' . strlen($response[1]));
            echo $response[1];
            exit(0);
        } else {
            // continue self::view() method after include xoops_header
        }
    }

    /**
     * Handle a standard OpenID server request
     * 
     * @return array OR FALSE
     */
    private function action_Default()
    {
        require_once XOOPS_ROOT_PATH . '/modules/openid/class/Server/LibWrapper.php';
        require_once XOOPS_ROOT_PATH . '/modules/openid/class/Server/Member.php';

        $library = new Openid_Server_LibWrapper($this->end_point);
        $identity = Openid_Server_Member::getLoggedInUser();
        $output = $library->handleRequest(!$identity);
        if ($output) {
            return $output;
        }
        if ($identity) {
            $this->renderTrust($library, $identity);
        } else {
            $claimed_identity = $library->getClaimedIdentity();
        	$this->renderLogin($claimed_identity);
		}
        return FALSE;
    }

    /**
     * 
     * @param string $identity
     * @return void
     */
    private function renderTrust($library, $identity)
    {
		$this->template = 'openid_server_trust.html';
		$this->tpl_vars['identifier'] = $identity;
        $trust_root = $library->getTrustRoot($identity);
		$this->tpl_vars['trust_root'] = $trust_root;
		$this->tpl_vars['trust_url'] = Openid_Server_Url::build('auth');
    }

    /**
     * Build Login Page
     * @param string $claimed_identity
     */
    private function renderLogin($claimed_identity)
    {
        $this->template = 'openid_server_login.html';
        $this->tpl_vars['redirect_page'] = Openid_Server_Url::build('trust', NULL, FALSE);
        $this->tpl_vars['cancel_url'] = Openid_Server_Url::build('cancel');
        if ($claimed_identity) {
            $need = Openid_Server_Member::getUnameFromUrl($claimed_identity);
            if ($need) {
                $this->tpl_vars['need'] = sprintf('You must be logged in as [%s] to approve this request.', $need);
            }
        }
    }

    /**
     * Ask the user whether he wants to trust this site
     * @return FALSE
     */
    private function action_Trust()
    {
        require_once XOOPS_ROOT_PATH . '/modules/openid/class/Server/LibWrapper.php';
        require_once XOOPS_ROOT_PATH . '/modules/openid/class/Server/Member.php';

        $library = new Openid_Server_LibWrapper($this->end_point, 'c');
        $identity = Openid_Server_Member::getLoggedInUser();
        if ($identity) {
            $this->renderTrust($library, $identity);
            return FALSE;
        } else {
            throw new Exception('This page is only show after success login.');
        }
    }

    /**
     * Potentially continue the requested identity approval
     * 
     * @return array
     */
    private function action_Auth()
    {
        require_once XOOPS_ROOT_PATH . '/modules/openid/class/Server/LibWrapper.php';
        $library = new Openid_Server_LibWrapper($this->end_point, 's');
    	if (isset($_POST['cancel'])) {
            return $library->authCancel();
        } elseif (empty($_POST['trust'])) {
    		exit(OPENID_BAD_REQUEST);
        }
        require_once XOOPS_ROOT_PATH . '/modules/openid/class/Server/Member.php';
        $identity = Openid_Server_Member::getLoggedInUser();
        if ($identity) {
            $user_data = Openid_Server_Member::getUserData($_POST['allowed']);
            //@todo Save trust info to DB
            return $library->doAuth($identity, $user_data);
        } else {
            exit(OPENID_BAD_REQUEST);
        }
    }

    /**
     * Cancel identify
     * @return array
     */
    private function action_Cancel()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	    	require_once XOOPS_ROOT_PATH . '/modules/openid/class/Server/LibWrapper.php';
	        $library = new Openid_Server_LibWrapper($this->end_point, 'c');
            return $library->authCancel();
        }
        exit(OPENID_BAD_REQUEST);
    }

    /**
     * OP Identifier XRDS
     * 
     * @return array
     */
    private function action_IdpXrds()
    {
        require_once XOOPS_ROOT_PATH . '/modules/openid/include/formats.php';
        return $this->renderXrds(OPENID_IDP_XRDS);
    }

    /**
     * User XRDS
     * 
     * @return array
     */
    private function action_UserXrds()
    {
        require_once XOOPS_ROOT_PATH . '/modules/openid/include/formats.php';
        return $this->renderXrds(OPENID_USER_XRDS);
    }

    /**
     * 
     * @param string $format
     * @return array
     */
    private function renderXrds($format)
    {
        $headers = array('Content-type: application/xrds+xml');
        $body = sprintf($format, $this->end_point);
        return array($headers, $body);
    }

    /**
     * User Claimed Identifier Page
     * 
     * @return array
     */
    private function action_User()
    {
        require_once XOOPS_ROOT_PATH . '/modules/openid/include/formats.php';
		$xrds_location = Openid_Server_Url::build('userXrds');
		$headers = array('X-XRDS-Location: '.$xrds_location);
        $body = sprintf(OPENID_IDPAGE, $xrds_location, $this->end_point);
		return array($headers, $body);
    }

    private function consumer()
    {
        // Not implemented yet
    }
}