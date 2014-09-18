<?php

  /**
   * Wordpress Welcome Redirect
   * @author Robert Janeson
   * @package Wordpress-Welcome-Redirect
   * @version 2.0.3
   */

  /*
    Plugin Name: Wordpress Welcome Redirect Plugin
    Version: 2.0.2
    Plugin URI: http://latitudemediaz.com/wordpress-welcome-redirect-plugin/
    Description: Redirects your website visitors on their first visit to your landing page or any other special pages of your choice.
    Author: Robert Janeson
    Author URI: http://www.robertjaneson.com/

    Copyright 2014 Robert Janeson (email: Robert@latitudemediaz.com)

    This program is Created By Robert Janeson and it is all his decision to make it free
    or sell.This plugin is use for  redirection of first time visitor.This plugin work on cookie concept.
    This plugin is developed using Advanced Php, javascript & Css.
  */

  class WP_Welcome_Redirect
  {

    // Option name to save to database
    const OPTION_NAME     = 'wpwr-redirects';
    // Cookie options
    const COOKIE_NAME     = 'wpwr-visited';
    const COOKIE_EXPIRES  = 2592000; // 30 days
    // Metabox name
    const META_BOX_NAME   = 'wpwr-url';
    // Set slug
    const OPTIONS_SLUG    = 'wpwr-options';
    const REDIRECT_TYPE    = 'wpwr-type';

    // Set post types
    private $postTypes = array('post', 'page');

    function __construct() {

      $this->initActions();
    }

    /**
     * Get redirects 
     * @return array Redirects
     */
    function getRedirects() {
      // Get option with default []
      $option = get_option(static::OPTION_NAME, '{}');
      // Decode and return
      return json_decode($option, TRUE);
    }

    /**
     * Save redirects
     * @param array $array Array of redirects
     */
    function saveRedirects(array $array) {
      // Save
      update_option(static::OPTION_NAME, json_encode($array));
      // Return
      return $this;
    }

    /**
     * Set a redirect
     * @param int $pageId Page id
     * @param string $url URL
     */
    function setRedirect($pageId, $url) {
      // Get redirects
      $redirects = $this->getRedirects();
      // Trim
      $url = trim($url);
      // If not empty
      if ($url) {
        // Make sure it's a valid url
        if (!$this->isValidUrl($url)) $url = '';
      }
      // Set
      $redirects[$pageId] = $url;
      // Save
      return $this->saveRedirects($redirects);
    }

    /**
     * Get page redirect url
     * @param int $pageId Page id
     * @return string|null Redirect URL or NULL if not set
     */
    function pageRedirectUrl($pageId) {
      // Get redirects
      $redirects = $this->getRedirects();
      // Return
      return isset($redirects[$pageId]) ? $redirects[$pageId] : NULL;
    }

    /**
     * Initialize actions
     */
    function initActions() {

      add_action('add_meta_boxes', array($this, 'registerMetaBox'));
      // Admin menu
      add_action('admin_menu', array($this, 'initOptions'));
      // On save
      add_action('save_post', array($this, 'onSave'));
      // On site initialize
      add_action('wp_head', array($this, 'initSite'));
    }

    /**
     * Initialize options page
     */
    function initOptions() {

      add_options_page('Wordpress Welcome Redirect', 'WP Welcome Redirect', 
                       'manage_options', static::OPTIONS_SLUG, array($this, 'optionsPage'));
    }

    /**
     * Options page
     */
    function optionsPage() {

      if (isset($_POST['submit']) && $_POST['submit'] == 'Clear All Redirects') {
        // Clear
        $this->saveRedirects(array());

      } else {
        // Set options
        $options = array(
          'general-page'=> '*', 
          'blog-page'=> '0'
        );
        foreach ($options as $input=> $pageId) {
          if (isset($_POST[$input])) {
            // Set
            $this->setRedirect($pageId, $_POST[$input]);
          }
        }
        update_option(static::REDIRECT_TYPE, $_POST['redirect-type']);
      }

      // Load cookie class
      $this->cookieClass();
?>
<div class="wrap">
  <h2>Wordpress Welcome Redirect Settings</h2>
  <form method="post" action="options-general.php?page=<?php echo static::OPTIONS_SLUG; ?>">
    <table class="form-table">
      <tbody>
        <tr>
          <th scope="row">
            <label for="general-page">General Page Redirect URL</label>
          </th>
          <td>
            <input name="general-page" type="text" id="general-page" value="<?php echo esc_html($this->pageRedirectUrl('*')); ?>" class="regular-text" placeholder="e.g. http://lp.domain.com" />
            <p class="description">When a visitor visits any page without specific redirect URL, this will be the default redirect URL</p>
            <p class="description">Leave this option empty if not applicable</p>
          </td>
        </tr>
        <tr>
          <th scope="row">
            <label for="blog-page">Blog Page Redirect URL</label>
          </th>
          <td>
            <input name="blog-page" type="text" id="blog-page" value="<?php echo esc_html($this->pageRedirectUrl('0')); ?>" class="regular-text" placeholder="e.g. http://lp.domain.com" />
            <p class="description">When a visitor visits the blog page, this will be the redirect URL to use</p>
            <p class="description">Leave this option empty if not applicable</p>
          </td>
        </tr>
        <tr>
          <th scope="row">
            <label for="redirects-page">Where do you want to apply redirects?</label>
          </th>
          <td>
            <select name="redirect-type" id="redirect-type" class="regular-text">
              <option value="website">Entire Website</option>
              <option value="home">Home Page Only</option>
              <option value="pages">Pages Only</option>
              <option value="posts">Posts Only</option>
            </select>
            <p class="description">The redirects will work only on the pages or posts you choose here</p>
          </td>
        </tr>
      </tbody>
    </table>
    <p class="submit">
      <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"  />
      <input onclick="return confirm('This will clear all redirects in all pages including those in specific pages and blog posts\n\nPress OK to proceed');" type="submit" name="submit" id="submit" class="button" value="Clear All Redirects"  />
      <input onclick="if (confirm('This will clear all cookies for visited pages\n\nPress OK to proceed')){ wpwr_cookie.set('<?php echo static::COOKIE_NAME; ?>', '', -1); alert('Cookies cleared'); }" type="button" class="button" value="Clear Cookies"  />
    </p>
  </form>
</div>
<?php
    }

    /**
     * Initialize all pages
     */
    function initSite() {

      if (!is_admin()) {
        // Get page id
        $pageId = $this->getPageId();
        // Page url
        $redirectUrl = $this->pageRedirectUrl($pageId);
        // If no redirect url
        if (!$redirectUrl) {
          // If there's general url
          $generalUrl = $this->pageRedirectUrl('*');
          // Set
          if ($generalUrl) $redirectUrl = $generalUrl;
        }

        // Load cookie class
        $this->cookieClass();
?>
<script type="text/javascript">
  var wpwr_app = {
    cookieName: '<?php echo addslashes(static::COOKIE_NAME); ?>', 
    cookieExpires: <?php echo static::COOKIE_EXPIRES; ?>,
    pageId: '<?php echo $pageId; ?>',
    redirectUrl: '<?php echo addslashes($redirectUrl); ?>',
    pagesVisited: function() {
      var visited = wpwr_cookie.get(this.cookieName);
      return visited ? visited.split(',') : [];
    },
    visited: function() {
      return (this.pagesVisited().indexOf(this.pageId) >= 0);
    },
    visit: function() {
      if (this.visited()) return this;
      var visited = this.pagesVisited();
      visited[visited.length] = this.pageId;
      wpwr_cookie.set(this.cookieName, visited.join(','), this.cookieExpires);
      return this;
    },
    init: function() {
      /*if (typeof(wpwr_cookie.get(this.cookieName)) === 'undefined') {
        this.visit();
              if (this.redirectUrl) window.location = this.redirectUrl;*/

        <?php
        switch (get_option( static::REDIRECT_TYPE, 'website' )) {
          case 'website':
            ?>
              if (typeof(wpwr_cookie.get(this.cookieName)) === 'undefined') {
                this.visit();
                if (this.redirectUrl) window.location = this.redirectUrl;
              }
            <?php
            break;
          case 'home':
            if (is_home()) { ?>
              if (typeof(wpwr_cookie.get(this.cookieName)) === 'undefined') {
                this.visit();
                if (this.redirectUrl) window.location = this.redirectUrl;
              }
            <?php }
            break;
          case 'pages':
            //exit(var_dump(is_page()));
            if (is_page()) { ?>
              if (typeof(wpwr_cookie.get(this.cookieName)) === 'undefined') {
                this.visit();
                if (this.redirectUrl) window.location = this.redirectUrl;
              }
            <?php }
            break;
          case 'posts':
            if (is_single()) { ?>
              if (typeof(wpwr_cookie.get(this.cookieName)) === 'undefined') {
                this.visit();
                if (this.redirectUrl) window.location = this.redirectUrl;
              }
            <?php }
            break;
          
          default:
            break;
        }
        ?>
      /*}*/
    }
  };
  wpwr_app.init();
</script>
<?php
      }
    }

    /**
     * JS Cookie class
     */
    function cookieClass() {
?>

<script type="text/javascript">
  var wpwr_cookie = {
    all: {},
    init: function() {
      var arrCookies = document.cookie.split(';');
      for (var i in arrCookies) {
        var arrValue = arrCookies[i].split('=');
        this.all[arrValue[0].trim()]=this.decode(arrValue[1].trim());
      }
    },
    set: function(name, value, expires) {
      var cookie = [name+'='+this.encode(value)];
      if (expires) {
        var d = new Date();
        d.setTime(d.getTime() + (expires * 1000));
        cookie[cookie.length] = 'expires=' + d.toGMTString();
      }
      cookie[cookie.length] = 'path=/';
      document.cookie = cookie.join('; ');
      return this;
    },
    get: function(name) {
      return this.all[name];
    },
    encode: function(str) {
      return encodeURIComponent((str + '').toString())
        .replace(/!/g, '%21').replace(/'/g, '%27').replace(/\(/g, '%28')
        .replace(/\)/g, '%29').replace(/\*/g, '%2A').replace(/%20/g, '+');
    },
    decode: function(str) {
      return decodeURIComponent((str + '')
        .replace(/%(?![\da-f]{2})/gi, function() { return '%25'; })
        .replace(/\+/g, '%20'));
    }
  };
  wpwr_cookie.init();
</script>
<?php
    }

    function registerMetaBox() {
      // Loop through each post type
      foreach ($this->postTypes as $postType) {
        // Add meta box
        add_meta_box('wpwr-redirect', 'Wordpress Welcome Redirect URL', array($this, 'showMetaBox'), $postType, 'advanced', 'high');
      }
    }

    function showMetaBox() {
      // Get current url
      $url = $this->pageRedirectUrl(get_the_ID());
      ?>
      <input type="text" name="<?php echo static::META_BOX_NAME; ?>" value="<?php echo esc_html($url); ?>" placeholder="Insert redirect URL here (e.g. http://lp.domain.com/)" style="width: 100%" />
      <br />
      <p><em>If redirection is not applicable, leave as blank</em></p>
      <?php
    }

    /**
     * Check if post save is on autosave
     * @return bool True if autosave
     */
    function isAutosave() {
      // Check for autosave constant
      return defined('DOING_AUTOSAVE') && DOING_AUTOSAVE;
    }

    /**
     * Get current page id
     */
    function getPageId() {

      if (is_single()) {
        // Return id immediately (this refers to a single blog post)
        return get_the_ID();
      }

      if (!is_single() && is_page()) {
        // Return id (this refers to a page)
        return get_the_ID();
      }

      // If blog page
      if (is_home()) {
        // Return 0
        return 0;
      }
      // Return (refers to any page)
      return '*';
    }

    function onSave() {
      // If autosave, exit immediately
      if ($this->isAutosave()) return $this;
      // If invalid post type, exit immediately
      if (!isset($_POST['post_type']) || !in_array($_POST['post_type'], $this->postTypes)) return $this;
      // Set post id
      $pageId = isset($_POST['post_ID']) ? intval($_POST['post_ID']) : 0;
      // If no post id, exit
      if (!$pageId) return $this;
      // Get redirect url
      $redirectUrl = isset($_POST[static::META_BOX_NAME]) ? trim($_POST[static::META_BOX_NAME]) : '';
      // Set redirect
      return $this->setRedirect($pageId, $redirectUrl);
    }

    /**
     * Check if valid url
     * @param string $url URL
     * @return bool True if valid url
     */
    function isValidUrl($url) {
      // Return 
      return preg_match('/(http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/', $url);
    }

  }

  // Instantiate plugin
  $wpwr = new WP_Welcome_Redirect();