<?php

Class Twitter_Listener_Listener
{
    public static function loadClassController($class, &$extend)
   	{
   		if ($class == 'XenForo_ControllerPublic_Register'){
   			$extend[] = 'Twitter_ControllerPublic_Register';
   		}

        if ($class == 'XenForo_ControllerPublic_Account'){
           $extend[] = 'Twitter_ControllerPublic_Account';
        }
   	}
}

