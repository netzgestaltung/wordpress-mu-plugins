<?php
/**
 * The base configuration for Matomo
 *
 * copy this file to config.php and add your Matomo credentials
 *
 * This file contains the following configurations:
 *
 * * Matomo tracker URL
 * * Matomo site ID
 * * Matomo auth token
 *
 */

/** Matomo base URL, for example http://example.org/matomo/ Must be set */
define( 'SOCOS_IO_STATS_TRACKER_URL', '' );

/** Specify the site ID to track */
define( 'SOCOS_IO_STATS_SITE_ID', 1 );

/**
 * Specify an API token with at least Write permission, so the Visitor IP address can be recorded
 * Learn more about token_auth: https://matomo.org/faq/general/faq_114/
 */
define( 'SOCOS_IO_STATS_AUTH_TOKEN', '' );
