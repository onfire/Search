
require('../scss/index.scss');
require('chosen-js');
require('../node_modules/chosen-js/chosen.min.css');

$(document).ready( function(){
	if ($('.search-form').length > 0){
		$('.search-form .chosen-select select').chosen({
			width: "100%"
		});
	}
});

// Start your website!
console.log('Loaded search');