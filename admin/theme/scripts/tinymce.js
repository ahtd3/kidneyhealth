/**
 * Sets up the full editor
 * @param	{HTMLTextAreaElement}	element			The element to start TinyMCE on
 * @param	{Function}				pickerCallback	The callback to call for the picker
 * @return	{Promise<tinymce[]>}					A promise for the initialised instances
 */
export function startTinymce(element, pickerCallback)
{
	/* initialise tinymce instances */
	//noinspection JSUnusedGlobalSymbols
	return tinymce.init(
	{
		target: element,
		
		// General options
		plugins : "anchor,charmap,code,fullscreen,image,link,lists,media,preview,searchreplace,table,template,visualchars",
		dialog_type : "modal",
		relative_urls: false,
		paste_as_text: true,
		browser_spellcheck: true,
		height: 600,
		
		//form plugin
		extended_valid_elements : "form[name|id|action|method|enctype|accept-charset|onsubmit|onreset|target|class],input[id|name|type|value|size|maxlength|checked|accept|src|width|height|disabled|readonly|tabindex|accesskey|onfocus|onblur|onchange|onselect|onclick|onkeyup|onkeydown|required|style|class|placeholder],textarea[id|name|rows|cols|maxlength|disabled|readonly|tabindex|accesskey|onfocus|onblur|onchange|onselect|onclick|onkeyup|onkeydown|required|style|class|placeholder],option[name|id|value|selected|style|class],select[id|name|type|value|size|maxlength|checked|width|height|disabled|readonly|tabindex|accesskey|onfocus|onblur|onchange|onselect|onclick|multiple|style|class],th[*],thead[*],tfoot[*],iframe[*],span[*]",
		
		// Theme options
		toolbar: "blocks | undo redo | cut copy paste | bullist numlist | bold italic subscript superscript | alignleft aligncenter alignright alignjustify | link unlink anchor image media | hr charmap table removeformat | visualchars searchreplace | code fullscreen preview",
		toolbar_mode: "wrap",
		
		// Example content CSS (should be your site CSS)
		content_css : "/theme/style.css",
		menubar: false,
		schema: "html5",
		
		valid_children: "+a[p|div|section|article|h1|h2|h3|h4|h5|h6]",
		block_formats: "Paragraph=p;Heading=h2;Subheading=h3;H4=h4;Preformatted=pre;Centre Block=centreblock;Float Left=floatleft;Float Right=floatright;",
		
		formats:
		{
			centreblock:
			{
				attributes: {class: "centre-block"},
				block: "p",
				remove: "all"
			},
			
			floatleft:
			{
				attributes: {class: "float-left"},
				block: "p",
				remove: "all"
			},
			
			floatright:
			{
				attributes: {class: "float-right"},
				block: "p",
				remove: "all"
			}
		},
		
		link_class_list: [
		{
			title: "None",
			value: ""
		},
		{
			title: "Button",
			value: "button"
		}],
		
		image_class_list: [
		{
			title: "None",
			value: ""
		},
		{
			title: "Centre Block",
			value: "centre-block",
		},
		{
			title: "Float Left",
			value: "float-left"
		},
		{
			title: "Float Right",
			value: "float-right"
		}],
		
		automatic_uploads: true,
		images_upload_url: "/admin/picker/image/file/modify/",
		images_reuse_filename: true,
		
		file_picker_types: 'file image',
		
		file_picker_callback: pickerCallback
	});
}

/**
 * Sets up the editor with a minimal taskbar
 * @param	{HTMLTextAreaElement}	element		The element to start TinyMCE on
 * @return	{Promise<tinymce[]>}				A promise for the initialised instances
 */
export function startBasicTinymce(element)
{
	/* initialise tinymce instances */
	return tinymce.init(
	{
		target: element,
		
		// General options
		plugins : "lists,searchreplace",
		dialog_type : "modal",
		relative_urls: false,
		paste_as_text: true,
		height: 200,
		
		//form plugin
		extended_valid_elements : "form[name|id|action|method|enctype|accept-charset|onsubmit|onreset|target|class],input[id|name|type|value|size|maxlength|checked|accept|src|width|height|disabled|readonly|tabindex|accesskey|onfocus|onblur|onchange|onselect|onclick|onkeyup|onkeydown|required|style|class|placeholder],textarea[id|name|rows|cols|maxlength|disabled|readonly|tabindex|accesskey|onfocus|onblur|onchange|onselect|onclick|onkeyup|onkeydown|required|style|class|placeholder],option[name|id|value|selected|style|class],select[id|name|type|value|size|maxlength|checked|width|height|disabled|readonly|tabindex|accesskey|onfocus|onblur|onchange|onselect|onclick|multiple|style|class],th[*],thead[*],tfoot[*],iframe[*]",
		
		// Theme options
		toolbar : "formatselect styleselect bold italic | bullist numlist | undo redo | cut copy paste pastetext pasteword | searchreplace | removeformat cleanup help",
		
		// Example content CSS (should be your site CSS)
		content_css : "/theme/style.css",
		menubar: false,
		schema: "html5",
		block_formats: "Paragraph=p;Heading=h2;Subheading=h3;",
		style_formats: [
			{title: 'Highlight', inline: 'span', classes: 'highlight'}
		],
		
		valid_children: "+a[p|div|section|article|h1|h2|h3|h4|h5|h6]"
	});
}