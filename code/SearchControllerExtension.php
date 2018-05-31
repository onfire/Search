<?php

namespace Jaedb\Search;

use Exception;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\View\Requirements;

class SearchControllerExtension extends DataExtension {
	
	private static $allowed_actions = array(
		'SearchForm',
		'MiniSearchForm'
	);

	public static function init(){
		parent::init();
		Requirements::css('/resources/jaedb/search/css/Search.css');
	}
	

	/**
	 * Mini search form (ie in menus and footers)
	 *
	 * @return Form
	 **/
	public function MiniSearchForm(){
		
		// create our search form fields
        $fields = FieldList::create();
		
		// search keywords
		$fields->push( TextField::create('query','',SearchPageController::get_query())->addExtraClass('query')->setAttribute('placeholder', 'Keywords') );
		
		// create the form actions (we only need a submit button)
        $actions = FieldList::create(
            FormAction::create("doSearchForm")->setTitle("Search")
        );
		
		// now build the actual form object
        $form = Form::create(
			$controller = $this->owner,
			$name = 'MiniSearchForm', 
			$fields = $fields,
			$actions = $actions
		)->addExtraClass('search-form search-mini-form');
		
        return $form;
	}
	

	/**
	 * Build the search form
	 *
	 * @return Form
	 **/
	public function SearchForm(){
		
		// create our search form fields
        $fields = FieldList::create();
		
		// search keywords
		$fields->push( TextField::create('query','',SearchPageController::get_query())->addExtraClass('query')->setAttribute('placeholder', 'Keywords') );
		
		// classes to search		
		if ($types_available = SearchPageController::get_types_available()){
			$source = ['' => 'All types'];

			// Construct the array of options for the field
			foreach ($types_available as $key => $type){
				$source[$key] = $type['Label'];
			}

			$fields->push(CheckboxSetField::create('types', 'Types', $source, SearchPageController::get_types()));
		}
		
		// Filters that we need to map
		if ($filters_available = SearchPageController::get_filters_available()){

			// Grab our already-set filters
			$filters = SearchPageController::get_filters();

			foreach ($filters_available as $key => $filter){

				// Identify any existing values (ie if we're on the results page with values already set)
				$value = null;
				if (isset($filters[$key])){
					$value = $filters[$key];
				}

				switch ($filter['Structure']){
					
					/**
					 * Plain column value field
					 **/
					case 'db':
						$fields->push(TextField::create($key, $filter['Label'], $value));
						break;

					/**
					 * Simple relation field
					 **/
					case 'has_one':
						$source = $filter['ClassName']::get();

						// We need to apply a filter to the displayed relational options (based on config)
						if (isset($filter['Filters'])){
							$source = $source->filter($filter['Filters']);
						}

						$empty_string = 'All '.$filter['Label'];
						if (substr($empty_string, -1) != 's'){
							$empty_string.= 's';
						}

						$fields->push(DropdownField::create($key, $filter['Label'], $source->map('ID','Title','All'), $value)->setEmptyString($empty_string));
						break;

					/**
					 * Complex relational field
					 **/
					case 'many_many':
						$source = $filter['ClassName']::get();

						// We need to apply a filter to the displayed relational options (based on config)
						if (isset($filter['Filters'])){
							$source = $source->filter($filter['Filters']);
						}

						$fields->push(CheckboxSetField::create($key, $filter['Label'], $source->map('ID','Title','All'), explode(',',$value)));

						break;
				}
			}
		}
		
		// create the form actions (we only need a submit button)
        $actions = FieldList::create(
            FormAction::create("doSearchForm")->setTitle("Search")
        );
		
		// now build the actual form object
        $form = Form::create(
			$controller = $this->owner,
			$name = 'SearchForm', 
			$fields = $fields,
			$actions = $actions
		)->addExtraClass('search-form');
		
        return $form;
	}
	
	
	
	/**
	 * Process the submitted search form. All we're really doing is redirecting to our structured URL
	 * @param $data = array (post data)
	 * @param $form = obj (the originating SearchForm object)
	 * @return HTTPRedirect
	 **/
	public function doSearchForm($data, $form){

		$page = SearchPage::get()->first();
		if (!$page){
			throw new Exception("The required SearchPage record does not exist");
			die();
		}

		$filters_available = SearchPageController::get_filters_available();

		$vars = '';
		foreach ($data as $key => $value){

			// Make sure we only carry configured filters
			// This begins to protect us against malicious use :-)
			if ((isset($filters_available[$key]) || $key == 'query' || $key == 'types') && $value && $value !== ''){

				// Concat into a URL string
				if ($vars == ''){
					$vars .= '?'.$key.'=';
				} else {
					$vars .= '&'.$key.'=';
				}

				// And merge any arrays into comma-separated values
				if (is_array($value)){
					$vars .= join(',',$value);
				} else {
					$vars .= $value;
				}
			}
		}

		return $this->owner->redirect($page->Link().$vars);		
	}
}