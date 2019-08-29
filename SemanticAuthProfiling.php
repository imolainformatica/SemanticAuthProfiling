<?php

/**
 * CustomPermission extension - 
 *
 * @file
 * @ingroup Extensions
 * @author Gabriele Morlini,Giacomo Lorenzo
 * @copyright Â© 2019 Imola Informatica
 * @license Imola Informatica
 */
if (! defined('MEDIAWIKI')) {
    echo ("This file is an extension to the MediaWiki software and cannot be used standalone.\n");
    die(1);
}

$wgExtensionCredits['other'][] = array(
    'path' => __FILE__,
    'name' => 'SelectiveAction',
    'version' => '2.0.1',
    'author' => array(
        'Gabriele Morlini'
    ),
    'descriptionmsg' => 'Gestore dei permessi di amministrazione della singola pagina per le action in base a query'
);

$wgMessagesDirs['CustomPermission'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['CustomPermission'] = dirname(__FILE__) . '/CustomPermission.i18n.php';


$wgHooks['MediaWikiPerformAction'][] = 'onMediaWikiPerformAction'; // controllo permessi
$wgHooks['BeforePageDisplay'][] = 'onBeforePageDisplay';

// eventuale validazione su view
function onUserLoginComplete(User &$user, &$inject_html)
{
    global $wgPageSemanticPropertiesPermission;
    global $wgUser;
    if (function_exists('fromUserToPageAdmin') && is_callable('fromUserToPageAdmin')) {
        // da utente->matricola
        $username = $wgUser->getEmail();

        $_SESSION['pageAdmin'] = fromUserToPageAdmin($username);
    } else {
        wfWarn('SelectiveAction - ' . __METHOD__ . ": per poter funzione la funzione fromUserToPageAdmin($username) deve essere definita, in questo modo nessun utente sarÃ bilitato alla modifica delle pagine", 'log');
    }

    $_SESSION['currentUser'] = $user;
    return true;
}

function init()
{
    global $wgUser;
    // in secondi
    global $wgSelectiveActionResetTime;
    $resetTime = isset($wgSelectiveActionResetTime) ? $wgSelectiveActionResetTime : 3600;
    // wfdebug( 'SelectiveAction - '. __METHOD__ . "resetTime: ".$resetTime." currentTime: ".time()." timestampSession: ".(array_key_exists('pageAdminTimestamp', $_SESSION)?$_SESSION['pageAdminTimestamp']:"-"),'log');
    if (array_key_exists('pageAdmin', $_SESSION) && (time() - $_SESSION['pageAdminTimestamp']) < $resetTime) {
        return $_SESSION['pageAdmin'];
    } else {
        // wfdebug( 'SelectiveAction - '. __METHOD__ . "inizializzazione \n", 'log' );
        $_SESSION['pageAdminTimestamp'] = time();
        if (function_exists('fromUserToPageAdmin') && is_callable('fromUserToPageAdmin')) {
            // da utente->matricola
            $username = $wgUser->getEmail();

            $pageAdmin = fromUserToPageAdmin($username);
            if (! empty($pageAdmin)) {
                $_SESSION['pageAdmin'] = $pageAdmin;
                return $pageAdmin;
            } else {
                $_SESSION['pageAdmin'] = "nessuna-pagina";
                return "nessuna-pagina";
            }
            return $pageAdmin;
        } else {
            wfWarn('SelectiveAction - ' . __METHOD__ . ": per poter funzione la funzione fromUserToPageAdmin($username) deve essere definita, in questo modo nessun utente sarÃ bilitato alla modifica delle pagine", 'log');
            $_SESSION['pageAdmin'] = "nessuna-metodo";
            return "nessuna-metodo";
        }
    }
}

function onMediaWikiPerformAction($output, $article, $title, $user, $request, $wiki)
{

    global $wgPageUserMasterGroup;
    global $wgUser;
    global $wgSelectiveActionEditCategories;
    global $wgdefaultPermissionEdit;
    global $wgSelectiveActionDeleteCategories;

    $currentUserGroups = $wgUser->getEffectiveGroups();
    $username = $wgUser->getName();
    // le pagine di cui sono admin sono tutte html encoded, eseguo quindi la conoversione del nome della pagina
    $page = htmlspecialchars(strtolower($title));
    // singleton style init
    $pageAdmin = strtolower(init());
    // se "nessun-metodo" autodisabilito estensione per sicurezza

    // Get current action
    $action = $wiki->getAction($request);
    wfdebug('SelectiveActionAzione - ' . __METHOD__ . ": azione di " . $action . " su pagina: " . $title . "\n", 'log');
    // --- controllo utenti master
    $groupName = isset($wgPageUserMasterGroup) ? $wgPageUserMasterGroup : 'sysop';
    $groupUser = 'user';
    $masterGroupExists = preg_grep('/.?' . $groupName . '.?/', $currentUserGroups, 0);

    $sysopExists = preg_grep("/.?sysop.?/", $currentUserGroups, 0);
    // sysop sempre può fare
    if (! empty($sysopExists)) {
        return true;
    }
    // poi master del wiki (per esempio architetti)
    if (! empty($masterGroupExists)) {
        return true;
    }

    if (! empty($pageAdmin) && strpos($pageAdmin, $page) !== false) {
        return true;
    }
    if ($action == "delete") {

        foundGroupsAndDoAction($output, $currentUserGroups, $wgSelectiveActionDeleteCategories);
    }

    if ($action == "formedit") {
        foundGroupsAndDoAction($output, $currentUserGroups, $wgSelectiveActionEditCategories);
    }
    if ($action == "edit") {
        $output->showErrorPage('permission-error', 'custompermission-error-message', $wgUser);
        return false;
    }
}

/**
 *
 * @param
 *            output
 * @param
 *            currentUserGroups
 * @param
 *            gruppopPerCategoriaDelete
 * @param
 *            gruppopPerCategoriaDelete
 */
function foundGroupsAndDoAction($output, $currentUserGroups, $wgSelectiveActionCategories)
{
    $gruppopPerCategoria = '';
    foreach ($currentUserGroups as $chiave => $valore) {

        if (isset($wgSelectiveActionCategories[$valore])) {
            $gruppopPerCategoria = $valore;
        }
    }
    $categorie = getCategories($title);
   
    $categorie = str_ireplace("&lt;MANY&gt;Category:Pages using duplicate arguments in template calls", "", $categorie);
    $categorie = str_ireplace("Category:Pages using duplicate arguments in template calls&lt;MANY&gt;", "", $categorie);

    $categorieCompare = explode(":", $categorie);
    $daproteggere = false;
    $semaforo = false;
    doAndCompare($output, $wgSelectiveActionCategories, $gruppopPerCategoria);

    checkWildcard($output, $wgSelectiveActionCategories, $gruppopPerCategoria);
    // se non c'è stato nessun match procedo con la visualizzazione della pagina altrimenti devo fare gli ulteriori controlli sull'utente corrente
    if (! $daproteggere) {
        return true;
    }
}


/**
 * @param output
 * @param wgSelectiveActionCategories
 * @param gruppopPerCategoria
 */

function checkWildcard($output, $wildCardActionCategories, $groupsCheckWildCard)
{
    if (isset($wildCardActionCategories[$groupsCheckWildCard]["*"])) {
        if (! $wildCardActionCategories[$groupsCheckWildCard]["*"]) {
            $output->showErrorPage('permission-error', 'custompermission-error-message', $wgUser);
            return $wildCardActionCategories[$groupsCheckWildCard]["*"];
        } else {
            return $wildCardActionCategories[$groupsCheckWildCard]["*"];
        }
    }}

    function doAndCompare($output, $wgSelectiveDoActionCategories, $groupsDoActionCategories)
{
    foreach (array_keys($wgSelectiveDoActionCategories[$groupsDoActionCategories]) as $i => $categoria) {
        $categoriaConfronto = str_replace("?", "", mb_convert_encoding($categoria, "Windows-1252", "UTF-8"));
        wfdebug('SelectiveActionFormEdit - ' . __METHOD__ . ": " . str_replace("?", "", mb_convert_encoding($categoria, "Windows-1252", "UTF-8")) . " " . utf8_encode($categorieCompare[1]) . "controllo titolo" . str_replace(' ', "pippo", $categoria) . "" . str_replace(' ', "pippo", $categorieCompare[1]) . "832482342 categoria edit " . $categoria . "\n", 'log');
        if (! empty($categorie) && ! empty($categoria) && strcasecmp(trim($categoriaConfronto), trim($categorieCompare[1])) == 0) {

            if ($wgSelectiveDoActionCategories[$groupsDoActionCategories][$categoriaConfronto] === false) {
                wfdebug('SelectiveActionFormEdit - ' . __METHOD__ . $categoriaConfronto . ": prima dell'errore edit categoria ha avuto successo \n", 'log');

                $output->showErrorPage('permission-error', 'custompermission-error-message', $wgUser);
                return $wgSelectiveDoActionCategories[$groupsDoActionCategories][$categoria];
                break;
            } else {
                return $wgSelectiveDoActionCategories[$groupsDoActionCategories][$categoria];
                break;
            }
        }
    }
}

/**
 * Questo metodo viene utilizzato per bloccare anche la semplice visualizzazione di una pagina
 * *
 */
function onBeforePageDisplay(OutputPage &$out, Skin &$skin)
{
    global $wgDisplayPagePermissionBlacklist;
    global $wgTitle;
    global $wgUser;
    global $wgSelectiveActionCreateCategories;
    global $wgSelectiveActionViewCategories;
    global $wgSelectiveActionMoveCategories;
    global $wgPageUserActionPermission;
    global $wgPageUserMasterGroup;
    global $wgdefaultPermissionCreate;
    global $wgdefaultPermissionView;
    global $wgdefaultPermissionMove;
    $categorieView = getCategories($wgTitle);
    $title = $wgTitle->getPartialURL();

    $currentUserGroups = $wgUser->getEffectiveGroups();
    // --- controllo utenti master
    $groupName = isset($wgPageUserMasterGroup) ? $wgPageUserMasterGroup : 'sysop';
    $masterGroupExists = preg_grep('/.?' . $groupName . '.?/', $currentUserGroups, 0);
    $sysopExists = preg_grep("/.?sysop.?/", $currentUserGroups, 0);
    $page = str_ireplace("_", " ", htmlspecialchars(strtolower($title)));
    // singleton style init
    $pageAdmin = strtolower(init());
    if (! empty($pageAdmin) && strpos($pageAdmin, $page) !== false) {
        
        return true;
    }
    if (empty($sysopExists) && empty($masterGroupExists)) {
        $titleArray = explode("/", $title);

        // la creazione tramite modulo non viene gestita come "action" ma come una visualizzazione della pagina
        if (count($titleArray) > 1 && (strpos($wgTitle, str_ireplace(" ", "_", "Special:FormEdit")) !== false)) {

            // TODO verificare perchè arriva con questo formato
            $array_string_converter = function ($value) {
                return mb_convert_encoding($value, 'utf-8', 'ISO-8859-1');
            };

            // meccanismo per fare il check di quale categoria � stata settata nel custom setting $wgSelectiveActionCreateCategories ['IT_User_Global']['Application'] = true;
            $gruppopPerCategoriaCreate = '';
            foreach ($currentUserGroups as $chiaveCreate => $valoreCreate) {
                if (isset($wgSelectiveActionCreateCategories[$valoreCreate])) {
                    $gruppopPerCategoriaCreate = $valoreCreate;
                }
            }
            $categorieCreate = getCategories($titleArray[1]);
            $categorieCompareCreate = explode("/", str_replace("_", " ", $titleArray[1]));
            $categoriaConfrontoCreate = str_replace("?", "", mb_convert_encoding(str_replace("_", " ", $titleArray[1]), "Windows-1252", "UTF-8"));
            foreach (array_keys($wgSelectiveActionCreateCategories[$gruppopPerCategoriaCreate]) as $i => $categoriaCreate2) {
                $categoriaConfrontoCreate2 = str_replace("?", "", mb_convert_encoding(str_replace("_", " ", $categoriaCreate2), "Windows-1252", "UTF-8"));
                wfdebug('SelectiveAction - ' . __METHOD__ . ": " . "controllo titolo create" . $categoriaConfrontoCreate . " categoria create " . $categoriaConfrontoCreate2 . "\n", 'log');
                if (strcasecmp(trim($categoriaConfrontoCreate), trim($categoriaConfrontoCreate2)) == 0) {
                    if ($wgSelectiveActionCreateCategories[$gruppopPerCategoriaCreate][$categoriaCreate2] === false) {

                        wfdebug('SelectiveAction - ' . __METHOD__ . " DEBUG45 categoria pagina decodificata:" . $categoriaCreate2 . " \n", 'log');
                        $out->showErrorPage('permission-error', 'custompermission-error-create-page', $wgUser, $categoriaCreate2);
                        return false;
                    } else {
                        return true;
                    }
                }
            }
            if (isset($wgSelectiveActionCreateCategories[$gruppopPerCategoriaCreate]["*"])) {
                if (! $wgSelectiveActionCreateCategories[$gruppopPerCategoriaCreate]["*"]) {
                    wfdebug('SelectiveAction - ' . __METHOD__ . ": prima dell'errore edit wildcard categoria ha avuto successo \n", 'log');
                    $out->showErrorPage('permission-error', 'custompermission-error-create-page', $wgUser, $categorieCompareCreate);
                    return $wgSelectiveActionCreateCategories[$gruppopPerCategoriaCreate]["*"];
                } else {
                    return $wgSelectiveActionCreateCategories[$gruppopPerCategoriaCreate]["*"];
                }
            } else {

                return ! $wgdefaultPermissionCreate;
            }
        }

        // to do action MOVE
        $preCategoriesMove = getCategories($titleArray[1]);
        $categorieMove = explode(":", $preCategoriesMove);

        if (count($titleArray) > 1 && $titleArray[0] == "MovePage") {
            // thr = '';
            foreach ($currentUserGroups as $chiaveMove => $valoreMove) {
                if (isset($wgSelectiveActionMoveCategories[$valoreMove])) {
                    $gruppopPerCategoriaMove = $valoreMove;
                }
            }
            foreach (array_keys($wgSelectiveActionMoveCategories[$gruppopPerCategoriaMove]) as $i => $categoriaMove) {
                if (! empty($categoriaMove) && strcmp(trim($categoriaMove), trim($categorieMove[1])) == 0) {

                    // //wfdebug('SelectiveAction - contenuto move before if categoria : '. $wgSelectiveActionMoveCategories[$gruppopPerCategoriaMove][$categorieMove] ."\n", 'log');
                    if ($wgSelectiveActionMoveCategories[$gruppopPerCategoriaMove][$categoriaMove] === false) {

                        $out->showErrorPage('permission-error', 'custompermission-error-move-page', $wgUser);
                        return false;
                    } else {

                        return true;
                    }
                }
            }
            if (isset($wgSelectiveActionMoveCategories[$gruppopPerCategoriaMove]["*"])) {
                // //wfdebug('SelectiveAction - prova contenuto if * categoria : '. $categoriaMove ."\n", 'log');
                if (! $wgSelectiveActionMoveCategories[$gruppopPerCategoriaMove]["*"]) {
                    $out->showErrorPage('permission-error', 'custompermission-error-move-page', $wgUser);
                    return false;
                } else {
                    return true;
                }
            } else {

                return $wgdefaultPermissionMove;
            }
        }
        if (strpos($wgTitle, str_ireplace(" ", "_", "Special:FormEdit")) === false) {

            $gruppopPerCategoriaView = '';
            $preCategoriesView = getCategories(urldecode(htmlspecialchars($title)));
            $preCategoriesView = str_replace("&lt;MANY&gt;Category:Pages using duplicate arguments in template calls", "", $preCategoriesView);

            $categorieViewNormal = explode(":", $preCategoriesView);
            foreach ($currentUserGroups as $chiaveView => $valoreView) {
                if (isset($wgSelectiveActionViewCategories[$valoreView])) {
                    $gruppopPerCategoriaView = $valoreView;
                }

                // Check se pagina è nel gruppo di pagine da proteggere
            }

            foreach (array_keys($wgSelectiveActionViewCategories[$gruppopPerCategoriaView]) as $i => $categoriaView) {
                if (! empty($categoriaView) && (strpos($title, str_ireplace(" ", "_", $categoriaView)) !== false)) {
                    // //wfdebug('SelectiveAction - '.$title.'foreach view category attuale :DEBUG IF','log');
                    if (! $wgSelectiveActionViewCategories[$gruppopPerCategoriaView][$categoriaView]) {
                        $out->showErrorPage('permission-error', 'custompermission-error-show-page', $wgUser); // throw new PermissionsError( "", array( $err ) );
                        return $wgSelectiveActionViewCategories[$gruppopPerCategoriaView][$categoriaView];
                    } else {
                        return $wgSelectiveActionViewCategories[$gruppopPerCategoriaView][$categoriaView];
                    }
                }
                // wfdebug('SelectiveAction - '. __METHOD__ .' '. $categoriaView.": valore trovato view: " . $categorieViewNormal[1] ."\n", 'log');
                if (strcmp(trim($categoriaView), trim($categorieViewNormal[1])) == 0 && $categoriaView != "*") {
                    // wfdebug('SelectiveAction - '. __METHOD__ . ": valore trovato view: " . $wgSelectiveActionViewCategories[$gruppopPerCategoriaView][$categoriaView] ."\n", 'log');
                    if (! $wgSelectiveActionViewCategories[$gruppopPerCategoriaView][$categoriaView]) {
                        $out->showErrorPage('permission-error', 'custompermission-error-show-page', $wgUser); // throw new PermissionsError( "", array( $err ) );
                        return $wgSelectiveActionViewCategories[$gruppopPerCategoriaView][$categoriaView];
                    } else {
                        return $wgSelectiveActionViewCategories[$gruppopPerCategoriaView][$categoriaView];
                    }
                }
            }
            if (isset($wgSelectiveActionViewCategories[$gruppopPerCategoriaView]["*"])) {
                if (! $wgSelectiveActionViewCategories[$gruppopPerCategoriaView]["*"]) {
                    $out->showErrorPage('permission-error', 'custompermission-error-show-page', $wgUser);
                    return false;
                } else {

                    return true;
                }
            } else {
                
                return true;
            }
        }

        foreach ($currentUserGroups as $chiave => $valore) {
            if (isset($wgDisplayPagePermissionBlacklist[$valore])) {
                foreach ($wgDisplayPagePermissionBlacklist[$valore] as $chiave2 => $valore2) {
                    if (! empty($valore2) && $valore2 == $title) {
                        $out->showErrorPage('permission-error', 'custompermission-error-show-page', $wgUser); // throw new PermissionsError( "", array( $err ) );
                        return false;
                    } else {
                        return true;
                    }
                }
            }
        }
    }
}

// Returns the category of a selected page
function getCategories($title)
{
    $out = new OutputPage();
    $title = str_ireplace("'", "&apos;", $title);
    $out->addWikiText("{{#ask:[[" . addslashes($title) . "]]|?Category|format=array
			
|link=none
|headers=hide
|searchlabel=… risultati successivi
|titles=hide
|hidegaps=none
|sep=,
|propsep=<PROP>
|manysep=<MANY>
|recordsep=<RCRD>
|headersep=
}}");
    $noParagraphs = str_ireplace("<p>", "", $out->mBodytext);
    $noParagraphs = str_ireplace("</p>", "", $noParagraphs);
    $out->mBodytext = '';
    $noParagraphs = str_ireplace("&lt;MANY&gt;Category:Pages using duplicate arguments in template calls", "", $noParagraphs);
    $noParagraphs = str_ireplace("Category:Pages using duplicate arguments in template calls&lt;MANY&gt;", "", $noParagraphs);
    return $noParagraphs;
}

