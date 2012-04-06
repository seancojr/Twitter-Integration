<?php

class Twitter_ControllerPublic_Register extends XFCP_Twitter_ControllerPublic_Register
{
	public function actionTwitter()
	{
		$assocUserId = $this->_input->filterSingle('assoc', XenForo_Input::UINT);
		$redirect = $this->_input->filterSingle('redirect', XenForo_Input::STRING);
		
		/** @var XenForo_Session */
		$session = XenForo_Application::get('session');

		if ($this->_input->filterSingle('reg', XenForo_Input::UINT))
		{
			$consumer = new Zend_Oauth_Consumer($this->_getAuthConfig());

			$token = $consumer->getRequestToken();

			$session->set('twitter_oauth_token', $token->getToken());
			$session->set('twitter_oauth_secret', $token->getTokenSecret());
			$session->save();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				$consumer->getRedirectUrl()
			);
		}

		if (!$session->get('twitter_oauth_token') OR !$session->get('twitter_oauth_secret'))
		{
			return $this->responseError('OAuth token missing or bad request');
		}

		$request = new Zend_Oauth_Token_Request();
		$request->setToken($session->get('twitter_oauth_token'))->setTokenSecret($session->get('twitter_oauth_secret'));
		
		// retrieve the token
		$consumer = new Zend_Oauth_Consumer($this->_getAuthConfig());
		$token = $consumer->getAccessToken($this->_input->getInput(), $request);
		
		if ($token->getParam('user_id') == null)
		{
			return $this->responseError('OAuth didn\'t give back a userid');
		}
		
		$session->set('twitter_token', $token);
        /* @var $userModel XenForo_Model_User*/
		$userModel = $this->_getUserModel();
        /* @var $userExternalModel XenForo_Model_UserExternal*/
		$userExternalModel = $this->_getUserExternalModel();

		$twAssoc = $userExternalModel->getExternalAuthAssociation('twitter', $token->getParam('user_id'));
        //print_r($twAssoc);print_r($a);
		if ($twAssoc && $userModel->getUserById($twAssoc['user_id']))
		{
            $userModel->setUserRememberCookie($twAssoc['user_id']);
			XenForo_Application::get('session')->changeUserId($twAssoc['user_id']);
			XenForo_Visitor::setup($twAssoc['user_id']);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect(false, false)
			);
		}
		
		$existingUser = false;
		if (XenForo_Visitor::getUserId())
		{
			$existingUser = XenForo_Visitor::getInstance();
		}
		else if ($assocUserId)
		{
			$existingUser = $userModel->getUserById($assocUserId);
		}

		if ($existingUser)
		{
			// must associate: matching user
			return $this->responseView('XenForo_ViewPublic_Register_Twitter', 'register_twitter', array(
				'associateOnly' => true,

				'existingUser' => $existingUser,
				'redirect' => $redirect
			));
		}
		
		if (!XenForo_Application::get('options')->get('registrationSetup', 'enabled'))
		{
			$this->_assertRegistrationActive();
		}
		
		// give a unique username suggestion
		$i = 2;
		$username = $token->getParam('screen_name');
		$origName = $username;
		while ($userModel->getUserByName($username))
		{
			$username = $origName . ' ' . $i++;
		}
		
		return $this->responseView('XenForo_ViewPublic_Register_Twitter', 'register_twitter', array(
			'username' => $username,
			'redirect' => $redirect,
		     
		    'customFields' => $this->_getFieldModel()->prepareUserFields(
				$this->_getFieldModel()->getUserFields(array('registration' => true)),
				true
			),
			'timeZones' => XenForo_Helper_TimeZone::getTimeZones(),
			'tosUrl' => XenForo_Dependencies_Public::getTosUrl()
		), $this->_getRegistrationContainerParams());
	}
	
	public function actionTwitterRegister()
	{
		$this->_assertPostOnly();
		
		$session = XenForo_Application::get('session');
		
		if (!$session->get('twitter_token'))
		{
			return $this->responseError('Lost twitter token');
		}
		
		$token = $session->get('twitter_token');
		
        /* @var $userModel XenForo_Model_User*/
		$userModel = $this->_getUserModel();
        /* @var $userExternalModel XenForo_Model_UserExternal*/
		$userExternalModel = $this->_getUserExternalModel();
		
		$doAssoc = ($this->_input->filterSingle('associate', XenForo_Input::STRING)
			|| $this->_input->filterSingle('force_assoc', XenForo_Input::UINT)
		);
		
		if ($doAssoc)
		{
			$associate = $this->_input->filter(array(
				'associate_login' => XenForo_Input::STRING,
				'associate_password' => XenForo_Input::STRING
			));
			
			$loginModel = $this->_getLoginModel();
			
			if ($loginModel->requireLoginCaptcha($associate['associate_login']))
			{
				return $this->responseError(new XenForo_Phrase('your_account_has_temporarily_been_locked_due_to_failed_login_attempts'));
			}

			$userId = $userModel->validateAuthentication($associate['associate_login'], $associate['associate_password'], $error);
			if (!$userId)
			{
				$loginModel->logLoginAttempt($associate['associate_login']);
				return $this->responseError($error);
			}
			
			$userExternalModel->updateExternalAuthAssociation('twitter', $token->getParam('user_id'), $userId);
			
			$session->changeUserId($userId);
			XenForo_Visitor::setup($userId);
			
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect(false, false)
			);
		}
		
		$this->_assertRegistrationActive();
				
		$data = $this->_input->filter(array(
			'username'   => XenForo_Input::STRING,
			'timezone'   => XenForo_Input::STRING,
			'email'      => XenForo_Input::STRING,
			'gender'     => XenForo_Input::STRING,
			'dob_day'    => XenForo_Input::UINT,
			'dob_month'  => XenForo_Input::UINT,
			'dob_year'   => XenForo_Input::UINT,
		));

		if (XenForo_Dependencies_Public::getTosUrl() && !$this->_input->filterSingle('agree', XenForo_Input::UINT))
		{
			return $this->responseError(new XenForo_Phrase('you_must_agree_to_terms_of_service'));
		}

		$options = XenForo_Application::get('options');
		
		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
		if ($options->registrationDefaults)
		{
			$writer->bulkSet($options->registrationDefaults, array('ignoreInvalidFields' => true));
		}
		$writer->bulkSet($data);
		
		$auth = XenForo_Authentication_Abstract::create('XenForo_Authentication_NoPassword');
		$writer->set('scheme_class', $auth->getClassName());
		$writer->set('data', $auth->generate(''), 'xf_user_authenticate');
		
		$writer->set('user_group_id', XenForo_Model_User::$defaultRegisteredGroupId);
		$writer->set('language_id', XenForo_Visitor::getInstance()->get('language_id'));
		
		$customFields = $this->_input->filterSingle('custom_fields', XenForo_Input::ARRAY_SIMPLE);
		$customFieldsShown = $this->_input->filterSingle('custom_fields_shown', XenForo_Input::STRING, array('array' => true));
		$writer->setCustomFields($customFields, $customFieldsShown);
		
		$writer->advanceRegistrationUserState(false);
		$writer->preSave();
		
	    if ($options->get('registrationSetup', 'requireDob'))
		{
			// dob required
			if (!$data['dob_day'] || !$data['dob_month'] || !$data['dob_year'])
			{
				$writer->error(new XenForo_Phrase('please_enter_valid_date_of_birth'), 'dob');
			}
			else
			{
				$userAge = $this->_getUserProfileModel()->getUserAge($writer->getMergedData(), true);
				if ($userAge < 1)
				{
					$writer->error(new XenForo_Phrase('please_enter_valid_date_of_birth'), 'dob');
				}
				else if ($userAge < intval($options->get('registrationSetup', 'minimumAge')))
				{
					// TODO: set a cookie to prevent re-registration attempts
					$writer->error(new XenForo_Phrase('sorry_you_too_young_to_create_an_account'));
				}
			}
		}
		
		$writer->save();
		$user = $writer->getMergedData();

        $avatarFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
        if ($avatarFile)
        {
            $data = Twitter_Helper_Twitter::getUserPicture($token->getParam('user_id'));
            if ($data && $data[0] != '{') // ensure it's not a json response
            {
                file_put_contents($avatarFile, $data);

                try
                {
                    $user = array_merge($user,
                        $this->getModelFromCache('XenForo_Model_Avatar')->applyAvatar($user['user_id'], $avatarFile)
                    );
                }
                catch (XenForo_Exception $e) {}
            }

            @unlink($avatarFile);
        }

		$userExternalModel->updateExternalAuthAssociation('twitter', $token->getParam('user_id'), $user['user_id']);
		
		XenForo_Model_Ip::log($user['user_id'], 'user', $user['user_id'], 'register');
		
		$session->changeUserId($user['user_id']);
		XenForo_Visitor::setup($user['user_id']);
		
		$redirect = $this->_input->filterSingle('redirect', XenForo_Input::STRING);
		
		$viewParams = array(
			'user' => $user,
			'redirect' => ($redirect ? XenForo_Link::convertUriToAbsoluteUri($redirect) : ''),
			'twitter' => true
		);

		return $this->responseView(
			'XenForo_ViewPublic_Register_Process',
			'register_process',
			$viewParams,
			$this->_getRegistrationContainerParams()
		);
	}
	
	private function _getAuthConfig()
	{
		$callbackUri = XenForo_Link::buildPublicLink('canonical:register/twitter', false, array(
			'redirect' => $this->getDynamicRedirect()
		));
		
		$options = XenForo_Application::get('options');

		return array(
			'callbackUrl'	=> $callbackUri,
			'siteUrl'		=> 'https://api.twitter.com/oauth',
			'consumerKey'	=> $options->twitterConsumerKey,
			'consumerSecret'=> $options->twitterConsumerSecret,
			'authorizeUrl'  => 'https://api.twitter.com/oauth/authenticate'
		);
	}
}