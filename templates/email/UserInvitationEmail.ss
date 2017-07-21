<h3><%t UserInvitationEmail.HEADING "Hi {name}" name=$Invite.FirstName %></h3>
<p><%t UserInvitationEmail.BODY "You have been invited to join {site} by {inviterName} " site=$SiteURL inviterName=$Invite.InvitedBy.FirstName %></p>
<p><a href="{$SiteURL}user/accept/$Invite.TempHash"><%t UserInvitationEmail.ACCEPT "Click here to accept this invitation." %></a>|</p>


