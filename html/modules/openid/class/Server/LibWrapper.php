<?php
class Openid_Server_LibWrapper
{
    const STORE_NAME = 'openid_request';

    /**
     * End Point URL of this server
     * @var string
     */
    private $endpoint;

    /**
     * 
     * @var Auth_OpenID_Request
     */
    private $request = NULL;

    /**
     * 
     * @param string $endpoint
     */
    public function __construct($endpoint, $restore_from=NULL)
    {
        $this->endpoint = $endpoint;
        if ($restore_from == 'c') {
            $this->restoreRequestInfo(TRUE);
        } elseif ($restore_from == 's') {
        	$this->restoreRequestInfo();
        }
    }

    /**
     * Handle a standard OpenID server request
     * @param boolean $store_to_cookie
     * @return array OR FALSE
     */
    public function handleRequest($store_to_cookie)
    {
        $server = $this->getServer();
        $request = $server->decodeRequest();
        if (!$request) {
            throw new Exception('This is a OP-identifier of this server.');
        } elseif ($request instanceof Auth_OpenID_ServerError) {
            return $this->handleError($request);
        }

        if (in_array($request->mode,
                    array('checkid_immediate', 'checkid_setup'))) {

            if ($request->idSelect()) {
                // Perform IDP-driven identifier selection
                if ($request->mode == 'checkid_immediate') {
                    $response = $request->answer(FALSE);
                } else {
                    $this->request = $request;
                    $this->storeRequestInfo($store_to_cookie);
                    return FALSE;
                }
            } elseif (!$request->identity) {
                // No identifier used or desired; display a page saying so.
                $message = 'You did not send an identifier with the request,'
                         . 'and it was not an identifier selection request.'
                         . 'Please return to the relying party and try again.';
                throw new Exception($message);
            } elseif ($request->immediate) {
                $response = $request->answer(FALSE, $this->endpoint);
            } else {
                $this->request = $request;
                $this->storeRequestInfo($store_to_cookie);
                return FALSE;
            }
        } else {
            $response = $server->handleRequest($request);
        }

        $webresponse = $server->encodeResponse($response);

        $code = NULL;
        if ($webresponse->code != AUTH_OPENID_HTTP_OK) {
            $code = $webresponse->code;
	    }

	    $headers = array();
	    foreach ($webresponse->headers as $k => $v) {
            $headers[] = "$k: $v";
        }
        return array($headers, $webresponse->body, $code);
    }

    /**
     * 
     * @param string $current_user_identity
     * @return string $trust_root
     */
    public function getTrustRoot($current_user_identity)
    {
        if (!$this->request) {
            // Bad request(logged)
            exit(1);
        }
    	if ($this->request->idSelect()
          || ($this->request->identity == $current_user_identity)) {
            return $this->request->trust_root;
        } else {
            throw new Exception("Claimed Identifier is not owned by current user.");
        }
    }

    /**
     * 
     * @return string $identity
     */
    public function getClaimedIdentity()
    {
        if ($this->request->identity) {
            $identity = $this->request->identity;
        } else {
            $identity = NULL;
        }
        return $identity;
    }

    /**
     * 
     * @param string $claimed_id
     * @param array $sreg_data
     * @return array
     */
    public function doAuth($claimed_id, array $sreg_data)
    {
        if (!$this->request) {
            // Bad request(logged)
        	exit(1);
        }
        $trust_root = $this->getTrustRoot($claimed_id);
		$response = $this->request->answer(TRUE, NULL, $claimed_id);
        if ($response instanceof Auth_OpenID_ServerError) {
            return $this->handleError($response);
        }

        // Answer with Simple Registration data.
        require_once "Auth/OpenID/SReg.php";
        $sreg_request = Auth_OpenID_SRegRequest::fromOpenIDRequest($this->request);
        $sreg_response = Auth_OpenID_SRegResponse::extractResponse(
                                              $sreg_request, $sreg_data);

        // Add the simple registration response values to the OpenID
        // response message.
        $sreg_response->toMessage($response->fields);

        // Generate a response to send to the user agent.
        $server = $this->getServer();
        $webresponse = $server->encodeResponse($response);
        $headers = array();
        foreach ($webresponse->headers as $k => $v) {
            $headers[] = "$k: $v";
        }
        return array($headers, $webresponse->body);
    }

    /**
     * 
     * @return array
     */
    public function authCancel()
    {
        if (!$this->request) {
            exit(1);
        }
    	$url = $this->request->getCancelURL();
        if (is_string($url)) {
            $headers = array('Location: ' . $url);
            return array($headers, '');
        } elseif ($url instanceof Auth_OpenID_NoReturnToError) {
            throw new Exception('Fail cancel. Please manualy back to RP.');
        } elseif ($url instanceof Auth_OpenID_ServerError) {
            return $this->handleError($url);
        } else {
            $type = gettype($url);
            if ($type == 'object') {
                $type = get_class($url);
            }
            exit($type);
        }
    }

    /**
     * 
     * @param boolean $store_to_cookie
     * @return boolean
     */
    private function restoreRequestInfo($store_to_cookie=FALSE)
    {
        $serialized = NULL;
        if ($store_to_cookie) {
            if (!empty($_COOKIE[self::STORE_NAME])) {
                $hashed = $_COOKIE[self::STORE_NAME];
                $cache_file = XOOPS_CACHE_PATH . '/openid_' . $hashed;
                if (file_exists($cache_file)) {
                    $serialized = file_get_contents($cache_file);
                    @unlink($cache_file);
                }
                setcookie(self::STORE_NAME, '', time() - 3600);
            }
        } else {
            if (isset($_SESSION[self::STORE_NAME])) {
                $serialized = $_SESSION[self::STORE_NAME];
                unset($_SESSION[self::STORE_NAME]);
            }
        }
        if ($serialized) {
            require_once "Auth/OpenID/Server.php";
            $this->request = unserialize($serialized);
        }
        if ($this->request) {
            if ($store_to_cookie) {
                $this->storeRequestInfo();
            }
            return TRUE;
        } else {
            Auth_OpenID::log('Fail Restore Request');
            return FALSE;
        }
    }

    /**
     * Instantiate a new OpenID server object
     * @return Auth_OpenID_Server
     */
    private function getServer()
    {
        require_once "Auth/OpenID/Server.php";
        $server = new Auth_OpenID_Server($this->getOpenIDStore(),
                                         $this->endpoint);
        return $server;
    }

    /**
     * Initialize an OpenID store
     *
     * @return object $store an instance of OpenID store
     */
    private function getOpenIDStore()
    {
        require_once 'xoopsDBconnection.php';
        require_once 'ExMySQLStore.php';

        $connection = new OpenID_XoopsDBconnection($GLOBALS['xoopsDB']);
        $store = new OpenID_ExMySQLStore($connection,
                                $GLOBALS['xoopsDB']->prefix('openid_assoc'),
                                $GLOBALS['xoopsDB']->prefix('openid_nonce')
                                );
        return $store;
    }


    /**
     * 
     * @param Auth_OpenID_ServerError $error
     * @return array
     */
    private function handleError(Auth_OpenID_ServerError $error)
    {
        $encode = $error->whichEncoding();
        if ($encode == Auth_OpenID_ENCODE_URL) {
            $url = $error->encodeToURL();
            if (is_string($url)) {
                $headers = array('Location: ' . $url);
                return array($headers, '');
            }
        } elseif ($encode == Auth_OpenID_ENCODE_HTML_FORM) {
            $headers = array();
            $body = $error->toHTML();
            return array($headers, $body);
        }
        throw new Exception('Error: ' . $error->toString());
    }

    /**
     * Save request info temporarily
     * @param boolean $store_to_cookie
     */
    private function storeRequestInfo($store_to_cookie=FALSE)
    {
        $ret = TRUE;
        $serialized = serialize($this->request);
        if ($store_to_cookie) {
            $hashed = md5($serialized);
            $cache_file = XOOPS_CACHE_PATH . '/openid_' . $hashed;
            if (setcookie(self::STORE_NAME, $hashed)) {
                if (!file_put_contents($cache_file, $serialized)) {
                    Auth_OpenID::log('Fail: write cache file');
                    $ret = FALSE;
                }
            } else {
                Auth_OpenID::log('Fail: write data to cookie');
                $ret = FALSE;
            }
        } else {
            $_SESSION[self::STORE_NAME] = $serialized;
        }
        return $ret;
    }
}