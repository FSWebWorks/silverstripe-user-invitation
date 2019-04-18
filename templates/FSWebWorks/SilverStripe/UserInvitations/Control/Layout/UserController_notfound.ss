<% if $Menu(2) || $SideBarView.Widgets %>
	<% include SideBar %>
<% end_if %>

<div class="col-sm content-container" role="main">
	<article>
		<div class="content">
            <h1><%t UserInvitation.NOTFOUND_HEADING 'Invitation not found' %></h1>
            <p><%t UserController.NOTFOUND_BODY "Oops, the invitation ID was not found." %></p>
        </div>
	</article>
</div>