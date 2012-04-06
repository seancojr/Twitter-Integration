<?php

/**
 * Helper for Twitter integration.
 *
 * @package Twitter_Twitter
 */
class Twitter_Helper_Twitter
{
	public static function getUserInfo($uid)
	{
        $client = XenForo_Helper_Http::getClient('https://api.twitter.com/1/users/show.json');
        $client->setParameterGet('user_id', $uid);
        //$client->setParameterGet('sizes', 'original');
        $response = $client->request('GET');
        $responseArray = json_decode($response->getBody(), true);

        return $responseArray;
	}

	public static function getUserPicture($uid)
	{
        $client = XenForo_Helper_Http::getClient('https://api.twitter.com/1/users/profile_image');
        $client->setParameterGet('user_id', $uid);
        $client->setParameterGet('size', 'original');
        $response = $client->request('GET');
        $avatarData = $response->getBody();

        return $avatarData;
	}

	/**
	 * Sets the twUid cookie that is used by the JavaScript.
	 *
	 * @param integer $twUid 64-bit int of TW user ID
	 */
	public static function setUidCookie($twUid)
	{
		XenForo_Helper_Cookie::setCookie('twUid', $twUid, 14 * 86400);
	}
}