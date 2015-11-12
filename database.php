<?php
if (!defined('SMF'))
	require_once('SSI.php');

global $smcFunc, $modSettings, $boardurl, $sourcedir, $boarddir, $oirc_config;
$smcFunc['db_create_table']('{db_prefix}instructions_images', array(
	array('name' => 'id', 'type' => 'int', 'null' => false, 'auto' => true),
	array('name' => 'owner', 'type' => 'int', 'null' => false),
	array('name' => 'tags', 'type' => 'text', 'null' => false),
	array('name' => 'annotations', 'type' => 'text', 'null' => false),
	array('name' => 'extension', 'type' => 'varchar', 'size' => 10, 'null' => false),
	array('name' => 'success', 'type' => 'int', 'null' => false, 'default' => 0),
	array('name' => 'resizeTypes', 'type' => 'text', 'null' => false),
	array('name' => 'name', 'type' => 'text', 'null' => false)
), array(
	array('type' => 'primary', 'columns' => array('id')),
	array('columns' => array('owner'))
));

$smcFunc['db_create_table']('{db_prefix}instructions_instructions', array(
	array('name' => 'id', 'type' => 'int', 'null' => false, 'auto' => true),
	array('name' => 'owner', 'type' => 'int', 'null' => false),
	array('name' => 'name', 'type' => 'varchar', 'size' => 255, 'null' => false),
	array('name' => 'url', 'type' => 'varchar', 'size' => 255, 'null' => false),
	array('name' => 'status', 'type' => 'int', 'null' => false, 'default' => 0),
	array('name' => 'views', 'type' => 'int', 'null' => false, 'default' => 0),
	array('name' => 'upvotes', 'type' => 'int', 'null' => false, 'default' => 0),
	array('name' => 'downvotes', 'type' => 'int', 'null' => false, 'default' => 0),
	array('name' => 'category', 'type' => 'int', 'null' => false, 'default' => -1),
	array('name' => 'main_image', 'type' => 'int', 'null' => false, 'default' => -1),
	array('name' => 'num_steps', 'type' => 'int', 'null' => false, 'default' => 0),
	array('name' => 'publish_date', 'type' => 'datetime'),
	array('name' => 'topic_id', 'type' => 'int', 'null' => false, 'default' => -1),
	array('name' => 'new_instruction', 'type' => 'int', 'null' => false, 'default' => -1),
	array('name' => 'import_data', 'type' => 'text', 'null' => false)
), array(
	array('type' => 'primary', 'columns' => array('id')),
	array('columns' => array('owner')),
	array('columns' => array('url'))
));

$smcFunc['db_create_table']('{db_prefix}instructions_members', array(
	array('name' => 'member_id', 'type' => 'int', 'null' => false),
	array('name' => 'tags', 'type' => 'text', 'null' => false),
	array('name' => 'votes', 'type' => 'text', 'null' => false)
), array(
	array('type' => 'primary', 'columns' => array('member_id'))
));

$smcFunc['db_create_table']('{db_prefix}instructions_steps', array(
	array('name' => 'id', 'type' => 'int', 'null' => false, 'auto' => true),
	array('name' => 'sorder', 'type' => 'int', 'null' => false),
	array('name' => 'instruction_id', 'type' => 'int', 'null' => false),
	array('name' => 'body', 'type' => 'text', 'null' => false),
	array('name' => 'images', 'type' => 'text', 'null' => false),
	array('name' => 'title', 'type' => 'text', 'null' => false),
	array('name' => 'main_image', 'type' => 'int', 'null' => false, 'default' => -1)
), array(
	array('type' => 'primary', 'columns' => array('id')),
	array('columns' => array('sorder')),
	array('columns' => array('instruction_id'))
));

?>