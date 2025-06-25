<div class="account-page sws">
	<h2>$Title</h2>

	$Content
	
	<% if Orders %>
		<table class="table table-striped table-bordered">
			<thead>
				<tr>
					<th>#</th>
					<th><%t AccountPage.DATE "Date" %></th>
					<th><%t AccountPage.TOTAL "Total" %></th>
					<th><%t AccountPage.STATUS "Status" %></th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>
				<% loop Orders %>
				<tr>
					<td>$ID</td>
					<td>$OrderedOn.Format(d MMM y)</td>
					<td>$TotalPrice.Nice</td>
					<td>$Status ($PaymentStatus)</td>
					<td><a href="$Link"><%t AccountPage.VIEW_THIS_ORDER "View this order" %></a></td>
				</tr>
				<% end_loop %>
			</tbody>
		</table>
	<% else %> 
		<div class="alert alert-info">
			<%t AccountPage.NO_ORDERS "You do not currently have any orders. In future you will be able to view your recent orders from here." %>
		</div>
	<% end_if %>
</div>