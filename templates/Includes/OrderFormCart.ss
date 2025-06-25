<% with Cart %>
<table id="checkout-order-table" class="table table-bordered">
	<thead>
		<tr>
			<th><%t CheckoutFormOrder.PRODUCT "Product" %></th>
			<th><%t CheckoutFormOrder.PRICE "Price" %> ($TotalPrice.Currency)</th>
			<th><%t CheckoutFormOrder.QUANTITY "Quantity" %></th>
			<th class="totals-column"><%t CheckoutFormOrder.TOTAL "Total" %> ($TotalPrice.Currency)</th>
		</tr>
	</thead>
	<tbody>
	
		<% if Items %>
		
			<% loop Top.ItemsFields %>
				$FieldHolder
			<% end_loop %>
			
		<% else %>
			<tr>
				<td colspan="4">
					<div class="error"><%t CheckoutFormOrder.NO_ITEMS_IN_CART "There are no items in your cart." %></div>
				</td>
			</tr>
		<% end_if %>

		<% loop Top.SubTotalModificationsFields %>
			$FieldHolder
		<% end_loop %>
		
		<tr>
			<td class="row-header"><%t CheckoutFormOrder.SUB_TOTAL "Sub Total" %></td>
			<td class="totals-column" colspan="3">$SubTotalPrice.Nice</td>
		</tr>
		
		<% loop Top.TotalModificationsFields %>
			$FieldHolder
		<% end_loop %>

		<tr>
			<td class="row-header"><%t CheckoutFormOrder.TOTAL "Total" %></td>
			<td class="totals-column" colspan="3">$TotalPrice.Nice</td>
		</tr>

	</tbody>
</table>
<% end_with %>