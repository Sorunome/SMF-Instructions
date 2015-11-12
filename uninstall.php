<?php
if (!defined('SMF'))
	require_once('SSI.php');
global $smcFunc, $modSettings, $boardurl, $sourcedir, $boarddir, $oirc_config;

remove_integration_function('integrate_pre_include','$sourcedir/Subs-Instructions.php');
remove_integration_function('integrate_actions','loadInstructionsActions_hook');
remove_integration_function('integrate_load_permissions','loadInstructionsPermissions_hook');
remove_integration_function('integrate_menu_buttons','loadInstructionsMenu_hook');
?>