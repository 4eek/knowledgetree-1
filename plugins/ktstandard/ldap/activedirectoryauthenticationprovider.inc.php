<?php

/**
 * $Id$
 *
 * Copyright (c) 2006 Jam Warehouse http://www.jamwarehouse.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; using version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * -------------------------------------------------------------------------
 *
 * You can contact the copyright owner regarding licensing via the contact
 * details that can be found on the KnowledgeTree web site:
 *
 *         http://www.ktdms.com/
 */

require_once(KT_LIB_DIR . '/authentication/authenticationprovider.inc.php');
require_once(KT_LIB_DIR . '/authentication/Authenticator.inc');
require_once("ldapbaseauthenticationprovider.inc.php");

class KTActiveDirectoryAuthenticationProvider extends KTLDAPBaseAuthenticationProvider {
    var $sNamespace = "ktstandard.authentication.adprovider";      

    var $bGroupSource = true;

    var $sAuthenticatorClass = "KTActiveDirectoryAuthenticator";
    var $aAttributes = array ("cn", "samaccountname", "givenname", "sn", "userprincipalname", "telephonenumber");

    function KTActiveDirectoryAuthenticationProvider() {
        $this->sName = _kt("ActiveDirectory authentication provider");
        parent::KTLDAPBaseAuthenticationProvider();
    }
}

class KTActiveDirectoryAuthenticator extends KTLDAPBaseAuthenticator {
    var $aAttributes = array ("cn", "samaccountname", "givenname", "sn", "userprincipalname", "telephonenumber");
}

