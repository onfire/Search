
The built-in SilverStripe search form is a very simple search engine. This plugin takes SQL-based searching to the next level, without requiring the implementation of a full-blown search engine like Solr or Elastic Search. It is designed to bring data-oriented filters on top of the simple text search functionality.


# Requirements

* SilverStripe 4


# Usage

* Create a `SearchPage` instance (typically at the root of your website). This page only is used to display results, so please refrain from creating multiple instances.
* Configure your website's `_config/config.yml` to define search parameters.
* Run `dev/build` to instansiate your new configuration


# Example configuration

```
Jaedb\Search\SearchPageController:
  types:
    docs:
      Label: 'Documents'						# For display in the search form
      Table: 'File_Live'						# The object's primary table (note _Live for versioned objects)
      ClassName: 'SilverStripe\Assets\File'		# As per the table's ClassName column
      ClassNameShort: 'File'					# Namespaced classname; used when joining relationships
      Filters:									# List of any filters you want to apply pre-search (maps to DataList->Filter(key => value))
        File_Live.ShowInSearch: '1'
      Columns: ['File_Live.Title','File_Live.Description','File_Live.Name']		# Columns to search for query string matches
    pages:
      Label: 'Pages'
      ClassName: 'Page'
      ClassNameShort: 'Page'
      Table: 'Page_Live'
      Filters: 
        SiteTree_Live.ShowInSearch: '1'
      JoinTables: ['SiteTree_Live']
      Columns: ['SiteTree_Live.Title','SiteTree_Live.MetaDescription','SiteTree_Live.MenuTitle','SiteTree_Live.Content']
  filters:
    updated_before:								# Unique label for filter
      Structure: 'db'							# Type of filter structure (db, has_one or many_many)
      Label: 'Updated before'					# For display in the search form
      Column: 'LastEdited'						# Column in each type's table
      Operator: '<'								# Filter operator
    updated_after:
      Structure: 'db'
      Label: 'Updated after'
      Column: 'LastEdited'
      Operator: '>'
    tags:
      Structure: 'many_many'
      Label: 'Tags'
      ClassName: 'Tag'
      Table: 'Tag'								# Table containing related records
      JoinTables:								# List of relationship mappings for each type
        docs: 
          Table: 'File_Tags'					# Join table
          Column: 'FileID'						# Join column
        pages: 
          Table: 'Page_Tags'
          Column: 'PageID'
```