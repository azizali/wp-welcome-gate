<?php

  /**
   * Wordpress Welcome Redirect
   * @author Robert Janeson
   * @package Wordpress-Welcome-Redirect
   * @version 2.0.4
   */

  /*
    Plugin Name: Wordpress Welcome Redirect Plugin
    Version: 2.0.4
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
      // Settings link Plugins Page
      add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this, 'wpwr_action_links') );
      // CSS of Social Media boxes at the bottom
      function my_enqueue($hook) {
          if ( 'settings_page_wpwr-options' != $hook ) {
              return;
          }
          wp_enqueue_style('wpwr-social-box', plugin_dir_url(__FILE__)."/css/bootstrap.min.css" );
          wp_enqueue_style('wpwr-social-box', "http://fonts.googleapis.com/css?family=Noto+Sans:400,700" );
          wp_enqueue_script('wpwr-social-box', plugin_dir_url(__FILE__)."/js/wp-welcome-gate.js" );
      }
      add_action( 'admin_enqueue_scripts', 'my_enqueue' );
    }

    /**
     * Initialize options page
     */
    function initOptions() {

      add_options_page('Wordpress Welcome Redirect', 'WP Welcome Redirect', 
                       'manage_options', static::OPTIONS_SLUG, array($this, 'optionsPage'));
    }


    function wpwr_action_links( $links ) {
       $settings_link1 = '<a href="'. get_admin_url(null, 'options-general.php?page=wpwr-options') .'">Settings</a>';
       array_unshift( $links, $settings_link1 );
       return $links;
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
            <label for="general-page">Redirect URL</label>
          </th>
          <td>
            <input name="general-page" type="text" id="general-page" value="<?php echo esc_html($this->pageRedirectUrl('*')); ?>" class="regular-text" placeholder="e.g. http://lp.domain.com" />
            <p class="description">When a visitor visits any page without specific redirect URL, this will be the default redirect URL</p>
            <p class="description">Leave this option empty if not applicable</p>
          </td>
        </tr>
        <tr>
          <th scope="row">
            <label for="redirects-page">Where do you want to apply redirects?</label>
          </th>
          <td>
            <select name="redirect-type" id="redirect-type" class="regular-text">
              <option value="website" <?php echo ( get_option(static::REDIRECT_TYPE) == 'website' ) ? 'selected = "selected"' : ''; ?> >Entire Website</option>
              <option value="home" <?php echo ( get_option(static::REDIRECT_TYPE) == 'home' ) ? 'selected = "selected"' : ''; ?> >Home Page Only</option>
              <option value="pages" <?php echo ( get_option(static::REDIRECT_TYPE) == 'pages' ) ? 'selected = "selected"' : ''; ?> >Pages Only</option>
              <option value="posts" <?php echo ( get_option(static::REDIRECT_TYPE) == 'posts' ) ? 'selected = "selected"' : ''; ?> >Posts Only</option>
            </select>
            <p class="description">Redirection will affect the part that you choose. The Entire Website, Home Page Only, Pages Only or Posts Only.</p>
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
<hr />
<div class="mt-social-boxes" id="toBeZoomedOut">
  <div class="container bg-top gradient-bg">
  <div class="logo-bg"><a href="http://latitudemediaz.com"><img src="<?php echo plugin_dir_url(__FILE__); ?>/images/latitudemediaz-logo.png"/></a></div>
  <div class="row">
      <div class="col-md-3">
      <h4 class="text-center text-outline blue-text"><strong><em>Help Keep This Plugin 
          Free Send Your Donation</em></strong></h4>
      </div>
      <div class="col-md-3">
      <h4 class="text-center text-outline orange-text"><strong><em>Loved It?<br>
Leave a review</em></strong></h4>
      </div>
      <div class="col-md-3">
      <h4 class="text-center text-outline gray-text"><strong><em>Need Help?<br>
View Support Forum</em></strong></h4>
      </div>
      <div class="col-md-3">
      <h4 class="text-center text-outline light-blue-text"><strong><em>Get Soical<br>
With Us</em></strong></h4>
      </div>
    </div>
    <div class="row">
      <div class="col-md-3 box-bg">
      <div class="col-md-12 top-padding"><img src="<?php echo plugin_dir_url(__FILE__); ?>/images/papypal-logo.png"></div>
      <div class="col-md-12 top-padding text-center">
      <div class="col-md-12 text-center padding-overall"><img src="<?php echo plugin_dir_url(__FILE__); ?>/images/paypal-verified.png"/></div>      
      <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top"><input name="cmd" type="hidden" value="_s-xclick" />
      <input name="hosted_button_id" type="hidden" value="MKD6RXA3D8VML" />
      <input alt="PayPal - The safer, easier way to pay online!" name="submit" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" type="image" />
      <img src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" alt="" width="1" height="1" border="0" />
      </form>
      </div>
      </div>
      
      <div class="col-md-3 box-bg">
      <div class="col-md-12 text-center top-padding bg-img">
      <div class="review-bg"><img src="<?php echo plugin_dir_url(__FILE__); ?>/images/5-star.png"/></div>
      </div>
      <div class="col-md-12 text-center">
      <a href="https://wordpress.org/support/view/plugin-reviews/wp-welcome-gate" class="btn-main">
          <span class="btn-main-image"><span></span></span>
                      <span class="btn-main-text">Please
                      <h4 class="margin-none white-text">Leave Review</h4>
      </span> 
    </a>
      </div>
      </div>
      
      <div class="col-md-3 box-bg">
      <div class="col-md-12 text-center top-padding wp-support"></div>
       <div class="col-md-12 text-center">
      <a href="https://wordpress.org/support/plugin/wp-welcome-gate" class="btn-main02">
          <span class="btn-main-image02"><span></span></span>
                      <span class="btn-main-text02">
                      <h4 class="margin-none white-text">Need Help?</h4>
                      Contact Us Here </span> 
    </a>
      </div>
      
      </div>
      <div class="col-md-3 box-bg text-center padding-top-bottom">
      <div class="col-md-6 padding-allover"><a href="https://www.facebook.com/Latitudemediaz" target="_blank"><img src="<?php echo plugin_dir_url(__FILE__); ?>/images/fb.png"/></a></div>
      <div class="col-md-6 padding-allover"><a href="https://twitter.com/latitudemediaz" target="_blank"><img src="<?php echo plugin_dir_url(__FILE__); ?>/images/twitter.png"/></a></div>
      <div class="col-md-6 padding-allover"><a href="https://plus.google.com/+LatitudeMediazNairobi" target="_blank"><img src="<?php echo plugin_dir_url(__FILE__); ?>/images/google+.png"/></a></div>
      <div class="col-md-6 padding-allover"><a href="https://www.youtube.com/user/latitudemediaz" target="_blank"><img src="<?php echo plugin_dir_url(__FILE__); ?>/images/youtube.png"/></a></div>
      </div>
    </div>
  </div>
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