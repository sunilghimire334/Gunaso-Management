<?php
// Absolute no-cache headers
header_remove("Pragma");
header_remove("Cache-Control");
header_remove("Expires");

header("Expires: 0");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (ob_get_length()) ob_end_clean();
ob_start();

// Change this version when you update your site
define("ASSET_VERSION", 'v2.0.5');
?>