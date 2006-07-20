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

// main library routines and defaults
require_once("./config/dmsDefaults.php");

/**
 * $Id$
 *
 * Controller page -- controls the web application by responding to a set of
 * defined actions.  The controller performs session handling, page-level
 * authentication and forwards the request to the appropriate handling
 * page.
 *
 * Licensed under the GNU GPL. For full terms see the file DOCS/COPYING.
 *
 * @version $Revision$
 * @author Michael Joseph <michael@jamwarehouse.com>, Jam Warehouse (Pty) Ltd, South Africa
 * @package controller
 */

// -------------------------------
// page start
// -------------------------------

$action = $_REQUEST['action'];

if ($action != "login") {

    // check the session, but don't redirect if the check fails
    $ret = checkSessionAndRedirect(false);
    if ($ret === true) {
        //get around the problem with search
        if (strcmp($_REQUEST['fForStandardSearch'], "yes") == 0) {
            $action = "standardSearch";
        } else if (!isset($action)) {
        // session check succeeds, so default action should be the dashboard if no action was specified
            $action = "dashboard";
        }
    } else {
        // session check fails, so default action should be the login form if no action was specified
        $oKTConfig = KTConfig::getSingleton();
        $dest = 'login';
        if ($oKTConfig->get('allowAnonymousLogin', false)) { $dest = 'dashboard'; }
            
        if (!isset($action)) {
            $action = $dest;
        } elseif ($action <> $dest) {
            // we have a controller link and auth has failed, so redirect to the login page
            // with the controller link as the redirect
            $url = generateControllerUrl("login");
            $redirect = urlencode($_SERVER[PHP_SELF] . "?" . $_SERVER['QUERY_STRING']);
            if ((strlen($redirect) > 1)) {
                $url = $url . "&redirect=" . $redirect;
            }
            if (PEAR::isError($ret)) {
                $url = $url . "&errorMessage=" .  urlencode($ret->getMessage());
            }
            redirect($url);
            exit(0);
        }
    }
}

// we appear to have some encoding/decoding issues, so we need to force-check for %30 type situations
$queryString = KTUtil::arrayGet($_REQUEST, 'qs', '');
if (is_array($queryString)) {
    $aStrings = array();
    foreach ($queryString as $k => $v) {
        $aStrings[] = $k . '=' . $v;
    }
    $queryString = join('&', $aStrings);
} elseif (count(preg_match('#\%#', $queryString) != 0)) {
    $queryString = urldecode($queryString);
}

if (empty($queryString)) {
    // need to strip query string params from action before attempting to retrieve from sitemap
    $queryString = "";
    // check for the presence of additional params
    if (strstr($_SERVER["QUERY_STRING"], "&")) {
        // strip and save the querystring
        $queryString = substr($_SERVER["QUERY_STRING"], strpos($_SERVER["QUERY_STRING"], "&")+1, strlen($_SERVER["QUERY_STRING"]));
    } else if (strstr($_SERVER["QUERY_STRING"], "?")) {
        // strip and save the querystring
        $queryString = substr($_SERVER["QUERY_STRING"], strpos($_SERVER["QUERY_STRING"], "?")+1, strlen($_SERVER["QUERY_STRING"]));
        // update
        $action = substr($_SERVER["QUERY_STRING"], 0, strpos($_SERVER["QUERY_STRING"], "?"));
    }
}

// retrieve the page from the sitemap (checks whether this user has access to the requested page)
$page = $default->siteMap->getPage($action, isset($_SESSION["userID"]) ? $_SESSION["userID"] : "");

if (!$page) {
    // this user doesn't have permission to access the page
    // or there is no page mapping for the requested action
    // redirect to no permission page
    $default->log->error("control.php getPage failed for ($action, " . $_SESSION["userID"] . ")");
    redirect("$default->uiUrl/noAccess.php");
} else {
    $page = $default->rootUrl . $page;
    // set authorised flag and redirect
    // strip querystring from the page returned from the sitemap
    // before setting page authorisation flag (since checkSession checks page level
    // access by checking $_SESSION["pageAccess"][$_SERVER["PHP_SELF"] ie. without querystring(?)
    if (strstr($page, "?")) {
        $accessPage = substr($page, 0, strpos($page, "?"));
    } else {
        $accessPage = $page;
    }
    $_SESSION["pageAccess"][$accessPage] = true;
    // if we have a querystring add it on
    if (strlen($queryString) > 0) {
        $page = $page . (strstr($page, "?") ? "&$queryString" : "?$queryString");
        $default->log->info("control.php: about to redirect to $page");
    }
    redirect($page);
}

?>
