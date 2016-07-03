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
	<table class="table_grid instruction_table" cellspacing="0" width="100%">
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
			<td class="'.($i['published']?'windowbg':'approvetbg').'"><img src="'.htmlspecialchars($i['image']).'" alt="'.htmlspecialchars($i['name']).'" /></td>
			<td class="'.($i['published']?'windowbg':'approvetbg').'2 instruction_name"><a href="'.htmlentities($i['url']).'" style="font-weight:bold;">'.$i['name'].'</a></td>
			<td class="'.($i['published']?'windowbg':'approvetbg').'">
				Rating: +'.$i['upvotes'].' / -'.$i['downvotes'].'<br>Views: '.$i['views'].'
			</td>
			<td class="'.($i['published']?'windowbg':'approvetbg').'">published on:<br>'.$i['publish_date'].'<br>by <strong><a href="'.htmlentities($i['author']['url']).'">'.$i['author']['name'].'</a></strong></td>
		</tr>';
	}
	echo '</tbody></table>'.$txt['pages'].': '.$inst['page_index'];
}

function template_view(){
	global $instr,$context,$txt,$memberContext;
	
	if($instr->owner['id_member']!=-1 && isset($memberContext[$instr->owner['id_member']])){
		$ownerCtx = $memberContext[$instr->owner['id_member']];
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
			<div class="instruction_step_img" style="background-image:url(&quot;'.htmlentities($step['main_image']->getUrl('square')).'&quot;);"></div>
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
	<ul class="instruction_description description">
		'.($instr->topic_id!=-1?'<li>→ <a href="index.php?topic='.$instr->topic_id.'.0;topscreen">Discuss</a></li>':'').'
		<li>→ Rating: +'.$instr->upvotes.'/-'.$instr->downvotes.(allowedTo('karma_edit')?' <a href="'.$instr->getUrl('upvote').'">[upvote]</a> <a href="'.$instr->getUrl('downvote').'">[downvote]</a>':'').'
		'.($instr->publish_date!=0?'<li>→ Published on '.timeformat($instr->publish_date).'</li>':'').'
	</ul>
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
			$images[$i] = array();
			foreach($step['images'] as $img){
				$images[$i][] = array(
					'annotations' => $img->getAnnotations(),
					'urls' => array(
						'large' => $img->getUrl('large'),
						'medium' => $img->getUrl('medium')
					)
				);
			}
			echo '<div class="windowbg2 instruction_imagecontainer">
			<div class="instruction_mainimage" id="instruction_mainimage_',$i,'">
			<div data-step="',$i,'">
			<img src="'.(isset($step['images'][0])?$step['images'][0]->getUrl('medium'):'').'">
			<div id="instruction_mainimage_annotations_',$i,'"></div>
			</div>
			</div>';
			
			if(sizeof($step['images']) > 1){
				echo '<div class="instruction_imageslider" id="instruction_imageslider_',$i,'">';
				$j = 0;
				foreach($step['images'] as $img){
					echo '<div class="instruction_imagehover',$j==0?' current':'','" style="background-image:url(&quot;'.htmlentities($img->getUrl('square')).'&quot;);" onclick="instruction_dispImage(',$i,',',$j,',this);"></div>';
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
	
	// we generate the quicknav before the admin tools so that we know the current step ID
	$curstep = 0;
	$stepnav = '<ul class="windowbg instruction_stepcontainer invisibleList">';
	foreach($instr->steps as $i => $step){
		$stepnav .= '<li data-id="'.$step['id'].'"><a href="'.htmlspecialchars($step['url_edit']).'" class="instruction_step instruction_imagehover'.($step['full_parse']?' current':'').'">
			<div class="instruction_step_img" style="background-image:url(&quot;'.htmlentities($step['main_image']->getUrl('square')).'&quot;);"></div>
			<div class="txt">'.($i == 0?$txt['inst_intro']:$txt['inst_step'].' '.$i).'</div>
		</a></li>';
		if($step['full_parse']){
			$curstep = $i;
		}
	}
	$stepnav .= '</ul>';
	
	echo '<div id="instructions_publish" class="instructions_overlay">
		<div>
			<button class="button_submit" id="instructions_close_publish">',$txt['inst_close'],'</button>
			<h1>',sprintf($txt['inst_publish'],$instr->name_parsed),'</h1>
			',$txt['inst_publish_cat'],'<select>';
	foreach(getCatList(InstructionsGetPublishCats()) as $c){
		echo '<option value="'.$c['id'].'" data-can="'.($c['canpublish']?1:0).'">'.$c['name'].'</option>';
	}
	echo '	</select><br>
			',$txt['inst_publish_post'],'<br>
			<textarea>Please select a category!</textarea><br>
			<button class="button_submit" id="instructions_publish_real">',$txt['inst_publish_go'],'</button>
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
		<li><a class="instructions_ible_import instructions_nosaveask"><span>'.$txt['inst_edit_iblesimport'].'</span></a></li>
		<li><a href="'.htmlspecialchars($instr->steps[$curstep]['url']).'"><span>'.$txt['inst_edit_fullpreview'].'</span></a></li>
	</ul></div>';
	
	echo $actionButtons.'<div id="instructions_edit_top"></div>';
	echo $stepnav,'Drag&amp;drop to change step order!<br>
	<br><span id="instruction_edit_name">Step Title: <input type="text" value="',htmlentities($instr->steps[$curstep]['title']),'"></span><br><br>
	<textarea cols="80" id="instructions_edit_bbceditor" rows="10">',htmlentities($instr->steps[$curstep]['body']),'</textarea>
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
	',$actionButtons;
	$images = array();
	if($instr->exists){
		foreach($instr->steps[$curstep]['images'] as $img){
			$images[] = $img->getJSON(array('small','medium','large'));
		}
	}
	echo '<script type="text/javascript">
	$(document).ready(function(){
		instruction_edit_beginningText = '.json_encode($instr->exists?$instr->steps[$curstep]['body']:'').';
		instruction_edit_images = '.json_encode($images).';
		instruction_edit_sceditorurl = '.json_encode($modSettings['instructions_sceditor_url']).';
		instruction_urls = '.json_encode(array(
			'save' => $instr->getUrl('save',array('stepid')),
			'savenotes' => $instr->getUrl('savenotes'),
			'deletestep' => $instr->getUrl('deletestep',array('stepid')),
			'publish' => $instr->getUrl('publish'),
			'newversion' => $instr->getUrl('newversion'),
			'savesteporder' => $instr->getUrl('savesteporder'),
		)).';
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
				<td class="info"><a class="subject" href="index.php?action=instructions_cat;id='.$id.'"><span>'.$name.'</span></a></td>
			</tr>';
		}
		echo '</tbody>
		<tbody class="divider"><tr><td></td></tr></tbody>
	</table>';
	if($inst['num_instructions'] > 0){
		getInstructionsTable($inst);
	}
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

?>
