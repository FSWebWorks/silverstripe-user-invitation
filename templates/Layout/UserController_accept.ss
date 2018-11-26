<% if $Menu(2) || $SideBarView.Widgets %>
	<% include SideBar %>
<% end_if %>

<div class="col-sm content-container" role="main">
	<article>
		<div class="content">
            <h1><%t UserInvitation.ACCEPTED_SIGN_UP 'Sign Up' %></h1>
            <p><%t UserInvitation.ACCEPTED_BODY 'Complete the form below to confirm your registration for {name}' name=$Invite.Email%></p>
            $AcceptForm
        </div>
	</article>
</div>