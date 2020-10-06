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

define('SANDBOX_ADAPTIONS_PATH', plugin_dir_path(__FILE__));
define('SANDBOX_ADAPTIONS_URL', plugin_dir_url(__FILE__));

/**
 * Ajax hooks
 * ==========
 */
// user edit ajax
add_action('wp_ajax_user_edit', 'sandbox_ajax_user_edit');

/**
 * Plugin setup hook
 * ================
 */
add_action('plugins_loaded', 'sandbox_setup_site');


function sandbox_setup_site() {
  add_post_type_support('page', 'excerpt');

  // init image sizes
  sandbox_image_sizes();

  // init custom widgets
  add_action('widgets_init', 'sandbox_widgets_init');
  add_action('init', 'sandbox_disable_emojis');

  // load admin styles
  add_action('admin_head', 'sandbox_admin_styles_load');

  // quick fix for https://wordpress.org/support/topic/jqeuery-error-live-is-not-a-function/
  add_action('admin_enqueue_scripts', 'sandbox_jcf_admin_fix');

  if ( !is_admin() ) {
    // add usefull classNames
    add_filter('body_class', 'sandbox_body_class');
    add_filter('post_class', 'sandbox_post_class');
    add_filter('nav_menu_css_class', 'sandbox_menu_item_class', 10, 2);
    add_filter('the_category', 'sandbox_category_class');

    // adds login and out links to menus "site-loggedin" and "site-loggedout"
    add_filter('wp_nav_menu_items', 'sandbox_nav_menu_items', 10, 2);

    // Custom login screen
    add_filter('login_headerurl', 'sandbox_login_headerurl');
    add_filter('login_headertext', 'sandbox_login_headertext');
    add_filter('login_enqueue_scripts', 'sandbox_login_enqueue_scripts');

    // Change Wordpress email subject
    add_filter('wp_mail_from_name', 'sandbox_mail_from_name');

    // remove lazy block wrapper container markup in frontend
    add_filter( 'lzb/block_render/allow_wrapper', '__return_false' );

  // Admin pages related stuff
  } else {
    // add usefull classNames
    add_filter('admin_body_class', 'sandbox_admin_body_class');
  }

  // plugin integrations
  add_filter('wpcf7_form_tag_data_option', 'sandbox_listo_ordered', 11, 1);
  // listo uses an iso3 country list
  // country iso2 to iso3:
  // https://github.com/i-rocky/country-list-js/blob/master/data/iso_alpha_3.json
  // all countries and phone-codes (iso2):
  // https://github.com/ChromaticHQ/intl-tel-input/blob/master/src/js/data.js

  // filter values of form tags to email
  add_filter( 'cf7sg_mailtag_email-updates', 'sandbox_cf7_mailtag_email_updates', 10, 3);

  // remove layout from "cf7 smart grid plugin"
  add_action('smart_grid_register_styles', 'sandbox_smart_grid_register_styles');


  // use really simple captcha in contact form 7
  // add_filter('wpcf7_use_really_simple_captcha', '__return_true');

}

// Check if function "wp_body_open" exits, if not create it
if ( !function_exists('wp_body_open') ) {
  function wp_body_open() {
    do_action('wp_body_open');
  }
}

// add custom image sizes
function sandbox_image_sizes() {
  $image_sizes = sandbox_get_image_sizes();

  if ( !empty( $image_sizes ) ) {
    foreach ( $image_sizes as $id => $size ) {
      add_image_size( $id, $size['args']['w'], $size['args']['h'], $size['args']['crop'] );
    }
  }
}

// custom image sizes settings
function sandbox_get_image_sizes() {

  $sizes = array();
  $crop = true;
  $ratio = '16_9';
  $ratio_opts = explode("_", $ratio);

  // Standard (with sidebar)
  $width = 805;
  $height = absint($width*$ratio_opts[1]/$ratio_opts[0]);
  $sizes['sandbox-default'] = array('title' => __('Standard (with sidebar)', 'sandbox'), 'args' => array('w' => $width, 'h' => $height, 'crop' => $crop));

  //Full width (no sidebar)
  $width = 1090;
  $height = absint($width*$ratio_opts[1]/$ratio_opts[0]);
  $sizes['sandbox-wide'] = array('title' => __('Full Width (no sidebar)', 'sandbox'), 'args' => array('w' => $width, 'h' => $height, 'crop' => $crop));

  //Hero image
  $width = 1920;
  $height = absint($width*$ratio_opts[1]/$ratio_opts[0]);
  $sizes['sandbox-hero'] = array('title' => __('Hero image', 'sandbox'), 'args' => array('w' => $width, 'h' => $height, 'crop' => $crop));

  return $sizes;
}

// Custom login screen - home url
function sandbox_login_headerurl(){
  return home_url();
}

// Custom login screen - header text
function sandbox_login_headertext(){
  return wp_get_document_title();
}

// Custom login screen - login style
function sandbox_login_enqueue_scripts(){
  // loads style/images from template folder
  wp_register_style('sandbox_login', get_template_directory_uri() . '/css/login.css');
  wp_enqueue_style('sandbox_login');
}

// init custom widgets
function sandbox_widgets_init(){;
  register_widget('sandbox_faq_nav_widget');
  // register_widget('sandbox_events_widget');
  // register_widget('sandbox_social_widget');
  // register_widget('sandbox_slider_widget');
}

/**
 * Disable the emoji's
 * https://kinsta.com/knowledgebase/disable-emojis-wordpress/
 */
function sandbox_disable_emojis(){
  remove_action('wp_head', 'print_emoji_detection_script', 7 );
  remove_action('admin_print_scripts', 'print_emoji_detection_script');
  remove_action('wp_print_styles', 'print_emoji_styles');
  remove_action('admin_print_styles', 'print_emoji_styles');
  remove_filter('the_content_feed', 'wp_staticize_emoji');
  remove_filter('comment_text_rss', 'wp_staticize_emoji');
  remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
  add_filter('tiny_mce_plugins', 'disable_emojis_tinymce');
  add_filter('wp_resource_hints', 'disable_emojis_remove_dns_prefetch', 10, 2 );
}

/**
 * Filter function used to remove the tinymce emoji plugin.
 *
 * @param array $plugins
 * @return array Difference betwen the two arrays
 */
function disable_emojis_tinymce($plugins) {
  if ( is_array($plugins) ) {
    return array_diff($plugins, array('wpemoji'));
  } else {
    return array();
  }
}

/**
 * Remove emoji CDN hostname from DNS prefetching hints.
 *
 * @param array $urls URLs to print for resource hints.
 * @param string $relation_type The relation type the URLs are printed for.
 * @return array Difference betwen the two arrays.
 */
function disable_emojis_remove_dns_prefetch( $urls, $relation_type ) {
  if ( 'dns-prefetch' == $relation_type ) {
    /** This filter is documented in wp-includes/formatting.php */
    $emoji_svg_url = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/');
    $urls = array_diff( $urls, array( $emoji_svg_url ) );
  }
  return $urls;
}

// adds usefull body classNames
// @param  $classes  array
function sandbox_body_class($classes){
  $classNames = array();
  $post = get_post();
  $parents = get_post_ancestors($post);
  $slug = sandbox_get_the_slug();
  $post_type = get_post_type();

  $classNames[] = $post_type . '-' . $slug;

  if ( is_home() ) {
    $classNames[] = 'archive';
  }
  if ( is_active_sidebar('content-before') && is_active_sidebar('content-after') ) {
    $classNames[] = 'has-content-sidebars';
  } else if ( is_active_sidebar('content-before') || is_active_sidebar('content-after') ) {
    $classNames[] = 'has-content-sidebar';
    if ( is_active_sidebar('content-before') ) {
      $classNames[] = 'has-sidebar-content-before';
    } else if ( is_active_sidebar('content-after') ) {
      $classNames[] = 'has-sidebar-content-after';
    }
  }
  /* use for parent pages as sections
  if ( !empty($parents) ) {
    $parents[] = $post->ID;
    foreach ( $parents as $parentID ) {
      if ( $parentID === 8 ) {
        $classNames[] = 'section-you-name-it';
      }
    }
  }
  */
  // echo var_dump($classes);
  return array_merge( $classes, $classNames );
}

// add usefull classNames
// @param  $classes  string
function sandbox_admin_body_class($classes){
  $classNames = '';
  $screen = get_current_screen();

  if ( $screen->base === 'post' && $screen->post_type === 'page' ) {
    $page_template = get_post_meta(get_the_ID(), '_wp_page_template', true);
    $classNames .= ' used-template-' . esc_attr(str_replace('.php', '', $page_template));
  }
  return $classes . ' ' . $classNames;
}

// add custom styles for admin section
function sandbox_admin_styles_load(){
  wp_register_style('sandbox_admin', SANDBOX_ADAPTIONS_URL . '/admin/style.css');
  wp_enqueue_style('sandbox_admin');
}

function sandbox_jcf_admin_fix(){
  $screen = get_current_screen();
  if ( in_array($screen->id, array('settings_page_jcf_admin', 'settings_page_jcf_fieldset_index')) ) {
    wp_enqueue_script('jquery-migrate');
  }
}

// adds usefull post classNames
function sandbox_post_class($classes){
  $classNames = array();
  if ( !has_post_thumbnail() ) {
    $classNames[] = 'no-post-thumbnail';
  }
  return array_merge($classes, $classNames);
}

// adds usefull menu-item classNames
function sandbox_menu_item_class($classes, $item){

  // Add slugs to menu-items
  if ('category' == $item->object ) {
    $category = get_category( $item->object_id );
    $classes[] = 'category-' . $category->slug;
  } else if ('format' == $item->object ){
    $format = get_term($item->object_id);
    $classes[] = 'format-' . $format->slug;
  }
  return $classes;
}

// adds usefull category classNames
function sandbox_category_class($thelist){
  $categories = get_the_category();

  if ( !$categories || is_wp_error($categories) ) {
    return $thelist;
  }
  $output = '<ul class="post-categories">';
  foreach ( $categories as $category ) {
    $output .= '<li class="category-' . $category->slug . '"><a href="' . esc_url(get_category_link($category->term_id)) . '">' . $category->name . '</a></li>';
  }
  $output .= '</ul>';
  return $output;
}

// adds login and out links to menus "site-loggedin" and "site-loggedout"
function sandbox_nav_menu_items($items, $args){
  if ( is_user_logged_in() ) {
    if ( $args->menu->slug === 'site-loggedin' ) {
      // add logout link to the end of the menu
      $logout_class = 'menu-item-logout menu-item menu-item-type-custom menu-item-object-custom';
      $items .= '<li class="' . $logout_class . '">' . wp_loginout(get_permalink(), false) . '</li>';
    }
  } elseif ( $args->menu->slug === 'site-loggedout' ) {
    // add login link to the begin of the menu
    $login_class = 'menu-item-login menu-item menu-item-type-custom menu-item-object-custom';
    $items = '<li class="' . $login_class . '">' . wp_loginout(get_permalink(), false) . '</li>' . $items;
  }
  return $items;
}

/**
 * user edit ajax system
 * =====================
 * passwords strenght meter taken from:
 * https://code.tutsplus.com/articles/using-the-included-password-strength-meter-script-in-wordpress--wp-34736
 * https://github.com/WordPress/WordPress/blob/master/wp-admin/js/user-profile.js#L223
 * ajax idea:
 * https://wordpress.stackexchange.com/questions/274778/updating-user-profile-with-ajax-not-working
 * form field validation:
 * https://itnext.io/https-medium-com-joshstudley-form-field-validation-with-html-and-a-little-javascript-1bda6a4a4c8c
 * page template idea:
 * https://wordpress.stackexchange.com/questions/9775/how-to-edit-a-user-profile-on-the-front-end
 */
function sandbox_ajax_user_edit(){
  check_ajax_referer('sandbox_ajax_call', 'verify');

  // list of valid field names
  $fields = array('user_login', 'first_name', 'last_name', 'nickname', 'display_name', 'pass1');

  if ( isset($_POST['field_name']) ) {
    $field_name = $_POST['field_name'];

    if ( in_array($field_name, $fields) ) { // valid field
      if ( isset($_POST[$field_name]) ) { // field value is set
        $value = filter_var(trim($_POST[$_POST['field_name']]), FILTER_SANITIZE_STRING);
        $pattern = '/[A-Za-z0-9 ]{3,32}/';
        if ( $_POST['field_name'] === 'nickname' ) {
          $pattern = '/[a-zA-Z0-9-_ ]{3,32}/';
        }
        if ( $_POST['field_name'] === 'pass1' ) {
          $pattern = '/^[^\\\\]*$/';
        }
        if ( preg_match($pattern, $value) ) { // check valid pattern
          $current_user = wp_get_current_user();
          // reset $_POST
          $_POST = array(
            'email' => $current_user->user_email,
          );
          if ( $field_name !== 'nickname' ) {
            $_POST['nickname'] = $current_user->nickname;
          }
          // readd value
          $_POST[$field_name] = $value;
          if ( $field_name === 'pass1' ) {
            $_POST['pass2'] = $value;
          }
          $edited_user = edit_user($current_user->ID);
          if ( gettype($edited_user) === "integer" ) {
            $json = array('error' => false, 'message' => 'Value "' . $value . '" for field "' . $field_name . '" was saved');
          } else {
            $json = array('error' => true, 'debug' => array('user_edit' => $edited_user), 'message' => 'User was not saved, look at "debug" array');
          }
        } else {
          $json = array('error' => true, 'message' => 'Value for field "' . $_POST['field_name'] . '" is not valid');
        }
      } else {
        $json = array('error' => true, 'message' => 'No value for field "' . $_POST['field_name'] . '" sent');
      }
    } else {
      $json = array('error' => true, 'message' => 'Given fieldname "' . $_POST['field_name'] . '" is not valid');
    }
  } else {
    $json = array('error' => true, 'message' => 'No "field" Param fieldname sent');
  }
  wp_send_json($json);
}

// Change Wordpress email subject
function sandbox_mail_from_name($name){
  return get_bloginfo('name', 'display');
}

/**
 * Sort countries list by name instead country-iso-code
 * https://wordpress.org/support/topic/excellent-9087/
 */
function sandbox_listo_ordered($data){
  sort($data);
  return $data;
}

/**
 * filter values of form tags to email
 *
 * @param $tag_replace string to change
 * @param $submitted an array containing all submitted fields
 * @param $cf7_key is a unique string key to identify your form, which you can find in your form table in the dashboard.
 */
function sandbox_cf7_mailtag_email_updates($tag_replace, $submitted, $cf7_key){
  if ( $cf7_key == 'free-trial' ) {
    if ( $tag_replace === '' ) { // empty means no
      $tag_replace = 'no'; //change the email-updates.
    }
  }
  return $tag_replace;
}

function sandbox_smart_grid_register_styles(){
  // wp_deregister_style('cf7-grid-layout');
}

/**
 * Site Widgets
 * =============
 */

/**
 * custom faq navigation widget
 * ============================
 * requires the post-type "faq"
 * requires the taxonomy "topic"
 */
class sandbox_faq_nav_widget extends WP_Widget {

  public function __construct() {
    /* Widget settings. */
    $widget_ops = array('classname' => 'faq-navigation', 'description' => 'Displays the FAQ navigation.');

    /* Widget control settings. */
    $control_ops = array('width' => 300, 'height' => 350, 'id_base' => 'faq-navigation');

    /* Create the widget. */
    parent::__construct('faq-navigation', 'FAQ Navigation', $widget_ops, $control_ops);
  }

  // processes widget options to be saved
  public function update($new_instance, $old_instance) {
    $instance = $old_instance;
    $instance['title'] = strip_tags($new_instance['title']);
    return $instance;
  }

  // outputs the content of the widget
  public function widget($args, $instance) {
    sandbox_faq_nav($args, $instance);
  }

  // outputs the options form on admin
  public function form($instance) {
    $title = esc_attr($instance['title']);

  ?><p>
      <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'sandbox'); ?></label>
      <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
    </p><?php
  }

}
function sandbox_faq_nav($args, $instance){
  extract($args);

  $widget_title = apply_filters('widget_title', $instance['title']);

  /**
   * FAQ entries
   * grouped by topic
   * ordered by menu_order      *
   */
  $faq_ids = get_posts(array(
    'post_type' => 'faq',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'meta_query' => array(
      'relation' => 'OR',
      array(
        'key' => '_hide',
        'value' => 1,
        'compare' => 'NOT LIKE',
      ),
      array(
        'key' => '_hide',
        'value' => 'bug #23268',
        'compare' => 'NOT EXISTS',
      )
    ),
  ));
  $topic_term_objects = get_terms(array(
    'taxonomy' => 'topic',
    'object_ids' => $faq_ids,
    'orderby' => 'order', // needs plugin https://github.com/stuttter/wp-term-order/
    'order' => 'ASC',
  ));

  if ( !empty($topic_term_objects) && !is_wp_error($topic_term_objects) ) {
    echo $before_widget;

    if ( $widget_title ) {
      echo $before_title . $widget_title . $after_title;
    }
    echo '<ul class="menu menu-faq">';

    foreach ( $topic_term_objects as $topic_term_object ) {
      $faq_options = array(
        'post_type' => 'faq',
        'tax_query' => array(
          array(
            'field' => 'slug',
            'taxonomy' => 'topic',
            'terms' => $topic_term_object->slug,
          )
        ),
        'meta_query' => array(
          'relation' => 'OR',
          array(
            'key' => '_hide',
            'value' => 1,
            'compare' => 'NOT LIKE',
          ),
          array(
            'key' => '_hide',
            'value' => 'bug #23268',
            'compare' => 'NOT EXISTS',
          )
        ),
        'posts_per_page' => -1,
        'orderby' => 'menu_order',
        'order' => 'ASC',
      );
      $faq_query = new WP_Query($faq_options);

      if ( $faq_query->have_posts() ) {
        echo '<li class="menu-item menu-item-' . esc_attr($topic_term_object->slug) . '">';
        echo '<a href="#submenu-' . esc_attr($topic_term_object->slug) . '">' . $topic_term_object->name . '</a>';
        echo '<ul id="submenu-' . esc_attr($topic_term_object->slug) . '" class="sub-menu">';

        while ( $faq_query->have_posts() ) {
          $faq_query->the_post();

          $post_id = get_the_ID();
          $post_slug = sandbox_get_the_slug();
          $post_class = get_post_class();

          echo '<li id="menu-item-' . $post_slug . '" class="' . esc_attr(join(' ', $post_class)) . '"><a href="#' . $post_slug . '">' . get_the_title() . '</a></li>';
        }
        wp_reset_postdata();
        echo '</ul></li>';
      }
    }
    echo $after_widget;
  }
}

/**
 * custom events calender widget
 * =============================
 * requires the post-type "events" with custom fields:
 * -_date-end
 * -_date-begin
 */
class sandbox_events_widget extends WP_Widget {

  public function __construct() {
    /* Widget settings. */
    $widget_ops = array('classname' => 'events-calendar', 'description' => 'Test um zu schauen ob ich ein widget basteln kann.');

    /* Widget control settings. */
    $control_ops = array('width' => 300, 'height' => 350, 'id_base' => 'custom-events-calendar');

    /* Create the widget. */
    parent::__construct('custom-events-calendar', 'N&auml;chste Termine', $widget_ops, $control_ops );
  }

  // processes widget options to be saved
  public function update($new_instance, $old_instance) {
    $instance = $old_instance;
    $instance['title'] = strip_tags( $new_instance['title'] );
    $instance['eventtype'] = $new_instance['eventtype'];
    $instance['maximum'] = intval($new_instance['maximum']);
    $instance['want_excerpt'] = filter_var($new_instance['want_excerpt'], FILTER_VALIDATE_BOOLEAN);

    return $instance;
  }

  // outputs the content of the widget
  public function widget($args, $instance) {
    sandbox_events($args, $instance);
  }

  // outputs the options form on admin
  public function form($instance) {
    $title = esc_attr($instance['title']);
    $eventtypes = get_terms('eventtypen');
    $maximum = esc_attr($instance['maximum']);
    $want_excerpt = esc_attr($instance['want_excerpt']);
?>
    <p>
      <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'sandbox'); ?></label>
      <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
    </p>
    <p>
      <label for="<?php echo $this->get_field_id('eventtype'); ?>"><?php _e('Event-Typ:', 'sandbox'); ?></label>
      <select class="widefat" id="<?php echo $this->get_field_id('eventtype'); ?>" name="<?php echo $this->get_field_name('eventtype'); ?>">
        <?php foreach ($eventtypes as $eventtype){ ?>
        <option value="<?php echo $eventtype->slug; ?>"<?php if ( $instance['eventtype'] == $eventtype->slug ) { echo ' selected="selected"'; } ?>><?php echo $eventtype->name; ?></option>
        <?php  }  ?>
      </select>
    </p>
    <p>
      <label for="<?php echo $this->get_field_id('maximum'); ?>"><?php _e('Maximum:', 'sandbox'); ?></label>
      <input class="widefat" id="<?php echo $this->get_field_id('maximum'); ?>" name="<?php echo $this->get_field_name('maximum'); ?>" type="text" value="<?php echo $maximum; ?>" />
    </p>
    <p>
      <input class="checkbox" type="checkbox" value="true" <?php checked( 1, $want_excerpt ); ?> id="<?php echo $this->get_field_id('want_excerpt'); ?>" name="<?php echo $this->get_field_name('want_excerpt'); ?>" />
      <label for="<?php echo $this->get_field_id('want_excerpt'); ?>"><?php _e('Auszug anzeigen', 'sandbox'); ?></label>
    </p>
<?php
  }
}

function sandbox_events($args, $instance){
  extract($args);

  $title = apply_filters('widget_title', $instance['title'] );
  $max = $instance['maximum'];
  $index = 0;
  $eventsQuery = array(
    'post_type' => 'events',
    'order' => 'ASC',
    'orderby' => 'meta_value',
    'meta_key' => '_date-begin',
    'eventtypen' => $instance['eventtype'],
    'posts_per_page' => 100,
  );
  $events = new WP_Query($eventsQuery);
  $want_excerpt = $instance['want_excerpt'];

  if ( $events->have_posts() ) {
    echo $before_widget;
    if ( $title ) { echo $before_title . $title . $after_title; }
    echo '<ul class="events events-widget">';
    while ( $events->have_posts() ) {
      $events->the_post();
      if ( $index < $max ) {
        $event_meta = get_post_meta(get_the_ID());
        $target_day = isset($event_meta["_date-end"][0]) && is_string($event_meta["_date-end"][0]) && strlen($event_meta["_date-end"][0]) > 0 ? $event_meta["_date-end"][0] : $event_meta["_date-begin"][0];
        $current_date = $date = date('Y-m-d', time());

        // DEBUG
        // echo '<!-- title:', get_the_title(), ', max: ', $max, ', index: ', $index + 1, ' -->';
         // echo '<!-- title:', get_the_title(), ' ', $current_date, ' lte ', $target_day, ' === ', var_dump( $current_date <= $target_day ), ' -->';
        if ( $current_date <= $target_day ) {
          $index++;
          $begin_dayDisplay   = date("j. n.", strtotime($event_meta["_date-begin"][0]));
          $begin_yearDisplay   = date("Y", strtotime($event_meta["_date-begin"][0]));
  ?>
    <li <?php post_class('clearfix'); ?>>
      <aside class="meta"><p class="date"><span class="day"><?php echo $begin_dayDisplay; ?></span> <span class="year"><?php echo $begin_yearDisplay; ?></span></p></aside>
      <h4><a href="<?php the_permalink() ?>"><span class="entry-title"><?php the_title() ?></span></a></h4>
    <?php if ( $want_excerpt && has_excerpt() ) { ?>
      <div class="entry-summary">
        <?php the_excerpt(); ?>
      </div>
    <?php  } ?>
    </li>
  <?php
        }
      }
    }
    echo '</ul>';
    echo $after_widget;
  }
  wp_reset_postdata();
}

/**
 * custom social share widget
 * ==========================
 * No user tracking, no javascript.
 *
 * - to use in your themes function.php
 * - edit the markup of the widget at sandbox_social();
 * - add your services at update()
 * - Service adresses are hardcoded and can change during time
 * - Add the CSS part of this file in yourThemes style.css
 */
class sandbox_social_widget extends WP_Widget {
  public function __construct() {
    /* Widget settings. */
    $widget_ops = array('classname' => 'social', 'description' => 'Display social share icons without automatic user tracking');
    /* Widget control settings. */
    $control_ops = array('width' => 300, 'height' => 350, 'id_base' => 'social');
    /* Create the widget. */
    parent::__construct('social', 'Social share', $widget_ops, $control_ops );
  }
  // processes widget options to be saved
  public function update($new_instance, $old_instance) {
    $new_instance = (array) $new_instance;
    $instance = array(
      'title' => strip_tags( $new_instance['title'] ),
      'services' => array(
        'xing' => array(
          'name' => 'XING',
          'url' => 'https://www.xing.com/spi/shares/new?url=',
        ),
        'facebook' => array(
          'name' => 'Facebook',
          'url' => 'https://www.facebook.com/sharer/sharer.php?u=',
        ),
        'twitter' => array(
          'name' => 'Twitter',
          'url' => 'http://twitter.com/share?url=',
        ),
        'googleplus' => array(
          'name' => 'Google+',
          'url' => 'https://plus.google.com/share?url=',
        ),
        'linkedin' => array(
          'name' => 'LinkedIn',
          'url' => 'http://www.linkedin.com/sharer.php?u=',
        ),
        'pinterest' => array(
          'name' => 'Pinterest',
          'url' => 'http://www.pinterest.com/pin/create/bookmarklet/?url=',
        ),
        'email' => array(
          'name' => 'Email',
          'url' => 'mailto:?subject=' . __('Teilen mit', 'sandbox') . ' ' . wp_get_document_title() . '&body=',
        ),
      ),
    );
    foreach ( $instance['services'] as $serviceName => $serviceData ) {
      $instance[$serviceName] = filter_var($new_instance[$serviceName], FILTER_VALIDATE_BOOLEAN);
    }
    return $instance;
  }
  // outputs the content of the widget
  public function widget($args, $instance) {
    sandbox_social($args, $instance);
  }
  // outputs the options form on admin
  public function form($instance) {
    $defaults = array('facebook' => true, 'twitter' => true, 'googleplus' => true, 'linkedin' => false);
    $services = $instance['services'];
    $instance = wp_parse_args( (array) $instance, $defaults);
    $title = esc_attr($instance['title']);
?>
    <p>
      <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'sandbox'); ?></label>
      <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
    </p>
  <?php foreach ( $services as $serviceName => $serviceData ) { ?>
    <p>
      <input class="checkbox" value="true" type="checkbox" id="<?php echo $this->get_field_id($serviceName); ?>" name="<?php echo $this->get_field_name($serviceName); ?>" <?php checked($instance[$serviceName], true) ?> />
      <label for="<?php echo $this->get_field_id($serviceName); ?>"><?php echo $serviceData['name']; ?></label>
    </p>
  <?php } ?>
<?php
  }
}
function sandbox_social($args, $instance){
  extract($args);
  $title = apply_filters('widget_title', $instance['title'] );
  $services = $instance['services'];
  $protocol = strpos(strtolower($_SERVER['SERVER_PROTOCOL']),'https') === false ? 'http' : 'https';
  $host = $_SERVER["HTTP_HOST"];
  $path = $_SERVER["REQUEST_URI"];
  $pageUrl =  $protocol . '://' . $host . $path;
  $servicesCount = 0;
  $servicesList = '';
  foreach ( $services as $serviceName => $serviceData ) {
    if ( $instance[$serviceName] ) {
      $servicesCount++;
      $servicesList .= '<li class="' . $serviceName . '"><a href="' . esc_url($serviceData["url"], ( $serviceName === 'email' ? 'mailto' : 'https' ) ) . $pageUrl . '" target="_blank" title="' . __('Teilen mit', 'sandbox') . ' ' . $serviceData["name"] . '">' . $serviceData["name"] . '</a></li>';
    }
  }
  if ( $servicesCount > 0 ) {
    echo $before_widget;
  ?>
    <div class="social">
      <?php if ( !empty($title) ) { echo $before_title . $title . $after_title; } ?>
      <ul>
        <?php echo $servicesList; ?>
      </ul>
    </div>
  <?php
    echo $after_widget;
  }
}

/**
 * custom slider widget
 */
class sandbox_slider_widget extends WP_Widget {

  function __construct() {
    /* Widget settings. */
    $widget_ops = array('classname' => 'slider', 'description' => 'Select a slider to display');

    /* Widget control settings. */
    $control_ops = array('width' => 300, 'height' => 350, 'id_base' => 'slider');

    /* Create the widget. */
    parent::__construct('slider', 'Slider', $widget_ops, $control_ops );

  }

  // processes widget options to be saved
  public function update($new_instance, $old_instance) {

    $instance = $old_instance;
    $instance['title'] = strip_tags( $new_instance['title'] );
    $instance['slider'] = $new_instance['slider'];
    $instance['show-titles'] = filter_var($new_instance['show-titles'], FILTER_VALIDATE_BOOLEAN);
    $instance['show-content'] = filter_var($new_instance['show-content'], FILTER_VALIDATE_BOOLEAN);
    $instance['show-excerpt'] = filter_var($new_instance['show-excerpt'], FILTER_VALIDATE_BOOLEAN);

    return $instance;
  }

  // outputs the content of the widget
  public function widget($args, $instance) {
    sandbox_slider($args, $instance);
  }

  // outputs the options form on admin
  public function form($instance) {

    $title = esc_attr($instance['title']);
    $sliders = get_terms('slider');
?>
    <p>
      <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'sandbox'); ?></label>
      <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
    </p>
    <p>
      <label for="<?php echo $this->get_field_id('slider'); ?>"><?php _e('Slider:', 'sandbox'); ?></label>
      <select class="widefat" id="<?php echo $this->get_field_id('slider'); ?>" name="<?php echo $this->get_field_name('slider'); ?>">
        <?php foreach ($sliders as $slider){ ?>
        <option value="<?php echo $slider->slug; ?>"<?php if ( $instance['slider'] == $eventtype->slug ) { echo ' selected="selected"'; } ?>><?php echo $slider->name; ?></option>
        <?php  }  ?>
      </select>
    </p>
    <p>
      <input class="checkbox" value="true" type="checkbox" id="<?php echo $this->get_field_id('show-titles'); ?>" name="<?php echo $this->get_field_name('show-titles'); ?>" <?php checked($instance['show-titles'], true) ?> />
      <label for="<?php echo $this->get_field_id('show-titles'); ?>"><?php echo 'Show titles'; ?></label>
    </p>
    <p>
      <input class="checkbox" value="true" type="checkbox" id="<?php echo $this->get_field_id('show-content'); ?>" name="<?php echo $this->get_field_name('show-content'); ?>" <?php checked($instance['show-content'], true) ?> />
      <label for="<?php echo $this->get_field_id('show-content'); ?>"><?php echo 'Show content'; ?></label>
    </p>
    <p>
      <input class="checkbox" value="true" type="checkbox" id="<?php echo $this->get_field_id('show-excerpt'); ?>" name="<?php echo $this->get_field_name('show-excerpt'); ?>" <?php checked($instance['show-excerpt'], true) ?> />
      <label for="<?php echo $this->get_field_id('show-excerpt'); ?>"><?php echo 'Show excerpt'; ?></label>
    </p>
<?php
  }
}
function sandbox_slider($args, $instance){
  extract($args);

  $title = apply_filters('widget_title', $instance['title']);
  $sliderQuery = array(
    'post_type' => 'slide',
    'order' => 'ASC',
    'orderby' => 'menu_order',
    'slider' => $instance['slider']
  );
  $slider = new WP_Query($sliderQuery);
  $sliderCount = 0;
  $sliderNav = '<ul class="slider-nav">';
  $sliderId = $slider->query_vars['taxonomy'] . '-' . $slider->query_vars['term'];
  $sliderClass = $slider->query_vars['taxonomy'];
  $show_titles = filter_var($instance['show-titles'], FILTER_VALIDATE_BOOLEAN);
  $show_content = filter_var($instance['show-content'], FILTER_VALIDATE_BOOLEAN);
  $show_excerpt = filter_var($instance['show-excerpt'], FILTER_VALIDATE_BOOLEAN);

  if ( $slider->have_posts() ) {

    echo $before_widget;
    echo '<section class="', $sliderClass, ' ', $sliderId, '" id="', $sliderId, '">';
    if ( $title ){ echo $before_title . $title . $after_title; }
    echo '  <ul class="slides clearfix">';

    while ( $slider->have_posts() ) { $slider->the_post();

      $slideId = get_post_field('post_name', get_the_ID() );
      $sliderCount++;
      $sliderNav .= '<li><a href="#slide-' . $slideId . '">' . $sliderCount . '</a></li>';
      $sliderImage = get_the_post_thumbnail(get_the_ID(), 'slider', array('title' => the_title_attribute('echo=0')));
      $sliderMeta = get_post_meta( get_the_ID() );
      $the_content = $show_content ? get_the_content() : $show_content;
      $has_content = !empty(trim(str_replace('&nbsp;', '', strip_tags($the_content))));

    // echo var_dump($sliderMeta["slider-link"][0]);
    ?>
    <li id="slide-<?php echo $slideId ?>" class="slide slide-<?php echo $slideId ?> slide-<?php echo $sliderCount ?> clearfix">
      <?php echo $sliderImage; ?>
      <?php if ( $show_titles || $show_excerpt || $show_content ) { ?>
        <div class="inner">
        <?php if ( $show_titles ) { ?>
          <h3><?php the_title(); ?></h3>
        <?php } ?>
        <?php if ( $show_content && $has_content ) { ?>
          <div class="content">
            <?php the_content(); ?>
          </div>
        <?php } ?>
        <?php if ( $show_excerpt && has_excerpt() ) { ?>
          <div class="content excerpt">
            <?php the_excerpt(); ?>
          </div>
        <?php } ?>
        </div>
      <?php } ?>
    </li>
    <?php
    }
    echo '</ul>';

    $sliderNav .= '</ul>';

    echo $sliderNav;
    echo '</section>';
    echo $after_widget;
  }
  wp_reset_postdata();
}

?>
