<?php
if (!defined('SMF'))
	die('Hacking attempt...');


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
		default:
			$instr->loadSteps(isset($_REQUEST['allsteps'])?'all':0)->view();
			break;
		
	}
	return;
	if(isset($_REQUEST['sa']) && isset($sub_actions[$_REQUEST['sa']])){
		$sub_actions[$_REQUEST['sa']]();
		return;
	}
	//InstructionsDisplay();
	//return;
	if(isset($_REQUEST['id'])){
		InstructionsDisplay($_REQUEST['id'],(isset($_REQUEST['step'])?$_REQUEST['step']:0));
		return;
	}
	if(isset($_REQUEST['edit'])){
		InstructionsEdit($_REQUEST['edit'],(isset($_REQUEST['step'])?$_REQUEST['step']:0));
		return;
	}
	if(isset($_REQUEST['save'])){
		InstructionsSave($_REQUEST['save'],(isset($_REQUEST['stepid'])?$_REQUEST['stepid']:0));
		return;
	}
	if(isset($_REQUEST['savesteporder'])){
		InstructionsSaveStepOrder($_REQUEST['savesteporder']);
		return;
	}
	if(isset($_REQUEST['addstep'])){
		InstructionsAddStep($_REQUEST['addstep']);
		return;
	}
	if(isset($_REQUEST['deletestep'])){
		InstructionsDeleteStep($_REQUEST['deletestep'],(isset($_REQUEST['stepid'])?$_REQUEST['stepid']:-1));
		return;
	}
	if(isset($_REQUEST['delete'])){
		InstructionsDelete($_REQUEST['delete']);
		return;
	}
	if(isset($_REQUEST['fileupload'])){
		InstructionsUpload();
		return;
	}
	if(isset($_REQUEST['urlupload'])){
		InstructionsUrlUpload();
		return;
	}
	if(isset($_REQUEST['savenotes'])){
		InstructionsSaveNotes($_REQUEST['savenotes']);
		return;
	}
	if(isset($_REQUEST['setimgtags'])){
		InstructionsSetImgTags($_REQUEST['setimgtags']);
		return;
	}
	if(isset($_REQUEST['getimgtags'])){
		header('Content-Type: text/json');
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
	}
	if(isset($_REQUEST['getlibrary'])){
		InstructionsGetLibrary($_REQUEST['getlibrary']);
		return;
	}
	if(isset($_REQUEST['new'])){
		redirectexit(InstructionsGetURL(-1,0,'Create a new instruction','',true));
	}
	if(isset($_REQUEST['cat'])){
		InstructionsDisplayCat($_REQUEST['cat']);
		return;
	}
	if(isset($_REQUEST['deleteimage'])){
		InstructionsDeleteImage($_REQUEST['deleteimage']);
		return;
	}
	if(isset($_REQUEST['getpublishcats'])){
		header('Content-Type:application/json');
		echo json_encode(array('categories' => InstructionsGetPublishCats()));
		exit;
	}
	if(isset($_REQUEST['unpublish'])){
		InstructionsUnpublish($_REQUEST['unpublish']);
		return;
	}
	if(isset($_REQUEST['publish'])){
		InstructionsPublish($_REQUEST['publish']);
		return;
	}
	if(isset($_REQUEST['upvote'])){
		InstructionsKarma($_REQUEST['upvote'],+1);
	}
	if(isset($_REQUEST['downvote'])){
		InstructionsKarma($_REQUEST['downvote'],-1);
	}
	if(isset($_REQUEST['newversion'])){
		InstructionsNewVersion($_REQUEST['newversion']);
		exit;
	}
	if(isset($_REQUEST['getinstructable'])){
		InstructionsGetInstructable($_REQUEST['getinstructable']);
		exit;
	}
	if(isset($_REQUEST['iblesimport'])){
		InstructionsIblesImport($_REQUEST['iblesimport']);
		exit;
	}
	if(isset($_REQUEST['getimages'])){
		InstructionsGetImageIdsForStep($_REQUEST['getimages']);
	}
	InstructionsDisplayCat();
}

class Instruction{
	public $exists = false;
	protected $id = -1;
	protected $dispId = '-1';
	public $steps = array();
	protected $numSteps = 0;
	protected $owner = -1;
	public $name = '';
	public $name_parsed = '';
	protected $category = -1;
	public $upvotes = 0;
	public $downvotes = 0;
	protected $topic_id = -1;
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
	protected function makeSqlArray($a){
		$s = '';
		foreach($a as $i){
			$s .= '['.$i.']';
		}
		return $s;
	}
	protected function getSqlArray($s){
		$a = array();
		foreach(explode('[',$s) as $i){
			if($i!=']' && $i!=''){
				$a[] = substr($i,0,-1);
			}
		}
		return $a;
	}
	public function getId(){
		return $this->id;
	}
	protected $sql_image_fetch_vars = 'id,annotations,extension,resizeTypes,name';
	protected function getImageObject($res){
		global $modSettings;
		
		// get image URL sizes
		$id = (int)$res['id'];
		$imgdir = $modSettings['instructions_uploads_url']."/$id/";
		$resizeTypes = explode(',',$res['resizeTypes']);
		$urls = array(
			'square' => $imgdir.'square.jpg',
			'largesquare' => $imgdir.'largesquare.jpg',
			'original' => $imgdir.'original.'.$res['extension']
		);
		if(in_array('small',$resizeTypes)){
			$urls['small'] = $imgdir.'small.jpg';
		}else{
			$urls['small'] = $urls['original'];
		}
		
		if(in_array('medium',$resizeTypes)){
			$urls['medium'] = $imgdir.'medium.jpg';
		}else{
			$urls['medium'] = $urls['original'];
		}
		
		if(in_array('large',$resizeTypes)){
			$urls['large'] = $imgdir.'large.jpg';
		}else{
			$urls['large'] = $urls['original'];
		}
		
		
		$annotations = json_decode($res['annotations'],true);
		foreach($annotations as &$a){
			$a['body_parsed'] = parse_bbc(htmlentities($a['body']));
		}
		return array(
			'urls' => $urls,
			'id' => $id,
			'annotations' => $annotations,
			'name' => $res['name']
		);
	}
	protected function getImages($ids){
		global $smcFunc;
		if(sizeof($ids) == 0 || !$ids){
			return array();
		}
		
		$a = array();
		$request = $smcFunc['db_query']('','SELECT '.$this->sql_image_fetch_vars.' FROM {db_prefix}instructions_images WHERE id IN ('.implode(',', array_map('intval', $ids)).') AND success=1',array());
		while($res = $smcFunc['db_fetch_assoc']($request)){
			$a[$res['id']] = $this->getImageObject($res);
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
		foreach($this->imageCache as $i => $img){
			$this->imageCacheMap[$img['id']] = $i;
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
				$a[] = $this->imageCache[$this->imageCacheMap[$i]];
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
		$result = $smcFunc['db_query']('','SELECT id,body,images,title,main_image FROM {db_prefix}instructions_steps WHERE instruction_id={int:instr_id} ORDER BY sorder ASC',array(
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
				'image_ids' => $this->getSqlArray($row['images']),
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
			
			$this->imageIdCache = array_merge($this->imageIdCache,$this->getSqlArray($row['images']),array($row['main_image']));
			
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
		return $scripturl.'?action=instructions'.($sa != ''?';sa='.$sa:'').';id='.$this->dispId.$addUrl.$hash;
	}
	public function __construct($id,$follow = false,$depth = 0,$origDispId = ''){
		global $smcFunc,$sourcedir,$modSettings,$scripturl,$settings;
		if(is_array($id)){
			$instr = $id;
		}else{
			$id = str_replace("\x12#039;","\x12",str_replace('"',"\x12",str_replace("\x26","\x12",$id)));
			
			$request = $smcFunc['db_query']('','SELECT id,main_image,owner,name,url,status,category,upvotes,downvotes,topic_id,UNIX_TIMESTAMP(publish_date) AS publish_date,new_instruction,import_data FROM {db_prefix}instructions_instructions WHERE url = {string:id} OR id = {string:id} LIMIT 1',array('id' => $id));
			$instr = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);
		}
		if($instr){
			$this->id = (int)$instr['id'];
			if($instr['url']!=''){
				$this->dispId = $instr['url'];
			}else{
				$this->dispId = $id;
			}
			if($follow && !empty($instr['new_instruction']) && $instr['new_instruction']!=-1 && $depth <= 10){
				if($this->__construct($instr['new_instruction'],true,$depth+1,$origDispId == ''?$this->dispId:$origDispId)){
					return true;
				}
			}
			
			$this->exists = true;
			$this->owner = (int)$instr['owner'];
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
			
			$this->imageCacheUpdater[] = function(){
				$this->main_image = $this->getImagesFromCache(array($this->main_image_id))[0];
			};
			
			$request = $smcFunc['db_query']('','SELECT id FROM {db_prefix}instructions_steps WHERE instruction_id={int:id}',array('id' => $this->id));
			$this->numSteps = $smcFunc['db_num_rows']($request);
			$smcFunc['db_free_result']($request);
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
		foreach($ids as $i => $var){
			if(isset($this->steps[$var]) && !$this->steps[$var]['full_parse']){
				$unset = $this->fullParseStep($var);
			}
		}
		
		return $this;
	}
	public function loadSteps($ids = 0){
		return $this->loadStep($ids);
	}
	public function canView(){
		global $user_info;
		return ($this->published && allowedTo('inst_can_view_published')) || allowedTo('inst_can_view_unpublished_any') || ($user_info['id'] == $this->owner && allowedTo('inst_can_view_unpublished_own'));
	}
	public function canEdit(){
		global $user_info;
		return ($this->canView() || $this->id==-1) && (allowedTo('inst_can_edit_any') || (($this->id==-1||$user_info['id'] == $this->owner) && allowedTo('inst_can_edit_own')));
	}
	public function canDelete(){
		global $user_info;
		return $this->canEdit() && (allowedTo('inst_can_delete_any') || ($user_info['id'] == $this->owner && allowedTo('inst_can_delete_any')));
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
	public function view(){
		global $context,$settings;
		$context['html_headers'] .= $this->html_headers;
		$context['page_title'] = $this->name;
		$context['sub_template'] = 'view';
		if($this->id == -1){
			fatal_lang_error('instruction_not_found',false);
		}
		return $this->mustView()->get();
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
		// unfortunately SMF doesn't allow multiple selects else i'd have used a joined update
		$request = $smcFunc['db_query']('','SELECT title,main_image FROM {db_prefix}instructions_steps WHERE instruction_id = {int:id} ORDER BY sorder ASC LIMIT 1',array('id' => $this->id));
		if($res = $smcFunc['db_fetch_assoc']($request)){
			$smcFunc['db_query']('','UPDATE {db_prefix}instructions_instructions
				SET main_image = {string:main_image},name = {string:title}
				WHERE id={int:id}',array('id' => $this->id,'main_image' => $res['main_image'],'title' => $res['title']));
		}
		return $this;
	}
	public function addStep(){
		global $smcFunc;
		if($this->id == -1){ // we actually need this to be an instruction!
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
		
		return $this;
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
		return $this->updateFirstStep();
	}
	public function deleteStep(){
		global $smcFunc;
		if(!$this->exists || $this->editstep == -1){
			return $this;
		}
		$smcFunc['db_query']('','DELETE FROM {db_prefix}instructions_steps WHERE id = {int:stepid}',array('stepid' => $this->editstep));
		$this->stepid = -1;
		return $this;
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
					$request2 = $smcFunc['db_query']('','SELECT id FROM {db_prefix}instructions_images WHERE id IN ('.implode(',', array_map('intval', $json)).') AND success=1 AND owner={int:owner}',array('owner' => $this->owner));
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
			$this->preLoadSteps()->loadImagesInCache();
			$post = trim($_REQUEST['post']);
			$post = str_replace('{NAME}',$this->name,$post);
			$post = str_replace('{URL}',$this->url,$post);
			
			$post = str_replace('{IMG}',$this->main_image['urls']['medium'],$post);
			
			
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
	public function data(){
		global $scripturl;
		$url = '';
		if($this->id == -1){
			$url = $scripturl.'?action=instructions';
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
		header('Content-Type:text/json');
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
}


function InstructionsGetPublishCats_Recursion($node){
	$children = array();
	foreach($node['tree']['children'] as $child){
		$children[] = InstructionsGetPublishCats_Recursion($child['node']);
	}
	return array(
		'id' => (int)$node['id'],
		'name' => $node['name'],
		'canpublish' => (bool)allowedTo('inst_publish_instruction',$node['id']),
		'children' => $children
	);
}

function InstructionsGetPublishCats(){
	global $context, $modSettings, $scripturl, $txt, $settings;
	global $user_info, $smcFunc, $board, $sourcedir, $board_info;
	global $boards,$boardList,$cat_tree;
	$base_cat = (isset($modSettings['instructions_board'])?(int)$modSettings['instructions_board']:1);
	include_once($sourcedir . '/Subs-Boards.php');
	getBoardTree();
	return InstructionsGetPublishCats_Recursion($boards[$base_cat]);
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
		'name' => 't1.name',
		'rating' => '(t1.upvotes - t1.downvotes)',
		'views' => 't1.views',
		'date' => 't1.publish_date',
		'author' => 't2.member_name'
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
	
	
	$request = $smcFunc['db_query']('','SELECT id FROM {db_prefix}instructions_instructions t1 WHERE '.$where,$vars);
	$numInstructions = $smcFunc['db_num_rows']($request);
	$smcFunc['db_free_result']($request);
	$request = $smcFunc['db_query']('','SELECT t2.member_name,t2.id_member,t1.url,t1.views,t1.upvotes,t1.downvotes,t1.id AS instruction_id,
					t1.name,t1.main_image,t1.id,UNIX_TIMESTAMP(t1.publish_date) AS publish_date,t2.real_name,t1.views,t1.status,t1.new_instruction
				FROM {db_prefix}instructions_instructions t1 INNER JOIN {db_prefix}members t2 ON t1.owner=t2.id_member
				WHERE '.$where.' '.InstructionsGetCatOrderBy($offset),$vars);
	while($res = $smcFunc['db_fetch_assoc']($request)){
		$dispId = $res['instruction_id'];
		if($res['url']!=''){
			$dispId = $res['url'];
		}
		$instructions[] = array(
			'url' => InstructionsGetURL($dispId).($res['new_instruction']!=-1?';original':''),
			'name' => $res['name'],
			'upvotes' => (int)$res['upvotes'],
			'downvotes' => (int)$res['downvotes'],
			'image' => '<img width="100" height="100" src="'.htmlentities($modSettings['instructions_uploads_url'].'/'.$res['main_image'].'/square.jpg').'" alt="'.htmlentities($res['name']).'" />',
			'publish_date' => timeformat($res['publish_date']),
			'views' => (int)$res['views'],
			'author' => array(
				'id' => (int)$res['id_member'],
				'url' => $scripturl.'?action=profile;u='.$res['id_member'],
				'name' => $res['member_name']
			),
			'published' => $res['status'] >= 1
		);
	}
	$smcFunc['db_free_result']($request);
	return array(
		'instructions' => $instructions,
		'num_instructions' => $numInstructions,
		'offset' => $offset
	);
}

function InstructionsDisplayCat($cat_id = -1){
	global $context, $modSettings, $scripturl, $txt, $settings, $mbname;
	global $user_info, $smcFunc, $board, $sourcedir, $board_info;
	$base_cat = (isset($modSettings['instructions_board'])?(int)$modSettings['instructions_board']:1);
	$offset = 0;
	
	if(is_string($cat_id) && strpos($cat_id,'.') /* no need for !== false as it may not be first element anyways */){
		$cat_id = explode('.',$cat_id);
		$offset = (int)$cat_id[1];
		$cat_id = (int)$cat_id[0];
	}
	
	if($cat_id != (int)$cat_id || (int)$cat_id <= 0){
		$cat_id = $base_cat;
	}
	
	$board = $cat_id;
	loadBoard();
	if(!$board_info || ($board_info['id'] != $base_cat && empty($board_info['parent_boards'][$base_cat]))){
		$board = $base_cat;
		loadBoard();
	}
	$path = array();
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
	
	
	$context['instruction_cat'] = array_merge($context['instruction_cat'],InstructionsGetInstructions('t1.category={int:catid} AND status=1',array('catid' => $board_info['id']),$offset));
	
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
			'url' => $scripturl.'?action=instructions'.($id!=$base_cat?';cat='.$id:''),
			'name' => $name
		);
	}
	$context['linktree'][] = array(
		'url' => $scripturl.'?action=instructions'.($cat_id!=$base_cat?';cat='.$cat_id:''),
		'name' => $context['instruction_cat']['name']
	);
	
	loadTemplate('Instructions');
	
	$context['instruction_cat']['children'] = $children;
	$context['instruction_cat']['page_index'] = constructPageIndex($scripturl.'?action=instructions;cat='.$cat_id,$context['instruction_cat']['offset'],$context['instruction_cat']['num_instructions'],30,false);
	$context['instruction_cat']['caturl'] = $scripturl.'?action=instructions;cat='.$cat_id.($context['instruction_cat']['offset']>0?'.'.$context['instruction_cat']['offset']:'');
	
	
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



function InstructionsSaveStepOrder($id){
	global $context, $modSettings, $scripturl, $txt, $settings;
	global $user_info, $smcFunc, $board;
	
	header('Content-Type: text/json');
	
	$request = $smcFunc['db_query']('','SELECT id,owner,name,url,status FROM {db_prefix}instructions_instructions WHERE url = {string:id} OR id = {string:id}',array('id' => $id));
	if($res = $smcFunc['db_fetch_assoc']($request)){
		$id = (int)$res['id'];
	}else{
		$id = -1;
	}
	$smcFunc['db_free_result']($request);
	if($id == -1){
		die('{"success":false,"msg":"instruction not found"}');
	}
	$canEdit = allowedTo('inst_can_edit_any') || ($user_info['id'] == $res['owner'] && allowedTo('inst_can_edit_own'));
	
	if(!$canEdit){
		die('{"success":false,"msg":"permission denied"}');
	}
	
	if(!isset($_REQUEST['steporder'])){
		die('{"success":false,"msg":"missing required field!"}');
	}
	
	$newStepIds = json_decode($_REQUEST['steporder'],true);
	if(!is_array($newStepIds)){
		die('{"success":false,"msg":"invalid data type"}');
	}
	
	
	$request = $smcFunc['db_query']('','SELECT id FROM {db_prefix}instructions_steps WHERE instruction_id = {int:id}',array('id' => $id));
	$oldStepIds = array();
	while($res = $smcFunc['db_fetch_assoc']($request)){
		$oldStepIds[] = (int)$res['id'];
	}
	$smcFunc['db_free_result']($request);
	if(!empty(array_merge(array_diff($newStepIds,$oldStepIds),array_diff($oldStepIds,$newStepIds)))){
		die('{"success":false,"msg":"not all steps are in this instruction"}');
	}
	foreach($newStepIds as $i => $sid){
		$smcFunc['db_query']('','UPDATE {db_prefix}instructions_steps SET sorder={int:sorder} WHERE id={int:id}',array('sorder' => $i,'id' => $sid));
	}
	InstructionsUpdateFirstStep($id);
	echo '{"success":true}';
	exit;
}

function InstructionsUrlUpload(){
	global $context, $modSettings, $scripturl, $txt, $settings;
	global $user_info, $smcFunc, $board, $sourcedir;
	include_once($sourcedir . '/Subs-Package.php');
	header('Content-Type:application/json');
	if(!allowedTo('inst_can_edit_any') && !allowedTo('inst_can_edit_own')){
		die('{"success":false,"msg":"permission denied"}');
	}
	if(empty($_REQUEST['url'])){
		die('{"success":false,"msg":"missing required fields"}');
	}
	$url = parse_url($_REQUEST['url']);
	$contents = fetch_web_data('http://' . $url['host'] . (empty($url['port']) ? '' : ':' . $url['port']) . str_replace(' ', '%20', trim($url['path'])));
	
	$tmp_filename = $modSettings['instructions_uploads_path'] . '/' . 'upload_tmp_' . $user_info['id'] . rand() . time();
	if ($contents != false && $tmpImg = fopen($tmp_filename, 'wb')){
		fwrite($tmpImg, $contents);
		fclose($tmpImg);
		$name = explode('?',explode('#',trim($url['path']))[0])[0];
		$name = explode('/',$name);
		$name = $name[sizeof($name) - 1];
		$_FILES['files'] = array(
			'tmp_name' => $tmp_filename,
			'error' => 0,
			'name' => $name
		);
		InstructionsUpload(true);
		@unlink($tmp_filename);
		exit;
	}
	die('{"success":false,"msg":"Could not download file"}');
}

function InstructionsUpload($urlupload = false){
	global $context, $modSettings, $scripturl, $txt, $settings;
	global $user_info, $smcFunc, $board;
	header('Content-Type:application/json');
	if(!allowedTo('inst_can_edit_any') && !allowedTo('inst_can_edit_own')){
		echo '{"success":false,"upload-error":"permission denied"}';
		$urlupload or die();
		return false;
	}
	if(!isset($_FILES['files']) || !isset($_FILES['files']['tmp_name']) || $_FILES['files']['error'] !== 0){
		echo '{"success":false,"upload-error":"missing file"}';
		$urlupload or die();
		return false;
	}
	if(!preg_match('#([ !\#$%\'()+-.\d;=@-\[\]-{}~]+)\.(\w+)$#',$_FILES['files']['name'],$name)){
		echo '{"success":false,"upload-error":"invalid filename"}';
		$urlupload or die();
		return false;
	}
	$extension = strtolower($name[2]);
	if(!in_array($extension,array('png','gif','jpg','jpeg'))){
		echo '{"success":false,"upload-error":"invalid file type! Allowed types: png, gif, jpg, jpeg"}';
		$urlupload or die();
		return false;
	}
	if(!$img = @imagecreatefromstring(file_get_contents($_FILES['files']['tmp_name']))){
		echo '{"success":false,"upload-error":"uploaded file is not an image!"}';
		$urlupload or die();
		return false;
	}
	$smcFunc['db_insert']('insert','{db_prefix}instructions_images',
		array(
			'owner' => 'int', 'extension' => 'string', 'name' => 'string'
		),
		array(
			$user_info['id'], $extension, $_FILES['files']['name']
		),
		array('owner','extension','name')
	);
	$id = (int)$smcFunc['db_insert_id']('{db_prefix}instructions_images','id');
	
	mkdir($modSettings['instructions_uploads_path']."/$id");
	$imgFileName = $modSettings['instructions_uploads_path']."/$id/original.$extension";
	if(!(move_uploaded_file($_FILES['files']['tmp_name'],$imgFileName) || ($urlupload && rename($_FILES['files']['tmp_name'],$imgFileName)))){
		echo '{"success":false,"upload-error":"Could not move uploaded file!"}';
		$urlupload or die();
		return false;
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
	echo json_encode(array(
		'success' => true,
		'image' => array(
			'annotations' => false,
			'id' => (int)$id,
			'urls' => InstructionsGetImageUrls($id,$resizeTypes,$extension),
			'name' => $_FILES['files']['name']
		)
	));
	$urlupload or die();
	return true;
}

function array_is_of_pattern($array,$pattern){
	if(!is_array($array)){
		return false;
	}
	return array_map('gettype',$array) == array_map('gettype',$pattern);
}

function InstructionsSaveNotes($id){
	global $context, $modSettings, $scripturl, $txt, $settings;
	global $user_info, $smcFunc, $board;
	header('Content-Type: application/json');
	$id = (int)$id;
	
	$request = $smcFunc['db_query']('','SELECT id,owner FROM {db_prefix}instructions_images WHERE id = {int:id}',array('id' => $id));
	if($res = $smcFunc['db_fetch_assoc']($request)){
		$id = (int)$res['id'];
	}else{
		$id = -1;
	}
	$smcFunc['db_free_result']($request);
	if($id == -1){
		die('{"success":false,"msg":"image not found"}');
	}
	$canEdit = allowedTo('inst_can_edit_any') || ($user_info['id'] == $res['owner'] && allowedTo('inst_can_edit_own'));
	if(!$canEdit){
		die('{"success":false,"msg":"permission denied"}');
	}
	
	if(!isset($_REQUEST['annotations'])){
		die('{"success":false,"msg":"missing required fields"}');
	}
	$json = json_decode($_REQUEST['annotations'],true);
	if(!is_array($json)){
		die('{"success":false,"msg":"invalid data type"}');
	}
	$newAnnotations = array();
	foreach($json as $j){
		if(array_is_of_pattern($j,array('x'=>0.0,'y'=>0.0,'w'=>0.0,'h'=>0.0,'body'=>'')) && $j['x'] < 1 && $j['y'] < 1 && $j['x'] >= 0 && $j['y'] >= 0 && $j['w'] > 0 && $j['h'] > 0){
			$j['w'] = min($j['w'],1-$j['x']);
			$j['h'] = min($j['h'],1-$j['y']);
			$newAnnotations[] = $j;
		}
	}
	$smcFunc['db_query']('','UPDATE {db_prefix}instructions_images SET annotations = {string:annotations} WHERE id={int:id}',array('annotations' => json_encode($newAnnotations),'id' => $id));
	echo '{"success":true}';
	
	exit;
}

function InstructionsSetImgTags($id){
	global $context, $modSettings, $scripturl, $txt, $settings;
	global $user_info, $smcFunc, $board;
	header('Content-Type: application/json');
	$id = (int)$id;
	
	$request = $smcFunc['db_query']('','SELECT id,owner FROM {db_prefix}instructions_images WHERE id = {int:id}',array('id' => $id));
	if($res = $smcFunc['db_fetch_assoc']($request)){
		$id = (int)$res['id'];
		$owner = (int)$res['owner']; // important as admin id != owner id
	}else{
		$id = -1;
	}
	$smcFunc['db_free_result']($request);
	
	if($id == -1){
		die('{"success":false,"msg":"image not found"}');
	}
	$canEdit = allowedTo('inst_can_edit_any') || ($user_info['id'] == $owner && allowedTo('inst_can_edit_own'));
	if(!$canEdit){
		die('{"success":false,"msg":"permission denied"}');
	}
	
	if(!isset($_REQUEST['tags'])){
		die('{"success":false,"msg":"missing required fields"}');
	}
	$tags = $_REQUEST['tags'];
	if(!preg_match('/^[a-zA-Z0-9- _:.#]+(,[a-zA-Z0-9- _:.#]+)*,?$/',$tags) || $tags ===''){
		die('{"success":false,"msg":"invalid format"}');
	}
	
	$tags = explode(',',$tags);
	$tags = array_filter($tags); // remove empty elements
	$tags = array_unique($tags); // remove duplicates
	$smcFunc['db_query']('','UPDATE {db_prefix}instructions_images SET tags = {string:tags} WHERE id={int:id}',array('tags' => InstructionsMakeSQLArray($tags),'id' => $id));
	
	// now update the cache for which tags one has
	$request = $smcFunc['db_query']('','SELECT tags FROM {db_prefix}instructions_members WHERE member_id = {int:id}',array('id' => $owner));
	if($res = $smcFunc['db_fetch_assoc']($request)){
		$smcFunc['db_free_result']($request);
		$tags = array_merge($tags,InstructionsGetSQLArray($res['tags']));
		$tags = array_unique($tags);
		$smcFunc['db_query']('','UPDATE {db_prefix}instructions_members SET tags = {string:tags} WHERE member_id={int:id}',array('tags' => InstructionsMakeSQLArray($tags),'id' => $owner));
	}else{
		$smcFunc['db_free_result']($request);
		$smcFunc['db_insert']('insert','{db_prefix}instructions_members',
			array(
				'member_id' => 'int', 'tags' => 'string'
			),
			array(
				$owner, InstructionsMakeSQLArray($tags)
			),
			array('member_id','tags')
		);
	}
	
	die('{"success":true}');
	exit;
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
	$request = $smcFunc['db_query']('','SELECT id,annotations,extension,resizeTypes,name FROM {db_prefix}instructions_images WHERE owner = {int:member_id} AND tags LIKE {string:tag} AND success=1 ORDER BY id DESC LIMIT {int:offset},30',array('member_id' => $user_info['id'],'tag'=>$tag,'offset'=>$offset));
	$images = array();
	while($res = $smcFunc['db_fetch_assoc']($request)){
		$images[] = InstructionsGetImageObject($res);
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

function InstructionsDeleteImage($imgid){
	global $context, $modSettings, $scripturl, $txt, $settings;
	global $user_info, $smcFunc, $board;
	
	header('Content-Type: text/json');
	if($imgid != (int)$imgid){
		die('{"success":false,"msg":"invalid image id"}');
	}
	$imgid = (int)$imgid;
	
	$request = $smcFunc['db_query']('','SELECT owner,id FROM {db_prefix}instructions_images WHERE id = {int:imgid} AND success=1',array('imgid' => $imgid));
	$found = false;
	if(($res = $smcFunc['db_fetch_assoc']($request)) && (allowedTo('inst_can_delete_any') || ($user_info['id'] == $res['owner'] && allowedTo('inst_can_edit_own') /* image deleting is less heavy so edit is sufficient */))){
		$found = true;
		$imgid = (int)$res['id'];
	}
	$smcFunc['db_free_result']($request);
	
	if(!$found){
		die('{"success":false,"msg":"image not found or permission denied"}');
	}
	foreach(glob($modSettings['instructions_uploads_path'].'/'.$imgid.'/*') as $f){
		if(!unlink($f)){
			die('{"success":false,"msg":"Could not remove image"}');
		}
	}
	
	if(!rmdir($modSettings['instructions_uploads_path'].'/'.$imgid)){
		die('{"success":false,"msg":"could not delete folder"}');
	}
	$smcFunc['db_query']('','UPDATE {db_prefix}instructions_images SET success=-1 WHERE id = {int:imgid}',array('imgid' => $imgid));
	echo '{"success":true}';
	exit;
}

function InstructionsKarma($id,$direction){
	global $context, $modSettings, $scripturl, $txt, $settings;
	global $user_info, $smcFunc, $board, $user_profile, $memberContext, $board_info, $mbname;
	
	isAllowedTo('karma_edit');
	
	$direction = $direction==-1?-1:1; // make sure it's only one of the two
	
	$canView = allowedTo('inst_can_view_published');
	$canEdit = false;
	$canDelete = false;
	if($id == ''){
		$id = -1;
	}
	if($canView){ // are we allowed to view the instruction?
	
		$step = (int)$step; // just to make sure
		
		$instruction_name = '';
		
		$request = $smcFunc['db_query']('','SELECT id,owner,name,url,status,category,upvotes,downvotes,topic_id FROM {db_prefix}instructions_instructions WHERE url = {string:id} OR id = {string:id}',array('id' => $id));
		if($instr = $smcFunc['db_fetch_assoc']($request)){
			$id = (int)$instr['id'];
			if($instr['url']!=''){
				$dispId = $instr['url'];
			}else{
				$dispId = $id;
			}
			$instruction_name = $instr['name'];
		}else{
			$id = -1;
			$dispId = -1;
		}
		$smcFunc['db_free_result']($request);
		$published = (int)$instr['status'] > 0;
		if(!$published){ // this instruction isn't published yet!
			$canView = false;
			if(allowedTo('inst_can_view_unpublished_any') || ($user_info['id'] == $instr['owner'] && allowedTo('inst_can_view_unpublished_own'))){
				$canView = true;
			}
		}
		$canEdit = allowedTo('inst_can_edit_any') || ($user_info['id'] == $instr['owner'] && allowedTo('inst_can_edit_own'));
	}
	if($id == -1){
		fatal_lang_error('instruction_not_found',false);
	}
	if(!$canView){
		fatal_lang_error('instruction_cant_view',false);
	}
	
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
		$votes = array();
	}
	$smcFunc['db_free_result']($request);
	
	if(isset($votes[$id])){
		if($direction != $votes[$id]){
			// we actually have to do something
			$votes[$id] = $direction;
			if($direction == 1){
				$s = 'upvotes = upvotes + 1,downvotes = downvotes - 1';
			}else{
				$s = 'upvotes = upvotes - 1,downvotes = downvotes + 1';
			}
			$smcFunc['db_query']('','UPDATE {db_prefix}instructions_instructions SET '.$s.' WHERE id = {int:id}',array('id' => $id));
		}
	}else{
		$votes[$id] = $direction;
		if($direction == 1){
			$s = 'upvotes = upvotes + 1';
		}else{
			$s = 'upvotes = upvotes - 1';
		}
		$smcFunc['db_query']('','UPDATE {db_prefix}instructions_instructions SET '.$s.' WHERE id = {int:id}',array('id' => $id));
	}
	$smcFunc['db_query']('','UPDATE {db_prefix}instructions_members SET votes = {string:votes} WHERE member_id = {int:id}',array('id' => $user_info['id'],'votes' => json_encode($votes)));
	redirectexit(InstructionsGetURL($dispId));
}

function InstructionsGetInstructable($ible){
	header('Content-Type: text/json');
	$s = file_get_contents('http://www.instructables.com/json-api/showInstructable?id='.urlencode($ible).'&t='.time());
	if($s == ''){
		echo '{"success":false}';
	}
	echo $s;
	exit;
}

function InstructionsIblesImport($id){
	global $context, $modSettings, $scripturl, $txt, $settings;
	global $user_info, $smcFunc, $board;
	
	header('Content-Type: text/json');
	
	$request = $smcFunc['db_query']('','SELECT id,owner,name,url,status, publish_date FROM {db_prefix}instructions_instructions WHERE url = {string:id} OR id = {string:id}',array('id' => $id));
	if($res = $smcFunc['db_fetch_assoc']($request)){
		$id = (int)$res['id'];
	}else{
		$id = -1;
	}
	$smcFunc['db_free_result']($request);
	if($id == -1){
		die('{"success":false,"msg":"instruction not found"}');
	}
	$canEdit = allowedTo('inst_can_edit_any') || ($user_info['id'] == $res['owner'] && allowedTo('inst_can_edit_own'));
	
	if(!$canEdit){
		die('{"success":false,"msg":"permission denied"}');
	}
	if(empty($_REQUEST['data'])){
		die('{"success":false,"msg":"missing required field"}');
	}
	if(!($json = json_decode($_REQUEST['data']))){
		die('{"success":false,"msg":"invalid data format"}');
	}
	
	if(isset($_REQUEST['done'])){
		$smcFunc['db_query']('','UPDATE {db_prefix}instructions_instructions SET status=0,import_data="" WHERE id = {int:id}',array('id' => $id));
	}else{
		$smcFunc['db_query']('','UPDATE {db_prefix}instructions_instructions SET status=-1,import_data={string:data} WHERE id = {int:id}',array('id' => $id,'data' => json_encode($json)));
	}
	echo '{"success":true}';
	exit;
}

function InstructionsGetImageIdsForStep($id){
	global $context, $modSettings, $scripturl, $txt, $settings;
	global $user_info, $smcFunc, $board;
	
	header('Content-Type: text/json');
	
	$request = $smcFunc['db_query']('','SELECT id,owner,name,url,status, publish_date FROM {db_prefix}instructions_instructions WHERE url = {string:id} OR id = {string:id}',array('id' => $id));
	if($res = $smcFunc['db_fetch_assoc']($request)){
		$id = (int)$res['id'];
	}else{
		$id = -1;
	}
	$smcFunc['db_free_result']($request);
	if($id == -1){
		die('{"success":false,"msg":"instruction not found"}');
	}
	$canEdit = allowedTo('inst_can_edit_any') || ($user_info['id'] == $res['owner'] && allowedTo('inst_can_edit_own'));
	
	if(!$canEdit){
		die('{"success":false,"msg":"permission denied"}');
	}
	if(empty($_REQUEST['step']) || $_REQUEST['step'] != (int)$_REQUEST['step']){
		die('{"success":false,"msg":"missing required field"}');
	}
	$request = $smcFunc['db_query']('','SELECT images FROM {db_prefix}instructions_steps WHERE instruction_id = {int:id} AND id = {int:step} ORDER BY sorder ASC',array('id' => $id,'step' => $_REQUEST['step']));
	
	if($res = $smcFunc['db_fetch_assoc']($request)){
		$images = InstructionsGetSQLArray($res['images']);
	}else{
		$smcFunc['db_free_result']($request);
		die('{"success":false,"msg":"step not found"}');
	}
	$smcFunc['db_free_result']($request);
	echo json_encode(array(
		'success' => true,
		'images' => $images
	));
	exit;
}
?>
