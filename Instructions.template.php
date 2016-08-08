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
		<li>→ <a href="'.htmlspecialchars($instr->getUrl('',array('allsteps'))).'">View all steps</a></li>
		<li>→ <a href="'.htmlspecialchars($instr->getUrl('pdf')).'">Download PDF</a></li>
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

function template_instruction_pdf(){
	global $instr,$sourcedir,$boarddir,$txt;
	if(!class_exists('FPDF')){
		require($sourcedir.'/fpdf.php');
	}
	class PDF extends FPDF {
		private $bbc = array(
			'img' => array(
				'use' => false,
				'widht' => 0,
				'height' => 0
			),
			'youtube' => false,
			'b' => 0,
			'i' => 0,
			'u' => 0,
			'href' => '',
			'lineheight' => 5,
			'fontlist' => array('arial', 'times', 'courier', 'helvetica', 'symbol'),
			'issetfont' => false,
			'issetcolor' => false
		);
		public function Header(){
			global $boarddir;
			$this->image($boarddir.'/red_connector.png',6,6,10);
			$this->setFont('Arial','B',20);
			$this->SetDrawColor(0);
			$this->SetLineWidth(0.2);
			$this->SetTextColor(0xAC,0x3C,0x2E);
			$this->Cell(8);
			$this->Cell(0,4,'Knexflux',0,0,'',false,'https://knexflux.net');
			$this->SetXY(6,20);
			$this->Cell($this->DefPageSize[0] - 12,0,'','T');
			$this->SetXY($this->lMargin,26);
		}
		public function Footer(){
			$this->SetY(-5);
			//$this->SetX(0);
			$this->SetFont('Arial','I',9);
			$this->SetTextColor(0x77);
			$this->Cell(0,0,'- '.$this->PageNo().'/{nb} -',0,0,'C');
		}
		public function getPageHeight(){
			return $this->DefPageSize[1];
		}
		public function getRenderWidth(){
			return $this->DefPageSize[0] - $this->lMargin - $this->rMargin;
		}
		
		public function preLoadImage($file,$type = ''){
			if(isset($this->images[$file])){
				return;
			}
			if($type == ''){
				$pos = strrpos($file,'.');
				if(!$pos){
					$this->Error('Image file has no extension and no type was specified: '.$file);
				}
				$type = substr($file,$pos+1);
			}
			$type = strtolower($type);
			if($type == 'jpeg'){
				$type = 'jpg';
			}
			$mtd = '_parse'.$type;
			if(!method_exists($this,$mtd)){
				$this->Error('Unsupported image type: '.$type);
			}
			$info = $this->$mtd($file);
			$info['i'] = count($this->images)+1;
			$this->images[$file] = $info;
		}
		private function px2mm($px){
			return $px*25.4/100;
		}
		private function setBBCLineHeight($h){
			if($h > $this->bbc['lineheight']){
				$this->bbc['lineheight'] = $h;
			}
		}
		public function WriteBBC($bbc){
			//HTML parser
			//$html=strip_tags($html,"<b><u><i><a><img><p><br><strong><em><font><tr><blockquote>"); //supprime tous les tags sauf ceux reconnus
			
			$this->SetFont('Arial','',10);
			$this->SetTextColor(0);
			$this->bbc['lineheight'] = 5;
			
			$bbc = str_replace("\n",'[br]',$bbc);
			$a = preg_split('/\[([^\]]+)\]/U',$bbc,-1,PREG_SPLIT_DELIM_CAPTURE); //éclate la chaîne avec les balises
			foreach($a as $i=>$e){
				if($i%2==0){
					//Text
					if($this->bbc['href']){
						$this->PutLink($this->bbc['href'],$e);
					}else if($this->bbc['img']['use']){
						$this->Image($e, $this->GetX(), $this->GetY(), $this->px2mm($this->bbc['img']['width']), $this->px2mm($this->bbc['img']['height']));
						$dh = $this->bbc['img']['height'];
						if($dh == 0){
							$dh = $this->getImgHeight($e);
							if($this->bbc['img']['width'] != 0){
								$dh *= $this->bbc['img']['width']/$this->getImgWidth($e);
							}
						}
						$dh = $this->px2mm($dh);
						$this->setBBCLineHeight($dh);
					}else if($this->bbc['youtube']){
						$this->Cell(50,30,'Youtube Video',1,0,'C',0,'https://www.youtube.com/watch?v='.$e);
						$this->setBBCLineHeight(30);
					}else{
						$this->Write($this->bbc['lineheight'],stripslashes($e));
					}
				}else{
					//Tag
					if($e[0]=='/')
						$this->CloseTag(strtolower(substr($e,1)));
					else{
						//Extract attributes
						$a2 = explode(' ',$e);
						$tag = strtolower(explode('=',$a2[0])[0]);
						
						$attr=array();
						foreach($a2 as $v){
							if(preg_match('/([^=]*)=(.*)/',$v,$a3))
								$attr[strtolower($a3[1])]=$a3[2];
						}
						$this->OpenTag($tag,$attr);
					}
				}
			}
			$this->Ln($this->bbc['lineheight']);
		}
		private function OpenTag($tag, $attr)	{
			//Opening tag
			switch($tag){
				case 'b':
				case 'i':
				case 'u':
					$this->SetStyle($tag,true);
					break;
				case 'url':
					$this->bbc['href'] = $attr['url'];
					break;
				case 'img':
					$this->bbc['img']['use'] = true;
					$this->bbc['img']['width'] = isset($attr['width'])?$attr['width']:0;
					$this->bbc['img']['height'] = isset($attr['height'])?$attr['height']:0;
					break;
				case 'br':
					$this->Ln($this->bbc['lineheight']);
					$this->bbc['lineheight'] = 5;
					break;
				case 'size':
					if(isset($attr['size'])){
						$s = 10*((1/3)*$attr['size'] + (1/3));
						$this->SetFont('Arial','',$s);
						$this->setBBCLineHeight($s/2);
					}
					break;
				case 'color':
					if(isset($attr['color'])){
						$c = ltrim($attr['color'],'#');
						$r = 0;
						$g = 0;
						$b = 0;
						if(strlen($c) == 6){
							$r = hexdec(substr($c,0,2));
							$g = hexdec(substr($c,2,2));
							$b = hexdec(substr($c,4,2));
						}else{
							$r = hexdec($c[0]);
							$g = hexdec($c[1]);
							$b = hexdec($c[2]);
						}
						$this->SetTextColor($r,$g,$b);
					}
					break;
				case 'youtube':
					$this->bbc['youtube'] = true;
					break;
			}
		}
		private function CloseTag($tag){
			//Closing tag
			switch($tag){
				case 'b':
				case 'i':
				case 'u':
					$this->SetStyle($tag,false);
					break;
				case 'url':
					$this->bbc['href'] = '';
					break;
				case 'img':
					$this->bbc['img']['use'] = false;
					break;
				case 'size':
					$this->SetFont('Arial','',10);
					break;
				case 'color':
					$this->SetTextColor(0);
					break;
				case 'youtube':
					$this->bbc['youtube'] = false;
					break;
			}
		}
		private function SetStyle($tag, $enable){
			//Modify style and select corresponding font
			$this->bbc[$tag]+=($enable ? 1 : -1);
			$style='';
			foreach(array('b','i','u') as $s)
			{
				if($this->bbc[$s]>0)
					$style.=$s;
			}
			$this->SetFont('',$style);
		}
		private function PutLink($URL, $txt){
			//Put a hyperlink
			$this->SetTextColor(0,0,255);
			$this->SetStyle('u',true);
			$this->Write($this->bbc['lineheight'],$txt,$URL);
			$this->SetStyle('u',false);
			$this->SetTextColor(0);
		}
		public function getImgWidth($s){
			if(isset($this->images[$s])){
				return $this->images[$s]['w'];
			}
			return -1;
		}
		public function getImgHeight($s){
			if(isset($this->images[$s])){
				return $this->images[$s]['h'];
			}
			return -1;
		}
	}
	$pdf = new PDF('P','mm','A4');
	
	$textImageNotes = function($startJ,$j,$imgs) use ($pdf){
		$haveNotes = false;
		for($k = $startJ;$k < $j;$k++){
			$haveNotes |= $imgs[$k]->haveAnnotations;
		}
		if(!$haveNotes){
			$pdf->Ln($imgs[$startJ]->dh + 10);
			return;
		}
		
		$pdf->SetFont('Arial','',9);
		$pdf->SetTextColor(0x77);
		$first = true;
		$maxNotes = 0;
		for($k = $startJ;$k < $j;$k++){
			if($imgs[$k]->haveAnnotations){
				if($first){
					$pdf->SetY($imgs[$k]->y + $imgs[$k]->dh - 5);
					$pdf->Ln();
					$first = false;
				}
				$pdf->SetX($imgs[$k]->x);
				
				$pdf->Cell(0,5,'Image Notes:');
				$size = sizeof($imgs[$k]->getAnnotations());
				if($size > $maxNotes){
					$maxNotes = $size;
				}
			}
		}
		
		for($l = 0;$l < $maxNotes;$l++){
			$pdf->Ln();
			for($k = $startJ;$k < $j;$k++){
				if($imgs[$k]->haveAnnotations){
					$annotations = $imgs[$k]->getAnnotations();
					if(isset($annotations[$l])){
						$pdf->SetX($imgs[$k]->x);
						$pdf->Cell(0,5,($l+1).': '.$annotations[$l]['body_parsed']);
					}
				}
			}
		}
		$pdf->Ln(10);
		
	};
	
	$pdf->AliasNbPages();
	$pdf->SetTitle($instr->name,true);
	$pdf->SetCreator('knexflux.net',true);
	if($instr->owner['id_member']!=-1){
		$pdf->SetAuthor($instr->owner['real_name'],true);
	}
	$pdf->SetMargins(10,10);
	$pdf->AddPage();
	
	// print the name of the instruction
	$pdf->SetFont('Arial','B',25);
	$pdf->Cell(0,3,$instr->name,0,1,'',false,$instr->getUrl());
	$pdf->SetFont('Arial','B',10);
	$pdf->Cell(0,10,'By: '.$instr->owner['real_name'],0,1);
	
	// print the steps
	foreach($instr->steps as $i => $step){
		if($pdf->GetY() + 70 > $pdf->getPageHeight()){
			$pdf->AddPage();
		}
		$pdf->SetFont('Arial','',16);
		$pdf->Cell(0,10,$i==0?$txt['inst_intro']:$txt['inst_step'].' '.$i.': '.$step['title_parsed'],0,1);
		$maxHeight = 0;
		$startJ = 0;
		foreach($step['images'] as $j => &$img){
			$x = $pdf->GetX();
			$y = $pdf->GetY();
			
			$imgPath = $img->getPath('medium');
			$pdf->preLoadImage($imgPath);
			$h = $pdf->getImgHeight($imgPath);
			$w = $pdf->getImgWidth($imgPath);
			$dh = 65;
			$scale = $dh / $h;
			$dw = $scale * $w;
			
			if($x + $dw - 10 > $pdf->getRenderWidth()){
				$textImageNotes($startJ,$j,$step['images']);
				
				$y = $pdf->GetY();
				$startJ = $j;
				if($y + $dh >= $pdf->getPageHeight()){
					$pdf->AddPage();
				}
				$x = $pdf->GetX();
				$y = $pdf->GetY();
			}
			$img->x = $x;
			$img->y = $y;
			$img->dh = $dh;
			$img->dw = $dw;
			$img->haveAnnotations = sizeof($img->getAnnotations()) > 0;
			
			$pdf->Image($imgPath,$x,$y,0,$dh);
			
			
			$nx = $x + $dw + 10;
			$ny = $y;
			
			if($img->haveAnnotations){
				$pdf->SetFont('Arial','',8);
				$pdf->SetFillColor(255);
				$pdf->SetTextColor(0);
				foreach($img->getAnnotations() as $k => $ann){
					$k++;
					$pdf->SetXY($x + ($dw*$ann['x']) - 0.1,$y + ($dh*$ann['y']) - 0.1);
					$pdf->Cell($pdf->GetStringWidth((string)$j) + 1,3,'',0,0,'',true);
					$pdf->SetXY($x + ($dw*$ann['x']) - 0.5,$y + ($dh*$ann['y']) - 0.5);
					$pdf->Cell(0,4,(string)$k);
					
					$pdf->SetDrawColor(255);
					$pdf->SetLineWidth(0.3);
					$pdf->SetXY($x + ($dw*$ann['x']),$y + ($dh*$ann['y']));
					$pdf->Cell($dw*$ann['w'] - 0.2,$dh*$ann['h'] - 0.2,'',1);
					
					$pdf->SetDrawColor(0);
					$pdf->SetLineWidth(0.1);
					$pdf->SetXY($x + ($dw*$ann['x']) - 0.1,$y + ($dh*$ann['y']) - 0.1);
					$pdf->Cell($dw*$ann['w'],$dh*$ann['h'],'',1);
				}
			}
			$pdf->SetXY($nx,$ny);
			
		}
		$textImageNotes($startJ,$j+1,$step['images']); // $j+1 as we need to have one higher because else the for-loop increases for us
		
		
		$pdf->WriteBBC($step['body']);
		$pdf->Ln(10);
	}
	$pdf->Output('I',$instr->name.'.pdf',true);
}

?>
