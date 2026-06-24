<?php
/**
 * Pressing module header include file
 * Include this file at the top of each pressing page after llxHeader()
 */

// Add CSS
echo '<link rel="stylesheet" href="' . DOL_URL_ROOT . '/custom/pressing/css/pressing.css?v=' . time() . '">';

// Add FontAwesome if not already loaded
if (!defined('PRESSING_FA_LOADED')) {
	echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">';
	define('PRESSING_FA_LOADED', true);
}
