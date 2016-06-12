<?php
if (!defined('SMF'))
	die('Hacking attempt...');


define('INSTRUCTIONS_SELECT','SELECT i.id,i.main_image,i.name,i.url,i.status,i.category,i.upvotes,i.downvotes,i.topic_id,
	UNIX_TIMESTAMP(i.publish_date) AS publish_date,i.new_instruction,i.import_data,i.num_steps,i.views,
	u.member_name,u.id_member,u.real_name
	FROM {db_prefix}instructions_instructions i INNER JOIN {db_prefix}members u ON i.owner=u.id_member ');
define('INSTRUCTIONS_STEPS_FETCH_VARS','id,body,images,title,main_image');
define('INSTRUCTIONS_IMAGES_FETCH_VARS','id,annotations,extension,resizeTypes,name,owner');


function InstructionsMain(){
	global $context, $modSettings, $scripturl, $txt, $settings;
	global $user_info, $smcFunc, $board, $sourcedir, $board_info,$instr;
	loadLanguage('Instructions');
	loadTemplate('Instructions');
	$instr = new Instruction(isset($_REQUEST['id'])?$_REQUEST['id']:-1,!isset($_REQUEST['original']) && empty($_REQUEST['sa']));
	switch(isset($_REQUEST['sa'])?$_REQUEST['sa']:''){
		case 'edit':
			$instr->loadStep()->edit();
			break;
		case 'new':
			$instr = (new Instruction(-1))->edit();
			break;
		case 'save':
			$instr->editStep()->savePost()->data();
			break;
		case 'delete':
			$instr->mustDelete()->goEdit()->deleteInstruction()->data();
			break;
		case 'addstep':
			$instr->goEdit()->addStep()->data();
			break;
		case 'deletestep':
			$instr->editStep()->deleteStep()->data();
			break;
		case 'publish':
			$instr->goEdit()->publish()->data();
			break;
		case 'unpublish':
			$instr->goEdit()->unpublish()->data();
			break;
		case 'newversion':
			$instr->goEdit()->newVersion(isset($_REQUEST['newid'])?$_REQUEST['newid']:'')->data();
			break;
		case 'savesteporder':
			$instr->goEdit()->saveStepOrder()->data();
			break;
		case 'savenotes':
			$instr->goEdit()->saveNotes(isset($_REQUEST['note'])?$_REQUEST['note']:-1)->data();
			break;
		case 'upvote':
			$instr->changeKarma(+1);
			break;
		case 'downvote':
			$instr->changeKarma(-1);
			break;
		case 'data':
			$instr->loadStep()->data();
			break;
		case 'import':
			$instr->goEdit()->import(json_decode($_REQUEST['data'],true))->data();
			break;
		default:
			$instr->loadSteps(isset($_REQUEST['allsteps'])?'all':0)->view();
	}
}
function InstructionsCats(){
	loadLanguage('Instructions');
	loadTemplate('Instructions');
	
	InstructionsDisplayCat();
}
function InstructionsMisc(){
	global $user_info,$smcFunc;
	switch(isset($_REQUEST['sa'])?$_REQUEST['sa']:''){
		case 'urlupload':
		case 'fileupload':
			header('Content-Type:application/json');
			$img = false;
			$msg = '';
			$arg = false;
			if($_REQUEST['sa'] == 'urlupload'){
				if(isset($_REQUEST['url'])){
					$arg = $_REQUEST['url'];
				}else{
					$msg = 'missing parameter';
				}
			}else{
				if(isset($_FILES['files'])){
					$arg = $_FILES['files'];
				}else{
					$msg = 'missing file!';
				}
			}
			if(empty($msg)){
				$msg = ($img = (new InstructionFile()))->upload($arg);
			}
			
			$json = array(
				'success' => empty($msg)
			);
			if(!$json['success']){
				$json['upload-error'] = $msg;
			}else{
				$json['image'] = $img->getJSON(array('small','medium','large'));
			}
			echo json_encode($json);
			exit;
		case 'setimgtags':
			header('Content-Type:application/json');
			$img = new InstructionFile($_REQUEST['id']);
			$msg = $img->isOwner()?$img->setTags($_REQUEST['tags']):'Permission denied';
			echo json_encode(array(
				'success' => empty($msg),
				'msg' => $msg
			));
			exit;
		case 'getimgtags':
			header('Content-Type: application/json');
			$request = $smcFunc['db_query']('','SELECT tags FROM {db_prefix}instructions_members WHERE member_id = {int:id}',array('id' => $user_info['id']));
			$tags = array();
			if($res = $smcFunc['db_fetch_assoc']($request)){
				$tags = InstructionsGetSQLArray($res['tags']);
			}
			$smcFunc['db_free_result']($request);
			echo json_encode(array(
				'tags' => $tags
			));
			exit;
		case 'getlibrary':
			InstructionsGetLibrary($_REQUEST['lib']);
			exit;
		case 'deleteimage':
			header('Content-Type: application/json');
			$img = new InstructionFile($_REQUEST['id']);
			$msg = $img->isOwner()?$img->delete():'Permission denied';
			echo json_encode(array(
				'success' => empty($msg),
				'msg' => $msg
			));
			exit;
		case 'getinstructable':
			header('Content-Type: application/json');
			$s = file_get_contents('http://www.instructables.com/json-api/showInstructable?id='.urlencode($_REQUEST['id']).'&t='.time());
			if($s == ''){
				echo '{"success":false}';
			}
			echo $s;
			exit;
	}
}

class Instruction{
	public $exists = false;
	protected $id = -1;
	protected $dispId = '-1';
	public $steps = array();
	protected $numSteps = 0;
	public $owner = array(
		'id_member' => -1,
		'member_name' => '',
		'real_name' => ''
	);
	public $name = '';
	public $name_parsed = '';
	protected $category = -1;
	public $upvotes = 0;
	public $downvotes = 0;
	public $topic_id = -1;
	public $publish_date = 0;
	protected $new_instruction = -1;
	public $origUrl = '';
	public $newUrl = '';
	public $published = false;
	protected $status = 0;
	public $url = '';
	public $url_edit = '';
	protected $imageCache = array();
	protected $imageIdCache = array();
	protected $imageCacheUpdater = array();
	protected $imageCacheMap = array();
	protected $html_headers = '';
	protected $import_data = '';
	protected $last_step_id = -1;
	protected $last_step = 0;
	protected $main_image = false;
	protected $main_image_id = -1;
	protected $views = 0;
	public function getId(){
		return $this->id;
	}
	protected function main_image_square(){
		global $modSettings;
		// we are cheating here 100%, all to save SQL queries!
		return $modSettings['instructions_uploads_url'].'/'.$this->main_image_id.'/square.jpg';
	}
	protected function getImages($ids){
		global $smcFunc;
		if(sizeof($ids) == 0 || !$ids){
			return array();
		}
		
		$a = array();
		$request = $smcFunc['db_query']('','SELECT '.INSTRUCTIONS_IMAGES_FETCH_VARS.' FROM {db_prefix}instructions_images WHERE id IN ('.implode(',', array_map('intval', $ids)).') AND success=1',array());
		while($res = $smcFunc['db_fetch_assoc']($request)){
			$a[$res['id']] = new InstructionFile($res);
		}
		$smcFunc['db_free_result']($request);
		return $a;
	}
	protected function loadImagesInCache(){
		$ids = array();
		
		// let's make sure that we haven't loaded the image already
		foreach(array_unique($this->imageIdCache) as $i){
			if(!isset($this->imageCache[$i])){
				$ids[] = $i;
			}
		}
		
		// now load them!
		$this->imageCache = array_merge($this->imageCache,$this->getImages($ids));
		
		// time to build the cache map
		$this->imageCacheMap = array();
		foreach($this->imageCache as $img){
			$this->imageCacheMap[$img->getId()] = $img;
		}
		
		// time to call all the cache updaters!
		foreach($this->imageCacheUpdater as $u){
			$u();
		}
	}
	protected function getImagesFromCache($ids){
		$a = array();
		foreach($ids as $i){
			if(isset($this->imageCacheMap[$i])){
				$a[] = $this->imageCacheMap[$i];
			}else{
				$a[] = new InstructionFile;
			}
		}
		return $a;
	}
	protected function fullParseStep($i){
		global $modSettings,$sourcedir,$boardurl,$context,$scripturl;
		if(empty($this->steps[$i])){
			return false;
		}
		$step = &$this->steps[$i];
		if($step['full_parse']){ // no need to parse it again!
			return true;
		}
		$step['body_parsed'] = parse_bbc(htmlentities($step['body']));
		
		$this->imageIdCache = array_merge($this->imageIdCache,$step['image_ids']);
		
		$step['full_parse'] = true;
		
		$this->last_step_id = $step['id'];
		$this->last_step = $i;
		
		$this->imageCacheUpdater[] = function() use (&$step){
			$step['images'] = $this->getImagesFromCache($step['image_ids']);
		};
		return true;
	}
	public function preLoadSteps(){
		global $smcFunc,$scripturl,$modSettings,$sourcedir;
		if(sizeof($this->steps) > 0){ // nothing to do!
			return $this;
		}
		$result = $smcFunc['db_query']('','SELECT '.INSTRUCTIONS_STEPS_FETCH_VARS.' FROM {db_prefix}instructions_steps WHERE instruction_id={int:instr_id} ORDER BY sorder ASC',array(
			'instr_id' => $this->id
		));
		$i = 0;
		while($row = $smcFunc['db_fetch_assoc']($result)){
			$title = '';
			if($i != 0 && !isset($_REQUEST['allsteps']) && !empty($modSettings['pretty_enable_filters'])){
				include_once($sourcedir.'/Subs-PrettyUrls.php');
				$title = pretty_generate_url($step['title']);
			}
			
			$this->steps[$i] = array(
				'id' => (int)$row['id'],
				'body' => $row['body'],
				'image_ids' => InstructionsGetSQLArray($row['images']),
				'title' => $row['title'],
				'title_parsed' => parse_bbc(htmlentities($row['title'])),
				'main_image_id' => $row['main_image'],
				'full_parse' => false,
				'url' => $this->getUrl('',array(
						($i!=0 && !isset($_REQUEST['allsteps'])?'step':'') => $i,
						(!empty($title)?'stepname':'') => $title,
						($this->new_instruction != -1?'original':''),
						(isset($_REQUEST['allsteps'])?'allsteps':'')
					),isset($_REQUEST['allsteps'])?'step'.$i:''),
				'url_edit' => $this->getUrl('edit',array('step' => $i))
			);
			$this->imageIdCache = array_merge($this->imageIdCache,array($row['main_image']));
			
			$this->imageCacheUpdater[] = function() use ($i){
				$this->steps[$i]['main_image'] = $this->getImagesFromCache(array($this->steps[$i]['main_image_id']))[0];
			};
			$i++;
		}
		$smcFunc['db_free_result']($result);
		return $this;
	}
	public function getUrl($sa = '',$params = array(),$hash = ''){
		global $scripturl;
		if($hash != ''){
			$hash = '#'.$hash;
		}
		$reserved = array(
			'stepid' => $this->last_step_id
		);
		$addUrl = '';
		foreach($params as $i => $v){
			if($v === '' || $i === ''){
				continue;
			}
			if(is_int($i)){
				if(isset($reserved[$v])){
					$addUrl .= ';'.$v.'='.urlencode($reserved[$v]);
				}else{
					$addUrl .= ';'.urlencode($v);
				}
			}else{
				$addUrl .= ';'.urlencode($i).'='.urlencode($v);
			}
		}
		return $scripturl.'?action=instructions;id='.$this->dispId.($sa != ''?';sa='.$sa:'').$addUrl.$hash;
	}
	public function __construct($id,$follow = false,$depth = 0,$origDispId = ''){
		global $smcFunc,$sourcedir,$modSettings,$scripturl,$settings;
		if(is_array($id)){
			$instr = $id;
		}else{
			$id = str_replace("\x12#039;","\x12",str_replace('"',"\x12",str_replace("\x26","\x12",$id)));
			
			$request = $smcFunc['db_query']('',INSTRUCTIONS_SELECT.'WHERE i.url = {string:id} OR i.id = {string:id} LIMIT 1',array('id' => $id));
			$instr = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);
		}
		if($instr){
			$this->id = (int)$instr['id'];
			if($instr['url']!=''){
				$this->dispId = $instr['url'];
			}else{
				$this->dispId = (string)$this->id;
			}
			if($follow && !empty($instr['new_instruction']) && $instr['new_instruction']!=-1 && $depth <= 10){
				if($this->__construct($instr['new_instruction'],true,$depth+1,$origDispId == ''?$this->dispId:$origDispId)){
					return true;
				}
			}
			
			$this->exists = true;
			$this->owner = array(
				'id_member' => (int)$instr['id_member'],
				'member_name' => $instr['member_name'],
				'real_name' => $instr['real_name']
			);
			$this->name = $instr['name'];
			$this->name_parsed = parse_bbc(htmlspecialchars($instr['name']));
			$this->category = (int)$instr['category'];
			$this->upvotes = (int)$instr['upvotes'];
			$this->downvotes = (int)$instr['downvotes'];
			$this->topic_id = (int)$instr['topic_id'];
			$this->publish_date = (int)$instr['publish_date'];
			$this->new_instruction = (int)$instr['new_instruction'];
			if($origDispId != ''){ // as this is already the display ID we don't need to create the object only to fetch the display-friendly URL
				$this->origUrl = $scripturl.'?action=instructions;id='.$origDispId.';original';
			}
			if($origDispId == '' && !empty($instr['new_instruction']) && $instr['new_instruction'] != -1){
				$this->newUrl = (new Instruction($instr['new_instruction'],false))->url;
			}
			$this->status = (int)$instr['status'];
			$this->published = $instr['status'] >= 1;
			$this->main_image_id = (int)$instr['main_image'];
			$this->imageIdCache = array_merge($this->imageIdCache,array($instr['main_image']));
			$this->import_data = $instr['import_data'];
			$this->numSteps = (int)$instr['num_steps'];
			$this->views = (int)$instr['views'];
			
			$this->imageCacheUpdater[] = function(){
				$this->main_image = $this->getImagesFromCache(array($this->main_image_id))[0];
			};
		}
		$this->url = $this->getUrl();
		$this->html_headers = '<link rel="stylesheet" type="text/css" href="'.$settings['default_theme_url'].'/css/instructions.css?fin20" />
					<script type="text/javascript" src="'.$settings['default_theme_url'].'/scripts/instructions.js?fin20"></script>';
		return $instr?true:false;
	}
	public function loadStep($ids = 0){
		global $smcFunc;
		if(!$this->exists){
			return $this;
		}
		if($ids === 0){
			$ids = !empty($_REQUEST['step'])?(int)$_REQUEST['step']:0;
		}
		if($ids === 'all'){
			$ids = range(0,$this->numSteps - 1);
		}
		if(!is_array($ids)){
			$ids = array($ids);
		}
		
		$this->preLoadSteps(); // make sure the initial pre-loading is there!
		
		$ids = array_unique($ids); // we only want to be looking at unique ids!
		foreach($ids as $var){
			if(isset($this->steps[$var]) && !$this->steps[$var]['full_parse']){
				$this->fullParseStep($var);
			}
		}
		
		return $this;
	}
	public function loadSteps($ids = 0){
		return $this->loadStep($ids);
	}
	public function canView(){
		global $user_info;
		return ($this->published && allowedTo('inst_can_view_published')) || allowedTo('inst_can_view_unpublished_any') || ($user_info['id'] == $this->owner['id_member'] && allowedTo('inst_can_view_unpublished_own'));
	}
	public function canEdit(){
		global $user_info;
		return ($this->canView() || $this->id==-1) && (allowedTo('inst_can_edit_any') || (($this->id==-1||$user_info['id'] == $this->owner['id_member']) && allowedTo('inst_can_edit_own')));
	}
	public function canDelete(){
		global $user_info;
		return $this->canEdit() && (allowedTo('inst_can_delete_any') || ($user_info['id'] == $this->owner['id_member'] && allowedTo('inst_can_delete_any')));
	}
	public function mustView(){
		if(!$this->canView()){
			fatal_lang_error('instruction_cant_view',false);
		}
		return $this;
	}
	public function mustEdit(){
		if(!$this->canEdit()){
			fatal_lang_error('instruction_cant_edit',false);
		}
		return $this;
	}
	public function mustDelete(){
		if(!$this->canDelete()){
			fatal_lang_error('instruction_cant_delete',false);
		}
		return $this;
	}
	protected function loadLinkTree(){
		if($this->category == -1){
			return $this;
		}
		global $context, $scripturl, $mbname, $board, $board_info;
		$board = $this->category;
		loadBoard();
		$path = array(
			-1 => InstructionsGetRootCatName()
		);
		foreach($board_info['parent_boards'] as $key => $board){
			$path[$key] = $board['name'];
		}
		$path = array_reverse($path,true);
		$path[$this->category] = $board_info['name'];
		$board = 0;
		loadBoard();
		$context['linktree'] = array(
			array(
				'url' => $scripturl,
				'name' => $mbname
			)
		);
		foreach($path as $id => $name){
			$context['linktree'][] = array(
				'url' => $scripturl.'?action=instructions_cat'.($id!=-1?';id='.$id:''),
				'name' => $name
			);
		}
		$context['linktree'][] = array(
			'url' => $this->getUrl(),
			'name' => $this->name
		);
		return $this;
	}
	public function view(){
		global $context,$settings;
		$context['html_headers'] .= $this->html_headers;
		$context['page_title'] = $this->name;
		$context['sub_template'] = 'view';
		if(!$this->exists){
			fatal_lang_error('instruction_not_found',false);
		}
		loadMemberData((int)$this->owner['id_member']); // load some data about the author
		loadMemberContext((int)$this->owner['id_member']);
		return $this->mustView()->loadLinkTree()->get();
	}
	public function edit(){
		global $context,$settings,$smcFunc,$modSettings,$txt;
		$this->mustEdit();
		
		$context['page_title'] = $txt['inst_edit_title'].$this->name;
		
		$context['html_headers'] .= $this->html_headers.'<script type="text/javascript">
			instruction_edit_id = '.$this->id.'
		</script>';
		
		if($this->status == -1){
			$context['html_headers'] .= '<script type="text/javascript">
			instruction_import_data = '.json_encode(json_decode($this->import_data /* to make sure that we don't inject js */)).'
			</script>';
			$context['sub_template'] = 'import';
			return $this->get();
		}
		// fetching smileys from Subs-Editor.php start (modified)
		$sceditor_smileys = array();
		$sceditor_smileys_hidden = array();
		$smileys_baseurl = $modSettings['smileys_url'].'/'.$modSettings['smiley_sets_default'].'/';
		$request = $smcFunc['db_query']('', '
			SELECT code, filename, description, smiley_row, hidden
			FROM {db_prefix}smileys
			WHERE hidden IN (0, 2)
			ORDER BY smiley_row, smiley_order',
			array(
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if(empty($row['hidden'])){
				$sceditor_smileys[$row['code']] = $smileys_baseurl.$row['filename'];
			}else{
				$sceditor_smileys_hidden[$row['code']] = $smileys_baseurl.$row['filename'];
			}
		}
		$smcFunc['db_free_result']($request);
		// fetching smileys from Subs-Editor.php end
		
		$context['html_headers'] .= '
			<script type="text/javascript">
			SCEDITOR_SMILEYS = '.json_encode($sceditor_smileys).';
			SCEDITOR_SMILEYS_HIDDEN = '.json_encode($sceditor_smileys_hidden).';
			</script>
			<link rel="stylesheet" type="text/css" href="'.$modSettings['instructions_sceditor_url'].'/themes/default.min.css" media="all" />
			<script type="text/javascript" src="'.$modSettings['instructions_sceditor_url'].'/jquery.sceditor.bbcode.min.js"></script>
			<link rel="stylesheet" type="text/css" href="'.$settings['default_theme_url'].'/css/uploadfile.min.css?fin20" />
			<script type="text/javascript" src="'.$settings['default_theme_url'].'/scripts/jquery.form.min.js?fin20"></script>
			<script type="text/javascript" src="'.$settings['default_theme_url'].'/scripts/jquery.uploadfile.min.js?fin20"></script>
			<script type="text/javascript" src="'.$settings['default_theme_url'].'/scripts/jquery-ui.min.js?fin20"></script>
			<link rel="stylesheet" type="text/css" href="'.$settings['default_theme_url'].'/css/jquery-ui.min.css?fin20" />';
		
		$context['sub_template'] = 'edit';
		
		return $this->get();
	}
	protected $editstep = -1;
	public function goEdit(){
		$this->mustEdit();
		if($this->exists){
			array_shift($this->imageCacheUpdater); // we need to re-set it due to the different closure
		}
		$reflect = new ReflectionClass($this);
		$props = array();
		foreach($reflect->getProperties() as $p){
			$name = $p->name;
			$props[$name] = $this->$name;
		}
		return new EditInstruction($props);
	}
	public function editStep($stepid = -1){
		global $smcFunc;
		$this->mustEdit();
		if($stepid == -1){
			$stepid = !empty($_REQUEST['stepid'])?(int)$_REQUEST['stepid']:-1;
		}
		if($this->id != -1){
			$request = $smcFunc['db_query']('','SELECT id FROM {db_prefix}instructions_steps WHERE id = {int:stepid} AND instruction_id = {int:instruction_id} LIMIT 1',array('stepid' => $stepid,'instruction_id' => $this->id));
			if($smcFunc['db_num_rows']($request) == 0){
				fatal_lang_error('instruction_cant_edit',false);
			}
			$editstep = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);
			$this->editstep = (int)$editstep['id'];
		}else{
			$this->editstep = -1;
		}
		
		return $this->goEdit();
	}
	public function get(){
		$this->loadImagesInCache();
		return $this;
	}
	public function getTableRow(){
		global $scripturl;
		return array(
			'url' => $this->url,
			'name' => $this->name_parsed,
			'upvotes' => $this->upvotes,
			'downvotes' => $this->downvotes,
			'image' => $this->main_image_square(),
			'publish_date' => timeformat($this->publish_date),
			'views' => $this->views,
			'author' => array(
				'id' => $this->owner['id_member'],
				'url' => $scripturl.'?action=profile;u='.$this->owner['id_member'],
				'name' => $this->owner['member_name']
			),
			'published' => $this->published
		);
	}
	public function changeKarma($direction){
		global $smcFunc,$user_info;
		if(!$this->exists || !allowedTo('karma_edit')){
			fatal_lang_error('instruction_not_found',false);
		}
		$votes = array();
		$request = $smcFunc['db_query']('','SELECT votes FROM {db_prefix}instructions_members WHERE member_id = {int:id}',array('id' => $user_info['id']));
		if($res = $smcFunc['db_fetch_assoc']($request)){
			$votes = json_decode($res['votes'],true);
		}else{
			$smcFunc['db_insert']('insert','{db_prefix}instructions_members',
				array(
					'member_id' => 'int'
				),
				array(
					$user_info['id']
				),
				array('member_id')
			);
		}
		$smcFunc['db_free_result']($request);
		if(isset($votes[$this->id])){
			if($direction != $votes[$this->id]){
				// we actually have to do something
				$votes[$this->id] = $direction;
				if($direction == 1){
					$s = 'upvotes = upvotes + 1,downvotes = downvotes - 1';
				}else{
					$s = 'upvotes = upvotes - 1,downvotes = downvotes + 1';
				}
				$smcFunc['db_query']('','UPDATE {db_prefix}instructions_instructions SET '.$s.' WHERE id = {int:id}',array('id' => $this->id));
			}
		}else{
			$votes[$this->id] = $direction;
			if($direction == 1){
				$s = 'upvotes = upvotes + 1';
			}else{
				$s = 'upvotes = upvotes - 1';
			}
			$smcFunc['db_query']('','UPDATE {db_prefix}instructions_instructions SET '.$s.' WHERE id = {int:id}',array('id' => $this->id));
		}
		$smcFunc['db_query']('','UPDATE {db_prefix}instructions_members SET votes = {string:votes} WHERE member_id = {int:id}',array('id' => $user_info['id'],'votes' => json_encode($votes)));
		redirectexit($this->getUrl());
	}
	public function data(){
		header('Content-Type: application/json');
		$json = array(
			'name' => $this->name,
			'id' => $this->dispId,
			'steps' => $this->steps,
			'success' => true
		);
		
		echo json_encode($json);
		exit;
	}
}
class EditInstruction extends Instruction{
	public function __construct($props){
		header('Content-Type:text/plain');
		foreach($props as $var => $val){
			$this->$var = $val;
		}
		if($this->exists){
			// now set the main image updater again, this time with the correct closure
			$this->imageCacheUpdater[] = function(){
				$this->main_image = $this->getImagesFromCache(array($this->main_image_id))[0];
			};
		}
	}
	protected function updateFirstStep(){
		global $smcFunc;
		if(!$this->exists){
			return $this;
		}
		// unfortunately SMF doesn't allow multiple selects else i'd have used a joined update
		$request = $smcFunc['db_query']('','SELECT title,main_image FROM {db_prefix}instructions_steps WHERE instruction_id = {int:id} ORDER BY sorder',array('id' => $this->id));
		if($res = $smcFunc['db_fetch_assoc']($request)){
			$smcFunc['db_query']('','UPDATE {db_prefix}instructions_instructions
				SET main_image = {string:main_image},name = {string:title},num_steps = {int:steps}
				WHERE id={int:id}',array('id' => $this->id,'main_image' => $res['main_image'],'title' => $res['title'],'steps' => $smcFunc['db_num_rows']($request)));
		}
		$smcFunc['db_free_result']($request);
		return $this;
	}
	public function addStep(){
		global $smcFunc;
		if(!$this->exists){
			return $this;
		}
		
		$newSOrder = 0;
		$request = $smcFunc['db_query']('','SELECT MAX(sorder) AS sorder FROM {db_prefix}instructions_steps WHERE instruction_id = {int:id}',array('id' => $this->id));
		if($res = $smcFunc['db_fetch_assoc']($request)){
			$newSOrder = (int)$res['sorder']+1;
		}
		$smcFunc['db_insert']('insert','{db_prefix}instructions_steps',
			array(
				'sorder' => 'int', 'instruction_id' => 'int'
			),
			array(
				$newSOrder, $this->id
			),
			array('sorder','instruction_id')
		);
		$this->editstep = (int)$smcFunc['db_insert_id']('{db_prefix}instructions_steps','id');
		
		return $this->updateFirstStep();
	}
	protected $createdNewInstruction = false;
	protected $error = false;
	protected $error_msg = '';
	public function newInstruction(){
		global $smcFunc,$user_info;
		$smcFunc['db_insert']('insert','{db_prefix}instructions_instructions',
			array(
				'owner' => 'int', 'name' => 'string'
			),
			array(
				$user_info['id'], (isset($_REQUEST['title'])?$_REQUEST['title']:'')
			),
			array('owner','name')
		);
		$this->id = (int)$smcFunc['db_insert_id']('{db_prefix}instructions_instructions','id');
		$this->dispId = (string)$this->id;
		$this->createdNewInstruction = true;
		$this->exists = true;
		return $this;
	}
	public function deleteInstruction(){
		global $smcFunc;
		if(!$this->exists){
			return $this;
		}
		// delete steps
		$smcFunc['db_query']('','DELETE FROM {db_prefix}instructions_steps WHERE instruction_id = {int:id}',array('id' => $this->id));
		
		// delete instruction
		$smcFunc['db_query']('','DELETE FROM {db_prefix}instructions_instructions WHERE id = {int:id}',array('id' => $this->id));
		
		$this->exists = false;
		$this->id = -1;
		return $this;
	}
	public function deleteStep(){
		global $smcFunc;
		if(!$this->exists || $this->editstep == -1){
			return $this;
		}
		$smcFunc['db_query']('','DELETE FROM {db_prefix}instructions_steps WHERE id = {int:stepid}',array('stepid' => $this->editstep));
		$this->stepid = -1;
		return $this->updateFirstStep();
	}
	public function savePost(){
		global $smcFunc;
		if($this->id == -1){ // create new instruction!
			// we need to create a new instruction!
			$this->newInstruction()->addStep();
			
			if($this->editstep == -1){
				// don't forget to delete the dummy instruction!
				$this->deleteInstruction();
			}
			// now just process with the normal updating of the step
		}
		if($this->editstep == -1 || $this->id == -1){ // by now it should be a valid step
			return $this;
		}
		// time to save this thing!
		$updateArray = array();
		$updateParams = array();
		if(isset($_REQUEST['body'])){
			$updateArray[] = 'body = {string:body}';
			$updateParams['body'] = $_REQUEST['body'];
		}
		if(isset($_REQUEST['title'])){
			$updateArray[] = 'title = {string:title}';
			$updateParams['title'] = $_REQUEST['title'];
		}
		if(isset($_REQUEST['images'])){
			$json = json_decode($_REQUEST['images'],true);
			if(is_array($json)){
				$valid = !empty($json);
				if($valid){
					foreach($json as $i){
						if(!is_int($i)){
							$valid = false;
						}
					}
				}
				if($valid){
					$request2 = $smcFunc['db_query']('','SELECT id FROM {db_prefix}instructions_images WHERE id IN ('.implode(',', array_map('intval', $json)).') AND success=1 AND owner={int:owner}',array('owner' => $this->owner['id_member']));
					$valid = $smcFunc['db_num_rows']($request2) == sizeof($json);
					$smcFunc['db_free_result']($request2);
				}
				if($valid){
					$updateArray[] = 'images = {string:images}';
					$updateArray[] = 'main_image = {int:main_image}';
					$updateParams['images'] = InstructionsMakeSQLArray($json);
					$updateParams['main_image'] = (sizeof($json) > 0?$json[0]:-1);
				}
			}
		}
		$updateParams['id'] = $this->editstep;
		if(sizeof($updateArray) > 0){
			$smcFunc['db_query']('','UPDATE {db_prefix}instructions_steps SET '.implode(',',$updateArray).' WHERE id={int:id}',$updateParams);
		}
		
		return $this->updateFirstStep();
	}
	public function publish(){
		global $txt,$sourcedir,$smcFunc,$user_info,$board_info;
		if(!$this->exists){
			return $this;
		}
		if($this->main_image_id == -1){
			$this->error = true;
			$this->error_msg = $txt['inst_publish_main_image'];
			return $this;
		}
		if($this->name == ''){
			$this->error = true;
			$this->error_msg = $txt['inst_publish_no_name'];
			return $this;
		}
		$category = isset($_REQUEST['category'])?(int)$_REQUEST['category']:-1;
		if(!allowedTo('inst_publish_instruction',$category)){
			$this->error = true;
			$this->error_msg = $txt['inst_publish_wrong_cat'];
			return $this;
		}
		
		$updateVars = array(
			'id' => $this->id,
			'category' => $category
		);
		$updateParams = array(
			'status' => '1',
			'category' => '{int:category}'
		);
		
		if($this->dispId == $this->id){
			// time to generate a new URL!
			if(!empty($modSettings['pretty_enable_filters'])){
				include_once($sourcedir.'/Subs-PrettyUrls.php');
				$url = pretty_generate_url($this->name);
			}else{
				$url = preg_replace('/[^a-zA-Z0-9]+/','',$this->name);
			}
			
			// check if the URL is already taken
			$request = $smcFunc['db_query']('','SELECT url FROM {db_prefix}instructions_instructions WHERE url REGEXP {string:url}',array(
			'url' => '^'.str_replace(')','[)]',str_replace('(','[(]',$url)).'(-[0-9]+)?$'));
			$urlsTaken = array();
			while($res = $smcFunc['db_fetch_assoc']($request)){
				$urlsTaken[] = $res['url'];
			}
			$smcFunc['db_free_result']($request);
			
			// now loop through and add -<num> tot he url if it is taken
			for($i = 0;in_array($url,$urlsTaken);$url = $url.'-'.(++$i));
			
			$updateParams['url'] = '{string:url}';
			$updateVars['url'] = $url;
			
			$this->dispId = $url;
			$this->url = $this->getUrl();
		}
		
		if($this->topic_id == -1 && !empty($_REQUEST['post'])){
			// time to post a new topic!
			include_once($sourcedir.'/Subs-Post.php');
			$this->loadImagesInCache();
			$post = trim($_REQUEST['post']);
			$post = str_replace('{NAME}',$this->name,$post);
			$post = str_replace('{URL}',$this->url,$post);
			
			$post = str_replace('{IMG}',$this->main_image->getUrl('medium'),$post);
			
			
			$post = str_replace('{INTRO}',$this->steps[0]['body'],$post);
			
			$_POST['message'] = $post;
			$_POST['guestname'] = htmlspecialchars($user_info['username']);
			$_POST['email'] = htmlspecialchars($user_info['email']);
			$_POST['subject'] = strtr($smcFunc['htmlspecialchars']($this->name),array("\r" => '', "\n" => '', "\t" => ''));
			if ($smcFunc['strlen']($_POST['subject']) > 100){
				$_POST['subject'] = $smcFunc['substr']($_POST['subject'], 0, 100);
			}
			$_POST['icon'] = 'clip';
			$msgOptions = array(
				'id' => 0,
				'subject' => $_POST['subject'],
				'body' => $_POST['message'],
				'icon' => preg_replace('~[\./\\\\*:"\'<>]~', '', $_POST['icon']),
				'smileys_enabled' => !isset($_POST['ns']),
				'attachments' => empty($attachIDs) ? array() : $attachIDs,
				'approved' => true,
			);
			$topicOptions = array(
				'id' => 0,
				'board' => $category,
				'poll' => null,
				'lock_mode' => null,
				'sticky_mode' => null,
				'mark_as_read' => false,
				'is_approved' => true,
			);
			$posterOptions = array(
				'id' => $user_info['id'],
				'name' => $_POST['guestname'],
				'email' => $_POST['email'],
				'update_post_count' => !$user_info['is_guest'] && !isset($_REQUEST['msg']) && $board_info['posts_count'],
			);
			createPost($msgOptions, $topicOptions, $posterOptions);
			if (isset($topicOptions['id'])){
				$topic = $topicOptions['id'];
				$updateParams['topic_id'] = '{int:topic_id}';
				$updateVars['topic_id'] = $topic;
			}
		}
		if($this->publish_date == 0){
			$updateParams['publish_date'] = 'CURRENT_TIMESTAMP';
		}
		$updateString = '';
		foreach($updateParams as $p=>$v){
			$updateString != '' && $updateString .= ',';
			
			$updateString .= $p.'='.$v;
		}
		
		$smcFunc['db_query']('','UPDATE {db_prefix}instructions_instructions SET '.$updateString.' WHERE id={int:id}',$updateVars);
		return $this;
	}
	public function unpublish(){
		global $smcFunc;
		if(!$this->exists){
			return $this;
		}
		
		$request = $smcFunc['db_query']('','UPDATE {db_prefix}instructions_instructions SET status=0 WHERE id = {int:id}',array('id' => $this->id));
		
		return $this;
	}
	public function newVersion($newid = -1){
		global $txt,$smcFunc;
		if(!$this->exists){
			return $this;
		}
		if($newid == ''){
			$newid = -1;
		}
		$newid = (new Instruction($newid))->getId();
		if($newid == $this->id){
			$this->error = true;
			$this->error_msg = $txt['inst_newversion_self'];
			return $this;
		}
		
		$smcFunc['db_query']('','UPDATE {db_prefix}instructions_instructions SET new_instruction = {int:newid} WHERE id = {int:id}',array('id' => $this->id,'newid' => $newid));
		return $this;
	}
	public function saveStepOrder(){
		global $txt,$smcFunc;
		if(!$this->exists){
			return $this;
		}
		if(!isset($_REQUEST['steporder']) || !is_array($newStepIds = json_decode($_REQUEST['steporder'],true))){
			$this->error = true;
			$this->error_msg = $txt['inst_edit_invalid_data'];
			return $this;
		}
		$this->preLoadSteps();
		
		$oldStepIds = array();
		foreach($this->steps as $step){
			$oldStepIds[] = $step['id'];
		}
		
		if(!empty(array_merge(array_diff($newStepIds,$oldStepIds),array_diff($oldStepIds,$newStepIds)))){
			$this->error = true;
			$this->error_msg = $txt['inst_edit_invalid_data'];
			return $this;
		}
		foreach($newStepIds as $i => $sid){
			$smcFunc['db_query']('','UPDATE {db_prefix}instructions_steps SET sorder={int:sorder} WHERE id={int:id}',array('sorder' => $i,'id' => $sid));
		}
		return $this->updateFirstStep();
	}
	public function saveNotes($noteid){
		global $smcFunc,$user_info;
		$img = new InstructionFile($noteid);
		$id = $img->getId();
		if($id == -1){
			$this->error = true;
			$this->error_msg = 'No image note specified';
			return $this;
		}
		if($user_info['id'] != $img->getOwner()){
			$found = false;
			foreach($this->steps as $s){
				if(in_array($id,$s['image_ids'])){
					$found = true;
					break;
				}
			}
			if(!$found){
				$this->error = true;
				$this->error_msg = 'Permission denied';
				return $this;
			}
		}
		if(!isset($_REQUEST['annotations'])){
			$this->error = true;
			$this->error_msg = 'No post body specified';
			return $this;
		}
		$json = json_decode($_REQUEST['annotations'],true);
		if(!is_array($json)){
			$this->error = true;
			$this->error_msg = 'Invalid post body';
		}
		$img->setAnnotations($json);
		
		return $this;
	}
	public function data(){
		global $scripturl;
		$url = '';
		if($this->id == -1){
			$url = $scripturl;
		}else{
			$noeditActions = array(
				'delete',
				'publish'
			);
			$url = $this->getUrl(!empty($_REQUEST['sa']) && in_array($_REQUEST['sa'],$noeditActions)?'':'edit');
		}
		if(isset($_REQUEST['redirect'])){
			redirectexit($url);
		}
		header('Content-Type: application/json');
		echo json_encode(array(
			'success' => $this->id != -1 && $this->exists && !$this->error,
			'new_instruction' => $this->createdNewInstruction,
			'instruction_id' => $this->id,
			'step_id' => $this->editstep,
			'url' => $url,
			'msg' => $this->error_msg
		));
		exit;
	}
	public function import($json){
		global $smcFunc;
		if(!$json){
			$this->error = true;
			$this->error_msg = 'Invalid data format';
			return $this;
		}
		if(isset($_REQUEST['done'])){
			$smcFunc['db_query']('','UPDATE {db_prefix}instructions_instructions SET status=0,import_data="" WHERE id = {int:id}',array('id' => $this->id));
		}else{
			$smcFunc['db_query']('','UPDATE {db_prefix}instructions_instructions SET status=-1,import_data={string:data} WHERE id = {int:id}',array('id' => $this->id,'data' => json_encode($json)));
		}
		return $this;
	}
}

class InstructionFile{
	private $id = -1;
	private $name = '';
	private $owner = -1;
	private $urls = array(
		'original' => ''
	);
	private $annotations = array();
	private function getImageObject($res){
		global $modSettings;
		
		// get image URL sizes
		$this->id = (int)$res['id'];
		$this->owner = (int)$res['owner'];
		$imgdir = $modSettings['instructions_uploads_url'].'/'.$this->id.'/';
		$resizeTypes = explode(',',$res['resizeTypes']);
		$this->urls = array(
			'square' => $imgdir.'square.jpg',
			'largesquare' => $imgdir.'largesquare.jpg',
			'original' => $imgdir.'original.'.$res['extension']
		);
		if(in_array('small',$resizeTypes)){
			$this->urls['small'] = $imgdir.'small.jpg';
		}
		
		if(in_array('medium',$resizeTypes)){
			$this->urls['medium'] = $imgdir.'medium.jpg';
		}
		
		if(in_array('large',$resizeTypes)){
			$this->urls['large'] = $imgdir.'large.jpg';
		}
		
		
		$this->annotations = json_decode($res['annotations'],true);
		foreach($this->annotations as &$a){
			$a['body_parsed'] = parse_bbc(htmlentities($a['body']));
		}
		$this->name = $res['name'];
	}
	public function __construct($res = NULL){
		global $smcFunc;
		if($res && is_array($res)){
			$this->getImageObject($res);
		}elseif(is_int($res) || (is_string($res) && (int)$res == $res)){
			$request = $smcFunc['db_query']('','SELECT '.INSTRUCTIONS_IMAGES_FETCH_VARS.' FROM {db_prefix}instructions_images WHERE id={int:id} AND success=1',array('id' => $res));
			while($res = $smcFunc['db_fetch_assoc']($request)){
				$this->getImageObject($res);
			}
			$smcFunc['db_free_result']($request);
		}
	}
	public function getAnnotations(){
		return $this->annotations;
	}
	public function setAnnotations($json = array()){
		global $smcFunc;
		$newAnnotations = array();
		foreach($json as $j){
			if(array_is_of_pattern($j,array('x'=>0.0,'y'=>0.0,'w'=>0.0,'h'=>0.0,'body'=>'')) && $j['x'] < 1 && $j['y'] < 1 && $j['x'] >= 0 && $j['y'] >= 0 && $j['w'] > 0 && $j['h'] > 0){
				$j['w'] = min($j['w'],1-$j['x']);
				$j['h'] = min($j['h'],1-$j['y']);
				$newAnnotations[] = $j;
			}
		}
		$smcFunc['db_query']('','UPDATE {db_prefix}instructions_images SET annotations = {string:annotations} WHERE id={int:id}',array('annotations' => json_encode($newAnnotations),'id' => $this->id));
		$this->annotations = $newAnnotations;
	}
	public function getUrl($type = 'original'){
		if(isset($this->urls[$type])){
			return $this->urls[$type];
		}
		return $this->urls['original'];
	}
	public function getId(){
		return $this->id;
	}
	public function getJSON($extra_urls = array()){
		$urls = $this->urls;
		foreach($extra_urls as $u){
			$urls[$u] = $this->getUrl($u);
		}
		return array(
			'urls' => $urls,
			'id' => $this->id,
			'annotations' => $this->annotations,
			'name' => $this->name
		);
	}
	public function getOwner(){
		return $this->owner;
	}
	public function isOwner(){
		global $user_info;
		return $this->owner == $user_info['id'];
	}
	private function upload_internal($img_upload,$move = false){
		global $user_info, $smcFunc, $modSettings;
		if($this->id != -1){
			return 'Already an image!';
		}
		if(!allowedTo('inst_can_edit_any') && !allowedTo('inst_can_edit_own')){
			return 'Permission denied';
		}
		if(!$img_upload || !isset($img_upload['tmp_name']) || $img_upload['error'] !== 0){
			return 'Missing file';
		}
		if(!preg_match('#([ !\#$%\'()+-.\d;=@-\[\]-{}~]+)\.(\w+)$#',$img_upload['name'],$name)){
			return 'invalid filename';
		}
		$extension = strtolower($name[2]);
		if(!in_array($extension,array('png','gif','jpg','jpeg'))){
			return 'invalid file type! Allowed types: png, gif, jpg, jpeg';
		}
		if(!$img = @imagecreatefromstring(file_get_contents($img_upload['tmp_name']))){
			return 'uploaded file is not an image!';
		}
		$smcFunc['db_insert']('insert','{db_prefix}instructions_images',
			array(
				'owner' => 'int', 'extension' => 'string', 'name' => 'string'
			),
			array(
				$user_info['id'], $extension, $img_upload['name']
			),
			array('owner','extension','name')
		);
		$id = (int)$smcFunc['db_insert_id']('{db_prefix}instructions_images','id');
		
		mkdir($modSettings['instructions_uploads_path']."/$id");
		$imgFileName = $modSettings['instructions_uploads_path']."/$id/original.$extension";
		if(!(move_uploaded_file($img_upload['tmp_name'],$imgFileName) || ($move && rename($img_upload['tmp_name'],$imgFileName)))){
			return 'Could not move uploaded file';
		}
		$resizeTypes = array();
		list($width,$height) = @getimagesize($imgFileName);
		if($height > 1500){
			$img2 = imagecreatetruecolor(($width/$height)*1500,1500);
			imagecopyresized($img2,$img,0,0,0,0,($width/$height)*1500,1500,$width,$height);
			if(imagejpeg($img2,$modSettings['instructions_uploads_path']."/$id/large.jpg")){
				$resizeTypes[] = 'large';
			}
			imagedestroy($img2);
		}
		if($height > 600){
			$img2 = imagecreatetruecolor(($width/$height)*600,600);
			imagecopyresized($img2,$img,0,0,0,0,($width/$height)*600,600,$width,$height);
			if(imagejpeg($img2,$modSettings['instructions_uploads_path']."/$id/medium.jpg")){
				$resizeTypes[] = 'medium';
			}
			imagedestroy($img2);
		}
		if($height > 150){
			$img2 = imagecreatetruecolor(($width/$height)*150,150);
			imagecopyresized($img2,$img,0,0,0,0,($width/$height)*150,150,$width,$height);
			if(imagejpeg($img2,$modSettings['instructions_uploads_path']."/$id/small.jpg")){
				$resizeTypes[] = 'small';
			}
			imagedestroy($img2);
		}
		
		$img2 = imagecreatetruecolor(100,100);
		$srcx = 0;
		$srcy = 0;
		$srcw = $width;
		$srch = $height;
		if($width > $height){
			$srcw = $srch;
			$srcx = ($width - $height) / 2;
		}else{
			$srch = $srcw;
			$srcy = ($height - $width) / 2;
		}
		imagecopyresized($img2,$img,0,0,$srcx,$srcy,100,100,$srcw,$srch);
		if(imagejpeg($img2,$modSettings['instructions_uploads_path']."/$id/square.jpg")){
			$resizeTypes[] = 'square';
		}
		imagedestroy($img2);
		
		$img2 = imagecreatetruecolor(500,500);
		imagecopyresized($img2,$img,0,0,$srcx,$srcy,500,500,$srcw,$srch);
		if(imagejpeg($img2,$modSettings['instructions_uploads_path']."/$id/largesquare.jpg")){
			$resizeTypes[] = 'largesquare';
		}
		imagedestroy($img2);
		imagedestroy($img);
		$smcFunc['db_query']('','UPDATE {db_prefix}instructions_images SET success=1,resizeTypes={string:types} WHERE id={int:id}',array('types' => implode(',',$resizeTypes),'id' => $id));
		$this->getImageObject(array(
			'annotations' => false,
			'id' => (int)$id,
			'urls' => implode(',',$resizeTypes),
			'name' => $img_upload['name'],
			'owner' => $user_info['id'],
			'extension' => $extension
		));
		return '';
	}
	public function upload($file){
		if(is_array($file)){
			return $this->upload_internal($file);
		}
		global $sourcedir,$modSettings;
		include_once($sourcedir . '/Subs-Package.php');
		if(empty($_REQUEST['url'])){
			return 'missing required fields';
		}
		$url = $_REQUEST['url'];
		$contents = fetch_web_data($url);
		
		$tmp_filename = $modSettings['instructions_uploads_path'] . '/' . 'upload_tmp_' . $user_info['id'] . rand() . time();
		if (!$contents || !($tmpImg = fopen($tmp_filename, 'wb'))){
			return 'Could not download file';
		}
		fwrite($tmpImg, $contents);
		fclose($tmpImg);
		$url = parse_url($url);
		$name = explode('?',explode('#',trim($url['path']))[0])[0];
		$name = explode('/',$name);
		$name = $name[sizeof($name) - 1];
		
		$msg = $this->upload_internal(array(
			'tmp_name' => $tmp_filename,
			'error' => 0,
			'name' => $name
		),true);
		@unlink($tmp_filename);
		return $msg;
	}
	public function setTags($tags){
		global $smcFunc;
		if($this->id == -1){
			return 'image not found';
		}
		if(!is_array($tags)){
			if(!preg_match('/^[a-zA-Z0-9- _:.#]+(,[a-zA-Z0-9- _:.#]+)*,?$/',$tags) || $tags ===''){
				return 'invalid format';
			}
			$tags = explode(',',$tags);
		}
		$tags = array_filter($tags); // remove empty elements
		$tags = array_unique($tags); // remove duplicates
		$smcFunc['db_query']('','UPDATE {db_prefix}instructions_images SET tags = {string:tags} WHERE id={int:id}',array('tags' => InstructionsMakeSQLArray($tags),'id' => $this->id));
		
		// now update the cache for which tags one has
		$request = $smcFunc['db_query']('','SELECT tags FROM {db_prefix}instructions_members WHERE member_id = {int:id}',array('id' => $this->owner));
		if($res = $smcFunc['db_fetch_assoc']($request)){
			$smcFunc['db_free_result']($request);
			$tags = array_merge($tags,InstructionsGetSQLArray($res['tags']));
			$tags = array_unique($tags);
			$smcFunc['db_query']('','UPDATE {db_prefix}instructions_members SET tags = {string:tags} WHERE member_id={int:id}',array('tags' => InstructionsMakeSQLArray($tags),'id' => $this->owner));
		}else{
			$smcFunc['db_free_result']($request);
			$smcFunc['db_insert']('insert','{db_prefix}instructions_members',
				array(
					'member_id' => 'int', 'tags' => 'string'
				),
				array(
					$this->owner, InstructionsMakeSQLArray($tags)
				),
				array('member_id','tags')
			);
		}
		
		return '';
	}
	public function delete(){
		global $smcFunc,$modSettings;
		if($this->id == -1){
			return 'no such image';
		}
		foreach(glob($modSettings['instructions_uploads_path'].'/'.$this->id.'/*') as $f){
			if(!unlink($f)){
				return 'Could not remove image';
			}
		}
		if(!rmdir($modSettings['instructions_uploads_path'].'/'.$this->id)){
			return 'Could not delete folder';
		}
		$smcFunc['db_query']('','UPDATE {db_prefix}instructions_images SET success=-1 WHERE id = {int:imgid}',array('imgid' => $this->id));
		return '';
	}
}

function InstructionsGetPublishCats_Recursion($node){
	$children = array();
	foreach($node['tree']['children'] as $child){
		$children[] = InstructionsGetPublishCats_Recursion($child['node']);
	}
	return array(
		'id' => (int)$node['id'],
		'name' => $node['name'],
		'canpublish' => $node['id']!=-1 && (bool)allowedTo('inst_publish_instruction',$node['id']),
		'children' => $children
	);
}

function InstructionsGetPublishCats(){
	global $context, $modSettings, $scripturl, $txt, $settings;
	global $user_info, $smcFunc, $board, $sourcedir, $board_info;
	global $boards,$boardList,$cat_tree;
	$base_cat = (isset($modSettings['instructions_category'])?(int)$modSettings['instructions_category']:1);
	include_once($sourcedir . '/Subs-Boards.php');
	getBoardTree();
	$children = array();
	foreach($boards as $id => $b){
		if($b['level'] == 0 && $b['category'] == $base_cat){
			$children[] = array(
				'node' => $b
			);
		}
	}
	
	return InstructionsGetPublishCats_Recursion(array(
		'id' => -1,
		'name' => InstructionsGetRootCatName(),
		'tree' => array(
			'children' => $children
		)
	));
}



function InstructionsMakeSQLArray($a){
	$s = '';
	foreach($a as $i){
		$s .= '['.$i.']';
	}
	return $s;
}

function InstructionsGetSQLArray($s){
	$a = array();
	foreach(explode('[',$s) as $i){
		if($i!=']' && $i!=''){
			$a[] = substr($i,0,-1);
		}
	}
	return $a;
}

function InstructionsGetCatOrderBy($offset){
	global $context;
	$sortmap = array(
		'name' => 'i.name',
		'rating' => '(i.upvotes - i.downvotes)',
		'views' => 'i.views',
		'date' => 'i.publish_date',
		'author' => 'u.member_name'
	);
	$directionmap = array(
		'asc' => 'ASC',
		'desc' => 'DESC'
	);
	$sort = 'date';
	$direction = 'desc';
	$context['sort_by'] = 'default';
	$context['sort_direction'] = 'down';
	if(isset($_REQUEST['sort']) && isset($sortmap[strtolower($_REQUEST['sort'])])){
		$direction = 'asc';
		$sort = strtolower($_REQUEST['sort']);
		
		if(isset($_REQUEST['desc'])){
			$direction = 'desc';
		}
		if(isset($_REQUEST['asc'])){
			$direction = 'asc';
		}
		
	}
	$context['sort_by'] = $sort;
	$context['sort_direction'] = $direction=='asc'?'up':'down';
	
	if(isset($_REQUEST['start']) && $_REQUEST['start'] == (int)$_REQUEST['start']){
		$offset = $_REQUEST['start'];
	}
	return 'ORDER BY '.$sortmap[$sort].' '.$directionmap[$direction].' LIMIT '.((int)$offset).',30';
}

function InstructionsGetInstructions($where,$vars,$offset){
	global $context, $modSettings, $scripturl, $txt, $settings, $mbname;
	global $user_info, $smcFunc, $board, $sourcedir, $board_info;
	$instructions = array();
	
	
	$request = $smcFunc['db_query']('','SELECT id FROM {db_prefix}instructions_instructions i WHERE '.$where,$vars);
	$numInstructions = $smcFunc['db_num_rows']($request);
	$smcFunc['db_free_result']($request);
	$request = $smcFunc['db_query']('',INSTRUCTIONS_SELECT.'WHERE '.$where.' '.InstructionsGetCatOrderBy($offset),$vars);
	while($res = $smcFunc['db_fetch_assoc']($request)){
		$instructions[] = (new Instruction($res))->getTableRow();
	}
	$smcFunc['db_free_result']($request);
	
	return array(
		'instructions' => $instructions,
		'num_instructions' => $numInstructions,
		'offset' => $offset
	);
}


function InstructionsGetRootCatName(){
	global $modSettings, $smcFunc;
	$base_cat = (isset($modSettings['instructions_category'])?(int)$modSettings['instructions_category']:1);
	$request = $smcFunc['db_query']('','SELECT name FROM {db_prefix}categories WHERE id_cat={int:id}',array('id' => $base_cat));
	$res = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);
	return $res['name'];
}

function InstructionsDisplayCat(){
	global $context, $modSettings, $scripturl, $txt, $settings, $mbname;
	global $user_info, $smcFunc, $board, $sourcedir, $board_info;
	$base_cat = (isset($modSettings['instructions_category'])?(int)$modSettings['instructions_category']:1);
	$offset = 0;
	
	$cat_id = isset($_REQUEST['id'])?$_REQUEST['id']:-1;
	
	if(is_string($cat_id) && strpos($cat_id,'.') /* no need for !== false as it may not be first element anyways */){
		$cat_id = explode('.',$cat_id);
		$offset = (int)$cat_id[1];
		$cat_id = (int)$cat_id[0];
	}
	
	if($cat_id != (int)$cat_id){
		$cat_id = -1;
	}
	$request = $smcFunc['db_query']('','SELECT name FROM {db_prefix}categories WHERE id_cat={int:id}',array('id' => $base_cat));
	$res = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);
	$path = array(
		-1 => InstructionsGetRootCatName()
	);
	if($cat_id == -1){
		$context['instruction_cat'] = array(
			'name' => $path[-1],
			'id' => -1,
			'path' => $path
		);
		$children = array();
		
		$request = $smcFunc['db_query']('','SELECT id_board,name FROM {db_prefix}boards WHERE id_parent = 0 AND id_cat={int:id} ORDER BY board_order ASC',array('id' => $base_cat));
		while($res = $smcFunc['db_fetch_assoc']($request)){
			$children[(int)$res['id_board']] = $res['name'];
		}
		$smcFunc['db_free_result']($request);
		
	}else{
		$board = $cat_id;
		loadBoard();
		
		if(!$board_info ||$board_info['cat']['id'] != $base_cat){
			$_REQUEST['id'] = -1;
			InstructionsDisplayCat();
			return;
		}
		foreach($board_info['parent_boards'] as $key => $board){
			$path[$key] = $board['name'];
		}
		$path = array_reverse($path,true); // true to also preserve the index
		
		$context['instruction_cat'] = array(
			'name' => $board_info['name'],
			'id' => $board_info['id'],
			'path' => $path
		);
		$children = array();
		$request = $smcFunc['db_query']('','SELECT id_board,name FROM {db_prefix}boards WHERE id_parent = {int:id} ORDER BY board_order ASC',array('id' => $board_info['id']));
		while($res = $smcFunc['db_fetch_assoc']($request)){
			$children[(int)$res['id_board']] = $res['name'];
		}
		$smcFunc['db_free_result']($request);
		
		$context['instruction_cat'] = array_merge($context['instruction_cat'],InstructionsGetInstructions('i.category={int:catid} AND status=1',array('catid' => $board_info['id']),$offset));
	}
	
	
	
	$board = 0; // make sure that no board is loaded, else some page style stuff will be off
	loadBoard();
	
	$context['linktree'] = array(
		array(
			'url' => $scripturl,
			'name' => $mbname
		)
	);
	
	foreach($context['instruction_cat']['path'] as $id => $name){
		$context['linktree'][] = array(
			'url' => $scripturl.'?action=instructions_cat'.($id!=-1?';id='.$id:''),
			'name' => $name
		);
	}
	if($cat_id != -1){
		$context['linktree'][] = array(
			'url' => $scripturl.'?action=instructions_cat'.($cat_id!=-1?';id='.$cat_id:''),
			'name' => $context['instruction_cat']['name']
		);
	}
	loadTemplate('Instructions');
	
	$context['instruction_cat']['children'] = $children;
	$context['instruction_cat']['page_index'] = constructPageIndex($scripturl.'?action=instructions_cat;id='.$cat_id,$context['instruction_cat']['offset'],$context['instruction_cat']['num_instructions'],30,false);
	$context['instruction_cat']['caturl'] = $scripturl.'?action=instructions_cat;id='.$cat_id.($context['instruction_cat']['offset']>0?'.'.$context['instruction_cat']['offset']:'');
	
	
	$context['html_headers'] .= '<link rel="stylesheet" type="text/css" href="'.$settings['default_theme_url'].'/css/instructions.css?fin20" />';
	$context['html_headers'] .= '<script type="text/javascript" src="'.$settings['default_theme_url'].'/scripts/instructions.js?fin20"></script>';
	$context['page_title'] = $context['instruction_cat']['name'];
	$context['sub_template'] = 'category';
}

function InstructionsLoadProfile($member_id){
	global $context, $modSettings, $scripturl, $txt, $settings, $mbname;
	global $user_info, $smcFunc, $board, $sourcedir, $board_info;
	if(!allowedTo('inst_can_view_published')){
		$context['instructions'] = array();
		return;
	}
	$context['instructions'] = InstructionsGetInstructions(
		'(owner={int:member_id}'.(
			allowedTo('inst_can_view_unpublished_any') || ($user_info['id'] == $member_id && allowedTo('inst_can_view_unpublished_own'))?'':' AND status=1').
		')',array('member_id' => $member_id),0);
	
	$context['instructions']['page_index'] = constructPageIndex($scripturl.'?action=profile;u='.$member_id,$context['instructions']['offset'],$context['instructions']['num_instructions'],30,false);
	$context['instructions']['caturl'] = $scripturl.'?action=profile;u='.$member_id.($context['instructions']['offset']>0?';start='.$context['instructions']['offset']:'');
}

function array_is_of_pattern($array,$pattern){
	if(!is_array($array)){
		return false;
	}
	return array_map('gettype',$array) == array_map('gettype',$pattern);
}

function InstructionsGetLibrary($tag = -1){
	global $context, $modSettings, $scripturl, $txt, $settings;
	global $user_info, $smcFunc, $board;
	header('Content-Type: text/javascript');
	$all = $tag  == -1 || !preg_match('/^[a-zA-Z0-9- _:.#]+$/',$tag); // $tag is clean now and stuff!
	$offset = 0;
	if(isset($_REQUEST['offset'])){
		$offset = (int)$_REQUEST['offset'];
	} // SELECT * FROM `smf_instructions_images` WHERE `tags` LIKE '%test,%' 
	if($all){
		$tag = '%';
	}else{
		$tag = '%['.$tag.']%';
	}
	$request = $smcFunc['db_query']('','SELECT '.INSTRUCTIONS_IMAGES_FETCH_VARS.' FROM {db_prefix}instructions_images WHERE owner = {int:member_id} AND tags LIKE {string:tag} AND success=1 ORDER BY id DESC LIMIT {int:offset},30',array('member_id' => $user_info['id'],'tag'=>$tag,'offset'=>$offset));
	$images = array();
	while($res = $smcFunc['db_fetch_assoc']($request)){
		$images[] = (new InstructionFile($res))->getJSON(array('small','medium','large'));
	}
	$smcFunc['db_free_result']($request);
	
	$pages = 0;
	$max = 0;
	if(!empty($images)){
		$request = $smcFunc['db_query']('','SELECT COUNT(id) AS max FROM {db_prefix}instructions_images WHERE owner = {int:member_id} AND tags LIKE {string:tag} AND success=1',array('member_id' => $user_info['id'],'tag'=>$tag));
		if($res = $smcFunc['db_fetch_assoc']($request)){
			$max = (int)$res['max'];
			$pages = ceil($max / 30);
		}
		$smcFunc['db_free_result']($request);
	}
	echo json_encode(array(
		'pages' => $pages,
		'max' => $max,
		'images' => $images
	));
	exit;
}


?>
