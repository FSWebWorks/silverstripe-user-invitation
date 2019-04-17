<% if $Menu(2) || $SideBarView.Widgets %>
	<% include SideBar %>
<% end_if %>
<div class="col-sm content-container" role="main">
	<article>
		<div class="content">
            <h1><%t UserInvation.INVITEFORM_HEADING "User Invitation" %></h1>
            <p><%t UserInvation.INVITEFORM_BODY "Enter the details of the person you would like to invite below." %></p>
            $InvitationForm
        </div>
	</article>
</div>