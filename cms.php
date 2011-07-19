<?php

function phorum_mod_cms_page_index()
{
    global $PHORUM;

    // Handle "mark read" clicks.
    //
    // We have to intercept these, because the default markread code will
    // redirect to the plain Phorum index. As a result, this module would
    // redirect the browser to the CMS addon index page.
    if (isset($PHORUM['args'][1]) && $PHORUM['args'][1] === 'markread' &&
        !empty($PHORUM['user']['user_id'])) {
        require_once PHORUM_PATH.'/include/api/newflags.php';
        phorum_api_newflags_markread(
            $PHORUM['forum_id'], PHORUM_MARKREAD_FORUMS
        );
        if (!empty($PHORUM["args"][2])) {
            phorum_api_redirect(
                PHORUM_INDEX_URL, (int)$PHORUM['args'][2], 'showindex'
            );
        } else {
            phorum_api_redirect(PHORUM_INDEX_URL, 'showindex');
        }
    }

    // Handle "mark all read" clicks.
    //
    // We have to intercept these, because the default markread code will
    // redirect to the plain Phorum index. As a result, this module would
    // redirect the browser to the CMS addon index page.
    if (isset($PHORUM['args'][1]) && $PHORUM['args'][1] === 'markallread' &&
        !empty($PHORUM['user']['user_id'])) {
        require_once PHORUM_PATH.'/include/api/newflags.php';
        phorum_api_newflags_markread(
            $PHORUM['vroot'], PHORUM_MARKREAD_VROOTS
        );
        phorum_api_redirect(
            PHORUM_INDEX_URL, (int)$PHORUM['forum_id'], 'showindex'
        );
    }

    // The template URL parameter is not interesting for the following check.
    $template = isset($PHORUM['args']['template'])
              ? $PHORUM['args']['template'] : NULL;
    unset($PHORUM['args']['template']);
    unset($_GET['template']);

    // Decide if we need to redirect to the CMS addon.
    $do_show_custom_index = (
        // No arguments provided at all ...
        empty($PHORUM['args'])         &&
        empty($_GET)                   &&
        empty($_POST)
    ) || (
        // ... or only a vroot id provided ...
        count($PHORUM['args']) === 1   &&
        isset($PHORUM['args'][0])      &&
        is_numeric($PHORUM['args'][0]) &&
        $PHORUM['args'][0] == $PHORUM['vroot']
    );

    // Restore original template query parameter.
    if ($template !== NULL) {
        $PHORUM['args']['template'] = $template;
        $_GET['template'] = $template;
    }

    // Redirect to the CMS addon page if needed.
    if ($do_show_custom_index)
    {
        $page = 'index';
        if (isset($PHORUM['args']['cms'])) $page = $PHORUM['args']['cms'];
        if (isset($_GET['cms']))           $page = $_GET['cms'];
        if (isset($_POST['cms']))          $page = $_POST['cms'];
        $page = urlencode($page);
        phorum_redirect_by_url(
            phorum_get_url(PHORUM_ADDON_URL, 'module=cms', "page=$page")
        );
    }
}

function phorum_mod_cms_start_output()
{
    global $PHORUM;

    // Generate an index URL that points to the forums overview.
    // This is done by adding the vroot id and "showindex" to the URL.
    // When using the index alone or the index with the vroot id,
    // this module would redirect the browser to the CMS pages.
    $PHORUM['DATA']['URL']['INDEX'] =
        phorum_get_url(PHORUM_INDEX_URL, $PHORUM['vroot'], 'showindex'
    );

    // An URL, that can be used to point to the home page.
    // This is the plain index URL, which is picked up by this module
    // to redirect the browser to the CMS addon index page.
    $PHORUM['DATA']['URL']['HOME'] =
        phorum_get_url(PHORUM_INDEX_URL);

    // Inject a "Forums" breadcrumb. We pretend that the forums page
    // is a separate page now, so let's reflect that in the breadcrumbs.
    if (phorum_page !== 'addon' || $PHORUM['args']['module'] != 'cms')
    {
        $do_show = (
            (
                phorum_page != 'pm'       &&
                phorum_page != 'control'  &&
                phorum_page != 'login'    &&
                phorum_page != 'register'
            )
            || $PHORUM['vroot'] !== $PHORUM['forum_id']
        );
        if ($do_show) {
            $breadcrumb = array(
                'URL'  => $PHORUM['DATA']['URL']['INDEX'],
                'TEXT' => $PHORUM['DATA']['LANG']['Forums'],
                'ID'   => $PHORUM['vroot'],
                'TYPE' => 'forums'
            );
            array_splice(
                $PHORUM['DATA']['BREADCRUMBS'], 1, 0,
                array($breadcrumb)
            );
        }
    }
}

function phorum_mod_cms_addon()
{
    global $PHORUM;

    phorum_build_common_urls();

    $page = 'index';

    if (isset($PHORUM['args']['page'])) $page = $PHORUM['args']['page'];
    if (isset($_GET['page']))           $page = $_GET['page'];
    if (isset($_POST['page']))          $page = $_POST['page'];
    $page = basename($page);

    if (!preg_match('/^[\w\.\-\_]+$/', $page)) {
        trigger_error(
            'Illegal character(s) in parameter "page"',
            E_USER_ERROR
        );
    }

    // If we have a controller script for the requested custom page,
    // then include that script now.
    $controller = dirname(__FILE__) . '/controllers/' . $page . '.php';
    if (file_exists($controller)) {
        include($controller);
    }

    // Output the page templates.
    phorum_output("cms::$page");
}

/**
 * This function can be used to generate CMS URLs.
 *
 * @param string $name
 *   The name of the page to generate a link for.
 */ 
function cms_url($page)
{
    $page = urlencode($page);
    return  phorum_api_url(PHORUM_ADDON_URL, "module=cms", "page=$page");
}

/**
 * This function can be used to generate CMS URLs from the templates:
 * <a href="{HOOK "cms_url" "pagename"}">link</a>
 *
 * @param string $name
 *   The name of the page to generate a link for.
 */
function phorum_mod_cms_url($page)
{
    print cms_url($page);
}

/**
 * This function is used to include CMS content blocks in templates.
 * 
 * @param array|string $cms_parameters
 *   The name of the block to load or an array in which the first
 *   element is the name of the block and the rest of the elements are
 *   parameters for the block implementation.
 *   The block code can access the parameters through the $cms_parameters
 *   variable.
 */
function phorum_mod_cms_block($cms_parameters)
{
    global $PHORUM;

    if (is_array($cms_parameters)) {
      $cms_block = array_shift($cms_parameters);
    } else {
      $cms_block = $cms_parameters;
      $cms_parameters = array();
    }

    if (!preg_match('/^\w+$/', $cms_block)) {
        print '[CMS error: illegal block name ' .
              '"' . htmlspecialchars($cms_block) . '": ' .
              'only letters, numbers and underscores are allowed]'; 
    }

    $block_file = dirname(__FILE__) . '/blocks/' . $cms_block . '.php';
    if (file_exists($block_file)) {
        include $block_file; 
    } else {
        print "[CMS error: no block script found for block \"$cms_block\"]";
    }
}

?>
