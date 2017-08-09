<?php


class UserControllerTest extends FunctionalTest
{

    public static $fixture_file = 'UserControllerTest.yml';

    /**
     * @var UserController
     */
    private $controller;

    public function setUp()
    {
        parent::setUp();
        $this->autoFollowRedirection = false;
        $this->logInWithPermission('ADMIN');
        $this->controller = new UserController();
    }

    private function logoutMember()
    {
        if ($member = Member::currentUser()) {
            $member->logOut();
        }
    }

    /**
     * Tests redirected to a login screen if you're logged out.
     */
    public function testCantAccessWhenLoggedOut()
    {
        $this->logoutMember();
        $response = $this->get($this->controller->Link('index'));
        $this->assertFalse($response->isError());
        $this->assertEquals(302, $response->getStatusCode());
        $this->autoFollowRedirection = true;
    }

    /**
     * Tests if a form is returned and that expected fields are present
     */
    public function testInvitationForm()
    {
        $form = $this->controller->InvitationForm();
        $this->assertInstanceOf('Form', $form);
        $this->assertNotNull($form->Fields()->fieldByName('FirstName'));
        $this->assertNotNull($form->Fields()->fieldByName('Email'));
        $this->assertNotNull($form->Fields()->fieldByName('Groups'));
    }

    /**
     * Tests whether an email is sent and that an invitation record is created
     */
    public function testSendInvite()
    {
        $this->loginAsJane();
        /** @var Form $form */
        $data = array(
            'FirstName' => 'Joe',
            'Email' => 'joe@example.com',
            'Groups' => array('test1', 'test2')
        );
        $response = $this->controller->sendInvite($data, $this->controller->InvitationForm()->loadDataFrom($data));
        $invitation = UserInvitation::get()->filter('Email', $data['Email']);
        $this->assertCount(1, $invitation);
        /** @var UserInvitation $invitation */
        $joe = $invitation->first();
        $this->assertEquals('Joe', $joe->FirstName);
        $this->assertEquals('joe@example.com', $joe->Email);
        $this->assertEquals('test1,test2', $joe->Groups);
        $this->assertEquals(302, $response->getStatusCode());
    }

    /**
     * Tests whether switching the UserInvitation::force_require_group has an effect or not
     */
    public function testGroupFieldNotRequired()
    {
        $this->loginAsJane();
        $data = array(
            'FirstName' => 'Joe',
            'Email' => 'joe@example.com',
            'Groups' => array('test1', 'test2')
        );
        $form = $this->controller->InvitationForm()->loadDataFrom($data);
        $this->assertTrue($form->validate());

        Config::inst()->update('UserInvitation', 'force_require_group', true);
        unset($data['Groups']);
        $form = $this->controller->InvitationForm()->loadDataFrom($data);
        $this->assertFalse($form->validate());
    }

    private function loginAsJane()
    {
        $this->logInAs($this->objFromFixture('Member', 'jane'));
    }

    /**
     * Tests for 403 if no ID parameter given
     */
    public function testAcceptForbiddenError()
    {
        $response = $this->get($this->controller->Link('accept'));
        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * Tests for expired TempHash and that it redirects to the expired page
     */
    public function testAcceptExpiredTempHash()
    {
        /** @var UserInvitation $invitation */
        $invitation = $this->objFromFixture('UserInvitation', 'expired');
        $response = $this->get($this->controller->Link('accept/' . $invitation->TempHash));
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/user/expired', $response->getHeader('Location'));
    }

    /**
     * Tests if a form is returned and that expected fields are present
     */
    public function testAcceptForm()
    {
        $form = $this->controller->AcceptForm();
        $this->assertInstanceOf('Form', $form);
        $this->assertNotNull($form->Fields()->fieldByName('FirstName'));
        $this->assertNull($form->Fields()->fieldByName('Email'));
        $this->assertNotNull($form->Fields()->fieldByName('HashID'));
        $this->assertNotNull($form->Fields()->fieldByName('Surname'));
    }

    /**
     * Tests that redirected to not found if has not found
     */
    public function testSaveInviteWrongHashError()
    {
        $data = array(
            'HashID' => '432'
        );
        $response = $this->controller->saveInvite($data, $this->controller->AcceptForm()->loadDataFrom($data));
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/user/notfound', $response->getHeader('Location'));
    }

    public function testSaveInvite()
    {
        /** @var UserInvitation $invitation */
        $invitation = $this->objFromFixture('UserInvitation', 'joe');
        $data = array(
            'HashID' => $invitation->TempHash,
            'FirstName' => $invitation->FirstName,
            'Surname' => 'Soap',
            'Password' => array(
                '_Password' => 'password',
                '_ConfirmPassword' => 'password'
            )
        );

        $response = $this->controller->saveInvite($data, $this->controller->AcceptForm()->loadDataFrom($data));
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/user/success', $response->getHeader('Location'));

        // Assert that invitation is deleted
        $this->assertNull(UserInvitation::get()->filter('Email', $invitation->Email)->first());

        /** @var Member $joe */
        $joe = Member::get()->filter('Email', $invitation->Email)->first();
        // Assert that member is created
        $this->assertTrue($joe->exists());

        // Assert that member belongs to the groups selected
        $this->assertTrue($joe->inGroup($this->objFromFixture('Group', 'test1')));
        $this->assertTrue($joe->inGroup($this->objFromFixture('Group', 'test2')));
    }

    /**
     * Tests that a login link is presented to the user
     */
    public function testSuccess()
    {
        $this->logoutMember();
        $response = $this->get($this->controller->Link('success'));
        $body = Convert::nl2os($response->getBody(), '');
        $this->assertContains('Congratulations!', $body);
        $this->assertContains('You are now registered member', $body);
        $baseURL = Director::absoluteBaseURL();
        $this->assertContains("<a href=\"{$baseURL}//Security/login?BackURL=/\">", $body);
    }

    /**
     * Tests that the expired action is shown
     */
    public function testExpired()
    {
        $this->logoutMember();
        $response = $this->get($this->controller->Link('expired'));
        $body = Convert::nl2os($response->getBody(), '');
        $this->assertContains('Invitation expired', $body);
        $this->assertContains('Oops, you took too long to accept this invitation', $body);
    }

    /**
     * Tests that the notfound action is shown.
     */
    public function testNotFound()
    {
        $this->logoutMember();
        $response = $this->get($this->controller->Link('notfound'));
        $body = Convert::nl2os($response->getBody(), '');
        $this->assertContains('Invitation not found', $body);
        $this->assertContains('Oops, the invitation ID was not found.', $body);
    }

    /**
     * Tests whether links are correctly re-written.
     */
    public function testLink()
    {
        $this->assertEquals('user/accept', $this->controller->Link('accept'));
        $this->assertEquals('user/index', $this->controller->Link('index'));
    }
}
