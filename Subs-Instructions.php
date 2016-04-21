<?php
if(!defined('SMF'))
	die('Hacking attempt...');

//check for pretty urls to add the instructions hook
if(!empty($modSettings['pretty_enable_filters'])){
	$cbs = unserialize($modSettings['pretty_filter_callbacks']);
	$cbs[] = 'Instructions_pretty_filter';
	$modSettings['pretty_filter_callbacks'] = serialize($cbs);
	function Instructions_pretty_filter($urls){
		global $boardurl, $context, $modSettings, $scripturl;
		
		$pattern = '`' . $scripturl . '(.*)action=([^;]+)`S';
		$replacement = $boardurl . '/$2/$1';
		foreach ($urls as $url_id => $url){
			if (!isset($url['replacement']))
				if(preg_match('`'.$scripturl.'(.*)action=instructions;id=([^;]+);step=([^;]+);stepname=([^;]+)`S',$url['url'],$matches)){
					$urls[$url_id]['replacement'] = preg_replace('`'.$scripturl.'(.*)action=instructions;id=([^;]+);step=([^;]+);stepname=([^;]+)`S',$boardurl.'/instructions/$2/step$3/$4/$1', $url['url']);
				}elseif(preg_match('`'.$scripturl.'(.*)action=instructions;id=([^;]+);step=([^;]+)`S',$url['url'],$matches)){
					$urls[$url_id]['replacement'] = preg_replace('`'.$scripturl.'(.*)action=instructions;id=([^;]+);step=([^;]+)`S',$boardurl.'/instructions/$2/step$3/$1', $url['url']);
				}elseif(preg_match('`'.$scripturl.'(.*)action=instructions;id=([^;]+)`S',$url['url'],$matches)){
					$urls[$url_id]['replacement'] = preg_replace('`'.$scripturl.'(.*)action=instructions;id=([^;]+)`S',$boardurl.'/instructions/$2/$1', $url['url']);
				}elseif(preg_match('`'.$scripturl.'(.*)action=instructions`S',$url['url'],$matches)){
					$urls[$url_id]['replacement'] = preg_replace('`'.$scripturl.'(.*)action=instructions`S',$boardurl.'/instructions/$1', $url['url']);
				}
		}
		
		return $urls;
	}
}

loadLanguage('Instructions_global');

function loadInstructionsActions_hook(&$actionArray){
	$actionArray = array_merge($actionArray,array(
		'instructions' => array('Instructions.php','InstructionsMain'),
		'instructions_cat' => array('Instructions.php','InstructionsCats'),
		'instructions_misc' => array('Instructions.php','InstructionsMisc')
	));
}
function loadInstructionsMenu_hook(&$buttons){
	global $txt;
	$buttons['instructions'] = array(
		'title' => $txt['instructions'],
		'href' => $scripturl . '?action=instructions_cat',
		'show' => true,
		'sub_buttons' => array(
			'new' => array(
				'title' => $txt['instructions_new'],
				'href' => $scripturl . '?action=instructions;sa=new',
				'show' => allowedTo('inst_can_edit_own') || allowedTo('inst_can_edit_any'),
				'is_last' => true
			)
		),
	);
}
function loadInstructionsPermissions_hook(&$permissionGroups,&$permissionList,&$leftPermissionGroups,&$hiddenPermissions,&$relabelPermissions){
	$permissionList['membergroup'] = array_merge($permissionList['membergroup'],array(
		'inst_can_delete' => array(true, 'instructions', 'instructions'),
		'inst_can_view_published' => array(false, 'instructions', 'instructions'),
		'inst_can_view_unpublished' => array(true, 'instructions', 'instructions'),
		'inst_can_edit' => array(true, 'instructions', 'instructions'),
	));
	$permissionList['board']['inst_publish_instruction'] = array(false, 'instructions', 'instructions');
}
?>
