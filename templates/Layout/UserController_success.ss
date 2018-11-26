<% if $Menu(2) || $SideBarView.Widgets %>
	<% include SideBar %>
<% end_if %>

<div class="col-sm content-container" role="main">
	<article>
		<div class="content">
            <h1><%t UserInvitation.ACCEPTED_HEADING 'Congratulations!' %></h1>
            <p><%t UserController.ACCEPTED_BODY 'You are now a registered member.' %><a href="{$LoginLink}">
                <%t UserInvitation.ACCEPTED_LOGIN_LINK_TEXT "Click here to login." %></a>
            </p>
        </div>
	</article>
</div>