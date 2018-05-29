
$SearchForm

<% if Query %>

	<div class="search-summary">
	    <p>You searched <% if Types %><% loop Types %><% if not First %><% if Last %> and <% else %>, <% end_if %><% end_if %><em>$Name</em><% end_loop %><% else %>everything<% end_if %> for <em>"$Query"</em> and got $Results.Count result<% if not Results %>s<% else_if Results.Count > 1 %>s<% end_if %></p>
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
		                    Last updated $LastEdited.Format(j F), $LastEdited.Format(Y)
		                </div>
		            </div>
		            <div class="preview">
		                <p>
		                	<% if Description %>$Description<% else_if Content %>$Content.ContextSummary(250, $Top.Query)<% end_if %>
		                </p>
		            </div>
				</article>    
			<% end_cached %>
	    <% end_loop %>

	    <% with Results %>
	        <% include Pagination %>
	    <% end_with %>

	<% end_if %>

<% end_if %>
