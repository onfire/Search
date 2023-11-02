<?php  

namespace Jaedb\Search;

use PageController;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\ORM\DB;
use SilverStripe\Core\Config\Config;
use SilverStripe\View\Requirements;
use SilverStripe\Control\Director;

class SearchPageController extends PageController {
	
	// these statics hold the search data
	private static $query;
	private static $types;
	private static $filters;
	private static $sort;
	private static $results;
	
	public function index($request){
		
		if (Director::isLive()){
			Requirements::css('/resources/jaedb/search/client/Search.min.css');
			Requirements::javascript('/resources/jaedb/search/client/Search.min.js');
		} else {
			Requirements::css('/resources/jaedb/search/client/Search.css');
			Requirements::javascript('/resources/jaedb/search/client/Search.js');
		}
		
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
		
		if (isset($vars['sort']) && $vars['sort'] != ''){
			self::set_sort($vars['sort']);
			unset($vars['sort']);
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

	public static function get_sorts_available(){
		$sorts = Config::inst()->get('Jaedb\Search\SearchPageController', 'sorts');
		$array = [];

		if ($sorts){
			foreach ($sorts as $key => $value){
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
	
	public static function set_filters($filters){
		self::$filters = $filters;
	}
	
	public static function get_mapped_filters(){
		$filters_available = self::get_filters_available();
		$mapped_filters = [];

		foreach (self::get_filters() as $key => $value){
			if (isset($filters_available[$key])){
				$filter = $filters_available[$key];
				$filter['Value'] = $value;
				$mapped_filters[] = $filter;
			}
		}
		return $mapped_filters;
	}
	
	public static function get_query($mysqlSafe = false){
		$query = self::$query;
		if( $mysqlSafe ){
			$query = str_replace("'", "\'", $query);
			$query = str_replace('"', '\"', $query);
			$query = str_replace('`', '\`', $query);
		}
		return $query;
	}
	
	public static function set_query($query = null){
		self::$query = $query;
	}
	
	public static function get_sort(){
		return self::$sort;
	}
	
	public static function get_mapped_sort(){
		$sorts_available = self::get_sorts_available();
		$sort = self::get_sort();

		// If no sort, assume the first item
		if (!$sort){
			return reset($sorts_available);
		} else {
			return $sorts_available[$sort];
		}
	}
	
	public static function set_sort($sort){
		self::$sort = $sort;
	}
	
	public static function get_results(){
		return self::$results;
	}
	
	public static function set_results($results){
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
	 * Get the search query
	 * This is just an alias to get my static variable
	 * @return ArrayList
	 **/
	public function Sort(){
		return self::get_sort();
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
    public function PerformSearch() {
        // Get all our search requirements
        $query = self::get_query($mysqlSafe = true);
        $types = self::get_mapped_types();
        $filters = self::get_mapped_filters();

        // Prepare our final result object
        $allResults = ArrayList::create();

        // Loop through all the records we need to look up
        foreach ($types as $type) {
            $sql = '';
            $joins = '';
            $where = '';

            // Result selection: We only need ClassName and ID to fetch the full object (using SilverStripe ORM) once we've got our results
            $sql .= "SELECT \"" . $type['Table'] . "\".\"ID\" AS \"ResultObject_ID\" FROM \"" . $type['Table'] . "\" ";

            // Join this type with any dependent tables (if applicable)
            if (isset($type['JoinTables'])) {
                foreach ($type['JoinTables'] as $joinTable) {
                    $joins .= "LEFT JOIN \"" . $joinTable . "\" ON \"" . $joinTable . "\".\"ID\" = \"" . $type['Table'] . "\".\"ID\" ";
                }
            }

            // Query term: We search each column for this type for the provided query string
            $where .= ' WHERE (';
            foreach ($type['Columns'] as $i => $column) {
                $column = explode('.', $column);
                if ($i > 0) {
                    $where .= ' OR ';
                }
                $where .= "\"$column[0]\".\"$column[1]\" LIKE CONCAT('%', ?, '%')"; // Use a parameterized query
            }
            $where .= ')';

            // Apply our type-level filters (if applicable)
            if (isset($type['Filters'])) {
                foreach ($type['Filters'] as $key => $value) {
                    $where .= ' AND (' . $key . ' = ?)'; // Use a parameterized query
                }
            }

            // Apply filtering
            $relations_sql = '';

            if ($filters) {
                foreach ($filters as $filter) {
                    // Apply filters based on filter structure
                    switch ($filter['Structure']) {
                        case 'db':
                            $table_with_column = null;
                            $tables_to_check = isset($type['JoinTables']) ? $type['JoinTables'] : [];
                            $tables_to_check[] = $type['Table'];
                            foreach ($tables_to_check as $table_to_check) {
                                $column_exists_query = DB::query("SHOW COLUMNS FROM \"$table_to_check\" LIKE '{$filter['Column']}'");
                                foreach ($column_exists_query as $column) {
                                    $table_with_column = $table_to_check;
                                }
                            }
                            if (!$table_with_column) {
                                continue 2;
                            }
                            $where .= ' AND (';
                            $valuesString = is_array($filter['Value']) ? "'" . implode("','", $filter['Value']) . "'" : $filter['Value'];
                            $where .= "\"$table_with_column\".\"{$filter['Column']}\" {$filter['Operator']} ?"; // Use a parameterized query
                            $where .= ')';
                            break;
                        case 'has_one':
                            $table_with_column = null;
                            $tables_to_check = isset($type['JoinTables']) ? $type['JoinTables'] : [];
                            $tables_to_check[] = $type['Table'];
                            foreach ($tables_to_check as $table_to_check) {
                                $column_exists_query = DB::query("SHOW COLUMNS FROM \"$table_to_check\" LIKE '{$filter['Column']}'");
                                foreach ($column_exists_query as $column) {
                                    $table_with_column = $table_to_check;
                                }
                            }
                            if (!$table_with_column) {
                                continue 2;
                            }
                            $joins .= "LEFT JOIN \"{$filter['Table']}\" ON \"{$filter['Table']}\".\"ID\" = \"{$table_with_column}.\"{$filter['Column']}\"";
                            $ids = is_array($filter['Value']) ? "'" . implode("','", $filter['Value']) . "'" : $filter['Value'];
                            $where .= " AND \"{$table_with_column}.\"{$filter['Column']}\" IN (?)"; // Use a parameterized query
                            break;
                        case 'many_many':
                            if (isset($filter['JoinTables'][$type['Key']])) {
                                $filter_join = $filter['JoinTables'][$type['Key']];
                                $joins .= "LEFT JOIN \"{$filter_join['Table']}\" ON \"{$type['Table']}\".\"ID\" = \"{$filter_join['Column']}\"";
                                $ids = is_array($filter['Value']) ? "'" . implode("','", $filter['Value']) . "'" : $filter['Value'];
                                $relations_sql .= "\"{$filter_join['Table']}\".\"{$filter['Table']}ID\" IN (?)"; // Use a parameterized query
                            }
                            break;
                    }
                }
                // Append any required relations SQL
                if ($relations_sql !== '') {
                    $where .= ' AND (' . $relations_sql . ')';
                }
            }

            // Compile our SQL string
            $sql .= $joins . $where;

            // Prepare the statement with parameters
            $stmt = DB::prepare($sql);

            // Bind parameters to the statement
            $params = []; // Collect all parameters for binding
            $params[] = $query;
            if (isset($type['Filters'])) {
                foreach ($type['Filters'] as $value) {
                    $params[] = $value;
                }
            }
            if ($filters) {
                foreach ($filter['Value'] as $value) {
                    $params[] = $value;
                }
            }
            $stmt->execute($params);

            // Fetch the results
            $results = $stmt->fetchAll();

            $resultIDs = [];

            // Add all the result IDs to our array
            foreach ($results as $result) {
                if (!isset($resultIDs[$result['ResultObject_ID']])) {
                    $resultIDs[$result['ResultObject_ID']] = $result['ResultObject_ID'];
                }
            }

            // Convert our SQL results into SilverStripe objects of the appropriate class
            if ($resultIDs) {
                $resultObjects = $type['ClassName']::get()->filter(['ID' => $resultIDs]);
                $allResults->merge($resultObjects);
            }
        }

        // Apply sorting
        $sort = self::get_mapped_sort()['Sort'];
        $sort = str_replace("'", "\'", $sort);
        $sort = str_replace('"', '\"', $sort);
        $sort = str_replace('`', '\`', $sort);
        $allResults = $allResults->Sort($sort);

        return PaginatedList::create($allResults, $this->request);
    }


}