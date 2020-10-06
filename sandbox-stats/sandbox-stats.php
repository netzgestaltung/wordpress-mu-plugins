<?php
/**
 * Plugin name: sandbox adaptions
 * Plugin URI: https://www.netzgestaltung.at
 * Author: Thomas Fellinger
 * Author URI: https://www.netzgestaltung.at
 * Version: 0.1
 * Description: Custom website functions
 * License: GPL v2
 * Copyright 2020  Thomas Fellinger  (email : office@netzgestaltung.at)

 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
define('SANDBOX_STATS_PATH', plugin_dir_path(__FILE__));
define('SANDBOX_STATS_URL', plugin_dir_url(__FILE__));

/**
 * Plugin setup hook
 * ================
 */
add_action('plugins_loaded', 'sandbox_setup_stats');

function sandbox_setup_stats() {
    // track pageviews and url params
    add_action('wp_footer', 'sandbox_matomo_tracker');

    // track download actions
    // add_action('ddownload_save_success_before', 'sandbox_action_tracker');

    // track form submits
    // cf7 hooks: https://github.com/netzgestaltung/contact-form-7-hooks/
    add_action('wpcf7_submit', 'sandbox_form_submit_tracker', 10, 2);
}

/**
 * Matomo Tracker implementing class MatomoTracker
 * =============================================
 * https://github.com/netzgestaltung/wordpress-snippets/blob/master/matomo-tracker.php
 * Tracks anonymous pageViews on every visit by HTTP Tracking API
 *
 * Usage:
 * Tracks campaigns with the scheme: https://www.domain.tld/?c=<pk_campaign>(-<pk_source>)(-<pk_medum>)(-<pk_keyword>)(-<pk_content>)
 *
 * Installation:
 * Download: https://github.com/matomo-org/matomo-php-tracker
 * save MatomoTracker.php in yourThemes <folderRoot>/includes/matomo-php-tracker/MatomoTracker.php
 * Integrate this file into yourThemes functions.php and rename "sandbox" to your themes name
 *
 * Configuration:
 * Specify $tracker_url, $matomo_site_id and $matomo_user_token
 *
 *
 * License: GNU General Public License v2.0
 */
function sandbox_get_matomo_tracker(){
  include_once(SANDBOX_STATS_PATH . '/config.php');
  include_once(SANDBOX_STATS_PATH . '/MatomoTracker.php');

  MatomoTracker::$URL = SANDBOX_STATS_TRACKER_URL;
  $matomoTracker = new MatomoTracker(SANDBOX_STATS_SITE_ID);

  // Specify an API token with at least Write permission, so the Visitor IP address can be recorded
  // Learn more about token_auth: https://matomo.org/faq/general/faq_114/
  $matomoTracker->setTokenAuth(SANDBOX_STATS_AUTH_TOKEN);

  // You can manually set the visitor details (resolution, time, plugins, etc.)
  // See all other ->set* functions available in the MatomoTracker.php file
  // $matomoTracker->setResolution(1600, 1400);
  // only track anonymous data!
  $matomoTracker->disableCookieSupport();
  $matomoTracker->setIp('0.0.0.0');

  return $matomoTracker;
}

function sandbox_action_tracker($download_id){
  $matomoTracker = sandbox_get_matomo_tracker();
  // requires plugin "alpha-downloads"
  $download_url = get_post_meta($download_id, '_alpha_file_url', true);
  $matomoTracker->doTrackAction($download_url, 'download');
}

// track form submits
// cf7 hooks: https://github.com/netzgestaltung/contact-form-7-hooks/
function sandbox_form_submit_tracker($cf7, $result){
  $matomoTracker = sandbox_get_matomo_tracker();

  // a form has been viewed
  $matomoTracker->doTrackContentImpression('form-' . $cf7->name(), 'Status: ' . $result['status'] . ', Message: ' . $result['message']);

  if ( $result['status'] === 'mail_sent' ) {
    // event for goal tracking
    $matomoTracker->doTrackEvent('form', 'Form submit successful', 'form-' . $cf7->name(), true);
  } else {
    // collect errors as custom variable
    $matomoTracker->setCustomVariable(2, 'Form submit not successful', 'Form-ID: ' . $result['contact_form_id'] . ', Status: ' . $result['status'] . ', Message: ' . $result['message'], 'event');
  }

  // a form has been submitted
  $matomoTracker->doTrackContentInteraction('Form submit', 'form-' . $cf7->name(), 'Status: ' . $result['status'] . ', Message: ' . $result['message']);
}

function sandbox_matomo_tracker(){
  // exclude json calls
  if ( isset($_POST['json']) || isset($_GET['json']) ) {
    return;
  }
  // page url
  $site_url = '';
  $schema = 'https://';
  if ( empty($_SERVER['HTTPS']) or $_SERVER['HTTPS'] === 'off' ) {
    $schema = 'http://';
  }
  $uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2);
  $site_url .= $schema . $_SERVER['SERVER_NAME'] . $uri_parts[0];

  $is_referer = isset($_SERVER['HTTP_REFERER']) ? ( $site_url === $_SERVER['HTTP_REFERER'] ) : false;

  // exclude:
  // - site_url ist referer
  // - admin pages
  // - is outside main query
  // - is wp doing ajax
  // - bot user agents by regex
  if ( !$is_referer && !is_admin() && is_main_query() && !wp_doing_ajax() && !preg_match('/bot|crawl|slurp|spider/i', $_SERVER['HTTP_USER_AGENT']) ) {

    $matomoTracker = sandbox_get_matomo_tracker();
    // page title
    $page_title = wp_get_document_title();

    if ( is_404() ) {
      $page_title = '404 not found, Look for 404 Data at Custom Variables';
      $matomoTracker->setCustomVariable(1, '404', $site_url, 'page');
      $site_url = $schema . $_SERVER['SERVER_NAME'] . '/404';
    }

    // Campaign Tracking
    if ( isset($_GET['c']) ) {

      // Matomo related campain query params
      $campaign_parts = array(
        'pk_campaign',
        'pk_source',
        'pk_medium',
        'pk_kwd',
        'pk_content',
      );

      // get array from query string, delimited by "-"
      $campaign_params = explode('-', $_GET['c'], 5);
      // how much params are given?
      $campaign_param_length = count($campaign_params);
      // reduce the parts set to the number of given params
      $campaign_parts = array_slice($campaign_parts, 0, $campaign_param_length, true);
      // put params and parts together
      $campaign = array_combine($campaign_parts, $campaign_params);
      // create new querystring
      if ( count($campaign) > 0 ) { // if we have campain params
        $site_url .= '?' . http_build_query($campaign);
      }
    }

    // Event tracking
    // ?mtb={category}-{action}-{name}
    // we map short URL IDs to readable Values
    // asking for spezific values is very secure but hard to maintain
    if ( isset($_GET['mtb']) ) {
      $mtb_params = explode('-', $_GET['mtb'], 3);
      $mtb_value = false;
      if ( is_404() ) {
        $mtb_value = true;
      }
      if ( $mtb_params[0] === 'h' ) {
        // Homepage mapping
        $mtb_params[0] = 'Home';
      } else if ( $mtb_params[0] === 'sb' ) {
        // Sidebar Banner mapping
        $mtb_params[0] = 'Sidebar Banner';
      } else if ( $mtb_params[0] === 'hb' ) {
        // Header Button mapping
        $mtb_params[0] = 'Header Button';
      }
      // Type mapping
      if ( $mtb_params[1] === 'b' ) {
        $mtb_params[1] = 'Button';
      } else if ( $mtb_params[1] === 'l' ) {
        $mtb_params[1] = 'Link';
      } else if ( $mtb_params[1] === 't' ) {
        $mtb_params[1] = 'Thumbnail';
      }
      // Content Identifier mapping
      if ( $mtb_params[2] === 'bb' ) {
        $mtb_params[2] = 'Banner button';
      } else if ( $mtb_params[2] === 'bt' ) {
        $mtb_params[2] = 'Banner thumbnail';
      } else if ( $mtb_params[2] === 'db' ) {
        $mtb_params[2] = 'Demo Button';
      }
      // track event
      $matomoTracker->doTrackEvent($mtb_params[0], $mtb_params[1], $mtb_params[2], $mtb_value);
    }

    // Set the url of visited page that we send to matomo
    $matomoTracker->setUrl($site_url);

    // Sends Tracker request via http
    $matomoTracker->doTrackPageView($page_title);
    // You can also track Goal conversions
    // $matomoTracker->doTrackGoal($idGoal = 1, $revenue = 42);
  }
}

?>
