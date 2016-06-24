<?php
if (!defined('SMF'))
	require_once('SSI.php');

global $modSettings, $smcFunc;

$defaultTopic = '[size=18pt][b]{NAME}[/b][/size]
[size=16pt][url={URL}]--> View Instruction[/url][/size]
[img]{IMG}[/img]


{INTRO}';

updateSettings(array(
	'instructions_sceditor_url' => (empty($modSettings['instructions_sceditor_url'])?'http://www.sceditor.com/minified':$modSettings['instructions_sceditor_url']),
	'instructions_uploads_path' => (empty($modSettings['instructions_uploads_path'])?'instruction_uploads':$modSettings['instructions_uploads_path']),
	'instructions_uploads_url' => (empty($modSettings['instructions_uploads_url'])?$boardurl.'/instruction_uploads':$modSettings['instructions_uploads_url']),
	'instructions_default_topic' => (empty($modSettings['instructions_default_topic'])?$defaultTopic:$modSettings['instructions_default_topic'])
));


add_integration_function('integrate_pre_include','$sourcedir/Subs-Instructions.php',true);
add_integration_function('integrate_actions','loadInstructionsActions_hook',true);
add_integration_function('integrate_load_permissions','loadInstructionsPermissions_hook',true);
add_integration_function('integrate_menu_buttons','loadInstructionsMenu_hook',true);


?>
