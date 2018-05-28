<?php

Jaedb\Search\SearchPageController::set_types_available([
	'Documents' => array(
		'ClassName' => 'SilverStripe\Assets\File',
		'Table' => 'File_Live',
/*		'ExtraTables' => array('File_Live'),*/
/*		'ExtraWhere' => 'ShowInSearch = 1',*/
		'Columns' => array('Title','Description','Name')
	),
	'Pages' => array(
		'ClassName' => 'Page',
		'Table' => 'Page_Live',
		'ExtraWhere' => 'ShowInSearch = 1',
		'Columns' => array('Title','MetaDescription','MenuTitle','Content')
	)
]);