
<div class="search-results-page">

	$SearchForm

	<% include SearchSummary %>

	<% if Results %>

		<div class="search-results">
		    <% loop Results %>
				<% include SearchResult %>
		    <% end_loop %>
		</div>

	    <% with Results %>
	        <% include Pagination %>
	    <% end_with %>

	<% end_if %>

</div>
