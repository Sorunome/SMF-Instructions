<?php
function instructionMakeSortLink($baseurl,$name,$displayname){
	global $context,$settings;
	return '<a href="'.$baseurl.';sort='.$name.($context['sort_by'] == $name && $context['sort_direction'] == 'up' ? ';desc' : ''). '">'.$displayname.( $context['sort_by'] == $name ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" />' : '') .'</a>';
}

function getInstructionsTable($inst){
	global $txt;
	if(sizeof($inst['instructions']) == 0){
		echo 'No instructions...';
		return;
	}
	echo $txt['pages'].': '.$inst['page_index'].'
	<table class="table_grid" cellspacing="0" width="100%">
		<thead><tr class="catbg">
			<th class="first_th" width="100px"></th>
			<th scope="col">'.instructionMakeSortLink($inst['caturl'],'name','Name').'
			<th width="15%" scope="col">'.instructionMakeSortLink($inst['caturl'],'rating','Rating').' / '.instructionMakeSortLink($inst['caturl'],'views','Views').'</th>
			<th scope="col" class="last_th" width="22%">'.instructionMakeSortLink($inst['caturl'],'date','Date').' / '.instructionMakeSortLink($inst['caturl'],'author','Author').'</th>
		</tr></thead>
		<tbody>
	';
	foreach($inst['instructions'] as $i){
		echo '<tr>
			<td class="'.($i['published']?'windowbg':'approvetbg').'">'.$i['image'].'</td>
			<td class="'.($i['published']?'windowbg':'approvetbg').'2 instruction_name"><a href="'.htmlentities($i['url']).'" style="font-weight:bold;font-size:2em;">'.$i['name'].'</a></td>
			<td class="'.($i['published']?'windowbg':'approvetbg').'">
				Rating: +'.$i['upvotes'].' / -'.$i['downvotes'].'<br>Views: '.$i['views'].'
			</td>
			<td class="'.($i['published']?'windowbg':'approvetbg').'">published on:<br>'.$i['publish_date'].'<br>by <strong><a href="'.htmlentities($i['author']['url']).'">'.$i['author']['name'].'</a></strong></td>
		</tr>';
	}
	echo '</tbody></table>'.$txt['pages'].': '.$inst['page_index'];
}

function template_view(){
	global $instr,$context,$txt;
	
	if(!$instr->published){
		echo '<div class="information"><span class="error">',$txt['inst_unpublished'],'</span></div>';
	}
	if($instr->origUrl != ''){
		echo '<div class="information"><span class="error">',sprintf($txt['inst_origversion'],htmlspecialchars($instr->origUrl)),'</span></div>';
	}
	if($instr->newUrl != ''){
		echo '<div class="information"><span class="error">',sprintf($txt['inst_newversion'],htmlspecialchars($instr->newUrl)),'</span></div>';
	}
	
	
	// we generate the quicknav before the admin tools so that we know the current step ID
	$curstep = 0;
	$stepnav = '<ul class="windowbg instruction_stepcontainer invisibleList">';
	foreach($instr->steps as $i => $step){
		$stepnav .= '<li data-id="'.$step['id'].'"><a href="'.htmlspecialchars($step['url']).'" class="instruction_step instruction_imagehover'.($step['full_parse'] && !isset($_REQUEST['allsteps'])?' current':'').'">
			<div class="instruction_step_img" style="background-image:url(&quot;'.htmlentities($step['main_image']['urls']['square']).'&quot;);"></div>
			<div class="txt">'.($i == 0?$txt['inst_intro']:$txt['inst_step'].' '.$i).'</div>
		</a></li>';
		if($step['full_parse']){
			$curstep = $i;
		}
	}
	$stepnav .= '</ul>';
	
	
	
	$admintools = '';
	if($instr->canEdit()){
		$admintools = '<div class="buttonlist floatright"><ul>
			'.($instr->canDelete()?'<li><a class="instructions_edit_delete_instruciton" href="'.htmlspecialchars($instr->getUrl('delete',array('redirect'))).'"><span>'.$txt['inst_adminbutton_delete'].'</span></a></li>':'').'
			<li><a href="'.htmlspecialchars(isset($instr->steps[$curstep]['url_edit'])?$instr->steps[$curstep]['url_edit']:$instr->getUrl('edit')).'"><span>'.$txt['inst_adminbutton_edit'].'</span></a></li>
		</ul></div>';
	}
	
	echo $admintools,'
	<h1 class="instruction_name">',$instr->name_parsed,'</h1>
	',$stepnav,'
	<div id="instruction_bigimage" class="instructions_overlay"><div><img></img><div id="instruction_big_annotations"></div><span class="close">',$txt['inst_close'],'</span><span class="left"></span><span class="right"></span></div></div>
	
	';
	$images = array();
	$stepnums = array();
	foreach($instr->steps as $i => $step){
		if(!$step['full_parse']){
			continue;
		}
		$stepnums[] = $i;
		echo '<div id="step',$i,'">
		<h1 class="instruction_stepname">',$i==0?'':$txt['inst_step'].' '.$i.': ',$step['title_parsed'].'</h1>
			<div class="windowbg description instruction_body">';
		if(sizeof($step['images'] > 0)){
			$images[$i] = $step['images'];
			
			echo '<div class="windowbg2 instruction_imagecontainer">
			<div class="instruction_mainimage" id="instruction_mainimage_',$i,'">
			<div data-step="',$i,'">
			<img src="'.$step['images'][0]['urls']['medium'].'">
			<div id="instruction_mainimage_annotations_',$i,'"></div>
			</div>
			</div>';
			
			if(sizeof($step['images']) > 1){
				echo '<div class="instruction_imageslider" id="instruction_imageslider_',$i,'">';
				$j = 0;
				foreach($step['images'] as $img){
					echo '<div class="instruction_imagehover',$j==0?' current':'','" style="background-image:url(&quot;'.htmlentities($img['urls']['square']).'&quot;);" onclick="instruction_dispImage(',$i,',',$j,',this);"></div>';
					$j++;
				}
				echo '</div>';
			}
			echo '</div>';
		}
		echo $step['body_parsed'],'</div></div>';
	}
	echo $admintools,'&nbsp;<script type="text/javascript">
	instruction_images = '.json_encode($images).';
	instruction_init_display('.min($stepnums).');
	</script>';
}

function template_import(){
	global $instr,$txt;
	echo '
	<h1 style="font-size:3em;font-weight:bold;line-height:normal;">',$txt['inst_importing'],'</h1>
	<div id="import_status"></div>
	<div id="import_bottom"></div>
	<script type="text/javascript">
		$(function(){
			instruction_edit_runImport();
		});
	</script>';
}

function template_edit(){
	global $context,$txt,$modSettings,$scripturl,$settings,$boardurl,$instr;
	
	$curstep = 0;
	
	if(!$instr->published){
		echo '<div class="information"><span class="error">',$txt['inst_unpublished'],'</span></div>';
	}
	echo '<div id="instructions_publish" class="instructions_overlay">
		<div>
			<button class="button_submit" id="instructions_close_publish">',$txt['inst_close'],'</button>
			<h1>Publish your instruction <em>'.$inst['instruction_name'].'</em></h1>
			Category:<select>';
	foreach(getCatList($inst['publishcats']) as $c){
		echo '<option value="'.$c['id'].'" data-can="'.($c['canpublish']?1:0).'">'.$c['name'].'</option>';
	}
	echo '	</select><br>
			Forum post:<br>
			<textarea>[size=18pt][b]{NAME}[/b][/size]
[size=16pt][url={URL}]--> View Instruction[/url][/size]
[img]{IMG}[/img]


{INTRO}</textarea><br>
			<button class="button_submit" id="instructions_publish_real">Publish my instruction!</button>
		</div>
	</div>';
	
	$actionButtons = '<div class="buttonlist floatleft instruction_edit_buttons"><ul>
		<li><a href="#" class="instruction_save"><span>Save Instruction</span></a></li>
		'.($instr->canDelete()?'<li><a class="instructions_edit_delete_instruciton" href="'.htmlspecialchars($instr->getUrl('delete',array('redirect'))).'"><span>'.$txt['inst_adminbutton_delete'].'</span></a></li>':'').'
		<li><a href="'.htmlspecialchars($instr->getUrl('addstep',array('redirect'))).'"><span>'.$txt['inst_edit_addstep'].'</span></a></li>
		<li><a class="instructions_edit_delete_step" href="'.htmlspecialchars($instr->getUrl('deletestep',array('stepid','redirect'))).'"><span>'.$txt['inst_edit_deletestep'].'</span></a></li>
		'.($instr->published?
			'<li><a href="'.htmlspecialchars($instr->getUrl('unpublish',array('redirect'))).'"><span>'.$txt['inst_edit_unpublish'].'</span></a></li>'
		:
			'<li><a class="instructions_publish_open"><span>'.$txt['inst_edit_publish'].'</span></a></li>'
		).'
		<li><a class="instructions_new_version"><span>'.$txt['inst_edit_newversion'].'</span></a></li>
		<li><a class="instructions_ible_import instructions_nosaveask"><span>'.$txt['inst_edit_iblesimport'].'</span>
		<li><a href="'.$instr->url.'"><span>'.$txt['inst_edit_fullpreview'].'</span></a></li>
	</ul></div>';
	
	echo $actionButtons.'<div id="instructions_edit_top"></div>';
	echoStepsNav($inst);
	echo 'Drag&amp;drop to change step order!<br>
	<br><span id="instruction_edit_name">Step Title: <input type="text" value="'.htmlentities($inst['name']).'"></span><br><br>
	<textarea cols="80" id="instructions_edit_bbceditor" rows="10">'.htmlentities($inst['body']).'</textarea>
	<div id="instructions_edit_fileannotation_box" class="description">
		<span class="buttonlist floatleft">
			<ul>
				<li><a href="#" id="instruction_add_annotation" class="instructions_nosaveask"><span>Add Imagenote</span></a></li>
			</ul>
		</span>
		<div></div>
	</div>
	<div id="instructions_edit_files" class="description"></div>
	Drag&amp;drop to change image order! The first image is the main image.<br><br>
	<div id="instructions_edit_imagetabs" class="instructions_nosaveask">
		<ul>
			<li><a href="#instructions_edit_imageuploadtab">Upload new images</a></li>
			<li><a href="#instructions_edit_imagelibrarytab">Image library</a></li>
		</ul>
		<div id="instructions_edit_imageuploadtab" data-tab="upload">
			<label><input type="checkbox" checked="checked" id="instructions_edit_autoaddnewimg">Automatically add uploaded images to step</label><br>
			<label>Tag new images as: <input type="text" id="instructions_edit_autotagnewimg" pattern="^[a-zA-Z0-9- _:.#]+(,[a-zA-Z0-9- _:.#]+)*$"></label>
			<br><br>
			<div id="instructions_edit_upload_tabs">
				<ul>
					<li><a href="#instructions_edit_upload_img">Upload from your computer</a></li>
					<li><a href="#instructions_edit_upload_url">Upload from a website</a></li>
				</ul>
				<div id="instructions_edit_upload_img">
					<button class="button_submit" id="instructions_start_upload">Start upload</button>
					<div id="instructions_fileupload">Select Files...</div>
				</div>
				<div id="instructions_edit_upload_url">
					<label>URL: <input type="url"></label><br>
					<button class="button_submit" id="instructions_start_urlupload">Upload</button>
					<div id="instructions_uploadurl_progress"></div>
				</div>
			</div>
		</div>
		<div id="instructions_edit_imagelibrarytab" data-tab="library">
			Filter by tags: <select></select>
			<div id="instructions_edit_imagelibrary"></div>
		</div>
	</div>
	'.$actionButtons;
	
	
	echo '<script type="text/javascript">
	$(document).ready(function(){
		instruction_edit_beginningText = '.json_encode($inst['body']).';
		instruction_edit_images = '.json_encode($inst['images']).';
		instruction_edit_stepid = '.$inst['stepid'].';
		instruction_edit_sceditorurl = '.json_encode($modSettings['instructions_sceditor_url']).';
		instruction_edit_buildEditor();
	});
	</script>';
}


function template_category(){
	global $context,$txt,$modSettings,$scripturl,$settings;
	$inst = $context['instruction_cat'];
	echo '
	<table class="table_list">
		<tbody class="header">
			<tr>
				<td><div class="cat_bar"><h3 class="catbg">'.$inst['name'].'</h3></div></td>
			</tr>
		</tbody>
		<tbody class="content instruction_childcats">';
		foreach($inst['children'] as $id => $name){
			echo '<tr class="windowbg2">
				<td class="info"><a class="subject" href="index.php?action=instructions;cat='.$id.'"><span>'.$name.'</span></a></td>
			</tr>';
		}
		echo '</tbody>
		<tbody class="divider"><tr><td></td></tr></tbody>
	</table>';
	if($inst['num_instructions'] > 0){
		getInstructionsTable($inst);
	}
}

function echoStepsNav($inst){
	$curstep = $inst['curstep'];
	echo '<ul class="windowbg instruction_stepcontainer invisibleList">';
	$i = 0;
	foreach($inst['steps'] as $s){
		echo '<li data-id="'.$s['stepid'].'"><a href="'.$s['href'].'" class="instruction_step instruction_imagehover'.($i==$curstep?' current':'').'">
			<div class="instruction_step_img" style="background-image:url(&quot;'.htmlentities($s['img']).'&quot;);"></div>
			<div class="txt">'.($i==0?'intro':"Step $i").'</div>
		</a></li>';
		$i++;
	}
	echo '</ul>';
}

function template_display(){
	global $context,$txt,$modSettings,$scripturl,$settings,$memberContext;
	
	$inst = $context['instruction'];
	$curstep = $inst['curstep'];
	
	if($inst['owner'] && isset($memberContext[$inst['owner']])){
		$ownerCtx = $memberContext[$inst['owner']];
		echo '<div class="windowbg instruction_authorinfo"><span class="topslice"><span></span></span>
		<div class="content">
			<ul class="invisibleList">
				'.($ownerCtx['avatar']['image']!=''?'<li class="avatar">'.$ownerCtx['avatar']['image'].'</li>':'').'
				<li>
					<strong class="username">'.$ownerCtx['link'].'</strong>
					'.($ownerCtx['title']!=''?'<br>'.htmlentities($ownerCtx['title']):'').'
					'.($ownerCtx['group']!=''?'<br>'.htmlentities($ownerCtx['group']):'').'
					'.($ownerCtx['post_group']!=''?'<br>'.htmlentities($ownerCtx['post_group']):'').'
					<br>'.$ownerCtx['group_stars'].'
				</li>
			</ul>
		</div>
		<span class="botslice"><span></span></span></div>';
	}
	if(!$inst['published']){
		echo '<div class="information"><span class="error">Important: This instruction is <strong>unpublished</strong>, that means nobody will be able to see it yet.</span></div>';
	}
	if($inst['depth'] > 0){
		echo '<div class="information"><span class="error">This is a newer version of an instruction. To see the original one click <a href="'.$inst['origurl'].'">here</a></span></div>';
	}
	if($inst['depth'] == 0 && $inst['newerVersion']){
		echo '<div class="information"><span class="error">A newer version of this instruction is available. To see it click <a href="'.$inst['url'].'">here</a></span></div>';
	}
	
	$admintools = '';
	if($inst['canedit']){
		$admintools = '<div class="buttonlist floatright"><ul>
			'.($inst['candelete']?'<li><a class="instructions_edit_delete_instruciton" href="index.php?action=instructions;delete='.$inst['instruction_id'].';redirect"><span>Delete Instruction</span></a></li>':'').'
			<li><a href="'.$inst['thisurl_edit'].'"><span>Edit Instruction</span></a></li>
		</ul></div>';
	}
	// here we display the steps buttons
	echo $admintools.'<h1 class="instruction_name" id="instruction">'.$inst['instruction_name'].'</h1>
	<ul class="instruction_description description">
		'.($inst['topic_id']!=-1?'<li>→ <a href="index.php?topic='.$inst['topic_id'].'.0;topscreen">Discuss</a></li>':'').'
		<li>→ Rating: +'.$inst['upvotes'].'/-'.$inst['downvotes'].(allowedTo('karma_edit')?' <a href="index.php?action=instructions;upvote='.$inst['instruction_id'].'">[upvote]</a> <a href="index.php?action=instructions;downvote='.$inst['instruction_id'].'">[downvote]</a>':'').'
		'.($inst['publish_date']!=0?'<li>→ Published on '.timeformat($inst['publish_date']).'</li>':'').'
	</ul>';
	
	echoStepsNav($inst);
	
	// time to display the step name
	echo '<h1 class="instruction_stepname">'.($curstep==0?'':"Step $curstep: ").$inst['steps'][$curstep]['name'].'</h1>
		<div class="windowbg description instruction_body">';
	
	// image viewer thingy
		
	if(sizeof($inst['images']) > 0){
		echo '
			<div id="instruction_bigimage" class="instructions_overlay"><div><img></img><div id="instruction_big_annotations"></div><span class="close">Close</span><span class="left"></span><span class="right"></span></div></div>
			<div class="windowbg2 instruction_imagecontainer">
			<div id="instruction_mainimage">
			<div>
			<img src="'.$inst['images'][0]['urls']['square'].'">
			<div id="instruction_mainimage_annotations"></div>
			</div>
			</div>';
		if(sizeof($inst['images']) > 1){
			echo '<div class="instruction_imageslider">';
			$i = 0;
			foreach($inst['images'] as $img){
				echo '<div class="instruction_imagehover'.($i==0?' current':'').'" style="background-image:url(&quot;'.htmlentities($img['urls']['square']).'&quot;);" onclick="instruction_dispImage('.$i.',this);"></div>';
				$i++;
			}
			echo '</div>';
		}
		echo '</div>';
	}
	echo $inst['body'].'</div>
	<div class="buttonlist floatleft"><ul>';
	if($curstep > 0){
		echo '<li><a href="'.$inst['prevurl'].'"><span>Previous Step</span></a></li>';
	}
	if($curstep < ($inst['numsteps']-1)){
		echo '<li><a href="'.$inst['nexturl'].'"><span>Next Step</span></a></li>';
	}
	echo '</ul></div>'.$admintools.'
	<script type="text/javascript">
		instruction_images = '.json_encode($inst['images']).';
		instruction_init_display();
	</script>';
}

function getCatList($c,$s = ''){
	$a = array(array(
		'name' => $s.' '.$c['name'],
		'id' => $c['id'],
		'canpublish' => $c['canpublish']
	));
	foreach($c['children'] as $child){
		$a = array_merge($a,getCatList($child,$s==''?'=>':'=='.$s));
	}
	return $a;
}

function template_edit_old(){
	global $context,$txt,$modSettings,$scripturl,$settings,$boardurl;
	
	$inst = $context['instruction'];
	$curstep = $inst['curstep'];
	
	echo '<a name="instruction"></a>';
	if($inst['status'] == -1){
		// we are in import mode!
		echo '
		<h1 style="font-size:3em;font-weight:bold;line-height:normal;">Importing instruction...</h1>
		<div id="import_status"></div>
		<div id="import_bottom"></div>
		<script type="text/javascript">
			instruction_import_data = '.json_encode($inst['import_data']).';
			instruction_edit_id = '.$inst['instruction_id'].';
			$(function(){
				instruction_edit_runImport();
			});
		</script>';
		return;
	}
	if(!$inst['published']){
		echo '<div class="information"><span class="error">Important: This instruction is <strong>unpublished</strong>, that means nobody will be able to see it yet.</span></div>';
	}
	echo '<div id="instructions_publish" class="instructions_overlay">
		<div>
			<button class="button_submit" id="instructions_close_publish">Close</button>
			<h1>Publish your instruction <em>'.$inst['instruction_name'].'</em></h1>
			Category:<select>';
	foreach(getCatList($inst['publishcats']) as $c){
		echo '<option value="'.$c['id'].'" data-can="'.($c['canpublish']?1:0).'">'.$c['name'].'</option>';
	}
	echo '	</select><br>
			Forum post:<br>
			<textarea>[size=18pt][b]{NAME}[/b][/size]
[size=16pt][url={URL}]--> View Instruction[/url][/size]
[img]{IMG}[/img]


{INTRO}</textarea><br>
			<button class="button_submit" id="instructions_publish_real">Publish my instruction!</button>
		</div>
	</div>';
	$actionButtons = '<div class="buttonlist floatleft instruction_edit_buttons"><ul>
		<li><a href="#" class="instruction_save"><span>Save Instruction</span></a></li>
		'.($inst['candelete']?'<li><a class="instructions_edit_delete_instruciton" href="index.php?action=instructions;delete='.$inst['instruction_id'].';redirect"><span>Delete Instruction</span></a></li>':'').'
		<li><a href="index.php?action=instructions;addstep='.$inst['instruction_id'].';redirect"><span>Add Step</span></a></li>
		<li><a class="instructions_edit_delete_step" href="index.php?action=instructions;deletestep='.$inst['instruction_id'].';stepid='.$inst['stepid'].';redirect"><span>Delete Step</span></a></li>
		'.($inst['published']?
			'<li><a href="index.php?action=instructions;unpublish='.$inst['instruction_id'].';redirect"><span>Unpublish Instruction</span></a></li>'
		:
			'<li><a class="instructions_publish_open"><span>Publish Instruction</span></a></li>'
		).'
		<li><a class="instructions_new_version"><span>Set new version</span></a></li>
		<li><a class="instructions_ible_import instructions_nosaveask"><span>Instructables import</span>
		<li><a href="'.$inst['thisurl_noedit'].'"><span>Full Preview</span></a></li>
	</ul></div>';
	
	echo $actionButtons.'<div id="instructions_edit_top"></div>';
	echoStepsNav($inst);
	echo 'Drag&amp;drop to change step order!<br>
	<br><span id="instruction_edit_name">Step Title: <input type="text" value="'.htmlentities($inst['name']).'"></span><br><br>
	<textarea cols="80" id="instructions_edit_bbceditor" rows="10">'.htmlentities($inst['body']).'</textarea>
	<div id="instructions_edit_fileannotation_box" class="description">
		<span class="buttonlist floatleft">
			<ul>
				<li><a href="#" id="instruction_add_annotation" class="instructions_nosaveask"><span>Add Imagenote</span></a></li>
			</ul>
		</span>
		<div></div>
	</div>
	<div id="instructions_edit_files" class="description"></div>
	Drag&amp;drop to change image order! The first image is the main image.<br><br>
	<div id="instructions_edit_imagetabs" class="instructions_nosaveask">
		<ul>
			<li><a href="#instructions_edit_imageuploadtab">Upload new images</a></li>
			<li><a href="#instructions_edit_imagelibrarytab">Image library</a></li>
		</ul>
		<div id="instructions_edit_imageuploadtab" data-tab="upload">
			<label><input type="checkbox" checked="checked" id="instructions_edit_autoaddnewimg">Automatically add uploaded images to step</label><br>
			<label>Tag new images as: <input type="text" id="instructions_edit_autotagnewimg" pattern="^[a-zA-Z0-9- _:.#]+(,[a-zA-Z0-9- _:.#]+)*$"></label>
			<br><br>
			<div id="instructions_edit_upload_tabs">
				<ul>
					<li><a href="#instructions_edit_upload_img">Upload from your computer</a></li>
					<li><a href="#instructions_edit_upload_url">Upload from a website</a></li>
				</ul>
				<div id="instructions_edit_upload_img">
					<button class="button_submit" id="instructions_start_upload">Start upload</button>
					<div id="instructions_fileupload">Select Files...</div>
				</div>
				<div id="instructions_edit_upload_url">
					<label>URL: <input type="url"></label><br>
					<button class="button_submit" id="instructions_start_urlupload">Upload</button>
					<div id="instructions_uploadurl_progress"></div>
				</div>
			</div>
		</div>
		<div id="instructions_edit_imagelibrarytab" data-tab="library">
			Filter by tags: <select></select>
			<div id="instructions_edit_imagelibrary"></div>
		</div>
	</div>
	'.$actionButtons;
	
	
	echo '<script type="text/javascript">
	$(document).ready(function(){
		instruction_edit_beginningText = '.json_encode($inst['body']).';
		instruction_edit_images = '.json_encode($inst['images']).';
		instruction_edit_id = '.$inst['instruction_id'].';
		instruction_edit_stepid = '.$inst['stepid'].';
		instruction_edit_sceditorurl = '.json_encode($modSettings['instructions_sceditor_url']).';
		instruction_edit_buildEditor();
	});
	</script>';
}
?>
