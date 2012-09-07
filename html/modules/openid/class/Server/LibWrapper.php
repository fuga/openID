<?php
/**
 * Wrapper class of JanRain OpenID Library.
 *
 */
class Openid_Server_LibWrapper
{
    const STORE_NAME = 'openid_request';
    const CASH_PATH = XOOPS_CACHE_PATH;

    /**
     * Object contain request-info from RP
     * 
     * @var Auth_OpenID_Request
     */
    private $request = NULL;

    /**
     * Constructor
     * 
     * @param array $config
     */
    public function __construct(array $config)
    {
        if (empty($config['openid_rand_souce'])) {
            define('Auth_OpenID_RAND_SOURCE', NULL);
        } else if (!@is_readable($config['openid_rand_souce'])) {
            redirect_header(XOOPS_URL, 2, 'Please set rand_source on admin panel');
        } else {
            define('Auth_OpenID_RAND_SOURCE', $config['openid_rand_souce']);
        }
        if (!empty($config['curl_cainfo_file'])) {
            $cainfo = str_replace('XOOPS_ROOT_PATH', XOOPS_ROOT_PATH, $config['curl_cainfo_file']);
            define('Auth_OpenID_CURLOPT_CAINFO_FILE', $cainfo);
        }

        $path_extra = XOOPS_ROOT_PATH . '/modules/openid/class/php5-openid';
        $path = ini_get('include_path');
        $path = $path_extra . PATH_SEPARATOR . $path;
        ini_set('include_path', $path);
    }

    /**
     * Handle a standard OpenID server request
     * 
     * @param string $endpoint end-point URL of this server
     * @return array (array $headers, string $body, int code=NULL) OR FALSE
     */
    public function handleConsumerRequest($endpoint)
    {
        $server = $this->getServer($endpoint);
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
                    return FALSE;
                }
            } elseif (!$request->identity) {
                // No identifier used or desired; display a page saying so.
                $message = 'You did not send an identifier with the request,'
                         . 'and it was not an identifier selection request.'
                         . 'Please return to the relying party and try again.';
                throw new Exception($message);
            } elseif ($request->immediate) {
                $response = $request->answer(FALSE);
            } else {
                $this->request = $request;
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
        if ($this->request->idSelect()
          || ($this->request->identity == $current_user_identity)) {
            return $this->request->trust_root;
        } else {
            throw new Exception('Claimed Identifier is not owned by current user.');
        }
    }

    /**
     * 
     * @return string $identity
     */
    public function getClaimedIdentity()
    {
        if ($this->request->identity
          && !$this->request->idSelect()) {
            $identity = $this->request->identity;
        } else {
            $identity = NULL;
        }
        return $identity;
    }

    /**
     * Save request info temporarily
     * 
     * @param string $store_to
     */
    public function stash($store_to='session')
    {
        $serialized = serialize($this->request);
        if ($store_to == 'session') {
            $_SESSION[self::STORE_NAME] = $serialized;
        } else {
            $hashed = md5($serialized);
            $cache_file = XOOPS_CACHE_PATH . '/openid_' . $hashed;
            if (setcookie(self::STORE_NAME, $hashed)) {
                if (!file_put_contents($cache_file, $serialized)) {
                    Auth_OpenID::log('It failed to save the temporary data to CASH FILE');
                    throw new Exception('It failed to save the temporary data.');
                }
            } else {
                Auth_OpenID::log('It failed to save the temporary data to COOKIE');
                throw new Exception('It failed to save the temporary data.');
            }
        }
    }

    /**
     * 
     * @param string $restore_from
     * @return boolean
     */
    public function resume($restore_from='session')
    {
        $serialized = NULL;
        if ($restore_from == 'session') {
            if (isset($_SESSION[self::STORE_NAME])) {
                $serialized = $_SESSION[self::STORE_NAME];
                unset($_SESSION[self::STORE_NAME]);
            }
        } else {
            if (!empty($_COOKIE[self::STORE_NAME])) {
                $hashed = $_COOKIE[self::STORE_NAME];
                $cache_file = self::CASH_PATH . '/openid_' . $hashed;
                if (file_exists($cache_file)) {
                    $serialized = file_get_contents($cache_file);
                    @unlink($cache_file);
                }
                setcookie(self::STORE_NAME, '', time() - 3600);
            }
        }
        if ($serialized) {
            require_once 'Auth/OpenID/Server.php';
            $this->request = unserialize($serialized);
        }
        if ($this->request) {
            return TRUE;
        } else {
            require_once 'Auth/OpenID.php';
            Auth_OpenID::log('Fail Restore Request');
            return FALSE;
        }
    }

    /**
     * 
     * @param string $claimed_id
     * @param array $sreg_data
     * @return array (array $headers, string $body, int code=NULL)
     */
    public function doAuth($claimed_id, array $sreg_data)
    {
        $trust_root = $this->getTrustRoot($claimed_id);
        $response = $this->request->answer(TRUE, NULL, $claimed_id);
        if ($response instanceof Auth_OpenID_ServerError) {
            return $this->handleError($response);
        }

        // Answer with Simple Registration data.
        require_once 'Auth/OpenID/SReg.php';
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
     * Send cancel response to consumer
     * 
     * @return array (array $headers, string $body, int code=NULL)
     */
    public function authCancel()
    {
        $url = $this->request->getCancelURL();
        if (is_string($url)) {
            $headers = array('Location: ' . $url);
            return array($headers, '');
        } elseif ($url instanceof Auth_OpenID_NoReturnToError) {
            throw new Exception('It fail to cancel. Please manualy back to RP.');
        } elseif ($url instanceof Auth_OpenID_ServerError) {
            return $this->handleError($url);
        } else {
            $type = gettype($url);
            if ($type == 'object') {
                $type = get_class($url);
            }
            exit('Unexpected return ' . $type);
        }
    }

    /**
     * Instantiate a new OpenID server object
     * 
     * @param string $endpoint
     * @return Auth_OpenID_Server
     */
    private function getServer($endpoint)
    {
        require_once 'Auth/OpenID/Server.php';
        $server = new Auth_OpenID_Server($this->getOpenIDStore(),
                                         $endpoint);
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
     * Handle Server Error
     * 
     * @param Auth_OpenID_ServerError $error
     * @return array (array $headers, string $body, int code=NULL)
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
            $body = $error->toHTML();
            if (is_string($body)) {
                return array(NULL, $body);
            }
        } elseif ($encode == Auth_OpenID_ENCODE_KVFORM) {
            $body = $error->encodeToKVForm();
            if (is_string($body)) {
                return array(NULL, $body, AUTH_OPENID_HTTP_ERROR);
            }
        }
        throw new Exception('Error: ' . $error->toString());
    }
}