	<table class="table table-bordered">
		<thead>
			<tr>
				<th><%t Order.PRODUCT "Product" %></th>
				<th><%t Order.PRICE "Price" %> ($TotalPrice.Currency)</th>
				<th><%t Order.QUANTITY "Quantity" %></th>
				<th class="totals-column"><%t Order.TOTAL "Total" %> ($TotalPrice.Currency)</th>
			</tr>
		</thead>
		<tbody>
			<% loop Items %>
			
				<tr  class="itemRow $EvenOdd $FirstLast">

					<td>
						<% with Product %>  
							<% if Link %>
								<a href="$Link" target="_blank">$Title</a>
							<% else %>
								$Title
							<% end_if %>
						<% end_with %>

						<br />
						$SummaryOfOptions
					</td>

					<td>
						$UnitPrice.Nice
					</td>
				
					<td>
						$Quantity
					</td>
	
					<td class="totals-column">$TotalPrice.Nice</td>
				
				</tr>
			<% end_loop %>
			
			<% if SubTotalModifications %>
				<% loop SubTotalModifications %>
					<tr>
						<td class="row-header mod-title">$Description</td>
						<td class="totals-column" colspan="3">$Price.Nice</td>
					</tr>
				<% end_loop %>
			<% end_if %>
			
			<tr>
				<td class="row-header"><%t Order.SUB_TOTAL "Sub Total" %></td>
				<td class="totals-column" colspan="3">$SubTotalPrice.Nice</td>
			</tr>
			
			<% if TotalModifications %>
				<% loop TotalModifications %>
					<tr>
						<td class="row-header mod-title">$Description</td>
						<td class="totals-column" colspan="3">$Price.Nice</td>
					</tr>
				<% end_loop %>
			<% end_if %>
	
			<tr>
				<td class="row-header"><%t Order.TOTAL "Total" %></td>
				<td class="totals-column" colspan="3">$TotalPrice.Nice</td>
			</tr>
		</tbody>
	</table>
