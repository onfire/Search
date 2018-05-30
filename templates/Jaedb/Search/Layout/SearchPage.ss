
$SearchForm

<div class="search-summary">
    <p>Searched <% if Types %><% loop Types %><% if not First %><% if Last %> and <% else %>, <% end_if %><% end_if %><em>$Label</em><% end_loop %><% else %>everything<% end_if %><% if Query %> for <em>"$Query"</em><% end_if %> and got $Results.Count result<% if not Results %>s<% else_if Results.Count > 1 %>s<% end_if %></p>
</div>

<% if Results %>

    <% loop Results %>
		<% cached $ClassName, $ID, $LastEdited %>
			<article class="search-result">
	            <h2 class="title">
	            	<a href="$Link">$Title</a>
	            </h2>    
	            <div class="details">	                
	                <div class="edited">
	                    Last updated $LastEdited.Format(MMMM d), $LastEdited.Format(y)
	                </div>
	            </div>
	            <div class="preview">
	                <p>
	                	<% if Description %>
	                		$Description
	                	<% else_if Content %>
	                		$Content.LimitCharactersToClosestWord(40)
	                	<% end_if %>
	                </p>
	            </div>
			</article>    
		<% end_cached %>
    <% end_loop %>

    <% with Results %>
        <% include Pagination %>
    <% end_with %>

<% end_if %>
