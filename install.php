<?php
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

global $modSettings, $smcFunc;

updateSettings(array(
	'instructions_sceditor_url' => (empty($modSettings['instructions_sceditor_url'])?'http://www.sceditor.com/minified':$modSettings['instructions_sceditor_url']),
	'instructions_uploads_path' => (empty($modSettings['instructions_uploads_path'])?'instruction_uploads':$modSettings['instructions_uploads_path']),
	'instructions_uploads_url' => (empty($modSettings['instructions_uploads_url'])?$boardurl.'/instruction_uploads':$modSettings['instructions_uploads_url'])
));

$smcFunc['db_query']('', "CREATE TABLE IF NOT EXISTS `{db_prefix}instructions_images` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `owner` int(11) NOT NULL,
	  `tags` text NOT NULL,
	  `annotations` text NOT NULL,
	  `extension` varchar(10) NOT NULL,
	  `success` int(11) NOT NULL DEFAULT '0',
	  `resizeTypes` text NOT NULL,
	  `name` text NOT NULL,
	  PRIMARY KEY (`id`),
	  INDEX `owner` (`owner`)
	)");

$smcFunc['db_query']('', "CREATE TABLE IF NOT EXISTS `{db_prefix}instructions_instructions` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `owner` int(11) NOT NULL,
	  `name` varchar(255) NOT NULL,
	  `url` varchar(255) NOT NULL,
	  `status` int(11) NOT NULL DEFAULT '0',
	  `views` int(11) NOT NULL DEFAULT '0',
	  `upvotes` int(11) NOT NULL DEFAULT '0',
	  `category` int(11) NOT NULL DEFAULT '-1',
	  `downvotes` int(11) NOT NULL DEFAULT '0',
	  `main_image` int(11) NOT NULL DEFAULT '-1',
	  `publish_date` datetime NOT NULL,
	  `topic_id` int(11) NOT NULL DEFAULT '-1',
	  `new_instruction` int(11) NOT NULL DEFAULT '-1',
	  `import_data` text NOT NULL,
	  PRIMARY KEY (`id`),
	  INDEX `owner` (`owner`),
	  INDEX `url` (`url`)
	)");

$smcFunc['db_query']('', "CREATE TABLE IF NOT EXISTS `{db_prefix}instructions_members` (
	  `member_id` int(11) NOT NULL,
	  `tags` text NOT NULL,
	  `votes` text NOT NULL,
	  PRIMARY KEY (`member_id`)
	)");

$smcFunc['db_query']('', "CREATE TABLE IF NOT EXISTS `{db_prefix}instructions_steps` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `sorder` int(11) NOT NULL,
	  `instruction_id` int(11) NOT NULL ,
	  `body` text NOT NULL,
	  `images` text NOT NULL,
	  `title` text NOT NULL,
	  `main_image` int(11) NOT NULL DEFAULT '-1',
	  PRIMARY KEY (`id`),
	  INDEX `sorder` (`sorder`),
	  INDEX `instruction_id` (`instruction_id`)
	)");

if(SMF == 'SSI')
	echo 'Database settings successfully made!';

?>