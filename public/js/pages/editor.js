//[editor Javascript]

//Project:	Doclinic - Responsive Admin Template
//Primary use:   Used only for the wysihtml5 Editor 


//Add text editor
    $(function () {
    "use strict";

    // Replace the <textarea id="editor1"> with a CKEditor
	// instance, using default configuration.
	CKEDITOR.replace('operatorio')
	//bootstrap WYSIHTML5 - text editor
	$('.textarea').wysihtml5();		
	
  });

