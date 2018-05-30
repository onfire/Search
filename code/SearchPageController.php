<?php  

namespace Jaedb\Search;

use PageController;
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
	
	public static function set_types($types){
		self::$types = $types;
	}
	
	public static function get_mapped_types(){
		$types_available = self::get_types_available();
		$mapped_types = [];
		if ($types = self::get_types()){
			foreach (self::get_types() as $key){
				if (isset($types_available[$key])){
					$mapped_types[] = $types_available[$key];
				}
			}
		} else {
			$mapped_types = $types_available;
		}
		return $mapped_types;
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
						
					// Identify which table has the column which we're trying to filter by
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


					/**
					 * A specific table has been configured. We need to
					 * treat this as a relational filter (ie Page.Author; has_one)
					 **/
					if (isset($filter['Table'])){

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
						if (isset($type['JoinTables'])){
							foreach ($type['JoinTables'] as $join_table){
								//$joins.= "LEFT JOIN \"".$type['Table']."\" ON \"".$join_table."\".\"ID\" = \"".$type['Table']."\".\"ID\"";
							}
						}
						
						if (is_array($filter['Value'])){
							$valuesString = '';
							foreach ($filter['Value'] as $value){
								if ($valuesString != ''){
									$valuesString.= ',';
								}
								$valuesString.= "'".$value."'";
							}
						} else {
							$valuesString = $filter['Value'];
						}
						
						$where.= "\"".$table_with_column."\".\"".$filter['Column']."\" ".$filter['Operator']." '".$valuesString ."'";
					
						// close our wrapper
						$where.= ')';
					}
				}
			}
			
			
			// compile our sql string
			$sql.= $joins;
			$sql.= $where;

			//echo '<h3 style="position: relative; padding: 20px; background: #EEEEEE; z-index: 999;">'.$sql.'</h3>';

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