<?php

class Twitter_ControllerPublic_Account extends XFCP_Twitter_ControllerPublic_Account
{
	public function actionTwitter()
	{
		$visitor = XenForo_Visitor::getInstance();

		$auth = $this->_getUserModel()->getUserAuthenticationObjectByUserId($visitor['user_id']);
		if (!$auth)
		{
			return $this->responseNoPermission();
		}

		if ($this->isConfirmedPost())
		{
			$disassociate = $this->_input->filter(array(
				'disassociate' => XenForo_Input::STRING,
				'disassociate_confirm' => XenForo_Input::STRING
			));
			if ($disassociate['disassociate'] && $disassociate['disassociate_confirm'])
			{
                Twitter_Helper_Twitter::setUidCookie(0);
				$this->getModelFromCache('XenForo_Model_UserExternal')->deleteExternalAuthAssociation(
					'twitter', $visitor['twitter_auth_id'], $visitor['user_id']
				);

				if (!$auth->hasPassword())
				{
					$this->getModelFromCache('XenForo_Model_UserConfirmation')->resetPassword($visitor['user_id']);
				}
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('account/twitter')
			);
		}
		else
		{
			if ($visitor['twitter_auth_id'])
			{
				$twUser = Twitter_Helper_Twitter::getUserInfo($visitor['twitter_auth_id']);
			}
			else
			{
                $twUser = false;
			}

			$viewParams = array(
				'twUser' => $twUser,
				'hasPassword' => $auth->hasPassword()
			);

			return $this->_getWrapper(
				'account', 'twitter',
				$this->responseView('XenForo_ViewPublic_Account_Twitter', 'account_twitter', $viewParams)
			);
		}
	}
}