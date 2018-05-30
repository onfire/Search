<?php  

namespace Jaedb\Search;

use PageController;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\ORM\DB;
use SilverStripe\Core\Config\Config;

class SearchPageController extends PageController {
	
	// these statics hold the search data
	private static $query;
	private static $filters;
	private static $types;
	private static $results;
	private static $results_url;
	
	// setup the actions to expose our engine
	private static $allowed_actions = array(
		'SearchForm'
	);
	
	// setup the search parameters
	private static $url_handlers = array(
		'SearchForm' => 'SearchForm'
	);
	
	public function index($request){
		
		// get the parameters and variables of this request (ie the query and filters)
		$vars = $request->requestVars();
		
		if (isset($vars['query']) && $vars['query'] != ''){
			self::set_query($vars['query']);
			unset($vars['query']);
		}
		
		if (isset($vars['types']) && $vars['types'] != ''){
			self::set_types(explode(',',$vars['types']));
			unset($vars['types']);
		}

		self::set_filters($vars);
		self::set_results($this->PerformSearch());
		
		return [];
	}
	
	
	/**
	 * Getters
	 **/

	public static function get_types_available(){
		$types = Config::inst()->get('Jaedb\Search\SearchPageController', 'types');
		$array = [];

		if ($types){
			foreach ($types as $key => $value){
				$value['Key'] = $key;
				$array[$key] = $value;
			}
		}

		return $array;
	}

	public static function get_filters_available(){
		$filters = Config::inst()->get('Jaedb\Search\SearchPageController', 'filters');
		$array = [];

		if ($filters){
			foreach ($filters as $key => $value){
				$value['Key'] = $key;
				$array[$key] = $value;
			}
		}

		return $array;
	}
	
	public static function get_types(){
		return self::$types;
	}
	
	public static function set_types( $types ){
		self::$types = $types;
	}
	
	public static function get_mapped_types(){
		$types_available = self::get_types_available();
		$types = [];
		foreach (self::get_types() as $key){
			if (isset($types_available[$key])){
				$types[] = $types_available[$key];
			}
		}
		return $types;
	}
	
	public static function get_filters(){
		return self::$filters;
	}
	
	public static function set_filters( $filters ){
		self::$filters = $filters;
	}
	
	public static function get_mapped_filters(){
		$filters_available = self::get_filters_available();
		$filters = [];

		foreach (self::get_filters() as $key => $value){
			if (isset($filters_available[$key])){
				$filter = $filters_available[$key];
				$filter['Value'] = $value;
				$filters[] = $filter;
			}
		}
		return $filters;
	}
	
	public static function get_query( $mysqlSafe = false ){
		$query = self::$query;
		if( $mysqlSafe ){
			$query = str_replace("'", "\'", $query);
			$query = str_replace('"', '\"', $query);
			$query = str_replace('`', '\`', $query);
		}
		return $query;
	}
	
	public static function set_query( $query = null ){
		self::$query = $query;
	}
	
	public static function get_results(){
		return self::$results;
	}
	
	public static function set_results( $results ){
		self::$results = $results;
	}
	
	/**
	 * Get the search query
	 * This is just an alias to get my static variable
	 * @return ArrayList
	 **/
	public function Query(){
		return self::get_query();
	}
	
	
	/**
	 * Get the results
	 * This is just an alias to get my static variable
	 * @return ArrayList
	 **/
	public function Results(){
		return self::get_results();
	}
	
	
	/**
	 * Get the types searched
	 * This is just an alias to get my static variable. We then construct them as ArrayList for template use
	 * @return ArrayList
	 **/
	public function Types(){
		$types = self::get_types();
		$types_available = self::get_types_available();
		if (!$types){
			return false;
		}

		$completeTypes = ArrayList::create();
		foreach ($types as $type){
			if (isset($types_available[$type])){
				$completeTypes->push($types_available[$type]);
			}
		}

		return $completeTypes;	
	}
	
	
	/**
	 * Get the results_url
	 * This is just an alias to get my static variable
	 * @return ArrayList
	 **/
	public function ResultsURL(){
		return self::get_results_url();
	}
	
	/**
	 * Placeholder function to facilitate manipulation of the search results url form without having to
	 * clone ResultsURL method. To use, just create a method updateResultsURL( $url ) and apply your edits
	 * @param $url = str
	 * @return str
	 **/
	public function updateResultsURL( $url ){
		return $url;
	}

	
	
	/**
	 * Build the search form
	 * Use this as the base platform when creating specific search forms
	 * @return obj
	 **/
	public function SearchForm(){
		
		// create our search form fields
        $fields = FieldList::create();
		
		// search keywords
		$fields->push( TextField::create('query','',self::get_query())->addExtraClass('query')->setAttribute('placeholder', 'Keywords') );
		
		// classes to search		
		if ($types_available = self::get_types_available()){
			$source = ['' => 'All types'];

			// Construct the array of options for the field
			foreach ($types_available as $key => $type){
				$source[$key] = $type['Label'];
			}

			$fields->push(CheckboxSetField::create('types', 'Types', $source, self::get_types()));
		}
		
		// Filters that we need to map
		if ($filters_available = self::get_filters_available()){

			// Grab our already-set filters
			$filters = self::get_filters();

			foreach ($filters_available as $key => $filter){

				// Identify any existing values (ie if we're on the results page with values already set)
				$value = null;
				if (isset($filters[$key])){
					$value = $filters[$key];
				}

				// Table is defined, so it's a relational-based filter
				if (isset($filter['Table'])){

					$source = $filter['ClassName']::get();

					// We need to apply a filter to the displayed relational options (based on config)
					if (isset($filter['Filters'])){
						$source = $source->filter($filter['Filters']);
					}

					$fields->push(DropdownField::create($key, $filter['Label'], $source->map('ID','Title','All'), $value)->setEmptyString('All '.$filter['Label'].'s'));

				// Non-relational; just a simple column on the subject's record
				} else {
					$fields->push(TextField::create($key, $filter['Label'], $value));
				}
			}
		}
		
		// use this page's link as the search results URL (customise this within your page's updateSearchForm() )
		$fields->push( HiddenField::create('ResultsURL', 'ResultsURL', $this->owner->Link()));
		
		// create the form actions (we only need a submit button)
        $actions = FieldList::create(
            FormAction::create("doSearchForm")->setTitle("Search")
        );
		
		// now build the actual form object
        $form = Form::create(
			$controller = $this,
			$name = 'SearchForm', 
			$fields = $fields,
			$actions = $actions
		);
		
        $form = $this->owner->updateSearchForm( $form );
		
        return $form;
	}
	
	
	/**
	 * Placeholder function to facilitate manipulation of the search form without having to
	 * clone SearchForm method. To use, just create a method updateSearchForm( $form ) and apply your edits
	 * @param $form = obj
	 * @return obj
	 **/
	public function updateSearchForm( $form ){
		return $form;
	}
	
	
	
	/**
	 * Process the submitted search form. All we're really doing is redirecting to our structured URL
	 * @param $data = array (post data)
	 * @param $form = obj (the originating SearchForm object)
	 * @return null
	 **/
	public function doSearchForm($data, $form){

		$filters_available = self::get_filters_available();

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

		return $this->redirect($data['ResultsURL'].$vars);		
	}
	
	
	/**
	 * Placeholder function to facilitate manipulation of the search form DOER. To use, just create a method
	 * updateDoSearchForm( $url ) and apply your edits
	 * @param $data = array (the original form $_POST data)
	 * @param $searchParameters = array
	 * @return string
	 **/
	public function updateDoSearchForm( $data, $searchParameters ){
		return $searchParameters;
	}
	
	
	/**
	 * Have a squiz through our site and find all matches
	 * @return PaginatedList
	 **/
	public function PerformSearch(){
		
		// get all our search requirements
		$query = self::get_query($mysqlSafe = true);
		$types = self::get_mapped_types();
		$filters = self::get_mapped_filters();
		
		// prepare our final result object
		$allResults = ArrayList::create();
		
		// loop all the records we need to lookup
		foreach ($types as $type){
			
			$sql = '';
			$joins = '';
			$where = '';
			$sort = '';
			
			/**
			 * Result selection
			 * We only need ClassName and ID to fetch the full object (using the SilverStripe ORM)
			 * once we've got our results
			 **/
			$sql.= "SELECT \"".$type['Table']."\".\"ID\" AS \"ResultObject_ID\" FROM \"".$type['Table']."\" ";
			
			// Join this type with any dependent tables (if applicable)
			if (isset($type['JoinTables'])){
				foreach ($type['JoinTables'] as $joinTable){
					$joins.= "LEFT JOIN \"".$joinTable."\" ON \"".$joinTable."\".\"ID\" = \"".$type['Table']."\".\"ID\" ";		
				}
			}
			
			/**
			 * Query term
			 * We search each column for this type for the provided query string
			 */
			$where .= ' WHERE (';
			foreach ($type['Columns'] as $i => $column){
				$column = explode('.',$column);
				if ($i > 0){
					$where .= ' OR ';
				}
				$where .= "\"".$column[0]."\".\"".$column[1]."\" LIKE CONCAT('%','".$query."','%')";
			}
			$where.= ')';
			
			
			/**
			 * Apply our type-level filters (if applicable)
			 **/		
			if (isset($type['Filters'])){
				foreach ($type['Filters'] as $key => $value){
					$where.= ' AND ('.$key.' = '.$value.')';
				}
			}
			
			
			/**
			 * Apply filtering
			 **/
			if ($filters){
				foreach ($filters as $filter){

					/**
					 * A specific table has been configured. We need to
					 * treat this as a relational filter (ie Page.Author; has_one)
					 **/
					if (isset($filter['Table'])){
						
						// Identify which table has the column which we need to filter by
						$table_with_column = null;
						if (isset($type['JoinTables'])){
							$tables_to_check = $type['JoinTables'];
						} else {
							$tables_to_check = [];
						}
						$tables_to_check[] = $type['Table'];

						foreach ($tables_to_check as $table_to_check){
							$column_exists_query = DB::query( "SHOW COLUMNS FROM \"".$table_to_check."\" LIKE '".$filter['Column']."'" );				

							foreach ($column_exists_query as $column){
								$table_with_column = $table_to_check;
							}
						}

						// Not anywhere in this type's table joins, so we can't search this particular type
						if (!$table_with_column){
							continue 2;
						}

						// join the relationship table to our record(s)
						$joins.= "LEFT JOIN \"".$filter['Table']."\" ON \"".$filter['Table']."\".\"ID\" = \"".$table_with_column."\".\"".$filter['Column']."\"";
						
						if (is_array($filter['Value'])){
							$ids = '';
							foreach ($filter['Value'] as $id){
								if ($ids != ''){
									$ids.= ',';
								}
								$ids.= "'".$id."'";
							}
						} else {
							$ids = $filter['Value'];
						}
						$where.= ' AND ('."\"".$table_with_column."\".\"".$filter['Column']."\" IN (". $ids .")".')';

					/**
					 * Not relational, so just filter on the subject's table; a simple
					 * WHERE clause (ie Page.Title; db)
					 **/
					} else {
				
						// open our wrapper
						$where.= ' AND (';
						
						/**
						 * This particular type needs to join with other parent tables to
						 * form a complete, and searchable row
						 **/
						if (isset($filter['JoinTables'])){
							// TODO
						}
						
						if (is_array($filter['Value'])){
							$valuesString = '';
							foreach ($filter['Value'] as $value){
								if ($valuesString != ''){
									$valuesString.= ',';
								}
								$valuesString.= "'".$value."'";
							}
						}else{
							$valuesString = $filter['Value'];
						}
						
						$where.= "\"".$type['Table']."\".\"".$filter['Column']."\" ".$filter['Operator']." ". $valuesString ."";
					
						// close our wrapper
						$where.= ')';
					}
				}
			}
			
			
			// compile our sql string
			$sql.= $joins;
			$sql.= $where;

			echo '<h3 style="position: relative; padding: 20px; background: #EEEEEE; z-index: 999;">'.$sql.'</h3>';

			// executioners enter stage left
			$results = DB::query( $sql );
			$resultIDs = array();

			// add all the result ids to our array
			foreach( $results as $result ){
				$resultIDs[ $result['ResultObject_ID'] ] = $result['ResultObject_ID'];
			}
			
			// convert our sql result into SilverStripe objects, of the appropriate class
			if ($resultIDs){
				$resultObjects = $type['ClassName']::get()->filter('ID', $resultIDs );
				$allResults->merge($resultObjects);
			}
		}
		
		// sort our results
		$allResults->removeDuplicates('ID');
		$allResults = $allResults->Sort('PublishDate DESC');
		
		// load into a paginated list. To change the items per page, set via the template (ie Results.setPageLength(20))
		$paginatedItems = PaginatedList::create($allResults, $this->request);
		
		return $paginatedItems;
	}
}