
	<% if IncludeFormTag %>
	<form $FormAttributes data-layout-type="border">
	<% end_if %>
	<div class="fill-height">

		<div class="cms-content-header toolbar--north">
			
			<div class="cms-content-header-info flexbox-area-grow">
				<% with Controller %>
					<!-- 	<% include SilverStripe\\Admin\\BackLink_Button %> -->
					<% include SilverStripe\\Admin\\CMSBreadcrumbs %>
				<% end_with %>
			</div>
			

			<div class="cms-content-header-tabs cms-tabset-nav-primary ss-ui-tabs-nav">

				<% if Fields.hasTabset %>
					<% with Fields.fieldByName('Root') %>
						<ul>
						<% loop Tabs %>
							<li <% if extraClass %>class="$extraClass"<% end_if %>>
								<a href="#$id" class="cms-panel-link" title="Form_EditForm">$Title</a>
							</li>
						<% end_loop %>
						</ul>
					<% end_with %>
				<% end_if %>

			</div>

		</div>

		<div class="flexbox-area-grow" data-layout-type="border">

			<% with Controller %>
				$Tools
				$EditFormTools
			<% end_with %>
			

			<div class="cms-content-fields center ui-widget-content flexbox-area-grow panel panel--padded panel--scrollable">
				<% if Message %>
				<p id="{$FormName}_error" class="message $MessageType">$Message</p>
				<% else %>
				<p id="{$FormName}_error" class="message $MessageType" style="display: none"></p>
				<% end_if %>

				<fieldset>
					<% if Legend %><legend>$Legend</legend><% end_if %> 
					<% loop Fields %>
						$FieldHolder
					<% end_loop %>
					<div class="clear"><!-- --></div>
				</fieldset>
			</div>



		</div>

		<div class="cms-content-actions toolbar--south">
			<% if Actions %>
			<div class="Actions">
				<% loop Actions %>
					$Field
				<% end_loop %>
				<% if Controller.LinkPreview %>
				<a href="$Controller.LinkPreview" class="cms-preview-toggle-link ss-ui-button" data-icon="preview">
					<% _t('LeftAndMain.PreviewButton', 'Preview') %> &raquo;
				</a>
				<% end_if %>
			</div>
			<% end_if %>
		</div>
	</div>

	<% if IncludeFormTag %>
	</form>
	<% end_if %>
