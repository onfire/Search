<?php

namespace Jaedb\Search;

use Page;

class SearchPage extends Page {
	private static $description = "Search engine and results page. You only need one of these page types.";

	/**
	 * We need to have a SearchPage to use it
	 */
	public function requireDefaultRecords() {
		parent::requireDefaultRecords();
		
		if (static::class == self::class && $this->config()->create_default_pages) {
			if (!SearchPage::get()){
				$page = SearchPage::create();
				$page->Title = 'Search';
				$page->Content = '';
				$page->write();
				$page->flushCache();
				DB::alteration_message('Search page created', 'created');
			}
		}
	}
}