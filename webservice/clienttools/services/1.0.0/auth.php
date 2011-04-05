<?php

class auth extends client_service {

    public function login()
    {
        $this->logTrace((__METHOD__.'('.__FILE__.' '.__LINE__.')'), 'Enter Function');
        $params = $this->AuthInfo;

        $username = $params['user'];
        $passhash = $params['passhash'];
        $token = $params['token'];
        $app_type = $params['appType'];
        $session_id = $params['session'];
        $ip = $_SERVER['REMOTE_ADDR'];
        $language = isset($params['language']) ? $params['language'] : 'en';

        $this->Response->setDebug('parameters',$params);

        setcookie("kt_language", $language, 2147483647, '/');

        $kt =& $this->KT;

        if ($username != 'admin') {
            //$this->addDebug('@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@','');
            try {
                if (class_exists('BaobabKeyUtil')) {
                    if (!BaobabKeyUtil::checkIfLicensed(true)) {
                        $this->setResponse(array('authenticated'=> false, 'message'=> 'license_expired'));
                        $this->addError('Licence Expired');
                        return false;
                    }
                }
                else {
                    $this->addError('Licence Utility could not be loaded. Appears to be a Community version.');
                    $this->setResponse(array('authenticated'=> false, 'message'=> 'Licence Utility could not be loaded. Appears to be a Community version.'));
                    return false;
                }
            }
            catch(Exception $e) {
                $this->addError('could not execute BaobabKeyUtil::checkIfLicensed');
                $this->setResponse(array('authenticated' => false, 'message' => 'BaobabKeyUtil::checkIfLicensed error'));
                return;
            }
        }

        $user=$kt->get_user_object_by_username($username);
        if(!PEAR::isError($user)){

			$session=new stdClass();
			$this->Response->setDebug('trying to start session with',array('username'=>$username,'password'=>$params['passhash']));

			$session = $kt->start_session($username, $params['passhash'],NULL,$app_type);
			if(!PEAR::isError($session)){
				$this->Response->setStatus('session_id',$session->get_session());
				$ret=array('fullName' => $user->getName());
		        $this->setResponse($ret);
			}else{
				$this->setResponse(array('authenticated'=> false, 'message'=> 'Invalid username and/or password.'));
				$this->addDebug('failed login '.$session->getMessage());
				//$this->addError('Unknown Login Error');
				$this->addError('Invalid username and/or password');
				return false;
			}

			/*
			// OLD WAY = DO NOT DELETE!
			$password=$user->getPassword();
			$localPassHash=md5($password.$token);
			if($localPassHash==$passhash){
				$session=new stdClass();
				$this->Response->setDebug('trying to start session with',array('username'=>$username,'password'=>$password));
		        $session = $kt->start_session($username, $params['pass'],NULL,$app_type);
		        if(!PEAR::isError($session)){
		        	$this->Response->setStatus('session_id',$session->get_session());
		        }else{
					$this->setResponse(array('authenticated'=> false, 'message'=> 'Invalid username and/or password.'));
		        	$this->addDebug('failed login '.$session->getMessage());
		        	$this->addError('Unknown Login Error');
		        	return false;
		        }
			}else{
				$this->addError('Incorrect Credentials');
				//throw new Exception('Incorrect Credentials');
				return false;
			}
			*/

        } else {
            $this->addError('Incorrect Credentials');
            //throw new Exception('Unrecognized User');
            return false;
        }

        return true;
    }

	public function japiLogin(){

	    return $this->login();

	    // redirecting to login() as this function appears to return the user regardless of the supplied password
	    // not sure why this happens or when it changed ... but it is causing a security risk in ExplorerCP
	    /*
		$this->logTrace((__METHOD__.'('.__FILE__.' '.__LINE__.')'),'Enter Function');
		global $default;
       	$user=$this->KT->get_user_object_by_username($this->AuthInfo['user']);
		$ret=array('fullName' => PEAR::isError($user)?'':$user->getName());
		$this->setResponse($ret);
		return true;
		*/
	}

    public function pickup_session()
    {
        $this->logTrace((__METHOD__.'('.__FILE__.' '.__LINE__.')'), 'Enter Function');
        $params = $this->AuthInfo;
        $app_type = $params['appType'];
        $session_id = $params['session'];
        $ip = $_SERVER['REMOTE_ADDR'];

        $session = $this->KT->get_active_session($session_id, $ip, $app_type);

        if (PEAR::isError($session)) {
            return false;
        }

        $this->Response->setStatus('session_id',$session->get_session());

        return true;
    }

    public function ping()
    {
        global $default;
        $this->logTrace((__METHOD__.'('.__FILE__.' '.__LINE__.')'), 'Enter Function');
        $user = $this->KT->get_user_object_by_username($this->AuthInfo['user']);
        $versions = $this->handler->getServerVersions();
        $bestVer = $versions[count($versions) - 1];
        $clientVer = $this->handler->getVersion();
        $ret = array(
            'response' => 'pong',
            'loginLocation' => '/index.html',
            'versionok' => in_array($clientVer, $versions),
            'fullName' => PEAR::isError($user) ? '' : $user->getName(),
            'serverVersions' => $versions,
            'serverBestVersion' => $bestVer,
            'clientVersion' => $clientVer,
            'canUpgradeClient' => ($clientVer<$bestVer ? true : false),
            'canUpgradeServer' => ($clientVer>$bestVer ? true : false)
        );

        $this->setResponse($ret);

        return true;
    }

    public function logout($params)
    {
        $this->logTrace((__METHOD__.'('.__FILE__.' '.__LINE__.')'),'Enter Function');
        $params = $this->AuthInfo;
        $app_type = $params['appType'];
        $session_id = $params['session'];
        $ip = $_SERVER['REMOTE_ADDR'];

        $session=$this->KT->get_session();
        $this->logInfo((__METHOD__.'('.__FILE__.' '.__LINE__.')'),'Logout Session Object (From KT)',$session);

        if (get_class($session)!='KTAPI_UserSession') {
            $session = $this->KT->get_active_session($session_id, $ip, $app_type);
        }

        $this->logInfo((__METHOD__.'('.__FILE__.' '.__LINE__.')'),'Logout Session Object (To Logout)',$session);

        if (PEAR::isError($session)) {
            return false;
        }

        $this->logTrace((__METHOD__.'('.__FILE__.' '.__LINE__.')'),'Proceeding With Logout');

        $newSessId = md5(session_id());
        $session->logout();

        $this->logTrace((__METHOD__.'('.__FILE__.' '.__LINE__.')'),'KTAPI session logout requested');

        session_id($newSessId);

        $this->logTrace((__METHOD__.'('.__FILE__.' '.__LINE__.')'),'New SessionID Requested from PHP (Redundant Security)['.session_id().']');
        $this->Response->setStatus('session_id',$newSessId);
        $this->logTrace((__METHOD__.'('.__FILE__.' '.__LINE__.')'),'New SessionID set in response status ['.$newSessId.']');
        $this->setResponse(array('logout'=>true));

        session_destroy();

        $this->logTrace((__METHOD__.'('.__FILE__.' '.__LINE__.')'),'PHP Session Destroyed');

        return true;
    }

}

?>
