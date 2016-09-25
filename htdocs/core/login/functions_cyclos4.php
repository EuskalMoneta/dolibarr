<?php
/*
 * Copyright (C) 2016 Xebax Christy <xebax@wanadoo.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file       htdocs/core/login/functions_cyclos4.php
 * \ingroup    core
 * \brief      Authentication functions for Cyclos 4
 */


/**
 * Check validity of user/password/entity
 * If test is ko, reason must be filled into $_SESSION["dol_loginmesg"]
 *
 * @param	string	$usertotest		Login
 * @param	string	$passwordtotest	Password
 * @param   int		$entitytotest   Number of instance (always 1 if module multicompany not enabled)
 * @return	string					Login if OK, '' if KO
*/
function check_user_password_cyclos4($usertotest, $passwordtotest, $entitytotest)
{
    global $langs;
    global $dolibarr_main_auth_cyclos4_network_url;

    dol_syslog("functions_dolibarr::check_user_password_cyclos4 usertotest=".$usertotest);

    $login = $usertotest;

    // remove trailing slashes before adding one (to ensure there will be only one)
    $url = rtrim($dolibarr_main_auth_cyclos4_network_url, '/').'/web-rpc/login/login';

    $headers = array(
        "Authorization: Basic " . base64_encode($usertotest.':'.$passwordtotest)
    );

    $ch = curl_init($url);
    dol_syslog("functions_dolibarr::check_user_password_cyclos4 url = ".$url, LOG_DEBUG);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);

    $error = '';
    $result = curl_exec($ch);
    dol_syslog("functions_dolibarr::check_user_password_cyclos4 result = ".$result, LOG_DEBUG);

    // Check if any error occurred
    if (curl_errno($ch))
    {
        $error = curl_error($ch);
    }

    // Check HTTP status code
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpcode !== 200)
    {
        $error = "httpcode=".$httpcode." result=".$result;
    }

    if (!empty($error))
    {
        $login = '';
        dol_syslog("functions_dolibarr::check_user_password_cyclos4 Authentification KO for '".$usertotest."' error=".$error);
        $langs->load('errors');
        $_SESSION["dol_loginmesg"] = $langs->trans("ErrorBadLoginPassword");
    }
    else
    {
        dol_syslog("functions_dolibarr::check_user_password_dolibarr Authentification OK for '".$usertotest."'");
    }

    curl_close($ch);

    return $login;
}
