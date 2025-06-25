<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" >
		$InlineCSS
	</head>
	<body>
	
		<h3><%t Order_ReceiptEmail.GREETING "Hi" %> $Customer.Name,</h3>
		<% if IsStandingOrder %>
			<p>We've received your order. You will be billed and sent your items automatically at the selected frequency.<p>
			<p>You can view and make changes to your order at any time <a href="$Link" id="OrderLink">here</a>.</p>
			<p>Your order details are below.</p>
		<% else %>
			$Message
		<% end_if %>
	
		<% with Order %>
			<div class="order sws">
				<table class="table table-bordered">
					<tr>
						<th>
							<%t Order_ReceiptEmail.ORDER "Order" %> #$ID - $Status<br />
							<a href="$Link" id="OrderLink"><%t Order_ReceiptEmail.VIEW_ORDER "View this order" %></a> 
						</th>
					</tr>
					<tr>
						<td>
							$OrderedOn.Format(d MMM y - h:mm a)<br />
							($PaymentStatus)
						</td>
					</tr>
				</table>

				<% include Order %>
				 
				<% if Payments %>
					<% include OrderPayments %>
				<% end_if %>
			 
				<% if CustomerUpdates || Notes %>
					<% include OrderNotes %>
				<% end_if %>
			</div>
		<% end_with %>
		
		<p>
			<%t Order_ReceiptEmail.PAYMENTNOTICE "Please note that orders will not be shipped until payment has been successfully processed." %>
		</p>
		
		$Signature
	</body>
</html>
