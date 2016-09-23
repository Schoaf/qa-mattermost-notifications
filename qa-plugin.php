<?php

/*
  Plugin Name: Mattermost Notifications
  Plugin URI: https://github.com/Schoaf/qa-mattermost-notifications/
  Plugin Description: Sends Mattermost notifications of various events.
  Plugin Version: 0.2
  Plugin Date: 2016-08-19
  Plugin Author: Andreas Scharf
  Plugin Author URI: https://github.com/Schoaf
  Plugin License: MIT
  Plugin Minimum Question2Answer Version: 1.5
  Plugin Update Check URI: https://raw.githubusercontent.com/Schoaf/qa-mattermost-notifications/master/qa-plugin.php
*/

if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
  header('Location: ../../');
  exit;
}

qa_register_plugin_module('module', 'qa-mattermost-notifications-page.php', 'qa_mattermost_notifications_page', 'Mattermost Notifications Configuration');
qa_register_plugin_module('event', 'qa-mattermost-notifications-event.php', 'qa_mattermost_notifications_event', 'Mattermost Notifications');
