<% if $Menu(2) || $SideBarView.Widgets %>
	<% include SideBar %>
<% end_if %>

<div class="col-sm content-container" role="main">
	<article>
		<div class="content">
            <h1><%t UserInvitation.EXPIRED_HEADING 'Invitation expired' %></h1>
            <p><%t UserController.EXPIRED_BODY "Oops, you took too long to accept this invitation." %></p>
        </div>
	</article>

	$Form
	$PageComments
</div>