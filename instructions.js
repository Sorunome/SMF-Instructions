ls = (function(){
	var getCookie = function(c_name){
			var i,x,y,ARRcookies=document.cookie.split(";");
			for(i=0;i<ARRcookies.length;i++){
				x=ARRcookies[i].substr(0,ARRcookies[i].indexOf("="));
				y=ARRcookies[i].substr(ARRcookies[i].indexOf("=")+1);
				x=x.replace(/^\s+|\s+$/g,"");
				if(x==c_name){
					return unescape(y);
				}
			}
		},
		setCookie = function(c_name,value,exdays){
			var exdate = new Date(),
				c_value = escape(value);
			exdate.setDate(exdate.getDate() + exdays);
			c_value += ((exdays===null) ? '' : '; expires='+exdate.toUTCString());
			document.cookie=c_name + '=' + c_value;
		},
		support = function(){
			try{
				localStorage.setItem('test',1);
				localStorage.removeItem('test');
				return true;
			}catch(e){
				return false;
			}
		};
	return {
		get:function(name){
			if(support()){
				return JSON.parse(localStorage.getItem(name));
			}
			return JSON.parse(getCookie(name));
		},
		set:function(name,value){
			value = JSON.stringify(value);
			if(support()){
				localStorage.setItem(name,value);
			}else{
				setCookie(name,value,30);
			}
		}
	};
})();

function instruction_init_display(curImgStep){
	instruction_register_delete();
	instruction_dispImage(curImgStep,0,false); // add image anotations
	var nextImage = function(){
			var $elem = $('.instruction_imagehover.current').next();
			if($elem.length > 0){
				$elem.click();
			}
		},
		prevImage = function(){
			var $elem = $('.instruction_imagehover.current').prev();
			if($elem.length > 0){
				$elem.click();
			}
		},
		nextBigImage = function(){
			var id = $('#instruction_bigimage > div > img').data('id') + 1;
			if(id >= instruction_images.length){
				return;
			}
			nextImage();
			instruction_dispImage(curImgStep,id,true);
		},
		prevBigImage = function(){
			var id = $('#instruction_bigimage > div > img').data('id') - 1;
			if(id < 0){
				return;
			}
			prevImage();
			instruction_dispImage(curImgStep,id,true);
		},
		startBigImage = function(step){
			curImgStep = step;
			$('#instruction_bigimage').addClass('show');
			instruction_dispImage(curImgStep,$('#instruction_mainimage_'+curImgStep+' > div > img').data('id'),true);
		},
		toggleAnnoatationType = function(){
			if(ls.get('instruction_annotation_type') == 1){
				ls.set('instruction_annotation_type',0);
			}else{
				ls.set('instruction_annotation_type',1);
			}
			$(window).trigger('resize'); // cause recalculating the image notes
		};
	$('.instruction_mainimage > div > img').click(function(){
		startBigImage(parseInt($(this).parent().data('step'),10));
	});
	$('#instruction_bigimage .close').click(function(e){
		e.stopPropagation();
		$('#instruction_bigimage').removeClass('show');
	});
	$('#instruction_bigimage > div > img,#instruction_big_annotations').click(function(e){
		e.stopPropagation();
	})
	$('#instruction_bigimage').click(function(e){
		if($(window).width() / 2 > e.pageX){
			prevBigImage();
		}else{
			nextBigImage();
		}
	});
	$(document).keydown(function(e){
		console.log(e);
		if($('#instruction_bigimage').hasClass('show')){
			switch(e.which){
				case 37: // left
					prevBigImage();
					break;
				case 39: // right
					nextBigImage();
					break;
				case 27: // esc
					$('#instruction_bigimage').removeClass('show');
					break;
				case 78: // n
					toggleAnnoatationType();
					break;
			}
		}else{
			switch(e.which){
				case 70: // f
					startBigImage(curImgStep);
					break;
				case 37: // left
					if(e.ctrlKey){
						var url = $('.instruction_stepcontainer .instruction_step.current').parent().prev().find('a').attr('href');
						if(url){
							window.location.href = url;
						}
					}else{
						prevImage();
					}
					break;
				case 39: // right
					if(e.ctrlKey){
						var url = $('.instruction_stepcontainer .instruction_step.current').parent().next().find('a').attr('href');
						if(url){
							window.location.href = url;
						}
					}else{
						nextImage();
					}
					break;
				case 78: // n
					toggleAnnoatationType();
					break;
			}
		}
	});
	$(window).resize(function(){
		$('#instruction_bigimage > div > img,.instruction_mainimage > div > img').trigger('calcresize');
	});
}

function instruction_dispImage(step,id,invocedElem){
	if(!instruction_images[step][id]){
		return;
	}
	var big = false;
	if(invocedElem === true){
		big = true;
	}
	var $mainImg = $((big?'#instruction_bigimage':'#instruction_mainimage_'+step)+' > div > img'),
		$annotations = $(big?'#instruction_big_annotations':'#instruction_mainimage_annotations_'+step);
	if(big){
		$mainImg.attr('src',instruction_images[step][id]['urls']['large']);
	}else{
		$mainImg.attr('src',instruction_images[step][id]['urls']['medium']);
	}
	$mainImg.data('id',id);
	if(!big && invocedElem!==false){
		$('#instruction_imageslider_'+step+' > div').removeClass('current');
	}
	
	$annotations.empty();
	$mainImg.off('resize').off('load');
	$mainImg.css('margin-left',0);
	console.log(instruction_images[step][id]);
	if(instruction_images[step][id]['annotations']){
		$mainImg.css('margin-left',0);
		$mainImg.off('calcresize');
		$mainImg.on('calcresize',function(e){
			var altAnnotations = ls.get('instruction_annotation_type') == 1;
			$mainImg.css('margin-left',0);
			if(big){
				$('#instruction_big_annotations').css('max-width',(($(window).width() - $mainImg.width()) / 2) - $('#instruction_bigimage .right').outerWidth()*2);
				$('#instruction_big_annotations').css('max-height',$mainImg.height() - 50);
			}
			$annotations.addClass(altAnnotations?'':'hoverannotations').removeClass(altAnnotations?'hoverannotations':'').empty().append(
				$.map(instruction_images[step][id]['annotations'],function(a,i){
					return [
						$('<div>').addClass('instruction_annotation').append(
							$('<div>').addClass('instruction_annotationBox').css({
								'top':$mainImg.height()*a.y + $mainImg.position().top,
								'height':$mainImg.height()*a.h,
								'left':$mainImg.width()*a.x + $mainImg.position().left,
								'width':$mainImg.width()*a.w
							}).append(
								$('<span>').addClass('instruction_annotationNum').addClass(altAnnotations?'':'hoverannotations').text(i+1)
							),
							$('<div>').addClass('instruction_annotationText').addClass(altAnnotations?'':'hoverannotations').html((altAnnotations?(i+1).toString()+': ':'')+a.body).css({
								'top':$mainImg.height()*(a.y+a.h) + $mainImg.position().top,
								'left':$mainImg.width()*a.x + $mainImg.position().left
							})
						)
					];
				})
			);
			var offset = altAnnotations && big?$annotations.outerWidth():0;
			if(big){
				$mainImg.css('margin-left',offset);
				if(offset != 0){
					$('.instruction_annotationBox').map(function(){
						$(this).css('left',$(this).css('left') + (offset / 2));
					});
				}
			}else{
				$mainImg.parent().css('width',$mainImg.width());
			}
		});
		$mainImg.load(function(){
			$mainImg.trigger('calcresize');
		});
	}
	if(!big && invocedElem!==undefined){
		$(invocedElem).addClass('current');
	}
}


function instruction_register_delete(){
	$('.instructions_edit_delete_instruciton').click(function(e){
		if(!confirm('Are you sure you want to delete this instruction AND all steps associated with it?')){
			e.preventDefault();
		}
	});
}


// edit stuff
function instruction_edit_runImport(){
	var $status = $('#import_status').on('DOMNodeInserted',function(e){
			document.getElementById('import_bottom').scrollIntoView();
		}),
		numSteps = instruction_import_data.length,
		tag = instruction_import_data.title.replace(/[^a-zA-Z0-9 -]/g,'').trim(),
		updateImport = function(done){
			$.ajax({
				url:smf_scripturl+'?action=instructions;iblesimport='+instruction_edit_id+(done===true?';done':''),
				async:false,
				method:'POST',
				data:{data:JSON.stringify(instruction_import_data)}
			}).done(function(data){
				if(!data.success){
					$status.append("<b>ERROR: Couldn't initialize image import"+(data.msg?': '+data.msg:'!')+'</b><br>');
				}
			});
		};
	$.map(instruction_import_data.steps,function(step,i){
		$status.append('Starting with import of step '+i+'...<br>');
		if(step.done){
			$status.append('Nothing to do here in step '+i+'!<br>');
		}else{
			var imagelist;
			$.ajax({
				url:smf_scripturl+'?action=instructions;getimages='+instruction_edit_id+';step='+parseInt(step.id,10),
				async:false,
				method:'GET'
			}).done(function(data){
				if(!data.success){
					$status.append("<b>ERROR: Couldn't fetch image list for step"+(data.msg?': '+data.msg:'!')+'</b><br>');
				}
				imagelist = data.images;
			});
			$.map(step.images,function(img,ii){
				if(!img.done){
					var imagedata;
					$status.append('Fetching image '+img.url.toString()+'...<br>');
					$.ajax({
						url:smf_scripturl+'?action=instructions;urlupload',
						async:false,
						method:'POST',
						data:{url:img.url}
					}).done(function(data){
						imagedata = data;
					});
					if(!imagedata.success){
						$status.append("<b>ERROR: Couldn't fetch image "+img.url.toString()+' '+(data.msg?': '+data.msg:(data['upload-error']?': '+data['upload-error']:'!'))+'</b><br>');
					}else{
						instruction_import_data.steps[i].images[ii].done = true;
						updateImport();
						imagelist.push(imagedata.image.id)
						$status.append('Adding image '+img.url.toString()+' to step '+i+'...<br>');
						$.ajax({
							url:smf_scripturl+'?action=instructions;save='+instruction_edit_id+';stepid='+parseInt(step.id,10),
							async:false,
							method:'POST',
							data:{
								images:JSON.stringify(imagelist)
							}
						}).done(function(data){
							if(!data.success){
								$status.append("<b>ERROR: Couldn't save step "+i+(data.msg?': '+data.msg:'!')+'</b><br>');
							}
						});
						$status.append('Adding tag to image '+img.url+'...<br>');
						$.ajax({
							url:smf_scripturl+'?action=instructions;setimgtags='+imagedata.image.id,
							async:false,
							method:'POST',
							data:{
								tags:tag
							}
						}).done(function(data){
							if(!data.success){
								$status.append("<b>ERROR: Couldn't add tag to image"+(data.msg?': '+data.msg:'!')+'</b><br>');
							}
						});
						$status.append('Saving annotations of image '+img.url.toString()+'...<br>');
						$.ajax({
							url:smf_scripturl+'?action=instructions;savenotes='+imagedata.image.id,
							async:false,
							method:'POST',
							data:{
								annotations:JSON.stringify(img.annotations)
							}
						}).done(function(data){
							if(!data.success){
								$status.append("<b>ERROR: Couldn't add annotations to image"+(data.msg?': '+data.msg:'!')+'</b><br>');
							}
						});
					}
				}
			});
			$status.append('Importing of step '+i+' done!<br>');
			instruction_import_data.steps[i].done = true;
			updateImport();
		}
	});
	updateImport(true);
	window.location.href = smf_scripturl+'?action=instructions;edit='+instruction_edit_id;
}

function instruction_edit_initImport(){
	var replace = confirm("Replace this instruction with 'ible? Cancle will append it to the end of the instruction."),
		ibleid = prompt("'ible URL");
	if(ibleid===null){
		return;
	}
	ibleid = ibleid.replace(/^(http:\/\/www\.instructables\.com\/id\/)([^\/?;#]+)(.*)/,'$2');
	if(ibleid.indexOf('/')!==-1){
		alert('Invalid URL');
		return;
	}
	$.getJSON(smf_scripturl+'?action=instructions;getinstructable='+encodeURIComponent(ibleid)).done(function(ible){
		if(ible.success === false){
			alert('Instructable not found!');
			return;
		}
		if(ible.type != 'Step by Step'){
			alert('You can only import step by step instructables!');
			return;
		}
		if(ible.author.url.indexOf(smf_scripturl.split('/index.php')[0])!==0){
			alert('Please set your homepage URL on instructables temporarily to your forum profile to prove that you own this instruction!');
			return;
		}
		if(!confirm('Continue with import?\nInstructable to import: '+ible.title+'\nImported instructabl will '+(replace?'replace':'be added to')+' the current instruction\nThis can take some time, just leave the page open!')){
			return;
		}
		instruction_edit_save(false,function(){
			var addInstructions = function(){
				var totalSteps = ible.steps.length,
					instructionStepIds = [],
					addStep = function(i){
						$.getJSON(smf_scripturl+'?action=instructions;addstep='+instruction_edit_id).done(function(data){
							if(!data.success){
								alert("ERROR: Couldn't add a step"+(data.msg?': '+data.msg:'!'));
								alert('Instructable import failed');
							}else{
								var bbcode = $('#instructions_edit_bbceditor').sceditor('instance').toBBCode(ible.steps[i].body),
									title = ible.steps[i].title;
								instructionStepIds.push({
									id:data.stepid,
									done:false,
									images:$.map(ible.steps[i].files,function(f){
										if(f.image){
											return {
												url:f.downloadUrl,
												done:false,
												annotations:$.map(f.imageNotes,function(a){
													return {
														w:a.width,
														h:a.height,
														y:a.top,
														x:a.left,
														body:a.text
													}
												})
											};
										}
									})
								});
								$.post(smf_scripturl+'?action=instructions;save='+instruction_edit_id+';stepid='+data.stepid,{
									body:bbcode,
									title:title
								}).done(function(data2){
									if(!data.success){
										alert("ERROR: Couldn't save a step"+(data.msg?': '+data.msg:'!'));
									}
									i++;
									if(i < totalSteps){
										addStep(i);
									}else{
										var callback = function(){
											// here we store in the DB the stuff needed for the import and then redirect to the same page again where the import will begin!
											$.post(smf_scripturl+'?action=instructions;iblesimport='+instruction_edit_id,{
												data:JSON.stringify({
													title:ible.title,
													steps:instructionStepIds
												})
											}).done(function(data){
												if(!data.success){
													alert("ERROR: Couldn't initialize image import"+(data.msg?': '+data.msg:'!'));
												}
												window.location.href = smf_scripturl+'?action=instructions;edit='+instruction_edit_id; // we are now in image import mode!
											});
										};
										if(replace){
											// now we can delete the last unneeded step
											$.getJSON(smf_scripturl+'?action=instructions;deletestep='+instruction_edit_id+';stepid='+instruction_edit_stepid).done(function(data){
												if(!data.success){
													alert("ERROR: Couldn't delete a step"+(data.msg?': '+data.msg:'!'));
												}
												callback();
											});
										}else{
											callback();
										}
									}
								});
							}
						});
					};
				addStep(0);
			};
			if(replace){
				// delete all the steps except the one which is currently been edited as we need a step in an instruction
				var todo_steps = $('ul.instruction_stepcontainer.invisibleList > li').length - 1; // -1 as we need to save one step
				if(todo_steps>0){
					$('ul.instruction_stepcontainer.invisibleList > li').map(function(i){
						var id = parseInt(this.dataset.id,10)
						if(id!=-1 && id!=instruction_edit_stepid){
							$.getJSON(smf_scripturl+'?action=instructions;deletestep='+instruction_edit_id+';stepid='+id).done(function(data){
								if(!data.success){
									alert("ERROR: Couldn't delete a step"+(data.msg?': '+data.msg:'!'));
								}
								todo_steps--;
								if(todo_steps==0){
									addInstructions();
								}
							});
						};
					});
				}else{
					addInstructions();
				}
			}else{
				addInstructions();
			}
		});
	});
}

function instruction_edit_initSCEditor(){
	// Replace the <textarea id="editor1"> with an SCEditor
	// instance, using the "bbcode" plugin, customizing some of the
	// editor configuration options to fit BBCode environment.
	$.sceditor.plugins.bbcode.bbcode.set('ul',{tags:{'abc':null}}); // add stuff that will never trigger
	$.sceditor.plugins.bbcode.bbcode.set('ol',{tags:{'abc':null}});
	
	$.sceditor.plugins.bbcode.bbcode.set('list',{
		styles: {},
		tags: {
			"ul": null,
			"ol": null
		},
		isSelfClosing: false,
		isInline: false,
		isHtmlInline: undefined,
		allowedChildren: ['li'],
		allowsEmpty: false,
		excludeClosing: false,
		skipLastLineBreak: false,

		breakBefore: false,
		breakStart: false,
		breakEnd: false,
		breakAfter: false,

		format: function(element,content){
			if(element[0].tagName.toLowerCase() == 'ol'){
				return '[list type=decimal]'+content+'[/list]';
			}else{
				return '[list]'+content+'[/list]';
			}
		},
		html: function(token, attr, content){
			if(typeof attr.type !== "undefined" && attr.type == "decimal"){
				return '<ol>'+content+'</ol>';
			}else{
				return '<ul>'+content+'</ul>';
			}
		},

		quoteType: $.sceditor.BBCodeParser.QuoteType.auto
	});
	
	$.sceditor.plugins.bbcode.bbcode.set('img',{
		styles: {},
		tags: {
			"img": null
		},
		isSelfClosing: false,
		isInline: true,
		isHtmlInline: undefined,
		allowedChildren: null,
		allowsEmpty: false,
		excludeClosing: false,
		skipLastLineBreak: false,

		breakBefore: false,
		breakStart: false,
		breakEnd: false,
		breakAfter: false,

		format: function(element,content){
			return '[img'+(element[0].height?' height='+element[0].height:'')+(element[0].width?' width='+element[0].width:'')+']'+element[0].src+'[/img]';
		},
		html: function(token, attr, content){
			return '<img src="'+$('<span>').text(content).html()+'"'+(attr.height?' height="'+attr.height+'"':'')+(attr.width?' width="'+attr.width+'"':'')+'>';
		},

		quoteType: $.sceditor.BBCodeParser.QuoteType.auto
	});
	$.sceditor.plugins.bbcode.bbcode.set('youtube',{
		tags: {
			"iframe": null
		},
		format: function(element,content){
			var ytid = element.attr('data-youtube-id');
			if(!ytid){
				ytid = element.attr('src');
				if(ytid){
					ytid = ytid.split('youtube.com/embed/')[1].split('/')[0].split('?')[0].split('#')[0];
				}
			}
			return ytid?'[youtube]' + ytid + '[/youtube]':content;
		}
	});
	$('#instructions_edit_bbceditor').css('height',500).sceditor({
		plugins:'bbcode',
		width:'100%',
		autoUpdate:true,
		style:instruction_edit_sceditorurl+'/jquery.sceditor.default.min.css',
		emoticons:{
			dropdown:SCEDITOR_SMILEYS,
			more:{},
			hidden:SCEDITOR_SMILEYS_HIDDEN
		}
	});
}
function instruction_edit_buildEditor(){
	instruction_edit_initSCEditor();
	
	var uploadObj = $('#instructions_fileupload').uploadFile({
		url:smf_scripturl+'?action=instructions;fileupload=1',
		multiple:true,
		dragDrop:true,
		fileName:"files",
		acceptFiles:"image/*",
		returnType:'json',
		customErrorKeyStr:'upload-error',
		showPreview:false,
		previewHeight:'100px',
		previewWidth:'100px',
		autoSubmit:false,
		sequential:true,
		sequentialCount:5,
		onSuccess:instruction_edit_uploadSuccess,
		showStatusAfterSuccess:false
	});
	instruction_upload_id_offset = 0;
	instruciton_upload_order = [];
	$('#instructions_start_upload').click(function(e){
		$('#instructions_edit_files li.ui-sortable-handle').map(function(i){
			$(this).data('sorder',i);
		});
		instruciton_upload_order = $('#instructions_edit_upload_img > .ajax-file-upload-statusbar > .ajax-file-upload-filename').map(function(){
			var s = $(this).text();
			return s.substr(s.indexOf(' ')+1).toLowerCase();
		}).get().reverse()
		instruction_upload_id_offset = $('#instructions_edit_files li.ui-sortable-handle').length;
		uploadObj.startUpload();
	});
	
	$('#instruction_add_annotation').click(function(e){
		e.preventDefault();
		e.stopPropagation();
		if($('#instructions_edit_fileannotation_box > div > img').length > 0){
			instruction_edit_annotation_changed = true;
			$('#instructions_edit_fileannotation').append(
				instruction_edit_buildAnnotation(0.1,0.1,0.1,0.1,'')
			);
		}
	});
	instruction_edit_buildImageNotes(0);
	instruction_edit_buildImages();
	
	instruction_edit_stuff_changed = false;
	instruction_edit_annotation_changed = false;
	$('#instruction_edit_name input').keyup(function(){
		instruction_edit_stuff_changed = true;
	});
	$('a').click(function(e){
		if($(this).hasClass('sceditor-button') || $(this).hasClass('instructions_nosaveask') || $(this).parents('.instructions_nosaveask').length != 0){
			return;
		}
		if($(this).hasClass('instruction_save')){
			e.preventDefault();
			instruction_edit_save();
		}else{
			if(instruction_edit_annotation_changed || instruction_edit_stuff_changed || $('#instructions_edit_bbceditor').val()!=instruction_edit_beginningText){
				if(confirm('Save unsaved changes first?')){
					instruction_edit_save($(this).attr('href'));
					e.preventDefault();
				}
			}
		}
	});
	$.get(smf_scripturl+'?action=instructions;getimgtags=1').done(function(data){
		$('#instructions_edit_imagetabs select').append(
			$('<option>').text('all').val('-1'),
			$.map(data.tags,function(t){
				return $('<option>').text(t).val(t);
			})
		).change(function(){
			instruction_edit_getimglibrary(this.value,0);
		});
	});
	$('#instructions_edit_imagetabs').tabs({
		activate:function(event,ui){
			if(ui.newPanel[0].dataset.tab == 'library' && $('#instructions_edit_imagelibrary').is(':empty')){
				instruction_edit_getimglibrary($('#instructions_edit_imagetabs select').val(),0);
			}
		}
	});
	$('#instructions_edit_upload_tabs').tabs();
	
	instructions_uploading_url = false;
	$('#instructions_start_urlupload').click(function(e){
		Instructions_edit_upload_url();
	});
	
	$('.instructions_edit_delete_step').click(function(e){
		if(!confirm('Are you sure you want to delete this step?')){
			e.preventDefault();
		}
	});
	instruction_register_delete();
	$('ul.instruction_stepcontainer.invisibleList').sortable({
		update:function(event,ui){
			var steporder = $('ul.instruction_stepcontainer.invisibleList > li').map(function(i){
				$(this).find('.txt').text((i==0?'intro':'Step '+i));
				return parseInt(this.dataset.id,10);
			}).get();
			$.post(instruction_urls.savesteporder,{
				steporder:JSON.stringify(steporder)
			}).done(function(data){
				if(!data.success){
					alert("ERROR: Couldn't save step order"+(data.msg?': '+data.msg:'!'));
				}
			});
		}
	});
	
	$('.instructions_publish_open').click(function(e){
		e.preventDefault();
		if($('ul.instruction_stepcontainer.invisibleList > li:first .instruction_step_img').css('background-image').match(/\/\d+\//) == null){
			alert('The intro needs an image!');
		}else{
			$('#instructions_publish').addClass('show');
		}
	});
	$('#instructions_close_publish').click(function(e){
		e.preventDefault();
		$('#instructions_publish').removeClass('show');
	});
	$('#instructions_publish select').change(function(e){
		if(this.options[this.selectedIndex].dataset.can == 1){
			$(this).removeClass('cant');
		}else{
			$(this).addClass('cant');
		}
	}).trigger('change');
	$('#instructions_publish_real').click(function(e){
		e.preventDefault();
		if($('#instructions_publish select').hasClass('cant')){
			alert('Please enter a valid category!');
		}else{
			$.post(instruction_urls.publish,{
				category:$('#instructions_publish select').val(),
				post:$('#instructions_publish textarea').val()
			}).done(function(data){
				if(!data.success){
					alert("ERROR: Couldn't publish instruciton"+(data.msg?': '+data.msg:'!'));
					return;
				}
				window.location.href = data.url;
			})
		}
	});
	
	$('.instructions_new_version').click(function(e){
		e.preventDefault();
		var url = prompt('URL or ID of new instruction version (empty for no newer version)');
		if(url !== null){
			url = url.replace(/^(.*\/instructions\/)([^\/?;#]+)(.*)/,'$2');
			url = url.replace(/^(.*id=)([^\/&;#]+)(.*)/,'$2');
			$.post(instruction_urls.newversion,{
				newid:url
			}).done(function(data){
				if(data.success){
					alert('Set the new version of this instruction!');
				}else{
					alert("ERROR: Couldn't set new version of instruction"+(data.msg?': '+data.msg:'!'));
				}
			})
		}
	});
	
	$('.instructions_ible_import').click(function(e){
		e.preventDefault();
		instruction_edit_initImport();
	});
}

function Instructions_edit_upload_url(){
	var url = $('#instructions_edit_upload_url input').val();
	if(instructions_uploading_url || url==''){
		return;
	}
	instructions_uploading_url = true;
	$('#instructions_uploadurl_progress').text('Uploading....');
	$.post(smf_scripturl+'?action=instructions;urlupload',{
		url:url
	}).done(function(data){
		instructions_uploading_url = false;
		if(!data.success){
			$('#instructions_uploadurl_progress').text("ERROR: Couldn't fetch image"+(data.msg?': '+data.msg:(data['upload-error']?': '+data['upload-error']:'!')));
			return;
		}
		$('#instructions_uploadurl_progress').text('');
		$('#instructions_edit_upload_url input').val('');
		instruction_edit_uploadSuccess([],data);
	}).error(function(data){
		$('#instructions_uploadurl_progress').text("ERROR: Couldn't fetch image!");
		instructions_uploading_url = false;
	});
}

function instruction_edit_getimglibrary(tag,offset){
	$.getJSON(smf_scripturl+'?action=instructions;getlibrary='+tag+';offset='+offset).done(function(data){
		var $pager = $('<div>').addClass('instruction_edit_pager').append(
				(offset!=0?$('<a>').text('« Previous').addClass('instruction_edit_previous'):''),
				$('<span>').text(' '+(offset + 1).toString()+'-'+(offset + data.images.length).toString()+' of '+data.max.toString()+' '),
				((offset+30)<(data.pages*30)?$('<a>').text('Next »').addClass('instruction_edit_next'):'')
			);
		$('#instructions_edit_imagelibrary').empty().append(
			$pager.clone(),
			$('<div>').append(
				$.map(data.images,function(i){
					return $('<div>').addClass('instruction_imagehover').css('background-image','url("'+$('<span>').text(i.urls.square).html()+'")').click(function(e){
						instruction_edit_addImage($(this).data('img'));
					}).data('img',i).append(
						$('<span>').text('x').addClass('instruction_edit_image_close').click(function(e){
							e.stopPropagation();
							if(confirm('Are you sure you want to delete this image completely? This cannot be undone!')){
								$.get(smf_scripturl+'?action=instructions;deleteimage='+$(this).parent().data('img').id).done(function(data){
									if(data.success){
										instruction_edit_getimglibrary(tag,offset);
										return;
									}
									alert("ERROR: Couldn't delete image"+(data.msg?': '+data.msg:'!'));
								});
							}
						})
					);
				})
			),
			$pager
		);
		$('.instruction_edit_previous').button().click(function(e){
			e.preventDefault();
			instruction_edit_getimglibrary(tag,Math.max(0,offset-30));
		});
		$('.instruction_edit_next').button().click(function(e){
			e.preventDefault();
			instruction_edit_getimglibrary(tag,Math.min(data.max - 1,offset+30));
		});
	});
}

function instruction_edit_setImageTags(id,tags){
	$.post(smf_scripturl+'?action=instructions;setimgtags='+id,{
		tags:tags.trim()
	}).done(function(data){
		if(!data.success){
			alert("ERROR: Couldn't save image tags"+(data.msg?': '+data.msg:'!'));
		}
	});
}

function instruction_edit_uploadSuccess(fileArray,data,xhr,pd){
	if($('#instructions_edit_autoaddnewimg')[0].checked && $('#instructions_edit_autotagnewimg').val()!=''){
		var i = instruciton_upload_order.indexOf(data.image.name.toLowerCase());
		if(i != -1){
			instruction_edit_addImage(data.image,i + instruction_upload_id_offset);
		}else{
			instruction_edit_addImage(data.image);
		}
		instruction_edit_setImageTags(data.image.id,$('#instructions_edit_autotagnewimg').val());
	}
}

function instruction_edit_buildAnnotation(x,y,w,h,body){
	var $mainImg = $('#instructions_edit_fileannotation_box > div > img'),
		imgWidth = $mainImg.width(),
		imgHeight = $mainImg.height(),
		recalcTextBox = function(){
			$annotationText.css({
				left:$annotationBox.css('left'),
				top:parseInt($annotationBox.css('top'),10) + $annotationBox.height() + 4
			})
		},
		saveAnnotationPosition = function(){
			$annotation.data({
				x:parseInt($annotationBox.css('left'),10) / imgWidth,
				y:parseInt($annotationBox.css('top'),10) / imgHeight,
				w:$annotationBox.width() / imgWidth,
				h:$annotationBox.height() / imgHeight
			});
			instruction_edit_annotation_changed = true;
		},
		$annotationBox = $('<div>').addClass('annotation_box').css({
			'top':imgHeight*y,
			'height':imgHeight*h,
			'left':imgWidth*x,
			'width':imgWidth*w
		}).draggable({
			cursor:'corsshair',
			scroll:false,
			drag:function(event,ui){
				ui.position.left = Math.min(imgWidth - $annotationBox.width(),Math.max(0,ui.position.left));
				ui.position.top = Math.min(imgHeight - $annotationBox.height(),Math.max(0,ui.position.top));
				recalcTextBox();
				saveAnnotationPosition();
			}
		}).resizable({
			containment:$('#instructions_edit_fileannotation_box > div > img'),
			handles:'all',
			resize:function(event,ui){
				if(ui.size.width === undefined || ui.size.width < 0){
					ui.size.width = lastResizeWidth;
				}else{
					lastResizeWidth = ui.size.width;
				}
				recalcTextBox();
				saveAnnotationPosition();
			}
		}),
		lastResizeWidth = $annotationBox.width(),
		$annotationText = $('<div>').addClass('annotation_text').append(
			$('<textarea>').text(body).keyup(function(e){
				$annotation.data('body',this.value);
				instruction_edit_annotation_changed = true;
			}),'&nbsp;',
			$('<a>').attr('href','#').text('Delete').click(function(e){
				instruction_edit_annotation_changed = true;
				e.preventDefault();
				var $elem = $(this).parent().parent();
				$elem.css({
					transition:'opacity 0.4s ease 0s',
					opacity:0
				})
				setTimeout(function(){
					$elem.remove();
				},400);
			})
		),
		$annotation = $('<div>').addClass('annotation').append(
			$annotationBox,
			$annotationText
		).data({
			x:x,
			y:y,
			w:w,
			h:h,
			body:body
		});
	recalcTextBox();
	return $annotation;
}

function instruction_edit_buildImageNotes(id){
	$('#instructions_edit_fileannotation_box > div').empty();
	instruction_edit_curimage = id;
	if(id!==undefined && instruction_edit_images[id]!==undefined){
		$('#instructions_edit_fileannotation_box > div').append(
			$('<img>').attr('src',instruction_edit_images[id].urls.medium),
			$('<div>').attr('id','instructions_edit_fileannotation'),
			$('<div>').attr('class','imagename').text(instruction_edit_images[id].name)
		);
		if(instruction_edit_images[id].annotations){
			$('#instructions_edit_fileannotation_box > div > img').load(function(){
				$('#instructions_edit_fileannotation').empty().append(
					$.map(instruction_edit_images[id].annotations,function(a,i){
						return instruction_edit_buildAnnotation(a.x,a.y,a.w,a.h,a.body)
					})
				);
				$(this).off('load');
			});
		}
	}
}

function instruction_edit_build_single_image(i,ii,sorder){
	if(sorder === undefined){
		sorder = $('#instructions_edit_files li.ui-sortable-handle').length;
	}
	return $('<li>').append(
		$('<div>').addClass('instruction_imagehover').addClass((instruction_edit_curimage===ii?'current':'')).css('background-image','url("'+$('<span>').text(i.urls.square).html()+'")').append(
			$('<span>').addClass('instruction_edit_image_close').text('x').click(function(e){
				var _self = this,
				callback = function(){
					$(_self).parent().parent().parent().find('li div').removeClass('current');
					
					instruction_edit_buildImageNotes(0);
					$(_self).parent().parent().parent().find('li div:first').addClass('current');
					$(_self).parent().parent().remove();
				};
				e.stopPropagation();
				
				instruction_edit_stuff_changed = true;
				if(instruction_edit_annotation_changed){
					instruction_edit_save_annotation(callback);
				}else{
					callback();
				}
			})
		).data('offset',ii).click(function(e){
			var _self = this,
				callback = function(){
					$(_self).parent().parent().find('li div').removeClass('current');
					instruction_edit_buildImageNotes($(_self).data('offset'));
					$(_self).addClass('current');
				};
			if(instruction_edit_annotation_changed){
				instruction_edit_save_annotation(callback);
			}else{
				callback();
			}
		})
	).data({
		id:i.id,
		sorder:sorder
	});
}

function instruction_edit_addImage(img,i){
	instruction_edit_stuff_changed = true;
	instruction_edit_images.push(img);
	$('#instructions_edit_files > ul').append(instruction_edit_build_single_image(img,instruction_edit_images.length-1,i)).sortable('refresh');
	if(i !== undefined){
		setTimeout(function(){ // please kill me, I don't know why this is needed ;-;
			$('#instructions_edit_files > ul li.ui-sortable-handle').sort(function(a,b){
				return $(a).data('sorder') - $(b).data('sorder');
			}).appendTo($('#instructions_edit_files > ul'));
			$('#instructions_edit_files > ul').sortable('refresh');
			console.log('sorted!');
		},100);
	}
}

function instruction_edit_buildImages(){
	$('#instructions_edit_files').empty();
	var $ul = $('<ul>').addClass('invisibleList').append(
		$.map(instruction_edit_images,instruction_edit_build_single_image)
	);
	$ul.sortable({
		update:function(event,ui){
			instruction_edit_stuff_changed = true;
		}
	});
	$('#instructions_edit_files').append($ul);
}

function instruction_edit_save_annotation(callback){
	if(instruction_edit_curimage === undefined || instruction_edit_images[instruction_edit_curimage]===undefined){
		if(callback!==undefined){
			callback();
		}
		return;
	}
	var id = instruction_edit_images[instruction_edit_curimage].id,
		annotations = $('#instructions_edit_fileannotation > .annotation').map(function(){
			return {
				x:$(this).data('x'),
				y:$(this).data('y'),
				w:$(this).data('w'),
				h:$(this).data('h'),
				body:$(this).data('body')
			};
		}).get();
	instruction_edit_images[instruction_edit_curimage].annotations = annotations;
	$.post(smf_scripturl+'?action=instructions;savenotes='+id,{
		annotations:JSON.stringify(annotations)
	}).done(function(data){
		if(!data.success){
			alert("ERROR: Couldn't save image notes"+(data.msg?': '+data.msg:'!'));
		}else{
			instruction_edit_annotation_changed = false;
		}
		callback();
	})
}

function instruction_edit_save(redirect,innerCallback){
	var images = $('#instructions_edit_files > ul > li').map(function(){
		return $(this).data('id');
	}).get();
	$.post(instruction_urls.save,{
		body:$('#instructions_edit_bbceditor').val(),
		title:$('#instruction_edit_name input').val(),
		images:JSON.stringify(images)
	}).done(function(data){
		if(data.success){
			var callback = function(){
				alert('Instruction saved!');
				if(redirect && redirect != ''){
					window.location.href = redirect;
				}else{
					if(redirect === false){
						instruction_edit_id = data.instruction_id;
						instruction_edit_stepid = data.step_id;
					}
					instruction_edit_stuff_changed = false;
					instruction_edit_beginningText = $('#instructions_edit_bbceditor').val();
					if(innerCallback!==undefined){
						innerCallback();
					}
				}
			};
			if(instruction_edit_annotation_changed){
				instruction_edit_save_annotation(callback);
			}else{
				callback();
			}
			if(redirect !== false && (data.new_instruction)){
				window.location.href = data.url; // reload as we created a new instruction
			}
		}else{
			alert("ERROR: Couldn't save instruction"+(data.msg?': '+data.msg:'!'));
		}
	})
}
